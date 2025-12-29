<?php
/**
 * Opportunity synchronization service.
 *
 * @package BookingX\Salesforce
 */

namespace BookingX\Salesforce\Services;

defined( 'ABSPATH' ) || exit;

/**
 * OpportunitySync class.
 */
class OpportunitySync {

	/**
	 * Salesforce API instance.
	 *
	 * @var SalesforceApi
	 */
	private $api;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param SalesforceApi $api      Salesforce API instance.
	 * @param array         $settings Plugin settings.
	 */
	public function __construct( SalesforceApi $api, array $settings ) {
		$this->api      = $api;
		$this->settings = $settings;
	}

	/**
	 * Create an Opportunity from a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|\WP_Error Salesforce Opportunity ID or error.
	 */
	public function create_from_booking( $booking_id ) {
		// Get booking data.
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error( 'invalid_booking', __( 'Invalid booking', 'bkx-salesforce' ) );
		}

		// Check if already synced.
		$existing_sf_id = $this->get_sf_opportunity_for_booking( $booking_id );
		if ( $existing_sf_id ) {
			return $this->update_from_booking( $booking_id );
		}

		// Get or create the associated Contact.
		$contact_id = $this->get_contact_for_booking( $booking_id );

		// Build opportunity data.
		$opp_data = $this->build_opportunity_data( $booking_id, $contact_id );

		// Create opportunity.
		$result = $this->api->create_record( 'Opportunity', $opp_data );

		if ( is_wp_error( $result ) ) {
			$this->log_error( 'create', $booking_id, null, $result->get_error_message() );
			return $result;
		}

		$sf_id = $result['id'] ?? null;

		if ( $sf_id ) {
			$this->save_mapping( $booking_id, $sf_id );
			$this->log_success( 'create', $booking_id, $sf_id );

			// Create OpportunityContactRole to link Contact.
			if ( $contact_id ) {
				$this->create_contact_role( $sf_id, $contact_id );
			}
		}

		return $sf_id;
	}

	/**
	 * Update an Opportunity from a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|\WP_Error Salesforce Opportunity ID or error.
	 */
	public function update_from_booking( $booking_id ) {
		$sf_id = $this->get_sf_opportunity_for_booking( $booking_id );

		if ( ! $sf_id ) {
			return $this->create_from_booking( $booking_id );
		}

		// Build opportunity data.
		$opp_data = $this->build_opportunity_data( $booking_id );

		// Update opportunity.
		$result = $this->api->update_record( 'Opportunity', $sf_id, $opp_data );

		if ( is_wp_error( $result ) ) {
			$this->log_error( 'update', $booking_id, $sf_id, $result->get_error_message() );
			return $result;
		}

		$this->log_success( 'update', $booking_id, $sf_id );
		return $sf_id;
	}

	/**
	 * Update Opportunity stage based on booking status.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New booking status.
	 * @return bool|\WP_Error
	 */
	public function update_stage_from_status( $booking_id, $new_status ) {
		$sf_id = $this->get_sf_opportunity_for_booking( $booking_id );

		if ( ! $sf_id ) {
			return false;
		}

		$sf_stage = $this->map_booking_status_to_stage( $new_status );

		$update_data = array(
			'StageName' => $sf_stage,
		);

		// If closed won, set close date to today.
		if ( 'Closed Won' === $sf_stage ) {
			$update_data['CloseDate'] = gmdate( 'Y-m-d' );
		}

		$result = $this->api->update_record( 'Opportunity', $sf_id, $update_data );

		if ( is_wp_error( $result ) ) {
			$this->log_error( 'update_stage', $booking_id, $sf_id, $result->get_error_message() );
			return $result;
		}

		$this->log_success( 'update_stage', $booking_id, $sf_id );
		return true;
	}

	/**
	 * Sync all bookings as Opportunities.
	 *
	 * @param int $limit Maximum records to sync.
	 * @return int Number of records synced.
	 */
	public function sync_all( $limit = 100 ) {
		global $wpdb;
		$mapping_table = $wpdb->prefix . 'bkx_sf_mappings';

		// Get bookings not yet synced as Opportunities.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$booking_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				LEFT JOIN {$mapping_table} m ON m.wp_object_id = p.ID
					AND m.wp_object_type = 'booking'
					AND m.sf_object_type = 'Opportunity'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status NOT IN ('trash', 'auto-draft')
				AND m.id IS NULL
				ORDER BY p.post_date DESC
				LIMIT %d",
				$limit
			)
		);

		$synced = 0;

		foreach ( $booking_ids as $booking_id ) {
			$result = $this->create_from_booking( $booking_id );
			if ( ! is_wp_error( $result ) ) {
				$synced++;
			}
		}

		return $synced;
	}

	/**
	 * Process incoming webhook from Salesforce.
	 *
	 * @param string $event Event type (created, updated, deleted).
	 * @param array  $data  Opportunity data.
	 */
	public function process_webhook( $event, $data ) {
		$sf_id = $data['Id'] ?? null;

		if ( ! $sf_id ) {
			return;
		}

		switch ( $event ) {
			case 'updated':
				// Sync stage changes back to WordPress.
				$this->sync_stage_to_wp( $sf_id, $data );
				break;

			case 'deleted':
				$this->remove_mapping_by_sf_id( $sf_id );
				break;
		}
	}

	/**
	 * Build opportunity data from booking.
	 *
	 * @param int         $booking_id Booking ID.
	 * @param string|null $contact_id Salesforce Contact ID.
	 * @return array Opportunity data for Salesforce.
	 */
	private function build_opportunity_data( $booking_id, $contact_id = null ) {
		$booking = get_post( $booking_id );

		// Get service name.
		$base_id      = get_post_meta( $booking_id, 'base_id', true );
		$service_name = $base_id ? get_the_title( $base_id ) : 'Booking';

		// Get staff name.
		$seat_id    = get_post_meta( $booking_id, 'seat_id', true );
		$staff_name = $seat_id ? get_the_title( $seat_id ) : '';

		// Get booking date.
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$close_date   = $booking_date ? gmdate( 'Y-m-d', strtotime( $booking_date ) ) : gmdate( 'Y-m-d' );

		// Get amount.
		$total = get_post_meta( $booking_id, 'total_amount', true );
		$amount = $total ? (float) $total : 0;

		// Get customer name.
		$customer_first = get_post_meta( $booking_id, 'customer_first_name', true );
		$customer_last  = get_post_meta( $booking_id, 'customer_last_name', true );
		$customer_name  = trim( $customer_first . ' ' . $customer_last ) ?: 'Customer';

		// Build name.
		$opp_name = sprintf(
			'%s - %s (#%d)',
			$service_name,
			$customer_name,
			$booking_id
		);

		// Map booking status to stage.
		$booking_status = get_post_status( $booking_id );
		$stage          = $this->map_booking_status_to_stage( $booking_status );

		$data = array(
			'Name'        => $opp_name,
			'StageName'   => $stage,
			'CloseDate'   => $close_date,
			'Amount'      => $amount,
			'Description' => $this->build_description( $booking_id ),
		);

		// Add custom fields from mappings.
		$field_mappings = $this->get_field_mappings();
		foreach ( $field_mappings as $mapping ) {
			if ( 'Opportunity' !== $mapping->object_type ) {
				continue;
			}

			if ( ! in_array( $mapping->sync_direction, array( 'both', 'wp_to_sf' ), true ) ) {
				continue;
			}

			$wp_value = get_post_meta( $booking_id, $mapping->wp_field, true );
			if ( '' !== $wp_value ) {
				$data[ $mapping->sf_field ] = $wp_value;
			}
		}

		return $data;
	}

	/**
	 * Build description for Opportunity.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string Description text.
	 */
	private function build_description( $booking_id ) {
		$lines = array();

		// Booking ID.
		$lines[] = sprintf( 'BookingX ID: #%d', $booking_id );

		// Service.
		$base_id = get_post_meta( $booking_id, 'base_id', true );
		if ( $base_id ) {
			$lines[] = sprintf( 'Service: %s', get_the_title( $base_id ) );
		}

		// Staff.
		$seat_id = get_post_meta( $booking_id, 'seat_id', true );
		if ( $seat_id ) {
			$lines[] = sprintf( 'Staff: %s', get_the_title( $seat_id ) );
		}

		// Date/Time.
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time = get_post_meta( $booking_id, 'booking_time', true );
		if ( $booking_date ) {
			$datetime = $booking_date;
			if ( $booking_time ) {
				$datetime .= ' ' . $booking_time;
			}
			$lines[] = sprintf( 'Date/Time: %s', $datetime );
		}

		// Notes.
		$notes = get_post_meta( $booking_id, 'booking_notes', true );
		if ( $notes ) {
			$lines[] = '';
			$lines[] = 'Notes:';
			$lines[] = $notes;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Map booking status to Opportunity stage.
	 *
	 * @param string $booking_status Booking status.
	 * @return string Salesforce Opportunity stage.
	 */
	private function map_booking_status_to_stage( $booking_status ) {
		$stage_map = array(
			'bkx-pending'   => $this->settings['default_opp_stage'] ?? 'Prospecting',
			'bkx-ack'       => 'Qualification',
			'bkx-completed' => 'Closed Won',
			'bkx-cancelled' => 'Closed Lost',
			'bkx-missed'    => 'Closed Lost',
		);

		return $stage_map[ $booking_status ] ?? 'Prospecting';
	}

	/**
	 * Get Contact ID for a booking's customer.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|null Salesforce Contact ID or null.
	 */
	private function get_contact_for_booking( $booking_id ) {
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		if ( empty( $customer_email ) ) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT sf_object_id FROM {$table}
				WHERE wp_object_type = 'customer_email'
				AND wp_object_id = %s
				AND sf_object_type = 'Contact'",
				$customer_email
			)
		);
	}

	/**
	 * Create OpportunityContactRole to link Contact to Opportunity.
	 *
	 * @param string $opp_id     Opportunity ID.
	 * @param string $contact_id Contact ID.
	 */
	private function create_contact_role( $opp_id, $contact_id ) {
		$this->api->create_record(
			'OpportunityContactRole',
			array(
				'OpportunityId' => $opp_id,
				'ContactId'     => $contact_id,
				'IsPrimary'     => true,
				'Role'          => 'Decision Maker',
			)
		);
	}

	/**
	 * Sync stage changes from Salesforce back to WordPress.
	 *
	 * @param string $sf_id Salesforce Opportunity ID.
	 * @param array  $data  Opportunity data.
	 */
	private function sync_stage_to_wp( $sf_id, $data ) {
		$booking_id = $this->get_booking_for_sf_opportunity( $sf_id );

		if ( ! $booking_id ) {
			return;
		}

		$sf_stage = $data['StageName'] ?? null;
		if ( ! $sf_stage ) {
			return;
		}

		$wp_status = $this->map_stage_to_booking_status( $sf_stage );

		$current_status = get_post_status( $booking_id );
		if ( $current_status !== $wp_status ) {
			wp_update_post(
				array(
					'ID'          => $booking_id,
					'post_status' => $wp_status,
				)
			);

			// Log the sync.
			$addon = \BookingX\Salesforce\SalesforceAddon::get_instance();
			$addon->log_sync(
				'sf_to_wp',
				'stage_sync',
				'booking',
				$booking_id,
				'Opportunity',
				$sf_id,
				'success',
				"Stage {$sf_stage} synced to status {$wp_status}"
			);
		}
	}

	/**
	 * Map Salesforce stage to booking status.
	 *
	 * @param string $sf_stage Salesforce stage.
	 * @return string WordPress booking status.
	 */
	private function map_stage_to_booking_status( $sf_stage ) {
		$status_map = array(
			'Prospecting'         => 'bkx-pending',
			'Qualification'       => 'bkx-ack',
			'Needs Analysis'      => 'bkx-ack',
			'Value Proposition'   => 'bkx-ack',
			'Id. Decision Makers' => 'bkx-ack',
			'Perception Analysis' => 'bkx-ack',
			'Proposal/Price Quote' => 'bkx-ack',
			'Negotiation/Review'  => 'bkx-ack',
			'Closed Won'          => 'bkx-completed',
			'Closed Lost'         => 'bkx-cancelled',
		);

		return $status_map[ $sf_stage ] ?? 'bkx-pending';
	}

	/**
	 * Get field mappings for Opportunity.
	 *
	 * @return array
	 */
	private function get_field_mappings() {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_field_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE object_type = 'Opportunity' AND is_active = 1"
		);
	}

	/**
	 * Get Salesforce Opportunity ID for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|null Salesforce Opportunity ID or null.
	 */
	private function get_sf_opportunity_for_booking( $booking_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT sf_object_id FROM {$table}
				WHERE wp_object_type = 'booking'
				AND wp_object_id = %d
				AND sf_object_type = 'Opportunity'",
				$booking_id
			)
		);
	}

	/**
	 * Get booking ID for a Salesforce Opportunity.
	 *
	 * @param string $sf_id Salesforce Opportunity ID.
	 * @return int|null Booking ID or null.
	 */
	private function get_booking_for_sf_opportunity( $sf_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT wp_object_id FROM {$table}
				WHERE sf_object_type = 'Opportunity'
				AND sf_object_id = %s
				AND wp_object_type = 'booking'",
				$sf_id
			)
		);
	}

	/**
	 * Save booking to Salesforce ID mapping.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $sf_id      Salesforce Opportunity ID.
	 */
	private function save_mapping( $booking_id, $sf_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$table,
			array(
				'wp_object_type' => 'booking',
				'wp_object_id'   => $booking_id,
				'sf_object_type' => 'Opportunity',
				'sf_object_id'   => $sf_id,
				'sync_status'    => 'synced',
				'last_sync'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Remove mapping by Salesforce ID.
	 *
	 * @param string $sf_id Salesforce Opportunity ID.
	 */
	private function remove_mapping_by_sf_id( $sf_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$table,
			array(
				'sf_object_type' => 'Opportunity',
				'sf_object_id'   => $sf_id,
			),
			array( '%s', '%s' )
		);
	}

	/**
	 * Log successful sync.
	 *
	 * @param string      $action     Action performed.
	 * @param int         $booking_id Booking ID.
	 * @param string|null $sf_id      Salesforce Opportunity ID.
	 */
	private function log_success( $action, $booking_id, $sf_id ) {
		$addon = \BookingX\Salesforce\SalesforceAddon::get_instance();
		$addon->log_sync(
			'wp_to_sf',
			$action . '_opportunity',
			'booking',
			$booking_id,
			'Opportunity',
			$sf_id,
			'success',
			sprintf( 'Opportunity %s successfully', $action )
		);
	}

	/**
	 * Log sync error.
	 *
	 * @param string      $action     Action attempted.
	 * @param int         $booking_id Booking ID.
	 * @param string|null $sf_id      Salesforce Opportunity ID.
	 * @param string      $message    Error message.
	 */
	private function log_error( $action, $booking_id, $sf_id, $message ) {
		$addon = \BookingX\Salesforce\SalesforceAddon::get_instance();
		$addon->log_sync(
			'wp_to_sf',
			$action . '_opportunity',
			'booking',
			$booking_id,
			'Opportunity',
			$sf_id,
			'error',
			$message
		);
	}
}

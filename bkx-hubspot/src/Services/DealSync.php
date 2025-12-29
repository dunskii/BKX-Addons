<?php
/**
 * Deal synchronization service.
 *
 * @package BookingX\HubSpot
 */

namespace BookingX\HubSpot\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DealSync class.
 */
class DealSync {

	/**
	 * HubSpot API instance.
	 *
	 * @var HubSpotApi
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
	 * @param HubSpotApi $api      HubSpot API instance.
	 * @param array      $settings Plugin settings.
	 */
	public function __construct( HubSpotApi $api, array $settings ) {
		$this->api      = $api;
		$this->settings = $settings;
	}

	/**
	 * Create a Deal from a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|\WP_Error HubSpot Deal ID or error.
	 */
	public function create_from_booking( $booking_id ) {
		// Get booking data.
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error( 'invalid_booking', __( 'Invalid booking', 'bkx-hubspot' ) );
		}

		// Check if already synced.
		$existing_hs_id = $this->get_hs_deal_for_booking( $booking_id );
		if ( $existing_hs_id ) {
			return $this->update_from_booking( $booking_id );
		}

		// Get or create the associated Contact.
		$contact_id = $this->get_contact_for_booking( $booking_id );

		// Build deal properties.
		$properties = $this->build_deal_properties( $booking_id );

		// Create deal.
		$result = $this->api->create_deal( $properties );

		if ( is_wp_error( $result ) ) {
			$this->log_error( 'create', $booking_id, null, $result->get_error_message() );
			return $result;
		}

		$hs_id = $result['id'] ?? null;

		if ( $hs_id ) {
			$this->save_mapping( $booking_id, $hs_id );
			$this->log_success( 'create', $booking_id, $hs_id );

			// Associate with contact.
			if ( $contact_id ) {
				$this->api->associate_deal_contact( $hs_id, $contact_id );
			}

			// Log activity if enabled.
			if ( ! empty( $this->settings['track_activities'] ) && $contact_id ) {
				$this->log_booking_activity( $contact_id, $booking_id );
			}
		}

		return $hs_id;
	}

	/**
	 * Update a Deal from a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|\WP_Error HubSpot Deal ID or error.
	 */
	public function update_from_booking( $booking_id ) {
		$hs_id = $this->get_hs_deal_for_booking( $booking_id );

		if ( ! $hs_id ) {
			return $this->create_from_booking( $booking_id );
		}

		// Build deal properties.
		$properties = $this->build_deal_properties( $booking_id );

		// Update deal.
		$result = $this->api->update_deal( $hs_id, $properties );

		if ( is_wp_error( $result ) ) {
			$this->log_error( 'update', $booking_id, $hs_id, $result->get_error_message() );
			return $result;
		}

		$this->log_success( 'update', $booking_id, $hs_id );
		return $hs_id;
	}

	/**
	 * Update Deal stage based on booking status.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New booking status.
	 * @return bool|\WP_Error
	 */
	public function update_stage_from_status( $booking_id, $new_status ) {
		$hs_id = $this->get_hs_deal_for_booking( $booking_id );

		if ( ! $hs_id ) {
			return false;
		}

		$stage_id = $this->map_booking_status_to_stage( $new_status );

		if ( ! $stage_id ) {
			return false;
		}

		$result = $this->api->update_deal( $hs_id, array( 'dealstage' => $stage_id ) );

		if ( is_wp_error( $result ) ) {
			$this->log_error( 'update_stage', $booking_id, $hs_id, $result->get_error_message() );
			return $result;
		}

		$this->log_success( 'update_stage', $booking_id, $hs_id );
		return true;
	}

	/**
	 * Sync all bookings as Deals.
	 *
	 * @param int $limit Maximum records to sync.
	 * @return int Number of records synced.
	 */
	public function sync_all( $limit = 100 ) {
		global $wpdb;
		$mapping_table = $wpdb->prefix . 'bkx_hs_mappings';

		// Get bookings not yet synced as Deals.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$booking_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				LEFT JOIN {$mapping_table} m ON m.wp_object_id = p.ID
					AND m.wp_object_type = 'booking'
					AND m.hs_object_type = 'deal'
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
	 * Process incoming webhook.
	 *
	 * @param string $event_type Event type.
	 * @param string $object_id  Object ID.
	 */
	public function process_webhook( $event_type, $object_id ) {
		switch ( $event_type ) {
			case 'deal.propertyChange':
				$this->sync_stage_to_wp( $object_id );
				break;

			case 'deal.deletion':
				$this->remove_mapping_by_hs_id( $object_id );
				break;
		}
	}

	/**
	 * Build deal properties from booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array Deal properties for HubSpot.
	 */
	private function build_deal_properties( $booking_id ) {
		$booking = get_post( $booking_id );

		// Get service name.
		$base_id      = get_post_meta( $booking_id, 'base_id', true );
		$service_name = $base_id ? get_the_title( $base_id ) : 'Booking';

		// Get customer name.
		$customer_first = get_post_meta( $booking_id, 'customer_first_name', true );
		$customer_last  = get_post_meta( $booking_id, 'customer_last_name', true );
		$customer_name  = trim( $customer_first . ' ' . $customer_last ) ?: 'Customer';

		// Build deal name.
		$deal_name = sprintf(
			'%s - %s (#%d)',
			$service_name,
			$customer_name,
			$booking_id
		);

		// Get booking date as close date.
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$close_date   = $booking_date ? strtotime( $booking_date ) * 1000 : time() * 1000;

		// Get amount.
		$total  = get_post_meta( $booking_id, 'total_amount', true );
		$amount = $total ? (float) $total : 0;

		// Map booking status to stage.
		$booking_status = get_post_status( $booking_id );
		$stage_id       = $this->map_booking_status_to_stage( $booking_status );

		$properties = array(
			'dealname'  => $deal_name,
			'amount'    => $amount,
			'closedate' => $close_date,
		);

		// Add stage if mapped.
		if ( $stage_id ) {
			$properties['dealstage'] = $stage_id;
		}

		// Add pipeline if configured.
		if ( ! empty( $this->settings['pipeline_id'] ) ) {
			$properties['pipeline'] = $this->settings['pipeline_id'];
		}

		// Add custom fields from mappings.
		$field_mappings = $this->get_property_mappings();
		foreach ( $field_mappings as $mapping ) {
			if ( 'deal' !== $mapping->object_type ) {
				continue;
			}

			if ( ! in_array( $mapping->sync_direction, array( 'both', 'wp_to_hs' ), true ) ) {
				continue;
			}

			$wp_value = get_post_meta( $booking_id, $mapping->wp_field, true );
			if ( '' !== $wp_value ) {
				$properties[ $mapping->hs_property ] = $wp_value;
			}
		}

		return $properties;
	}

	/**
	 * Map booking status to Deal stage ID.
	 *
	 * @param string $booking_status Booking status.
	 * @return string|null Stage ID or null.
	 */
	private function map_booking_status_to_stage( $booking_status ) {
		// If no default stage set, return null.
		if ( empty( $this->settings['default_stage_id'] ) ) {
			return null;
		}

		// Standard HubSpot deal stages (default pipeline).
		$stage_map = array(
			'bkx-pending'   => 'appointmentscheduled',  // Appointment Scheduled.
			'bkx-ack'       => 'qualifiedtobuy',        // Qualified to Buy.
			'bkx-completed' => 'closedwon',             // Closed Won.
			'bkx-cancelled' => 'closedlost',            // Closed Lost.
			'bkx-missed'    => 'closedlost',            // Closed Lost.
		);

		// Use default stage for pending if no explicit mapping.
		if ( 'bkx-pending' === $booking_status ) {
			return $this->settings['default_stage_id'];
		}

		return $stage_map[ $booking_status ] ?? $this->settings['default_stage_id'];
	}

	/**
	 * Get Contact ID for a booking's customer.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|null HubSpot Contact ID or null.
	 */
	private function get_contact_for_booking( $booking_id ) {
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		if ( empty( $customer_email ) ) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT hs_object_id FROM {$table}
				WHERE wp_object_type = 'customer_email'
				AND wp_object_id = %s
				AND hs_object_type = 'contact'",
				$customer_email
			)
		);
	}

	/**
	 * Log booking activity to HubSpot contact.
	 *
	 * @param string $contact_id HubSpot Contact ID.
	 * @param int    $booking_id Booking ID.
	 */
	private function log_booking_activity( $contact_id, $booking_id ) {
		$base_id      = get_post_meta( $booking_id, 'base_id', true );
		$service_name = $base_id ? get_the_title( $base_id ) : 'Booking';
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time = get_post_meta( $booking_id, 'booking_time', true );

		$body = sprintf(
			"New booking created:\n- Service: %s\n- Date: %s %s\n- Booking ID: #%d",
			$service_name,
			$booking_date,
			$booking_time,
			$booking_id
		);

		$this->api->create_engagement(
			$contact_id,
			'NOTE',
			array( 'body' => $body )
		);
	}

	/**
	 * Sync stage changes from HubSpot back to WordPress.
	 *
	 * @param string $deal_id HubSpot Deal ID.
	 */
	private function sync_stage_to_wp( $deal_id ) {
		$booking_id = $this->get_booking_for_hs_deal( $deal_id );

		if ( ! $booking_id ) {
			return;
		}

		// Get deal from HubSpot.
		$deal = $this->api->request( 'GET', "/crm/v3/objects/deals/{$deal_id}?properties=dealstage" );

		if ( is_wp_error( $deal ) ) {
			return;
		}

		$stage_id = $deal['properties']['dealstage'] ?? null;
		if ( ! $stage_id ) {
			return;
		}

		$wp_status = $this->map_stage_to_booking_status( $stage_id );

		$current_status = get_post_status( $booking_id );
		if ( $current_status !== $wp_status ) {
			wp_update_post(
				array(
					'ID'          => $booking_id,
					'post_status' => $wp_status,
				)
			);

			// Log the sync.
			$addon = \BookingX\HubSpot\HubSpotAddon::get_instance();
			$addon->log_sync(
				'hs_to_wp',
				'stage_sync',
				'booking',
				$booking_id,
				'deal',
				$deal_id,
				'success',
				"Stage {$stage_id} synced to status {$wp_status}"
			);
		}
	}

	/**
	 * Map HubSpot stage to booking status.
	 *
	 * @param string $stage_id HubSpot stage ID.
	 * @return string WordPress booking status.
	 */
	private function map_stage_to_booking_status( $stage_id ) {
		$status_map = array(
			'appointmentscheduled'      => 'bkx-pending',
			'qualifiedtobuy'            => 'bkx-ack',
			'presentationscheduled'     => 'bkx-ack',
			'decisionmakerboughtin'     => 'bkx-ack',
			'contractsent'              => 'bkx-ack',
			'closedwon'                 => 'bkx-completed',
			'closedlost'                => 'bkx-cancelled',
		);

		return $status_map[ $stage_id ] ?? 'bkx-pending';
	}

	/**
	 * Get property mappings.
	 *
	 * @return array
	 */
	private function get_property_mappings() {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_property_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE object_type = 'deal' AND is_active = 1"
		);
	}

	/**
	 * Get HubSpot Deal ID for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|null HubSpot Deal ID or null.
	 */
	private function get_hs_deal_for_booking( $booking_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT hs_object_id FROM {$table}
				WHERE wp_object_type = 'booking'
				AND wp_object_id = %d
				AND hs_object_type = 'deal'",
				$booking_id
			)
		);
	}

	/**
	 * Get booking ID for a HubSpot Deal.
	 *
	 * @param string $deal_id HubSpot Deal ID.
	 * @return int|null Booking ID or null.
	 */
	private function get_booking_for_hs_deal( $deal_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT wp_object_id FROM {$table}
				WHERE hs_object_type = 'deal'
				AND hs_object_id = %s
				AND wp_object_type = 'booking'",
				$deal_id
			)
		);
	}

	/**
	 * Save booking to HubSpot ID mapping.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $hs_id      HubSpot Deal ID.
	 */
	private function save_mapping( $booking_id, $hs_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$table,
			array(
				'wp_object_type' => 'booking',
				'wp_object_id'   => $booking_id,
				'hs_object_type' => 'deal',
				'hs_object_id'   => $hs_id,
				'sync_status'    => 'synced',
				'last_sync'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Remove mapping by HubSpot ID.
	 *
	 * @param string $hs_id HubSpot Deal ID.
	 */
	private function remove_mapping_by_hs_id( $hs_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$table,
			array(
				'hs_object_type' => 'deal',
				'hs_object_id'   => $hs_id,
			),
			array( '%s', '%s' )
		);
	}

	/**
	 * Log successful sync.
	 *
	 * @param string      $action     Action performed.
	 * @param int         $booking_id Booking ID.
	 * @param string|null $hs_id      HubSpot Deal ID.
	 */
	private function log_success( $action, $booking_id, $hs_id ) {
		$addon = \BookingX\HubSpot\HubSpotAddon::get_instance();
		$addon->log_sync(
			'wp_to_hs',
			$action . '_deal',
			'booking',
			$booking_id,
			'deal',
			$hs_id,
			'success',
			sprintf( 'Deal %s successfully', $action )
		);
	}

	/**
	 * Log sync error.
	 *
	 * @param string      $action     Action attempted.
	 * @param int         $booking_id Booking ID.
	 * @param string|null $hs_id      HubSpot Deal ID.
	 * @param string      $message    Error message.
	 */
	private function log_error( $action, $booking_id, $hs_id, $message ) {
		$addon = \BookingX\HubSpot\HubSpotAddon::get_instance();
		$addon->log_sync(
			'wp_to_hs',
			$action . '_deal',
			'booking',
			$booking_id,
			'deal',
			$hs_id,
			'error',
			$message
		);
	}
}

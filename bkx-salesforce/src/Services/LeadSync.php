<?php
/**
 * Lead synchronization service.
 *
 * @package BookingX\Salesforce
 */

namespace BookingX\Salesforce\Services;

defined( 'ABSPATH' ) || exit;

/**
 * LeadSync class.
 */
class LeadSync {

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
	 * Sync a booking's customer as Salesforce Lead.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|\WP_Error Salesforce Lead ID or error.
	 */
	public function sync_from_booking( $booking_id ) {
		// Get booking data.
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error( 'invalid_booking', __( 'Invalid booking', 'bkx-salesforce' ) );
		}

		// Get customer email.
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		if ( empty( $customer_email ) ) {
			return new \WP_Error( 'no_email', __( 'No customer email found', 'bkx-salesforce' ) );
		}

		// Check if already synced as Lead.
		$existing_sf_id = $this->get_sf_lead_id_for_email( $customer_email );

		// Build lead data.
		$lead_data = $this->build_lead_data( $booking_id );

		if ( $existing_sf_id ) {
			// Update existing lead.
			$result = $this->api->update_record( 'Lead', $existing_sf_id, $lead_data );

			if ( is_wp_error( $result ) ) {
				$this->log_error( 'update', $booking_id, $existing_sf_id, $result->get_error_message() );
				return $result;
			}

			$this->log_success( 'update', $booking_id, $existing_sf_id );
			return $existing_sf_id;
		} else {
			// Create new lead.
			$result = $this->api->create_record( 'Lead', $lead_data );

			if ( is_wp_error( $result ) ) {
				$this->log_error( 'create', $booking_id, null, $result->get_error_message() );
				return $result;
			}

			$sf_id = $result['id'] ?? null;

			if ( $sf_id ) {
				$this->save_mapping( $customer_email, $sf_id );
				$this->log_success( 'create', $booking_id, $sf_id );
			}

			return $sf_id;
		}
	}

	/**
	 * Sync all customers as leads.
	 *
	 * @param int $limit Maximum records to sync.
	 * @return int Number of records synced.
	 */
	public function sync_all( $limit = 100 ) {
		global $wpdb;

		// Get unique customer emails from bookings that don't have Contact mapping.
		$contact_table = $wpdb->prefix . 'bkx_sf_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$emails = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value
				FROM {$wpdb->postmeta} pm
				JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				LEFT JOIN {$contact_table} cm ON cm.wp_object_id = pm.meta_value
					AND cm.sf_object_type = 'Contact'
				WHERE pm.meta_key = 'customer_email'
				AND p.post_type = 'bkx_booking'
				AND pm.meta_value != ''
				AND cm.id IS NULL
				LIMIT %d",
				$limit
			)
		);

		$synced = 0;

		foreach ( $emails as $email ) {
			$result = $this->sync_customer_by_email( $email );
			if ( ! is_wp_error( $result ) ) {
				$synced++;
			}
		}

		return $synced;
	}

	/**
	 * Sync a customer by email as Lead.
	 *
	 * @param string $email Customer email.
	 * @return string|\WP_Error Salesforce Lead ID or error.
	 */
	public function sync_customer_by_email( $email ) {
		global $wpdb;

		// Get the most recent booking for this email.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$booking_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id
				FROM {$wpdb->postmeta} pm
				JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = 'customer_email'
				AND pm.meta_value = %s
				AND p.post_type = 'bkx_booking'
				ORDER BY p.post_date DESC
				LIMIT 1",
				$email
			)
		);

		if ( ! $booking_id ) {
			return new \WP_Error( 'no_booking', __( 'No booking found for email', 'bkx-salesforce' ) );
		}

		return $this->sync_from_booking( $booking_id );
	}

	/**
	 * Process incoming webhook from Salesforce.
	 *
	 * @param string $event Event type (created, updated, deleted, converted).
	 * @param array  $data  Lead data.
	 */
	public function process_webhook( $event, $data ) {
		$sf_id = $data['Id'] ?? null;
		$email = $data['Email'] ?? null;

		if ( ! $sf_id ) {
			return;
		}

		switch ( $event ) {
			case 'converted':
				// Lead was converted to Contact - update mapping.
				$contact_id = $data['ConvertedContactId'] ?? null;
				if ( $contact_id && $email ) {
					$this->handle_lead_conversion( $email, $sf_id, $contact_id );
				}
				break;

			case 'deleted':
				$this->remove_mapping_by_sf_id( $sf_id );
				break;
		}
	}

	/**
	 * Convert lead status based on booking status.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New booking status.
	 * @return bool|\WP_Error
	 */
	public function update_lead_status( $booking_id, $new_status ) {
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		if ( empty( $customer_email ) ) {
			return false;
		}

		$sf_id = $this->get_sf_lead_id_for_email( $customer_email );
		if ( ! $sf_id ) {
			return false;
		}

		$sf_status = $this->map_booking_status_to_lead_status( $new_status );

		$result = $this->api->update_record(
			'Lead',
			$sf_id,
			array( 'Status' => $sf_status )
		);

		return ! is_wp_error( $result );
	}

	/**
	 * Build lead data from booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array Lead data for Salesforce.
	 */
	private function build_lead_data( $booking_id ) {
		$field_mappings = $this->get_field_mappings();
		$data           = array();

		foreach ( $field_mappings as $mapping ) {
			if ( 'Lead' !== $mapping->object_type ) {
				continue;
			}

			if ( ! in_array( $mapping->sync_direction, array( 'both', 'wp_to_sf' ), true ) ) {
				continue;
			}

			$wp_value = $this->get_wp_field_value( $booking_id, $mapping->wp_field );

			if ( null !== $wp_value ) {
				$data[ $mapping->sf_field ] = $this->transform_value( $wp_value, $mapping->transform );
			}
		}

		// Ensure required fields.
		if ( empty( $data['LastName'] ) ) {
			$data['LastName'] = 'Unknown';
		}

		if ( empty( $data['Company'] ) ) {
			$data['Company'] = 'Individual';
		}

		// Set default status if not set.
		if ( empty( $data['Status'] ) ) {
			$data['Status'] = $this->settings['default_lead_status'] ?? 'Open - Not Contacted';
		}

		// Add lead source.
		$data['LeadSource'] = 'BookingX';

		return $data;
	}

	/**
	 * Get WordPress field value from booking.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $field_name Field name.
	 * @return mixed Field value or null.
	 */
	private function get_wp_field_value( $booking_id, $field_name ) {
		// Direct meta fields.
		$meta_value = get_post_meta( $booking_id, $field_name, true );
		if ( '' !== $meta_value ) {
			return $meta_value;
		}

		// Handle special computed fields.
		switch ( $field_name ) {
			case 'booking_service':
				$base_id = get_post_meta( $booking_id, 'base_id', true );
				if ( $base_id ) {
					return get_the_title( $base_id );
				}
				break;
		}

		return null;
	}

	/**
	 * Transform a value based on transform type.
	 *
	 * @param mixed  $value     Original value.
	 * @param string $transform Transform type.
	 * @return mixed Transformed value.
	 */
	private function transform_value( $value, $transform ) {
		if ( empty( $transform ) ) {
			return $value;
		}

		switch ( $transform ) {
			case 'uppercase':
				return strtoupper( $value );
			case 'lowercase':
				return strtolower( $value );
			case 'ucfirst':
				return ucfirst( strtolower( $value ) );
			default:
				return $value;
		}
	}

	/**
	 * Map booking status to Lead status.
	 *
	 * @param string $booking_status Booking status.
	 * @return string Salesforce Lead status.
	 */
	private function map_booking_status_to_lead_status( $booking_status ) {
		$status_map = array(
			'bkx-pending'   => 'Open - Not Contacted',
			'bkx-ack'       => 'Working - Contacted',
			'bkx-completed' => 'Closed - Converted',
			'bkx-cancelled' => 'Closed - Not Converted',
			'bkx-missed'    => 'Closed - Not Converted',
		);

		return $status_map[ $booking_status ] ?? 'Open - Not Contacted';
	}

	/**
	 * Get field mappings for Lead.
	 *
	 * @return array
	 */
	private function get_field_mappings() {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_field_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE object_type = 'Lead' AND is_active = 1"
		);
	}

	/**
	 * Get Salesforce Lead ID for a customer email.
	 *
	 * @param string $email Customer email.
	 * @return string|null Salesforce Lead ID or null.
	 */
	private function get_sf_lead_id_for_email( $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_mappings';

		// Check local mapping.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$sf_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT sf_object_id FROM {$table}
				WHERE wp_object_type = 'customer_email'
				AND wp_object_id = %s
				AND sf_object_type = 'Lead'",
				$email
			)
		);

		if ( $sf_id ) {
			return $sf_id;
		}

		// Check Salesforce directly.
		$lead = $this->api->find_lead_by_email( $email );
		if ( $lead && ! empty( $lead['Id'] ) ) {
			$this->save_mapping( $email, $lead['Id'] );
			return $lead['Id'];
		}

		return null;
	}

	/**
	 * Save email to Salesforce ID mapping.
	 *
	 * @param string $email Customer email.
	 * @param string $sf_id Salesforce Lead ID.
	 */
	private function save_mapping( $email, $sf_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$table,
			array(
				'wp_object_type' => 'customer_email',
				'wp_object_id'   => $email,
				'sf_object_type' => 'Lead',
				'sf_object_id'   => $sf_id,
				'sync_status'    => 'synced',
				'last_sync'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Handle lead conversion - update mapping from Lead to Contact.
	 *
	 * @param string $email      Customer email.
	 * @param string $lead_id    Salesforce Lead ID.
	 * @param string $contact_id Salesforce Contact ID.
	 */
	private function handle_lead_conversion( $email, $lead_id, $contact_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_mappings';

		// Remove Lead mapping.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$table,
			array(
				'sf_object_type' => 'Lead',
				'sf_object_id'   => $lead_id,
			),
			array( '%s', '%s' )
		);

		// Add Contact mapping.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$table,
			array(
				'wp_object_type' => 'customer_email',
				'wp_object_id'   => $email,
				'sf_object_type' => 'Contact',
				'sf_object_id'   => $contact_id,
				'sync_status'    => 'synced',
				'last_sync'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// Log the conversion.
		$addon = \BookingX\Salesforce\SalesforceAddon::get_instance();
		$addon->log_sync(
			'sf_to_wp',
			'lead_converted',
			'customer_email',
			null,
			'Lead',
			$lead_id,
			'success',
			"Lead converted to Contact {$contact_id}"
		);
	}

	/**
	 * Remove mapping by Salesforce ID.
	 *
	 * @param string $sf_id Salesforce Lead ID.
	 */
	private function remove_mapping_by_sf_id( $sf_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$table,
			array(
				'sf_object_type' => 'Lead',
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
	 * @param string|null $sf_id      Salesforce Lead ID.
	 */
	private function log_success( $action, $booking_id, $sf_id ) {
		$addon = \BookingX\Salesforce\SalesforceAddon::get_instance();
		$addon->log_sync(
			'wp_to_sf',
			$action . '_lead',
			'booking',
			$booking_id,
			'Lead',
			$sf_id,
			'success',
			sprintf( 'Lead %s successfully', $action )
		);
	}

	/**
	 * Log sync error.
	 *
	 * @param string      $action     Action attempted.
	 * @param int         $booking_id Booking ID.
	 * @param string|null $sf_id      Salesforce Lead ID.
	 * @param string      $message    Error message.
	 */
	private function log_error( $action, $booking_id, $sf_id, $message ) {
		$addon = \BookingX\Salesforce\SalesforceAddon::get_instance();
		$addon->log_sync(
			'wp_to_sf',
			$action . '_lead',
			'booking',
			$booking_id,
			'Lead',
			$sf_id,
			'error',
			$message
		);
	}
}

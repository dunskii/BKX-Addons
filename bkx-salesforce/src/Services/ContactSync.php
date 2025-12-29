<?php
/**
 * Contact synchronization service.
 *
 * @package BookingX\Salesforce
 */

namespace BookingX\Salesforce\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ContactSync class.
 */
class ContactSync {

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
	 * Sync a booking's customer to Salesforce Contact.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|\WP_Error Salesforce Contact ID or error.
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

		// Check if already synced.
		$existing_sf_id = $this->get_sf_id_for_email( $customer_email );

		// Build contact data.
		$contact_data = $this->build_contact_data( $booking_id );

		if ( $existing_sf_id ) {
			// Update existing contact.
			$result = $this->api->update_record( 'Contact', $existing_sf_id, $contact_data );

			if ( is_wp_error( $result ) ) {
				$this->log_error( 'update', $booking_id, $existing_sf_id, $result->get_error_message() );
				return $result;
			}

			$this->log_success( 'update', $booking_id, $existing_sf_id );
			return $existing_sf_id;
		} else {
			// Create new contact.
			$result = $this->api->create_record( 'Contact', $contact_data );

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
	 * Sync all customers to Salesforce.
	 *
	 * @param int $limit Maximum records to sync.
	 * @return int Number of records synced.
	 */
	public function sync_all( $limit = 100 ) {
		global $wpdb;

		// Get unique customer emails from bookings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$emails = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value
				FROM {$wpdb->postmeta} pm
				JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = 'customer_email'
				AND p.post_type = 'bkx_booking'
				AND pm.meta_value != ''
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
	 * Sync a customer by email.
	 *
	 * @param string $email Customer email.
	 * @return string|\WP_Error Salesforce Contact ID or error.
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
	 * @param string $event Event type (created, updated, deleted).
	 * @param array  $data  Contact data.
	 */
	public function process_webhook( $event, $data ) {
		$sf_id = $data['Id'] ?? null;
		$email = $data['Email'] ?? null;

		if ( ! $sf_id || ! $email ) {
			return;
		}

		switch ( $event ) {
			case 'created':
			case 'updated':
				$this->update_wp_customer_from_sf( $email, $data );
				break;

			case 'deleted':
				$this->remove_mapping_by_sf_id( $sf_id );
				break;
		}
	}

	/**
	 * Build contact data from booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array Contact data for Salesforce.
	 */
	private function build_contact_data( $booking_id ) {
		$field_mappings = $this->get_field_mappings();
		$data           = array();

		foreach ( $field_mappings as $mapping ) {
			if ( 'Contact' !== $mapping->object_type ) {
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

			case 'booking_date':
				$date = get_post_meta( $booking_id, 'booking_date', true );
				if ( $date ) {
					return gmdate( 'Y-m-d', strtotime( $date ) );
				}
				break;

			case 'booking_total':
				$total = get_post_meta( $booking_id, 'total_amount', true );
				if ( $total ) {
					return (float) $total;
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

			case 'date_iso':
				return gmdate( 'Y-m-d', strtotime( $value ) );

			case 'datetime_iso':
				return gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $value ) );

			case 'float':
				return (float) $value;

			case 'int':
				return (int) $value;

			case 'bool':
				return (bool) $value;

			default:
				return $value;
		}
	}

	/**
	 * Get field mappings for Contact.
	 *
	 * @return array
	 */
	private function get_field_mappings() {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_field_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE object_type = 'Contact' AND is_active = 1"
		);
	}

	/**
	 * Get Salesforce ID for a customer email.
	 *
	 * @param string $email Customer email.
	 * @return string|null Salesforce Contact ID or null.
	 */
	private function get_sf_id_for_email( $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_mappings';

		// First check local mapping.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$sf_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT sf_object_id FROM {$table}
				WHERE wp_object_type = 'customer_email'
				AND wp_object_id = %s
				AND sf_object_type = 'Contact'",
				$email
			)
		);

		if ( $sf_id ) {
			return $sf_id;
		}

		// Check Salesforce directly.
		$contact = $this->api->find_contact_by_email( $email );
		if ( $contact && ! empty( $contact['Id'] ) ) {
			// Save mapping for future use.
			$this->save_mapping( $email, $contact['Id'] );
			return $contact['Id'];
		}

		return null;
	}

	/**
	 * Save email to Salesforce ID mapping.
	 *
	 * @param string $email Customer email.
	 * @param string $sf_id Salesforce Contact ID.
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
				'sf_object_type' => 'Contact',
				'sf_object_id'   => $sf_id,
				'sync_status'    => 'synced',
				'last_sync'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Update WordPress customer data from Salesforce.
	 *
	 * @param string $email Customer email.
	 * @param array  $data  Salesforce Contact data.
	 */
	private function update_wp_customer_from_sf( $email, $data ) {
		global $wpdb;

		// Get bookings with this email.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$booking_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = 'customer_email'
				AND meta_value = %s",
				$email
			)
		);

		$field_mappings = $this->get_field_mappings();

		foreach ( $booking_ids as $booking_id ) {
			foreach ( $field_mappings as $mapping ) {
				if ( ! in_array( $mapping->sync_direction, array( 'both', 'sf_to_wp' ), true ) ) {
					continue;
				}

				if ( isset( $data[ $mapping->sf_field ] ) ) {
					update_post_meta( $booking_id, $mapping->wp_field, sanitize_text_field( $data[ $mapping->sf_field ] ) );
				}
			}
		}
	}

	/**
	 * Remove mapping by Salesforce ID.
	 *
	 * @param string $sf_id Salesforce Contact ID.
	 */
	private function remove_mapping_by_sf_id( $sf_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$table,
			array(
				'sf_object_type' => 'Contact',
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
	 * @param string|null $sf_id      Salesforce Contact ID.
	 */
	private function log_success( $action, $booking_id, $sf_id ) {
		$addon = \BookingX\Salesforce\SalesforceAddon::get_instance();
		$addon->log_sync(
			'wp_to_sf',
			$action . '_contact',
			'booking',
			$booking_id,
			'Contact',
			$sf_id,
			'success',
			sprintf( 'Contact %s successfully', $action )
		);
	}

	/**
	 * Log sync error.
	 *
	 * @param string      $action     Action attempted.
	 * @param int         $booking_id Booking ID.
	 * @param string|null $sf_id      Salesforce Contact ID.
	 * @param string      $message    Error message.
	 */
	private function log_error( $action, $booking_id, $sf_id, $message ) {
		$addon = \BookingX\Salesforce\SalesforceAddon::get_instance();
		$addon->log_sync(
			'wp_to_sf',
			$action . '_contact',
			'booking',
			$booking_id,
			'Contact',
			$sf_id,
			'error',
			$message
		);
	}
}

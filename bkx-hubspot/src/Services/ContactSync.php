<?php
/**
 * Contact synchronization service.
 *
 * @package BookingX\HubSpot
 */

namespace BookingX\HubSpot\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ContactSync class.
 */
class ContactSync {

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
	 * Sync a booking's customer to HubSpot Contact.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|\WP_Error HubSpot Contact ID or error.
	 */
	public function sync_from_booking( $booking_id ) {
		// Get booking data.
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error( 'invalid_booking', __( 'Invalid booking', 'bkx-hubspot' ) );
		}

		// Get customer email.
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		if ( empty( $customer_email ) ) {
			return new \WP_Error( 'no_email', __( 'No customer email found', 'bkx-hubspot' ) );
		}

		// Check if already synced.
		$existing_hs_id = $this->get_hs_id_for_email( $customer_email );

		// Build contact properties.
		$properties = $this->build_contact_properties( $booking_id );

		if ( $existing_hs_id ) {
			// Update existing contact.
			$result = $this->api->update_contact( $existing_hs_id, $properties );

			if ( is_wp_error( $result ) ) {
				$this->log_error( 'update', $booking_id, $existing_hs_id, $result->get_error_message() );
				return $result;
			}

			$this->log_success( 'update', $booking_id, $existing_hs_id );

			// Add to list if configured.
			$this->maybe_add_to_list( $existing_hs_id );

			return $existing_hs_id;
		} else {
			// Create new contact.
			$result = $this->api->create_contact( $properties );

			if ( is_wp_error( $result ) ) {
				$this->log_error( 'create', $booking_id, null, $result->get_error_message() );
				return $result;
			}

			$hs_id = $result['id'] ?? null;

			if ( $hs_id ) {
				$this->save_mapping( $customer_email, $hs_id );
				$this->log_success( 'create', $booking_id, $hs_id );

				// Add to list if configured.
				$this->maybe_add_to_list( $hs_id );
			}

			return $hs_id;
		}
	}

	/**
	 * Sync all customers to HubSpot.
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
	 * @return string|\WP_Error HubSpot Contact ID or error.
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
			return new \WP_Error( 'no_booking', __( 'No booking found for email', 'bkx-hubspot' ) );
		}

		return $this->sync_from_booking( $booking_id );
	}

	/**
	 * Process incoming webhook.
	 *
	 * @param string $event_type Event type.
	 * @param string $object_id  Object ID.
	 */
	public function process_webhook( $event_type, $object_id ) {
		switch ( $event_type ) {
			case 'contact.propertyChange':
			case 'contact.creation':
				$this->sync_from_hubspot( $object_id );
				break;

			case 'contact.deletion':
				$this->remove_mapping_by_hs_id( $object_id );
				break;
		}
	}

	/**
	 * Build contact properties from booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array Contact properties for HubSpot.
	 */
	private function build_contact_properties( $booking_id ) {
		$field_mappings = $this->get_property_mappings();
		$properties     = array();

		foreach ( $field_mappings as $mapping ) {
			if ( 'contact' !== $mapping->object_type ) {
				continue;
			}

			if ( ! in_array( $mapping->sync_direction, array( 'both', 'wp_to_hs' ), true ) ) {
				continue;
			}

			$wp_value = $this->get_wp_field_value( $booking_id, $mapping->wp_field );

			if ( null !== $wp_value && '' !== $wp_value ) {
				$properties[ $mapping->hs_property ] = $this->transform_value( $wp_value, $mapping->transform );
			}
		}

		return $properties;
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
			case 'timestamp':
				return strtotime( $value ) * 1000; // HubSpot uses milliseconds.
			case 'float':
				return (float) $value;
			case 'int':
				return (int) $value;
			default:
				return $value;
		}
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
			"SELECT * FROM {$table} WHERE object_type = 'contact' AND is_active = 1"
		);
	}

	/**
	 * Get HubSpot ID for a customer email.
	 *
	 * @param string $email Customer email.
	 * @return string|null HubSpot Contact ID or null.
	 */
	private function get_hs_id_for_email( $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_mappings';

		// Check local mapping first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$hs_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT hs_object_id FROM {$table}
				WHERE wp_object_type = 'customer_email'
				AND wp_object_id = %s
				AND hs_object_type = 'contact'",
				$email
			)
		);

		if ( $hs_id ) {
			return $hs_id;
		}

		// Check HubSpot directly.
		$contact = $this->api->find_contact_by_email( $email );
		if ( $contact && ! empty( $contact['id'] ) ) {
			$this->save_mapping( $email, $contact['id'] );
			return $contact['id'];
		}

		return null;
	}

	/**
	 * Save email to HubSpot ID mapping.
	 *
	 * @param string $email Customer email.
	 * @param string $hs_id HubSpot Contact ID.
	 */
	private function save_mapping( $email, $hs_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$table,
			array(
				'wp_object_type' => 'customer_email',
				'wp_object_id'   => $email,
				'hs_object_type' => 'contact',
				'hs_object_id'   => $hs_id,
				'sync_status'    => 'synced',
				'last_sync'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Maybe add contact to list.
	 *
	 * @param string $contact_id HubSpot Contact ID.
	 */
	private function maybe_add_to_list( $contact_id ) {
		if ( empty( $this->settings['add_to_list'] ) || empty( $this->settings['list_id'] ) ) {
			return;
		}

		$this->api->add_contact_to_list( $this->settings['list_id'], $contact_id );
	}

	/**
	 * Sync contact data from HubSpot to WordPress.
	 *
	 * @param string $contact_id HubSpot Contact ID.
	 */
	private function sync_from_hubspot( $contact_id ) {
		$contact = $this->api->get_contact( $contact_id, array( 'email', 'firstname', 'lastname', 'phone' ) );

		if ( is_wp_error( $contact ) ) {
			return;
		}

		$email = $contact['properties']['email'] ?? null;
		if ( ! $email ) {
			return;
		}

		// Update WordPress records with this email.
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

		$field_mappings = $this->get_property_mappings();

		foreach ( $booking_ids as $booking_id ) {
			foreach ( $field_mappings as $mapping ) {
				if ( ! in_array( $mapping->sync_direction, array( 'both', 'hs_to_wp' ), true ) ) {
					continue;
				}

				if ( isset( $contact['properties'][ $mapping->hs_property ] ) ) {
					update_post_meta( $booking_id, $mapping->wp_field, sanitize_text_field( $contact['properties'][ $mapping->hs_property ] ) );
				}
			}
		}
	}

	/**
	 * Remove mapping by HubSpot ID.
	 *
	 * @param string $hs_id HubSpot Contact ID.
	 */
	private function remove_mapping_by_hs_id( $hs_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_mappings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$table,
			array(
				'hs_object_type' => 'contact',
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
	 * @param string|null $hs_id      HubSpot Contact ID.
	 */
	private function log_success( $action, $booking_id, $hs_id ) {
		$addon = \BookingX\HubSpot\HubSpotAddon::get_instance();
		$addon->log_sync(
			'wp_to_hs',
			$action . '_contact',
			'booking',
			$booking_id,
			'contact',
			$hs_id,
			'success',
			sprintf( 'Contact %s successfully', $action )
		);
	}

	/**
	 * Log sync error.
	 *
	 * @param string      $action     Action attempted.
	 * @param int         $booking_id Booking ID.
	 * @param string|null $hs_id      HubSpot Contact ID.
	 * @param string      $message    Error message.
	 */
	private function log_error( $action, $booking_id, $hs_id, $message ) {
		$addon = \BookingX\HubSpot\HubSpotAddon::get_instance();
		$addon->log_sync(
			'wp_to_hs',
			$action . '_contact',
			'booking',
			$booking_id,
			'contact',
			$hs_id,
			'error',
			$message
		);
	}
}

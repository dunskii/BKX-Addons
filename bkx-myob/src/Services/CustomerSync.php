<?php
/**
 * Customer Sync Service for MYOB Integration.
 *
 * Handles syncing customers between BookingX and MYOB.
 *
 * @package BookingX\MYOB\Services
 */

namespace BookingX\MYOB\Services;

defined( 'ABSPATH' ) || exit;

/**
 * CustomerSync class.
 */
class CustomerSync {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\MYOB\MYOBAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\MYOB\MYOBAddon $addon Parent addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Sync customer from booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array|\WP_Error
	 */
	public function sync_customer( $booking_id ) {
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error( 'invalid_booking', __( 'Invalid booking.', 'bkx-myob' ) );
		}

		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		if ( empty( $customer_email ) ) {
			return new \WP_Error( 'no_email', __( 'Booking has no customer email.', 'bkx-myob' ) );
		}

		// Check if customer already exists in MYOB.
		$existing = $this->find_customer_by_email( $customer_email );

		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$customer_data = $this->prepare_customer_data( $booking_id );

		if ( $existing ) {
			// Update existing customer.
			$result = $this->update_customer( $existing['UID'], $customer_data, $existing );
		} else {
			// Create new customer.
			$result = $this->create_customer( $customer_data );
		}

		if ( is_wp_error( $result ) ) {
			$this->log_sync( $booking_id, 'customer', null, 'failed', $result->get_error_message() );
			return $result;
		}

		// Store MYOB customer ID.
		$myob_id = $result['UID'] ?? $result['Id'] ?? null;
		if ( $myob_id ) {
			update_post_meta( $booking_id, '_myob_customer_id', $myob_id );
			$this->log_sync( $booking_id, 'customer', $myob_id, 'success' );
		}

		return $result;
	}

	/**
	 * Find customer by email.
	 *
	 * @param string $email Customer email.
	 * @return array|null|\WP_Error
	 */
	public function find_customer_by_email( $email ) {
		$api = $this->addon->get_service( 'api_client' );

		$result = $api->get_customers( array( 'email' => $email ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Check for customers in response.
		$items = $result['Items'] ?? $result['items'] ?? $result;

		if ( ! empty( $items ) && is_array( $items ) ) {
			return $items[0];
		}

		return null;
	}

	/**
	 * Prepare customer data from booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	private function prepare_customer_data( $booking_id ) {
		$customer_name  = get_post_meta( $booking_id, 'customer_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_phone = get_post_meta( $booking_id, 'customer_phone', true );

		// Split name into first and last.
		$name_parts = explode( ' ', $customer_name, 2 );
		$first_name = $name_parts[0] ?? '';
		$last_name  = $name_parts[1] ?? '';

		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );

		if ( 'essentials' === $api_type ) {
			return array(
				'name'        => $customer_name,
				'type'        => 'Customer',
				'email'       => $customer_email,
				'phone1'      => $customer_phone,
				'isActive'    => true,
			);
		}

		// AccountRight format.
		return array(
			'CompanyName'     => '',
			'FirstName'       => $first_name,
			'LastName'        => $last_name,
			'IsIndividual'    => true,
			'IsActive'        => true,
			'Addresses'       => array(
				array(
					'Location' => 1,
					'Email'    => $customer_email,
					'Phone1'   => $customer_phone,
				),
			),
		);
	}

	/**
	 * Create customer in MYOB.
	 *
	 * @param array $data Customer data.
	 * @return array|\WP_Error
	 */
	private function create_customer( $data ) {
		$api = $this->addon->get_service( 'api_client' );
		return $api->create_customer( $data );
	}

	/**
	 * Update customer in MYOB.
	 *
	 * @param string $id       Customer ID.
	 * @param array  $data     New customer data.
	 * @param array  $existing Existing customer data.
	 * @return array|\WP_Error
	 */
	private function update_customer( $id, $data, $existing ) {
		// Merge with existing data to preserve fields.
		$merged = array_merge( $existing, $data );

		// Include RowVersion for optimistic concurrency.
		if ( ! empty( $existing['RowVersion'] ) ) {
			$merged['RowVersion'] = $existing['RowVersion'];
		}

		$api = $this->addon->get_service( 'api_client' );
		return $api->update_customer( $id, $merged );
	}

	/**
	 * Get MYOB customer ID for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|null
	 */
	public function get_myob_customer_id( $booking_id ) {
		$myob_id = get_post_meta( $booking_id, '_myob_customer_id', true );

		if ( ! empty( $myob_id ) ) {
			return $myob_id;
		}

		// Try to find by email.
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		if ( empty( $customer_email ) ) {
			return null;
		}

		$existing = $this->find_customer_by_email( $customer_email );
		if ( $existing && ! is_wp_error( $existing ) ) {
			$myob_id = $existing['UID'] ?? $existing['Id'] ?? null;
			if ( $myob_id ) {
				update_post_meta( $booking_id, '_myob_customer_id', $myob_id );
				return $myob_id;
			}
		}

		return null;
	}

	/**
	 * Log sync operation.
	 *
	 * @param int         $booking_id Booking ID.
	 * @param string      $type       Sync type.
	 * @param string|null $myob_id    MYOB ID.
	 * @param string      $status     Sync status.
	 * @param string|null $error      Error message.
	 */
	private function log_sync( $booking_id, $type, $myob_id, $status, $error = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_myob_sync_log';

		$wpdb->insert(
			$table_name,
			array(
				'booking_id'    => $booking_id,
				'myob_type'     => $type,
				'myob_id'       => $myob_id,
				'sync_status'   => $status,
				'error_message' => $error,
				'synced_at'     => 'success' === $status ? current_time( 'mysql' ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}

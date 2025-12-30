<?php
/**
 * Client Sync Service for FreshBooks Integration.
 *
 * @package BookingX\FreshBooks\Services
 */

namespace BookingX\FreshBooks\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ClientSync class.
 */
class ClientSync {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\FreshBooks\FreshBooksAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\FreshBooks\FreshBooksAddon $addon Parent addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Sync client from booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array|\WP_Error
	 */
	public function sync_client( $booking_id ) {
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error( 'invalid_booking', __( 'Invalid booking.', 'bkx-freshbooks' ) );
		}

		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		if ( empty( $customer_email ) ) {
			return new \WP_Error( 'no_email', __( 'Booking has no customer email.', 'bkx-freshbooks' ) );
		}

		// Check if client already exists.
		$existing = $this->find_client_by_email( $customer_email );

		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$client_data = $this->prepare_client_data( $booking_id );

		if ( $existing ) {
			// Update existing client.
			$result = $this->update_client( $existing['id'], $client_data );
		} else {
			// Create new client.
			$result = $this->create_client( $client_data );
		}

		if ( is_wp_error( $result ) ) {
			$this->log_sync( $booking_id, 'client', null, 'failed', $result->get_error_message() );
			return $result;
		}

		// Store FreshBooks client ID.
		$fb_id = $result['response']['result']['client']['id'] ?? null;
		if ( $fb_id ) {
			update_post_meta( $booking_id, '_freshbooks_client_id', $fb_id );
			$this->log_sync( $booking_id, 'client', $fb_id, 'success' );
		}

		return $result;
	}

	/**
	 * Find client by email.
	 *
	 * @param string $email Client email.
	 * @return array|null|\WP_Error
	 */
	public function find_client_by_email( $email ) {
		$api    = $this->addon->get_service( 'api_client' );
		$result = $api->get_clients( array( 'email' => $email ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$clients = $result['response']['result']['clients'] ?? array();
		return ! empty( $clients ) ? $clients[0] : null;
	}

	/**
	 * Prepare client data from booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	private function prepare_client_data( $booking_id ) {
		$customer_name  = get_post_meta( $booking_id, 'customer_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_phone = get_post_meta( $booking_id, 'customer_phone', true );

		// Split name.
		$name_parts = explode( ' ', $customer_name, 2 );
		$first_name = $name_parts[0] ?? '';
		$last_name  = $name_parts[1] ?? '';

		return array(
			'fname'      => $first_name,
			'lname'      => $last_name,
			'email'      => $customer_email,
			'mob_phone'  => $customer_phone,
		);
	}

	/**
	 * Create client in FreshBooks.
	 *
	 * @param array $data Client data.
	 * @return array|\WP_Error
	 */
	private function create_client( $data ) {
		$api = $this->addon->get_service( 'api_client' );
		return $api->create_client( $data );
	}

	/**
	 * Update client in FreshBooks.
	 *
	 * @param int   $client_id Client ID.
	 * @param array $data      Client data.
	 * @return array|\WP_Error
	 */
	private function update_client( $client_id, $data ) {
		$api = $this->addon->get_service( 'api_client' );
		return $api->update_client( $client_id, $data );
	}

	/**
	 * Get FreshBooks client ID for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return int|null
	 */
	public function get_fb_client_id( $booking_id ) {
		$fb_id = get_post_meta( $booking_id, '_freshbooks_client_id', true );

		if ( ! empty( $fb_id ) ) {
			return (int) $fb_id;
		}

		// Try to find by email.
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		if ( empty( $customer_email ) ) {
			return null;
		}

		$existing = $this->find_client_by_email( $customer_email );
		if ( $existing && ! is_wp_error( $existing ) ) {
			$fb_id = $existing['id'];
			update_post_meta( $booking_id, '_freshbooks_client_id', $fb_id );
			return (int) $fb_id;
		}

		return null;
	}

	/**
	 * Log sync operation.
	 *
	 * @param int         $booking_id Booking ID.
	 * @param string      $type       Sync type.
	 * @param int|null    $fb_id      FreshBooks ID.
	 * @param string      $status     Sync status.
	 * @param string|null $error      Error message.
	 */
	private function log_sync( $booking_id, $type, $fb_id, $status, $error = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_freshbooks_sync_log';

		$wpdb->insert(
			$table_name,
			array(
				'booking_id'    => $booking_id,
				'fb_type'       => $type,
				'fb_id'         => $fb_id,
				'sync_status'   => $status,
				'error_message' => $error,
				'synced_at'     => 'success' === $status ? current_time( 'mysql' ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}

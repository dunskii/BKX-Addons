<?php
/**
 * API client for communicating with remote BKX sites.
 *
 * @package BookingX\BkxIntegration\Services
 */

namespace BookingX\BkxIntegration\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ApiClient class.
 */
class ApiClient {

	/**
	 * Site service.
	 *
	 * @var SiteService
	 */
	private $sites;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->sites = new SiteService();
	}

	/**
	 * Make API request to remote site.
	 *
	 * @param int    $site_id  Site ID.
	 * @param string $endpoint API endpoint.
	 * @param string $method   HTTP method.
	 * @param array  $data     Request data.
	 * @return array|\WP_Error
	 */
	public function request( $site_id, $endpoint, $method = 'GET', $data = array() ) {
		$site = $this->sites->get( $site_id );

		if ( ! $site ) {
			return new \WP_Error( 'invalid_site', __( 'Site not found.', 'bkx-bkx-integration' ) );
		}

		$url       = trailingslashit( $site->url ) . 'wp-json/bkx-integration/v1/' . ltrim( $endpoint, '/' );
		$timestamp = time();
		$body      = ! empty( $data ) ? wp_json_encode( $data ) : '';

		// Generate signature.
		$signature = hash_hmac( 'sha256', $timestamp . $body, $site->api_secret );

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Content-Type'    => 'application/json',
				'X-BKX-API-Key'   => $site->api_key,
				'X-BKX-Signature' => $signature,
				'X-BKX-Timestamp' => $timestamp,
			),
		);

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && $body ) {
			$args['body'] = $body;
		}

		// Log request.
		$this->log_request( $site_id, 'out', $endpoint, $method, $data );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_response( $site_id, 'out', $endpoint, 'error', $response->get_error_message() );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code >= 400 ) {
			$error_message = $data['message'] ?? __( 'Remote server error.', 'bkx-bkx-integration' );
			$this->log_response( $site_id, 'out', $endpoint, 'error', $error_message, $data );
			return new \WP_Error( 'api_error', $error_message );
		}

		$this->log_response( $site_id, 'out', $endpoint, 'success', '', $data );

		return $data;
	}

	/**
	 * Ping remote site.
	 *
	 * @param int $site_id Site ID.
	 * @return array|\WP_Error
	 */
	public function ping( $site_id ) {
		return $this->request( $site_id, 'ping', 'GET' );
	}

	/**
	 * Send booking to remote site.
	 *
	 * @param int   $site_id     Site ID.
	 * @param array $booking_data Booking data.
	 * @param string $action     Action (create, update, delete).
	 * @return array|\WP_Error
	 */
	public function send_booking( $site_id, $booking_data, $action = 'create' ) {
		$method = 'POST';

		switch ( $action ) {
			case 'update':
				$method = 'PUT';
				break;
			case 'delete':
				$method = 'DELETE';
				break;
		}

		return $this->request( $site_id, 'booking', $method, $booking_data );
	}

	/**
	 * Send availability to remote site.
	 *
	 * @param int   $site_id          Site ID.
	 * @param array $availability_data Availability data.
	 * @return array|\WP_Error
	 */
	public function send_availability( $site_id, $availability_data ) {
		return $this->request( $site_id, 'availability', 'POST', $availability_data );
	}

	/**
	 * Check availability on remote site.
	 *
	 * @param int    $site_id    Site ID.
	 * @param string $date       Date.
	 * @param int    $service_id Service ID (optional).
	 * @param int    $staff_id   Staff ID (optional).
	 * @return array|\WP_Error
	 */
	public function check_availability( $site_id, $date, $service_id = 0, $staff_id = 0 ) {
		$params = array(
			'date'       => $date,
			'service_id' => $service_id,
			'staff_id'   => $staff_id,
		);

		return $this->request( $site_id, 'availability/check?' . http_build_query( $params ), 'GET' );
	}

	/**
	 * Send customer to remote site.
	 *
	 * @param int   $site_id       Site ID.
	 * @param array $customer_data Customer data.
	 * @param string $action       Action (create, update).
	 * @return array|\WP_Error
	 */
	public function send_customer( $site_id, $customer_data, $action = 'create' ) {
		$method = 'update' === $action ? 'PUT' : 'POST';
		return $this->request( $site_id, 'customer', $method, $customer_data );
	}

	/**
	 * Log outgoing request.
	 *
	 * @param int    $site_id  Site ID.
	 * @param string $direction Direction.
	 * @param string $endpoint Endpoint.
	 * @param string $method   HTTP method.
	 * @param array  $data     Request data.
	 */
	private function log_request( $site_id, $direction, $endpoint, $method, $data ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_remote_logs',
			array(
				'site_id'      => $site_id,
				'direction'    => $direction,
				'action'       => $method . ' ' . $endpoint,
				'object_type'  => $this->get_object_type_from_endpoint( $endpoint ),
				'status'       => 'pending',
				'request_data' => wp_json_encode( $data ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Log response.
	 *
	 * @param int    $site_id  Site ID.
	 * @param string $direction Direction.
	 * @param string $endpoint Endpoint.
	 * @param string $status   Status.
	 * @param string $message  Message.
	 * @param array  $data     Response data.
	 */
	private function log_response( $site_id, $direction, $endpoint, $status, $message = '', $data = array() ) {
		global $wpdb;

		// Get the last log entry for this request.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bkx_remote_logs WHERE site_id = %d AND direction = %s AND status = 'pending' ORDER BY id DESC LIMIT 1",
				$site_id,
				$direction
			)
		);

		if ( $log ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$wpdb->prefix . 'bkx_remote_logs',
				array(
					'status'        => $status,
					'message'       => $message,
					'response_data' => wp_json_encode( $data ),
				),
				array( 'id' => $log->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Get object type from endpoint.
	 *
	 * @param string $endpoint API endpoint.
	 * @return string
	 */
	private function get_object_type_from_endpoint( $endpoint ) {
		if ( strpos( $endpoint, 'booking' ) !== false ) {
			return 'booking';
		}
		if ( strpos( $endpoint, 'availability' ) !== false ) {
			return 'availability';
		}
		if ( strpos( $endpoint, 'customer' ) !== false ) {
			return 'customer';
		}
		return 'other';
	}
}

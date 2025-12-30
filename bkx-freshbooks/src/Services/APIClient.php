<?php
/**
 * API Client Service for FreshBooks Integration.
 *
 * @package BookingX\FreshBooks\Services
 */

namespace BookingX\FreshBooks\Services;

defined( 'ABSPATH' ) || exit;

/**
 * APIClient class.
 */
class APIClient {

	/**
	 * FreshBooks API Base URL.
	 */
	const API_BASE_URL = 'https://api.freshbooks.com';

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
	 * Make an API request.
	 *
	 * @param string $endpoint Endpoint path.
	 * @param string $method   HTTP method.
	 * @param array  $body     Request body.
	 * @return array|\WP_Error
	 */
	public function request( $endpoint, $method = 'GET', $body = array() ) {
		$oauth = $this->addon->get_service( 'oauth' );
		$token = $oauth->get_valid_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = self::API_BASE_URL . $endpoint;

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Api-Version'   => 'alpha',
			),
		);

		if ( ! empty( $body ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $response_code >= 400 ) {
			$error_message = $this->parse_error( $data );
			return new \WP_Error( 'api_error', $error_message, array( 'status' => $response_code ) );
		}

		return $data ?? array();
	}

	/**
	 * Parse error from response.
	 *
	 * @param array $data Response data.
	 * @return string
	 */
	private function parse_error( $data ) {
		if ( ! empty( $data['response']['errors'] ) ) {
			$errors = array();
			foreach ( $data['response']['errors'] as $error ) {
				$errors[] = $error['message'] ?? $error['errno'] ?? '';
			}
			return implode( ', ', array_filter( $errors ) );
		}

		if ( ! empty( $data['error_description'] ) ) {
			return $data['error_description'];
		}

		return __( 'Unknown API error.', 'bkx-freshbooks' );
	}

	/**
	 * Get current user info.
	 *
	 * @return array|\WP_Error
	 */
	public function get_current_user() {
		return $this->request( '/auth/api/v1/users/me' );
	}

	/**
	 * Get account ID.
	 *
	 * @return string
	 */
	private function get_account_id() {
		return $this->addon->get_setting( 'account_id', '' );
	}

	/**
	 * Get clients.
	 *
	 * @param array $params Query parameters.
	 * @return array|\WP_Error
	 */
	public function get_clients( $params = array() ) {
		$account_id = $this->get_account_id();
		$endpoint   = "/accounting/account/{$account_id}/users/clients";

		if ( ! empty( $params['email'] ) ) {
			$endpoint .= '?search[email]=' . urlencode( $params['email'] );
		}

		return $this->request( $endpoint );
	}

	/**
	 * Create client.
	 *
	 * @param array $data Client data.
	 * @return array|\WP_Error
	 */
	public function create_client( $data ) {
		$account_id = $this->get_account_id();
		return $this->request(
			"/accounting/account/{$account_id}/users/clients",
			'POST',
			array( 'client' => $data )
		);
	}

	/**
	 * Update client.
	 *
	 * @param int   $client_id Client ID.
	 * @param array $data      Client data.
	 * @return array|\WP_Error
	 */
	public function update_client( $client_id, $data ) {
		$account_id = $this->get_account_id();
		return $this->request(
			"/accounting/account/{$account_id}/users/clients/{$client_id}",
			'PUT',
			array( 'client' => $data )
		);
	}

	/**
	 * Get invoices.
	 *
	 * @param array $params Query parameters.
	 * @return array|\WP_Error
	 */
	public function get_invoices( $params = array() ) {
		$account_id = $this->get_account_id();
		return $this->request( "/accounting/account/{$account_id}/invoices/invoices" );
	}

	/**
	 * Create invoice.
	 *
	 * @param array $data Invoice data.
	 * @return array|\WP_Error
	 */
	public function create_invoice( $data ) {
		$account_id = $this->get_account_id();
		return $this->request(
			"/accounting/account/{$account_id}/invoices/invoices",
			'POST',
			array( 'invoice' => $data )
		);
	}

	/**
	 * Update invoice.
	 *
	 * @param int   $invoice_id Invoice ID.
	 * @param array $data       Invoice data.
	 * @return array|\WP_Error
	 */
	public function update_invoice( $invoice_id, $data ) {
		$account_id = $this->get_account_id();
		return $this->request(
			"/accounting/account/{$account_id}/invoices/invoices/{$invoice_id}",
			'PUT',
			array( 'invoice' => $data )
		);
	}

	/**
	 * Send invoice by email.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return array|\WP_Error
	 */
	public function send_invoice( $invoice_id ) {
		$account_id = $this->get_account_id();
		return $this->request(
			"/accounting/account/{$account_id}/invoices/invoices/{$invoice_id}",
			'PUT',
			array(
				'invoice' => array(
					'action_email' => true,
				),
			)
		);
	}

	/**
	 * Create payment.
	 *
	 * @param array $data Payment data.
	 * @return array|\WP_Error
	 */
	public function create_payment( $data ) {
		$account_id = $this->get_account_id();
		return $this->request(
			"/accounting/account/{$account_id}/payments/payments",
			'POST',
			array( 'payment' => $data )
		);
	}

	/**
	 * Get services/items.
	 *
	 * @return array|\WP_Error
	 */
	public function get_services() {
		$account_id = $this->get_account_id();
		return $this->request( "/accounting/account/{$account_id}/items/items" );
	}

	/**
	 * Get taxes.
	 *
	 * @return array|\WP_Error
	 */
	public function get_taxes() {
		$account_id = $this->get_account_id();
		return $this->request( "/accounting/account/{$account_id}/taxes/taxes" );
	}
}

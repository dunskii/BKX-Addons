<?php
/**
 * API Client Service for MYOB Integration.
 *
 * Handles communication with MYOB API.
 *
 * @package BookingX\MYOB\Services
 */

namespace BookingX\MYOB\Services;

defined( 'ABSPATH' ) || exit;

/**
 * APIClient class.
 */
class APIClient {

	/**
	 * MYOB API Base URL.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://api.myob.com/accountright/';

	/**
	 * MYOB Essentials API Base URL.
	 *
	 * @var string
	 */
	const ESSENTIALS_API_URL = 'https://api.myob.com/au/essentials/businesses/';

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
	 * Get API base URL based on type.
	 *
	 * @return string
	 */
	private function get_base_url() {
		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );

		if ( 'essentials' === $api_type ) {
			return self::ESSENTIALS_API_URL;
		}

		return self::API_BASE_URL;
	}

	/**
	 * Make an API request.
	 *
	 * @param string $endpoint Endpoint path.
	 * @param string $method   HTTP method.
	 * @param array  $body     Request body.
	 * @param array  $args     Additional arguments.
	 * @return array|\WP_Error
	 */
	public function request( $endpoint, $method = 'GET', $body = array(), $args = array() ) {
		$oauth = $this->addon->get_service( 'oauth' );
		$token = $oauth->get_valid_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = $this->build_url( $endpoint );

		$default_args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization'     => 'Bearer ' . $token,
				'x-myobapi-key'     => $this->addon->get_setting( 'client_id' ),
				'x-myobapi-version' => 'v2',
				'Content-Type'      => 'application/json',
				'Accept'            => 'application/json',
			),
		);

		if ( ! empty( $body ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$default_args['body'] = wp_json_encode( $body );
		}

		$request_args = array_merge( $default_args, $args );

		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API request failed', $response->get_error_message() );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $response_code >= 400 ) {
			$error_message = $this->parse_error( $data, $response_body );
			$this->log_error( 'API error', array(
				'code'    => $response_code,
				'message' => $error_message,
			) );
			return new \WP_Error( 'api_error', $error_message, array( 'status' => $response_code ) );
		}

		return $data ?? array();
	}

	/**
	 * Build full URL.
	 *
	 * @param string $endpoint Endpoint path.
	 * @return string
	 */
	private function build_url( $endpoint ) {
		$base_url       = $this->get_base_url();
		$api_type       = $this->addon->get_setting( 'api_type', 'essentials' );
		$company_file_id = $this->addon->get_setting( 'company_file_id' );

		if ( 'accountright' === $api_type && $company_file_id ) {
			return $base_url . $company_file_id . '/' . ltrim( $endpoint, '/' );
		}

		return $base_url . ltrim( $endpoint, '/' );
	}

	/**
	 * Parse error from response.
	 *
	 * @param array|null $data Response data.
	 * @param string     $body Raw response body.
	 * @return string
	 */
	private function parse_error( $data, $body ) {
		if ( ! empty( $data['Errors'] ) && is_array( $data['Errors'] ) ) {
			$messages = array_map(
				function ( $err ) {
					return $err['Message'] ?? $err['Name'] ?? '';
				},
				$data['Errors']
			);
			return implode( ', ', array_filter( $messages ) );
		}

		if ( ! empty( $data['Message'] ) ) {
			return $data['Message'];
		}

		if ( ! empty( $data['error_description'] ) ) {
			return $data['error_description'];
		}

		return __( 'Unknown API error.', 'bkx-myob' );
	}

	/**
	 * Get company files.
	 *
	 * @return array|\WP_Error
	 */
	public function get_company_files() {
		$oauth = $this->addon->get_service( 'oauth' );
		$token = $oauth->get_valid_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_get(
			self::API_BASE_URL,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization'     => 'Bearer ' . $token,
					'x-myobapi-key'     => $this->addon->get_setting( 'client_id' ),
					'x-myobapi-version' => 'v2',
					'Accept'            => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body ?? array();
	}

	/**
	 * Get income accounts.
	 *
	 * @return array|\WP_Error
	 */
	public function get_income_accounts() {
		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );

		if ( 'essentials' === $api_type ) {
			return $this->request( 'accounts?$filter=Classification eq \'Income\'' );
		}

		return $this->request( 'GeneralLedger/Account?$filter=Type eq \'Income\'' );
	}

	/**
	 * Get tax codes.
	 *
	 * @return array|\WP_Error
	 */
	public function get_tax_codes() {
		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );

		if ( 'essentials' === $api_type ) {
			return $this->request( 'taxcodes' );
		}

		return $this->request( 'GeneralLedger/TaxCode' );
	}

	/**
	 * Get customers.
	 *
	 * @param array $params Query parameters.
	 * @return array|\WP_Error
	 */
	public function get_customers( $params = array() ) {
		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );
		$endpoint = 'essentials' === $api_type ? 'contacts' : 'Contact/Customer';

		$query = '';
		if ( ! empty( $params['email'] ) ) {
			$query = '?$filter=Email eq \'' . urlencode( $params['email'] ) . '\'';
		}

		return $this->request( $endpoint . $query );
	}

	/**
	 * Create customer.
	 *
	 * @param array $data Customer data.
	 * @return array|\WP_Error
	 */
	public function create_customer( $data ) {
		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );
		$endpoint = 'essentials' === $api_type ? 'contacts' : 'Contact/Customer';

		return $this->request( $endpoint, 'POST', $data );
	}

	/**
	 * Update customer.
	 *
	 * @param string $id   Customer ID.
	 * @param array  $data Customer data.
	 * @return array|\WP_Error
	 */
	public function update_customer( $id, $data ) {
		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );
		$endpoint = 'essentials' === $api_type ? 'contacts/' : 'Contact/Customer/';

		return $this->request( $endpoint . $id, 'PUT', $data );
	}

	/**
	 * Get invoices.
	 *
	 * @param array $params Query parameters.
	 * @return array|\WP_Error
	 */
	public function get_invoices( $params = array() ) {
		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );
		$endpoint = 'essentials' === $api_type ? 'sale/invoices' : 'Sale/Invoice/Service';

		return $this->request( $endpoint );
	}

	/**
	 * Create invoice.
	 *
	 * @param array $data Invoice data.
	 * @return array|\WP_Error
	 */
	public function create_invoice( $data ) {
		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );
		$endpoint = 'essentials' === $api_type ? 'sale/invoices' : 'Sale/Invoice/Service';

		return $this->request( $endpoint, 'POST', $data );
	}

	/**
	 * Update invoice.
	 *
	 * @param string $id   Invoice ID.
	 * @param array  $data Invoice data.
	 * @return array|\WP_Error
	 */
	public function update_invoice( $id, $data ) {
		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );
		$endpoint = 'essentials' === $api_type ? 'sale/invoices/' : 'Sale/Invoice/Service/';

		return $this->request( $endpoint . $id, 'PUT', $data );
	}

	/**
	 * Create customer payment.
	 *
	 * @param array $data Payment data.
	 * @return array|\WP_Error
	 */
	public function create_payment( $data ) {
		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );
		$endpoint = 'essentials' === $api_type ? 'sale/payments' : 'Sale/CustomerPayment';

		return $this->request( $endpoint, 'POST', $data );
	}

	/**
	 * Get payment methods.
	 *
	 * @return array|\WP_Error
	 */
	public function get_payment_methods() {
		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );

		if ( 'essentials' === $api_type ) {
			// Essentials doesn't have a separate payment methods endpoint.
			return array(
				array( 'UID' => 'cash', 'Name' => 'Cash' ),
				array( 'UID' => 'card', 'Name' => 'Credit Card' ),
				array( 'UID' => 'eft', 'Name' => 'Electronic Transfer' ),
			);
		}

		return $this->request( 'GeneralLedger/PaymentMethod' );
	}

	/**
	 * Log error.
	 *
	 * @param string $message Error message.
	 * @param mixed  $context Error context.
	 */
	private function log_error( $message, $context = null ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BKX MYOB: ' . $message . ' - ' . print_r( $context, true ) );
		}
	}
}

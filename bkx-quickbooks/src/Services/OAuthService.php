<?php
/**
 * QuickBooks OAuth Service.
 *
 * @package BookingX\QuickBooks\Services
 * @since   1.0.0
 */

namespace BookingX\QuickBooks\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OAuthService Class.
 */
class OAuthService {

	/**
	 * OAuth endpoints.
	 */
	private const SANDBOX_AUTH_URL    = 'https://appcenter.intuit.com/connect/oauth2';
	private const PRODUCTION_AUTH_URL = 'https://appcenter.intuit.com/connect/oauth2';
	private const SANDBOX_TOKEN_URL   = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
	private const PRODUCTION_TOKEN_URL = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
	private const REVOKE_URL          = 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke';

	/**
	 * API Base URLs.
	 */
	private const SANDBOX_API_URL    = 'https://sandbox-quickbooks.api.intuit.com/v3/company/';
	private const PRODUCTION_API_URL = 'https://quickbooks.api.intuit.com/v3/company/';

	/**
	 * Get authorization URL.
	 *
	 * @return string|false Authorization URL or false on error.
	 */
	public function get_authorization_url() {
		$client_id = get_option( 'bkx_qb_client_id' );

		if ( empty( $client_id ) ) {
			return false;
		}

		$redirect_uri = admin_url( 'admin.php?page=bkx-quickbooks' );
		$state        = wp_create_nonce( 'bkx_qb_oauth' );

		// Store state for verification.
		set_transient( 'bkx_qb_oauth_state', $state, HOUR_IN_SECONDS );

		$params = array(
			'client_id'     => $client_id,
			'response_type' => 'code',
			'scope'         => 'com.intuit.quickbooks.accounting',
			'redirect_uri'  => $redirect_uri,
			'state'         => $state,
		);

		$auth_url = $this->get_auth_endpoint() . '?' . http_build_query( $params );

		return $auth_url;
	}

	/**
	 * Exchange authorization code for tokens.
	 *
	 * @param string $code     Authorization code.
	 * @param string $realm_id QuickBooks company ID.
	 * @return bool True on success, false on failure.
	 */
	public function exchange_code_for_tokens( $code, $realm_id ) {
		$client_id     = get_option( 'bkx_qb_client_id' );
		$client_secret = get_option( 'bkx_qb_client_secret' );
		$redirect_uri  = admin_url( 'admin.php?page=bkx-quickbooks' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			$this->log_error( 'Missing client credentials' );
			return false;
		}

		$response = wp_remote_post(
			$this->get_token_endpoint(),
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Accept'        => 'application/json',
				),
				'body'    => array(
					'grant_type'   => 'authorization_code',
					'code'         => $code,
					'redirect_uri' => $redirect_uri,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Token exchange failed: ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$this->log_error( 'Token exchange error: ' . ( $body['error_description'] ?? $body['error'] ) );
			return false;
		}

		if ( ! isset( $body['access_token'] ) || ! isset( $body['refresh_token'] ) ) {
			$this->log_error( 'Invalid token response' );
			return false;
		}

		// Store tokens securely.
		$this->store_tokens( $body, $realm_id );

		return true;
	}

	/**
	 * Refresh access token.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function refresh_access_token() {
		$refresh_token = $this->get_decrypted_option( 'bkx_qb_refresh_token' );
		$client_id     = get_option( 'bkx_qb_client_id' );
		$client_secret = get_option( 'bkx_qb_client_secret' );

		if ( empty( $refresh_token ) || empty( $client_id ) || empty( $client_secret ) ) {
			return false;
		}

		$response = wp_remote_post(
			$this->get_token_endpoint(),
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Accept'        => 'application/json',
				),
				'body'    => array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh_token,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Token refresh failed: ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$this->log_error( 'Token refresh error: ' . ( $body['error_description'] ?? $body['error'] ) );
			return false;
		}

		if ( ! isset( $body['access_token'] ) ) {
			return false;
		}

		$realm_id = get_option( 'bkx_qb_realm_id' );
		$this->store_tokens( $body, $realm_id );

		return true;
	}

	/**
	 * Revoke tokens and disconnect.
	 *
	 * @return bool True on success.
	 */
	public function revoke_tokens() {
		$refresh_token = $this->get_decrypted_option( 'bkx_qb_refresh_token' );
		$client_id     = get_option( 'bkx_qb_client_id' );
		$client_secret = get_option( 'bkx_qb_client_secret' );

		if ( ! empty( $refresh_token ) && ! empty( $client_id ) && ! empty( $client_secret ) ) {
			// Attempt to revoke token at QuickBooks.
			wp_remote_post(
				self::REVOKE_URL,
				array(
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
						'Content-Type'  => 'application/json',
						'Accept'        => 'application/json',
					),
					'body'    => wp_json_encode( array( 'token' => $refresh_token ) ),
					'timeout' => 30,
				)
			);
		}

		// Clear stored tokens.
		delete_option( 'bkx_qb_access_token' );
		delete_option( 'bkx_qb_refresh_token' );
		delete_option( 'bkx_qb_token_expires' );
		delete_option( 'bkx_qb_realm_id' );

		return true;
	}

	/**
	 * Check if connected to QuickBooks.
	 *
	 * @return bool True if connected.
	 */
	public function is_connected() {
		$access_token = get_option( 'bkx_qb_access_token' );
		$realm_id     = get_option( 'bkx_qb_realm_id' );
		$expires      = get_option( 'bkx_qb_token_expires' );

		if ( empty( $access_token ) || empty( $realm_id ) ) {
			return false;
		}

		// Check if token is expired and try to refresh.
		if ( $expires && time() > $expires ) {
			return $this->refresh_access_token();
		}

		return true;
	}

	/**
	 * Get valid access token.
	 *
	 * @return string|false Access token or false.
	 */
	public function get_access_token() {
		if ( ! $this->is_connected() ) {
			return false;
		}

		return $this->get_decrypted_option( 'bkx_qb_access_token' );
	}

	/**
	 * Get realm ID (company ID).
	 *
	 * @return string|false Realm ID or false.
	 */
	public function get_realm_id() {
		return get_option( 'bkx_qb_realm_id' );
	}

	/**
	 * Get API base URL.
	 *
	 * @return string API base URL.
	 */
	public function get_api_base_url() {
		$environment = get_option( 'bkx_qb_environment', 'sandbox' );
		$realm_id    = $this->get_realm_id();

		$base = 'production' === $environment ? self::PRODUCTION_API_URL : self::SANDBOX_API_URL;

		return $base . $realm_id . '/';
	}

	/**
	 * Make authenticated API request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method   HTTP method.
	 * @param array  $data     Request data.
	 * @return array|false Response data or false on error.
	 */
	public function api_request( $endpoint, $method = 'GET', $data = array() ) {
		$access_token = $this->get_access_token();

		if ( ! $access_token ) {
			$this->log_error( 'No valid access token for API request' );
			return false;
		}

		$url = $this->get_api_base_url() . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API request failed: ' . $response->get_error_message() );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		// Handle token expiration.
		if ( 401 === $status_code ) {
			if ( $this->refresh_access_token() ) {
				// Retry request with new token.
				return $this->api_request( $endpoint, $method, $data );
			}
			return false;
		}

		if ( $status_code >= 400 ) {
			$error_message = isset( $body['Fault']['Error'][0]['Message'] )
				? $body['Fault']['Error'][0]['Message']
				: 'Unknown API error';
			$this->log_error( "API error ($status_code): $error_message" );
			return false;
		}

		return $body;
	}

	/**
	 * Store tokens securely.
	 *
	 * @param array  $token_data Token response data.
	 * @param string $realm_id   QuickBooks company ID.
	 */
	private function store_tokens( $token_data, $realm_id ) {
		$this->store_encrypted_option( 'bkx_qb_access_token', $token_data['access_token'] );
		$this->store_encrypted_option( 'bkx_qb_refresh_token', $token_data['refresh_token'] );

		$expires_in = isset( $token_data['expires_in'] ) ? absint( $token_data['expires_in'] ) : 3600;
		update_option( 'bkx_qb_token_expires', time() + $expires_in - 300 ); // 5 min buffer.
		update_option( 'bkx_qb_realm_id', sanitize_text_field( $realm_id ) );
	}

	/**
	 * Store encrypted option.
	 *
	 * @param string $option_name Option name.
	 * @param string $value       Value to encrypt.
	 */
	private function store_encrypted_option( $option_name, $value ) {
		if ( class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();
			$value      = $encryption->encrypt( $value );
		}
		update_option( $option_name, $value );
	}

	/**
	 * Get decrypted option.
	 *
	 * @param string $option_name Option name.
	 * @return string Decrypted value.
	 */
	private function get_decrypted_option( $option_name ) {
		$value = get_option( $option_name );

		if ( $value && class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();
			$decrypted  = $encryption->decrypt( $value );
			if ( false !== $decrypted ) {
				return $decrypted;
			}
		}

		return $value;
	}

	/**
	 * Get auth endpoint based on environment.
	 *
	 * @return string Auth endpoint URL.
	 */
	private function get_auth_endpoint() {
		$environment = get_option( 'bkx_qb_environment', 'sandbox' );
		return 'production' === $environment ? self::PRODUCTION_AUTH_URL : self::SANDBOX_AUTH_URL;
	}

	/**
	 * Get token endpoint based on environment.
	 *
	 * @return string Token endpoint URL.
	 */
	private function get_token_endpoint() {
		$environment = get_option( 'bkx_qb_environment', 'sandbox' );
		return 'production' === $environment ? self::PRODUCTION_TOKEN_URL : self::SANDBOX_TOKEN_URL;
	}

	/**
	 * Log error.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( $message ) {
		if ( class_exists( 'BKX_Error_Logger' ) ) {
			\BKX_Error_Logger::log( 'QuickBooks OAuth: ' . $message, 'error' );
		}
		error_log( 'BKX QuickBooks OAuth: ' . $message );
	}

	/**
	 * Get company info.
	 *
	 * @return array|false Company info or false.
	 */
	public function get_company_info() {
		$realm_id = $this->get_realm_id();

		if ( ! $realm_id ) {
			return false;
		}

		$response = $this->api_request( "companyinfo/{$realm_id}" );

		if ( $response && isset( $response['CompanyInfo'] ) ) {
			return $response['CompanyInfo'];
		}

		return false;
	}
}

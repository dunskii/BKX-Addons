<?php
/**
 * Xero OAuth Service.
 *
 * @package BookingX\Xero\Services
 * @since   1.0.0
 */

namespace BookingX\Xero\Services;

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
	private const AUTH_URL     = 'https://login.xero.com/identity/connect/authorize';
	private const TOKEN_URL    = 'https://identity.xero.com/connect/token';
	private const REVOKE_URL   = 'https://identity.xero.com/connect/revocation';
	private const API_BASE_URL = 'https://api.xero.com/api.xro/2.0/';

	/**
	 * Required OAuth scopes.
	 */
	private const SCOPES = array(
		'openid',
		'profile',
		'email',
		'accounting.transactions',
		'accounting.contacts',
		'accounting.settings.read',
	);

	/**
	 * Get authorization URL.
	 *
	 * @return string|false Authorization URL or false on error.
	 */
	public function get_authorization_url() {
		$client_id = get_option( 'bkx_xero_client_id' );

		if ( empty( $client_id ) ) {
			return false;
		}

		$redirect_uri = admin_url( 'admin.php?page=bkx-xero' );
		$state        = wp_create_nonce( 'bkx_xero_oauth' );

		// Store state for verification.
		set_transient( 'bkx_xero_oauth_state', $state, HOUR_IN_SECONDS );

		$params = array(
			'response_type' => 'code',
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'scope'         => implode( ' ', self::SCOPES ),
			'state'         => $state,
		);

		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for tokens.
	 *
	 * @param string $code Authorization code.
	 * @return bool True on success, false on failure.
	 */
	public function exchange_code_for_tokens( $code ) {
		$client_id     = get_option( 'bkx_xero_client_id' );
		$client_secret = get_option( 'bkx_xero_client_secret' );
		$redirect_uri  = admin_url( 'admin.php?page=bkx-xero' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			$this->log_error( 'Missing client credentials' );
			return false;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
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
		$this->store_tokens( $body );

		// Get tenant ID.
		$this->fetch_and_store_tenant_id( $body['access_token'] );

		return true;
	}

	/**
	 * Fetch and store Xero tenant ID.
	 *
	 * @param string $access_token Access token.
	 */
	private function fetch_and_store_tenant_id( $access_token ) {
		$response = wp_remote_get(
			'https://api.xero.com/connections',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Failed to fetch tenant ID: ' . $response->get_error_message() );
			return;
		}

		$connections = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $connections[0]['tenantId'] ) ) {
			update_option( 'bkx_xero_tenant_id', $connections[0]['tenantId'] );
			update_option( 'bkx_xero_tenant_name', $connections[0]['tenantName'] ?? '' );
		}
	}

	/**
	 * Refresh access token.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function refresh_access_token() {
		$refresh_token = $this->get_decrypted_option( 'bkx_xero_refresh_token' );
		$client_id     = get_option( 'bkx_xero_client_id' );
		$client_secret = get_option( 'bkx_xero_client_secret' );

		if ( empty( $refresh_token ) || empty( $client_id ) || empty( $client_secret ) ) {
			return false;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
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

		$this->store_tokens( $body );

		return true;
	}

	/**
	 * Revoke tokens and disconnect.
	 *
	 * @return bool True on success.
	 */
	public function revoke_tokens() {
		$refresh_token = $this->get_decrypted_option( 'bkx_xero_refresh_token' );
		$client_id     = get_option( 'bkx_xero_client_id' );
		$client_secret = get_option( 'bkx_xero_client_secret' );

		if ( ! empty( $refresh_token ) && ! empty( $client_id ) && ! empty( $client_secret ) ) {
			// Attempt to revoke token at Xero.
			wp_remote_post(
				self::REVOKE_URL,
				array(
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
						'Content-Type'  => 'application/x-www-form-urlencoded',
					),
					'body'    => array( 'token' => $refresh_token ),
					'timeout' => 30,
				)
			);
		}

		// Clear stored tokens.
		delete_option( 'bkx_xero_access_token' );
		delete_option( 'bkx_xero_refresh_token' );
		delete_option( 'bkx_xero_token_expires' );
		delete_option( 'bkx_xero_tenant_id' );
		delete_option( 'bkx_xero_tenant_name' );

		return true;
	}

	/**
	 * Check if connected to Xero.
	 *
	 * @return bool True if connected.
	 */
	public function is_connected() {
		$access_token = get_option( 'bkx_xero_access_token' );
		$tenant_id    = get_option( 'bkx_xero_tenant_id' );
		$expires      = get_option( 'bkx_xero_token_expires' );

		if ( empty( $access_token ) || empty( $tenant_id ) ) {
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

		return $this->get_decrypted_option( 'bkx_xero_access_token' );
	}

	/**
	 * Get tenant ID.
	 *
	 * @return string|false Tenant ID or false.
	 */
	public function get_tenant_id() {
		return get_option( 'bkx_xero_tenant_id' );
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
		$tenant_id    = $this->get_tenant_id();

		if ( ! $access_token || ! $tenant_id ) {
			$this->log_error( 'No valid access token or tenant ID for API request' );
			return false;
		}

		$url = self::API_BASE_URL . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Xero-tenant-id' => $tenant_id,
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

		// Handle rate limiting.
		if ( 429 === $status_code ) {
			$retry_after = wp_remote_retrieve_header( $response, 'Retry-After' );
			$this->log_error( "Rate limited. Retry after: {$retry_after} seconds" );
			return false;
		}

		if ( $status_code >= 400 ) {
			$error_message = isset( $body['Message'] )
				? $body['Message']
				: ( isset( $body['Elements'][0]['ValidationErrors'][0]['Message'] )
					? $body['Elements'][0]['ValidationErrors'][0]['Message']
					: 'Unknown API error' );
			$this->log_error( "API error ($status_code): $error_message" );
			return false;
		}

		return $body;
	}

	/**
	 * Store tokens securely.
	 *
	 * @param array $token_data Token response data.
	 */
	private function store_tokens( $token_data ) {
		$this->store_encrypted_option( 'bkx_xero_access_token', $token_data['access_token'] );

		if ( isset( $token_data['refresh_token'] ) ) {
			$this->store_encrypted_option( 'bkx_xero_refresh_token', $token_data['refresh_token'] );
		}

		$expires_in = isset( $token_data['expires_in'] ) ? absint( $token_data['expires_in'] ) : 1800;
		update_option( 'bkx_xero_token_expires', time() + $expires_in - 300 ); // 5 min buffer.
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
	 * Log error.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( $message ) {
		if ( class_exists( 'BKX_Error_Logger' ) ) {
			\BKX_Error_Logger::log( 'Xero OAuth: ' . $message, 'error' );
		}
		error_log( 'BKX Xero OAuth: ' . $message );
	}

	/**
	 * Get organization info.
	 *
	 * @return array|false Organization info or false.
	 */
	public function get_organization_info() {
		$response = $this->api_request( 'Organisation' );

		if ( $response && isset( $response['Organisations'][0] ) ) {
			return $response['Organisations'][0];
		}

		return false;
	}
}

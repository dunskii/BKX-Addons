<?php
/**
 * OAuth2 Service.
 *
 * @package BookingX\EnterpriseAPI\Services
 */

namespace BookingX\EnterpriseAPI\Services;

defined( 'ABSPATH' ) || exit;

/**
 * OAuthService class.
 */
class OAuthService {

	/**
	 * Authenticate request via OAuth.
	 *
	 * @param int|false $user_id Current user ID.
	 * @return int|false
	 */
	public function authenticate( $user_id ) {
		// If already authenticated, skip.
		if ( $user_id ) {
			return $user_id;
		}

		// Check for Bearer token.
		$auth_header = $this->get_authorization_header();
		if ( ! $auth_header || strpos( $auth_header, 'Bearer ' ) !== 0 ) {
			return $user_id;
		}

		$token = substr( $auth_header, 7 );
		$token_data = $this->validate_access_token( $token );

		if ( $token_data && $token_data->user_id ) {
			return (int) $token_data->user_id;
		}

		return $user_id;
	}

	/**
	 * Check authentication errors.
	 *
	 * @param \WP_Error|null $errors Authentication errors.
	 * @return \WP_Error|null
	 */
	public function check_errors( $errors ) {
		$auth_header = $this->get_authorization_header();

		// Only check if Bearer token was provided but invalid.
		if ( $auth_header && strpos( $auth_header, 'Bearer ' ) === 0 ) {
			$token = substr( $auth_header, 7 );
			$token_data = $this->validate_access_token( $token );

			if ( ! $token_data ) {
				return new \WP_Error(
					'invalid_token',
					__( 'Invalid or expired access token.', 'bkx-enterprise-api' ),
					array( 'status' => 401 )
				);
			}
		}

		return $errors;
	}

	/**
	 * Handle authorization request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function authorize( $request ) {
		$client_id     = $request->get_param( 'client_id' );
		$redirect_uri  = $request->get_param( 'redirect_uri' );
		$response_type = $request->get_param( 'response_type' );
		$scope         = $request->get_param( 'scope' );
		$state         = $request->get_param( 'state' );
		$code_challenge = $request->get_param( 'code_challenge' );
		$code_challenge_method = $request->get_param( 'code_challenge_method' );

		// Validate client.
		$client = $this->get_client( $client_id );
		if ( ! $client ) {
			return new \WP_Error( 'invalid_client', __( 'Invalid client ID.', 'bkx-enterprise-api' ), array( 'status' => 400 ) );
		}

		// Validate redirect URI.
		if ( $redirect_uri && ! $this->validate_redirect_uri( $client, $redirect_uri ) ) {
			return new \WP_Error( 'invalid_redirect_uri', __( 'Invalid redirect URI.', 'bkx-enterprise-api' ), array( 'status' => 400 ) );
		}

		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			// Redirect to login page.
			$login_url = wp_login_url( add_query_arg( $_GET, rest_url( 'bookingx/v1/oauth/authorize' ) ) );
			return rest_ensure_response( array(
				'redirect' => $login_url,
			) );
		}

		// Generate authorization code.
		$code = $this->create_authorization_code(
			$client_id,
			get_current_user_id(),
			$redirect_uri ?: $client->redirect_uri,
			$scope,
			$code_challenge,
			$code_challenge_method
		);

		$redirect = add_query_arg( array(
			'code'  => $code,
			'state' => $state,
		), $redirect_uri ?: $client->redirect_uri );

		return rest_ensure_response( array(
			'redirect' => $redirect,
		) );
	}

	/**
	 * Handle token request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function token( $request ) {
		$grant_type = $request->get_param( 'grant_type' );

		switch ( $grant_type ) {
			case 'authorization_code':
				return $this->handle_authorization_code_grant( $request );

			case 'refresh_token':
				return $this->handle_refresh_token_grant( $request );

			case 'client_credentials':
				return $this->handle_client_credentials_grant( $request );

			default:
				return new \WP_Error(
					'unsupported_grant_type',
					__( 'Unsupported grant type.', 'bkx-enterprise-api' ),
					array( 'status' => 400 )
				);
		}
	}

	/**
	 * Handle authorization code grant.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function handle_authorization_code_grant( $request ) {
		$client_id     = $request->get_param( 'client_id' );
		$client_secret = $request->get_param( 'client_secret' );
		$code          = $request->get_param( 'code' );
		$redirect_uri  = $request->get_param( 'redirect_uri' );
		$code_verifier = $request->get_param( 'code_verifier' );

		// Validate client.
		$client = $this->validate_client( $client_id, $client_secret );
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		// Validate authorization code.
		$auth_code = $this->validate_authorization_code( $code, $client_id );
		if ( ! $auth_code ) {
			return new \WP_Error( 'invalid_grant', __( 'Invalid authorization code.', 'bkx-enterprise-api' ), array( 'status' => 400 ) );
		}

		// Validate PKCE if used.
		if ( $auth_code->code_challenge ) {
			if ( ! $code_verifier ) {
				return new \WP_Error( 'invalid_grant', __( 'Code verifier required.', 'bkx-enterprise-api' ), array( 'status' => 400 ) );
			}

			if ( ! $this->validate_pkce( $code_verifier, $auth_code->code_challenge, $auth_code->code_challenge_method ) ) {
				return new \WP_Error( 'invalid_grant', __( 'Invalid code verifier.', 'bkx-enterprise-api' ), array( 'status' => 400 ) );
			}
		}

		// Delete used authorization code.
		$this->delete_authorization_code( $code );

		// Generate tokens.
		return $this->generate_token_response( $client_id, $auth_code->user_id, $auth_code->scope );
	}

	/**
	 * Handle refresh token grant.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function handle_refresh_token_grant( $request ) {
		$client_id     = $request->get_param( 'client_id' );
		$client_secret = $request->get_param( 'client_secret' );
		$refresh_token = $request->get_param( 'refresh_token' );

		// Validate client.
		$client = $this->validate_client( $client_id, $client_secret );
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		// Validate refresh token.
		$token_data = $this->validate_refresh_token( $refresh_token, $client_id );
		if ( ! $token_data ) {
			return new \WP_Error( 'invalid_grant', __( 'Invalid refresh token.', 'bkx-enterprise-api' ), array( 'status' => 400 ) );
		}

		// Delete old refresh token.
		$this->delete_refresh_token( $refresh_token );

		// Generate new tokens.
		return $this->generate_token_response( $client_id, $token_data->user_id, $token_data->scope );
	}

	/**
	 * Handle client credentials grant.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function handle_client_credentials_grant( $request ) {
		$client_id     = $request->get_param( 'client_id' );
		$client_secret = $request->get_param( 'client_secret' );
		$scope         = $request->get_param( 'scope' );

		// Validate client.
		$client = $this->validate_client( $client_id, $client_secret );
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		// Client credentials don't have a user context.
		return $this->generate_token_response( $client_id, null, $scope, false );
	}

	/**
	 * Revoke token.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function revoke( $request ) {
		$token      = $request->get_param( 'token' );
		$token_type = $request->get_param( 'token_type_hint' );

		if ( ! $token_type || 'access_token' === $token_type ) {
			$this->delete_access_token( $token );
		}

		if ( ! $token_type || 'refresh_token' === $token_type ) {
			$this->delete_refresh_token( $token );
		}

		return rest_ensure_response( array( 'revoked' => true ) );
	}

	/**
	 * Introspect token.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function introspect( $request ) {
		$token      = $request->get_param( 'token' );
		$token_type = $request->get_param( 'token_type_hint' );

		$token_data = null;

		if ( ! $token_type || 'access_token' === $token_type ) {
			$token_data = $this->validate_access_token( $token );
		}

		if ( ! $token_data && ( ! $token_type || 'refresh_token' === $token_type ) ) {
			global $wpdb;
			$token_data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}bkx_oauth_refresh_tokens WHERE refresh_token = %s AND expires > NOW()",
					$token
				)
			);
		}

		if ( ! $token_data ) {
			return rest_ensure_response( array( 'active' => false ) );
		}

		return rest_ensure_response( array(
			'active'    => true,
			'client_id' => $token_data->client_id,
			'user_id'   => $token_data->user_id,
			'scope'     => $token_data->scope,
			'exp'       => strtotime( $token_data->expires ),
		) );
	}

	/**
	 * Generate token response.
	 *
	 * @param string   $client_id     Client ID.
	 * @param int|null $user_id       User ID.
	 * @param string   $scope         Scope.
	 * @param bool     $include_refresh Include refresh token.
	 * @return \WP_REST_Response
	 */
	private function generate_token_response( $client_id, $user_id, $scope, $include_refresh = true ) {
		$settings = get_option( 'bkx_enterprise_api_settings', array() );

		$access_token  = $this->generate_token();
		$token_expires = $settings['oauth_token_lifetime'] ?? 3600;

		$this->store_access_token( $access_token, $client_id, $user_id, $token_expires, $scope );

		$response = array(
			'access_token' => $access_token,
			'token_type'   => 'Bearer',
			'expires_in'   => $token_expires,
			'scope'        => $scope,
		);

		if ( $include_refresh ) {
			$refresh_token   = $this->generate_token();
			$refresh_expires = $settings['oauth_refresh_lifetime'] ?? 86400 * 30;

			$this->store_refresh_token( $refresh_token, $client_id, $user_id, $refresh_expires, $scope );
			$response['refresh_token'] = $refresh_token;
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Get client by ID.
	 *
	 * @param string $client_id Client ID.
	 * @return object|null
	 */
	public function get_client( $client_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_oauth_clients WHERE client_id = %s AND is_active = 1",
				$client_id
			)
		);
	}

	/**
	 * Validate client credentials.
	 *
	 * @param string $client_id     Client ID.
	 * @param string $client_secret Client secret.
	 * @return object|\WP_Error
	 */
	private function validate_client( $client_id, $client_secret ) {
		$client = $this->get_client( $client_id );

		if ( ! $client ) {
			return new \WP_Error( 'invalid_client', __( 'Invalid client.', 'bkx-enterprise-api' ), array( 'status' => 401 ) );
		}

		if ( ! password_verify( $client_secret, $client->client_secret ) ) {
			return new \WP_Error( 'invalid_client', __( 'Invalid client credentials.', 'bkx-enterprise-api' ), array( 'status' => 401 ) );
		}

		return $client;
	}

	/**
	 * Validate redirect URI.
	 *
	 * @param object $client      Client object.
	 * @param string $redirect_uri Redirect URI.
	 * @return bool
	 */
	private function validate_redirect_uri( $client, $redirect_uri ) {
		$allowed = array_map( 'trim', explode( ',', $client->redirect_uri ) );
		return in_array( $redirect_uri, $allowed, true );
	}

	/**
	 * Create authorization code.
	 *
	 * @param string      $client_id              Client ID.
	 * @param int         $user_id                User ID.
	 * @param string      $redirect_uri           Redirect URI.
	 * @param string      $scope                  Scope.
	 * @param string|null $code_challenge         PKCE code challenge.
	 * @param string|null $code_challenge_method  PKCE method.
	 * @return string
	 */
	private function create_authorization_code( $client_id, $user_id, $redirect_uri, $scope, $code_challenge = null, $code_challenge_method = null ) {
		global $wpdb;

		$code    = $this->generate_token();
		$expires = gmdate( 'Y-m-d H:i:s', time() + 600 ); // 10 minutes.

		$wpdb->insert(
			$wpdb->prefix . 'bkx_oauth_codes',
			array(
				'authorization_code'    => $code,
				'client_id'             => $client_id,
				'user_id'               => $user_id,
				'redirect_uri'          => $redirect_uri,
				'expires'               => $expires,
				'scope'                 => $scope,
				'code_challenge'        => $code_challenge,
				'code_challenge_method' => $code_challenge_method,
			)
		);

		return $code;
	}

	/**
	 * Validate authorization code.
	 *
	 * @param string $code      Authorization code.
	 * @param string $client_id Client ID.
	 * @return object|null
	 */
	private function validate_authorization_code( $code, $client_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_oauth_codes WHERE authorization_code = %s AND client_id = %s AND expires > NOW()",
				$code,
				$client_id
			)
		);
	}

	/**
	 * Delete authorization code.
	 *
	 * @param string $code Authorization code.
	 */
	private function delete_authorization_code( $code ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->prefix . 'bkx_oauth_codes',
			array( 'authorization_code' => $code )
		);
	}

	/**
	 * Validate PKCE.
	 *
	 * @param string $verifier  Code verifier.
	 * @param string $challenge Code challenge.
	 * @param string $method    Challenge method.
	 * @return bool
	 */
	private function validate_pkce( $verifier, $challenge, $method ) {
		if ( 'S256' === $method ) {
			$computed = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
			return hash_equals( $challenge, $computed );
		}

		// Plain method.
		return hash_equals( $challenge, $verifier );
	}

	/**
	 * Generate random token.
	 *
	 * @return string
	 */
	private function generate_token() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Store access token.
	 *
	 * @param string   $token     Access token.
	 * @param string   $client_id Client ID.
	 * @param int|null $user_id   User ID.
	 * @param int      $expires   Expiration in seconds.
	 * @param string   $scope     Scope.
	 */
	private function store_access_token( $token, $client_id, $user_id, $expires, $scope ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bkx_oauth_tokens',
			array(
				'access_token' => $token,
				'client_id'    => $client_id,
				'user_id'      => $user_id,
				'expires'      => gmdate( 'Y-m-d H:i:s', time() + $expires ),
				'scope'        => $scope,
			)
		);
	}

	/**
	 * Store refresh token.
	 *
	 * @param string   $token     Refresh token.
	 * @param string   $client_id Client ID.
	 * @param int|null $user_id   User ID.
	 * @param int      $expires   Expiration in seconds.
	 * @param string   $scope     Scope.
	 */
	private function store_refresh_token( $token, $client_id, $user_id, $expires, $scope ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bkx_oauth_refresh_tokens',
			array(
				'refresh_token' => $token,
				'client_id'     => $client_id,
				'user_id'       => $user_id,
				'expires'       => gmdate( 'Y-m-d H:i:s', time() + $expires ),
				'scope'         => $scope,
			)
		);
	}

	/**
	 * Validate access token.
	 *
	 * @param string $token Access token.
	 * @return object|null
	 */
	private function validate_access_token( $token ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_oauth_tokens WHERE access_token = %s AND expires > NOW()",
				$token
			)
		);
	}

	/**
	 * Validate refresh token.
	 *
	 * @param string $token     Refresh token.
	 * @param string $client_id Client ID.
	 * @return object|null
	 */
	private function validate_refresh_token( $token, $client_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_oauth_refresh_tokens WHERE refresh_token = %s AND client_id = %s AND expires > NOW()",
				$token,
				$client_id
			)
		);
	}

	/**
	 * Delete access token.
	 *
	 * @param string $token Access token.
	 */
	private function delete_access_token( $token ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->prefix . 'bkx_oauth_tokens',
			array( 'access_token' => $token )
		);
	}

	/**
	 * Delete refresh token.
	 *
	 * @param string $token Refresh token.
	 */
	private function delete_refresh_token( $token ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->prefix . 'bkx_oauth_refresh_tokens',
			array( 'refresh_token' => $token )
		);
	}

	/**
	 * Get authorization header.
	 *
	 * @return string|null
	 */
	private function get_authorization_header() {
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( isset( $headers['Authorization'] ) ) {
				return $headers['Authorization'];
			}
		}

		return null;
	}

	/**
	 * Create OAuth client.
	 *
	 * @param array $data Client data.
	 * @return string|WP_Error Client ID or error.
	 */
	public function create_client( $data ) {
		global $wpdb;

		$client_id     = 'bkx_' . bin2hex( random_bytes( 16 ) );
		$client_secret = bin2hex( random_bytes( 32 ) );

		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_oauth_clients',
			array(
				'client_id'     => $client_id,
				'client_secret' => password_hash( $client_secret, PASSWORD_DEFAULT ),
				'name'          => sanitize_text_field( $data['name'] ),
				'description'   => sanitize_textarea_field( $data['description'] ?? '' ),
				'redirect_uri'  => esc_url_raw( $data['redirect_uri'] ),
				'grant_types'   => sanitize_text_field( $data['grant_types'] ?? 'authorization_code,refresh_token' ),
				'scope'         => sanitize_text_field( $data['scope'] ?? '' ),
				'user_id'       => get_current_user_id(),
			)
		);

		if ( ! $result ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create OAuth client.', 'bkx-enterprise-api' ) );
		}

		return array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
		);
	}
}

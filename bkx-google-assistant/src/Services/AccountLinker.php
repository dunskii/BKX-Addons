<?php
/**
 * Account Linker for Google Assistant.
 *
 * Handles OAuth 2.0 account linking between Google and WordPress.
 *
 * @package BookingX\GoogleAssistant
 */

namespace BookingX\GoogleAssistant\Services;

defined( 'ABSPATH' ) || exit;

/**
 * AccountLinker class.
 */
class AccountLinker {

	/**
	 * Handle authorization request from Google.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_auth_request( $request ) {
		$client_id     = $request->get_param( 'client_id' );
		$redirect_uri  = $request->get_param( 'redirect_uri' );
		$state         = $request->get_param( 'state' );
		$response_type = $request->get_param( 'response_type' );

		$settings = get_option( 'bkx_google_assistant_settings', array() );

		// Validate client ID.
		if ( empty( $settings['client_id'] ) || $client_id !== $settings['client_id'] ) {
			return new \WP_Error( 'invalid_client', 'Invalid client ID', array( 'status' => 400 ) );
		}

		// Store state for verification.
		set_transient( 'bkx_assistant_auth_state_' . $state, array(
			'redirect_uri' => $redirect_uri,
			'client_id'    => $client_id,
		), HOUR_IN_SECONDS );

		// If user is logged in, show consent screen.
		if ( is_user_logged_in() ) {
			include BKX_GOOGLE_ASSISTANT_PATH . 'templates/auth/consent.php';
			exit;
		}

		// Redirect to login with return URL.
		$login_url = wp_login_url( add_query_arg( array(
			'bkx_assistant_auth' => 1,
			'state'              => $state,
		), rest_url( 'bkx-assistant/v1/auth' ) ) );

		wp_safe_redirect( $login_url );
		exit;
	}

	/**
	 * Handle token exchange request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_token_request( $request ) {
		$grant_type    = $request->get_param( 'grant_type' );
		$client_id     = $request->get_param( 'client_id' );
		$client_secret = $request->get_param( 'client_secret' );

		$settings = get_option( 'bkx_google_assistant_settings', array() );

		// Validate client credentials.
		if ( $client_id !== $settings['client_id'] || $client_secret !== $settings['client_secret'] ) {
			return new \WP_Error( 'invalid_client', 'Invalid client credentials', array( 'status' => 401 ) );
		}

		if ( 'authorization_code' === $grant_type ) {
			return $this->exchange_code( $request->get_param( 'code' ) );
		}

		if ( 'refresh_token' === $grant_type ) {
			return $this->refresh_token( $request->get_param( 'refresh_token' ) );
		}

		return new \WP_Error( 'unsupported_grant_type', 'Unsupported grant type', array( 'status' => 400 ) );
	}

	/**
	 * Exchange authorization code for tokens.
	 *
	 * @param string $code Authorization code.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function exchange_code( $code ) {
		$code_data = get_transient( 'bkx_assistant_auth_code_' . $code );

		if ( ! $code_data ) {
			return new \WP_Error( 'invalid_code', 'Invalid or expired authorization code', array( 'status' => 400 ) );
		}

		delete_transient( 'bkx_assistant_auth_code_' . $code );

		$user_id = $code_data['user_id'];

		// Generate tokens.
		$access_token  = $this->generate_token();
		$refresh_token = $this->generate_token();
		$expires_in    = 3600; // 1 hour.

		// Store tokens.
		$this->store_tokens( $user_id, $access_token, $refresh_token, $expires_in );

		return rest_ensure_response( array(
			'access_token'  => $access_token,
			'token_type'    => 'Bearer',
			'expires_in'    => $expires_in,
			'refresh_token' => $refresh_token,
		) );
	}

	/**
	 * Refresh access token.
	 *
	 * @param string $refresh_token Refresh token.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function refresh_token( $refresh_token ) {
		global $wpdb;

		$account = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_assistant_accounts WHERE refresh_token = %s", // phpcs:ignore
				$refresh_token
			)
		);

		if ( ! $account ) {
			return new \WP_Error( 'invalid_token', 'Invalid refresh token', array( 'status' => 401 ) );
		}

		// Generate new access token.
		$new_access_token = $this->generate_token();
		$expires_in       = 3600;

		// Update token.
		$wpdb->update(
			$wpdb->prefix . 'bkx_assistant_accounts',
			array(
				'access_token'  => $new_access_token,
				'token_expires' => gmdate( 'Y-m-d H:i:s', time() + $expires_in ),
				'last_used'     => current_time( 'mysql', true ),
			),
			array( 'id' => $account->id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return rest_ensure_response( array(
			'access_token' => $new_access_token,
			'token_type'   => 'Bearer',
			'expires_in'   => $expires_in,
		) );
	}

	/**
	 * Store tokens for user.
	 *
	 * @param int    $user_id       WordPress user ID.
	 * @param string $access_token  Access token.
	 * @param string $refresh_token Refresh token.
	 * @param int    $expires_in    Expiration in seconds.
	 */
	private function store_tokens( $user_id, $access_token, $refresh_token, $expires_in ) {
		global $wpdb;

		$user = get_user_by( 'id', $user_id );

		$data = array(
			'wp_user_id'     => $user_id,
			'customer_email' => $user->user_email,
			'customer_name'  => $user->display_name,
			'access_token'   => $access_token,
			'refresh_token'  => $refresh_token,
			'token_expires'  => gmdate( 'Y-m-d H:i:s', time() + $expires_in ),
			'last_used'      => current_time( 'mysql', true ),
		);

		// Check if user already has an account link.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bkx_assistant_accounts WHERE wp_user_id = %d", // phpcs:ignore
				$user_id
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$wpdb->prefix . 'bkx_assistant_accounts',
				$data,
				array( 'id' => $existing ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$data['google_user_id'] = 'pending_' . $user_id;
			$data['linked_at']      = current_time( 'mysql', true );
			$wpdb->insert(
				$wpdb->prefix . 'bkx_assistant_accounts',
				$data
			);
		}
	}

	/**
	 * Generate secure token.
	 *
	 * @return string
	 */
	private function generate_token() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Get user by access token.
	 *
	 * @param string $access_token Access token.
	 * @return object|null User data or null.
	 */
	public function get_user_by_token( $access_token ) {
		global $wpdb;

		$account = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_assistant_accounts WHERE access_token = %s AND token_expires > %s", // phpcs:ignore
				$access_token,
				current_time( 'mysql', true )
			)
		);

		if ( ! $account ) {
			return null;
		}

		// Update last used.
		$wpdb->update(
			$wpdb->prefix . 'bkx_assistant_accounts',
			array( 'last_used' => current_time( 'mysql', true ) ),
			array( 'id' => $account->id )
		);

		return $account;
	}

	/**
	 * Link Google user ID to account.
	 *
	 * @param string $google_user_id Google user ID.
	 * @param int    $wp_user_id     WordPress user ID.
	 * @return bool
	 */
	public function link_google_user( $google_user_id, $wp_user_id ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'bkx_assistant_accounts',
			array( 'google_user_id' => $google_user_id ),
			array( 'wp_user_id' => $wp_user_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get account by Google user ID.
	 *
	 * @param string $google_user_id Google user ID.
	 * @return object|null
	 */
	public function get_by_google_user( $google_user_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_assistant_accounts WHERE google_user_id = %s", // phpcs:ignore
				$google_user_id
			)
		);
	}

	/**
	 * Get linked accounts count.
	 *
	 * @return int
	 */
	public function get_linked_count() {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_assistant_accounts" // phpcs:ignore
		);
	}

	/**
	 * Unlink account.
	 *
	 * @param int $account_id Account ID.
	 * @return bool
	 */
	public function unlink( $account_id ) {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . 'bkx_assistant_accounts',
			array( 'id' => $account_id ),
			array( '%d' )
		);
	}
}

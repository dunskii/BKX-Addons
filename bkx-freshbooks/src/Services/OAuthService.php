<?php
/**
 * OAuth Service for FreshBooks Integration.
 *
 * @package BookingX\FreshBooks\Services
 */

namespace BookingX\FreshBooks\Services;

defined( 'ABSPATH' ) || exit;

/**
 * OAuthService class.
 */
class OAuthService {

	/**
	 * FreshBooks OAuth URL.
	 */
	const OAUTH_URL = 'https://auth.freshbooks.com/oauth/authorize';

	/**
	 * FreshBooks Token URL.
	 */
	const TOKEN_URL = 'https://api.freshbooks.com/auth/oauth/token';

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
	 * Get the OAuth authorization URL.
	 *
	 * @return string
	 */
	public function get_auth_url() {
		$client_id    = $this->addon->get_setting( 'client_id' );
		$redirect_uri = $this->get_redirect_uri();
		$state        = wp_create_nonce( 'bkx_freshbooks_oauth' );

		$params = array(
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'state'         => $state,
		);

		return self::OAUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Get the redirect URI.
	 *
	 * @return string
	 */
	public function get_redirect_uri() {
		return admin_url( 'admin.php?page=bkx-freshbooks&bkx_freshbooks_oauth=1' );
	}

	/**
	 * Exchange authorization code for tokens.
	 *
	 * @param string $code Authorization code.
	 * @return array|\WP_Error
	 */
	public function exchange_code( $code ) {
		$client_id     = $this->addon->get_setting( 'client_id' );
		$client_secret = $this->addon->get_setting( 'client_secret' );
		$redirect_uri  = $this->get_redirect_uri();

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'client_id'     => $client_id,
						'client_secret' => $client_secret,
						'redirect_uri'  => $redirect_uri,
						'grant_type'    => 'authorization_code',
						'code'          => $code,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 || empty( $body['access_token'] ) ) {
			$error = $body['error_description'] ?? $body['error'] ?? __( 'Failed to get access token.', 'bkx-freshbooks' );
			return new \WP_Error( 'oauth_error', $error );
		}

		// Save tokens.
		$this->addon->update_setting( 'access_token', $body['access_token'] );
		$this->addon->update_setting( 'refresh_token', $body['refresh_token'] ?? '' );
		$this->addon->update_setting( 'token_expires', time() + ( $body['expires_in'] ?? 43200 ) );

		// Fetch account info.
		$this->fetch_account_info();

		return array(
			'access_token' => $body['access_token'],
		);
	}

	/**
	 * Refresh the access token.
	 *
	 * @return array|\WP_Error
	 */
	public function refresh_token() {
		$client_id     = $this->addon->get_setting( 'client_id' );
		$client_secret = $this->addon->get_setting( 'client_secret' );
		$refresh_token = $this->addon->get_setting( 'refresh_token' );

		if ( empty( $refresh_token ) ) {
			return new \WP_Error( 'no_refresh_token', __( 'No refresh token available.', 'bkx-freshbooks' ) );
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'client_id'     => $client_id,
						'client_secret' => $client_secret,
						'grant_type'    => 'refresh_token',
						'refresh_token' => $refresh_token,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 || empty( $body['access_token'] ) ) {
			$error = $body['error_description'] ?? $body['error'] ?? __( 'Failed to refresh token.', 'bkx-freshbooks' );
			return new \WP_Error( 'refresh_error', $error );
		}

		// Save new tokens.
		$this->addon->update_setting( 'access_token', $body['access_token'] );
		if ( ! empty( $body['refresh_token'] ) ) {
			$this->addon->update_setting( 'refresh_token', $body['refresh_token'] );
		}
		$this->addon->update_setting( 'token_expires', time() + ( $body['expires_in'] ?? 43200 ) );

		return array(
			'access_token' => $body['access_token'],
		);
	}

	/**
	 * Refresh token if needed.
	 *
	 * @return bool
	 */
	public function refresh_token_if_needed() {
		$token_expires = $this->addon->get_setting( 'token_expires', 0 );

		// Refresh if token expires in less than 5 minutes.
		if ( $token_expires > 0 && ( $token_expires - time() ) < 300 ) {
			$result = $this->refresh_token();
			return ! is_wp_error( $result );
		}

		return true;
	}

	/**
	 * Fetch and store account info.
	 */
	private function fetch_account_info() {
		$api   = $this->addon->get_service( 'api_client' );
		$me    = $api->get_current_user();

		if ( is_wp_error( $me ) || empty( $me['response']['memberships'] ) ) {
			return;
		}

		// Use the first business membership.
		$membership = $me['response']['memberships'][0];
		$this->addon->update_setting( 'account_id', $membership['business']['account_id'] ?? '' );
		$this->addon->update_setting( 'business_id', $membership['business']['id'] ?? '' );
	}

	/**
	 * Get valid access token.
	 *
	 * @return string|\WP_Error
	 */
	public function get_valid_token() {
		$token_expires = $this->addon->get_setting( 'token_expires', 0 );

		if ( $token_expires > 0 && ( $token_expires - time() ) < 60 ) {
			$result = $this->refresh_token();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $this->addon->get_setting( 'access_token' );
	}
}

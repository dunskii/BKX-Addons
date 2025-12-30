<?php
/**
 * OAuth Service for MYOB Integration.
 *
 * Handles MYOB OAuth 2.0 authentication flow.
 *
 * @package BookingX\MYOB\Services
 */

namespace BookingX\MYOB\Services;

defined( 'ABSPATH' ) || exit;

/**
 * OAuthService class.
 */
class OAuthService {

	/**
	 * MYOB OAuth URL.
	 *
	 * @var string
	 */
	const OAUTH_URL = 'https://secure.myob.com/oauth2/account/authorize';

	/**
	 * MYOB Token URL.
	 *
	 * @var string
	 */
	const TOKEN_URL = 'https://secure.myob.com/oauth2/v1/authorize';

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
	 * Get the OAuth authorization URL.
	 *
	 * @return string
	 */
	public function get_auth_url() {
		$client_id    = $this->addon->get_setting( 'client_id' );
		$redirect_uri = $this->get_redirect_uri();
		$state        = wp_create_nonce( 'bkx_myob_oauth' );

		$params = array(
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => 'CompanyFile',
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
		return admin_url( 'admin.php?page=bkx-myob&bkx_myob_oauth=1' );
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
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => $redirect_uri,
					'grant_type'    => 'authorization_code',
					'code'          => $code,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 || empty( $body['access_token'] ) ) {
			$error = $body['error_description'] ?? $body['error'] ?? __( 'Failed to get access token.', 'bkx-myob' );
			return new \WP_Error( 'oauth_error', $error );
		}

		// Save tokens.
		$this->addon->update_setting( 'access_token', $body['access_token'] );
		$this->addon->update_setting( 'refresh_token', $body['refresh_token'] ?? '' );
		$this->addon->update_setting( 'token_expires', time() + ( $body['expires_in'] ?? 1200 ) );

		// Fetch company files.
		$this->fetch_company_files();

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
			return new \WP_Error( 'no_refresh_token', __( 'No refresh token available.', 'bkx-myob' ) );
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 || empty( $body['access_token'] ) ) {
			$error = $body['error_description'] ?? $body['error'] ?? __( 'Failed to refresh token.', 'bkx-myob' );
			return new \WP_Error( 'refresh_error', $error );
		}

		// Save new tokens.
		$this->addon->update_setting( 'access_token', $body['access_token'] );
		if ( ! empty( $body['refresh_token'] ) ) {
			$this->addon->update_setting( 'refresh_token', $body['refresh_token'] );
		}
		$this->addon->update_setting( 'token_expires', time() + ( $body['expires_in'] ?? 1200 ) );

		return array(
			'access_token' => $body['access_token'],
		);
	}

	/**
	 * Refresh token if needed.
	 *
	 * @return bool True if refreshed or not needed, false on error.
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
	 * Fetch and store company files.
	 */
	private function fetch_company_files() {
		$api    = $this->addon->get_service( 'api_client' );
		$files  = $api->get_company_files();

		if ( is_wp_error( $files ) || empty( $files ) ) {
			return;
		}

		// Use the first company file by default.
		$first_file = $files[0];
		$this->addon->update_setting( 'company_file_id', $first_file['Id'] ?? '' );
		$this->addon->update_setting( 'company_file_name', $first_file['Name'] ?? '' );
	}

	/**
	 * Check if the current token is valid.
	 *
	 * @return bool
	 */
	public function is_token_valid() {
		$access_token  = $this->addon->get_setting( 'access_token' );
		$token_expires = $this->addon->get_setting( 'token_expires', 0 );

		if ( empty( $access_token ) ) {
			return false;
		}

		// Token is valid if it expires more than 60 seconds from now.
		return ( $token_expires - time() ) > 60;
	}

	/**
	 * Get valid access token, refreshing if needed.
	 *
	 * @return string|\WP_Error
	 */
	public function get_valid_token() {
		if ( ! $this->is_token_valid() ) {
			$result = $this->refresh_token();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $this->addon->get_setting( 'access_token' );
	}
}

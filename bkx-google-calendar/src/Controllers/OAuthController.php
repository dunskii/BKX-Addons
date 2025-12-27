<?php
/**
 * OAuth Controller
 *
 * Handles Google OAuth authentication flow.
 *
 * @package BookingX\GoogleCalendar\Controllers
 * @since   1.0.0
 */

namespace BookingX\GoogleCalendar\Controllers;

use BookingX\GoogleCalendar\GoogleCalendarAddon;
use BookingX\GoogleCalendar\Services\GoogleApiService;
use BookingX\AddonSDK\Services\EncryptionService;
use WP_Error;

/**
 * OAuth controller class.
 *
 * @since 1.0.0
 */
class OAuthController {

	/**
	 * Addon instance.
	 *
	 * @var GoogleCalendarAddon
	 */
	protected GoogleCalendarAddon $addon;

	/**
	 * Google API service.
	 *
	 * @var GoogleApiService
	 */
	protected GoogleApiService $google_api;

	/**
	 * OAuth scopes required.
	 *
	 * @var array
	 */
	protected array $scopes = array(
		'https://www.googleapis.com/auth/calendar',
		'https://www.googleapis.com/auth/calendar.events',
		'https://www.googleapis.com/auth/userinfo.email',
	);

	/**
	 * Constructor.
	 *
	 * @param GoogleCalendarAddon $addon Addon instance.
	 * @param GoogleApiService    $google_api Google API service.
	 */
	public function __construct( GoogleCalendarAddon $addon, GoogleApiService $google_api ) {
		$this->addon      = $addon;
		$this->google_api = $google_api;
	}

	/**
	 * Get authorization URL.
	 *
	 * @param int|null $staff_id Optional staff ID.
	 * @return string|WP_Error Auth URL or error.
	 */
	public function get_auth_url( ?int $staff_id = null ) {
		$client_id = $this->get_client_id();

		if ( empty( $client_id ) ) {
			return new WP_Error(
				'missing_credentials',
				__( 'Google API credentials are not configured.', 'bkx-google-calendar' )
			);
		}

		$redirect_uri = $this->google_api->get_redirect_uri( $staff_id );
		$state        = $this->generate_state( $staff_id );

		$params = array(
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => implode( ' ', $this->scopes ),
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		);

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
	}

	/**
	 * Get staff authorization URL.
	 *
	 * @param int $staff_id Staff ID.
	 * @return string|WP_Error Auth URL or error.
	 */
	public function get_staff_auth_url( int $staff_id ) {
		return $this->get_auth_url( $staff_id );
	}

	/**
	 * Handle OAuth callback.
	 *
	 * @return void
	 */
	public function handle_oauth_callback(): void {
		// Check if this is our callback.
		if ( ! isset( $_GET['page'] ) || 'bkx-google-calendar' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) || 'oauth_callback' !== $_GET['action'] ) {
			return;
		}

		// Verify user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'bkx-google-calendar' ) );
		}

		// Check for error.
		if ( isset( $_GET['error'] ) ) {
			$this->redirect_with_error( sanitize_text_field( wp_unslash( $_GET['error'] ) ) );
			return;
		}

		// Verify state.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$state_data = $this->verify_state( $state );

		if ( ! $state_data ) {
			$this->redirect_with_error( __( 'Invalid state parameter.', 'bkx-google-calendar' ) );
			return;
		}

		// Get authorization code.
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		if ( empty( $code ) ) {
			$this->redirect_with_error( __( 'Authorization code missing.', 'bkx-google-calendar' ) );
			return;
		}

		$staff_id = $state_data['staff_id'] ?? null;

		// Exchange code for tokens.
		$tokens = $this->google_api->exchange_code( $code, $staff_id );

		if ( is_wp_error( $tokens ) ) {
			$this->redirect_with_error( $tokens->get_error_message() );
			return;
		}

		// Store connection.
		$result = $this->google_api->store_connection( $tokens, $staff_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( $result->get_error_message() );
			return;
		}

		// Redirect with success.
		$this->redirect_with_success( $staff_id );
	}

	/**
	 * Disconnect Google account.
	 *
	 * @return bool|WP_Error
	 */
	public function disconnect() {
		$result = $this->google_api->remove_connection();

		if ( ! $result ) {
			return new WP_Error( 'disconnect_failed', __( 'Failed to disconnect.', 'bkx-google-calendar' ) );
		}

		return true;
	}

	/**
	 * Disconnect staff calendar.
	 *
	 * @param int $staff_id Staff ID.
	 * @return bool|WP_Error
	 */
	public function disconnect_staff( int $staff_id ) {
		$result = $this->google_api->remove_connection( $staff_id );

		if ( ! $result ) {
			return new WP_Error( 'disconnect_failed', __( 'Failed to disconnect.', 'bkx-google-calendar' ) );
		}

		// Clear staff meta.
		delete_post_meta( $staff_id, '_bkx_google_calendar_id' );
		delete_post_meta( $staff_id, '_bkx_google_connected' );

		return true;
	}

	/**
	 * Generate state parameter.
	 *
	 * @param int|null $staff_id Optional staff ID.
	 * @return string
	 */
	protected function generate_state( ?int $staff_id = null ): string {
		$data = array(
			'nonce'    => wp_create_nonce( 'bkx_google_oauth' ),
			'user_id'  => get_current_user_id(),
			'staff_id' => $staff_id,
			'time'     => time(),
		);

		$json      = wp_json_encode( $data );
		$encrypted = EncryptionService::encrypt( $json );

		return base64_encode( $encrypted );
	}

	/**
	 * Verify state parameter.
	 *
	 * @param string $state State parameter.
	 * @return array|false State data or false.
	 */
	protected function verify_state( string $state ) {
		$decoded   = base64_decode( $state );
		$decrypted = EncryptionService::decrypt( $decoded );

		if ( ! $decrypted ) {
			return false;
		}

		$data = json_decode( $decrypted, true );

		if ( ! $data ) {
			return false;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( $data['nonce'] ?? '', 'bkx_google_oauth' ) ) {
			return false;
		}

		// Verify user.
		if ( ( $data['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return false;
		}

		// Verify time (10 minute expiry).
		if ( time() - ( $data['time'] ?? 0 ) > 600 ) {
			return false;
		}

		return $data;
	}

	/**
	 * Redirect with error.
	 *
	 * @param string $error Error message.
	 * @return void
	 */
	protected function redirect_with_error( string $error ): void {
		$url = add_query_arg(
			array(
				'page'          => 'bkx-google-calendar',
				'error_message' => rawurlencode( $error ),
			),
			admin_url( 'edit.php?post_type=bkx_booking' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Redirect with success.
	 *
	 * @param int|null $staff_id Optional staff ID.
	 * @return void
	 */
	protected function redirect_with_success( ?int $staff_id = null ): void {
		if ( $staff_id ) {
			// Redirect to staff edit page.
			$url = add_query_arg(
				array(
					'post'    => $staff_id,
					'action'  => 'edit',
					'message' => 'google_connected',
				),
				admin_url( 'post.php' )
			);
		} else {
			// Redirect to settings page.
			$url = add_query_arg(
				array(
					'page'    => 'bkx-google-calendar',
					'message' => 'connected',
				),
				admin_url( 'edit.php?post_type=bkx_booking' )
			);
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Get client ID.
	 *
	 * @return string
	 */
	protected function get_client_id(): string {
		$value = $this->addon->get_setting( 'client_id', '' );

		if ( empty( $value ) ) {
			return '';
		}

		return EncryptionService::decrypt( $value );
	}
}

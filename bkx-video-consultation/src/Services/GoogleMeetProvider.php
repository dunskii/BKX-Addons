<?php
/**
 * Google Meet Provider Service.
 *
 * @package BookingX\VideoConsultation
 * @since   1.0.0
 */

namespace BookingX\VideoConsultation\Services;

/**
 * GoogleMeetProvider class.
 *
 * Handles Google Meet integration via Google Calendar API.
 *
 * @since 1.0.0
 */
class GoogleMeetProvider {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Check if Google Meet is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->settings['google_meet_enabled'] ) &&
			! empty( $this->settings['google_client_id'] ) &&
			! empty( $this->settings['google_client_secret'] );
	}

	/**
	 * Create a Google Meet meeting via Calendar event.
	 *
	 * @since 1.0.0
	 *
	 * @param string $room_id Internal room ID.
	 * @param array  $booking_data Booking data.
	 * @return array|\WP_Error
	 */
	public function create_meeting( $room_id, $booking_data ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'google_disabled', __( 'Google Meet is not configured.', 'bkx-video-consultation' ) );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		// Calculate start and end times.
		$start_time = $booking_data['booking_date'] . 'T' . $booking_data['booking_time'] . ':00';
		$duration   = $booking_data['duration'] ?? 60;
		$end_time   = gmdate( 'Y-m-d\TH:i:s', strtotime( $start_time ) + ( $duration * 60 ) );

		$timezone = wp_timezone_string();

		// Create calendar event with Google Meet.
		$event_data = array(
			'summary'     => sprintf(
				/* translators: %s: Customer name */
				__( 'Video Consultation with %s', 'bkx-video-consultation' ),
				$booking_data['customer_name'] ?? __( 'Customer', 'bkx-video-consultation' )
			),
			'description' => sprintf(
				/* translators: %s: Room ID */
				__( 'BookingX Video Consultation - Room: %s', 'bkx-video-consultation' ),
				$room_id
			),
			'start'       => array(
				'dateTime' => $start_time,
				'timeZone' => $timezone,
			),
			'end'         => array(
				'dateTime' => $end_time,
				'timeZone' => $timezone,
			),
			'conferenceData' => array(
				'createRequest' => array(
					'requestId'             => $room_id,
					'conferenceSolutionKey' => array(
						'type' => 'hangoutsMeet',
					),
				),
			),
			'attendees'   => array(),
		);

		// Add customer as attendee.
		if ( ! empty( $booking_data['customer_email'] ) ) {
			$event_data['attendees'][] = array(
				'email' => $booking_data['customer_email'],
			);
		}

		$response = wp_remote_post(
			'https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $event_data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$error_message = $body['error']['message'] ?? __( 'Failed to create Google Meet.', 'bkx-video-consultation' );
			return new \WP_Error( 'google_api_error', $error_message );
		}

		$meet_link = $body['conferenceData']['entryPoints'][0]['uri'] ?? '';

		if ( empty( $meet_link ) ) {
			return new \WP_Error( 'no_meet_link', __( 'Google Meet link was not generated.', 'bkx-video-consultation' ) );
		}

		return array(
			'host_url'        => $meet_link,
			'participant_url' => $meet_link,
			'metadata'        => array(
				'google_event_id'   => $body['id'],
				'google_meet_link'  => $meet_link,
				'google_html_link'  => $body['htmlLink'] ?? '',
			),
		);
	}

	/**
	 * Delete a Google Meet event.
	 *
	 * @since 1.0.0
	 *
	 * @param string $event_id Google Calendar event ID.
	 * @return bool|\WP_Error
	 */
	public function delete_meeting( $event_id ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'google_disabled', __( 'Google Meet is not configured.', 'bkx-video-consultation' ) );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_request(
			'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . $event_id,
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 204 && $status !== 200 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return new \WP_Error(
				'google_api_error',
				$body['error']['message'] ?? __( 'Failed to delete Google Meet event.', 'bkx-video-consultation' )
			);
		}

		return true;
	}

	/**
	 * Get OAuth authorization URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_auth_url() {
		$client_id    = $this->settings['google_client_id'] ?? '';
		$redirect_uri = admin_url( 'admin.php?page=bkx-video-consultation&action=google_callback' );

		$params = array(
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => 'https://www.googleapis.com/auth/calendar.events',
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => wp_create_nonce( 'google_oauth' ),
		);

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
	}

	/**
	 * Handle OAuth callback.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code Authorization code.
	 * @return bool|\WP_Error
	 */
	public function handle_callback( $code ) {
		$client_id     = $this->settings['google_client_id'] ?? '';
		$client_secret = $this->settings['google_client_secret'] ?? '';
		$redirect_uri  = admin_url( 'admin.php?page=bkx-video-consultation&action=google_callback' );

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body' => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => $redirect_uri,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return new \WP_Error( 'token_error', $body['error_description'] ?? __( 'Failed to get access token.', 'bkx-video-consultation' ) );
		}

		// Store tokens.
		update_option( 'bkx_google_access_token', $body['access_token'] );
		update_option( 'bkx_google_refresh_token', $body['refresh_token'] ?? '' );
		update_option( 'bkx_google_token_expires', time() + ( $body['expires_in'] ?? 3600 ) );

		return true;
	}

	/**
	 * Refresh access token.
	 *
	 * @since 1.0.0
	 *
	 * @return string|\WP_Error
	 */
	private function refresh_token() {
		$refresh_token = get_option( 'bkx_google_refresh_token' );
		if ( empty( $refresh_token ) ) {
			return new \WP_Error( 'no_refresh_token', __( 'No refresh token available. Please reconnect Google.', 'bkx-video-consultation' ) );
		}

		$client_id     = $this->settings['google_client_id'] ?? '';
		$client_secret = $this->settings['google_client_secret'] ?? '';

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body' => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return new \WP_Error( 'refresh_error', $body['error_description'] ?? __( 'Failed to refresh token.', 'bkx-video-consultation' ) );
		}

		update_option( 'bkx_google_access_token', $body['access_token'] );
		update_option( 'bkx_google_token_expires', time() + ( $body['expires_in'] ?? 3600 ) );

		return $body['access_token'];
	}

	/**
	 * Get access token.
	 *
	 * @since 1.0.0
	 *
	 * @return string|\WP_Error
	 */
	private function get_access_token() {
		$token   = get_option( 'bkx_google_access_token' );
		$expires = get_option( 'bkx_google_token_expires', 0 );

		// Check if token is expired.
		if ( empty( $token ) || $expires < time() + 60 ) {
			return $this->refresh_token();
		}

		return $token;
	}

	/**
	 * Check if connected to Google.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_connected() {
		$token = get_option( 'bkx_google_access_token' );
		return ! empty( $token );
	}

	/**
	 * Disconnect from Google.
	 *
	 * @since 1.0.0
	 */
	public function disconnect() {
		delete_option( 'bkx_google_access_token' );
		delete_option( 'bkx_google_refresh_token' );
		delete_option( 'bkx_google_token_expires' );
	}
}

<?php
/**
 * Zoom Provider Service.
 *
 * @package BookingX\VideoConsultation
 * @since   1.0.0
 */

namespace BookingX\VideoConsultation\Services;

/**
 * ZoomProvider class.
 *
 * Handles Zoom meeting integration.
 *
 * @since 1.0.0
 */
class ZoomProvider {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://api.zoom.us/v2';

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
	 * Check if Zoom is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->settings['zoom_enabled'] ) &&
			! empty( $this->settings['zoom_api_key'] ) &&
			! empty( $this->settings['zoom_api_secret'] );
	}

	/**
	 * Create a Zoom meeting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $room_id Internal room ID.
	 * @param array  $booking_data Booking data.
	 * @return array|\WP_Error
	 */
	public function create_meeting( $room_id, $booking_data ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'zoom_disabled', __( 'Zoom is not configured.', 'bkx-video-consultation' ) );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		// Build meeting data.
		$meeting_data = array(
			'topic'      => sprintf(
				/* translators: %s: Booking ID */
				__( 'BookingX Consultation - %s', 'bkx-video-consultation' ),
				$room_id
			),
			'type'       => 2, // Scheduled meeting.
			'start_time' => $this->format_zoom_time( $booking_data['booking_date'], $booking_data['booking_time'] ),
			'duration'   => $booking_data['duration'] ?? 60,
			'timezone'   => wp_timezone_string(),
			'settings'   => array(
				'host_video'        => true,
				'participant_video' => true,
				'join_before_host'  => false,
				'mute_upon_entry'   => true,
				'waiting_room'      => $this->settings['enable_waiting_room'] ?? true,
				'auto_recording'    => $this->settings['enable_recording'] ? 'cloud' : 'none',
			),
		);

		$response = wp_remote_post(
			$this->api_base . '/users/me/meetings',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $meeting_data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( wp_remote_retrieve_response_code( $response ) !== 201 ) {
			return new \WP_Error(
				'zoom_api_error',
				$body['message'] ?? __( 'Failed to create Zoom meeting.', 'bkx-video-consultation' )
			);
		}

		return array(
			'host_url'        => $body['start_url'],
			'participant_url' => $body['join_url'],
			'metadata'        => array(
				'zoom_meeting_id' => $body['id'],
				'zoom_password'   => $body['password'] ?? '',
				'zoom_host_key'   => $body['h323_password'] ?? '',
			),
		);
	}

	/**
	 * Delete a Zoom meeting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $meeting_id Zoom meeting ID.
	 * @return bool|\WP_Error
	 */
	public function delete_meeting( $meeting_id ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'zoom_disabled', __( 'Zoom is not configured.', 'bkx-video-consultation' ) );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_request(
			$this->api_base . '/meetings/' . $meeting_id,
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
				'zoom_api_error',
				$body['message'] ?? __( 'Failed to delete Zoom meeting.', 'bkx-video-consultation' )
			);
		}

		return true;
	}

	/**
	 * Get meeting recordings.
	 *
	 * @since 1.0.0
	 *
	 * @param string $meeting_id Zoom meeting ID.
	 * @return array|\WP_Error
	 */
	public function get_recordings( $meeting_id ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'zoom_disabled', __( 'Zoom is not configured.', 'bkx-video-consultation' ) );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_get(
			$this->api_base . '/meetings/' . $meeting_id . '/recordings',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new \WP_Error(
				'zoom_api_error',
				$body['message'] ?? __( 'Failed to get recordings.', 'bkx-video-consultation' )
			);
		}

		return $body['recording_files'] ?? array();
	}

	/**
	 * Handle Zoom webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$body = $request->get_json_params();

		// Verify webhook if secret is set.
		$webhook_secret = $this->settings['zoom_webhook_secret'] ?? '';
		if ( ! empty( $webhook_secret ) ) {
			$signature = $request->get_header( 'x-zm-signature' );
			$timestamp = $request->get_header( 'x-zm-request-timestamp' );

			if ( ! $this->verify_webhook_signature( $signature, $timestamp, $request->get_body(), $webhook_secret ) ) {
				return new \WP_REST_Response( array( 'error' => 'Invalid signature' ), 401 );
			}
		}

		// Handle endpoint validation.
		if ( isset( $body['event'] ) && 'endpoint.url_validation' === $body['event'] ) {
			$plain_token = $body['payload']['plainToken'] ?? '';
			$encrypted_token = hash_hmac( 'sha256', $plain_token, $webhook_secret );

			return new \WP_REST_Response(
				array(
					'plainToken'     => $plain_token,
					'encryptedToken' => $encrypted_token,
				),
				200
			);
		}

		$event = $body['event'] ?? '';

		switch ( $event ) {
			case 'meeting.started':
				$this->handle_meeting_started( $body['payload']['object'] );
				break;

			case 'meeting.ended':
				$this->handle_meeting_ended( $body['payload']['object'] );
				break;

			case 'meeting.participant_joined':
				$this->handle_participant_joined( $body['payload']['object'] );
				break;

			case 'recording.completed':
				$this->handle_recording_completed( $body['payload']['object'] );
				break;
		}

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Verify webhook signature.
	 *
	 * @since 1.0.0
	 *
	 * @param string $signature Signature from header.
	 * @param string $timestamp Timestamp from header.
	 * @param string $body Request body.
	 * @param string $secret Webhook secret.
	 * @return bool
	 */
	private function verify_webhook_signature( $signature, $timestamp, $body, $secret ) {
		$message = 'v0:' . $timestamp . ':' . $body;
		$expected = 'v0=' . hash_hmac( 'sha256', $message, $secret );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Handle meeting started webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param array $meeting Meeting data.
	 */
	private function handle_meeting_started( $meeting ) {
		global $wpdb;

		$zoom_id = $meeting['id'] ?? '';
		if ( empty( $zoom_id ) ) {
			return;
		}

		// Find room by Zoom meeting ID.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$room = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE metadata LIKE %s",
				$wpdb->prefix . 'bkx_video_rooms',
				'%"zoom_meeting_id":' . $zoom_id . '%'
			)
		);

		if ( $room ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'bkx_video_rooms',
				array(
					'status'       => 'active',
					'actual_start' => current_time( 'mysql' ),
				),
				array( 'id' => $room->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Handle meeting ended webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param array $meeting Meeting data.
	 */
	private function handle_meeting_ended( $meeting ) {
		global $wpdb;

		$zoom_id = $meeting['id'] ?? '';
		if ( empty( $zoom_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$room = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE metadata LIKE %s",
				$wpdb->prefix . 'bkx_video_rooms',
				'%"zoom_meeting_id":' . $zoom_id . '%'
			)
		);

		if ( $room ) {
			$duration = 0;
			if ( $room->actual_start ) {
				$duration = round( ( time() - strtotime( $room->actual_start ) ) / 60 );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'bkx_video_rooms',
				array(
					'status'           => 'ended',
					'actual_end'       => current_time( 'mysql' ),
					'duration_minutes' => $duration,
				),
				array( 'id' => $room->id ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Handle participant joined webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Participant data.
	 */
	private function handle_participant_joined( $data ) {
		// Log participant join event.
		global $wpdb;

		$zoom_id = $data['id'] ?? '';
		if ( empty( $zoom_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$room = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE metadata LIKE %s",
				$wpdb->prefix . 'bkx_video_rooms',
				'%"zoom_meeting_id":' . $zoom_id . '%'
			)
		);

		if ( $room ) {
			$participant = $data['participant'] ?? array();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->prefix . 'bkx_video_session_logs',
				array(
					'room_id'          => $room->id,
					'participant_type' => 'participant',
					'event_type'       => 'zoom_join',
					'event_data'       => wp_json_encode( $participant ),
				),
				array( '%d', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Handle recording completed webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Recording data.
	 */
	private function handle_recording_completed( $data ) {
		global $wpdb;

		$zoom_id = $data['id'] ?? '';
		if ( empty( $zoom_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$room = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE metadata LIKE %s",
				$wpdb->prefix . 'bkx_video_rooms',
				'%"zoom_meeting_id":' . $zoom_id . '%'
			)
		);

		if ( ! $room ) {
			return;
		}

		$recording_files = $data['recording_files'] ?? array();

		foreach ( $recording_files as $file ) {
			if ( 'MP4' !== $file['file_type'] ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->prefix . 'bkx_video_recordings',
				array(
					'room_id'          => $room->id,
					'booking_id'       => $room->booking_id,
					'recording_id'     => $file['id'],
					'file_url'         => $file['download_url'],
					'file_size'        => $file['file_size'] ?? 0,
					'duration_seconds' => $file['recording_end'] ? strtotime( $file['recording_end'] ) - strtotime( $file['recording_start'] ) : 0,
					'format'           => 'mp4',
					'status'           => 'completed',
				),
				array( '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
			);
		}

		// Update room with recording URL.
		$first_mp4 = array_filter( $recording_files, fn( $f ) => $f['file_type'] === 'MP4' );
		if ( ! empty( $first_mp4 ) ) {
			$first_mp4 = reset( $first_mp4 );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'bkx_video_rooms',
				array( 'recording_url' => $first_mp4['download_url'] ),
				array( 'id' => $room->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Get access token.
	 *
	 * @since 1.0.0
	 *
	 * @return string|\WP_Error
	 */
	private function get_access_token() {
		$cached = get_transient( 'bkx_zoom_access_token' );
		if ( $cached ) {
			return $cached;
		}

		$api_key    = $this->settings['zoom_api_key'] ?? '';
		$api_secret = $this->settings['zoom_api_secret'] ?? '';

		if ( empty( $api_key ) || empty( $api_secret ) ) {
			return new \WP_Error( 'missing_credentials', __( 'Zoom API credentials not configured.', 'bkx-video-consultation' ) );
		}

		// For Server-to-Server OAuth.
		$account_id = $this->settings['zoom_account_id'] ?? '';

		$response = wp_remote_post(
			'https://zoom.us/oauth/token',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . $api_secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type' => 'account_credentials',
					'account_id' => $account_id,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			return new \WP_Error( 'token_error', $body['reason'] ?? __( 'Failed to get Zoom access token.', 'bkx-video-consultation' ) );
		}

		$expires_in = $body['expires_in'] ?? 3600;
		set_transient( 'bkx_zoom_access_token', $body['access_token'], $expires_in - 60 );

		return $body['access_token'];
	}

	/**
	 * Format time for Zoom API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date Date string.
	 * @param string $time Time string.
	 * @return string
	 */
	private function format_zoom_time( $date, $time ) {
		$datetime = $date . ' ' . $time;
		return gmdate( 'Y-m-d\TH:i:s', strtotime( $datetime ) );
	}
}

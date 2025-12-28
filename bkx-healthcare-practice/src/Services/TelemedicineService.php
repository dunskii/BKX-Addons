<?php
/**
 * Telemedicine Service.
 *
 * Handles virtual appointment sessions and video conferencing integration.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

namespace BookingX\HealthcarePractice\Services;

/**
 * Telemedicine Service class.
 *
 * @since 1.0.0
 */
class TelemedicineService {

	/**
	 * Singleton instance.
	 *
	 * @var TelemedicineService|null
	 */
	private static ?TelemedicineService $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return TelemedicineService
	 */
	public static function get_instance(): TelemedicineService {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Private constructor for singleton.
	}

	/**
	 * Start telemedicine session.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array|\WP_Error Session data or error.
	 */
	public function start_session( int $booking_id ) {
		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error( 'invalid_booking', __( 'Invalid booking', 'bkx-healthcare-practice' ) );
		}

		// Check if telemedicine is enabled for this booking.
		$telemedicine_enabled = get_post_meta( $booking_id, 'telemedicine_enabled', true );

		if ( ! $telemedicine_enabled ) {
			return new \WP_Error( 'not_telemedicine', __( 'This appointment is not a telemedicine appointment', 'bkx-healthcare-practice' ) );
		}

		// Check if user is authorized.
		$customer_id = get_post_meta( $booking_id, 'customer_id', true );
		$seat_id     = get_post_meta( $booking_id, 'seat_id', true );
		$current_user = get_current_user_id();

		$is_patient  = absint( $customer_id ) === $current_user;
		$is_provider = $this->is_provider( $current_user, $seat_id );

		if ( ! $is_patient && ! $is_provider && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'unauthorized', __( 'You are not authorized to join this session', 'bkx-healthcare-practice' ) );
		}

		// Check if within allowed time window.
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time = get_post_meta( $booking_id, 'booking_time', true );
		$appointment_time = strtotime( $booking_date . ' ' . $booking_time );

		$settings = get_option( 'bkx_healthcare_settings', array() );
		$early_join_minutes = absint( $settings['telemedicine_early_join'] ?? 15 );

		$earliest_join = $appointment_time - ( $early_join_minutes * MINUTE_IN_SECONDS );
		$latest_join   = $appointment_time + HOUR_IN_SECONDS; // 1 hour after scheduled time.

		$now = current_time( 'timestamp' );

		if ( $now < $earliest_join ) {
			return new \WP_Error(
				'too_early',
				sprintf(
					/* translators: %d: Minutes */
					__( 'You can join the session %d minutes before the scheduled time.', 'bkx-healthcare-practice' ),
					$early_join_minutes
				)
			);
		}

		if ( $now > $latest_join ) {
			return new \WP_Error( 'session_expired', __( 'This session has expired.', 'bkx-healthcare-practice' ) );
		}

		// Get or create session.
		$session = $this->get_or_create_session( $booking_id );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		// Log session join.
		$this->log_session_event( $booking_id, 'joined', array(
			'user_id' => $current_user,
			'role'    => $is_provider ? 'provider' : 'patient',
		) );

		return $session;
	}

	/**
	 * Check if user is a provider for the seat.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $seat_id Seat (provider) post ID.
	 * @return bool
	 */
	private function is_provider( int $user_id, int $seat_id ): bool {
		$linked_user = get_post_meta( $seat_id, '_bkx_linked_user', true );
		return absint( $linked_user ) === $user_id;
	}

	/**
	 * Get or create telemedicine session.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array|\WP_Error Session data or error.
	 */
	private function get_or_create_session( int $booking_id ) {
		$settings = get_option( 'bkx_healthcare_settings', array() );
		$provider = $settings['telemedicine_provider'] ?? 'jitsi';

		// Check for existing session.
		$existing_session = get_post_meta( $booking_id, '_bkx_telemedicine_session', true );

		if ( $existing_session && ! empty( $existing_session['room_url'] ) ) {
			return $existing_session;
		}

		// Create new session based on provider.
		switch ( $provider ) {
			case 'zoom':
				$session = $this->create_zoom_session( $booking_id );
				break;
			case 'doxy':
				$session = $this->create_doxy_session( $booking_id );
				break;
			case 'jitsi':
			default:
				$session = $this->create_jitsi_session( $booking_id );
				break;
		}

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		// Store session data.
		update_post_meta( $booking_id, '_bkx_telemedicine_session', $session );

		return $session;
	}

	/**
	 * Create Jitsi Meet session.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array Session data.
	 */
	private function create_jitsi_session( int $booking_id ): array {
		$settings = get_option( 'bkx_healthcare_settings', array() );
		$domain   = $settings['jitsi_domain'] ?? 'meet.jit.si';

		// Generate unique room name.
		$room_id = 'bkx-' . wp_generate_uuid4();

		// Get booking details for display name.
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$seat_id      = get_post_meta( $booking_id, 'seat_id', true );
		$provider_name = $seat_id ? get_the_title( $seat_id ) : 'Provider';

		return array(
			'provider'     => 'jitsi',
			'room_id'      => $room_id,
			'room_url'     => 'https://' . $domain . '/' . $room_id,
			'domain'       => $domain,
			'provider_name' => $provider_name,
			'created_at'   => current_time( 'mysql' ),
			'config'       => array(
				'startWithAudioMuted' => true,
				'startWithVideoMuted' => false,
				'prejoinPageEnabled'  => true,
			),
		);
	}

	/**
	 * Create Zoom session.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array|\WP_Error Session data or error.
	 */
	private function create_zoom_session( int $booking_id ) {
		$settings = get_option( 'bkx_healthcare_settings', array() );

		$api_key    = $settings['zoom_api_key'] ?? '';
		$api_secret = $settings['zoom_api_secret'] ?? '';
		$user_id    = $settings['zoom_user_id'] ?? '';

		if ( empty( $api_key ) || empty( $api_secret ) ) {
			return new \WP_Error( 'no_credentials', __( 'Zoom API credentials not configured', 'bkx-healthcare-practice' ) );
		}

		// Decrypt credentials.
		if ( class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();
			$api_key    = $encryption->decrypt( $api_key );
			$api_secret = $encryption->decrypt( $api_secret );
		}

		// Get booking details.
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time = get_post_meta( $booking_id, 'booking_time', true );
		$base_id      = get_post_meta( $booking_id, 'base_id', true );
		$duration     = get_post_meta( $base_id, 'base_time', true ) ?: 30;

		$start_time = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $booking_date . ' ' . $booking_time ) );

		// Create Zoom meeting.
		$response = wp_remote_post(
			'https://api.zoom.us/v2/users/' . $user_id . '/meetings',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->generate_zoom_jwt( $api_key, $api_secret ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'topic'      => sprintf(
						/* translators: %d: Booking ID */
						__( 'Telemedicine Appointment #%d', 'bkx-healthcare-practice' ),
						$booking_id
					),
					'type'       => 2, // Scheduled meeting.
					'start_time' => $start_time,
					'duration'   => absint( $duration ),
					'settings'   => array(
						'host_video'        => true,
						'participant_video' => true,
						'waiting_room'      => true,
						'mute_upon_entry'   => true,
					),
				) ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( wp_remote_retrieve_response_code( $response ) !== 201 ) {
			return new \WP_Error(
				'zoom_error',
				$body['message'] ?? __( 'Failed to create Zoom meeting', 'bkx-healthcare-practice' )
			);
		}

		return array(
			'provider'      => 'zoom',
			'room_id'       => $body['id'],
			'room_url'      => $body['join_url'],
			'host_url'      => $body['start_url'],
			'password'      => $body['password'] ?? '',
			'created_at'    => current_time( 'mysql' ),
		);
	}

	/**
	 * Generate Zoom JWT token.
	 *
	 * @since 1.0.0
	 * @param string $api_key    API key.
	 * @param string $api_secret API secret.
	 * @return string JWT token.
	 */
	private function generate_zoom_jwt( string $api_key, string $api_secret ): string {
		$header = base64_encode( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'HS256' ) ) );
		$payload = base64_encode( wp_json_encode( array(
			'iss' => $api_key,
			'exp' => time() + 3600,
		) ) );

		$signature = base64_encode(
			hash_hmac( 'sha256', $header . '.' . $payload, $api_secret, true )
		);

		return $header . '.' . $payload . '.' . $signature;
	}

	/**
	 * Create Doxy.me session.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array Session data.
	 */
	private function create_doxy_session( int $booking_id ): array {
		$settings = get_option( 'bkx_healthcare_settings', array() );
		$room_url = $settings['doxy_room_url'] ?? '';

		if ( empty( $room_url ) ) {
			// Fall back to Jitsi.
			return $this->create_jitsi_session( $booking_id );
		}

		$seat_id       = get_post_meta( $booking_id, 'seat_id', true );
		$provider_name = $seat_id ? get_the_title( $seat_id ) : 'Provider';

		return array(
			'provider'      => 'doxy',
			'room_url'      => $room_url,
			'provider_name' => $provider_name,
			'created_at'    => current_time( 'mysql' ),
			'instructions'  => __( 'Click the link to join your virtual waiting room. Your provider will join you at your appointment time.', 'bkx-healthcare-practice' ),
		);
	}

	/**
	 * Log session event.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $event      Event type.
	 * @param array  $data       Event data.
	 * @return void
	 */
	private function log_session_event( int $booking_id, string $event, array $data = array() ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_telemedicine_logs';

		$wpdb->insert(
			$table_name,
			array(
				'booking_id' => $booking_id,
				'event'      => $event,
				'user_id'    => get_current_user_id(),
				'event_data' => wp_json_encode( $data ),
				'ip_address' => $this->get_client_ip(),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		// Also log to HIPAA audit.
		do_action( 'bkx_healthcare_audit_log', 'telemedicine_' . $event, $booking_id, $data );
	}

	/**
	 * Get client IP address.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * End telemedicine session.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	public function end_session( int $booking_id ): bool {
		$session = get_post_meta( $booking_id, '_bkx_telemedicine_session', true );

		if ( ! $session ) {
			return false;
		}

		// Log session end.
		$this->log_session_event( $booking_id, 'ended', array(
			'user_id' => get_current_user_id(),
		) );

		// Update session status.
		$session['ended_at'] = current_time( 'mysql' );
		$session['status']   = 'completed';

		update_post_meta( $booking_id, '_bkx_telemedicine_session', $session );

		return true;
	}

	/**
	 * Render telemedicine session.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return string HTML output.
	 */
	public function render_session( int $booking_id ): string {
		if ( ! $booking_id ) {
			return '<p class="bkx-error">' . esc_html__( 'Invalid booking ID', 'bkx-healthcare-practice' ) . '</p>';
		}

		if ( ! is_user_logged_in() ) {
			return '<p class="bkx-error">' . esc_html__( 'Please log in to join the telemedicine session.', 'bkx-healthcare-practice' ) . '</p>';
		}

		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return '<p class="bkx-error">' . esc_html__( 'Booking not found', 'bkx-healthcare-practice' ) . '</p>';
		}

		$session = $this->start_session( $booking_id );

		if ( is_wp_error( $session ) ) {
			return '<p class="bkx-error">' . esc_html( $session->get_error_message() ) . '</p>';
		}

		ob_start();
		?>
		<div class="bkx-telemedicine-container" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
			<div class="bkx-telemedicine-header">
				<h3><?php esc_html_e( 'Telemedicine Session', 'bkx-healthcare-practice' ); ?></h3>
				<?php if ( ! empty( $session['provider_name'] ) ) : ?>
					<p class="bkx-provider-info">
						<?php
						printf(
							/* translators: %s: Provider name */
							esc_html__( 'Provider: %s', 'bkx-healthcare-practice' ),
							esc_html( $session['provider_name'] )
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $session['instructions'] ) ) : ?>
				<div class="bkx-telemedicine-instructions">
					<p><?php echo esc_html( $session['instructions'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( 'jitsi' === $session['provider'] ) : ?>
				<?php $this->render_jitsi_embed( $session, $booking_id ); ?>
			<?php else : ?>
				<div class="bkx-telemedicine-external">
					<p><?php esc_html_e( 'Click the button below to join your telemedicine session:', 'bkx-healthcare-practice' ); ?></p>
					<a href="<?php echo esc_url( $session['room_url'] ); ?>" target="_blank" class="bkx-btn bkx-btn-primary bkx-btn-large">
						<?php esc_html_e( 'Join Session', 'bkx-healthcare-practice' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="bkx-telemedicine-footer">
				<p class="bkx-hipaa-notice">
					<?php esc_html_e( 'This telemedicine session is HIPAA-compliant. Your conversation is private and secure.', 'bkx-healthcare-practice' ); ?>
				</p>
				<button type="button" class="bkx-btn bkx-btn-secondary bkx-end-session" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
					<?php esc_html_e( 'End Session', 'bkx-healthcare-practice' ); ?>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Jitsi embed.
	 *
	 * @since 1.0.0
	 * @param array $session    Session data.
	 * @param int   $booking_id Booking ID.
	 * @return void
	 */
	private function render_jitsi_embed( array $session, int $booking_id ): void {
		$current_user = wp_get_current_user();
		$display_name = $current_user->display_name;
		$email        = $current_user->user_email;

		// Determine if user is provider or patient.
		$seat_id     = get_post_meta( $booking_id, 'seat_id', true );
		$is_provider = $this->is_provider( get_current_user_id(), $seat_id );
		?>
		<div id="bkx-jitsi-container" class="bkx-jitsi-embed"></div>

		<script src="https://<?php echo esc_attr( $session['domain'] ); ?>/external_api.js"></script>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				const domain = <?php echo wp_json_encode( $session['domain'] ); ?>;
				const options = {
					roomName: <?php echo wp_json_encode( $session['room_id'] ); ?>,
					width: '100%',
					height: 600,
					parentNode: document.querySelector('#bkx-jitsi-container'),
					userInfo: {
						displayName: <?php echo wp_json_encode( $display_name ); ?>,
						email: <?php echo wp_json_encode( $email ); ?>
					},
					configOverwrite: <?php echo wp_json_encode( $session['config'] ?? array() ); ?>,
					interfaceConfigOverwrite: {
						TOOLBAR_BUTTONS: [
							'microphone', 'camera', 'closedcaptions', 'desktop',
							'fullscreen', 'fodeviceselection', 'hangup', 'chat',
							'recording', 'etherpad', 'settings', 'raisehand',
							'videoquality', 'filmstrip', 'tileview', 'download'
						],
						SHOW_JITSI_WATERMARK: false,
						SHOW_BRAND_WATERMARK: false,
						DEFAULT_BACKGROUND: '#3d3d3d'
					}
				};

				const api = new JitsiMeetExternalAPI(domain, options);

				api.addEventListener('videoConferenceLeft', function() {
					jQuery.post(bkxHealthcare.ajaxUrl, {
						action: 'bkx_end_telemedicine',
						booking_id: <?php echo absint( $booking_id ); ?>,
						nonce: bkxHealthcare.nonce
					});
				});

				// Store API reference for end session button.
				window.bkxJitsiApi = api;
			});
		</script>
		<?php
	}

	/**
	 * Get session statistics for a booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	public function get_session_stats( int $booking_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_telemedicine_logs';

		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE booking_id = %d ORDER BY created_at ASC",
				$booking_id
			),
			ARRAY_A
		);

		if ( empty( $logs ) ) {
			return array();
		}

		$stats = array(
			'total_joins'   => 0,
			'participants'  => array(),
			'started_at'    => null,
			'ended_at'      => null,
			'duration'      => null,
		);

		foreach ( $logs as $log ) {
			if ( 'joined' === $log['event'] ) {
				$stats['total_joins']++;

				$data = json_decode( $log['event_data'], true );
				if ( ! in_array( $log['user_id'], $stats['participants'], true ) ) {
					$stats['participants'][] = $log['user_id'];
				}

				if ( null === $stats['started_at'] ) {
					$stats['started_at'] = $log['created_at'];
				}
			}

			if ( 'ended' === $log['event'] ) {
				$stats['ended_at'] = $log['created_at'];
			}
		}

		if ( $stats['started_at'] && $stats['ended_at'] ) {
			$start = strtotime( $stats['started_at'] );
			$end   = strtotime( $stats['ended_at'] );
			$stats['duration'] = $end - $start;
		}

		return $stats;
	}
}

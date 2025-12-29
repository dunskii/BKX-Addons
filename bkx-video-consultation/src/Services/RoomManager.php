<?php
/**
 * Room Manager Service.
 *
 * @package BookingX\VideoConsultation
 * @since   1.0.0
 */

namespace BookingX\VideoConsultation\Services;

/**
 * RoomManager class.
 *
 * Manages video consultation rooms.
 *
 * @since 1.0.0
 */
class RoomManager {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		global $wpdb;
		$this->settings = $settings;
		$this->table    = $wpdb->prefix . 'bkx_video_rooms';
	}

	/**
	 * Create a video room.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Optional booking data.
	 * @return array|\WP_Error Room data or error.
	 */
	public function create_room( $booking_id, $booking_data = array() ) {
		global $wpdb;

		// Check for existing room.
		$existing = $this->get_room_by_booking( $booking_id );
		if ( $existing ) {
			return $existing;
		}

		// Get booking data if not provided.
		if ( empty( $booking_data ) ) {
			$booking_data = $this->get_booking_data( $booking_id );
		}

		// Generate unique room ID.
		$room_id = $this->generate_room_id();

		// Determine provider.
		$provider = $this->settings['default_provider'] ?? 'webrtc';

		// Create room based on provider.
		switch ( $provider ) {
			case 'zoom':
				$room_data = $this->create_zoom_room( $room_id, $booking_data );
				break;

			case 'google_meet':
				$room_data = $this->create_google_meet_room( $room_id, $booking_data );
				break;

			default:
				$room_data = $this->create_webrtc_room( $room_id, $booking_data );
		}

		if ( is_wp_error( $room_data ) ) {
			return $room_data;
		}

		// Generate password.
		$password = wp_generate_password( 8, false );

		// Calculate scheduled times.
		$scheduled_start = null;
		$scheduled_end   = null;

		if ( ! empty( $booking_data['booking_date'] ) && ! empty( $booking_data['booking_time'] ) ) {
			$scheduled_start = $booking_data['booking_date'] . ' ' . $booking_data['booking_time'];
			$duration        = $booking_data['duration'] ?? 60;
			$scheduled_end   = gmdate( 'Y-m-d H:i:s', strtotime( $scheduled_start ) + ( $duration * 60 ) );
		}

		// Insert room record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			array(
				'booking_id'      => $booking_id,
				'room_id'         => $room_id,
				'provider'        => $provider,
				'host_url'        => $room_data['host_url'],
				'participant_url' => $room_data['participant_url'],
				'password'        => $password,
				'status'          => 'scheduled',
				'scheduled_start' => $scheduled_start,
				'scheduled_end'   => $scheduled_end,
				'metadata'        => wp_json_encode( $room_data['metadata'] ?? array() ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create video room.', 'bkx-video-consultation' ) );
		}

		$room = array(
			'id'              => $wpdb->insert_id,
			'room_id'         => $room_id,
			'provider'        => $provider,
			'host_url'        => $room_data['host_url'],
			'participant_url' => $room_data['participant_url'],
			'password'        => $password,
			'status'          => 'scheduled',
		);

		// Update booking meta.
		update_post_meta( $booking_id, '_video_room_id', $room_id );
		update_post_meta( $booking_id, '_video_room_password', $password );

		/**
		 * Fires after a video room is created.
		 *
		 * @param array $room Room data.
		 * @param int   $booking_id Booking ID.
		 */
		do_action( 'bkx_video_room_created', $room, $booking_id );

		return $room;
	}

	/**
	 * Generate unique room ID.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function generate_room_id() {
		return 'bkx-' . wp_generate_password( 12, false, false );
	}

	/**
	 * Create WebRTC room.
	 *
	 * @since 1.0.0
	 *
	 * @param string $room_id Room ID.
	 * @param array  $booking_data Booking data.
	 * @return array
	 */
	private function create_webrtc_room( $room_id, $booking_data ) {
		$base_url = home_url( '/video-consultation/' );

		return array(
			'host_url'        => add_query_arg(
				array(
					'room' => $room_id,
					'host' => 1,
				),
				$base_url
			),
			'participant_url' => add_query_arg( 'room', $room_id, $base_url ),
			'metadata'        => array(
				'type' => 'webrtc',
			),
		);
	}

	/**
	 * Create Zoom room.
	 *
	 * @since 1.0.0
	 *
	 * @param string $room_id Room ID.
	 * @param array  $booking_data Booking data.
	 * @return array|\WP_Error
	 */
	private function create_zoom_room( $room_id, $booking_data ) {
		$zoom_provider = new ZoomProvider( $this->settings );
		return $zoom_provider->create_meeting( $room_id, $booking_data );
	}

	/**
	 * Create Google Meet room.
	 *
	 * @since 1.0.0
	 *
	 * @param string $room_id Room ID.
	 * @param array  $booking_data Booking data.
	 * @return array|\WP_Error
	 */
	private function create_google_meet_room( $room_id, $booking_data ) {
		$meet_provider = new GoogleMeetProvider( $this->settings );
		return $meet_provider->create_meeting( $room_id, $booking_data );
	}

	/**
	 * Get booking data.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	private function get_booking_data( $booking_id ) {
		return array(
			'booking_date'   => get_post_meta( $booking_id, 'booking_date', true ),
			'booking_time'   => get_post_meta( $booking_id, 'booking_time', true ),
			'duration'       => get_post_meta( $booking_id, 'total_duration', true ) ?: 60,
			'customer_name'  => get_post_meta( $booking_id, 'customer_name', true ),
			'customer_email' => get_post_meta( $booking_id, 'customer_email', true ),
		);
	}

	/**
	 * Get room by booking ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 * @return object|null
	 */
	public function get_room_by_booking( $booking_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE booking_id = %d",
				$this->table,
				$booking_id
			)
		);
	}

	/**
	 * Get room by room ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $room_id Room ID.
	 * @return object|null
	 */
	public function get_room( $room_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE room_id = %s",
				$this->table,
				$room_id
			)
		);
	}

	/**
	 * Get rooms with filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_rooms( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'    => '',
			'provider'  => '',
			'date_from' => '',
			'date_to'   => '',
			'limit'     => 50,
			'offset'    => 0,
			'orderby'   => 'scheduled_start',
			'order'     => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['provider'] ) ) {
			$where[]  = 'provider = %s';
			$values[] = $args['provider'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'scheduled_start >= %s';
			$values[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'scheduled_start <= %s';
			$values[] = $args['date_to'];
		}

		$allowed_orderby = array( 'scheduled_start', 'created_at', 'status', 'provider' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'scheduled_start';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT * FROM {$this->table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$values[] = $args['limit'];
		$values[] = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $query, $values ) );
	}

	/**
	 * Join a room.
	 *
	 * @since 1.0.0
	 *
	 * @param string $room_id Room ID.
	 * @param string $name Participant name.
	 * @param string $email Participant email.
	 * @param bool   $is_host Whether joining as host.
	 * @return array|\WP_Error
	 */
	public function join_room( $room_id, $name, $email = '', $is_host = false ) {
		global $wpdb;

		$room = $this->get_room( $room_id );

		if ( ! $room ) {
			return new \WP_Error( 'not_found', __( 'Video room not found.', 'bkx-video-consultation' ) );
		}

		// Check room status.
		if ( 'ended' === $room->status ) {
			return new \WP_Error( 'session_ended', __( 'This session has ended.', 'bkx-video-consultation' ) );
		}

		// Check if waiting room is enabled.
		$enable_waiting_room = $this->settings['enable_waiting_room'] ?? true;

		if ( $enable_waiting_room && ! $is_host ) {
			// Add to waiting room.
			$session_token = wp_generate_password( 32, false );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->prefix . 'bkx_video_waiting_room',
				array(
					'room_id'           => $room->id,
					'participant_name'  => $name,
					'participant_email' => $email,
					'session_token'     => $session_token,
					'status'            => 'waiting',
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);

			return array(
				'status'        => 'waiting',
				'session_token' => $session_token,
				'message'       => __( 'Please wait for the host to admit you.', 'bkx-video-consultation' ),
			);
		}

		// Start session if not already started.
		if ( 'scheduled' === $room->status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				array(
					'status'       => 'active',
					'actual_start' => current_time( 'mysql' ),
				),
				array( 'id' => $room->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		// Log the join event.
		$this->log_event( $room->id, 'join', array(
			'name'    => $name,
			'email'   => $email,
			'is_host' => $is_host,
		) );

		return array(
			'status'   => 'joined',
			'room_id'  => $room_id,
			'provider' => $room->provider,
			'is_host'  => $is_host,
		);
	}

	/**
	 * Admit participant from waiting room.
	 *
	 * @since 1.0.0
	 *
	 * @param int $participant_id Waiting room participant ID.
	 * @return bool|\WP_Error
	 */
	public function admit_participant( $participant_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$wpdb->prefix . 'bkx_video_waiting_room',
			array(
				'status'      => 'admitted',
				'admitted_at' => current_time( 'mysql' ),
			),
			array( 'id' => $participant_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'update_failed', __( 'Failed to admit participant.', 'bkx-video-consultation' ) );
		}

		return true;
	}

	/**
	 * End a session.
	 *
	 * @since 1.0.0
	 *
	 * @param int $room_id Room database ID.
	 * @return bool|\WP_Error
	 */
	public function end_session( $room_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$room = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$this->table,
				$room_id
			)
		);

		if ( ! $room ) {
			return new \WP_Error( 'not_found', __( 'Video room not found.', 'bkx-video-consultation' ) );
		}

		// Calculate duration.
		$duration = 0;
		if ( $room->actual_start ) {
			$duration = round( ( time() - strtotime( $room->actual_start ) ) / 60 );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array(
				'status'           => 'ended',
				'actual_end'       => current_time( 'mysql' ),
				'duration_minutes' => $duration,
			),
			array( 'id' => $room_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'update_failed', __( 'Failed to end session.', 'bkx-video-consultation' ) );
		}

		// Log event.
		$this->log_event( $room_id, 'session_end', array( 'duration_minutes' => $duration ) );

		/**
		 * Fires after a video session ends.
		 *
		 * @param object $room Room data.
		 * @param int    $duration Duration in minutes.
		 */
		do_action( 'bkx_video_session_ended', $room, $duration );

		return true;
	}

	/**
	 * Cancel room by booking.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	public function cancel_room_by_booking( $booking_id ) {
		global $wpdb;

		$room = $this->get_room_by_booking( $booking_id );
		if ( ! $room ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array( 'status' => 'cancelled' ),
			array( 'id' => $room->id ),
			array( '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Get room status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $room_id Room ID.
	 * @return array|\WP_Error
	 */
	public function get_room_status( $room_id ) {
		global $wpdb;

		$room = $this->get_room( $room_id );

		if ( ! $room ) {
			return new \WP_Error( 'not_found', __( 'Video room not found.', 'bkx-video-consultation' ) );
		}

		// Get waiting room participants.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$waiting = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, participant_name, created_at FROM %i WHERE room_id = %d AND status = 'waiting'",
				$wpdb->prefix . 'bkx_video_waiting_room',
				$room->id
			)
		);

		return array(
			'status'             => $room->status,
			'scheduled_start'    => $room->scheduled_start,
			'actual_start'       => $room->actual_start,
			'waiting_room_count' => count( $waiting ),
			'waiting_room'       => $waiting,
		);
	}

	/**
	 * Log an event.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $room_id Room database ID.
	 * @param string $event_type Event type.
	 * @param array  $event_data Event data.
	 */
	private function log_event( $room_id, $event_type, $event_data = array() ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_video_session_logs',
			array(
				'room_id'          => $room_id,
				'user_id'          => get_current_user_id() ?: null,
				'participant_type' => current_user_can( 'manage_options' ) ? 'host' : 'participant',
				'event_type'       => $event_type,
				'event_data'       => wp_json_encode( $event_data ),
				'ip_address'       => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'user_agent'       => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}

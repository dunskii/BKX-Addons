<?php
/**
 * WebRTC Provider Service.
 *
 * @package BookingX\VideoConsultation
 * @since   1.0.0
 */

namespace BookingX\VideoConsultation\Services;

/**
 * WebRTCProvider class.
 *
 * Handles WebRTC signaling and connection management.
 *
 * @since 1.0.0
 */
class WebRTCProvider {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Signaling table.
	 *
	 * @var string
	 */
	private $signal_table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		global $wpdb;
		$this->settings     = $settings;
		$this->signal_table = $wpdb->prefix . 'bkx_video_signals';

		// Create signaling table if not exists.
		$this->maybe_create_signal_table();
	}

	/**
	 * Create signaling table if not exists.
	 *
	 * @since 1.0.0
	 */
	private function maybe_create_signal_table() {
		global $wpdb;

		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$this->signal_table
			)
		);

		if ( $table_exists ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->signal_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			room_id varchar(100) NOT NULL,
			peer_id varchar(100) NOT NULL,
			target_peer varchar(100) DEFAULT NULL,
			signal_type varchar(50) NOT NULL,
			signal_data longtext NOT NULL,
			is_read tinyint(1) DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY room_id (room_id),
			KEY peer_id (peer_id),
			KEY target_peer (target_peer),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get ICE server configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_ice_servers() {
		$servers = array();

		// Add STUN servers.
		$stun_string = $this->settings['webrtc_stun_servers'] ?? 'stun:stun.l.google.com:19302';
		$stun_urls   = array_filter( array_map( 'trim', explode( ',', $stun_string ) ) );

		if ( ! empty( $stun_urls ) ) {
			$servers[] = array(
				'urls' => $stun_urls,
			);
		}

		// Add TURN server if configured.
		$turn_server = $this->settings['webrtc_turn_server'] ?? '';
		if ( ! empty( $turn_server ) ) {
			$servers[] = array(
				'urls'       => $turn_server,
				'username'   => $this->settings['webrtc_turn_username'] ?? '',
				'credential' => $this->settings['webrtc_turn_credential'] ?? '',
			);
		}

		return $servers;
	}

	/**
	 * Handle signaling message.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_signaling( $request ) {
		$room_id     = sanitize_text_field( $request->get_param( 'room_id' ) );
		$peer_id     = sanitize_text_field( $request->get_param( 'peer_id' ) );
		$signal_type = sanitize_text_field( $request->get_param( 'type' ) );
		$signal_data = $request->get_param( 'data' );
		$target_peer = sanitize_text_field( $request->get_param( 'target' ) );

		if ( empty( $room_id ) || empty( $peer_id ) || empty( $signal_type ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'Missing required parameters' ),
				400
			);
		}

		switch ( $signal_type ) {
			case 'join':
				return $this->handle_join( $room_id, $peer_id );

			case 'leave':
				return $this->handle_leave( $room_id, $peer_id );

			case 'offer':
			case 'answer':
			case 'ice-candidate':
				return $this->store_signal( $room_id, $peer_id, $target_peer, $signal_type, $signal_data );

			case 'poll':
				return $this->poll_signals( $room_id, $peer_id );

			default:
				return new \WP_REST_Response(
					array( 'error' => 'Unknown signal type' ),
					400
				);
		}
	}

	/**
	 * Handle peer join.
	 *
	 * @since 1.0.0
	 *
	 * @param string $room_id Room ID.
	 * @param string $peer_id Peer ID.
	 * @return \WP_REST_Response
	 */
	private function handle_join( $room_id, $peer_id ) {
		global $wpdb;

		// Clean up old signals.
		$this->cleanup_expired_signals();

		// Register peer presence.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->signal_table,
			array(
				'room_id'     => $room_id,
				'peer_id'     => $peer_id,
				'signal_type' => 'presence',
				'signal_data' => wp_json_encode( array( 'status' => 'online' ) ),
				'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + 60 ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		// Get other peers in room.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$peers = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT peer_id FROM %i
				WHERE room_id = %s
				AND signal_type = 'presence'
				AND peer_id != %s
				AND expires_at > NOW()",
				$this->signal_table,
				$room_id,
				$peer_id
			)
		);

		return new \WP_REST_Response(
			array(
				'success'     => true,
				'peers'       => $peers,
				'ice_servers' => $this->get_ice_servers(),
			),
			200
		);
	}

	/**
	 * Handle peer leave.
	 *
	 * @since 1.0.0
	 *
	 * @param string $room_id Room ID.
	 * @param string $peer_id Peer ID.
	 * @return \WP_REST_Response
	 */
	private function handle_leave( $room_id, $peer_id ) {
		global $wpdb;

		// Remove peer's signals.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$this->signal_table,
			array(
				'room_id' => $room_id,
				'peer_id' => $peer_id,
			),
			array( '%s', '%s' )
		);

		// Notify other peers.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$other_peers = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT peer_id FROM %i
				WHERE room_id = %s
				AND signal_type = 'presence'
				AND expires_at > NOW()",
				$this->signal_table,
				$room_id
			)
		);

		foreach ( $other_peers as $target ) {
			$this->store_signal(
				$room_id,
				$peer_id,
				$target,
				'peer-left',
				array( 'peer_id' => $peer_id )
			);
		}

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Store a signal.
	 *
	 * @since 1.0.0
	 *
	 * @param string $room_id Room ID.
	 * @param string $peer_id Sender peer ID.
	 * @param string $target_peer Target peer ID.
	 * @param string $signal_type Signal type.
	 * @param mixed  $signal_data Signal data.
	 * @return \WP_REST_Response
	 */
	private function store_signal( $room_id, $peer_id, $target_peer, $signal_type, $signal_data ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->signal_table,
			array(
				'room_id'     => $room_id,
				'peer_id'     => $peer_id,
				'target_peer' => $target_peer,
				'signal_type' => $signal_type,
				'signal_data' => wp_json_encode( $signal_data ),
				'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + 30 ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return new \WP_REST_Response(
				array( 'error' => 'Failed to store signal' ),
				500
			);
		}

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Poll for new signals.
	 *
	 * @since 1.0.0
	 *
	 * @param string $room_id Room ID.
	 * @param string $peer_id Peer ID.
	 * @return \WP_REST_Response
	 */
	private function poll_signals( $room_id, $peer_id ) {
		global $wpdb;

		// Update presence.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET expires_at = %s
				WHERE room_id = %s AND peer_id = %s AND signal_type = 'presence'",
				$this->signal_table,
				gmdate( 'Y-m-d H:i:s', time() + 60 ),
				$room_id,
				$peer_id
			)
		);

		// Get signals for this peer.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$signals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, peer_id as from_peer, signal_type, signal_data FROM %i
				WHERE room_id = %s
				AND target_peer = %s
				AND is_read = 0
				AND signal_type != 'presence'
				ORDER BY created_at ASC",
				$this->signal_table,
				$room_id,
				$peer_id
			)
		);

		if ( ! empty( $signals ) ) {
			$signal_ids = wp_list_pluck( $signals, 'id' );
			$ids_string = implode( ',', array_map( 'intval', $signal_ids ) );

			// Mark as read.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "UPDATE {$this->signal_table} SET is_read = 1 WHERE id IN ({$ids_string})" );
		}

		// Parse signal data.
		foreach ( $signals as &$signal ) {
			$signal->signal_data = json_decode( $signal->signal_data, true );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'signals' => $signals,
			),
			200
		);
	}

	/**
	 * Cleanup expired signals.
	 *
	 * @since 1.0.0
	 */
	private function cleanup_expired_signals() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE expires_at < NOW()",
				$this->signal_table
			)
		);
	}

	/**
	 * Generate a peer ID.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function generate_peer_id() {
		return 'peer-' . wp_generate_password( 16, false );
	}
}

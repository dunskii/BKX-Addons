<?php
/**
 * Session Manager for Google Assistant conversations.
 *
 * @package BookingX\GoogleAssistant
 */

namespace BookingX\GoogleAssistant\Services;

defined( 'ABSPATH' ) || exit;

/**
 * SessionManager class.
 */
class SessionManager {

	/**
	 * Get or create session.
	 *
	 * @param string $session_id Google session ID.
	 * @param string $user_id    Google user ID.
	 * @return array Session data.
	 */
	public function get_or_create( $session_id, $user_id = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_assistant_sessions';

		// Try to get existing session.
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE session_id = %s AND status = 'active'", // phpcs:ignore
				$session_id
			),
			ARRAY_A
		);

		if ( $session ) {
			// Update last activity.
			$wpdb->update(
				$table,
				array( 'updated_at' => current_time( 'mysql', true ) ),
				array( 'id' => $session['id'] ),
				array( '%s' ),
				array( '%d' )
			);

			$session['context']      = json_decode( $session['context'], true ) ?: array();
			$session['booking_data'] = json_decode( $session['booking_data'], true ) ?: array();

			return $session;
		}

		// Create new session.
		$new_session = array(
			'session_id'   => $session_id,
			'user_id'      => $user_id,
			'context'      => wp_json_encode( array() ),
			'intent'       => null,
			'booking_data' => wp_json_encode( array() ),
			'status'       => 'active',
			'expires_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '+30 minutes' ) ),
			'created_at'   => current_time( 'mysql', true ),
			'updated_at'   => current_time( 'mysql', true ),
		);

		$wpdb->insert( $table, $new_session );

		$new_session['id']           = $wpdb->insert_id;
		$new_session['context']      = array();
		$new_session['booking_data'] = array();

		return $new_session;
	}

	/**
	 * Update session context.
	 *
	 * @param string $session_id Session ID.
	 * @param array  $context    Context data.
	 * @return bool
	 */
	public function update_context( $session_id, $context ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'bkx_assistant_sessions',
			array(
				'context'    => wp_json_encode( $context ),
				'updated_at' => current_time( 'mysql', true ),
				'expires_at' => gmdate( 'Y-m-d H:i:s', strtotime( '+30 minutes' ) ),
			),
			array( 'session_id' => $session_id ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Update session intent.
	 *
	 * @param string $session_id Session ID.
	 * @param string $intent     Current intent.
	 * @return bool
	 */
	public function update_intent( $session_id, $intent ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'bkx_assistant_sessions',
			array(
				'intent'     => $intent,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'session_id' => $session_id ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Update booking data in session.
	 *
	 * @param string $session_id   Session ID.
	 * @param array  $booking_data Booking data.
	 * @return bool
	 */
	public function update_booking_data( $session_id, $booking_data ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'bkx_assistant_sessions',
			array(
				'booking_data' => wp_json_encode( $booking_data ),
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'session_id' => $session_id ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * End session.
	 *
	 * @param string $session_id Session ID.
	 * @return bool
	 */
	public function end_session( $session_id ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'bkx_assistant_sessions',
			array(
				'status'     => 'completed',
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'session_id' => $session_id ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Cleanup expired sessions.
	 *
	 * @return int Number of deleted sessions.
	 */
	public function cleanup_expired() {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bkx_assistant_sessions WHERE expires_at < %s OR (status = 'completed' AND updated_at < %s)", // phpcs:ignore
				current_time( 'mysql', true ),
				gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
			)
		);
	}

	/**
	 * Get session stats.
	 *
	 * @return array Stats.
	 */
	public function get_stats() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_assistant_sessions';

		return array(
			'active'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" ), // phpcs:ignore
			'completed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'completed'" ), // phpcs:ignore
			'total'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), // phpcs:ignore
		);
	}
}

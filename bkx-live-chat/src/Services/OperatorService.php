<?php
/**
 * Operator Service.
 *
 * @package BookingX\LiveChat\Services
 * @since   1.0.0
 */

namespace BookingX\LiveChat\Services;

defined( 'ABSPATH' ) || exit;

/**
 * OperatorService class.
 *
 * Manages chat operators.
 *
 * @since 1.0.0
 */
class OperatorService {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get all operators.
	 *
	 * @since 1.0.0
	 *
	 * @return array Operators.
	 */
	public function get_operators() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_operators';

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM %i ORDER BY display_name ASC", $table )
		);
	}

	/**
	 * Get operator by user ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return object|null Operator or null.
	 */
	public function get_operator( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_operators';

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE user_id = %d",
				$table,
				$user_id
			)
		);
	}

	/**
	 * Get or create operator.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return object Operator.
	 */
	public function get_or_create( $user_id ) {
		$operator = $this->get_operator( $user_id );

		if ( $operator ) {
			return $operator;
		}

		global $wpdb;

		$user  = get_user_by( 'id', $user_id );
		$table = $wpdb->prefix . 'bkx_livechat_operators';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'user_id'      => $user_id,
				'display_name' => $user->display_name,
				'avatar_url'   => get_avatar_url( $user_id ),
				'status'       => 'offline',
			)
		);

		return $this->get_operator( $user_id );
	}

	/**
	 * Update operator status.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $status  New status.
	 * @return true|\WP_Error True or error.
	 */
	public function update_status( $user_id, $status ) {
		global $wpdb;

		$operator = $this->get_or_create( $user_id );
		$table    = $wpdb->prefix . 'bkx_livechat_operators';

		$valid_statuses = array( 'online', 'away', 'busy', 'offline' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return new \WP_Error( 'invalid_status', __( 'Invalid status.', 'bkx-live-chat' ) );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'status'            => $status,
				'status_changed_at' => current_time( 'mysql' ),
			),
			array( 'user_id' => $user_id )
		);

		return true;
	}

	/**
	 * Check if any operator is online.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether any operator is online.
	 */
	public function is_any_online() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_operators';

		$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'online'", $table )
		);

		return $count > 0;
	}

	/**
	 * Get online operators count.
	 *
	 * @since 1.0.0
	 *
	 * @return int Count.
	 */
	public function get_online_count() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_operators';

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'online'", $table )
		);
	}

	/**
	 * Get available operator.
	 *
	 * @since 1.0.0
	 *
	 * @return object|null Operator or null.
	 */
	public function get_available_operator() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_operators';

		// Get online operator with least active chats and room for more.
		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE status = 'online' AND active_chats < max_chats ORDER BY active_chats ASC LIMIT 1",
				$table
			)
		);
	}

	/**
	 * Increment active chats.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 */
	public function increment_active_chats( $user_id ) {
		global $wpdb;

		$this->get_or_create( $user_id );
		$table = $wpdb->prefix . 'bkx_livechat_operators';

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE %i SET active_chats = active_chats + 1, total_chats = total_chats + 1 WHERE user_id = %d",
				$table,
				$user_id
			)
		);
	}

	/**
	 * Decrement active chats.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 */
	public function decrement_active_chats( $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_operators';

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE %i SET active_chats = GREATEST(0, active_chats - 1) WHERE user_id = %d",
				$table,
				$user_id
			)
		);
	}

	/**
	 * Update operator rating.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 */
	public function update_rating( $user_id ) {
		global $wpdb;

		$chats_table    = $wpdb->prefix . 'bkx_livechat_chats';
		$operators_table = $wpdb->prefix . 'bkx_livechat_operators';

		// Calculate average rating.
		$avg_rating = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT AVG(rating) FROM %i WHERE operator_id = %d AND rating IS NOT NULL",
				$chats_table,
				$user_id
			)
		);

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$operators_table,
			array( 'avg_rating' => $avg_rating ),
			array( 'user_id' => $user_id )
		);
	}

	/**
	 * Update operator profile.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id User ID.
	 * @param array $data    Profile data.
	 */
	public function update_profile( $user_id, $data ) {
		global $wpdb;

		$this->get_or_create( $user_id );
		$table = $wpdb->prefix . 'bkx_livechat_operators';

		$update_data = array();

		if ( isset( $data['display_name'] ) ) {
			$update_data['display_name'] = sanitize_text_field( $data['display_name'] );
		}

		if ( isset( $data['max_chats'] ) ) {
			$update_data['max_chats'] = absint( $data['max_chats'] );
		}

		if ( isset( $data['departments'] ) ) {
			$update_data['departments'] = wp_json_encode( $data['departments'] );
		}

		if ( ! empty( $update_data ) ) {
			$wpdb->update( $table, $update_data, array( 'user_id' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
}

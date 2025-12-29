<?php
/**
 * Visitor Service.
 *
 * @package BookingX\LiveChat\Services
 * @since   1.0.0
 */

namespace BookingX\LiveChat\Services;

defined( 'ABSPATH' ) || exit;

/**
 * VisitorService class.
 *
 * Tracks website visitors.
 *
 * @since 1.0.0
 */
class VisitorService {

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
	 * Track visitor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $session_id   Session ID.
	 * @param string $current_page Current page URL.
	 */
	public function track( $session_id, $current_page ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_visitors';

		// Check if visitor exists.
		$existing = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE session_id = %s",
				$table,
				$session_id
			)
		);

		if ( $existing ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'current_page' => $current_page,
					'last_seen'    => current_time( 'mysql' ),
				),
				array( 'session_id' => $session_id )
			);
		} else {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'session_id'   => $session_id,
					'visitor_ip'   => $this->get_visitor_ip(),
					'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
					'current_page' => $current_page,
					'referrer'     => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
					'last_seen'    => current_time( 'mysql' ),
				)
			);
		}
	}

	/**
	 * Get active visitors.
	 *
	 * @since 1.0.0
	 *
	 * @return array Visitors.
	 */
	public function get_active_visitors() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_visitors';

		// Visitors seen in last 5 minutes.
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT v.*,
					(SELECT c.id FROM {$wpdb->prefix}bkx_livechat_chats c WHERE c.session_id = v.session_id AND c.status IN ('pending', 'active') LIMIT 1) as active_chat_id
				FROM %i v
				WHERE v.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
				ORDER BY v.last_seen DESC",
				$table
			)
		);
	}

	/**
	 * Get visitor by session.
	 *
	 * @since 1.0.0
	 *
	 * @param string $session_id Session ID.
	 * @return object|null Visitor or null.
	 */
	public function get_visitor( $session_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_visitors';

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE session_id = %s",
				$table,
				$session_id
			)
		);
	}

	/**
	 * Update visitor info.
	 *
	 * @since 1.0.0
	 *
	 * @param string $session_id Session ID.
	 * @param string $name       Visitor name.
	 * @param string $email      Visitor email.
	 */
	public function update_info( $session_id, $name, $email ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_visitors';

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'visitor_name'  => $name,
				'visitor_email' => $email,
			),
			array( 'session_id' => $session_id )
		);
	}

	/**
	 * Get visitor IP.
	 *
	 * @since 1.0.0
	 *
	 * @return string IP address.
	 */
	private function get_visitor_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}
}

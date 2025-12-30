<?php
/**
 * Audit Logger Service for HIPAA Compliance.
 *
 * @package BookingX\HIPAA\Services
 */

namespace BookingX\HIPAA\Services;

defined( 'ABSPATH' ) || exit;

/**
 * AuditLogger class.
 */
class AuditLogger {

	/**
	 * Log an event.
	 *
	 * @param string $event_type   Event type (authentication, phi, settings, etc.).
	 * @param string $event_action Event action (login, view, update, etc.).
	 * @param array  $data         Additional data.
	 * @return int|false Insert ID or false on failure.
	 */
	public function log( $event_type, $event_action, $data = array() ) {
		global $wpdb;

		$user_id = isset( $data['user_id'] ) ? $data['user_id'] : get_current_user_id();

		$log_data = array(
			'event_type'    => sanitize_text_field( $event_type ),
			'event_action'  => sanitize_text_field( $event_action ),
			'user_id'       => $user_id ? absint( $user_id ) : null,
			'user_ip'       => $this->get_client_ip(),
			'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'resource_type' => isset( $data['resource_type'] ) ? sanitize_text_field( $data['resource_type'] ) : null,
			'resource_id'   => isset( $data['resource_id'] ) ? absint( $data['resource_id'] ) : null,
			'phi_accessed'  => isset( $data['phi_accessed'] ) && $data['phi_accessed'] ? 1 : 0,
			'data_before'   => isset( $data['data_before'] ) ? wp_json_encode( $data['data_before'] ) : null,
			'data_after'    => isset( $data['data_after'] ) ? wp_json_encode( $data['data_after'] ) : null,
			'metadata'      => isset( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null,
			'created_at'    => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_hipaa_audit_log',
			$log_data,
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get audit logs.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'event_type'   => '',
			'event_action' => '',
			'user_id'      => 0,
			'phi_accessed' => null,
			'date_from'    => '',
			'date_to'      => '',
			'limit'        => 50,
			'offset'       => 0,
			'orderby'      => 'created_at',
			'order'        => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['event_type'] ) ) {
			$where[] = 'event_type = %s';
			$values[] = $args['event_type'];
		}

		if ( ! empty( $args['event_action'] ) ) {
			$where[] = 'event_action = %s';
			$values[] = $args['event_action'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$values[] = $args['user_id'];
		}

		if ( null !== $args['phi_accessed'] ) {
			$where[] = 'phi_accessed = %d';
			$values[] = $args['phi_accessed'] ? 1 : 0;
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[] = 'created_at >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[] = 'created_at <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		$allowed_orderby = array( 'id', 'event_type', 'event_action', 'user_id', 'created_at' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order = in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $args['order'] ) : 'DESC';

		$table = $wpdb->prefix . 'bkx_hipaa_audit_log';

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $values, array( $args['limit'], $args['offset'] ) )
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$args['limit'],
				$args['offset']
			);
		}

		return $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count audit logs.
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public function count_logs( $args = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_hipaa_audit_log';

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['event_type'] ) ) {
			$where[] = 'event_type = %s';
			$values[] = $args['event_type'];
		}

		if ( ! empty( $args['phi_accessed'] ) ) {
			$where[] = 'phi_accessed = 1';
		}

		$where_clause = implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$values
			);
		} else {
			$query = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Cleanup old logs.
	 *
	 * @param int $retention_days Days to retain logs.
	 * @return int Number of deleted rows.
	 */
	public function cleanup( $retention_days ) {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bkx_hipaa_audit_log WHERE created_at < %s",
				$cutoff_date
			)
		);

		// Log the cleanup.
		$this->log(
			'system',
			'audit_cleanup',
			array(
				'metadata' => array(
					'deleted_count' => $deleted,
					'cutoff_date'   => $cutoff_date,
				),
			)
		);

		return $deleted;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) && ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs.
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Get PHI access summary.
	 *
	 * @param int $days Number of days.
	 * @return array
	 */
	public function get_phi_access_summary( $days = 30 ) {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$table       = $wpdb->prefix . 'bkx_hipaa_audit_log';

		// Get access by user.
		$by_user = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, COUNT(*) as access_count
				FROM {$table}
				WHERE phi_accessed = 1 AND created_at >= %s
				GROUP BY user_id
				ORDER BY access_count DESC
				LIMIT 10",
				$cutoff_date
			)
		);

		// Get access by resource type.
		$by_resource = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT resource_type, COUNT(*) as access_count
				FROM {$table}
				WHERE phi_accessed = 1 AND created_at >= %s
				GROUP BY resource_type",
				$cutoff_date
			)
		);

		// Get daily access trend.
		$daily_trend = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as date, COUNT(*) as access_count
				FROM {$table}
				WHERE phi_accessed = 1 AND created_at >= %s
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$cutoff_date
			)
		);

		return array(
			'by_user'     => $by_user,
			'by_resource' => $by_resource,
			'daily_trend' => $daily_trend,
		);
	}
}

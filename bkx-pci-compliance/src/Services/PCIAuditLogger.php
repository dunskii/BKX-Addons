<?php
/**
 * PCI Audit Logger Service.
 *
 * @package BookingX\PCICompliance
 */

namespace BookingX\PCICompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * PCIAuditLogger class.
 *
 * Implements PCI DSS Requirement 10: Track and monitor all access to network resources and cardholder data.
 */
class PCIAuditLogger {

	/**
	 * Log an event.
	 *
	 * @param string $event_type     Event type.
	 * @param string $category       Event category (authentication, data_access, configuration, payment, security).
	 * @param string $severity       Severity (info, warning, critical).
	 * @param array  $details        Additional details.
	 * @param string $resource_type  Resource type (optional).
	 * @param int    $resource_id    Resource ID (optional).
	 * @return int|false Log ID or false on failure.
	 */
	public function log( $event_type, $category, $severity = 'info', $details = array(), $resource_type = null, $resource_id = null ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$ip      = $this->get_client_ip();

		$data = array(
			'event_type'      => sanitize_key( $event_type ),
			'event_category'  => sanitize_key( $category ),
			'severity'        => sanitize_key( $severity ),
			'user_id'         => $user_id > 0 ? $user_id : null,
			'ip_address'      => $ip,
			'user_agent'      => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
			'resource_type'   => $resource_type ? sanitize_key( $resource_type ) : null,
			'resource_id'     => $resource_id ? absint( $resource_id ) : null,
			'action'          => $details['action'] ?? $event_type,
			'details'         => wp_json_encode( $this->sanitize_details( $details ) ),
			'pci_requirement' => $details['pci_requirement'] ?? null,
			'created_at'      => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_pci_audit_log',
			$data,
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get logs with pagination and filtering.
	 *
	 * @param int   $page     Page number.
	 * @param int   $per_page Items per page.
	 * @param array $filters  Filters.
	 * @return array
	 */
	public function get_logs( $page = 1, $per_page = 50, $filters = array() ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'bkx_pci_audit_log';
		$where  = array( '1=1' );
		$values = array();

		// Apply filters.
		if ( ! empty( $filters['event_type'] ) ) {
			$where[]  = 'event_type = %s';
			$values[] = sanitize_key( $filters['event_type'] );
		}

		if ( ! empty( $filters['category'] ) ) {
			$where[]  = 'event_category = %s';
			$values[] = sanitize_key( $filters['category'] );
		}

		if ( ! empty( $filters['severity'] ) ) {
			$where[]  = 'severity = %s';
			$values[] = sanitize_key( $filters['severity'] );
		}

		if ( ! empty( $filters['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$values[] = absint( $filters['user_id'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		if ( ! empty( $filters['pci_requirement'] ) ) {
			$where[]  = 'pci_requirement LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $filters['pci_requirement'] ) ) . '%';
		}

		if ( ! empty( $filters['search'] ) ) {
			$where[]  = '(action LIKE %s OR details LIKE %s)';
			$search   = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$values[] = $search;
			$values[] = $search;
		}

		$where_clause = implode( ' AND ', $where );
		$offset       = ( $page - 1 ) * $per_page;

		// Get total count.
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", $values ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		// Get logs.
		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$values[] = $per_page;
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$logs = $wpdb->get_results( $wpdb->prepare( $query, $values ), ARRAY_A );

		// Process logs.
		foreach ( $logs as &$log ) {
			$log['details'] = json_decode( $log['details'], true );
			if ( $log['user_id'] ) {
				$user = get_user_by( 'id', $log['user_id'] );
				$log['user_display'] = $user ? $user->display_name : '#' . $log['user_id'];
			} else {
				$log['user_display'] = __( 'System', 'bkx-pci-compliance' );
			}
		}

		return array(
			'logs'  => $logs,
			'total' => (int) $total,
			'pages' => ceil( $total / $per_page ),
			'page'  => $page,
		);
	}

	/**
	 * Get log by ID.
	 *
	 * @param int $id Log ID.
	 * @return array|null
	 */
	public function get_log( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_pci_audit_log WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		if ( $log ) {
			$log['details'] = json_decode( $log['details'], true );
		}

		return $log;
	}

	/**
	 * Export logs.
	 *
	 * @param array  $filters Filters.
	 * @param string $format  Export format (csv, json).
	 * @return string|false File URL or false on failure.
	 */
	public function export( $filters = array(), $format = 'csv' ) {
		$logs = $this->get_logs( 1, 10000, $filters );

		if ( empty( $logs['logs'] ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/bkx-pci-exports/';

		if ( ! is_dir( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
			file_put_contents( $export_dir . '.htaccess', 'Deny from all' );
			file_put_contents( $export_dir . 'index.php', '<?php // Silence is golden.' );
		}

		$filename = sprintf( 'pci-audit-log-%s.%s', gmdate( 'Y-m-d-His' ), $format );
		$filepath = $export_dir . $filename;

		if ( 'csv' === $format ) {
			$fp = fopen( $filepath, 'w' );
			fputcsv( $fp, array(
				'ID',
				'Date/Time',
				'Event Type',
				'Category',
				'Severity',
				'User',
				'IP Address',
				'Action',
				'PCI Requirement',
			) );

			foreach ( $logs['logs'] as $log ) {
				fputcsv( $fp, array(
					$log['id'],
					$log['created_at'],
					$log['event_type'],
					$log['event_category'],
					$log['severity'],
					$log['user_display'],
					$log['ip_address'],
					$log['action'],
					$log['pci_requirement'],
				) );
			}

			fclose( $fp );
		} else {
			file_put_contents( $filepath, wp_json_encode( $logs['logs'], JSON_PRETTY_PRINT ) );
		}

		return $upload_dir['baseurl'] . '/bkx-pci-exports/' . $filename;
	}

	/**
	 * Get log statistics.
	 *
	 * @param int $days Number of days.
	 * @return array
	 */
	public function get_statistics( $days = 30 ) {
		global $wpdb;

		$table     = $wpdb->prefix . 'bkx_pci_audit_log';
		$date_from = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// Events by category.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$by_category = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_category, COUNT(*) as count
				FROM {$table}
				WHERE created_at >= %s
				GROUP BY event_category",
				$date_from
			),
			ARRAY_A
		);

		// Events by severity.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$by_severity = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT severity, COUNT(*) as count
				FROM {$table}
				WHERE created_at >= %s
				GROUP BY severity",
				$date_from
			),
			ARRAY_A
		);

		// Events by day.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$by_day = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as date, COUNT(*) as count
				FROM {$table}
				WHERE created_at >= %s
				GROUP BY DATE(created_at)
				ORDER BY date",
				$date_from
			),
			ARRAY_A
		);

		// Failed logins.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$failed_logins = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE event_type = 'login_failed' AND created_at >= %s",
				$date_from
			)
		);

		// Critical events.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$critical_events = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE severity = 'critical' AND created_at >= %s",
				$date_from
			)
		);

		return array(
			'by_category'     => $by_category,
			'by_severity'     => $by_severity,
			'by_day'          => $by_day,
			'failed_logins'   => (int) $failed_logins,
			'critical_events' => (int) $critical_events,
			'total_events'    => array_sum( array_column( $by_category, 'count' ) ),
		);
	}

	/**
	 * Cleanup old logs.
	 *
	 * @param int $retention_days Days to retain.
	 * @return int Number of deleted records.
	 */
	public function cleanup( $retention_days = 365 ) {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d', strtotime( "-{$retention_days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bkx_pci_audit_log WHERE created_at < %s",
				$cutoff
			)
		);

		// Log the cleanup.
		$this->log(
			'audit_log_cleanup',
			'configuration',
			'info',
			array(
				'deleted_count'   => $deleted,
				'retention_days'  => $retention_days,
				'pci_requirement' => '10.7',
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
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( $_SERVER[ $header ] );
				// Handle comma-separated IPs (X-Forwarded-For).
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
	 * Sanitize log details.
	 *
	 * @param array $details Details array.
	 * @return array
	 */
	private function sanitize_details( $details ) {
		// Remove sensitive data.
		$sensitive_keys = array(
			'password',
			'pass',
			'secret',
			'api_key',
			'card_number',
			'cvv',
			'credit_card',
		);

		foreach ( $details as $key => $value ) {
			$lower_key = strtolower( $key );
			foreach ( $sensitive_keys as $sensitive ) {
				if ( strpos( $lower_key, $sensitive ) !== false ) {
					$details[ $key ] = '[REDACTED]';
					break;
				}
			}
		}

		return $details;
	}
}

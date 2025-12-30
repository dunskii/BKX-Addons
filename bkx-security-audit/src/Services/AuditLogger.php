<?php
/**
 * Audit Logger service.
 *
 * @package BookingX\SecurityAudit
 */

namespace BookingX\SecurityAudit\Services;

defined( 'ABSPATH' ) || exit;

/**
 * AuditLogger class.
 */
class AuditLogger {

	/**
	 * Log an event.
	 *
	 * @param string $action      Action name.
	 * @param array  $args        Additional arguments.
	 */
	public function log( $action, $args = array() ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();

		$data = array(
			'user_id'     => $user_id ?: null,
			'user_email'  => $user->user_email ?: ( $args['user_email'] ?? null ),
			'action'      => sanitize_text_field( $action ),
			'object_type' => sanitize_text_field( $args['object_type'] ?? '' ),
			'object_id'   => absint( $args['object_id'] ?? 0 ) ?: null,
			'object_name' => sanitize_text_field( $args['object_name'] ?? '' ),
			'details'     => isset( $args['details'] ) ? wp_json_encode( $args['details'] ) : null,
			'ip_address'  => $this->get_client_ip(),
			'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'session_id'  => wp_get_session_token(),
			'created_at'  => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_audit_log',
			$data,
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		/**
		 * Fires after an audit log entry is created.
		 *
		 * @param string $action The action logged.
		 * @param array  $data   The log data.
		 */
		do_action( 'bkx_audit_logged', $action, $data );
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
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (X-Forwarded-For).
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
	 * Log user login.
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       User object.
	 */
	public function log_login( $user_login, $user ) {
		$this->log( 'user_login', array(
			'object_type' => 'user',
			'object_id'   => $user->ID,
			'object_name' => $user->display_name,
			'user_email'  => $user->user_email,
			'details'     => array(
				'username' => $user_login,
				'roles'    => $user->roles,
			),
		) );
	}

	/**
	 * Log user logout.
	 */
	public function log_logout() {
		$user = wp_get_current_user();
		if ( ! $user->ID ) {
			return;
		}

		$this->log( 'user_logout', array(
			'object_type' => 'user',
			'object_id'   => $user->ID,
			'object_name' => $user->display_name,
		) );
	}

	/**
	 * Log failed login attempt.
	 *
	 * @param string $username Username attempted.
	 */
	public function log_failed_login( $username ) {
		$this->log( 'login_failed', array(
			'object_type' => 'user',
			'object_name' => $username,
			'details'     => array(
				'attempted_username' => $username,
				'reason'             => 'invalid_credentials',
			),
		) );
	}

	/**
	 * Log user creation.
	 *
	 * @param int $user_id User ID.
	 */
	public function log_user_created( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->log( 'user_created', array(
			'object_type' => 'user',
			'object_id'   => $user_id,
			'object_name' => $user->display_name,
			'details'     => array(
				'email' => $user->user_email,
				'roles' => $user->roles,
			),
		) );
	}

	/**
	 * Log user update.
	 *
	 * @param int      $user_id       User ID.
	 * @param \WP_User $old_user_data Old user data.
	 */
	public function log_user_updated( $user_id, $old_user_data ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$changes = array();
		if ( $user->user_email !== $old_user_data->user_email ) {
			$changes['email'] = array(
				'old' => $old_user_data->user_email,
				'new' => $user->user_email,
			);
		}
		if ( $user->display_name !== $old_user_data->display_name ) {
			$changes['display_name'] = array(
				'old' => $old_user_data->display_name,
				'new' => $user->display_name,
			);
		}

		if ( ! empty( $changes ) ) {
			$this->log( 'user_updated', array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				'object_name' => $user->display_name,
				'details'     => array( 'changes' => $changes ),
			) );
		}
	}

	/**
	 * Log user deletion.
	 *
	 * @param int $user_id User ID.
	 */
	public function log_user_deleted( $user_id ) {
		$user = get_userdata( $user_id );

		$this->log( 'user_deleted', array(
			'object_type' => 'user',
			'object_id'   => $user_id,
			'object_name' => $user ? $user->display_name : 'Unknown',
		) );
	}

	/**
	 * Log role change.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $new_role  New role.
	 * @param array  $old_roles Old roles.
	 */
	public function log_role_change( $user_id, $new_role, $old_roles ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->log( 'user_role_changed', array(
			'object_type' => 'user',
			'object_id'   => $user_id,
			'object_name' => $user->display_name,
			'details'     => array(
				'old_roles' => $old_roles,
				'new_role'  => $new_role,
			),
		) );
	}

	/**
	 * Log booking creation.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function log_booking_created( $booking_id, $booking_data ) {
		$this->log( 'booking_created', array(
			'object_type' => 'booking',
			'object_id'   => $booking_id,
			'object_name' => sprintf( 'Booking #%d', $booking_id ),
			'details'     => array(
				'customer_email' => $booking_data['customer_email'] ?? '',
				'service'        => $booking_data['service_name'] ?? '',
				'date'           => $booking_data['booking_date'] ?? '',
			),
		) );
	}

	/**
	 * Log booking update.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function log_booking_updated( $booking_id, $booking_data ) {
		$this->log( 'booking_updated', array(
			'object_type' => 'booking',
			'object_id'   => $booking_id,
			'object_name' => sprintf( 'Booking #%d', $booking_id ),
			'details'     => $booking_data,
		) );
	}

	/**
	 * Log booking status change.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function log_booking_status_changed( $booking_id, $old_status, $new_status ) {
		$this->log( 'booking_status_changed', array(
			'object_type' => 'booking',
			'object_id'   => $booking_id,
			'object_name' => sprintf( 'Booking #%d', $booking_id ),
			'details'     => array(
				'old_status' => $old_status,
				'new_status' => $new_status,
			),
		) );
	}

	/**
	 * Log booking deletion.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public function log_booking_deleted( $booking_id ) {
		$this->log( 'booking_deleted', array(
			'object_type' => 'booking',
			'object_id'   => $booking_id,
			'object_name' => sprintf( 'Booking #%d', $booking_id ),
		) );
	}

	/**
	 * Log option update.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 */
	public function log_option_updated( $option, $old_value, $new_value ) {
		// Only log BookingX options.
		if ( strpos( $option, 'bkx_' ) !== 0 && strpos( $option, 'bookingx_' ) !== 0 ) {
			return;
		}

		// Skip transients and internal options.
		if ( strpos( $option, '_transient' ) === 0 ) {
			return;
		}

		$this->log( 'option_updated', array(
			'object_type' => 'option',
			'object_name' => $option,
			'details'     => array(
				'option_name' => $option,
			),
		) );
	}

	/**
	 * Log plugin activation.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function log_plugin_activated( $plugin ) {
		$this->log( 'plugin_activated', array(
			'object_type' => 'plugin',
			'object_name' => $plugin,
		) );
	}

	/**
	 * Log plugin deactivation.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function log_plugin_deactivated( $plugin ) {
		$this->log( 'plugin_deactivated', array(
			'object_type' => 'plugin',
			'object_name' => $plugin,
		) );
	}

	/**
	 * Log theme switch.
	 *
	 * @param string $new_theme New theme name.
	 */
	public function log_theme_switched( $new_theme ) {
		$this->log( 'theme_switched', array(
			'object_type' => 'theme',
			'object_name' => $new_theme,
		) );
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
			'per_page'    => 20,
			'page'        => 1,
			'user_id'     => null,
			'action'      => null,
			'object_type' => null,
			'date_from'   => null,
			'date_to'     => null,
			'search'      => null,
			'orderby'     => 'created_at',
			'order'       => 'DESC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_audit_log';

		$where = array( '1=1' );
		$values = array();

		if ( $args['user_id'] ) {
			$where[]  = 'user_id = %d';
			$values[] = absint( $args['user_id'] );
		}

		if ( $args['action'] ) {
			$where[]  = 'action = %s';
			$values[] = sanitize_text_field( $args['action'] );
		}

		if ( $args['object_type'] ) {
			$where[]  = 'object_type = %s';
			$values[] = sanitize_text_field( $args['object_type'] );
		}

		if ( $args['date_from'] ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $args['date_from'] );
		}

		if ( $args['date_to'] ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $args['date_to'] );
		}

		if ( $args['search'] ) {
			$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(object_name LIKE %s OR user_email LIKE %s OR action LIKE %s)';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		$where_clause = implode( ' AND ', $where );
		$offset       = ( $args['page'] - 1 ) * $args['per_page'];
		$order        = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		$orderby      = in_array( $args['orderby'], array( 'created_at', 'action', 'user_email' ), true ) ? $args['orderby'] : 'created_at';

		// Get total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$values
			)
		);

		// Get logs.
		$query = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$values[] = $args['per_page'];
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$logs = $wpdb->get_results(
			$wpdb->prepare( $query, ...$values ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return array(
			'logs'  => $logs,
			'total' => (int) $total,
			'pages' => ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * Export logs to file.
	 *
	 * @param string $format Export format (csv, json).
	 * @param int    $days   Number of days to export.
	 * @return string|false File URL or false on failure.
	 */
	public function export_logs( $format = 'csv', $days = 30 ) {
		global $wpdb;

		$table    = $wpdb->prefix . 'bkx_audit_log';
		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE created_at >= %s ORDER BY created_at DESC",
				$date_from
			),
			ARRAY_A
		);

		if ( empty( $logs ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/bkx-security-logs/';

		if ( ! is_dir( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		$filename = 'audit-log-' . gmdate( 'Y-m-d-His' ) . '.' . $format;
		$filepath = $export_dir . $filename;

		if ( 'csv' === $format ) {
			$fp = fopen( $filepath, 'w' );
			if ( ! $fp ) {
				return false;
			}

			// Headers.
			fputcsv( $fp, array_keys( $logs[0] ) );

			// Data.
			foreach ( $logs as $log ) {
				fputcsv( $fp, $log );
			}

			fclose( $fp );
		} else {
			file_put_contents( $filepath, wp_json_encode( $logs, JSON_PRETTY_PRINT ) );
		}

		return $upload_dir['baseurl'] . '/bkx-security-logs/' . $filename;
	}
}

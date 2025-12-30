<?php
/**
 * Login Protection service.
 *
 * @package BookingX\SecurityAudit
 */

namespace BookingX\SecurityAudit\Services;

defined( 'ABSPATH' ) || exit;

/**
 * LoginProtection class.
 */
class LoginProtection {

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;

		// Check for lockout on init.
		add_action( 'init', array( $this, 'check_lockout' ) );

		// Track login attempts.
		add_action( 'wp_login', array( $this, 'record_successful_login' ), 10, 2 );
		add_action( 'wp_login_failed', array( $this, 'record_failed_login' ) );

		// Add login message for lockouts.
		add_filter( 'login_message', array( $this, 'lockout_message' ) );
	}

	/**
	 * Check if current IP is locked out.
	 */
	public function check_lockout() {
		$ip = $this->get_client_ip();

		// Check whitelist first.
		if ( $this->is_whitelisted( $ip ) ) {
			return;
		}

		// Check blocklist.
		if ( $this->is_blocked( $ip ) ) {
			$this->show_blocked_page();
		}

		// Check lockout.
		if ( $this->is_locked_out( $ip ) ) {
			$this->show_lockout_page();
		}
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

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
	 * Check if IP is whitelisted.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	private function is_whitelisted( $ip ) {
		$whitelist = $this->settings['allowed_ips'] ?? '';
		if ( empty( $whitelist ) ) {
			return false;
		}

		$ips = array_map( 'trim', explode( "\n", $whitelist ) );
		return in_array( $ip, $ips, true );
	}

	/**
	 * Check if IP is blocked.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	private function is_blocked( $ip ) {
		$blocklist = $this->settings['blocked_ips'] ?? '';
		if ( empty( $blocklist ) ) {
			return false;
		}

		$ips = array_map( 'trim', explode( "\n", $blocklist ) );
		return in_array( $ip, $ips, true );
	}

	/**
	 * Check if IP is currently locked out.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public function is_locked_out( $ip ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_ip_lockouts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$lockout = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ip_address = %s AND locked_until > %s AND unlocked_at IS NULL",
				$ip,
				current_time( 'mysql' )
			)
		);

		return ! empty( $lockout );
	}

	/**
	 * Get lockout info for an IP.
	 *
	 * @param string $ip IP address.
	 * @return object|null
	 */
	public function get_lockout( $ip ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_ip_lockouts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ip_address = %s AND locked_until > %s AND unlocked_at IS NULL",
				$ip,
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Record successful login.
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       User object.
	 */
	public function record_successful_login( $user_login, $user ) {
		global $wpdb;

		$ip = $this->get_client_ip();

		// Record the attempt.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_login_attempts',
			array(
				'ip_address'  => $ip,
				'username'    => $user_login,
				'success'     => 1,
				'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		// Clear any lockout for this IP.
		$this->clear_lockout( $ip );
	}

	/**
	 * Record failed login attempt.
	 *
	 * @param string $username Username attempted.
	 */
	public function record_failed_login( $username ) {
		global $wpdb;

		$ip = $this->get_client_ip();

		// Check if whitelisted.
		if ( $this->is_whitelisted( $ip ) ) {
			return;
		}

		// Record the attempt.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_login_attempts',
			array(
				'ip_address'     => $ip,
				'username'       => $username,
				'success'        => 0,
				'failure_reason' => 'invalid_credentials',
				'user_agent'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		// Check if should lockout.
		$this->maybe_lockout( $ip );
	}

	/**
	 * Maybe lockout an IP based on failed attempts.
	 *
	 * @param string $ip IP address.
	 */
	private function maybe_lockout( $ip ) {
		global $wpdb;

		$max_attempts      = absint( $this->settings['max_login_attempts'] ?? 5 );
		$lockout_duration  = absint( $this->settings['lockout_duration'] ?? 30 );
		$time_window       = gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );

		// Count recent failed attempts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attempts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_login_attempts
				WHERE ip_address = %s AND success = 0 AND created_at > %s",
				$ip,
				$time_window
			)
		);

		if ( $attempts >= $max_attempts ) {
			$this->create_lockout( $ip, $lockout_duration, $attempts );
		}
	}

	/**
	 * Create a lockout for an IP.
	 *
	 * @param string $ip       IP address.
	 * @param int    $duration Lockout duration in minutes.
	 * @param int    $attempts Number of failed attempts.
	 */
	private function create_lockout( $ip, $duration, $attempts ) {
		global $wpdb;

		$locked_until = gmdate( 'Y-m-d H:i:s', strtotime( "+{$duration} minutes" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->replace(
			$wpdb->prefix . 'bkx_ip_lockouts',
			array(
				'ip_address'     => $ip,
				'lockout_reason' => 'too_many_failed_logins',
				'attempts_count' => $attempts,
				'locked_until'   => $locked_until,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		// Create security event.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_security_events',
			array(
				'event_type'  => 'ip_lockout',
				'severity'    => 'medium',
				'title'       => sprintf( 'IP %s locked out', $ip ),
				'description' => sprintf( '%d failed login attempts', $attempts ),
				'ip_address'  => $ip,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// Notify admin if enabled.
		if ( ! empty( $this->settings['notify_admin_on_lockout'] ) ) {
			$this->notify_admin_lockout( $ip, $attempts, $locked_until );
		}
	}

	/**
	 * Clear lockout for an IP.
	 *
	 * @param string $ip IP address.
	 */
	public function clear_lockout( $ip ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'bkx_ip_lockouts',
			array(
				'unlocked_at' => current_time( 'mysql' ),
				'unlocked_by' => get_current_user_id(),
			),
			array( 'ip_address' => $ip ),
			array( '%s', '%d' ),
			array( '%s' )
		);
	}

	/**
	 * Notify admin of lockout.
	 *
	 * @param string $ip           IP address.
	 * @param int    $attempts     Number of attempts.
	 * @param string $locked_until Lockout end time.
	 */
	private function notify_admin_lockout( $ip, $attempts, $locked_until ) {
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Security Alert: IP Locked Out', 'bkx-security-audit' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: IP address, 2: number of attempts, 3: lockout end time */
			__( "An IP address has been locked out due to too many failed login attempts.\n\nIP Address: %1\$s\nFailed Attempts: %2\$d\nLocked Until: %3\$s\n\nView security dashboard: %4\$s", 'bkx-security-audit' ),
			$ip,
			$attempts,
			$locked_until,
			admin_url( 'admin.php?page=bkx-security-audit&tab=lockouts' )
		);

		wp_mail( get_option( 'admin_email' ), $subject, $message );
	}

	/**
	 * Show lockout page.
	 */
	private function show_lockout_page() {
		$ip      = $this->get_client_ip();
		$lockout = $this->get_lockout( $ip );

		if ( ! $lockout ) {
			return;
		}

		$remaining = human_time_diff( time(), strtotime( $lockout->locked_until ) );

		status_header( 403 );
		nocache_headers();

		wp_die(
			sprintf(
				/* translators: %s: remaining time */
				esc_html__( 'Your IP address has been temporarily blocked due to too many failed login attempts. Please try again in %s.', 'bkx-security-audit' ),
				esc_html( $remaining )
			),
			__( 'Access Temporarily Blocked', 'bkx-security-audit' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Show blocked page.
	 */
	private function show_blocked_page() {
		status_header( 403 );
		nocache_headers();

		wp_die(
			esc_html__( 'Your IP address has been blocked from accessing this site.', 'bkx-security-audit' ),
			__( 'Access Blocked', 'bkx-security-audit' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Add lockout message to login page.
	 *
	 * @param string $message Login message.
	 * @return string
	 */
	public function lockout_message( $message ) {
		$ip = $this->get_client_ip();

		if ( $this->is_locked_out( $ip ) ) {
			$lockout   = $this->get_lockout( $ip );
			$remaining = human_time_diff( time(), strtotime( $lockout->locked_until ) );

			$message .= '<div class="message" style="border-left: 4px solid #dc3232; background: #fbeaea; padding: 12px;">';
			$message .= sprintf(
				/* translators: %s: remaining time */
				esc_html__( 'Too many failed login attempts. Please try again in %s.', 'bkx-security-audit' ),
				esc_html( $remaining )
			);
			$message .= '</div>';
		}

		return $message;
	}

	/**
	 * Get lockouts list.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_lockouts( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page'    => 20,
			'page'        => 1,
			'active_only' => false,
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_ip_lockouts';

		$where = array( '1=1' );
		if ( $args['active_only'] ) {
			$where[] = 'locked_until > NOW() AND unlocked_at IS NULL';
		}

		$where_clause = implode( ' AND ', $where );
		$offset       = ( $args['page'] - 1 ) * $args['per_page'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$lockouts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$args['per_page'],
				$offset
			),
			ARRAY_A
		);

		return array(
			'lockouts' => $lockouts,
			'total'    => (int) $total,
			'pages'    => ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * Get login attempts.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_attempts( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page'     => 20,
			'page'         => 1,
			'ip_address'   => null,
			'success_only' => null,
		);

		$args   = wp_parse_args( $args, $defaults );
		$table  = $wpdb->prefix . 'bkx_login_attempts';
		$where  = array( '1=1' );
		$values = array();

		if ( $args['ip_address'] ) {
			$where[]  = 'ip_address = %s';
			$values[] = $args['ip_address'];
		}

		if ( $args['success_only'] !== null ) {
			$where[]  = 'success = %d';
			$values[] = $args['success_only'] ? 1 : 0;
		}

		$where_clause = implode( ' AND ', $where );
		$offset       = ( $args['page'] - 1 ) * $args['per_page'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var(
			empty( $values )
				? "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}"
				: $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", ...$values ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$values[] = $args['per_page'];
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attempts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$values
			),
			ARRAY_A
		);

		return array(
			'attempts' => $attempts,
			'total'    => (int) $total,
			'pages'    => ceil( $total / $args['per_page'] ),
		);
	}
}

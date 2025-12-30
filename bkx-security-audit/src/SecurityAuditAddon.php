<?php
/**
 * Main Security & Audit Addon class.
 *
 * @package BookingX\SecurityAudit
 */

namespace BookingX\SecurityAudit;

use BookingX\SecurityAudit\Services\AuditLogger;
use BookingX\SecurityAudit\Services\LoginProtection;
use BookingX\SecurityAudit\Services\SecurityScanner;
use BookingX\SecurityAudit\Services\FileIntegrity;
use BookingX\SecurityAudit\Services\SecurityHeaders;
use BookingX\SecurityAudit\Services\TwoFactorAuth;
use BookingX\SecurityAudit\Services\IPManager;

defined( 'ABSPATH' ) || exit;

/**
 * SecurityAuditAddon class.
 */
class SecurityAuditAddon {

	/**
	 * Singleton instance.
	 *
	 * @var SecurityAuditAddon
	 */
	private static $instance = null;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Service instances.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get singleton instance.
	 *
	 * @return SecurityAuditAddon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->settings = get_option( 'bkx_security_audit_settings', array() );
	}

	/**
	 * Initialize the addon.
	 */
	public function init() {
		// Initialize services.
		$this->init_services();

		// Admin hooks.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}

		// BookingX integration.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_security', array( $this, 'render_settings_tab' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_security_run_scan', array( $this, 'ajax_run_scan' ) );
		add_action( 'wp_ajax_bkx_security_clear_lockout', array( $this, 'ajax_clear_lockout' ) );
		add_action( 'wp_ajax_bkx_security_export_logs', array( $this, 'ajax_export_logs' ) );
		add_action( 'wp_ajax_bkx_security_resolve_event', array( $this, 'ajax_resolve_event' ) );
		add_action( 'wp_ajax_bkx_security_whitelist_ip', array( $this, 'ajax_whitelist_ip' ) );
		add_action( 'wp_ajax_bkx_security_block_ip', array( $this, 'ajax_block_ip' ) );

		// Cron handlers.
		add_action( 'bkx_security_daily_scan', array( $this, 'run_daily_scan' ) );
		add_action( 'bkx_security_audit_cleanup', array( $this, 'cleanup_old_logs' ) );

		// Security headers.
		if ( ! empty( $this->settings['security_headers'] ) ) {
			add_action( 'send_headers', array( $this->get_service( 'headers' ), 'send_security_headers' ) );
		}

		// Disable XML-RPC if enabled.
		if ( ! empty( $this->settings['disable_xmlrpc'] ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'remove_xmlrpc_header' ) );
		}

		// Track user activities for audit log.
		$this->register_audit_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		// Always initialize audit logger.
		$this->services['audit'] = new AuditLogger();

		// Initialize login protection.
		if ( ! empty( $this->settings['enable_login_protection'] ) ) {
			$this->services['login'] = new LoginProtection( $this->settings );
		}

		// Initialize file integrity monitoring.
		if ( ! empty( $this->settings['enable_file_monitoring'] ) ) {
			$this->services['files'] = new FileIntegrity();
		}

		// Initialize security scanner.
		if ( ! empty( $this->settings['enable_security_scanning'] ) ) {
			$this->services['scanner'] = new SecurityScanner();
		}

		// Initialize security headers.
		$this->services['headers'] = new SecurityHeaders( $this->settings );

		// Initialize two-factor auth.
		if ( ! empty( $this->settings['two_factor_enabled'] ) ) {
			$this->services['2fa'] = new TwoFactorAuth();
		}

		// Initialize IP manager.
		$this->services['ip'] = new IPManager( $this->settings );
	}

	/**
	 * Get a service instance.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Security & Audit', 'bkx-security-audit' ),
			__( 'Security & Audit', 'bkx-security-audit' ),
			'manage_options',
			'bkx-security-audit',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-security-audit' ) === false && strpos( $hook, 'bookingx' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-security-audit-admin',
			BKX_SECURITY_AUDIT_URL . 'assets/css/admin.css',
			array(),
			BKX_SECURITY_AUDIT_VERSION
		);

		wp_enqueue_script(
			'bkx-security-audit-admin',
			BKX_SECURITY_AUDIT_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_SECURITY_AUDIT_VERSION,
			true
		);

		wp_localize_script(
			'bkx-security-audit-admin',
			'bkxSecurityAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_security_admin' ),
				'i18n'    => array(
					'confirm'        => __( 'Are you sure?', 'bkx-security-audit' ),
					'scanning'       => __( 'Running security scan...', 'bkx-security-audit' ),
					'scanComplete'   => __( 'Scan complete!', 'bkx-security-audit' ),
					'error'          => __( 'An error occurred.', 'bkx-security-audit' ),
					'success'        => __( 'Success!', 'bkx-security-audit' ),
					'confirmClear'   => __( 'Clear this lockout?', 'bkx-security-audit' ),
					'confirmResolve' => __( 'Mark this event as resolved?', 'bkx-security-audit' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';

		include BKX_SECURITY_AUDIT_PATH . 'templates/admin/page.php';
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'bkx_security_audit',
			'bkx_security_audit_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Settings input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Boolean settings.
		$booleans = array(
			'enable_audit_logging',
			'enable_login_protection',
			'enable_file_monitoring',
			'enable_security_scanning',
			'notify_admin_on_lockout',
			'notify_admin_on_breach',
			'security_headers',
			'disable_xmlrpc',
			'two_factor_enabled',
		);

		foreach ( $booleans as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		// Integer settings.
		$sanitized['max_login_attempts']   = absint( $input['max_login_attempts'] ?? 5 );
		$sanitized['lockout_duration']     = absint( $input['lockout_duration'] ?? 30 );
		$sanitized['audit_retention_days'] = absint( $input['audit_retention_days'] ?? 90 );

		// IP lists.
		$sanitized['allowed_ips'] = sanitize_textarea_field( $input['allowed_ips'] ?? '' );
		$sanitized['blocked_ips'] = sanitize_textarea_field( $input['blocked_ips'] ?? '' );

		return $sanitized;
	}

	/**
	 * Add settings tab to BookingX.
	 *
	 * @param array $tabs Settings tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['security'] = __( 'Security', 'bkx-security-audit' );
		return $tabs;
	}

	/**
	 * Render settings tab content.
	 */
	public function render_settings_tab() {
		include BKX_SECURITY_AUDIT_PATH . 'templates/admin/settings-tab.php';
	}

	/**
	 * Register audit log hooks.
	 */
	private function register_audit_hooks() {
		$audit = $this->get_service( 'audit' );
		if ( ! $audit ) {
			return;
		}

		// User authentication.
		add_action( 'wp_login', array( $audit, 'log_login' ), 10, 2 );
		add_action( 'wp_logout', array( $audit, 'log_logout' ) );
		add_action( 'wp_login_failed', array( $audit, 'log_failed_login' ) );

		// User management.
		add_action( 'user_register', array( $audit, 'log_user_created' ) );
		add_action( 'profile_update', array( $audit, 'log_user_updated' ), 10, 2 );
		add_action( 'delete_user', array( $audit, 'log_user_deleted' ) );
		add_action( 'set_user_role', array( $audit, 'log_role_change' ), 10, 3 );

		// BookingX events.
		add_action( 'bkx_booking_created', array( $audit, 'log_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_updated', array( $audit, 'log_booking_updated' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $audit, 'log_booking_status_changed' ), 10, 3 );
		add_action( 'bkx_booking_deleted', array( $audit, 'log_booking_deleted' ) );

		// Settings changes.
		add_action( 'update_option', array( $audit, 'log_option_updated' ), 10, 3 );

		// Plugin/Theme changes.
		add_action( 'activated_plugin', array( $audit, 'log_plugin_activated' ) );
		add_action( 'deactivated_plugin', array( $audit, 'log_plugin_deactivated' ) );
		add_action( 'switch_theme', array( $audit, 'log_theme_switched' ) );
	}

	/**
	 * Remove X-Pingback header.
	 *
	 * @param array $headers HTTP headers.
	 * @return array
	 */
	public function remove_xmlrpc_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * AJAX: Run security scan.
	 */
	public function ajax_run_scan() {
		check_ajax_referer( 'bkx_security_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-security-audit' ) ) );
		}

		$scanner = $this->get_service( 'scanner' );
		if ( ! $scanner ) {
			wp_send_json_error( array( 'message' => __( 'Scanner not available.', 'bkx-security-audit' ) ) );
		}

		$results = $scanner->run_full_scan();

		wp_send_json_success( array(
			'results' => $results,
			'message' => __( 'Security scan completed.', 'bkx-security-audit' ),
		) );
	}

	/**
	 * AJAX: Clear IP lockout.
	 */
	public function ajax_clear_lockout() {
		check_ajax_referer( 'bkx_security_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-security-audit' ) ) );
		}

		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
		if ( empty( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid IP address.', 'bkx-security-audit' ) ) );
		}

		$login = $this->get_service( 'login' );
		if ( $login ) {
			$login->clear_lockout( $ip );
		}

		wp_send_json_success( array( 'message' => __( 'Lockout cleared.', 'bkx-security-audit' ) ) );
	}

	/**
	 * AJAX: Export audit logs.
	 */
	public function ajax_export_logs() {
		check_ajax_referer( 'bkx_security_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-security-audit' ) ) );
		}

		$audit  = $this->get_service( 'audit' );
		$format = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'csv';
		$days   = isset( $_POST['days'] ) ? absint( $_POST['days'] ) : 30;

		$file_url = $audit->export_logs( $format, $days );

		if ( $file_url ) {
			wp_send_json_success( array( 'download_url' => $file_url ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Export failed.', 'bkx-security-audit' ) ) );
		}
	}

	/**
	 * AJAX: Resolve security event.
	 */
	public function ajax_resolve_event() {
		check_ajax_referer( 'bkx_security_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-security-audit' ) ) );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		if ( ! $event_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid event ID.', 'bkx-security-audit' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_security_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'resolved'    => 1,
				'resolved_at' => current_time( 'mysql' ),
				'resolved_by' => get_current_user_id(),
			),
			array( 'id' => $event_id ),
			array( '%d', '%s', '%d' ),
			array( '%d' )
		);

		wp_send_json_success( array( 'message' => __( 'Event resolved.', 'bkx-security-audit' ) ) );
	}

	/**
	 * AJAX: Whitelist IP.
	 */
	public function ajax_whitelist_ip() {
		check_ajax_referer( 'bkx_security_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-security-audit' ) ) );
		}

		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
		if ( empty( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid IP address.', 'bkx-security-audit' ) ) );
		}

		$ip_manager = $this->get_service( 'ip' );
		$ip_manager->add_to_whitelist( $ip );

		wp_send_json_success( array( 'message' => __( 'IP added to whitelist.', 'bkx-security-audit' ) ) );
	}

	/**
	 * AJAX: Block IP.
	 */
	public function ajax_block_ip() {
		check_ajax_referer( 'bkx_security_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-security-audit' ) ) );
		}

		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
		if ( empty( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid IP address.', 'bkx-security-audit' ) ) );
		}

		$ip_manager = $this->get_service( 'ip' );
		$ip_manager->add_to_blocklist( $ip );

		wp_send_json_success( array( 'message' => __( 'IP added to blocklist.', 'bkx-security-audit' ) ) );
	}

	/**
	 * Run daily security scan.
	 */
	public function run_daily_scan() {
		$scanner = $this->get_service( 'scanner' );
		if ( $scanner ) {
			$scanner->run_full_scan();
		}

		$files = $this->get_service( 'files' );
		if ( $files ) {
			$files->check_integrity();
		}
	}

	/**
	 * Cleanup old audit logs.
	 */
	public function cleanup_old_logs() {
		global $wpdb;

		$retention_days = absint( $this->settings['audit_retention_days'] ?? 90 );
		if ( ! $retention_days ) {
			return;
		}

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		// Cleanup audit log.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bkx_audit_log WHERE created_at < %s",
				$cutoff_date
			)
		);

		// Cleanup login attempts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bkx_login_attempts WHERE created_at < %s",
				$cutoff_date
			)
		);

		// Cleanup resolved security events.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bkx_security_events WHERE resolved = 1 AND resolved_at < %s",
				$cutoff_date
			)
		);
	}
}

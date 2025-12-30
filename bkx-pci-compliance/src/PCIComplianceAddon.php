<?php
/**
 * Main PCI Compliance Addon class.
 *
 * @package BookingX\PCICompliance
 */

namespace BookingX\PCICompliance;

defined( 'ABSPATH' ) || exit;

/**
 * PCIComplianceAddon class.
 */
class PCIComplianceAddon {

	/**
	 * Single instance.
	 *
	 * @var PCIComplianceAddon
	 */
	private static $instance = null;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Services.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get instance.
	 *
	 * @return PCIComplianceAddon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings = get_option( 'bkx_pci_compliance_settings', array() );
	}

	/**
	 * Initialize the addon.
	 */
	public function init() {
		// Initialize services.
		$this->init_services();

		// Admin hooks.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
			add_action( 'bkx_settings_tab_pci_compliance', array( $this, 'render_settings_tab' ) );
		}

		// Register AJAX handlers.
		$this->register_ajax_handlers();

		// Register cron handlers.
		add_action( 'bkx_pci_compliance_scan', array( $this, 'run_scheduled_scan' ) );
		add_action( 'bkx_pci_cleanup_logs', array( $this, 'cleanup_old_logs' ) );

		// Security enforcement hooks.
		$this->init_security_hooks();

		// Payment data monitoring hooks.
		$this->init_payment_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['audit_logger']        = new Services\PCIAuditLogger();
		$this->services['compliance_scanner']  = new Services\ComplianceScanner();
		$this->services['data_protection']     = new Services\DataProtection();
		$this->services['vulnerability_scan']  = new Services\VulnerabilityScanner();
		$this->services['key_manager']         = new Services\KeyManager();
		$this->services['session_security']    = new Services\SessionSecurity();
	}

	/**
	 * Get service.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Initialize security hooks.
	 */
	private function init_security_hooks() {
		// SSL enforcement.
		if ( ! empty( $this->settings['ssl_enforcement'] ) ) {
			add_action( 'init', array( $this, 'enforce_ssl' ) );
		}

		// Session security.
		if ( isset( $this->services['session_security'] ) ) {
			$this->services['session_security']->init();
		}

		// Password policy enforcement.
		add_filter( 'user_profile_update_errors', array( $this, 'validate_password_strength' ), 10, 3 );

		// Failed login monitoring.
		add_action( 'wp_login_failed', array( $this, 'log_failed_login' ) );
		add_action( 'wp_login', array( $this, 'log_successful_login' ), 10, 2 );

		// Configuration change monitoring.
		add_action( 'update_option', array( $this, 'log_option_change' ), 10, 3 );
	}

	/**
	 * Initialize payment hooks.
	 */
	private function init_payment_hooks() {
		// Monitor payment data access.
		add_action( 'bkx_before_payment_process', array( $this, 'log_payment_data_access' ), 10, 2 );
		add_action( 'bkx_payment_completed', array( $this, 'log_payment_completion' ), 10, 2 );
		add_action( 'bkx_payment_failed', array( $this, 'log_payment_failure' ), 10, 2 );

		// Mask sensitive data in logs.
		add_filter( 'bkx_log_data', array( $this, 'mask_sensitive_data' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'PCI Compliance', 'bkx-pci-compliance' ),
			__( 'PCI Compliance', 'bkx-pci-compliance' ),
			'manage_options',
			'bkx-pci-compliance',
			array( $this, 'render_admin_page' ),
			'dashicons-shield',
			58
		);

		add_submenu_page(
			'bkx-pci-compliance',
			__( 'Dashboard', 'bkx-pci-compliance' ),
			__( 'Dashboard', 'bkx-pci-compliance' ),
			'manage_options',
			'bkx-pci-compliance',
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			'bkx-pci-compliance',
			__( 'Compliance Scan', 'bkx-pci-compliance' ),
			__( 'Compliance Scan', 'bkx-pci-compliance' ),
			'manage_options',
			'bkx-pci-scan',
			array( $this, 'render_scan_page' )
		);

		add_submenu_page(
			'bkx-pci-compliance',
			__( 'Audit Log', 'bkx-pci-compliance' ),
			__( 'Audit Log', 'bkx-pci-compliance' ),
			'manage_options',
			'bkx-pci-audit-log',
			array( $this, 'render_audit_log_page' )
		);

		add_submenu_page(
			'bkx-pci-compliance',
			__( 'Vulnerabilities', 'bkx-pci-compliance' ),
			__( 'Vulnerabilities', 'bkx-pci-compliance' ),
			'manage_options',
			'bkx-pci-vulnerabilities',
			array( $this, 'render_vulnerabilities_page' )
		);

		add_submenu_page(
			'bkx-pci-compliance',
			__( 'Reports', 'bkx-pci-compliance' ),
			__( 'Reports', 'bkx-pci-compliance' ),
			'manage_options',
			'bkx-pci-reports',
			array( $this, 'render_reports_page' )
		);

		add_submenu_page(
			'bkx-pci-compliance',
			__( 'Settings', 'bkx-pci-compliance' ),
			__( 'Settings', 'bkx-pci-compliance' ),
			'manage_options',
			'bkx-pci-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-pci' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-pci-admin',
			BKX_PCI_URL . 'assets/css/admin.css',
			array(),
			BKX_PCI_VERSION
		);

		wp_enqueue_script(
			'bkx-pci-admin',
			BKX_PCI_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			BKX_PCI_VERSION,
			true
		);

		wp_localize_script( 'bkx-pci-admin', 'bkxPCIAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bkx_pci_action' ),
			'i18n'    => array(
				'scanning'       => __( 'Running compliance scan...', 'bkx-pci-compliance' ),
				'scanComplete'   => __( 'Compliance scan completed.', 'bkx-pci-compliance' ),
				'scanError'      => __( 'Scan failed. Please try again.', 'bkx-pci-compliance' ),
				'confirmResolve' => __( 'Are you sure you want to mark this as resolved?', 'bkx-pci-compliance' ),
				'confirmKeyRotation' => __( 'Key rotation will re-encrypt all sensitive data. Continue?', 'bkx-pci-compliance' ),
				'requestError'   => __( 'Request failed. Please try again.', 'bkx-pci-compliance' ),
			),
		) );

		// Chart.js for dashboard.
		if ( 'toplevel_page_bkx-pci-compliance' === $hook ) {
			wp_enqueue_script(
				'chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
				array(),
				'4.4.1',
				true
			);
		}
	}

	/**
	 * Register AJAX handlers.
	 */
	private function register_ajax_handlers() {
		$actions = array(
			'bkx_pci_run_scan',
			'bkx_pci_get_scan_results',
			'bkx_pci_resolve_vulnerability',
			'bkx_pci_rotate_keys',
			'bkx_pci_export_report',
			'bkx_pci_save_settings',
			'bkx_pci_get_audit_logs',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, 'handle_ajax_' . str_replace( 'bkx_pci_', '', $action ) ) );
		}
	}

	/**
	 * Handle run scan AJAX.
	 */
	public function handle_ajax_run_scan() {
		check_ajax_referer( 'bkx_pci_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-pci-compliance' ) ) );
		}

		$scan_type = isset( $_POST['scan_type'] ) ? sanitize_key( $_POST['scan_type'] ) : 'full';

		$scanner = $this->services['compliance_scanner'];
		$result  = $scanner->run_scan( $scan_type );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Scan completed successfully.', 'bkx-pci-compliance' ),
			'scan_id' => $result,
		) );
	}

	/**
	 * Handle get scan results AJAX.
	 */
	public function handle_ajax_get_scan_results() {
		check_ajax_referer( 'bkx_pci_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-pci-compliance' ) ) );
		}

		$scan_id = isset( $_POST['scan_id'] ) ? absint( $_POST['scan_id'] ) : 0;

		$scanner = $this->services['compliance_scanner'];
		$result  = $scanner->get_scan_result( $scan_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Scan not found.', 'bkx-pci-compliance' ) ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Handle resolve vulnerability AJAX.
	 */
	public function handle_ajax_resolve_vulnerability() {
		check_ajax_referer( 'bkx_pci_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-pci-compliance' ) ) );
		}

		$vuln_id    = isset( $_POST['vulnerability_id'] ) ? absint( $_POST['vulnerability_id'] ) : 0;
		$resolution = isset( $_POST['resolution'] ) ? sanitize_textarea_field( $_POST['resolution'] ) : '';
		$status     = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'resolved';

		$scanner = $this->services['vulnerability_scan'];
		$result  = $scanner->resolve_vulnerability( $vuln_id, $status, $resolution );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Vulnerability status updated.', 'bkx-pci-compliance' ) ) );
	}

	/**
	 * Handle rotate keys AJAX.
	 */
	public function handle_ajax_rotate_keys() {
		check_ajax_referer( 'bkx_pci_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-pci-compliance' ) ) );
		}

		$key_type = isset( $_POST['key_type'] ) ? sanitize_key( $_POST['key_type'] ) : 'encryption';

		$key_manager = $this->services['key_manager'];
		$result      = $key_manager->rotate_key( $key_type );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Key rotation completed successfully.', 'bkx-pci-compliance' ) ) );
	}

	/**
	 * Handle export report AJAX.
	 */
	public function handle_ajax_export_report() {
		check_ajax_referer( 'bkx_pci_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-pci-compliance' ) ) );
		}

		$report_type = isset( $_POST['report_type'] ) ? sanitize_key( $_POST['report_type'] ) : 'compliance';
		$date_from   = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : '';
		$date_to     = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : '';

		$scanner = $this->services['compliance_scanner'];
		$url     = $scanner->generate_report( $report_type, $date_from, $date_to );

		if ( is_wp_error( $url ) ) {
			wp_send_json_error( array( 'message' => $url->get_error_message() ) );
		}

		wp_send_json_success( array( 'url' => $url ) );
	}

	/**
	 * Handle save settings AJAX.
	 */
	public function handle_ajax_save_settings() {
		check_ajax_referer( 'bkx_pci_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-pci-compliance' ) ) );
		}

		$settings = array();

		// Sanitize settings.
		$settings['compliance_level']     = isset( $_POST['compliance_level'] ) ? sanitize_key( $_POST['compliance_level'] ) : 'saq_a';
		$settings['card_data_storage']    = isset( $_POST['card_data_storage'] ) ? sanitize_key( $_POST['card_data_storage'] ) : 'none';
		$settings['tokenization_enabled'] = ! empty( $_POST['tokenization_enabled'] );
		$settings['pci_scan_frequency']   = isset( $_POST['pci_scan_frequency'] ) ? sanitize_key( $_POST['pci_scan_frequency'] ) : 'weekly';
		$settings['ssl_enforcement']      = ! empty( $_POST['ssl_enforcement'] );
		$settings['session_timeout']      = isset( $_POST['session_timeout'] ) ? absint( $_POST['session_timeout'] ) : 15;
		$settings['failed_login_lockout'] = isset( $_POST['failed_login_lockout'] ) ? absint( $_POST['failed_login_lockout'] ) : 5;
		$settings['audit_log_retention']  = isset( $_POST['audit_log_retention'] ) ? absint( $_POST['audit_log_retention'] ) : 365;
		$settings['data_masking_enabled'] = ! empty( $_POST['data_masking_enabled'] );
		$settings['vulnerability_alerts'] = ! empty( $_POST['vulnerability_alerts'] );
		$settings['alert_email']          = isset( $_POST['alert_email'] ) ? sanitize_email( $_POST['alert_email'] ) : '';

		// Password requirements.
		$settings['password_requirements'] = array(
			'min_length'     => isset( $_POST['password_min_length'] ) ? absint( $_POST['password_min_length'] ) : 12,
			'require_upper'  => ! empty( $_POST['password_require_upper'] ),
			'require_lower'  => ! empty( $_POST['password_require_lower'] ),
			'require_number' => ! empty( $_POST['password_require_number'] ),
			'require_special' => ! empty( $_POST['password_require_special'] ),
		);

		update_option( 'bkx_pci_compliance_settings', $settings );

		// Log configuration change.
		$this->services['audit_logger']->log(
			'configuration_change',
			'configuration',
			'info',
			array(
				'action'          => 'update_pci_settings',
				'pci_requirement' => '1.1,2.2',
			)
		);

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'bkx-pci-compliance' ) ) );
	}

	/**
	 * Handle get audit logs AJAX.
	 */
	public function handle_ajax_get_audit_logs() {
		check_ajax_referer( 'bkx_pci_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-pci-compliance' ) ) );
		}

		$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 50;
		$filters  = isset( $_POST['filters'] ) ? array_map( 'sanitize_text_field', (array) $_POST['filters'] ) : array();

		$logger = $this->services['audit_logger'];
		$logs   = $logger->get_logs( $page, $per_page, $filters );

		wp_send_json_success( $logs );
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_PCI_PATH . 'templates/admin/dashboard.php';
	}

	/**
	 * Render scan page.
	 */
	public function render_scan_page() {
		include BKX_PCI_PATH . 'templates/admin/scan.php';
	}

	/**
	 * Render audit log page.
	 */
	public function render_audit_log_page() {
		include BKX_PCI_PATH . 'templates/admin/audit-log.php';
	}

	/**
	 * Render vulnerabilities page.
	 */
	public function render_vulnerabilities_page() {
		include BKX_PCI_PATH . 'templates/admin/vulnerabilities.php';
	}

	/**
	 * Render reports page.
	 */
	public function render_reports_page() {
		include BKX_PCI_PATH . 'templates/admin/reports.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		include BKX_PCI_PATH . 'templates/admin/settings.php';
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['pci_compliance'] = __( 'PCI Compliance', 'bkx-pci-compliance' );
		return $tabs;
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab() {
		include BKX_PCI_PATH . 'templates/admin/settings-tab.php';
	}

	/**
	 * Enforce SSL.
	 */
	public function enforce_ssl() {
		if ( ! is_ssl() && ! is_admin() ) {
			// Check for payment-related pages.
			if ( $this->is_payment_page() ) {
				wp_safe_redirect( str_replace( 'http://', 'https://', home_url( $_SERVER['REQUEST_URI'] ) ) );
				exit;
			}
		}
	}

	/**
	 * Check if current page is payment related.
	 *
	 * @return bool
	 */
	private function is_payment_page() {
		global $post;

		if ( ! $post ) {
			return false;
		}

		// Check for booking form shortcode.
		if ( has_shortcode( $post->post_content, 'bookingx' ) ) {
			return true;
		}

		// Check for checkout page.
		$checkout_page = get_option( 'bkx_checkout_page' );
		if ( $checkout_page && $post->ID === absint( $checkout_page ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate password strength.
	 *
	 * @param \WP_Error $errors Error object.
	 * @param bool      $update Whether updating user.
	 * @param \WP_User  $user   User object.
	 */
	public function validate_password_strength( $errors, $update, $user ) {
		if ( ! isset( $_POST['pass1'] ) || empty( $_POST['pass1'] ) ) {
			return;
		}

		$password = $_POST['pass1'];
		$requirements = $this->settings['password_requirements'] ?? array();

		$min_length = $requirements['min_length'] ?? 12;
		if ( strlen( $password ) < $min_length ) {
			$errors->add(
				'weak_password',
				sprintf(
					/* translators: %d: Minimum password length */
					__( 'Password must be at least %d characters long.', 'bkx-pci-compliance' ),
					$min_length
				)
			);
		}

		if ( ! empty( $requirements['require_upper'] ) && ! preg_match( '/[A-Z]/', $password ) ) {
			$errors->add( 'weak_password', __( 'Password must contain at least one uppercase letter.', 'bkx-pci-compliance' ) );
		}

		if ( ! empty( $requirements['require_lower'] ) && ! preg_match( '/[a-z]/', $password ) ) {
			$errors->add( 'weak_password', __( 'Password must contain at least one lowercase letter.', 'bkx-pci-compliance' ) );
		}

		if ( ! empty( $requirements['require_number'] ) && ! preg_match( '/[0-9]/', $password ) ) {
			$errors->add( 'weak_password', __( 'Password must contain at least one number.', 'bkx-pci-compliance' ) );
		}

		if ( ! empty( $requirements['require_special'] ) && ! preg_match( '/[!@#$%^&*(),.?":{}|<>]/', $password ) ) {
			$errors->add( 'weak_password', __( 'Password must contain at least one special character.', 'bkx-pci-compliance' ) );
		}
	}

	/**
	 * Log failed login.
	 *
	 * @param string $username Username.
	 */
	public function log_failed_login( $username ) {
		$this->services['audit_logger']->log(
			'login_failed',
			'authentication',
			'warning',
			array(
				'username'        => $username,
				'pci_requirement' => '8.1',
			)
		);
	}

	/**
	 * Log successful login.
	 *
	 * @param string   $username Username.
	 * @param \WP_User $user     User object.
	 */
	public function log_successful_login( $username, $user ) {
		$this->services['audit_logger']->log(
			'login_success',
			'authentication',
			'info',
			array(
				'user_id'         => $user->ID,
				'username'        => $username,
				'pci_requirement' => '10.2.4',
			)
		);
	}

	/**
	 * Log option change.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 */
	public function log_option_change( $option, $old_value, $new_value ) {
		// Only log BookingX and security-related options.
		$monitored_prefixes = array( 'bkx_', 'users_can_register', 'default_role', 'blog_public' );

		$should_log = false;
		foreach ( $monitored_prefixes as $prefix ) {
			if ( strpos( $option, $prefix ) === 0 || $option === $prefix ) {
				$should_log = true;
				break;
			}
		}

		if ( ! $should_log ) {
			return;
		}

		$this->services['audit_logger']->log(
			'option_changed',
			'configuration',
			'info',
			array(
				'option'          => $option,
				'pci_requirement' => '10.2.7',
			)
		);
	}

	/**
	 * Log payment data access.
	 *
	 * @param int   $booking_id  Booking ID.
	 * @param array $payment_data Payment data.
	 */
	public function log_payment_data_access( $booking_id, $payment_data ) {
		$this->services['data_protection']->log_data_access(
			'card_number',
			'view',
			$booking_id,
			'Payment processing initiated'
		);
	}

	/**
	 * Log payment completion.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $result     Payment result.
	 */
	public function log_payment_completion( $booking_id, $result ) {
		$this->services['audit_logger']->log(
			'payment_completed',
			'payment',
			'info',
			array(
				'booking_id'      => $booking_id,
				'amount'          => $result['amount'] ?? 0,
				'pci_requirement' => '10.2.2',
			)
		);
	}

	/**
	 * Log payment failure.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $error      Error details.
	 */
	public function log_payment_failure( $booking_id, $error ) {
		$this->services['audit_logger']->log(
			'payment_failed',
			'payment',
			'warning',
			array(
				'booking_id'      => $booking_id,
				'error'           => $error['message'] ?? 'Unknown error',
				'pci_requirement' => '10.2.2',
			)
		);
	}

	/**
	 * Mask sensitive data.
	 *
	 * @param array $data Log data.
	 * @return array
	 */
	public function mask_sensitive_data( $data ) {
		if ( empty( $this->settings['data_masking_enabled'] ) ) {
			return $data;
		}

		return $this->services['data_protection']->mask_data( $data );
	}

	/**
	 * Run scheduled scan.
	 */
	public function run_scheduled_scan() {
		$scanner = $this->services['compliance_scanner'];
		$result  = $scanner->run_scan( 'full' );

		if ( ! is_wp_error( $result ) && ! empty( $this->settings['vulnerability_alerts'] ) ) {
			$scan_result = $scanner->get_scan_result( $result );
			if ( $scan_result && $scan_result['critical_issues'] > 0 ) {
				$this->send_vulnerability_alert( $scan_result );
			}
		}
	}

	/**
	 * Send vulnerability alert.
	 *
	 * @param array $scan_result Scan result.
	 */
	private function send_vulnerability_alert( $scan_result ) {
		$email   = $this->settings['alert_email'] ?? get_option( 'admin_email' );
		$subject = sprintf(
			/* translators: %d: Number of critical issues */
			__( '[PCI Alert] %d Critical Issues Found', 'bkx-pci-compliance' ),
			$scan_result['critical_issues']
		);

		$message = sprintf(
			/* translators: 1: Site name, 2: Number of critical issues */
			__( "A PCI compliance scan on %1\$s has detected %2\$d critical issues that require immediate attention.\n\nPlease log in to review the findings and take appropriate action.", 'bkx-pci-compliance' ),
			get_bloginfo( 'name' ),
			$scan_result['critical_issues']
		);

		wp_mail( $email, $subject, $message );
	}

	/**
	 * Cleanup old logs.
	 */
	public function cleanup_old_logs() {
		$retention = $this->settings['audit_log_retention'] ?? 365;
		$this->services['audit_logger']->cleanup( $retention );
	}
}

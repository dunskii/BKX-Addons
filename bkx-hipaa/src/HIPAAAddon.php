<?php
/**
 * Main HIPAA Addon Class.
 *
 * @package BookingX\HIPAA
 */

namespace BookingX\HIPAA;

defined( 'ABSPATH' ) || exit;

/**
 * HIPAAAddon class.
 */
class HIPAAAddon {

	/**
	 * Instance.
	 *
	 * @var HIPAAAddon
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
	 * @return HIPAAAddon
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
		$this->settings = get_option( 'bkx_hipaa_settings', array() );

		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['encryption']     = new Services\EncryptionService();
		$this->services['audit_logger']   = new Services\AuditLogger();
		$this->services['access_control'] = new Services\AccessControl();
		$this->services['phi_handler']    = new Services\PHIHandler();
		$this->services['baa_manager']    = new Services\BAAManager();
	}

	/**
	 * Get service.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return isset( $this->services[ $name ] ) ? $this->services[ $name ] : null;
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_hipaa_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_hipaa_export_audit', array( $this, 'ajax_export_audit' ) );
		add_action( 'wp_ajax_bkx_hipaa_create_baa', array( $this, 'ajax_create_baa' ) );
		add_action( 'wp_ajax_bkx_hipaa_update_access', array( $this, 'ajax_update_access' ) );

		// Add settings tab to BookingX.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_hipaa', array( $this, 'render_settings_tab' ) );

		// Cron handlers.
		add_action( 'bkx_hipaa_audit_cleanup', array( $this, 'cleanup_audit_logs' ) );
		add_action( 'bkx_hipaa_access_review', array( $this, 'trigger_access_review' ) );
		add_action( 'bkx_hipaa_baa_expiry_check', array( $this, 'check_baa_expiry' ) );

		// Booking hooks for PHI protection.
		add_action( 'bkx_booking_created', array( $this, 'log_booking_access' ), 10, 2 );
		add_action( 'bkx_booking_updated', array( $this, 'log_booking_update' ), 10, 3 );
		add_filter( 'bkx_booking_data', array( $this, 'encrypt_phi_fields' ), 10, 2 );
		add_filter( 'bkx_get_booking_data', array( $this, 'decrypt_phi_fields' ), 10, 2 );

		// Login/session security.
		add_action( 'wp_login', array( $this, 'log_user_login' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'log_user_logout' ) );
		add_action( 'init', array( $this, 'check_session_timeout' ) );

		// Password security.
		add_filter( 'user_profile_update_errors', array( $this, 'validate_password_strength' ), 10, 3 );

		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'bkx-hipaa',
			false,
			dirname( BKX_HIPAA_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'HIPAA Compliance', 'bkx-hipaa' ),
			__( 'HIPAA Compliance', 'bkx-hipaa' ),
			'manage_options',
			'bkx-hipaa',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'bkx_booking_page_bkx-hipaa' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-hipaa-admin',
			BKX_HIPAA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_HIPAA_VERSION
		);

		wp_enqueue_script(
			'bkx-hipaa-admin',
			BKX_HIPAA_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_HIPAA_VERSION,
			true
		);

		wp_localize_script(
			'bkx-hipaa-admin',
			'bkxHipaaAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_hipaa_admin' ),
				'strings' => array(
					'settingsSaved' => __( 'Settings saved successfully.', 'bkx-hipaa' ),
					'error'         => __( 'An error occurred. Please try again.', 'bkx-hipaa' ),
					'confirmDelete' => __( 'Are you sure you want to delete this?', 'bkx-hipaa' ),
					'exportSuccess' => __( 'Audit log exported successfully.', 'bkx-hipaa' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_HIPAA_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Add settings tab to BookingX.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['hipaa'] = __( 'HIPAA', 'bkx-hipaa' );
		return $tabs;
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab() {
		include BKX_HIPAA_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_hipaa_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-hipaa' ) );
		}

		$settings = array(
			'enabled'                => ! empty( $_POST['enabled'] ),
			'encryption_method'      => isset( $_POST['encryption_method'] ) ? sanitize_text_field( wp_unslash( $_POST['encryption_method'] ) ) : 'aes-256-gcm',
			'audit_retention_days'   => isset( $_POST['audit_retention_days'] ) ? absint( $_POST['audit_retention_days'] ) : 365 * 6,
			'phi_fields'             => isset( $_POST['phi_fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['phi_fields'] ) ) : array(),
			'auto_logout_minutes'    => isset( $_POST['auto_logout_minutes'] ) ? absint( $_POST['auto_logout_minutes'] ) : 15,
			'require_strong_password' => ! empty( $_POST['require_strong_password'] ),
			'two_factor_required'    => ! empty( $_POST['two_factor_required'] ),
			'access_review_days'     => isset( $_POST['access_review_days'] ) ? absint( $_POST['access_review_days'] ) : 90,
			'breach_notification'    => ! empty( $_POST['breach_notification'] ),
		);

		update_option( 'bkx_hipaa_settings', $settings );
		$this->settings = $settings;

		// Log settings change.
		$this->get_service( 'audit_logger' )->log(
			'settings',
			'update',
			array(
				'resource_type' => 'hipaa_settings',
				'metadata'      => array( 'changed_by' => get_current_user_id() ),
			)
		);

		wp_send_json_success( __( 'Settings saved.', 'bkx-hipaa' ) );
	}

	/**
	 * AJAX: Export audit log.
	 */
	public function ajax_export_audit() {
		check_ajax_referer( 'bkx_hipaa_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-hipaa' ) );
		}

		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

		$audit_logger = $this->get_service( 'audit_logger' );
		$logs         = $audit_logger->get_logs(
			array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'limit'     => 10000,
			)
		);

		// Log the export action.
		$audit_logger->log(
			'audit',
			'export',
			array(
				'metadata' => array(
					'exported_by' => get_current_user_id(),
					'date_range'  => array( $date_from, $date_to ),
					'record_count' => count( $logs ),
				),
			)
		);

		wp_send_json_success(
			array(
				'logs'     => $logs,
				'filename' => 'hipaa-audit-log-' . gmdate( 'Y-m-d' ) . '.csv',
			)
		);
	}

	/**
	 * AJAX: Create BAA.
	 */
	public function ajax_create_baa() {
		check_ajax_referer( 'bkx_hipaa_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-hipaa' ) );
		}

		$baa_data = array(
			'vendor_name'    => isset( $_POST['vendor_name'] ) ? sanitize_text_field( wp_unslash( $_POST['vendor_name'] ) ) : '',
			'vendor_email'   => isset( $_POST['vendor_email'] ) ? sanitize_email( wp_unslash( $_POST['vendor_email'] ) ) : '',
			'vendor_contact' => isset( $_POST['vendor_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['vendor_contact'] ) ) : '',
			'signed_date'    => isset( $_POST['signed_date'] ) ? sanitize_text_field( wp_unslash( $_POST['signed_date'] ) ) : '',
			'expiry_date'    => isset( $_POST['expiry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expiry_date'] ) ) : '',
			'notes'          => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
		);

		$baa_manager = $this->get_service( 'baa_manager' );
		$result      = $baa_manager->create_baa( $baa_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'BAA created successfully.', 'bkx-hipaa' ) );
	}

	/**
	 * AJAX: Update access control.
	 */
	public function ajax_update_access() {
		check_ajax_referer( 'bkx_hipaa_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-hipaa' ) );
		}

		$user_id      = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$access_level = isset( $_POST['access_level'] ) ? sanitize_text_field( wp_unslash( $_POST['access_level'] ) ) : '';
		$phi_fields   = isset( $_POST['phi_fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['phi_fields'] ) ) : array();

		$access_control = $this->get_service( 'access_control' );
		$result         = $access_control->set_user_access( $user_id, $access_level, $phi_fields );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Access updated successfully.', 'bkx-hipaa' ) );
	}

	/**
	 * Log booking access.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function log_booking_access( $booking_id, $booking_data ) {
		$this->get_service( 'audit_logger' )->log(
			'phi',
			'create',
			array(
				'resource_type' => 'booking',
				'resource_id'   => $booking_id,
				'phi_accessed'  => true,
			)
		);
	}

	/**
	 * Log booking update.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $old_data   Old data.
	 * @param array $new_data   New data.
	 */
	public function log_booking_update( $booking_id, $old_data, $new_data ) {
		$phi_handler = $this->get_service( 'phi_handler' );

		$this->get_service( 'audit_logger' )->log(
			'phi',
			'update',
			array(
				'resource_type' => 'booking',
				'resource_id'   => $booking_id,
				'phi_accessed'  => true,
				'data_before'   => $phi_handler->redact_phi( $old_data ),
				'data_after'    => $phi_handler->redact_phi( $new_data ),
			)
		);
	}

	/**
	 * Encrypt PHI fields.
	 *
	 * @param array $data       Booking data.
	 * @param int   $booking_id Booking ID.
	 * @return array
	 */
	public function encrypt_phi_fields( $data, $booking_id ) {
		if ( ! $this->get_setting( 'enabled', true ) ) {
			return $data;
		}

		$phi_handler = $this->get_service( 'phi_handler' );
		return $phi_handler->encrypt_fields( $data );
	}

	/**
	 * Decrypt PHI fields.
	 *
	 * @param array $data       Booking data.
	 * @param int   $booking_id Booking ID.
	 * @return array
	 */
	public function decrypt_phi_fields( $data, $booking_id ) {
		if ( ! $this->get_setting( 'enabled', true ) ) {
			return $data;
		}

		// Check access control.
		$access_control = $this->get_service( 'access_control' );
		if ( ! $access_control->can_access_phi( get_current_user_id() ) ) {
			return $this->get_service( 'phi_handler' )->redact_phi( $data );
		}

		// Log PHI access.
		$this->get_service( 'audit_logger' )->log(
			'phi',
			'view',
			array(
				'resource_type' => 'booking',
				'resource_id'   => $booking_id,
				'phi_accessed'  => true,
			)
		);

		$phi_handler = $this->get_service( 'phi_handler' );
		return $phi_handler->decrypt_fields( $data );
	}

	/**
	 * Log user login.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user       User object.
	 */
	public function log_user_login( $user_login, $user ) {
		$this->get_service( 'audit_logger' )->log(
			'authentication',
			'login',
			array(
				'user_id'  => $user->ID,
				'metadata' => array(
					'username' => $user_login,
				),
			)
		);

		// Set session timestamp.
		update_user_meta( $user->ID, '_bkx_hipaa_last_activity', time() );
	}

	/**
	 * Log user logout.
	 */
	public function log_user_logout() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$this->get_service( 'audit_logger' )->log(
			'authentication',
			'logout',
			array(
				'user_id' => $user_id,
			)
		);

		delete_user_meta( $user_id, '_bkx_hipaa_last_activity' );
	}

	/**
	 * Check session timeout.
	 */
	public function check_session_timeout() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id        = get_current_user_id();
		$last_activity  = get_user_meta( $user_id, '_bkx_hipaa_last_activity', true );
		$timeout_minutes = $this->get_setting( 'auto_logout_minutes', 15 );

		if ( $last_activity && ( time() - $last_activity ) > ( $timeout_minutes * 60 ) ) {
			// Log auto-logout.
			$this->get_service( 'audit_logger' )->log(
				'authentication',
				'auto_logout',
				array(
					'user_id'  => $user_id,
					'metadata' => array(
						'reason' => 'session_timeout',
					),
				)
			);

			wp_logout();
			wp_safe_redirect( wp_login_url() . '?session_expired=1' );
			exit;
		}

		// Update last activity.
		update_user_meta( $user_id, '_bkx_hipaa_last_activity', time() );
	}

	/**
	 * Validate password strength.
	 *
	 * @param WP_Error $errors Errors.
	 * @param bool     $update Is update.
	 * @param object   $user   User data.
	 * @return WP_Error
	 */
	public function validate_password_strength( $errors, $update, $user ) {
		if ( ! $this->get_setting( 'require_strong_password', true ) ) {
			return $errors;
		}

		if ( isset( $_POST['pass1'] ) && ! empty( $_POST['pass1'] ) ) {
			$password = sanitize_text_field( wp_unslash( $_POST['pass1'] ) );

			// Check minimum length (8 characters).
			if ( strlen( $password ) < 8 ) {
				$errors->add( 'weak_password', __( 'Password must be at least 8 characters long.', 'bkx-hipaa' ) );
			}

			// Check for uppercase.
			if ( ! preg_match( '/[A-Z]/', $password ) ) {
				$errors->add( 'weak_password', __( 'Password must contain at least one uppercase letter.', 'bkx-hipaa' ) );
			}

			// Check for lowercase.
			if ( ! preg_match( '/[a-z]/', $password ) ) {
				$errors->add( 'weak_password', __( 'Password must contain at least one lowercase letter.', 'bkx-hipaa' ) );
			}

			// Check for number.
			if ( ! preg_match( '/[0-9]/', $password ) ) {
				$errors->add( 'weak_password', __( 'Password must contain at least one number.', 'bkx-hipaa' ) );
			}

			// Check for special character.
			if ( ! preg_match( '/[!@#$%^&*(),.?":{}|<>]/', $password ) ) {
				$errors->add( 'weak_password', __( 'Password must contain at least one special character.', 'bkx-hipaa' ) );
			}
		}

		return $errors;
	}

	/**
	 * Cleanup audit logs.
	 */
	public function cleanup_audit_logs() {
		$retention_days = $this->get_setting( 'audit_retention_days', 365 * 6 );
		$this->get_service( 'audit_logger' )->cleanup( $retention_days );
	}

	/**
	 * Trigger access review.
	 */
	public function trigger_access_review() {
		$access_control = $this->get_service( 'access_control' );
		$access_control->send_review_reminder();
	}

	/**
	 * Check BAA expiry.
	 */
	public function check_baa_expiry() {
		$baa_manager = $this->get_service( 'baa_manager' );
		$baa_manager->check_expiring_baas();
	}

	/**
	 * Get setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Check if HIPAA is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->get_setting( 'enabled', true );
	}
}

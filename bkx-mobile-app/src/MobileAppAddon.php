<?php
/**
 * Main Mobile App Addon Class.
 *
 * @package BookingX\MobileApp
 */

namespace BookingX\MobileApp;

defined( 'ABSPATH' ) || exit;

/**
 * MobileAppAddon class.
 */
class MobileAppAddon {

	/**
	 * Instance.
	 *
	 * @var MobileAppAddon
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
	 * @return MobileAppAddon
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
		$this->settings = get_option( 'bkx_mobile_app_settings', array() );

		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['api_manager']      = new Services\APIManager();
		$this->services['push_service']     = new Services\PushNotificationService();
		$this->services['device_manager']   = new Services\DeviceManager();
		$this->services['deep_link']        = new Services\DeepLinkService();
		$this->services['app_config']       = new Services\AppConfigService();
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

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_mobile_app_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_mobile_app_generate_key', array( $this, 'ajax_generate_api_key' ) );
		add_action( 'wp_ajax_bkx_mobile_app_revoke_key', array( $this, 'ajax_revoke_api_key' ) );
		add_action( 'wp_ajax_bkx_mobile_app_test_push', array( $this, 'ajax_test_push' ) );

		// Add settings tab to BookingX.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_mobile_app', array( $this, 'render_settings_tab' ) );

		// Booking hooks for push notifications.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this, 'on_booking_status_changed' ), 10, 3 );

		// Cron handlers.
		add_action( 'bkx_mobile_app_send_reminders', array( $this, 'send_booking_reminders' ) );

		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'bkx-mobile-app',
			false,
			dirname( BKX_MOBILE_APP_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$api_manager = $this->get_service( 'api_manager' );
		$api_manager->register_routes();
	}

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Mobile App', 'bkx-mobile-app' ),
			__( 'Mobile App', 'bkx-mobile-app' ),
			'manage_options',
			'bkx-mobile-app',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'bkx_booking_page_bkx-mobile-app' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-mobile-app-admin',
			BKX_MOBILE_APP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_MOBILE_APP_VERSION
		);

		wp_enqueue_script(
			'bkx-mobile-app-admin',
			BKX_MOBILE_APP_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_MOBILE_APP_VERSION,
			true
		);

		wp_localize_script(
			'bkx-mobile-app-admin',
			'bkxMobileAppAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_mobile_app_admin' ),
				'strings' => array(
					'settingsSaved'   => __( 'Settings saved successfully.', 'bkx-mobile-app' ),
					'error'           => __( 'An error occurred. Please try again.', 'bkx-mobile-app' ),
					'keyGenerated'    => __( 'API key generated successfully.', 'bkx-mobile-app' ),
					'keyRevoked'      => __( 'API key revoked.', 'bkx-mobile-app' ),
					'confirmRevoke'   => __( 'Are you sure you want to revoke this API key?', 'bkx-mobile-app' ),
					'pushSent'        => __( 'Test notification sent successfully.', 'bkx-mobile-app' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_MOBILE_APP_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Add settings tab to BookingX.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['mobile_app'] = __( 'Mobile App', 'bkx-mobile-app' );
		return $tabs;
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab() {
		include BKX_MOBILE_APP_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_mobile_app_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-mobile-app' ) );
		}

		$settings = array(
			'enabled'               => ! empty( $_POST['enabled'] ),
			'ios_enabled'           => ! empty( $_POST['ios_enabled'] ),
			'android_enabled'       => ! empty( $_POST['android_enabled'] ),
			'fcm_server_key'        => isset( $_POST['fcm_server_key'] ) ? sanitize_text_field( wp_unslash( $_POST['fcm_server_key'] ) ) : '',
			'apns_key_id'           => isset( $_POST['apns_key_id'] ) ? sanitize_text_field( wp_unslash( $_POST['apns_key_id'] ) ) : '',
			'apns_team_id'          => isset( $_POST['apns_team_id'] ) ? sanitize_text_field( wp_unslash( $_POST['apns_team_id'] ) ) : '',
			'apns_bundle_id'        => isset( $_POST['apns_bundle_id'] ) ? sanitize_text_field( wp_unslash( $_POST['apns_bundle_id'] ) ) : '',
			'deep_link_scheme'      => isset( $_POST['deep_link_scheme'] ) ? sanitize_text_field( wp_unslash( $_POST['deep_link_scheme'] ) ) : 'bookingx',
			'app_store_url'         => isset( $_POST['app_store_url'] ) ? esc_url_raw( wp_unslash( $_POST['app_store_url'] ) ) : '',
			'play_store_url'        => isset( $_POST['play_store_url'] ) ? esc_url_raw( wp_unslash( $_POST['play_store_url'] ) ) : '',
			'push_booking_created'  => ! empty( $_POST['push_booking_created'] ),
			'push_booking_confirmed' => ! empty( $_POST['push_booking_confirmed'] ),
			'push_booking_reminder' => ! empty( $_POST['push_booking_reminder'] ),
			'push_booking_cancelled' => ! empty( $_POST['push_booking_cancelled'] ),
			'reminder_hours'        => isset( $_POST['reminder_hours'] ) ? absint( $_POST['reminder_hours'] ) : 24,
		);

		update_option( 'bkx_mobile_app_settings', $settings );
		$this->settings = $settings;

		wp_send_json_success( __( 'Settings saved.', 'bkx-mobile-app' ) );
	}

	/**
	 * AJAX: Generate API key.
	 */
	public function ajax_generate_api_key() {
		check_ajax_referer( 'bkx_mobile_app_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-mobile-app' ) );
		}

		$key_name = isset( $_POST['key_name'] ) ? sanitize_text_field( wp_unslash( $_POST['key_name'] ) ) : '';

		if ( empty( $key_name ) ) {
			wp_send_json_error( __( 'Key name is required.', 'bkx-mobile-app' ) );
		}

		$api_manager = $this->get_service( 'api_manager' );
		$result      = $api_manager->generate_api_key( $key_name );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Revoke API key.
	 */
	public function ajax_revoke_api_key() {
		check_ajax_referer( 'bkx_mobile_app_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-mobile-app' ) );
		}

		$key_id = isset( $_POST['key_id'] ) ? absint( $_POST['key_id'] ) : 0;

		if ( ! $key_id ) {
			wp_send_json_error( __( 'Invalid key ID.', 'bkx-mobile-app' ) );
		}

		$api_manager = $this->get_service( 'api_manager' );
		$result      = $api_manager->revoke_api_key( $key_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'API key revoked.', 'bkx-mobile-app' ) );
	}

	/**
	 * AJAX: Test push notification.
	 */
	public function ajax_test_push() {
		check_ajax_referer( 'bkx_mobile_app_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-mobile-app' ) );
		}

		$device_token = isset( $_POST['device_token'] ) ? sanitize_text_field( wp_unslash( $_POST['device_token'] ) ) : '';
		$device_type  = isset( $_POST['device_type'] ) ? sanitize_text_field( wp_unslash( $_POST['device_type'] ) ) : 'android';

		if ( empty( $device_token ) ) {
			wp_send_json_error( __( 'Device token is required.', 'bkx-mobile-app' ) );
		}

		$push_service = $this->get_service( 'push_service' );
		$result       = $push_service->send_test_notification( $device_token, $device_type );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Test notification sent.', 'bkx-mobile-app' ) );
	}

	/**
	 * On booking created.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		if ( ! $this->get_setting( 'push_booking_created', true ) ) {
			return;
		}

		$push_service = $this->get_service( 'push_service' );
		$push_service->send_booking_notification( $booking_id, 'created' );
	}

	/**
	 * On booking status changed.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function on_booking_status_changed( $booking_id, $old_status, $new_status ) {
		$push_service = $this->get_service( 'push_service' );

		if ( 'bkx-ack' === $new_status && $this->get_setting( 'push_booking_confirmed', true ) ) {
			$push_service->send_booking_notification( $booking_id, 'confirmed' );
		}

		if ( 'bkx-cancelled' === $new_status && $this->get_setting( 'push_booking_cancelled', true ) ) {
			$push_service->send_booking_notification( $booking_id, 'cancelled' );
		}
	}

	/**
	 * Send booking reminders.
	 */
	public function send_booking_reminders() {
		if ( ! $this->get_setting( 'push_booking_reminder', true ) ) {
			return;
		}

		$push_service = $this->get_service( 'push_service' );
		$push_service->send_upcoming_reminders();
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
	 * Check if enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->get_setting( 'enabled', true );
	}
}

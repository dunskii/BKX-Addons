<?php
/**
 * Main Apple Siri Addon class.
 *
 * @package BookingX\AppleSiri
 */

namespace BookingX\AppleSiri;

defined( 'ABSPATH' ) || exit;

/**
 * AppleSiriAddon class.
 */
class AppleSiriAddon {

	/**
	 * Single instance.
	 *
	 * @var AppleSiriAddon
	 */
	private static $instance = null;

	/**
	 * Services container.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Get instance.
	 *
	 * @return AppleSiriAddon
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
		$this->load_settings();
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Load settings.
	 */
	private function load_settings() {
		$this->settings = get_option( 'bkx_apple_siri_settings', array() );
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services = array(
			'intent_handler'    => new Services\IntentHandler( $this ),
			'shortcut_manager'  => new Services\ShortcutManager( $this ),
			'voice_processor'   => new Services\VoiceProcessor( $this ),
			'apple_auth'        => new Services\AppleAuthService( $this ),
			'sirikit_api'       => new Services\SiriKitAPI( $this ),
		);
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_apple_siri_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_apple_siri_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_bkx_apple_siri_generate_shortcut', array( $this, 'ajax_generate_shortcut' ) );

		// BookingX integration.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_apple_siri', array( $this, 'render_settings_tab' ) );

		// Booking hooks.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this, 'on_booking_status_changed' ), 10, 3 );
	}

	/**
	 * Check if enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->settings['enabled'] );
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = '' ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Update settings.
	 *
	 * @param array $settings New settings.
	 */
	public function update_settings( array $settings ) {
		$this->settings = $settings;
		update_option( 'bkx_apple_siri_settings', $settings );
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
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Apple Siri', 'bkx-apple-siri' ),
			__( 'Apple Siri', 'bkx-apple-siri' ),
			'manage_options',
			'bkx-apple-siri',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';

		include BKX_APPLE_SIRI_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-apple-siri' ) === false && strpos( $hook, 'bkx_booking' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-apple-siri-admin',
			BKX_APPLE_SIRI_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_APPLE_SIRI_VERSION
		);

		wp_enqueue_script(
			'bkx-apple-siri-admin',
			BKX_APPLE_SIRI_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_APPLE_SIRI_VERSION,
			true
		);

		wp_localize_script(
			'bkx-apple-siri-admin',
			'bkxAppleSiri',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_apple_siri_nonce' ),
				'settings' => $this->settings,
				'i18n'     => array(
					'saving'           => __( 'Saving...', 'bkx-apple-siri' ),
					'saved'            => __( 'Settings saved!', 'bkx-apple-siri' ),
					'testing'          => __( 'Testing connection...', 'bkx-apple-siri' ),
					'connectionSuccess' => __( 'Connection successful!', 'bkx-apple-siri' ),
					'connectionFailed' => __( 'Connection failed.', 'bkx-apple-siri' ),
					'error'            => __( 'An error occurred.', 'bkx-apple-siri' ),
				),
			)
		);
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		// SiriKit Intent handling endpoint.
		register_rest_route(
			'bkx-apple-siri/v1',
			'/intent',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->get_service( 'intent_handler' ), 'handle_intent' ),
				'permission_callback' => array( $this, 'verify_siri_request' ),
			)
		);

		// Shortcuts donation endpoint.
		register_rest_route(
			'bkx-apple-siri/v1',
			'/shortcuts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->get_service( 'shortcut_manager' ), 'get_shortcuts' ),
				'permission_callback' => '__return_true',
			)
		);

		// Availability check for Siri.
		register_rest_route(
			'bkx-apple-siri/v1',
			'/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->get_service( 'voice_processor' ), 'check_availability' ),
				'permission_callback' => array( $this, 'verify_siri_request' ),
			)
		);

		// Booking creation from Siri.
		register_rest_route(
			'bkx-apple-siri/v1',
			'/book',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->get_service( 'voice_processor' ), 'create_booking' ),
				'permission_callback' => array( $this, 'verify_siri_request' ),
			)
		);
	}

	/**
	 * Verify Siri request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function verify_siri_request( $request ) {
		$auth_header = $request->get_header( 'Authorization' );

		if ( empty( $auth_header ) ) {
			return false;
		}

		// Verify JWT token from Apple.
		return $this->get_service( 'apple_auth' )->verify_token( $auth_header );
	}

	/**
	 * Add settings tab to BookingX.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['apple_siri'] = __( 'Apple Siri', 'bkx-apple-siri' );
		return $tabs;
	}

	/**
	 * Render settings tab content.
	 */
	public function render_settings_tab() {
		include BKX_APPLE_SIRI_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_apple_siri_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-apple-siri' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$sanitized = $this->sanitize_settings( $settings );

		$this->update_settings( $sanitized );

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'bkx-apple-siri' ) ) );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	private function sanitize_settings( $settings ) {
		$sanitized = array();

		// Boolean settings.
		$booleans = array( 'enabled', 'require_confirmation', 'send_booking_to_reminders', 'shortcuts_enabled', 'log_requests' );
		foreach ( $booleans as $key ) {
			$sanitized[ $key ] = ! empty( $settings[ $key ] );
		}

		// Text settings.
		$texts = array( 'app_id', 'team_id', 'key_id', 'bundle_identifier', 'webhook_secret' );
		foreach ( $texts as $key ) {
			$sanitized[ $key ] = isset( $settings[ $key ] ) ? sanitize_text_field( $settings[ $key ] ) : '';
		}

		// Private key (allow multiline).
		$sanitized['private_key'] = isset( $settings['private_key'] ) ? sanitize_textarea_field( $settings['private_key'] ) : '';

		// Service ID.
		$sanitized['default_service_id'] = isset( $settings['default_service_id'] ) ? absint( $settings['default_service_id'] ) : 0;

		// Intent types array.
		if ( isset( $settings['intent_types'] ) && is_array( $settings['intent_types'] ) ) {
			$sanitized['intent_types'] = array_map( 'sanitize_text_field', $settings['intent_types'] );
		} else {
			$sanitized['intent_types'] = array();
		}

		// Voice phrases.
		if ( isset( $settings['voice_phrases'] ) && is_array( $settings['voice_phrases'] ) ) {
			$sanitized['voice_phrases'] = array();
			foreach ( $settings['voice_phrases'] as $key => $phrase ) {
				$sanitized['voice_phrases'][ sanitize_key( $key ) ] = sanitize_text_field( $phrase );
			}
		} else {
			$sanitized['voice_phrases'] = array();
		}

		return $sanitized;
	}

	/**
	 * AJAX: Test connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'bkx_apple_siri_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-apple-siri' ) ) );
		}

		$result = $this->get_service( 'apple_auth' )->test_connection();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Generate shortcut.
	 */
	public function ajax_generate_shortcut() {
		check_ajax_referer( 'bkx_apple_siri_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-apple-siri' ) ) );
		}

		$type = isset( $_POST['shortcut_type'] ) ? sanitize_text_field( wp_unslash( $_POST['shortcut_type'] ) ) : 'book';

		$shortcut = $this->get_service( 'shortcut_manager' )->generate_shortcut( $type );

		if ( $shortcut ) {
			wp_send_json_success( $shortcut );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to generate shortcut.', 'bkx-apple-siri' ) ) );
		}
	}

	/**
	 * On booking created - donate to Siri.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Donate shortcut for future bookings.
		$this->get_service( 'shortcut_manager' )->donate_booking_shortcut( $booking_id, $booking_data );
	}

	/**
	 * On booking status changed.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function on_booking_status_changed( $booking_id, $old_status, $new_status ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Update Siri shortcuts based on status.
		$this->get_service( 'shortcut_manager' )->update_booking_shortcut( $booking_id, $new_status );
	}
}

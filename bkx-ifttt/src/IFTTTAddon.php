<?php
/**
 * Main IFTTT Addon class.
 *
 * @package BookingX\IFTTT
 */

namespace BookingX\IFTTT;

defined( 'ABSPATH' ) || exit;

/**
 * IFTTTAddon class.
 */
class IFTTTAddon {

	/**
	 * Single instance.
	 *
	 * @var IFTTTAddon
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
	 * @return IFTTTAddon
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
		$this->settings = get_option( 'bkx_ifttt_settings', array() );
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services = array(
			'trigger_handler' => new Services\TriggerHandler( $this ),
			'action_handler'  => new Services\ActionHandler( $this ),
			'webhook_manager' => new Services\WebhookManager( $this ),
			'api_client'      => new Services\APIClient( $this ),
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
		add_action( 'wp_ajax_bkx_ifttt_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_ifttt_test_trigger', array( $this, 'ajax_test_trigger' ) );
		add_action( 'wp_ajax_bkx_ifttt_add_webhook', array( $this, 'ajax_add_webhook' ) );
		add_action( 'wp_ajax_bkx_ifttt_delete_webhook', array( $this, 'ajax_delete_webhook' ) );

		// BookingX integration.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_ifttt', array( $this, 'render_settings_tab' ) );

		// Booking hooks for triggers.
		if ( $this->is_enabled() ) {
			add_action( 'bkx_booking_created', array( $this->get_service( 'trigger_handler' ), 'on_booking_created' ), 10, 2 );
			add_action( 'bkx_booking_status_changed', array( $this->get_service( 'trigger_handler' ), 'on_booking_status_changed' ), 10, 3 );
		}
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
		update_option( 'bkx_ifttt_settings', $settings );
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
			__( 'IFTTT', 'bkx-ifttt' ),
			__( 'IFTTT', 'bkx-ifttt' ),
			'manage_options',
			'bkx-ifttt',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';

		include BKX_IFTTT_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-ifttt' ) === false && strpos( $hook, 'bkx_booking' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-ifttt-admin',
			BKX_IFTTT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_IFTTT_VERSION
		);

		wp_enqueue_script(
			'bkx-ifttt-admin',
			BKX_IFTTT_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_IFTTT_VERSION,
			true
		);

		wp_localize_script(
			'bkx-ifttt-admin',
			'bkxIFTTT',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_ifttt_nonce' ),
				'settings' => $this->settings,
				'i18n'     => array(
					'saving'       => __( 'Saving...', 'bkx-ifttt' ),
					'saved'        => __( 'Settings saved!', 'bkx-ifttt' ),
					'testing'      => __( 'Sending test...', 'bkx-ifttt' ),
					'testSuccess'  => __( 'Test trigger sent!', 'bkx-ifttt' ),
					'testFailed'   => __( 'Test failed.', 'bkx-ifttt' ),
					'error'        => __( 'An error occurred.', 'bkx-ifttt' ),
					'confirmDelete' => __( 'Are you sure you want to delete this webhook?', 'bkx-ifttt' ),
				),
			)
		);
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		// IFTTT Service API endpoints.
		register_rest_route(
			'bkx-ifttt/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_status' ),
				'permission_callback' => '__return_true',
			)
		);

		// Triggers endpoint for IFTTT.
		register_rest_route(
			'bkx-ifttt/v1',
			'/triggers/(?P<trigger>[a-z_]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->get_service( 'trigger_handler' ), 'api_get_trigger_data' ),
				'permission_callback' => array( $this, 'verify_ifttt_request' ),
			)
		);

		// Actions endpoint for IFTTT.
		register_rest_route(
			'bkx-ifttt/v1',
			'/actions/(?P<action>[a-z_]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->get_service( 'action_handler' ), 'handle_action' ),
				'permission_callback' => array( $this, 'verify_ifttt_request' ),
			)
		);

		// Webhook receiver.
		register_rest_route(
			'bkx-ifttt/v1',
			'/webhook/(?P<id>[a-zA-Z0-9]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->get_service( 'webhook_manager' ), 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		// Test endpoint.
		register_rest_route(
			'bkx-ifttt/v1',
			'/test',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'api_test' ),
				'permission_callback' => array( $this, 'verify_ifttt_request' ),
			)
		);
	}

	/**
	 * API status endpoint.
	 *
	 * @return \WP_REST_Response
	 */
	public function api_status() {
		return new \WP_REST_Response(
			array(
				'status'      => 'OK',
				'service'     => 'BookingX IFTTT Service',
				'version'     => BKX_IFTTT_VERSION,
				'triggers'    => array_keys( array_filter( $this->get_setting( 'triggers', array() ) ) ),
				'actions'     => array_keys( array_filter( $this->get_setting( 'actions', array() ) ) ),
			),
			200
		);
	}

	/**
	 * API test endpoint.
	 *
	 * @return \WP_REST_Response
	 */
	public function api_test() {
		return new \WP_REST_Response(
			array(
				'data' => array(
					'samples' => array(
						array(
							'id'           => 'sample_' . time(),
							'booking_id'   => 12345,
							'service_name' => 'Sample Service',
							'date'         => gmdate( 'Y-m-d' ),
							'time'         => '10:00',
							'customer_name' => 'John Doe',
							'customer_email' => 'john@example.com',
						),
					),
				),
			),
			200
		);
	}

	/**
	 * Verify IFTTT request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function verify_ifttt_request( $request ) {
		$service_key = $this->get_setting( 'service_key' );

		// Check header.
		$auth_header = $request->get_header( 'IFTTT-Service-Key' );

		if ( $auth_header && $auth_header === $service_key ) {
			return true;
		}

		// Check channel key parameter.
		$channel_key = $request->get_param( 'key' );

		if ( $channel_key && $channel_key === $service_key ) {
			return true;
		}

		return false;
	}

	/**
	 * Add settings tab to BookingX.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['ifttt'] = __( 'IFTTT', 'bkx-ifttt' );
		return $tabs;
	}

	/**
	 * Render settings tab content.
	 */
	public function render_settings_tab() {
		include BKX_IFTTT_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_ifttt_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-ifttt' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings  = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$sanitized = $this->sanitize_settings( $settings );

		$this->update_settings( $sanitized );

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'bkx-ifttt' ) ) );
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
		$sanitized['enabled']      = ! empty( $settings['enabled'] );
		$sanitized['log_requests'] = ! empty( $settings['log_requests'] );

		// Service key.
		$sanitized['service_key'] = isset( $settings['service_key'] )
			? sanitize_text_field( $settings['service_key'] )
			: $this->get_setting( 'service_key', wp_generate_password( 32, false ) );

		// Rate limit.
		$sanitized['rate_limit'] = isset( $settings['rate_limit'] )
			? absint( $settings['rate_limit'] )
			: 100;

		// Triggers.
		$sanitized['triggers'] = array();
		$valid_triggers        = array( 'booking_created', 'booking_confirmed', 'booking_cancelled', 'booking_completed', 'booking_reminder' );
		if ( isset( $settings['triggers'] ) && is_array( $settings['triggers'] ) ) {
			foreach ( $valid_triggers as $trigger ) {
				$sanitized['triggers'][ $trigger ] = ! empty( $settings['triggers'][ $trigger ] );
			}
		}

		// Actions.
		$sanitized['actions'] = array();
		$valid_actions        = array( 'create_booking', 'cancel_booking', 'update_booking' );
		if ( isset( $settings['actions'] ) && is_array( $settings['actions'] ) ) {
			foreach ( $valid_actions as $action ) {
				$sanitized['actions'][ $action ] = ! empty( $settings['actions'][ $action ] );
			}
		}

		// Preserve webhooks.
		$sanitized['webhooks'] = $this->get_setting( 'webhooks', array() );

		return $sanitized;
	}

	/**
	 * AJAX: Test trigger.
	 */
	public function ajax_test_trigger() {
		check_ajax_referer( 'bkx_ifttt_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-ifttt' ) ) );
		}

		$trigger = isset( $_POST['trigger'] ) ? sanitize_text_field( wp_unslash( $_POST['trigger'] ) ) : 'booking_created';

		$result = $this->get_service( 'trigger_handler' )->send_test_trigger( $trigger );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Test trigger sent successfully.', 'bkx-ifttt' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send test trigger.', 'bkx-ifttt' ) ) );
		}
	}

	/**
	 * AJAX: Add webhook.
	 */
	public function ajax_add_webhook() {
		check_ajax_referer( 'bkx_ifttt_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-ifttt' ) ) );
		}

		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$url     = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$event   = isset( $_POST['event'] ) ? sanitize_text_field( wp_unslash( $_POST['event'] ) ) : '';

		if ( empty( $name ) || empty( $url ) || empty( $event ) ) {
			wp_send_json_error( array( 'message' => __( 'All fields are required.', 'bkx-ifttt' ) ) );
		}

		$webhooks   = $this->get_setting( 'webhooks', array() );
		$webhook_id = wp_generate_password( 12, false );

		$webhooks[ $webhook_id ] = array(
			'id'         => $webhook_id,
			'name'       => $name,
			'url'        => $url,
			'event'      => $event,
			'created_at' => current_time( 'mysql' ),
			'last_used'  => null,
			'call_count' => 0,
		);

		$this->settings['webhooks'] = $webhooks;
		$this->update_settings( $this->settings );

		wp_send_json_success(
			array(
				'message' => __( 'Webhook added successfully.', 'bkx-ifttt' ),
				'webhook' => $webhooks[ $webhook_id ],
			)
		);
	}

	/**
	 * AJAX: Delete webhook.
	 */
	public function ajax_delete_webhook() {
		check_ajax_referer( 'bkx_ifttt_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-ifttt' ) ) );
		}

		$webhook_id = isset( $_POST['webhook_id'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_id'] ) ) : '';

		if ( empty( $webhook_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid webhook ID.', 'bkx-ifttt' ) ) );
		}

		$webhooks = $this->get_setting( 'webhooks', array() );

		if ( isset( $webhooks[ $webhook_id ] ) ) {
			unset( $webhooks[ $webhook_id ] );
			$this->settings['webhooks'] = $webhooks;
			$this->update_settings( $this->settings );

			wp_send_json_success( array( 'message' => __( 'Webhook deleted successfully.', 'bkx-ifttt' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Webhook not found.', 'bkx-ifttt' ) ) );
	}
}

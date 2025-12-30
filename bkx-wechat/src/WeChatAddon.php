<?php
/**
 * Main WeChat Addon class.
 *
 * @package BookingX\WeChat
 */

namespace BookingX\WeChat;

defined( 'ABSPATH' ) || exit;

/**
 * WeChatAddon class.
 */
class WeChatAddon {

	/**
	 * Single instance.
	 *
	 * @var WeChatAddon
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
	 * @return WeChatAddon
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
		$this->settings = get_option( 'bkx_wechat_settings', array() );
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services = array(
			'wechat_api'       => new Services\WeChatAPI( $this ),
			'official_account' => new Services\OfficialAccountService( $this ),
			'mini_program'     => new Services\MiniProgramService( $this ),
			'wechat_pay'       => new Services\WeChatPayService( $this ),
			'message_handler'  => new Services\MessageHandler( $this ),
			'qr_code'          => new Services\QRCodeService( $this ),
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
		add_action( 'wp_ajax_bkx_wechat_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_wechat_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_bkx_wechat_generate_qrcode', array( $this, 'ajax_generate_qrcode' ) );
		add_action( 'wp_ajax_bkx_wechat_sync_menu', array( $this, 'ajax_sync_menu' ) );

		// BookingX integration.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_wechat', array( $this, 'render_settings_tab' ) );

		// Booking hooks.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this, 'on_booking_status_changed' ), 10, 3 );

		// Cron for reminders.
		add_action( 'bkx_wechat_send_reminders', array( $this, 'send_scheduled_reminders' ) );

		// Register payment gateway.
		add_filter( 'bkx_payment_gateways', array( $this, 'register_payment_gateway' ) );
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
		update_option( 'bkx_wechat_settings', $settings );
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
			__( 'WeChat', 'bkx-wechat' ),
			__( 'WeChat', 'bkx-wechat' ),
			'manage_options',
			'bkx-wechat',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';

		include BKX_WECHAT_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-wechat' ) === false && strpos( $hook, 'bkx_booking' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-wechat-admin',
			BKX_WECHAT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_WECHAT_VERSION
		);

		wp_enqueue_script(
			'bkx-wechat-admin',
			BKX_WECHAT_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_WECHAT_VERSION,
			true
		);

		wp_localize_script(
			'bkx-wechat-admin',
			'bkxWeChat',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_wechat_nonce' ),
				'settings' => $this->settings,
				'i18n'     => array(
					'saving'            => __( 'Saving...', 'bkx-wechat' ),
					'saved'             => __( 'Settings saved!', 'bkx-wechat' ),
					'testing'           => __( 'Testing connection...', 'bkx-wechat' ),
					'connectionSuccess' => __( 'Connection successful!', 'bkx-wechat' ),
					'connectionFailed'  => __( 'Connection failed.', 'bkx-wechat' ),
					'error'             => __( 'An error occurred.', 'bkx-wechat' ),
					'generating'        => __( 'Generating QR code...', 'bkx-wechat' ),
					'syncing'           => __( 'Syncing menu...', 'bkx-wechat' ),
				),
			)
		);
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		// WeChat server verification & message callback.
		register_rest_route(
			'bkx-wechat/v1',
			'/callback',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->get_service( 'message_handler' ), 'verify_server' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this->get_service( 'message_handler' ), 'handle_message' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Mini Program API.
		register_rest_route(
			'bkx-wechat/v1',
			'/mini/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->get_service( 'mini_program' ), 'handle_login' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bkx-wechat/v1',
			'/mini/services',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->get_service( 'mini_program' ), 'get_services' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bkx-wechat/v1',
			'/mini/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->get_service( 'mini_program' ), 'get_availability' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bkx-wechat/v1',
			'/mini/book',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->get_service( 'mini_program' ), 'create_booking' ),
				'permission_callback' => array( $this, 'verify_mini_program_user' ),
			)
		);

		register_rest_route(
			'bkx-wechat/v1',
			'/mini/bookings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->get_service( 'mini_program' ), 'get_user_bookings' ),
				'permission_callback' => array( $this, 'verify_mini_program_user' ),
			)
		);

		// WeChat Pay callback.
		register_rest_route(
			'bkx-wechat/v1',
			'/pay/notify',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->get_service( 'wechat_pay' ), 'handle_payment_notification' ),
				'permission_callback' => '__return_true',
			)
		);

		// QR Code generation.
		register_rest_route(
			'bkx-wechat/v1',
			'/qrcode/(?P<type>[a-z_]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->get_service( 'qr_code' ), 'generate_qr_code' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verify Mini Program user.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function verify_mini_program_user( $request ) {
		$token = $request->get_header( 'X-WeChat-Token' );

		if ( empty( $token ) ) {
			return false;
		}

		return $this->get_service( 'mini_program' )->verify_session_token( $token );
	}

	/**
	 * Add settings tab to BookingX.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['wechat'] = __( 'WeChat', 'bkx-wechat' );
		return $tabs;
	}

	/**
	 * Render settings tab content.
	 */
	public function render_settings_tab() {
		include BKX_WECHAT_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_wechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-wechat' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings  = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$sanitized = $this->sanitize_settings( $settings );

		$this->update_settings( $sanitized );

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'bkx-wechat' ) ) );
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
		$booleans = array(
			'enabled',
			'official_account_enabled',
			'mini_program_enabled',
			'wechat_pay_enabled',
			'auto_reply_enabled',
			'qr_code_enabled',
			'sandbox_mode',
			'debug_mode',
		);
		foreach ( $booleans as $key ) {
			$sanitized[ $key ] = ! empty( $settings[ $key ] );
		}

		// Text settings.
		$texts = array(
			'app_id',
			'app_secret',
			'mch_id',
			'api_key',
			'api_v3_key',
			'certificate_serial',
			'private_key_path',
			'certificate_path',
			'mini_program_app_id',
			'mini_program_secret',
		);
		foreach ( $texts as $key ) {
			$sanitized[ $key ] = isset( $settings[ $key ] ) ? sanitize_text_field( $settings[ $key ] ) : '';
		}

		// Template messages.
		if ( isset( $settings['template_messages'] ) && is_array( $settings['template_messages'] ) ) {
			$sanitized['template_messages'] = array();
			foreach ( $settings['template_messages'] as $key => $value ) {
				$sanitized['template_messages'][ sanitize_key( $key ) ] = sanitize_text_field( $value );
			}
		} else {
			$sanitized['template_messages'] = array();
		}

		// Menu config.
		if ( isset( $settings['menu_config'] ) ) {
			$sanitized['menu_config'] = $this->sanitize_menu_config( $settings['menu_config'] );
		} else {
			$sanitized['menu_config'] = array();
		}

		// Auto reply rules.
		if ( isset( $settings['auto_reply_rules'] ) && is_array( $settings['auto_reply_rules'] ) ) {
			$sanitized['auto_reply_rules'] = array();
			foreach ( $settings['auto_reply_rules'] as $rule ) {
				$sanitized['auto_reply_rules'][] = array(
					'keyword' => sanitize_text_field( $rule['keyword'] ?? '' ),
					'type'    => sanitize_text_field( $rule['type'] ?? 'text' ),
					'content' => sanitize_textarea_field( $rule['content'] ?? '' ),
				);
			}
		} else {
			$sanitized['auto_reply_rules'] = array();
		}

		return $sanitized;
	}

	/**
	 * Sanitize menu config.
	 *
	 * @param mixed $config Menu configuration.
	 * @return array
	 */
	private function sanitize_menu_config( $config ) {
		if ( ! is_array( $config ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $config as $item ) {
			$sanitized_item = array(
				'name' => sanitize_text_field( $item['name'] ?? '' ),
				'type' => sanitize_text_field( $item['type'] ?? 'view' ),
			);

			if ( isset( $item['url'] ) ) {
				$sanitized_item['url'] = esc_url_raw( $item['url'] );
			}

			if ( isset( $item['key'] ) ) {
				$sanitized_item['key'] = sanitize_text_field( $item['key'] );
			}

			if ( isset( $item['sub_button'] ) && is_array( $item['sub_button'] ) ) {
				$sanitized_item['sub_button'] = $this->sanitize_menu_config( $item['sub_button'] );
			}

			$sanitized[] = $sanitized_item;
		}

		return $sanitized;
	}

	/**
	 * AJAX: Test connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'bkx_wechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-wechat' ) ) );
		}

		$result = $this->get_service( 'wechat_api' )->test_connection();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Generate QR code.
	 */
	public function ajax_generate_qrcode() {
		check_ajax_referer( 'bkx_wechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-wechat' ) ) );
		}

		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'follow';

		$result = $this->get_service( 'qr_code' )->generate( $type );

		if ( $result ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to generate QR code.', 'bkx-wechat' ) ) );
		}
	}

	/**
	 * AJAX: Sync menu.
	 */
	public function ajax_sync_menu() {
		check_ajax_referer( 'bkx_wechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-wechat' ) ) );
		}

		$result = $this->get_service( 'official_account' )->sync_menu();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * On booking created - send WeChat notification.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Get WeChat user ID from booking.
		$wechat_openid = get_post_meta( $booking_id, '_wechat_openid', true );

		if ( empty( $wechat_openid ) ) {
			return;
		}

		// Send confirmation message.
		$template_id = $this->settings['template_messages']['booking_confirmed'] ?? '';

		if ( ! empty( $template_id ) ) {
			$this->get_service( 'official_account' )->send_template_message(
				$wechat_openid,
				$template_id,
				array(
					'booking_id'   => $booking_id,
					'service_name' => $booking_data['service_name'] ?? '',
					'date'         => $booking_data['date'] ?? '',
					'time'         => $booking_data['time'] ?? '',
				)
			);
		}

		// Schedule reminder.
		$this->schedule_reminder( $booking_id, $booking_data );
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

		$wechat_openid = get_post_meta( $booking_id, '_wechat_openid', true );

		if ( empty( $wechat_openid ) ) {
			return;
		}

		// Send cancellation notification.
		if ( 'bkx-cancelled' === $new_status ) {
			$template_id = $this->settings['template_messages']['booking_cancelled'] ?? '';

			if ( ! empty( $template_id ) ) {
				$this->get_service( 'official_account' )->send_template_message(
					$wechat_openid,
					$template_id,
					array( 'booking_id' => $booking_id )
				);
			}
		}
	}

	/**
	 * Schedule booking reminder.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	private function schedule_reminder( $booking_id, $booking_data ) {
		$booking_datetime = strtotime( $booking_data['date'] . ' ' . $booking_data['time'] );
		$reminder_time    = $booking_datetime - HOUR_IN_SECONDS; // 1 hour before.

		if ( $reminder_time > time() ) {
			wp_schedule_single_event(
				$reminder_time,
				'bkx_wechat_send_reminders',
				array( $booking_id )
			);
		}
	}

	/**
	 * Send scheduled reminders.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public function send_scheduled_reminders( $booking_id ) {
		$wechat_openid = get_post_meta( $booking_id, '_wechat_openid', true );
		$template_id   = $this->settings['template_messages']['booking_reminder'] ?? '';

		if ( empty( $wechat_openid ) || empty( $template_id ) ) {
			return;
		}

		$this->get_service( 'official_account' )->send_template_message(
			$wechat_openid,
			$template_id,
			array( 'booking_id' => $booking_id )
		);
	}

	/**
	 * Register WeChat Pay gateway.
	 *
	 * @param array $gateways Existing gateways.
	 * @return array
	 */
	public function register_payment_gateway( $gateways ) {
		if ( $this->get_setting( 'wechat_pay_enabled', false ) ) {
			$gateways['wechat_pay'] = 'BookingX\\WeChat\\Gateways\\WeChatPayGateway';
		}
		return $gateways;
	}
}

<?php
/**
 * Main Google Assistant Addon Class.
 *
 * @package BookingX\GoogleAssistant
 */

namespace BookingX\GoogleAssistant;

use BookingX\GoogleAssistant\Services\WebhookHandler;
use BookingX\GoogleAssistant\Services\IntentHandler;
use BookingX\GoogleAssistant\Services\SessionManager;
use BookingX\GoogleAssistant\Services\AccountLinker;

defined( 'ABSPATH' ) || exit;

/**
 * GoogleAssistantAddon class.
 */
class GoogleAssistantAddon {

	/**
	 * Webhook handler.
	 *
	 * @var WebhookHandler
	 */
	private $webhook_handler;

	/**
	 * Intent handler.
	 *
	 * @var IntentHandler
	 */
	private $intent_handler;

	/**
	 * Session manager.
	 *
	 * @var SessionManager
	 */
	private $session_manager;

	/**
	 * Account linker.
	 *
	 * @var AccountLinker
	 */
	private $account_linker;

	/**
	 * Initialize the addon.
	 */
	public function init() {
		$this->load_services();
		$this->register_hooks();
	}

	/**
	 * Load services.
	 */
	private function load_services() {
		$this->session_manager = new SessionManager();
		$this->account_linker  = new AccountLinker();
		$this->intent_handler  = new IntentHandler( $this->session_manager, $this->account_linker );
		$this->webhook_handler = new WebhookHandler( $this->intent_handler );
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// REST API endpoints.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_assistant_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_assistant_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_bkx_assistant_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_bkx_assistant_export_action_package', array( $this, 'ajax_export_action_package' ) );

		// Cron for session cleanup.
		add_action( 'bkx_assistant_cleanup_sessions', array( $this, 'cleanup_expired_sessions' ) );

		if ( ! wp_next_scheduled( 'bkx_assistant_cleanup_sessions' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_assistant_cleanup_sessions' );
		}

		// Settings link.
		add_filter( 'plugin_action_links_' . BKX_GOOGLE_ASSISTANT_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Google Assistant', 'bkx-google-assistant' ),
			__( 'Google Assistant', 'bkx-google-assistant' ),
			'manage_options',
			'bkx-google-assistant',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-google-assistant' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-google-assistant-admin',
			BKX_GOOGLE_ASSISTANT_URL . 'assets/css/admin.css',
			array(),
			BKX_GOOGLE_ASSISTANT_VERSION
		);

		wp_enqueue_script(
			'bkx-google-assistant-admin',
			BKX_GOOGLE_ASSISTANT_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_GOOGLE_ASSISTANT_VERSION,
			true
		);

		wp_localize_script(
			'bkx-google-assistant-admin',
			'bkxAssistant',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'bkx_google_assistant' ),
				'webhookUrl' => rest_url( 'bkx-assistant/v1/webhook' ),
				'i18n'       => array(
					'saved'           => __( 'Settings saved!', 'bkx-google-assistant' ),
					'testSuccess'     => __( 'Connection test successful!', 'bkx-google-assistant' ),
					'testFailed'      => __( 'Connection test failed.', 'bkx-google-assistant' ),
					'exportSuccess'   => __( 'Action package exported!', 'bkx-google-assistant' ),
				),
			)
		);
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes() {
		// Main webhook endpoint for Google Assistant.
		register_rest_route(
			'bkx-assistant/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->webhook_handler, 'handle_request' ),
				'permission_callback' => array( $this, 'verify_google_request' ),
			)
		);

		// Account linking endpoints.
		register_rest_route(
			'bkx-assistant/v1',
			'/auth',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->account_linker, 'handle_auth_request' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bkx-assistant/v1',
			'/token',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->account_linker, 'handle_token_request' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verify Google request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function verify_google_request( $request ) {
		$settings = get_option( 'bkx_google_assistant_settings', array() );

		// In development mode, skip verification.
		if ( ! empty( $settings['dev_mode'] ) ) {
			return true;
		}

		// Verify the request is from Google.
		$authorization = $request->get_header( 'authorization' );

		if ( empty( $authorization ) ) {
			return false;
		}

		// Extract Bearer token.
		if ( preg_match( '/Bearer\s+(.+)/', $authorization, $matches ) ) {
			$token = $matches[1];
			// Verify JWT token from Google.
			return $this->verify_google_jwt( $token );
		}

		return false;
	}

	/**
	 * Verify Google JWT token.
	 *
	 * @param string $token JWT token.
	 * @return bool
	 */
	private function verify_google_jwt( $token ) {
		// Decode JWT header and payload.
		$parts = explode( '.', $token );

		if ( count( $parts ) !== 3 ) {
			return false;
		}

		$payload = json_decode( base64_decode( $parts[1] ), true );

		if ( ! $payload ) {
			return false;
		}

		// Verify issuer.
		$valid_issuers = array(
			'https://accounts.google.com',
			'accounts.google.com',
		);

		if ( ! isset( $payload['iss'] ) || ! in_array( $payload['iss'], $valid_issuers, true ) ) {
			return false;
		}

		// Verify expiration.
		if ( ! isset( $payload['exp'] ) || $payload['exp'] < time() ) {
			return false;
		}

		// Verify audience (project ID).
		$settings = get_option( 'bkx_google_assistant_settings', array() );
		if ( ! empty( $settings['project_id'] ) && isset( $payload['aud'] ) ) {
			if ( $payload['aud'] !== $settings['project_id'] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
		include BKX_GOOGLE_ASSISTANT_PATH . 'templates/admin/page.php';
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_google_assistant', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-google-assistant' ) ) );
		}

		$settings = array(
			'enabled'           => isset( $_POST['enabled'] ) ? 1 : 0,
			'project_id'        => isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '',
			'client_id'         => isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '',
			'client_secret'     => isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '',
			'invocation_name'   => isset( $_POST['invocation_name'] ) ? sanitize_text_field( wp_unslash( $_POST['invocation_name'] ) ) : '',
			'welcome_message'   => isset( $_POST['welcome_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['welcome_message'] ) ) : '',
			'dev_mode'          => isset( $_POST['dev_mode'] ) ? 1 : 0,
			'require_linking'   => isset( $_POST['require_linking'] ) ? 1 : 0,
		);

		update_option( 'bkx_google_assistant_settings', $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'bkx-google-assistant' ) ) );
	}

	/**
	 * AJAX: Test connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'bkx_google_assistant', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-google-assistant' ) ) );
		}

		$settings = get_option( 'bkx_google_assistant_settings', array() );

		if ( empty( $settings['project_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Project ID is required.', 'bkx-google-assistant' ) ) );
		}

		// Test Google Cloud connection.
		$response = wp_remote_get(
			'https://actions.googleapis.com/v2/projects/' . $settings['project_id'],
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );

		// 401/403 is expected without auth - means the endpoint exists.
		if ( in_array( $code, array( 200, 401, 403 ), true ) ) {
			wp_send_json_success( array( 'message' => __( 'Connection successful!', 'bkx-google-assistant' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Connection failed. Check your Project ID.', 'bkx-google-assistant' ) ) );
	}

	/**
	 * AJAX: Get stats.
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'bkx_google_assistant', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-google-assistant' ) ) );
		}

		global $wpdb;

		$logs_table     = $wpdb->prefix . 'bkx_assistant_logs';
		$accounts_table = $wpdb->prefix . 'bkx_assistant_accounts';

		$stats = array(
			'total_requests'    => $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table}" ), // phpcs:ignore
			'successful'        => $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE status = 'success'" ), // phpcs:ignore
			'bookings_created'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE booking_id IS NOT NULL" ), // phpcs:ignore
			'linked_accounts'   => $wpdb->get_var( "SELECT COUNT(*) FROM {$accounts_table}" ), // phpcs:ignore
			'today_requests'    => $wpdb->get_var( // phpcs:ignore
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$logs_table} WHERE created_at >= %s",
					gmdate( 'Y-m-d 00:00:00' )
				)
			),
		);

		wp_send_json_success( $stats );
	}

	/**
	 * AJAX: Export action package.
	 */
	public function ajax_export_action_package() {
		check_ajax_referer( 'bkx_google_assistant', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-google-assistant' ) ) );
		}

		$settings = get_option( 'bkx_google_assistant_settings', array() );

		$action_package = array(
			'manifest'     => array(
				'displayName' => get_bloginfo( 'name' ) . ' Booking',
				'invocation'  => array(
					'name' => $settings['invocation_name'] ?? 'book appointment',
				),
				'category'    => 'BUSINESS_AND_FINANCE',
			),
			'actions'      => array(
				array(
					'name'        => 'actions.intent.MAIN',
					'fulfillment' => array(
						'conversationName' => 'booking_main',
					),
				),
				array(
					'name'        => 'book_appointment',
					'fulfillment' => array(
						'conversationName' => 'booking_flow',
					),
				),
				array(
					'name'        => 'check_availability',
					'fulfillment' => array(
						'conversationName' => 'check_availability',
					),
				),
				array(
					'name'        => 'list_services',
					'fulfillment' => array(
						'conversationName' => 'list_services',
					),
				),
				array(
					'name'        => 'cancel_booking',
					'fulfillment' => array(
						'conversationName' => 'cancel_booking',
					),
				),
			),
			'conversations' => array(
				'booking_main' => array(
					'name'               => 'booking_main',
					'url'                => rest_url( 'bkx-assistant/v1/webhook' ),
					'fulfillmentApiVersion' => 2,
				),
			),
		);

		wp_send_json_success( array( 'package' => $action_package ) );
	}

	/**
	 * Cleanup expired sessions.
	 */
	public function cleanup_expired_sessions() {
		$this->session_manager->cleanup_expired();
	}

	/**
	 * Add settings link.
	 *
	 * @param array $links Plugin links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=bkx-google-assistant' ) . '">' . __( 'Settings', 'bkx-google-assistant' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}

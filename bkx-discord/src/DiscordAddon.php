<?php
/**
 * Main Discord Addon class.
 *
 * @package BookingX\Discord
 */

namespace BookingX\Discord;

use BookingX\Discord\Services\DiscordApi;
use BookingX\Discord\Services\NotificationService;
use BookingX\Discord\Services\WebhookManager;
use BookingX\Discord\Services\BotHandler;

defined( 'ABSPATH' ) || exit;

/**
 * DiscordAddon class.
 */
class DiscordAddon {

	/**
	 * Single instance.
	 *
	 * @var DiscordAddon
	 */
	private static $instance = null;

	/**
	 * Discord API service.
	 *
	 * @var DiscordApi
	 */
	private $api;

	/**
	 * Notification service.
	 *
	 * @var NotificationService
	 */
	private $notifications;

	/**
	 * Webhook manager.
	 *
	 * @var WebhookManager
	 */
	private $webhooks;

	/**
	 * Bot handler.
	 *
	 * @var BotHandler
	 */
	private $bot;

	/**
	 * Get instance.
	 *
	 * @return DiscordAddon
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
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->api           = new DiscordApi();
		$this->webhooks      = new WebhookManager();
		$this->notifications = new NotificationService( $this->api, $this->webhooks );
		$this->bot           = new BotHandler( $this->api );
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// REST API endpoints.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_discord_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_discord_add_webhook', array( $this, 'ajax_add_webhook' ) );
		add_action( 'wp_ajax_bkx_discord_delete_webhook', array( $this, 'ajax_delete_webhook' ) );
		add_action( 'wp_ajax_bkx_discord_test_webhook', array( $this, 'ajax_test_webhook' ) );
		add_action( 'wp_ajax_bkx_discord_toggle_webhook', array( $this, 'ajax_toggle_webhook' ) );
		add_action( 'wp_ajax_bkx_discord_clear_logs', array( $this, 'ajax_clear_logs' ) );

		// Booking hooks.
		add_action( 'bkx_booking_created', array( $this->notifications, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this->notifications, 'on_status_changed' ), 10, 3 );
		add_action( 'bkx_booking_rescheduled', array( $this->notifications, 'on_booking_rescheduled' ), 10, 2 );

		// Cron for log cleanup.
		add_action( 'bkx_discord_cleanup_logs', array( $this, 'cleanup_old_logs' ) );

		if ( ! wp_next_scheduled( 'bkx_discord_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_discord_cleanup_logs' );
		}
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Discord Notifications', 'bkx-discord' ),
			__( 'Discord', 'bkx-discord' ),
			'manage_options',
			'bkx-discord',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bkx_booking_page_bkx-discord' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-discord-admin',
			BKX_DISCORD_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_DISCORD_VERSION
		);

		wp_enqueue_script(
			'bkx-discord-admin',
			BKX_DISCORD_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_DISCORD_VERSION,
			true
		);

		wp_localize_script(
			'bkx-discord-admin',
			'bkxDiscord',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_discord_nonce' ),
				'i18n'    => array(
					'saved'            => __( 'Settings saved successfully.', 'bkx-discord' ),
					'error'            => __( 'An error occurred. Please try again.', 'bkx-discord' ),
					'testSent'         => __( 'Test notification sent!', 'bkx-discord' ),
					'confirmDelete'    => __( 'Are you sure you want to delete this webhook?', 'bkx-discord' ),
					'webhookAdded'     => __( 'Webhook added successfully.', 'bkx-discord' ),
					'webhookDeleted'   => __( 'Webhook deleted.', 'bkx-discord' ),
				),
			)
		);
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes() {
		// Discord bot interactions endpoint.
		register_rest_route(
			'bkx-discord/v1',
			'/interactions',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->bot, 'handle_interaction' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'webhooks';

		include BKX_DISCORD_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_discord_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-discord' ) ) );
		}

		$settings = array(
			'embed_color'        => sanitize_hex_color( wp_unslash( $_POST['embed_color'] ?? '#5865F2' ) ),
			'bot_username'       => sanitize_text_field( wp_unslash( $_POST['bot_username'] ?? 'BookingX' ) ),
			'include_customer'   => ! empty( $_POST['include_customer'] ),
			'include_staff'      => ! empty( $_POST['include_staff'] ),
			'include_price'      => ! empty( $_POST['include_price'] ),
			'mention_role'       => sanitize_text_field( wp_unslash( $_POST['mention_role'] ?? '' ) ),
			'notify_new'         => ! empty( $_POST['notify_new'] ),
			'notify_cancelled'   => ! empty( $_POST['notify_cancelled'] ),
			'notify_completed'   => ! empty( $_POST['notify_completed'] ),
			'notify_rescheduled' => ! empty( $_POST['notify_rescheduled'] ),
		);

		update_option( 'bkx_discord_settings', $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'bkx-discord' ) ) );
	}

	/**
	 * AJAX: Add webhook.
	 */
	public function ajax_add_webhook() {
		check_ajax_referer( 'bkx_discord_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-discord' ) ) );
		}

		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$webhook_url = esc_url_raw( wp_unslash( $_POST['webhook_url'] ?? '' ) );
		$events      = isset( $_POST['events'] ) ? array_map( 'sanitize_text_field', (array) $_POST['events'] ) : array();

		if ( empty( $name ) || empty( $webhook_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Name and webhook URL are required.', 'bkx-discord' ) ) );
		}

		// Validate Discord webhook URL.
		if ( ! preg_match( '/^https:\/\/(discord\.com|discordapp\.com)\/api\/webhooks\/\d+\/[\w-]+$/', $webhook_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid Discord webhook URL.', 'bkx-discord' ) ) );
		}

		$result = $this->webhooks->add_webhook( $name, $webhook_url, $events );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Webhook added successfully.', 'bkx-discord' ),
			'webhook' => $result,
		) );
	}

	/**
	 * AJAX: Delete webhook.
	 */
	public function ajax_delete_webhook() {
		check_ajax_referer( 'bkx_discord_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-discord' ) ) );
		}

		$webhook_id = absint( $_POST['webhook_id'] ?? 0 );

		if ( ! $webhook_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid webhook ID.', 'bkx-discord' ) ) );
		}

		$this->webhooks->delete_webhook( $webhook_id );

		wp_send_json_success( array( 'message' => __( 'Webhook deleted.', 'bkx-discord' ) ) );
	}

	/**
	 * AJAX: Test webhook.
	 */
	public function ajax_test_webhook() {
		check_ajax_referer( 'bkx_discord_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-discord' ) ) );
		}

		$webhook_id = absint( $_POST['webhook_id'] ?? 0 );

		if ( ! $webhook_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid webhook ID.', 'bkx-discord' ) ) );
		}

		$result = $this->notifications->send_test( $webhook_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Test notification sent!', 'bkx-discord' ) ) );
	}

	/**
	 * AJAX: Toggle webhook status.
	 */
	public function ajax_toggle_webhook() {
		check_ajax_referer( 'bkx_discord_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-discord' ) ) );
		}

		$webhook_id = absint( $_POST['webhook_id'] ?? 0 );
		$is_active  = ! empty( $_POST['is_active'] );

		if ( ! $webhook_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid webhook ID.', 'bkx-discord' ) ) );
		}

		$this->webhooks->toggle_webhook( $webhook_id, $is_active );

		wp_send_json_success();
	}

	/**
	 * AJAX: Clear old logs.
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'bkx_discord_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-discord' ) ) );
		}

		$this->cleanup_old_logs( 30 );

		wp_send_json_success( array( 'message' => __( 'Logs cleared.', 'bkx-discord' ) ) );
	}

	/**
	 * Cleanup old logs.
	 *
	 * @param int $days Days to keep.
	 */
	public function cleanup_old_logs( $days = 90 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_discord_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}
}

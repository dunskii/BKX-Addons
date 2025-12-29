<?php
/**
 * Main Slack Integration Addon Class.
 *
 * @package BookingX\Slack
 */

namespace BookingX\Slack;

use BookingX\Slack\Services\SlackApi;
use BookingX\Slack\Services\NotificationService;
use BookingX\Slack\Services\SlashCommandHandler;
use BookingX\Slack\Services\InteractiveHandler;

defined( 'ABSPATH' ) || exit;

/**
 * SlackAddon class.
 */
class SlackAddon {

	/**
	 * Slack API instance.
	 *
	 * @var SlackApi
	 */
	private $api;

	/**
	 * Notification service instance.
	 *
	 * @var NotificationService
	 */
	private $notifications;

	/**
	 * Slash command handler instance.
	 *
	 * @var SlashCommandHandler
	 */
	private $slash_commands;

	/**
	 * Interactive handler instance.
	 *
	 * @var InteractiveHandler
	 */
	private $interactive;

	/**
	 * Initialize the addon.
	 */
	public function init() {
		$this->api            = new SlackApi();
		$this->notifications  = new NotificationService( $this->api );
		$this->slash_commands = new SlashCommandHandler( $this->api );
		$this->interactive    = new InteractiveHandler( $this->api );

		$this->register_hooks();
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
		add_action( 'wp_ajax_bkx_slack_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_slack_test_notification', array( $this, 'ajax_test_notification' ) );
		add_action( 'wp_ajax_bkx_slack_disconnect_workspace', array( $this, 'ajax_disconnect_workspace' ) );
		add_action( 'wp_ajax_bkx_slack_add_channel', array( $this, 'ajax_add_channel' ) );
		add_action( 'wp_ajax_bkx_slack_remove_channel', array( $this, 'ajax_remove_channel' ) );

		// Booking event hooks.
		add_action( 'bkx_booking_created', array( $this->notifications, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this->notifications, 'on_booking_status_changed' ), 10, 3 );
		add_action( 'transition_post_status', array( $this->notifications, 'on_post_status_transition' ), 10, 3 );

		// Settings link.
		add_filter( 'plugin_action_links_' . BKX_SLACK_BASENAME, array( $this, 'add_settings_link' ) );

		// OAuth callback.
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Slack Integration', 'bkx-slack' ),
			__( 'Slack', 'bkx-slack' ),
			'manage_options',
			'bkx-slack',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bookingx_page_bkx-slack' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-slack-admin',
			BKX_SLACK_URL . 'assets/css/admin.css',
			array(),
			BKX_SLACK_VERSION
		);

		wp_enqueue_script(
			'bkx-slack-admin',
			BKX_SLACK_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_SLACK_VERSION,
			true
		);

		wp_localize_script( 'bkx-slack-admin', 'bkxSlack', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bkx_slack_nonce' ),
			'i18n'    => array(
				'saved'        => __( 'Settings saved successfully.', 'bkx-slack' ),
				'error'        => __( 'An error occurred.', 'bkx-slack' ),
				'testSent'     => __( 'Test notification sent!', 'bkx-slack' ),
				'confirmDisconnect' => __( 'Are you sure you want to disconnect this workspace?', 'bkx-slack' ),
			),
		) );
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_SLACK_PATH . 'templates/admin/page.php';
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		// OAuth callback.
		register_rest_route( 'bkx-slack/v1', '/oauth/callback', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_oauth_callback' ),
			'permission_callback' => '__return_true',
		) );

		// Slash commands.
		register_rest_route( 'bkx-slack/v1', '/slash', array(
			'methods'             => 'POST',
			'callback'            => array( $this->slash_commands, 'handle' ),
			'permission_callback' => '__return_true',
		) );

		// Interactive components.
		register_rest_route( 'bkx-slack/v1', '/interactive', array(
			'methods'             => 'POST',
			'callback'            => array( $this->interactive, 'handle' ),
			'permission_callback' => '__return_true',
		) );

		// Events (for event subscriptions).
		register_rest_route( 'bkx-slack/v1', '/events', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_events' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Handle OAuth callback via REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_oauth_callback( $request ) {
		$code  = $request->get_param( 'code' );
		$error = $request->get_param( 'error' );

		if ( $error ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bkx-slack&error=' . urlencode( $error ) ) );
			exit;
		}

		if ( ! $code ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bkx-slack&error=missing_code' ) );
			exit;
		}

		$result = $this->api->exchange_code_for_token( $code );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bkx-slack&error=' . urlencode( $result->get_error_message() ) ) );
			exit;
		}

		// Save workspace.
		$this->save_workspace( $result );

		wp_safe_redirect( admin_url( 'admin.php?page=bkx-slack&success=connected' ) );
		exit;
	}

	/**
	 * Handle OAuth callback via admin_init.
	 */
	public function handle_oauth_callback() {
		if ( ! isset( $_GET['page'] ) || 'bkx-slack' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['code'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$result = $this->api->exchange_code_for_token( $code );

		if ( ! is_wp_error( $result ) ) {
			$this->save_workspace( $result );
			wp_safe_redirect( admin_url( 'admin.php?page=bkx-slack&success=connected' ) );
			exit;
		}
	}

	/**
	 * Save workspace from OAuth response.
	 *
	 * @param array $data OAuth response data.
	 */
	private function save_workspace( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_slack_workspaces';

		$workspace_data = array(
			'team_id'                  => $data['team']['id'],
			'team_name'                => $data['team']['name'],
			'access_token'             => $this->encrypt_token( $data['access_token'] ),
			'bot_user_id'              => $data['bot_user_id'] ?? null,
			'scope'                    => $data['scope'] ?? '',
			'incoming_webhook_url'     => $data['incoming_webhook']['url'] ?? null,
			'incoming_webhook_channel' => $data['incoming_webhook']['channel'] ?? null,
			'status'                   => 'active',
			'connected_at'             => current_time( 'mysql' ),
		);

		// Check if workspace already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE team_id = %s",
			$data['team']['id']
		) );

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $workspace_data, array( 'id' => $existing->id ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $workspace_data );
		}
	}

	/**
	 * Handle Slack events.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_events( $request ) {
		$payload = $request->get_json_params();

		// Handle URL verification.
		if ( isset( $payload['type'] ) && 'url_verification' === $payload['type'] ) {
			return new \WP_REST_Response( array( 'challenge' => $payload['challenge'] ), 200 );
		}

		// Verify request signature.
		$signature   = $request->get_header( 'X-Slack-Signature' );
		$timestamp   = $request->get_header( 'X-Slack-Request-Timestamp' );
		$body        = $request->get_body();

		if ( ! $this->api->verify_signature( $body, $timestamp, $signature ) ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid signature' ), 401 );
		}

		// Process event.
		if ( isset( $payload['event'] ) ) {
			$this->process_event( $payload['event'] );
		}

		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Process a Slack event.
	 *
	 * @param array $event Event data.
	 */
	private function process_event( $event ) {
		$type = $event['type'] ?? '';

		switch ( $type ) {
			case 'app_home_opened':
				// Could update app home with booking summary.
				break;

			case 'member_joined_channel':
				// Could send welcome message with booking info.
				break;
		}
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_slack_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-slack' ) ) );
		}

		$settings = array(
			'client_id'            => sanitize_text_field( $_POST['client_id'] ?? '' ),
			'client_secret'        => sanitize_text_field( $_POST['client_secret'] ?? '' ),
			'signing_secret'       => sanitize_text_field( $_POST['signing_secret'] ?? '' ),
			'notify_new_booking'   => ! empty( $_POST['notify_new_booking'] ),
			'notify_cancelled'     => ! empty( $_POST['notify_cancelled'] ),
			'notify_completed'     => ! empty( $_POST['notify_completed'] ),
			'notify_rescheduled'   => ! empty( $_POST['notify_rescheduled'] ),
			'enable_slash_commands' => ! empty( $_POST['enable_slash_commands'] ),
			'enable_interactive'   => ! empty( $_POST['enable_interactive'] ),
		);

		update_option( 'bkx_slack_settings', $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'bkx-slack' ) ) );
	}

	/**
	 * AJAX: Test notification.
	 */
	public function ajax_test_notification() {
		check_ajax_referer( 'bkx_slack_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-slack' ) ) );
		}

		$workspace_id = absint( $_POST['workspace_id'] ?? 0 );
		$channel_id   = sanitize_text_field( $_POST['channel_id'] ?? '' );

		$result = $this->notifications->send_test_notification( $workspace_id, $channel_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Test notification sent!', 'bkx-slack' ) ) );
	}

	/**
	 * AJAX: Disconnect workspace.
	 */
	public function ajax_disconnect_workspace() {
		check_ajax_referer( 'bkx_slack_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-slack' ) ) );
		}

		global $wpdb;

		$workspace_id = absint( $_POST['workspace_id'] ?? 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->prefix . 'bkx_slack_workspaces',
			array( 'status' => 'disconnected' ),
			array( 'id' => $workspace_id )
		);

		wp_send_json_success( array( 'message' => __( 'Workspace disconnected.', 'bkx-slack' ) ) );
	}

	/**
	 * AJAX: Add channel.
	 */
	public function ajax_add_channel() {
		check_ajax_referer( 'bkx_slack_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-slack' ) ) );
		}

		global $wpdb;

		$workspace_id = absint( $_POST['workspace_id'] ?? 0 );
		$channel_id   = sanitize_text_field( $_POST['channel_id'] ?? '' );
		$channel_name = sanitize_text_field( $_POST['channel_name'] ?? '' );
		$types        = isset( $_POST['notification_types'] ) ? array_map( 'sanitize_text_field', $_POST['notification_types'] ) : array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_slack_channels',
			array(
				'workspace_id'       => $workspace_id,
				'channel_id'         => $channel_id,
				'channel_name'       => $channel_name,
				'notification_types' => wp_json_encode( $types ),
				'enabled'            => 1,
			)
		);

		wp_send_json_success( array( 'message' => __( 'Channel added.', 'bkx-slack' ) ) );
	}

	/**
	 * AJAX: Remove channel.
	 */
	public function ajax_remove_channel() {
		check_ajax_referer( 'bkx_slack_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-slack' ) ) );
		}

		global $wpdb;

		$channel_db_id = absint( $_POST['channel_db_id'] ?? 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete(
			$wpdb->prefix . 'bkx_slack_channels',
			array( 'id' => $channel_db_id )
		);

		wp_send_json_success( array( 'message' => __( 'Channel removed.', 'bkx-slack' ) ) );
	}

	/**
	 * Add settings link to plugins page.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=bkx-slack' ),
			__( 'Settings', 'bkx-slack' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Encrypt token for storage.
	 *
	 * @param string $token Token to encrypt.
	 * @return string
	 */
	private function encrypt_token( $token ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $token );
		}

		$key    = $this->get_encryption_key();
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $token, 'AES-256-CBC', $key, 0, $iv );

		return base64_encode( $iv . $cipher );
	}

	/**
	 * Get encryption key.
	 *
	 * @return string
	 */
	private function get_encryption_key() {
		$key = defined( 'BKX_SLACK_ENCRYPTION_KEY' ) ? BKX_SLACK_ENCRYPTION_KEY : AUTH_KEY;
		return hash( 'sha256', $key, true );
	}
}

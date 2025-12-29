<?php
/**
 * Main Live Chat Addon class.
 *
 * @package BookingX\LiveChat
 * @since   1.0.0
 */

namespace BookingX\LiveChat;

use BookingX\LiveChat\Services\ChatService;
use BookingX\LiveChat\Services\OperatorService;
use BookingX\LiveChat\Services\VisitorService;
use BookingX\LiveChat\Services\NotificationService;

defined( 'ABSPATH' ) || exit;

/**
 * LiveChatAddon class.
 *
 * @since 1.0.0
 */
class LiveChatAddon {

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
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'bkx_live_chat_settings', array() );
	}

	/**
	 * Initialize the addon.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		$this->init_services();
		$this->register_hooks();
	}

	/**
	 * Initialize services.
	 *
	 * @since 1.0.0
	 */
	private function init_services() {
		$this->services['chat']         = new ChatService( $this->settings );
		$this->services['operator']     = new OperatorService( $this->settings );
		$this->services['visitor']      = new VisitorService( $this->settings );
		$this->services['notification'] = new NotificationService( $this->settings );
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks() {
		// Admin.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_item' ), 100 );

		// Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_chat_widget' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_livechat_get_chats', array( $this, 'ajax_get_chats' ) );
		add_action( 'wp_ajax_bkx_livechat_get_messages', array( $this, 'ajax_get_messages' ) );
		add_action( 'wp_ajax_bkx_livechat_send_message', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_bkx_livechat_accept_chat', array( $this, 'ajax_accept_chat' ) );
		add_action( 'wp_ajax_bkx_livechat_close_chat', array( $this, 'ajax_close_chat' ) );
		add_action( 'wp_ajax_bkx_livechat_transfer_chat', array( $this, 'ajax_transfer_chat' ) );
		add_action( 'wp_ajax_bkx_livechat_update_status', array( $this, 'ajax_update_status' ) );
		add_action( 'wp_ajax_bkx_livechat_get_visitors', array( $this, 'ajax_get_visitors' ) );
		add_action( 'wp_ajax_bkx_livechat_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_livechat_save_response', array( $this, 'ajax_save_response' ) );
		add_action( 'wp_ajax_bkx_livechat_delete_response', array( $this, 'ajax_delete_response' ) );
		add_action( 'wp_ajax_bkx_livechat_upload_file', array( $this, 'ajax_upload_file' ) );

		// No-priv AJAX for visitors.
		add_action( 'wp_ajax_nopriv_bkx_livechat_start_chat', array( $this, 'ajax_start_chat' ) );
		add_action( 'wp_ajax_nopriv_bkx_livechat_send_visitor_message', array( $this, 'ajax_send_visitor_message' ) );
		add_action( 'wp_ajax_nopriv_bkx_livechat_get_visitor_messages', array( $this, 'ajax_get_visitor_messages' ) );
		add_action( 'wp_ajax_nopriv_bkx_livechat_end_chat', array( $this, 'ajax_end_chat' ) );
		add_action( 'wp_ajax_nopriv_bkx_livechat_submit_rating', array( $this, 'ajax_submit_rating' ) );
		add_action( 'wp_ajax_nopriv_bkx_livechat_track_visitor', array( $this, 'ajax_track_visitor' ) );

		// Logged-in visitors.
		add_action( 'wp_ajax_bkx_livechat_start_chat', array( $this, 'ajax_start_chat' ) );
		add_action( 'wp_ajax_bkx_livechat_send_visitor_message', array( $this, 'ajax_send_visitor_message' ) );
		add_action( 'wp_ajax_bkx_livechat_get_visitor_messages', array( $this, 'ajax_get_visitor_messages' ) );
		add_action( 'wp_ajax_bkx_livechat_end_chat', array( $this, 'ajax_end_chat' ) );
		add_action( 'wp_ajax_bkx_livechat_submit_rating', array( $this, 'ajax_submit_rating' ) );
		add_action( 'wp_ajax_bkx_livechat_track_visitor', array( $this, 'ajax_track_visitor' ) );

		// Cron.
		add_action( 'bkx_livechat_cleanup_sessions', array( $this, 'cleanup_sessions' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		// Main menu item.
		add_menu_page(
			__( 'Live Chat', 'bkx-live-chat' ),
			__( 'Live Chat', 'bkx-live-chat' ),
			'manage_options',
			'bkx-livechat',
			array( $this, 'render_dashboard' ),
			'dashicons-format-chat',
			56
		);

		// Submenus.
		add_submenu_page(
			'bkx-livechat',
			__( 'Dashboard', 'bkx-live-chat' ),
			__( 'Dashboard', 'bkx-live-chat' ),
			'manage_options',
			'bkx-livechat',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'bkx-livechat',
			__( 'Chat History', 'bkx-live-chat' ),
			__( 'History', 'bkx-live-chat' ),
			'manage_options',
			'bkx-livechat-history',
			array( $this, 'render_history' )
		);

		add_submenu_page(
			'bkx-livechat',
			__( 'Canned Responses', 'bkx-live-chat' ),
			__( 'Responses', 'bkx-live-chat' ),
			'manage_options',
			'bkx-livechat-responses',
			array( $this, 'render_responses' )
		);

		add_submenu_page(
			'bkx-livechat',
			__( 'Operators', 'bkx-live-chat' ),
			__( 'Operators', 'bkx-live-chat' ),
			'manage_options',
			'bkx-livechat-operators',
			array( $this, 'render_operators' )
		);

		add_submenu_page(
			'bkx-livechat',
			__( 'Settings', 'bkx-live-chat' ),
			__( 'Settings', 'bkx-live-chat' ),
			'manage_options',
			'bkx-livechat-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Add admin bar item.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Admin_Bar $admin_bar Admin bar instance.
	 */
	public function add_admin_bar_item( $admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$pending_count = $this->services['chat']->get_pending_count();

		$title = '<span class="ab-icon dashicons dashicons-format-chat"></span>';
		if ( $pending_count > 0 ) {
			$title .= '<span class="bkx-livechat-badge">' . $pending_count . '</span>';
		}

		$admin_bar->add_node(
			array(
				'id'    => 'bkx-livechat',
				'title' => $title,
				'href'  => admin_url( 'admin.php?page=bkx-livechat' ),
				'meta'  => array(
					'title' => __( 'Live Chat', 'bkx-live-chat' ),
				),
			)
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on live chat pages.
		if ( strpos( $hook, 'bkx-livechat' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-livechat-admin',
			BKX_LIVECHAT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_LIVECHAT_VERSION
		);

		wp_enqueue_script(
			'bkx-livechat-admin',
			BKX_LIVECHAT_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_LIVECHAT_VERSION,
			true
		);

		wp_localize_script(
			'bkx-livechat-admin',
			'bkxLiveChatData',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'bkx_livechat_nonce' ),
				'operator_id' => get_current_user_id(),
				'poll_interval' => 3000,
				'i18n'        => array(
					'sending'        => __( 'Sending...', 'bkx-live-chat' ),
					'confirm_close'  => __( 'Are you sure you want to close this chat?', 'bkx-live-chat' ),
					'confirm_delete' => __( 'Are you sure you want to delete this?', 'bkx-live-chat' ),
					'no_messages'    => __( 'No messages yet', 'bkx-live-chat' ),
				),
			)
		);

		// Sound notification.
		wp_enqueue_script( 'bkx-livechat-notification-sound' );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_frontend_assets() {
		if ( empty( $this->settings['enabled'] ) ) {
			return;
		}

		if ( ! $this->should_show_widget() ) {
			return;
		}

		wp_enqueue_style(
			'bkx-livechat-widget',
			BKX_LIVECHAT_PLUGIN_URL . 'assets/css/widget.css',
			array(),
			BKX_LIVECHAT_VERSION
		);

		wp_enqueue_script(
			'bkx-livechat-widget',
			BKX_LIVECHAT_PLUGIN_URL . 'assets/js/widget.js',
			array( 'jquery' ),
			BKX_LIVECHAT_VERSION,
			true
		);

		wp_localize_script(
			'bkx-livechat-widget',
			'bkxLiveChatWidget',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'bkx_livechat_visitor_nonce' ),
				'session_id'      => $this->get_visitor_session_id(),
				'position'        => $this->settings['widget_position'] ?? 'bottom-right',
				'color'           => $this->settings['widget_color'] ?? '#2196f3',
				'title'           => $this->settings['widget_title'] ?? __( 'Chat with us', 'bkx-live-chat' ),
				'welcome_message' => $this->settings['welcome_message'] ?? '',
				'offline_message' => $this->settings['offline_message'] ?? '',
				'require_email'   => ! empty( $this->settings['require_email'] ),
				'require_name'    => ! empty( $this->settings['require_name'] ),
				'is_online'       => $this->services['operator']->is_any_online(),
				'poll_interval'   => 3000,
				'i18n'            => array(
					'type_message' => __( 'Type a message...', 'bkx-live-chat' ),
					'send'         => __( 'Send', 'bkx-live-chat' ),
					'connecting'   => __( 'Connecting...', 'bkx-live-chat' ),
					'chat_ended'   => __( 'Chat ended', 'bkx-live-chat' ),
				),
			)
		);
	}

	/**
	 * Render chat widget.
	 *
	 * @since 1.0.0
	 */
	public function render_chat_widget() {
		if ( empty( $this->settings['enabled'] ) ) {
			return;
		}

		if ( ! $this->should_show_widget() ) {
			return;
		}

		include BKX_LIVECHAT_PLUGIN_DIR . 'templates/frontend/widget.php';
	}

	/**
	 * Check if widget should be shown.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether to show widget.
	 */
	private function should_show_widget() {
		// Don't show in admin.
		if ( is_admin() ) {
			return false;
		}

		$current_page_id = get_the_ID();

		// Check hide on pages.
		$hide_pages = $this->settings['hide_on_pages'] ?? array();
		if ( ! empty( $hide_pages ) && in_array( $current_page_id, $hide_pages, true ) ) {
			return false;
		}

		// Check show on pages (if set, only show on these pages).
		$show_pages = $this->settings['show_on_pages'] ?? array();
		if ( ! empty( $show_pages ) && ! in_array( $current_page_id, $show_pages, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get visitor session ID.
	 *
	 * @since 1.0.0
	 *
	 * @return string Session ID.
	 */
	private function get_visitor_session_id() {
		if ( isset( $_COOKIE['bkx_livechat_session'] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE['bkx_livechat_session'] ) );
		}

		$session_id = wp_generate_uuid4();

		return $session_id;
	}

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-livechat/v1',
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_status' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * REST: Get chat status.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_REST_Response Response.
	 */
	public function rest_get_status() {
		return new \WP_REST_Response(
			array(
				'online'    => $this->services['operator']->is_any_online(),
				'operators' => $this->services['operator']->get_online_count(),
			),
			200
		);
	}

	/**
	 * Render dashboard.
	 *
	 * @since 1.0.0
	 */
	public function render_dashboard() {
		include BKX_LIVECHAT_PLUGIN_DIR . 'templates/admin/dashboard.php';
	}

	/**
	 * Render history.
	 *
	 * @since 1.0.0
	 */
	public function render_history() {
		include BKX_LIVECHAT_PLUGIN_DIR . 'templates/admin/history.php';
	}

	/**
	 * Render responses.
	 *
	 * @since 1.0.0
	 */
	public function render_responses() {
		include BKX_LIVECHAT_PLUGIN_DIR . 'templates/admin/responses.php';
	}

	/**
	 * Render operators.
	 *
	 * @since 1.0.0
	 */
	public function render_operators() {
		include BKX_LIVECHAT_PLUGIN_DIR . 'templates/admin/operators.php';
	}

	/**
	 * Render settings.
	 *
	 * @since 1.0.0
	 */
	public function render_settings() {
		include BKX_LIVECHAT_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	// =========================================================================
	// AJAX Handlers
	// =========================================================================

	/**
	 * AJAX: Get chats.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_chats() {
		check_ajax_referer( 'bkx_livechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-live-chat' ) );
		}

		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
		$chats  = $this->services['chat']->get_chats( $status );

		wp_send_json_success( $chats );
	}

	/**
	 * AJAX: Get messages.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_messages() {
		check_ajax_referer( 'bkx_livechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-live-chat' ) );
		}

		$chat_id = isset( $_GET['chat_id'] ) ? absint( $_GET['chat_id'] ) : 0;
		$after   = isset( $_GET['after'] ) ? absint( $_GET['after'] ) : 0;

		$messages = $this->services['chat']->get_messages( $chat_id, $after );

		// Mark as read.
		$this->services['chat']->mark_messages_read( $chat_id, 'visitor' );

		wp_send_json_success( $messages );
	}

	/**
	 * AJAX: Send message (operator).
	 *
	 * @since 1.0.0
	 */
	public function ajax_send_message() {
		check_ajax_referer( 'bkx_livechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-live-chat' ) );
		}

		$chat_id = isset( $_POST['chat_id'] ) ? absint( $_POST['chat_id'] ) : 0;
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( empty( $chat_id ) || empty( $message ) ) {
			wp_send_json_error( __( 'Missing required fields.', 'bkx-live-chat' ) );
		}

		$user = wp_get_current_user();

		$result = $this->services['chat']->add_message(
			$chat_id,
			'operator',
			get_current_user_id(),
			$user->display_name,
			$message
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Accept chat.
	 *
	 * @since 1.0.0
	 */
	public function ajax_accept_chat() {
		check_ajax_referer( 'bkx_livechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-live-chat' ) );
		}

		$chat_id = isset( $_POST['chat_id'] ) ? absint( $_POST['chat_id'] ) : 0;

		$result = $this->services['chat']->accept_chat( $chat_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Chat accepted.', 'bkx-live-chat' ) );
	}

	/**
	 * AJAX: Close chat.
	 *
	 * @since 1.0.0
	 */
	public function ajax_close_chat() {
		check_ajax_referer( 'bkx_livechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-live-chat' ) );
		}

		$chat_id = isset( $_POST['chat_id'] ) ? absint( $_POST['chat_id'] ) : 0;

		$result = $this->services['chat']->close_chat( $chat_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Send transcript if enabled.
		if ( ! empty( $this->settings['email_transcripts'] ) ) {
			$this->services['notification']->send_transcript( $chat_id );
		}

		wp_send_json_success( __( 'Chat closed.', 'bkx-live-chat' ) );
	}

	/**
	 * AJAX: Transfer chat.
	 *
	 * @since 1.0.0
	 */
	public function ajax_transfer_chat() {
		check_ajax_referer( 'bkx_livechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-live-chat' ) );
		}

		$chat_id     = isset( $_POST['chat_id'] ) ? absint( $_POST['chat_id'] ) : 0;
		$operator_id = isset( $_POST['operator_id'] ) ? absint( $_POST['operator_id'] ) : 0;

		$result = $this->services['chat']->transfer_chat( $chat_id, $operator_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Chat transferred.', 'bkx-live-chat' ) );
	}

	/**
	 * AJAX: Update operator status.
	 *
	 * @since 1.0.0
	 */
	public function ajax_update_status() {
		check_ajax_referer( 'bkx_livechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-live-chat' ) );
		}

		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		$result = $this->services['operator']->update_status( get_current_user_id(), $status );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Status updated.', 'bkx-live-chat' ) );
	}

	/**
	 * AJAX: Get visitors.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_visitors() {
		check_ajax_referer( 'bkx_livechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-live-chat' ) );
		}

		$visitors = $this->services['visitor']->get_active_visitors();

		wp_send_json_success( $visitors );
	}

	/**
	 * AJAX: Save settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_livechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-live-chat' ) );
		}

		$settings = array(
			'enabled'               => ! empty( $_POST['enabled'] ),
			'widget_position'       => isset( $_POST['widget_position'] ) ? sanitize_text_field( wp_unslash( $_POST['widget_position'] ) ) : 'bottom-right',
			'widget_color'          => isset( $_POST['widget_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['widget_color'] ) ) : '#2196f3',
			'widget_title'          => isset( $_POST['widget_title'] ) ? sanitize_text_field( wp_unslash( $_POST['widget_title'] ) ) : '',
			'welcome_message'       => isset( $_POST['welcome_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['welcome_message'] ) ) : '',
			'offline_message'       => isset( $_POST['offline_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['offline_message'] ) ) : '',
			'require_email'         => ! empty( $_POST['require_email'] ),
			'require_name'          => ! empty( $_POST['require_name'] ),
			'typing_indicator'      => ! empty( $_POST['typing_indicator'] ),
			'sound_notifications'   => ! empty( $_POST['sound_notifications'] ),
			'email_transcripts'     => ! empty( $_POST['email_transcripts'] ),
			'file_sharing'          => ! empty( $_POST['file_sharing'] ),
			'max_file_size'         => isset( $_POST['max_file_size'] ) ? absint( $_POST['max_file_size'] ) : 5,
			'allowed_file_types'    => isset( $_POST['allowed_file_types'] ) ? sanitize_text_field( wp_unslash( $_POST['allowed_file_types'] ) ) : '',
			'idle_timeout'          => isset( $_POST['idle_timeout'] ) ? absint( $_POST['idle_timeout'] ) : 30,
			'satisfaction_survey'   => ! empty( $_POST['satisfaction_survey'] ),
		);

		update_option( 'bkx_live_chat_settings', $settings );

		wp_send_json_success( __( 'Settings saved.', 'bkx-live-chat' ) );
	}

	/**
	 * AJAX: Save canned response.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_response() {
		check_ajax_referer( 'bkx_livechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-live-chat' ) );
		}

		global $wpdb;

		$id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$shortcut = isset( $_POST['shortcut'] ) ? sanitize_text_field( wp_unslash( $_POST['shortcut'] ) ) : '';
		$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content  = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
		$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

		if ( empty( $shortcut ) || empty( $title ) || empty( $content ) ) {
			wp_send_json_error( __( 'All fields are required.', 'bkx-live-chat' ) );
		}

		$table = $wpdb->prefix . 'bkx_livechat_responses';
		$data  = array(
			'shortcut' => $shortcut,
			'title'    => $title,
			'content'  => $content,
			'category' => $category,
		);

		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		wp_send_json_success( __( 'Response saved.', 'bkx-live-chat' ) );
	}

	/**
	 * AJAX: Delete canned response.
	 *
	 * @since 1.0.0
	 */
	public function ajax_delete_response() {
		check_ajax_referer( 'bkx_livechat_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-live-chat' ) );
		}

		global $wpdb;

		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$table = $wpdb->prefix . 'bkx_livechat_responses';

		$wpdb->delete( $table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		wp_send_json_success( __( 'Response deleted.', 'bkx-live-chat' ) );
	}

	/**
	 * AJAX: Upload file.
	 *
	 * @since 1.0.0
	 */
	public function ajax_upload_file() {
		check_ajax_referer( 'bkx_livechat_nonce', 'nonce' );

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( __( 'No file uploaded.', 'bkx-live-chat' ) );
		}

		if ( empty( $this->settings['file_sharing'] ) ) {
			wp_send_json_error( __( 'File sharing is disabled.', 'bkx-live-chat' ) );
		}

		$file = $_FILES['file'];

		// Check file size.
		$max_size = ( $this->settings['max_file_size'] ?? 5 ) * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			wp_send_json_error( __( 'File is too large.', 'bkx-live-chat' ) );
		}

		// Check file type.
		$allowed_types = explode( ',', $this->settings['allowed_file_types'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx' );
		$ext           = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed_types, true ) ) {
			wp_send_json_error( __( 'File type not allowed.', 'bkx-live-chat' ) );
		}

		// Upload file.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload = wp_handle_upload(
			$file,
			array( 'test_form' => false )
		);

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( $upload['error'] );
		}

		wp_send_json_success(
			array(
				'url'  => $upload['url'],
				'name' => $file['name'],
			)
		);
	}

	// =========================================================================
	// Visitor AJAX Handlers
	// =========================================================================

	/**
	 * AJAX: Start chat (visitor).
	 *
	 * @since 1.0.0
	 */
	public function ajax_start_chat() {
		check_ajax_referer( 'bkx_livechat_visitor_nonce', 'nonce' );

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$page_url   = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

		if ( empty( $session_id ) ) {
			wp_send_json_error( __( 'Invalid session.', 'bkx-live-chat' ) );
		}

		$result = $this->services['chat']->start_chat(
			$session_id,
			$name,
			$email,
			$message,
			$page_url
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Send message (visitor).
	 *
	 * @since 1.0.0
	 */
	public function ajax_send_visitor_message() {
		check_ajax_referer( 'bkx_livechat_visitor_nonce', 'nonce' );

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( empty( $session_id ) || empty( $message ) ) {
			wp_send_json_error( __( 'Missing required fields.', 'bkx-live-chat' ) );
		}

		$chat = $this->services['chat']->get_chat_by_session( $session_id );

		if ( ! $chat ) {
			wp_send_json_error( __( 'Chat not found.', 'bkx-live-chat' ) );
		}

		$result = $this->services['chat']->add_message(
			$chat->id,
			'visitor',
			null,
			$chat->visitor_name,
			$message
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get messages (visitor).
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_visitor_messages() {
		check_ajax_referer( 'bkx_livechat_visitor_nonce', 'nonce' );

		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
		$after      = isset( $_GET['after'] ) ? absint( $_GET['after'] ) : 0;

		$chat = $this->services['chat']->get_chat_by_session( $session_id );

		if ( ! $chat ) {
			wp_send_json_success( array( 'messages' => array(), 'status' => 'closed' ) );
		}

		$messages = $this->services['chat']->get_messages( $chat->id, $after );

		// Mark operator messages as read.
		$this->services['chat']->mark_messages_read( $chat->id, 'operator' );

		wp_send_json_success(
			array(
				'messages' => $messages,
				'status'   => $chat->status,
			)
		);
	}

	/**
	 * AJAX: End chat (visitor).
	 *
	 * @since 1.0.0
	 */
	public function ajax_end_chat() {
		check_ajax_referer( 'bkx_livechat_visitor_nonce', 'nonce' );

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

		$chat = $this->services['chat']->get_chat_by_session( $session_id );

		if ( ! $chat ) {
			wp_send_json_error( __( 'Chat not found.', 'bkx-live-chat' ) );
		}

		$this->services['chat']->close_chat( $chat->id );

		wp_send_json_success( __( 'Chat ended.', 'bkx-live-chat' ) );
	}

	/**
	 * AJAX: Submit rating.
	 *
	 * @since 1.0.0
	 */
	public function ajax_submit_rating() {
		check_ajax_referer( 'bkx_livechat_visitor_nonce', 'nonce' );

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$rating     = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
		$feedback   = isset( $_POST['feedback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['feedback'] ) ) : '';

		$chat = $this->services['chat']->get_chat_by_session( $session_id );

		if ( ! $chat ) {
			wp_send_json_error( __( 'Chat not found.', 'bkx-live-chat' ) );
		}

		$this->services['chat']->submit_rating( $chat->id, $rating, $feedback );

		wp_send_json_success( __( 'Thank you for your feedback!', 'bkx-live-chat' ) );
	}

	/**
	 * AJAX: Track visitor.
	 *
	 * @since 1.0.0
	 */
	public function ajax_track_visitor() {
		check_ajax_referer( 'bkx_livechat_visitor_nonce', 'nonce' );

		$session_id   = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$current_page = isset( $_POST['current_page'] ) ? esc_url_raw( wp_unslash( $_POST['current_page'] ) ) : '';

		$this->services['visitor']->track( $session_id, $current_page );

		wp_send_json_success();
	}

	/**
	 * Cleanup old sessions.
	 *
	 * @since 1.0.0
	 */
	public function cleanup_sessions() {
		global $wpdb;

		// Close chats that have been idle for too long.
		$idle_timeout = absint( $this->settings['idle_timeout'] ?? 30 );

		$chats_table = $wpdb->prefix . 'bkx_livechat_chats';
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE %i SET status = 'closed', ended_at = NOW() WHERE status = 'active' AND last_message_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)",
				$chats_table,
				$idle_timeout
			)
		);

		// Clean up old visitor tracking.
		$visitors_table = $wpdb->prefix . 'bkx_livechat_visitors';
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM %i WHERE last_seen < DATE_SUB(NOW(), INTERVAL 24 HOUR)",
				$visitors_table
			)
		);
	}

	/**
	 * Get service.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Service name.
	 * @return object|null Service instance.
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}
}

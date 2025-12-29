<?php
/**
 * Main WhatsApp Business Addon class.
 *
 * @package BookingX\WhatsAppBusiness
 * @since   1.0.0
 */

namespace BookingX\WhatsAppBusiness;

use BookingX\WhatsAppBusiness\Services\CloudApiProvider;
use BookingX\WhatsAppBusiness\Services\TwilioProvider;
use BookingX\WhatsAppBusiness\Services\Dialog360Provider;
use BookingX\WhatsAppBusiness\Services\MessageService;
use BookingX\WhatsAppBusiness\Services\ConversationService;
use BookingX\WhatsAppBusiness\Services\TemplateService;
use BookingX\WhatsAppBusiness\Services\WebhookHandler;

defined( 'ABSPATH' ) || exit;

/**
 * WhatsAppBusinessAddon class.
 *
 * @since 1.0.0
 */
class WhatsAppBusinessAddon {

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
	 * API Provider instance.
	 *
	 * @var object
	 */
	private $provider;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'bkx_whatsapp_business_settings', array() );
	}

	/**
	 * Initialize the addon.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		$this->init_provider();
		$this->init_services();
		$this->register_hooks();
	}

	/**
	 * Initialize API provider.
	 *
	 * @since 1.0.0
	 */
	private function init_provider() {
		$provider_type = $this->settings['api_provider'] ?? 'cloud_api';

		switch ( $provider_type ) {
			case 'twilio':
				$this->provider = new TwilioProvider( $this->settings );
				break;
			case '360dialog':
				$this->provider = new Dialog360Provider( $this->settings );
				break;
			default:
				$this->provider = new CloudApiProvider( $this->settings );
				break;
		}
	}

	/**
	 * Initialize services.
	 *
	 * @since 1.0.0
	 */
	private function init_services() {
		$this->services['messages']      = new MessageService( $this->provider, $this->settings );
		$this->services['conversations'] = new ConversationService( $this->settings );
		$this->services['templates']     = new TemplateService( $this->provider, $this->settings );
		$this->services['webhooks']      = new WebhookHandler( $this->services['messages'], $this->services['conversations'], $this->settings );
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

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_whatsapp_send_message', array( $this, 'ajax_send_message' ) );
		add_action( 'wp_ajax_bkx_whatsapp_get_conversations', array( $this, 'ajax_get_conversations' ) );
		add_action( 'wp_ajax_bkx_whatsapp_get_messages', array( $this, 'ajax_get_messages' ) );
		add_action( 'wp_ajax_bkx_whatsapp_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_whatsapp_sync_templates', array( $this, 'ajax_sync_templates' ) );
		add_action( 'wp_ajax_bkx_whatsapp_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_bkx_whatsapp_save_quick_reply', array( $this, 'ajax_save_quick_reply' ) );
		add_action( 'wp_ajax_bkx_whatsapp_delete_quick_reply', array( $this, 'ajax_delete_quick_reply' ) );

		// REST API for webhooks.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// BookingX hooks.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this, 'on_booking_status_changed' ), 10, 3 );
		add_action( 'bkx_booking_rescheduled', array( $this, 'on_booking_rescheduled' ), 10, 3 );

		// Cron.
		add_action( 'bkx_whatsapp_send_reminders', array( $this, 'send_booking_reminders' ) );
		add_action( 'bkx_whatsapp_cleanup_old_messages', array( $this, 'cleanup_old_messages' ) );

		// Booking meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_booking_meta_box' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'WhatsApp', 'bkx-whatsapp-business' ),
			__( 'WhatsApp', 'bkx-whatsapp-business' ),
			'manage_options',
			'bkx-whatsapp',
			array( $this, 'render_admin_page' )
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
		if ( strpos( $hook, 'bkx-whatsapp' ) === false && get_post_type() !== 'bkx_booking' ) {
			return;
		}

		wp_enqueue_style(
			'bkx-whatsapp-admin',
			BKX_WHATSAPP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_WHATSAPP_VERSION
		);

		wp_enqueue_script(
			'bkx-whatsapp-admin',
			BKX_WHATSAPP_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_WHATSAPP_VERSION,
			true
		);

		wp_localize_script(
			'bkx-whatsapp-admin',
			'bkxWhatsAppData',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_whatsapp_nonce' ),
				'i18n'     => array(
					'sending'      => __( 'Sending...', 'bkx-whatsapp-business' ),
					'sent'         => __( 'Sent', 'bkx-whatsapp-business' ),
					'failed'       => __( 'Failed to send', 'bkx-whatsapp-business' ),
					'confirm_delete' => __( 'Are you sure you want to delete this?', 'bkx-whatsapp-business' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'conversations';
		include BKX_WHATSAPP_PLUGIN_DIR . 'templates/admin/whatsapp-admin.php';
	}

	/**
	 * Register REST routes for webhooks.
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-whatsapp/v1',
			'/webhook',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->services['webhooks'], 'verify_webhook' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this->services['webhooks'], 'handle_webhook' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * AJAX: Send message.
	 *
	 * @since 1.0.0
	 */
	public function ajax_send_message() {
		check_ajax_referer( 'bkx_whatsapp_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-whatsapp-business' ) );
		}

		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( empty( $phone ) || empty( $message ) ) {
			wp_send_json_error( __( 'Phone number and message are required.', 'bkx-whatsapp-business' ) );
		}

		$result = $this->services['messages']->send_text_message( $phone, $message );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get conversations.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_conversations() {
		check_ajax_referer( 'bkx_whatsapp_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-whatsapp-business' ) );
		}

		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'active';
		$page   = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;

		$conversations = $this->services['conversations']->get_conversations( $status, $page );

		wp_send_json_success( $conversations );
	}

	/**
	 * AJAX: Get messages.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_messages() {
		check_ajax_referer( 'bkx_whatsapp_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-whatsapp-business' ) );
		}

		$phone = isset( $_GET['phone'] ) ? sanitize_text_field( wp_unslash( $_GET['phone'] ) ) : '';
		$page  = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;

		if ( empty( $phone ) ) {
			wp_send_json_error( __( 'Phone number is required.', 'bkx-whatsapp-business' ) );
		}

		$messages = $this->services['messages']->get_messages_for_phone( $phone, $page );

		// Mark as read.
		$this->services['conversations']->mark_as_read( $phone );

		wp_send_json_success( $messages );
	}

	/**
	 * AJAX: Save settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_whatsapp_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-whatsapp-business' ) );
		}

		$settings = array(
			'enabled'               => ! empty( $_POST['enabled'] ),
			'api_provider'          => isset( $_POST['api_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['api_provider'] ) ) : 'cloud_api',
			'phone_number_id'       => isset( $_POST['phone_number_id'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number_id'] ) ) : '',
			'business_account_id'   => isset( $_POST['business_account_id'] ) ? sanitize_text_field( wp_unslash( $_POST['business_account_id'] ) ) : '',
			'access_token'          => isset( $_POST['access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : '',
			'twilio_account_sid'    => isset( $_POST['twilio_account_sid'] ) ? sanitize_text_field( wp_unslash( $_POST['twilio_account_sid'] ) ) : '',
			'twilio_auth_token'     => isset( $_POST['twilio_auth_token'] ) ? sanitize_text_field( wp_unslash( $_POST['twilio_auth_token'] ) ) : '',
			'twilio_phone_number'   => isset( $_POST['twilio_phone_number'] ) ? sanitize_text_field( wp_unslash( $_POST['twilio_phone_number'] ) ) : '',
			'dialog360_api_key'     => isset( $_POST['dialog360_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['dialog360_api_key'] ) ) : '',
			'webhook_verify_token'  => isset( $_POST['webhook_verify_token'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_verify_token'] ) ) : $this->settings['webhook_verify_token'],
			'send_booking_confirmation' => ! empty( $_POST['send_booking_confirmation'] ),
			'send_booking_reminder' => ! empty( $_POST['send_booking_reminder'] ),
			'reminder_hours'        => isset( $_POST['reminder_hours'] ) ? absint( $_POST['reminder_hours'] ) : 24,
			'send_booking_cancelled' => ! empty( $_POST['send_booking_cancelled'] ),
			'send_booking_rescheduled' => ! empty( $_POST['send_booking_rescheduled'] ),
			'enable_two_way_chat'   => ! empty( $_POST['enable_two_way_chat'] ),
			'auto_reply_enabled'    => ! empty( $_POST['auto_reply_enabled'] ),
			'auto_reply_message'    => isset( $_POST['auto_reply_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['auto_reply_message'] ) ) : '',
			'business_hours_start'  => isset( $_POST['business_hours_start'] ) ? sanitize_text_field( wp_unslash( $_POST['business_hours_start'] ) ) : '09:00',
			'business_hours_end'    => isset( $_POST['business_hours_end'] ) ? sanitize_text_field( wp_unslash( $_POST['business_hours_end'] ) ) : '18:00',
			'outside_hours_message' => isset( $_POST['outside_hours_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['outside_hours_message'] ) ) : '',
			'confirmation_template' => isset( $_POST['confirmation_template'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmation_template'] ) ) : '',
			'reminder_template'     => isset( $_POST['reminder_template'] ) ? sanitize_text_field( wp_unslash( $_POST['reminder_template'] ) ) : '',
			'cancelled_template'    => isset( $_POST['cancelled_template'] ) ? sanitize_text_field( wp_unslash( $_POST['cancelled_template'] ) ) : '',
			'rescheduled_template'  => isset( $_POST['rescheduled_template'] ) ? sanitize_text_field( wp_unslash( $_POST['rescheduled_template'] ) ) : '',
		);

		update_option( 'bkx_whatsapp_business_settings', $settings );

		wp_send_json_success( __( 'Settings saved.', 'bkx-whatsapp-business' ) );
	}

	/**
	 * AJAX: Sync templates.
	 *
	 * @since 1.0.0
	 */
	public function ajax_sync_templates() {
		check_ajax_referer( 'bkx_whatsapp_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-whatsapp-business' ) );
		}

		$result = $this->services['templates']->sync_templates();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Templates synced successfully.', 'bkx-whatsapp-business' ),
				'templates' => $result,
			)
		);
	}

	/**
	 * AJAX: Test connection.
	 *
	 * @since 1.0.0
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'bkx_whatsapp_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-whatsapp-business' ) );
		}

		$result = $this->provider->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Connection successful!', 'bkx-whatsapp-business' ) );
	}

	/**
	 * AJAX: Save quick reply.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_quick_reply() {
		check_ajax_referer( 'bkx_whatsapp_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-whatsapp-business' ) );
		}

		global $wpdb;

		$id       = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$shortcut = isset( $_POST['shortcut'] ) ? sanitize_text_field( wp_unslash( $_POST['shortcut'] ) ) : '';
		$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content  = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
		$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

		if ( empty( $shortcut ) || empty( $title ) || empty( $content ) ) {
			wp_send_json_error( __( 'All fields are required.', 'bkx-whatsapp-business' ) );
		}

		$table = $wpdb->prefix . 'bkx_whatsapp_quick_replies';
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

		wp_send_json_success( __( 'Quick reply saved.', 'bkx-whatsapp-business' ) );
	}

	/**
	 * AJAX: Delete quick reply.
	 *
	 * @since 1.0.0
	 */
	public function ajax_delete_quick_reply() {
		check_ajax_referer( 'bkx_whatsapp_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-whatsapp-business' ) );
		}

		global $wpdb;

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( __( 'Invalid ID.', 'bkx-whatsapp-business' ) );
		}

		$table = $wpdb->prefix . 'bkx_whatsapp_quick_replies';
		$wpdb->delete( $table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		wp_send_json_success( __( 'Quick reply deleted.', 'bkx-whatsapp-business' ) );
	}

	/**
	 * Handle booking created.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		if ( empty( $this->settings['enabled'] ) || empty( $this->settings['send_booking_confirmation'] ) ) {
			return;
		}

		$phone = get_post_meta( $booking_id, 'customer_phone', true );

		if ( empty( $phone ) ) {
			return;
		}

		$this->services['messages']->send_booking_confirmation( $booking_id, $phone );
	}

	/**
	 * Handle booking status changed.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 */
	public function on_booking_status_changed( $booking_id, $new_status, $old_status ) {
		if ( empty( $this->settings['enabled'] ) ) {
			return;
		}

		$phone = get_post_meta( $booking_id, 'customer_phone', true );

		if ( empty( $phone ) ) {
			return;
		}

		if ( 'bkx-cancelled' === $new_status && ! empty( $this->settings['send_booking_cancelled'] ) ) {
			$this->services['messages']->send_booking_cancelled( $booking_id, $phone );
		}
	}

	/**
	 * Handle booking rescheduled.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_date   New date.
	 * @param string $old_date   Old date.
	 */
	public function on_booking_rescheduled( $booking_id, $new_date, $old_date ) {
		if ( empty( $this->settings['enabled'] ) || empty( $this->settings['send_booking_rescheduled'] ) ) {
			return;
		}

		$phone = get_post_meta( $booking_id, 'customer_phone', true );

		if ( empty( $phone ) ) {
			return;
		}

		$this->services['messages']->send_booking_rescheduled( $booking_id, $phone, $new_date, $old_date );
	}

	/**
	 * Send booking reminders.
	 *
	 * @since 1.0.0
	 */
	public function send_booking_reminders() {
		if ( empty( $this->settings['enabled'] ) || empty( $this->settings['send_booking_reminder'] ) ) {
			return;
		}

		$this->services['messages']->send_pending_reminders();
	}

	/**
	 * Cleanup old messages.
	 *
	 * @since 1.0.0
	 */
	public function cleanup_old_messages() {
		global $wpdb;

		// Delete messages older than 90 days.
		$table = $wpdb->prefix . 'bkx_whatsapp_messages';
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM %i WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)",
				$table
			)
		);
	}

	/**
	 * Add booking meta box.
	 *
	 * @since 1.0.0
	 */
	public function add_booking_meta_box() {
		add_meta_box(
			'bkx_whatsapp_messages',
			__( 'WhatsApp Messages', 'bkx-whatsapp-business' ),
			array( $this, 'render_booking_meta_box' ),
			'bkx_booking',
			'side',
			'default'
		);
	}

	/**
	 * Render booking meta box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_booking_meta_box( $post ) {
		$phone = get_post_meta( $post->ID, 'customer_phone', true );

		if ( empty( $phone ) ) {
			echo '<p>' . esc_html__( 'No phone number available.', 'bkx-whatsapp-business' ) . '</p>';
			return;
		}

		$messages = $this->services['messages']->get_messages_for_booking( $post->ID, 5 );

		include BKX_WHATSAPP_PLUGIN_DIR . 'templates/admin/meta-box.php';
	}

	/**
	 * Get service.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Service name.
	 * @return object|null Service instance or null.
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Get provider.
	 *
	 * @since 1.0.0
	 *
	 * @return object Provider instance.
	 */
	public function get_provider() {
		return $this->provider;
	}
}

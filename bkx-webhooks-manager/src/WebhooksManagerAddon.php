<?php
/**
 * Main Webhooks Manager Addon class.
 *
 * @package BookingX\WebhooksManager
 */

namespace BookingX\WebhooksManager;

use BookingX\WebhooksManager\Services\WebhookManager;
use BookingX\WebhooksManager\Services\DeliveryService;
use BookingX\WebhooksManager\Services\EventDispatcher;
use BookingX\WebhooksManager\Services\SignatureService;

defined( 'ABSPATH' ) || exit;

/**
 * WebhooksManagerAddon class.
 */
class WebhooksManagerAddon {

	/**
	 * Single instance.
	 *
	 * @var WebhooksManagerAddon
	 */
	private static $instance = null;

	/**
	 * Services.
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
	 * @return WebhooksManagerAddon
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
		$this->settings = get_option( 'bkx_webhooks_manager_settings', array() );
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['webhook_manager']  = new WebhookManager();
		$this->services['delivery_service'] = new DeliveryService();
		$this->services['event_dispatcher'] = new EventDispatcher();
		$this->services['signature_service'] = new SignatureService();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_webhook_save', array( $this, 'ajax_save_webhook' ) );
		add_action( 'wp_ajax_bkx_webhook_delete', array( $this, 'ajax_delete_webhook' ) );
		add_action( 'wp_ajax_bkx_webhook_test', array( $this, 'ajax_test_webhook' ) );
		add_action( 'wp_ajax_bkx_webhook_toggle', array( $this, 'ajax_toggle_webhook' ) );
		add_action( 'wp_ajax_bkx_webhook_retry', array( $this, 'ajax_retry_delivery' ) );
		add_action( 'wp_ajax_bkx_webhook_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_webhook_clear_logs', array( $this, 'ajax_clear_logs' ) );

		// Cron handlers.
		add_action( 'bkx_webhooks_cleanup', array( $this, 'cleanup_old_data' ) );
		add_action( 'bkx_webhooks_process_retries', array( $this, 'process_retries' ) );

		// BookingX event hooks.
		if ( ! empty( $this->settings['enabled'] ) ) {
			$this->register_event_listeners();
		}

		// BookingX integration.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_webhooks', array( $this, 'render_settings_tab' ) );
	}

	/**
	 * Register event listeners for BookingX events.
	 */
	private function register_event_listeners() {
		$events = $this->get_available_events();

		foreach ( $events as $event_key => $event_config ) {
			if ( ! empty( $event_config['hook'] ) ) {
				add_action(
					$event_config['hook'],
					function ( ...$args ) use ( $event_key ) {
						$this->trigger_event( $event_key, $args );
					},
					10,
					$event_config['args'] ?? 1
				);
			}
		}
	}

	/**
	 * Trigger webhook event.
	 *
	 * @param string $event_type Event type.
	 * @param array  $args       Event arguments.
	 */
	public function trigger_event( $event_type, $args ) {
		$this->services['event_dispatcher']->dispatch( $event_type, $args );
	}

	/**
	 * Get available events.
	 *
	 * @return array
	 */
	public function get_available_events() {
		$events = array(
			// Booking events.
			'booking.created'     => array(
				'label' => __( 'Booking Created', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_booking_created',
				'args'  => 2,
				'group' => 'booking',
			),
			'booking.updated'     => array(
				'label' => __( 'Booking Updated', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_booking_updated',
				'args'  => 2,
				'group' => 'booking',
			),
			'booking.confirmed'   => array(
				'label' => __( 'Booking Confirmed', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_booking_confirmed',
				'args'  => 2,
				'group' => 'booking',
			),
			'booking.cancelled'   => array(
				'label' => __( 'Booking Cancelled', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_booking_cancelled',
				'args'  => 2,
				'group' => 'booking',
			),
			'booking.completed'   => array(
				'label' => __( 'Booking Completed', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_booking_completed',
				'args'  => 2,
				'group' => 'booking',
			),
			'booking.rescheduled' => array(
				'label' => __( 'Booking Rescheduled', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_booking_rescheduled',
				'args'  => 3,
				'group' => 'booking',
			),
			'booking.reminder'    => array(
				'label' => __( 'Booking Reminder Sent', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_booking_reminder_sent',
				'args'  => 2,
				'group' => 'booking',
			),

			// Payment events.
			'payment.pending'     => array(
				'label' => __( 'Payment Pending', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_payment_pending',
				'args'  => 2,
				'group' => 'payment',
			),
			'payment.completed'   => array(
				'label' => __( 'Payment Completed', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_payment_completed',
				'args'  => 2,
				'group' => 'payment',
			),
			'payment.failed'      => array(
				'label' => __( 'Payment Failed', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_payment_failed',
				'args'  => 2,
				'group' => 'payment',
			),
			'payment.refunded'    => array(
				'label' => __( 'Payment Refunded', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_payment_refunded',
				'args'  => 2,
				'group' => 'payment',
			),

			// Customer events.
			'customer.created'    => array(
				'label' => __( 'Customer Created', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_customer_created',
				'args'  => 2,
				'group' => 'customer',
			),
			'customer.updated'    => array(
				'label' => __( 'Customer Updated', 'bkx-webhooks-manager' ),
				'hook'  => 'bkx_customer_updated',
				'args'  => 2,
				'group' => 'customer',
			),

			// Service events.
			'service.created'     => array(
				'label' => __( 'Service Created', 'bkx-webhooks-manager' ),
				'hook'  => 'save_post_bkx_base',
				'args'  => 3,
				'group' => 'service',
			),
			'service.updated'     => array(
				'label' => __( 'Service Updated', 'bkx-webhooks-manager' ),
				'hook'  => 'post_updated',
				'args'  => 3,
				'group' => 'service',
			),

			// Staff events.
			'staff.created'       => array(
				'label' => __( 'Staff Created', 'bkx-webhooks-manager' ),
				'hook'  => 'save_post_bkx_seat',
				'args'  => 3,
				'group' => 'staff',
			),
			'staff.updated'       => array(
				'label' => __( 'Staff Updated', 'bkx-webhooks-manager' ),
				'hook'  => 'post_updated',
				'args'  => 3,
				'group' => 'staff',
			),
		);

		return apply_filters( 'bkx_webhooks_available_events', $events );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Webhooks', 'bkx-webhooks-manager' ),
			__( 'Webhooks', 'bkx-webhooks-manager' ),
			'manage_options',
			'bkx-webhooks',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bkx_booking_page_bkx-webhooks' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-webhooks-admin',
			BKX_WEBHOOKS_URL . 'assets/css/admin.css',
			array(),
			BKX_WEBHOOKS_VERSION
		);

		wp_enqueue_script(
			'bkx-webhooks-admin',
			BKX_WEBHOOKS_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			BKX_WEBHOOKS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-webhooks-admin',
			'bkxWebhooks',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_webhooks_nonce' ),
				'events'  => $this->get_available_events(),
				'i18n'    => array(
					'saving'        => __( 'Saving...', 'bkx-webhooks-manager' ),
					'saved'         => __( 'Saved', 'bkx-webhooks-manager' ),
					'testing'       => __( 'Testing...', 'bkx-webhooks-manager' ),
					'error'         => __( 'An error occurred', 'bkx-webhooks-manager' ),
					'confirmDelete' => __( 'Are you sure you want to delete this webhook?', 'bkx-webhooks-manager' ),
					'retrying'      => __( 'Retrying...', 'bkx-webhooks-manager' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'webhooks';

		include BKX_WEBHOOKS_PATH . 'templates/admin/page.php';
	}

	/**
	 * AJAX: Save webhook.
	 */
	public function ajax_save_webhook() {
		check_ajax_referer( 'bkx_webhooks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-webhooks-manager' ) ) );
		}

		$webhook_id = absint( $_POST['webhook_id'] ?? 0 );
		$data       = array(
			'name'             => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'url'              => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'events'           => wp_unslash( $_POST['events'] ?? '[]' ),
			'http_method'      => sanitize_text_field( wp_unslash( $_POST['http_method'] ?? 'POST' ) ),
			'content_type'     => sanitize_text_field( wp_unslash( $_POST['content_type'] ?? 'application/json' ) ),
			'headers'          => wp_unslash( $_POST['headers'] ?? '{}' ),
			'timeout'          => absint( $_POST['timeout'] ?? 30 ),
			'retry_count'      => absint( $_POST['retry_count'] ?? 3 ),
			'retry_delay'      => absint( $_POST['retry_delay'] ?? 60 ),
			'verify_ssl'       => ! empty( $_POST['verify_ssl'] ),
			'conditions'       => wp_unslash( $_POST['conditions'] ?? '{}' ),
			'status'           => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
		);

		$result = $this->services['webhook_manager']->save( $webhook_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'    => __( 'Webhook saved successfully', 'bkx-webhooks-manager' ),
			'webhook_id' => $result,
		) );
	}

	/**
	 * AJAX: Delete webhook.
	 */
	public function ajax_delete_webhook() {
		check_ajax_referer( 'bkx_webhooks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-webhooks-manager' ) ) );
		}

		$webhook_id = absint( $_POST['webhook_id'] ?? 0 );

		if ( ! $webhook_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid webhook ID', 'bkx-webhooks-manager' ) ) );
		}

		$result = $this->services['webhook_manager']->delete( $webhook_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete webhook', 'bkx-webhooks-manager' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Webhook deleted', 'bkx-webhooks-manager' ) ) );
	}

	/**
	 * AJAX: Test webhook.
	 */
	public function ajax_test_webhook() {
		check_ajax_referer( 'bkx_webhooks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-webhooks-manager' ) ) );
		}

		$webhook_id = absint( $_POST['webhook_id'] ?? 0 );

		$result = $this->services['delivery_service']->test( $webhook_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Toggle webhook status.
	 */
	public function ajax_toggle_webhook() {
		check_ajax_referer( 'bkx_webhooks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-webhooks-manager' ) ) );
		}

		$webhook_id = absint( $_POST['webhook_id'] ?? 0 );
		$status     = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) );

		$result = $this->services['webhook_manager']->update_status( $webhook_id, $status );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update status', 'bkx-webhooks-manager' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Status updated', 'bkx-webhooks-manager' ) ) );
	}

	/**
	 * AJAX: Retry delivery.
	 */
	public function ajax_retry_delivery() {
		check_ajax_referer( 'bkx_webhooks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-webhooks-manager' ) ) );
		}

		$delivery_id = absint( $_POST['delivery_id'] ?? 0 );

		$result = $this->services['delivery_service']->retry( $delivery_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_webhooks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-webhooks-manager' ) ) );
		}

		$settings = array(
			'enabled'                    => ! empty( $_POST['enabled'] ),
			'async_delivery'             => ! empty( $_POST['async_delivery'] ),
			'max_retries'                => absint( $_POST['max_retries'] ?? 3 ),
			'retry_delay'                => absint( $_POST['retry_delay'] ?? 60 ),
			'default_timeout'            => absint( $_POST['default_timeout'] ?? 30 ),
			'log_retention_days'         => absint( $_POST['log_retention_days'] ?? 30 ),
			'signature_algorithm'        => sanitize_text_field( wp_unslash( $_POST['signature_algorithm'] ?? 'sha256' ) ),
			'include_timestamp'          => ! empty( $_POST['include_timestamp'] ),
			'notify_on_failure'          => ! empty( $_POST['notify_on_failure'] ),
			'failure_threshold'          => absint( $_POST['failure_threshold'] ?? 5 ),
			'failure_notification_email' => sanitize_email( wp_unslash( $_POST['failure_notification_email'] ?? '' ) ),
		);

		update_option( 'bkx_webhooks_manager_settings', $settings );
		$this->settings = $settings;

		wp_send_json_success( array( 'message' => __( 'Settings saved', 'bkx-webhooks-manager' ) ) );
	}

	/**
	 * AJAX: Clear logs.
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'bkx_webhooks_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-webhooks-manager' ) ) );
		}

		$this->services['delivery_service']->clear_all_logs();

		wp_send_json_success( array( 'message' => __( 'Logs cleared', 'bkx-webhooks-manager' ) ) );
	}

	/**
	 * Cleanup old data.
	 */
	public function cleanup_old_data() {
		$days = $this->settings['log_retention_days'] ?? 30;
		$this->services['delivery_service']->cleanup( $days );
	}

	/**
	 * Process pending retries.
	 */
	public function process_retries() {
		$this->services['delivery_service']->process_pending_retries();
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['webhooks'] = __( 'Webhooks', 'bkx-webhooks-manager' );
		return $tabs;
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab() {
		include BKX_WEBHOOKS_PATH . 'templates/admin/settings-tab.php';
	}

	/**
	 * Get service.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Get setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return $this->settings[ $key ] ?? $default;
	}
}

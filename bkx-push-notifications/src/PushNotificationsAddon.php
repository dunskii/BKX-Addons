<?php
/**
 * Main Push Notifications Addon Class.
 *
 * @package BookingX\PushNotifications
 */

namespace BookingX\PushNotifications;

use BookingX\PushNotifications\Services\SubscriptionService;
use BookingX\PushNotifications\Services\PushService;
use BookingX\PushNotifications\Services\TemplateService;

defined( 'ABSPATH' ) || exit;

/**
 * PushNotificationsAddon class.
 */
class PushNotificationsAddon {

	/**
	 * Subscription service.
	 *
	 * @var SubscriptionService
	 */
	private $subscription_service;

	/**
	 * Push service.
	 *
	 * @var PushService
	 */
	private $push_service;

	/**
	 * Template service.
	 *
	 * @var TemplateService
	 */
	private $template_service;

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
		$this->subscription_service = new SubscriptionService();
		$this->template_service     = new TemplateService();
		$this->push_service         = new PushService( $this->subscription_service, $this->template_service );
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Frontend assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Service worker.
		add_action( 'init', array( $this, 'register_service_worker_route' ) );

		// AJAX handlers - Frontend.
		add_action( 'wp_ajax_bkx_push_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_bkx_push_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_bkx_push_unsubscribe', array( $this, 'ajax_unsubscribe' ) );
		add_action( 'wp_ajax_nopriv_bkx_push_unsubscribe', array( $this, 'ajax_unsubscribe' ) );

		// AJAX handlers - Admin.
		add_action( 'wp_ajax_bkx_push_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_push_save_template', array( $this, 'ajax_save_template' ) );
		add_action( 'wp_ajax_bkx_push_delete_template', array( $this, 'ajax_delete_template' ) );
		add_action( 'wp_ajax_bkx_push_send_test', array( $this, 'ajax_send_test' ) );
		add_action( 'wp_ajax_bkx_push_get_stats', array( $this, 'ajax_get_stats' ) );

		// Booking lifecycle hooks.
		add_action( 'bkx_booking_confirmed', array( $this, 'on_booking_confirmed' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this, 'on_booking_cancelled' ), 10, 2 );
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_rescheduled', array( $this, 'on_booking_rescheduled' ), 10, 2 );

		// Cron for reminders.
		add_action( 'bkx_push_send_reminders', array( $this, 'send_scheduled_reminders' ) );

		if ( ! wp_next_scheduled( 'bkx_push_send_reminders' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_push_send_reminders' );
		}

		// Settings link.
		add_filter( 'plugin_action_links_' . BKX_PUSH_BASENAME, array( $this, 'add_settings_link' ) );

		// REST API for push notification delivery tracking.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Push Notifications', 'bkx-push-notifications' ),
			__( 'Push Notifications', 'bkx-push-notifications' ),
			'manage_options',
			'bkx-push-notifications',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-push' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-push-admin',
			BKX_PUSH_URL . 'assets/css/admin.css',
			array(),
			BKX_PUSH_VERSION
		);

		wp_enqueue_script(
			'bkx-push-admin',
			BKX_PUSH_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_PUSH_VERSION,
			true
		);

		wp_localize_script(
			'bkx-push-admin',
			'bkxPush',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_push_admin' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Are you sure you want to delete this template?', 'bkx-push-notifications' ),
					'testSent'      => __( 'Test notification sent!', 'bkx-push-notifications' ),
					'saved'         => __( 'Settings saved!', 'bkx-push-notifications' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		$settings = get_option( 'bkx_push_settings', array() );

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		wp_enqueue_script(
			'bkx-push-frontend',
			BKX_PUSH_URL . 'assets/js/push.js',
			array(),
			BKX_PUSH_VERSION,
			true
		);

		wp_localize_script(
			'bkx-push-frontend',
			'bkxPushConfig',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'bkx_push_subscribe' ),
				'vapidPublicKey'   => $settings['vapid_public_key'] ?? '',
				'serviceWorkerUrl' => home_url( '/bkx-push-sw.js' ),
				'promptDelay'      => $settings['prompt_delay'] ?? 5000,
				'promptMessage'    => $settings['prompt_message'] ?? __( 'Get notified about your bookings!', 'bkx-push-notifications' ),
			)
		);
	}

	/**
	 * Register service worker route.
	 */
	public function register_service_worker_route() {
		add_rewrite_rule( 'bkx-push-sw\.js$', 'index.php?bkx_push_sw=1', 'top' );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve_service_worker' ) );
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'bkx_push_sw';
		return $vars;
	}

	/**
	 * Serve service worker.
	 */
	public function serve_service_worker() {
		if ( ! get_query_var( 'bkx_push_sw' ) ) {
			return;
		}

		header( 'Content-Type: application/javascript' );
		header( 'Service-Worker-Allowed: /' );

		include BKX_PUSH_PATH . 'assets/js/service-worker.js';
		exit;
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';

		include BKX_PUSH_PATH . 'templates/admin/page.php';
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-push/v1',
			'/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'track_notification' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Track notification event.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function track_notification( $request ) {
		$log_id = $request->get_param( 'log_id' );
		$event  = $request->get_param( 'event' );

		if ( ! $log_id || ! $event ) {
			return new \WP_REST_Response( array( 'error' => 'Missing parameters' ), 400 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_push_logs';

		$column = '';
		if ( 'delivered' === $event ) {
			$column = 'delivered_at';
		} elseif ( 'clicked' === $event ) {
			$column = 'clicked_at';
		}

		if ( $column ) {
			$wpdb->update( // phpcs:ignore
				$table,
				array( $column => current_time( 'mysql' ) ),
				array( 'id' => $log_id )
			);
		}

		return new \WP_REST_Response( array( 'success' => true ) );
	}

	/**
	 * AJAX: Subscribe.
	 */
	public function ajax_subscribe() {
		check_ajax_referer( 'bkx_push_subscribe', 'nonce' );

		$endpoint = isset( $_POST['endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['endpoint'] ) ) : '';
		$p256dh   = isset( $_POST['p256dh'] ) ? sanitize_text_field( wp_unslash( $_POST['p256dh'] ) ) : '';
		$auth     = isset( $_POST['auth'] ) ? sanitize_text_field( wp_unslash( $_POST['auth'] ) ) : '';

		if ( ! $endpoint || ! $p256dh || ! $auth ) {
			wp_send_json_error( array( 'message' => __( 'Invalid subscription data.', 'bkx-push-notifications' ) ) );
		}

		$subscription_id = $this->subscription_service->subscribe(
			array(
				'endpoint'   => $endpoint,
				'p256dh'     => $p256dh,
				'auth'       => $auth,
				'user_id'    => get_current_user_id(),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			)
		);

		if ( ! $subscription_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save subscription.', 'bkx-push-notifications' ) ) );
		}

		wp_send_json_success( array( 'subscription_id' => $subscription_id ) );
	}

	/**
	 * AJAX: Unsubscribe.
	 */
	public function ajax_unsubscribe() {
		check_ajax_referer( 'bkx_push_subscribe', 'nonce' );

		$endpoint = isset( $_POST['endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['endpoint'] ) ) : '';

		if ( ! $endpoint ) {
			wp_send_json_error( array( 'message' => __( 'Invalid endpoint.', 'bkx-push-notifications' ) ) );
		}

		$result = $this->subscription_service->unsubscribe( $endpoint );

		wp_send_json_success( array( 'unsubscribed' => $result ) );
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_push_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-push-notifications' ) ) );
		}

		$settings = get_option( 'bkx_push_settings', array() );

		$settings['enabled']        = isset( $_POST['enabled'] ) ? 1 : 0;
		$settings['prompt_delay']   = isset( $_POST['prompt_delay'] ) ? absint( $_POST['prompt_delay'] ) : 5000;
		$settings['prompt_message'] = isset( $_POST['prompt_message'] ) ? sanitize_text_field( wp_unslash( $_POST['prompt_message'] ) ) : '';
		$settings['icon']           = isset( $_POST['icon'] ) ? esc_url_raw( wp_unslash( $_POST['icon'] ) ) : '';
		$settings['badge']          = isset( $_POST['badge'] ) ? esc_url_raw( wp_unslash( $_POST['badge'] ) ) : '';
		$settings['reminder_hours'] = isset( $_POST['reminder_hours'] ) ? absint( $_POST['reminder_hours'] ) : 24;

		update_option( 'bkx_push_settings', $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'bkx-push-notifications' ) ) );
	}

	/**
	 * AJAX: Save template.
	 */
	public function ajax_save_template() {
		check_ajax_referer( 'bkx_push_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-push-notifications' ) ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

		$data = array(
			'name'            => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'slug'            => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '',
			'trigger_event'   => isset( $_POST['trigger_event'] ) ? sanitize_text_field( wp_unslash( $_POST['trigger_event'] ) ) : '',
			'title'           => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'body'            => isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '',
			'icon'            => isset( $_POST['icon'] ) ? esc_url_raw( wp_unslash( $_POST['icon'] ) ) : '',
			'url'             => isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '',
			'target_audience' => isset( $_POST['target_audience'] ) ? sanitize_text_field( wp_unslash( $_POST['target_audience'] ) ) : 'customer',
			'status'          => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active',
		);

		$result = $this->template_service->save_template( $template_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'     => __( 'Template saved.', 'bkx-push-notifications' ),
				'template_id' => $result,
			)
		);
	}

	/**
	 * AJAX: Delete template.
	 */
	public function ajax_delete_template() {
		check_ajax_referer( 'bkx_push_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-push-notifications' ) ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

		if ( ! $template_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template ID.', 'bkx-push-notifications' ) ) );
		}

		$this->template_service->delete_template( $template_id );

		wp_send_json_success( array( 'message' => __( 'Template deleted.', 'bkx-push-notifications' ) ) );
	}

	/**
	 * AJAX: Send test notification.
	 */
	public function ajax_send_test() {
		check_ajax_referer( 'bkx_push_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-push-notifications' ) ) );
		}

		$user_id = get_current_user_id();
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : __( 'Test Notification', 'bkx-push-notifications' );
		$body    = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : __( 'This is a test push notification from BookingX.', 'bkx-push-notifications' );

		$result = $this->push_service->send_to_user(
			$user_id,
			array(
				'title' => $title,
				'body'  => $body,
			)
		);

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'No active subscriptions found. Subscribe first by clicking Allow on the notification prompt.', 'bkx-push-notifications' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Test notification sent!', 'bkx-push-notifications' ) ) );
	}

	/**
	 * AJAX: Get stats.
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'bkx_push_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-push-notifications' ) ) );
		}

		global $wpdb;

		$subscriptions_table = $wpdb->prefix . 'bkx_push_subscriptions';
		$logs_table          = $wpdb->prefix . 'bkx_push_logs';

		$stats = array(
			'total_subscriptions' => $wpdb->get_var( "SELECT COUNT(*) FROM {$subscriptions_table} WHERE is_active = 1" ), // phpcs:ignore
			'total_sent'          => $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE status = 'sent'" ), // phpcs:ignore
			'total_delivered'     => $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE delivered_at IS NOT NULL" ), // phpcs:ignore
			'total_clicked'       => $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE clicked_at IS NOT NULL" ), // phpcs:ignore
		);

		wp_send_json_success( $stats );
	}

	/**
	 * On booking confirmed.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_confirmed( $booking_id, $booking_data ) {
		$this->push_service->send_booking_notification( 'bkx_booking_confirmed', $booking_id );
	}

	/**
	 * On booking cancelled.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_cancelled( $booking_id, $booking_data ) {
		$this->push_service->send_booking_notification( 'bkx_booking_cancelled', $booking_id );
	}

	/**
	 * On booking created.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		$this->push_service->send_booking_notification( 'bkx_booking_created', $booking_id );
	}

	/**
	 * On booking rescheduled.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_rescheduled( $booking_id, $booking_data ) {
		$this->push_service->send_booking_notification( 'bkx_booking_rescheduled', $booking_id );
	}

	/**
	 * Send scheduled reminders.
	 */
	public function send_scheduled_reminders() {
		$this->push_service->send_reminders();
	}

	/**
	 * Add settings link.
	 *
	 * @param array $links Plugin links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=bkx-push-notifications' ) . '">' . __( 'Settings', 'bkx-push-notifications' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}

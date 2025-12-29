<?php
/**
 * Main Facebook Booking Addon Class.
 *
 * @package BookingX\FacebookBooking
 */

namespace BookingX\FacebookBooking;

use BookingX\FacebookBooking\Services\FacebookApi;
use BookingX\FacebookBooking\Services\WebhookHandler;
use BookingX\FacebookBooking\Services\BookingSync;
use BookingX\FacebookBooking\Services\PageManager;

defined( 'ABSPATH' ) || exit;

/**
 * FacebookBookingAddon class.
 */
class FacebookBookingAddon {

	/**
	 * Facebook API handler.
	 *
	 * @var FacebookApi
	 */
	private $facebook_api;

	/**
	 * Webhook handler.
	 *
	 * @var WebhookHandler
	 */
	private $webhook_handler;

	/**
	 * Booking sync.
	 *
	 * @var BookingSync
	 */
	private $booking_sync;

	/**
	 * Page manager.
	 *
	 * @var PageManager
	 */
	private $page_manager;

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
		$this->facebook_api    = new FacebookApi();
		$this->page_manager    = new PageManager( $this->facebook_api );
		$this->booking_sync    = new BookingSync( $this->facebook_api );
		$this->webhook_handler = new WebhookHandler( $this->booking_sync );
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
		add_action( 'wp_ajax_bkx_fb_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_fb_connect_page', array( $this, 'ajax_connect_page' ) );
		add_action( 'wp_ajax_bkx_fb_disconnect_page', array( $this, 'ajax_disconnect_page' ) );
		add_action( 'wp_ajax_bkx_fb_sync_services', array( $this, 'ajax_sync_services' ) );
		add_action( 'wp_ajax_bkx_fb_sync_bookings', array( $this, 'ajax_sync_bookings' ) );
		add_action( 'wp_ajax_bkx_fb_get_stats', array( $this, 'ajax_get_stats' ) );

		// BookingX hooks.
		add_action( 'bkx_booking_created', array( $this->booking_sync, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_updated', array( $this->booking_sync, 'on_booking_updated' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this->booking_sync, 'on_booking_cancelled' ) );

		// Cron events.
		add_action( 'bkx_fb_sync_bookings', array( $this->booking_sync, 'sync_all' ) );
		add_action( 'bkx_fb_refresh_tokens', array( $this->page_manager, 'refresh_tokens' ) );

		// Schedule cron.
		if ( ! wp_next_scheduled( 'bkx_fb_sync_bookings' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_fb_sync_bookings' );
		}

		if ( ! wp_next_scheduled( 'bkx_fb_refresh_tokens' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_fb_refresh_tokens' );
		}

		// Settings link.
		add_filter( 'plugin_action_links_' . BKX_FB_BOOKING_BASENAME, array( $this, 'add_settings_link' ) );

		// OAuth callback.
		add_action( 'template_redirect', array( $this, 'handle_oauth_callback' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Facebook Booking', 'bkx-facebook-booking' ),
			__( 'Facebook Booking', 'bkx-facebook-booking' ),
			'manage_options',
			'bkx-facebook-booking',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-facebook-booking' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-facebook-booking-admin',
			BKX_FB_BOOKING_URL . 'assets/css/admin.css',
			array(),
			BKX_FB_BOOKING_VERSION
		);

		wp_enqueue_script(
			'bkx-facebook-booking-admin',
			BKX_FB_BOOKING_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_FB_BOOKING_VERSION,
			true
		);

		$settings = get_option( 'bkx_fb_booking_settings', array() );

		wp_localize_script(
			'bkx-facebook-booking-admin',
			'bkxFb',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'bkx_facebook_booking' ),
				'appId'       => $settings['app_id'] ?? '',
				'redirectUri' => $this->get_oauth_redirect_uri(),
				'i18n'        => array(
					'saved'       => __( 'Settings saved!', 'bkx-facebook-booking' ),
					'connected'   => __( 'Page connected successfully!', 'bkx-facebook-booking' ),
					'disconnected' => __( 'Page disconnected.', 'bkx-facebook-booking' ),
					'synced'      => __( 'Sync completed!', 'bkx-facebook-booking' ),
					'connecting'  => __( 'Connecting...', 'bkx-facebook-booking' ),
				),
			)
		);
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes() {
		// Webhook endpoint for Facebook.
		register_rest_route(
			'bkx-fb/v1',
			'/webhook',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->webhook_handler, 'verify_webhook' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this->webhook_handler, 'handle_webhook' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Booking widget endpoint.
		register_rest_route(
			'bkx-fb/v1',
			'/widget/(?P<page_id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'render_booking_widget' ),
				'permission_callback' => '__return_true',
			)
		);

		// Services endpoint.
		register_rest_route(
			'bkx-fb/v1',
			'/services/(?P<page_id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_services' ),
				'permission_callback' => '__return_true',
			)
		);

		// Availability endpoint.
		register_rest_route(
			'bkx-fb/v1',
			'/availability/(?P<page_id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_availability' ),
				'permission_callback' => '__return_true',
			)
		);

		// Create booking endpoint.
		register_rest_route(
			'bkx-fb/v1',
			'/book/(?P<page_id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_booking' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get OAuth redirect URI.
	 *
	 * @return string
	 */
	private function get_oauth_redirect_uri() {
		return add_query_arg( 'bkx_fb_oauth', '1', home_url( '/' ) );
	}

	/**
	 * Handle OAuth callback.
	 */
	public function handle_oauth_callback() {
		if ( ! isset( $_GET['bkx_fb_oauth'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( empty( $code ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bkx-facebook-booking&error=no_code' ) );
			exit;
		}

		// Exchange code for access token.
		$result = $this->facebook_api->exchange_code_for_token( $code, $this->get_oauth_redirect_uri() );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=bkx-facebook-booking&error=' . urlencode( $result->get_error_message() ) ) );
			exit;
		}

		// Store user token temporarily and get pages.
		set_transient( 'bkx_fb_user_token', $result['access_token'], HOUR_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=bkx-facebook-booking&tab=pages&connected=1' ) );
		exit;
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification
		include BKX_FB_BOOKING_PATH . 'templates/admin/page.php';
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_facebook_booking', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-facebook-booking' ) ) );
		}

		$settings = array(
			'enabled'          => isset( $_POST['enabled'] ) ? 1 : 0,
			'app_id'           => isset( $_POST['app_id'] ) ? sanitize_text_field( wp_unslash( $_POST['app_id'] ) ) : '',
			'app_secret'       => isset( $_POST['app_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['app_secret'] ) ) : '',
			'verify_token'     => isset( $_POST['verify_token'] ) ? sanitize_text_field( wp_unslash( $_POST['verify_token'] ) ) : wp_generate_uuid4(),
			'auto_sync'        => isset( $_POST['auto_sync'] ) ? 1 : 0,
			'sync_interval'    => isset( $_POST['sync_interval'] ) ? absint( $_POST['sync_interval'] ) : 60,
			'booking_page'     => isset( $_POST['booking_page'] ) ? absint( $_POST['booking_page'] ) : 0,
			'confirmation_msg' => isset( $_POST['confirmation_msg'] ) ? sanitize_textarea_field( wp_unslash( $_POST['confirmation_msg'] ) ) : '',
		);

		update_option( 'bkx_fb_booking_settings', $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'bkx-facebook-booking' ) ) );
	}

	/**
	 * AJAX: Connect page.
	 */
	public function ajax_connect_page() {
		check_ajax_referer( 'bkx_facebook_booking', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-facebook-booking' ) ) );
		}

		$page_id      = isset( $_POST['page_id'] ) ? sanitize_text_field( wp_unslash( $_POST['page_id'] ) ) : '';
		$access_token = isset( $_POST['access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : '';

		if ( empty( $page_id ) || empty( $access_token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid page data.', 'bkx-facebook-booking' ) ) );
		}

		$result = $this->page_manager->connect_page( $page_id, $access_token );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Page connected successfully.', 'bkx-facebook-booking' ) ) );
	}

	/**
	 * AJAX: Disconnect page.
	 */
	public function ajax_disconnect_page() {
		check_ajax_referer( 'bkx_facebook_booking', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-facebook-booking' ) ) );
		}

		$page_id = isset( $_POST['page_id'] ) ? sanitize_text_field( wp_unslash( $_POST['page_id'] ) ) : '';

		$this->page_manager->disconnect_page( $page_id );

		wp_send_json_success( array( 'message' => __( 'Page disconnected.', 'bkx-facebook-booking' ) ) );
	}

	/**
	 * AJAX: Sync services.
	 */
	public function ajax_sync_services() {
		check_ajax_referer( 'bkx_facebook_booking', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-facebook-booking' ) ) );
		}

		$page_id = isset( $_POST['page_id'] ) ? sanitize_text_field( wp_unslash( $_POST['page_id'] ) ) : '';
		$synced  = $this->page_manager->sync_services( $page_id );

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: Number of services */
				__( '%d services synced.', 'bkx-facebook-booking' ),
				$synced
			),
		) );
	}

	/**
	 * AJAX: Sync bookings.
	 */
	public function ajax_sync_bookings() {
		check_ajax_referer( 'bkx_facebook_booking', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-facebook-booking' ) ) );
		}

		$synced = $this->booking_sync->sync_all();

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: Number of bookings */
				__( '%d bookings synced.', 'bkx-facebook-booking' ),
				$synced
			),
		) );
	}

	/**
	 * AJAX: Get stats.
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'bkx_facebook_booking', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-facebook-booking' ) ) );
		}

		global $wpdb;

		$pages_table    = $wpdb->prefix . 'bkx_fb_pages';
		$bookings_table = $wpdb->prefix . 'bkx_fb_bookings';

		$stats = array(
			'pages_connected' => $wpdb->get_var( "SELECT COUNT(*) FROM {$pages_table} WHERE status = 'active'" ), // phpcs:ignore
			'total_bookings'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table}" ), // phpcs:ignore
			'pending'         => $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'pending'" ), // phpcs:ignore
			'confirmed'       => $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'confirmed'" ), // phpcs:ignore
			'today_bookings'  => $wpdb->get_var( // phpcs:ignore
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$bookings_table} WHERE created_at >= %s",
					gmdate( 'Y-m-d 00:00:00' )
				)
			),
		);

		wp_send_json_success( $stats );
	}

	/**
	 * Render booking widget.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function render_booking_widget( $request ) {
		$page_id = $request->get_param( 'page_id' );
		$page    = $this->page_manager->get_page( $page_id );

		if ( ! $page ) {
			return new \WP_REST_Response( array( 'error' => 'Page not found' ), 404 );
		}

		$settings = get_option( 'bkx_fb_booking_settings', array() );

		ob_start();
		include BKX_FB_BOOKING_PATH . 'templates/widget/booking-form.php';
		$html = ob_get_clean();

		return rest_ensure_response( array(
			'html'     => $html,
			'page'     => array(
				'id'   => $page->page_id,
				'name' => $page->page_name,
			),
		) );
	}

	/**
	 * Get services endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_services( $request ) {
		$page_id  = $request->get_param( 'page_id' );
		$services = $this->page_manager->get_services( $page_id );

		$formatted = array();
		foreach ( $services as $service ) {
			$formatted[] = array(
				'id'          => $service->id,
				'name'        => $service->name,
				'description' => $service->description,
				'price'       => (float) $service->price,
				'duration'    => $service->duration_minutes,
			);
		}

		return rest_ensure_response( array( 'services' => $formatted ) );
	}

	/**
	 * Get availability endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_availability( $request ) {
		$page_id    = $request->get_param( 'page_id' );
		$service_id = $request->get_param( 'service_id' );
		$date       = $request->get_param( 'date' );

		if ( empty( $date ) ) {
			$date = gmdate( 'Y-m-d' );
		}

		$slots = $this->booking_sync->get_available_slots( $service_id, $date );

		return rest_ensure_response( array(
			'date'  => $date,
			'slots' => $slots,
		) );
	}

	/**
	 * Create booking endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function create_booking( $request ) {
		$page_id = $request->get_param( 'page_id' );
		$body    = $request->get_json_params();

		$booking_data = array(
			'page_id'    => $page_id,
			'service_id' => $body['service_id'] ?? null,
			'date'       => $body['date'] ?? null,
			'time'       => $body['time'] ?? null,
			'name'       => $body['name'] ?? null,
			'email'      => $body['email'] ?? null,
			'phone'      => $body['phone'] ?? null,
			'notes'      => $body['notes'] ?? null,
			'fb_user_id' => $body['fb_user_id'] ?? null,
		);

		$result = $this->booking_sync->create_booking( $booking_data );

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( array(
				'success' => false,
				'error'   => $result->get_error_message(),
			) );
		}

		$settings = get_option( 'bkx_fb_booking_settings', array() );

		return rest_ensure_response( array(
			'success'    => true,
			'booking_id' => $result,
			'message'    => $settings['confirmation_msg'] ?? __( 'Your booking has been confirmed!', 'bkx-facebook-booking' ),
		) );
	}

	/**
	 * Add settings link.
	 *
	 * @param array $links Plugin links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=bkx-facebook-booking' ) . '">' . __( 'Settings', 'bkx-facebook-booking' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}

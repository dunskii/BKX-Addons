<?php
/**
 * Main BKX to BKX Integration addon class.
 *
 * @package BookingX\BkxIntegration
 */

namespace BookingX\BkxIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * BkxIntegrationAddon class.
 */
class BkxIntegrationAddon {

	/**
	 * Single instance.
	 *
	 * @var BkxIntegrationAddon
	 */
	private static $instance = null;

	/**
	 * Services container.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get singleton instance.
	 *
	 * @return BkxIntegrationAddon
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
		$this->services['sites']         = new Services\SiteService();
		$this->services['api_client']    = new Services\ApiClient();
		$this->services['booking_sync']  = new Services\BookingSync();
		$this->services['availability']  = new Services\AvailabilitySync();
		$this->services['customer_sync'] = new Services\CustomerSync();
		$this->services['queue']         = new Services\QueueProcessor();
		$this->services['conflicts']     = new Services\ConflictResolver();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Settings integration.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_bkx_integration', array( $this, 'render_settings_tab' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_bkx_save_site', array( $this, 'ajax_save_site' ) );
		add_action( 'wp_ajax_bkx_bkx_delete_site', array( $this, 'ajax_delete_site' ) );
		add_action( 'wp_ajax_bkx_bkx_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_bkx_bkx_sync_now', array( $this, 'ajax_sync_now' ) );
		add_action( 'wp_ajax_bkx_bkx_resolve_conflict', array( $this, 'ajax_resolve_conflict' ) );
		add_action( 'wp_ajax_bkx_bkx_regenerate_keys', array( $this, 'ajax_regenerate_keys' ) );
		add_action( 'wp_ajax_bkx_bkx_clear_logs', array( $this, 'ajax_clear_logs' ) );

		// REST API endpoints (for incoming sync requests).
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// BookingX hooks for outgoing sync.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_updated', array( $this, 'on_booking_updated' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this, 'on_booking_status_changed' ), 10, 3 );
		add_action( 'bkx_booking_deleted', array( $this, 'on_booking_deleted' ), 10, 1 );

		// Customer hooks.
		add_action( 'user_register', array( $this, 'on_customer_created' ), 10, 1 );
		add_action( 'profile_update', array( $this, 'on_customer_updated' ), 10, 2 );

		// Availability hooks.
		add_action( 'bkx_availability_changed', array( $this, 'on_availability_changed' ), 10, 2 );
		add_filter( 'bkx_check_availability', array( $this, 'check_remote_availability' ), 10, 4 );

		// Cron jobs.
		add_action( 'bkx_bkx_process_queue', array( $this, 'process_queue' ) );
		add_action( 'bkx_bkx_sync_availability', array( $this, 'sync_all_availability' ) );
		add_action( 'bkx_bkx_health_check', array( $this, 'health_check' ) );
		add_action( 'bkx_bkx_cleanup_logs', array( $this, 'cleanup_old_logs' ) );

		// Schedule cron jobs.
		$this->schedule_cron_jobs();
	}

	/**
	 * Schedule cron jobs.
	 */
	private function schedule_cron_jobs() {
		if ( ! wp_next_scheduled( 'bkx_bkx_process_queue' ) ) {
			wp_schedule_event( time(), 'every_minute', 'bkx_bkx_process_queue' );
		}

		if ( ! wp_next_scheduled( 'bkx_bkx_sync_availability' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_bkx_sync_availability' );
		}

		if ( ! wp_next_scheduled( 'bkx_bkx_health_check' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'bkx_bkx_health_check' );
		}

		if ( ! wp_next_scheduled( 'bkx_bkx_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_bkx_cleanup_logs' );
		}

		// Register custom interval.
		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				$schedules['every_minute'] = array(
					'interval' => 60,
					'display'  => __( 'Every Minute', 'bkx-bkx-integration' ),
				);
				return $schedules;
			}
		);
	}

	/**
	 * Get a service.
	 *
	 * @param string $name Service name.
	 * @return mixed
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'BKX Integration', 'bkx-bkx-integration' ),
			__( 'BKX Integration', 'bkx-bkx-integration' ),
			'manage_options',
			'bkx-integration',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bkx_booking_page_bkx-integration' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-bkx-admin',
			BKX_BKX_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_BKX_VERSION
		);

		wp_enqueue_script(
			'bkx-bkx-admin',
			BKX_BKX_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_BKX_VERSION,
			true
		);

		wp_localize_script(
			'bkx-bkx-admin',
			'bkxBkx',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_bkx_admin' ),
				'strings'  => array(
					'confirm_delete'     => __( 'Are you sure you want to delete this remote site?', 'bkx-bkx-integration' ),
					'confirm_regenerate' => __( 'Regenerating keys will require updating all connected sites. Continue?', 'bkx-bkx-integration' ),
					'testing'            => __( 'Testing connection...', 'bkx-bkx-integration' ),
					'syncing'            => __( 'Syncing...', 'bkx-bkx-integration' ),
					'saving'             => __( 'Saving...', 'bkx-bkx-integration' ),
					'error'              => __( 'An error occurred. Please try again.', 'bkx-bkx-integration' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = sanitize_text_field( $_GET['tab'] ?? 'sites' );
		include BKX_BKX_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['bkx_integration'] = __( 'BKX Integration', 'bkx-bkx-integration' );
		return $tabs;
	}

	/**
	 * Render settings tab content.
	 */
	public function render_settings_tab() {
		include BKX_BKX_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		// Incoming sync endpoints.
		register_rest_route(
			'bkx-integration/v1',
			'/booking',
			array(
				'methods'             => array( 'POST', 'PUT', 'DELETE' ),
				'callback'            => array( $this, 'handle_incoming_booking' ),
				'permission_callback' => array( $this, 'verify_api_request' ),
			)
		);

		register_rest_route(
			'bkx-integration/v1',
			'/availability',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_incoming_availability' ),
				'permission_callback' => array( $this, 'verify_api_request' ),
			)
		);

		register_rest_route(
			'bkx-integration/v1',
			'/availability/check',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_availability_check' ),
				'permission_callback' => array( $this, 'verify_api_request' ),
			)
		);

		register_rest_route(
			'bkx-integration/v1',
			'/customer',
			array(
				'methods'             => array( 'POST', 'PUT' ),
				'callback'            => array( $this, 'handle_incoming_customer' ),
				'permission_callback' => array( $this, 'verify_api_request' ),
			)
		);

		register_rest_route(
			'bkx-integration/v1',
			'/ping',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_ping' ),
				'permission_callback' => array( $this, 'verify_api_request' ),
			)
		);
	}

	/**
	 * Verify API request authentication.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function verify_api_request( $request ) {
		$api_key    = $request->get_header( 'X-BKX-API-Key' );
		$signature  = $request->get_header( 'X-BKX-Signature' );
		$timestamp  = $request->get_header( 'X-BKX-Timestamp' );

		if ( ! $api_key || ! $signature || ! $timestamp ) {
			return new \WP_Error( 'missing_auth', __( 'Missing authentication headers.', 'bkx-bkx-integration' ), array( 'status' => 401 ) );
		}

		// Check timestamp (within 5 minutes).
		$time_diff = abs( time() - intval( $timestamp ) );
		if ( $time_diff > 300 ) {
			return new \WP_Error( 'expired_request', __( 'Request timestamp expired.', 'bkx-bkx-integration' ), array( 'status' => 401 ) );
		}

		// Verify API key.
		$local_api_key = get_option( 'bkx_bkx_api_key' );
		if ( $api_key !== $local_api_key ) {
			return new \WP_Error( 'invalid_key', __( 'Invalid API key.', 'bkx-bkx-integration' ), array( 'status' => 401 ) );
		}

		// Verify signature.
		$api_secret      = get_option( 'bkx_bkx_api_secret' );
		$request_body    = $request->get_body();
		$expected_sig    = hash_hmac( 'sha256', $timestamp . $request_body, $api_secret );

		if ( ! hash_equals( $expected_sig, $signature ) ) {
			return new \WP_Error( 'invalid_signature', __( 'Invalid signature.', 'bkx-bkx-integration' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Handle incoming booking sync.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_incoming_booking( $request ) {
		$data   = $request->get_json_params();
		$method = $request->get_method();

		$result = $this->services['booking_sync']->handle_incoming( $data, $method );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'booking_id' => $result,
			),
			200
		);
	}

	/**
	 * Handle incoming availability sync.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_incoming_availability( $request ) {
		$data   = $request->get_json_params();
		$result = $this->services['availability']->handle_incoming( $data );

		return new \WP_REST_Response( array( 'success' => $result ), 200 );
	}

	/**
	 * Handle availability check request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_availability_check( $request ) {
		$date       = $request->get_param( 'date' );
		$service_id = $request->get_param( 'service_id' );
		$staff_id   = $request->get_param( 'staff_id' );

		$availability = $this->services['availability']->get_local_availability( $date, $service_id, $staff_id );

		return new \WP_REST_Response(
			array(
				'success'      => true,
				'availability' => $availability,
			),
			200
		);
	}

	/**
	 * Handle incoming customer sync.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_incoming_customer( $request ) {
		$data   = $request->get_json_params();
		$result = $this->services['customer_sync']->handle_incoming( $data );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		return new \WP_REST_Response(
			array(
				'success'     => true,
				'customer_id' => $result,
			),
			200
		);
	}

	/**
	 * Handle ping request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_ping( $request ) {
		return new \WP_REST_Response(
			array(
				'success'   => true,
				'version'   => BKX_BKX_VERSION,
				'site_name' => get_bloginfo( 'name' ),
				'site_url'  => home_url(),
			),
			200
		);
	}

	/**
	 * AJAX: Save remote site.
	 */
	public function ajax_save_site() {
		check_ajax_referer( 'bkx_bkx_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bkx-integration' ) ) );
		}

		$site_id = absint( $_POST['site_id'] ?? 0 );
		$data    = array(
			'name'              => sanitize_text_field( $_POST['name'] ?? '' ),
			'url'               => esc_url_raw( $_POST['url'] ?? '' ),
			'api_key'           => sanitize_text_field( $_POST['api_key'] ?? '' ),
			'api_secret'        => sanitize_text_field( $_POST['api_secret'] ?? '' ),
			'direction'         => sanitize_text_field( $_POST['direction'] ?? 'both' ),
			'status'            => sanitize_text_field( $_POST['status'] ?? 'active' ),
			'sync_bookings'     => absint( $_POST['sync_bookings'] ?? 1 ),
			'sync_availability' => absint( $_POST['sync_availability'] ?? 1 ),
			'sync_customers'    => absint( $_POST['sync_customers'] ?? 0 ),
			'sync_services'     => absint( $_POST['sync_services'] ?? 0 ),
			'sync_staff'        => absint( $_POST['sync_staff'] ?? 0 ),
		);

		if ( empty( $data['name'] ) || empty( $data['url'] ) || empty( $data['api_key'] ) || empty( $data['api_secret'] ) ) {
			wp_send_json_error( array( 'message' => __( 'All fields are required.', 'bkx-bkx-integration' ) ) );
		}

		$result = $this->services['sites']->save( $site_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Site saved successfully.', 'bkx-bkx-integration' ),
				'site_id' => $result,
			)
		);
	}

	/**
	 * AJAX: Delete remote site.
	 */
	public function ajax_delete_site() {
		check_ajax_referer( 'bkx_bkx_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bkx-integration' ) ) );
		}

		$site_id = absint( $_POST['site_id'] ?? 0 );

		if ( ! $site_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid site ID.', 'bkx-bkx-integration' ) ) );
		}

		$this->services['sites']->delete( $site_id );

		wp_send_json_success( array( 'message' => __( 'Site deleted.', 'bkx-bkx-integration' ) ) );
	}

	/**
	 * AJAX: Test connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'bkx_bkx_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bkx-integration' ) ) );
		}

		$site_id = absint( $_POST['site_id'] ?? 0 );

		$result = $this->services['api_client']->ping( $site_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'   => __( 'Connection successful!', 'bkx-bkx-integration' ),
				'site_info' => $result,
			)
		);
	}

	/**
	 * AJAX: Sync now.
	 */
	public function ajax_sync_now() {
		check_ajax_referer( 'bkx_bkx_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bkx-integration' ) ) );
		}

		$site_id   = absint( $_POST['site_id'] ?? 0 );
		$sync_type = sanitize_text_field( $_POST['sync_type'] ?? 'all' );

		$results = array();

		switch ( $sync_type ) {
			case 'bookings':
				$results['bookings'] = $this->services['booking_sync']->sync_site( $site_id );
				break;

			case 'availability':
				$results['availability'] = $this->services['availability']->sync_site( $site_id );
				break;

			case 'customers':
				$results['customers'] = $this->services['customer_sync']->sync_site( $site_id );
				break;

			default:
				$results['bookings']     = $this->services['booking_sync']->sync_site( $site_id );
				$results['availability'] = $this->services['availability']->sync_site( $site_id );
				break;
		}

		wp_send_json_success(
			array(
				'message' => __( 'Sync completed.', 'bkx-bkx-integration' ),
				'results' => $results,
			)
		);
	}

	/**
	 * AJAX: Resolve conflict.
	 */
	public function ajax_resolve_conflict() {
		check_ajax_referer( 'bkx_bkx_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bkx-integration' ) ) );
		}

		$conflict_id = absint( $_POST['conflict_id'] ?? 0 );
		$resolution  = sanitize_text_field( $_POST['resolution'] ?? '' );

		if ( ! $conflict_id || ! $resolution ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'bkx-bkx-integration' ) ) );
		}

		$result = $this->services['conflicts']->resolve( $conflict_id, $resolution );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Conflict resolved.', 'bkx-bkx-integration' ) ) );
	}

	/**
	 * AJAX: Regenerate API keys.
	 */
	public function ajax_regenerate_keys() {
		check_ajax_referer( 'bkx_bkx_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bkx-integration' ) ) );
		}

		$new_key    = wp_generate_password( 32, false );
		$new_secret = wp_generate_password( 64, false );

		update_option( 'bkx_bkx_api_key', $new_key );
		update_option( 'bkx_bkx_api_secret', $new_secret );

		wp_send_json_success(
			array(
				'message'    => __( 'API keys regenerated.', 'bkx-bkx-integration' ),
				'api_key'    => $new_key,
				'api_secret' => $new_secret,
			)
		);
	}

	/**
	 * AJAX: Clear logs.
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'bkx_bkx_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bkx-integration' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_remote_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		wp_send_json_success( array( 'message' => __( 'Logs cleared.', 'bkx-bkx-integration' ) ) );
	}

	/**
	 * On booking created.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		$this->services['booking_sync']->queue_outgoing( $booking_id, 'create' );
	}

	/**
	 * On booking updated.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_updated( $booking_id, $booking_data ) {
		$this->services['booking_sync']->queue_outgoing( $booking_id, 'update' );
	}

	/**
	 * On booking status changed.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function on_booking_status_changed( $booking_id, $old_status, $new_status ) {
		$this->services['booking_sync']->queue_outgoing( $booking_id, 'status_change' );
	}

	/**
	 * On booking deleted.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public function on_booking_deleted( $booking_id ) {
		$this->services['booking_sync']->queue_outgoing( $booking_id, 'delete' );
	}

	/**
	 * On customer created.
	 *
	 * @param int $user_id User ID.
	 */
	public function on_customer_created( $user_id ) {
		$this->services['customer_sync']->queue_outgoing( $user_id, 'create' );
	}

	/**
	 * On customer updated.
	 *
	 * @param int      $user_id       User ID.
	 * @param \WP_User $old_user_data Old user data.
	 */
	public function on_customer_updated( $user_id, $old_user_data ) {
		$this->services['customer_sync']->queue_outgoing( $user_id, 'update' );
	}

	/**
	 * On availability changed.
	 *
	 * @param int   $seat_id Seat ID.
	 * @param array $data    Availability data.
	 */
	public function on_availability_changed( $seat_id, $data ) {
		$this->services['availability']->queue_outgoing( $seat_id, $data );
	}

	/**
	 * Check remote availability.
	 *
	 * @param bool   $is_available Current availability.
	 * @param string $date         Date.
	 * @param int    $service_id   Service ID.
	 * @param int    $staff_id     Staff ID.
	 * @return bool
	 */
	public function check_remote_availability( $is_available, $date, $service_id, $staff_id ) {
		if ( ! $is_available ) {
			return false;
		}

		return $this->services['availability']->check_remote( $date, $service_id, $staff_id );
	}

	/**
	 * Process sync queue.
	 */
	public function process_queue() {
		$this->services['queue']->process();
	}

	/**
	 * Sync all availability.
	 */
	public function sync_all_availability() {
		$sites = $this->services['sites']->get_all( array( 'status' => 'active' ) );

		foreach ( $sites as $site ) {
			if ( $site->sync_availability ) {
				$this->services['availability']->sync_site( $site->id );
			}
		}
	}

	/**
	 * Health check for all sites.
	 */
	public function health_check() {
		$sites = $this->services['sites']->get_all( array( 'status' => 'active' ) );

		foreach ( $sites as $site ) {
			$result = $this->services['api_client']->ping( $site->id );

			if ( is_wp_error( $result ) ) {
				$this->services['sites']->update_status( $site->id, 'error', $result->get_error_message() );
			} else {
				$this->services['sites']->update_status( $site->id, 'active' );
			}
		}
	}

	/**
	 * Cleanup old logs.
	 */
	public function cleanup_old_logs() {
		global $wpdb;

		$retention_days = get_option( 'bkx_bkx_log_retention', 30 );
		$table          = $wpdb->prefix . 'bkx_remote_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$retention_days
			)
		);
	}
}

<?php
/**
 * Main HubSpot Addon class.
 *
 * @package BookingX\HubSpot
 */

namespace BookingX\HubSpot;

use BookingX\HubSpot\Services\HubSpotApi;
use BookingX\HubSpot\Services\ContactSync;
use BookingX\HubSpot\Services\DealSync;
use BookingX\HubSpot\Services\QueueProcessor;

defined( 'ABSPATH' ) || exit;

/**
 * HubSpotAddon class.
 */
class HubSpotAddon {

	/**
	 * Singleton instance.
	 *
	 * @var HubSpotAddon
	 */
	private static $instance = null;

	/**
	 * HubSpot API instance.
	 *
	 * @var HubSpotApi
	 */
	private $api;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Get singleton instance.
	 *
	 * @return HubSpotAddon
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
		$this->settings = get_option( 'bkx_hubspot_settings', array() );
		$this->api      = new HubSpotApi();

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Settings tab.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_hubspot', array( $this, 'render_settings_tab' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_hs_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_hs_connect', array( $this, 'ajax_connect' ) );
		add_action( 'wp_ajax_bkx_hs_disconnect', array( $this, 'ajax_disconnect' ) );
		add_action( 'wp_ajax_bkx_hs_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_bkx_hs_sync_now', array( $this, 'ajax_sync_now' ) );
		add_action( 'wp_ajax_bkx_hs_get_logs', array( $this, 'ajax_get_logs' ) );
		add_action( 'wp_ajax_bkx_hs_save_property_mapping', array( $this, 'ajax_save_property_mapping' ) );
		add_action( 'wp_ajax_bkx_hs_delete_property_mapping', array( $this, 'ajax_delete_property_mapping' ) );
		add_action( 'wp_ajax_bkx_hs_get_hs_properties', array( $this, 'ajax_get_hs_properties' ) );
		add_action( 'wp_ajax_bkx_hs_get_pipelines', array( $this, 'ajax_get_pipelines' ) );
		add_action( 'wp_ajax_bkx_hs_get_lists', array( $this, 'ajax_get_lists' ) );

		// OAuth callback.
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );

		// BookingX hooks for sync.
		if ( $this->is_connected() ) {
			add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
			add_action( 'bkx_booking_status_changed', array( $this, 'on_booking_status_changed' ), 10, 3 );
			add_action( 'bkx_booking_updated', array( $this, 'on_booking_updated' ), 10, 2 );
		}

		// Cron hooks.
		add_action( 'bkx_hubspot_sync_cron', array( $this, 'process_sync_queue' ) );
		add_action( 'bkx_hubspot_token_refresh', array( $this, 'refresh_access_token' ) );

		// REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'HubSpot', 'bkx-hubspot' ),
			__( 'HubSpot', 'bkx-hubspot' ),
			'manage_options',
			'bkx-hubspot',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-hubspot' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-hubspot-admin',
			BKX_HUBSPOT_URL . 'assets/css/admin.css',
			array(),
			BKX_HUBSPOT_VERSION
		);

		wp_enqueue_script(
			'bkx-hubspot-admin',
			BKX_HUBSPOT_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_HUBSPOT_VERSION,
			true
		);

		wp_localize_script(
			'bkx-hubspot-admin',
			'bkxHubSpot',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'bkx_hubspot_nonce' ),
				'isConnected' => $this->is_connected(),
				'strings'     => array(
					'connecting'    => __( 'Connecting...', 'bkx-hubspot' ),
					'syncing'       => __( 'Syncing...', 'bkx-hubspot' ),
					'success'       => __( 'Success!', 'bkx-hubspot' ),
					'error'         => __( 'Error occurred', 'bkx-hubspot' ),
					'confirmDelete' => __( 'Are you sure you want to delete this mapping?', 'bkx-hubspot' ),
				),
			)
		);
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['hubspot'] = __( 'HubSpot', 'bkx-hubspot' );
		return $tabs;
	}

	/**
	 * Render settings tab content.
	 */
	public function render_settings_tab() {
		include BKX_HUBSPOT_PATH . 'templates/admin/settings-tab.php';
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		include BKX_HUBSPOT_PATH . 'templates/admin/page.php';
	}

	/**
	 * Check if connected to HubSpot.
	 *
	 * @return bool
	 */
	public function is_connected() {
		$credentials = get_option( 'bkx_hubspot_credentials', array() );
		return ! empty( $credentials['access_token'] );
	}

	/**
	 * Handle OAuth callback.
	 */
	public function handle_oauth_callback() {
		if ( ! isset( $_GET['bkx_hs_oauth'] ) || ! isset( $_GET['code'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'bkx-hubspot' ) );
		}

		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$result = $this->api->exchange_code_for_token( $code );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'bkx-hubspot',
						'error'   => 'oauth_failed',
						'message' => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'edit.php?post_type=bkx_booking' )
				)
			);
			exit;
		}

		// Schedule token refresh.
		if ( ! wp_next_scheduled( 'bkx_hubspot_token_refresh' ) ) {
			wp_schedule_event( time() + 3600, 'hourly', 'bkx_hubspot_token_refresh' );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'bkx-hubspot',
					'success' => 'connected',
				),
				admin_url( 'edit.php?post_type=bkx_booking' )
			)
		);
		exit;
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_hubspot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-hubspot' ) ) );
		}

		$settings = array(
			'client_id'         => sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) ),
			'client_secret'     => sanitize_text_field( wp_unslash( $_POST['client_secret'] ?? '' ) ),
			'sync_contacts'     => ! empty( $_POST['sync_contacts'] ),
			'create_deals'      => ! empty( $_POST['create_deals'] ),
			'sync_on_booking'   => ! empty( $_POST['sync_on_booking'] ),
			'sync_on_status'    => ! empty( $_POST['sync_on_status'] ),
			'add_to_list'       => ! empty( $_POST['add_to_list'] ),
			'list_id'           => sanitize_text_field( wp_unslash( $_POST['list_id'] ?? '' ) ),
			'track_activities'  => ! empty( $_POST['track_activities'] ),
			'pipeline_id'       => sanitize_text_field( wp_unslash( $_POST['pipeline_id'] ?? '' ) ),
			'default_stage_id'  => sanitize_text_field( wp_unslash( $_POST['default_stage_id'] ?? '' ) ),
		);

		update_option( 'bkx_hubspot_settings', $settings );
		$this->settings = $settings;

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully', 'bkx-hubspot' ) ) );
	}

	/**
	 * AJAX: Connect to HubSpot.
	 */
	public function ajax_connect() {
		check_ajax_referer( 'bkx_hubspot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-hubspot' ) ) );
		}

		$auth_url = $this->api->get_authorization_url();

		if ( is_wp_error( $auth_url ) ) {
			wp_send_json_error( array( 'message' => $auth_url->get_error_message() ) );
		}

		wp_send_json_success( array( 'auth_url' => $auth_url ) );
	}

	/**
	 * AJAX: Disconnect from HubSpot.
	 */
	public function ajax_disconnect() {
		check_ajax_referer( 'bkx_hubspot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-hubspot' ) ) );
		}

		// Clear credentials.
		delete_option( 'bkx_hubspot_credentials' );

		// Clear scheduled refresh.
		wp_clear_scheduled_hook( 'bkx_hubspot_token_refresh' );

		wp_send_json_success( array( 'message' => __( 'Disconnected from HubSpot', 'bkx-hubspot' ) ) );
	}

	/**
	 * AJAX: Test connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'bkx_hubspot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-hubspot' ) ) );
		}

		$result = $this->api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Connection successful!', 'bkx-hubspot' ),
				'account' => $result,
			)
		);
	}

	/**
	 * AJAX: Manual sync.
	 */
	public function ajax_sync_now() {
		check_ajax_referer( 'bkx_hubspot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-hubspot' ) ) );
		}

		$sync_type = sanitize_text_field( wp_unslash( $_POST['sync_type'] ?? 'all' ) );
		$limit     = absint( $_POST['limit'] ?? 100 );

		$result = $this->run_sync( $sync_type, $limit );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get sync logs.
	 */
	public function ajax_get_logs() {
		check_ajax_referer( 'bkx_hubspot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-hubspot' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_logs';

		$page     = absint( $_POST['page'] ?? 1 );
		$per_page = absint( $_POST['per_page'] ?? 20 );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		wp_send_json_success(
			array(
				'logs'    => $logs,
				'total'   => (int) $total,
				'pages'   => ceil( $total / $per_page ),
				'current' => $page,
			)
		);
	}

	/**
	 * AJAX: Save property mapping.
	 */
	public function ajax_save_property_mapping() {
		check_ajax_referer( 'bkx_hubspot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-hubspot' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_property_mappings';

		$data = array(
			'object_type'    => sanitize_text_field( wp_unslash( $_POST['object_type'] ?? '' ) ),
			'wp_field'       => sanitize_text_field( wp_unslash( $_POST['wp_field'] ?? '' ) ),
			'hs_property'    => sanitize_text_field( wp_unslash( $_POST['hs_property'] ?? '' ) ),
			'sync_direction' => sanitize_text_field( wp_unslash( $_POST['sync_direction'] ?? 'both' ) ),
			'transform'      => sanitize_text_field( wp_unslash( $_POST['transform'] ?? '' ) ),
			'is_active'      => ! empty( $_POST['is_active'] ) ? 1 : 0,
		);

		$id = absint( $_POST['id'] ?? 0 );

		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $table, $data, array( 'id' => $id ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $table, $data );
			$id = $wpdb->insert_id;
		}

		wp_send_json_success(
			array(
				'message' => __( 'Property mapping saved', 'bkx-hubspot' ),
				'id'      => $id,
			)
		);
	}

	/**
	 * AJAX: Delete property mapping.
	 */
	public function ajax_delete_property_mapping() {
		check_ajax_referer( 'bkx_hubspot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-hubspot' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_property_mappings';
		$id    = absint( $_POST['id'] ?? 0 );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid mapping ID', 'bkx-hubspot' ) ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'id' => $id ) );

		wp_send_json_success( array( 'message' => __( 'Property mapping deleted', 'bkx-hubspot' ) ) );
	}

	/**
	 * AJAX: Get HubSpot object properties.
	 */
	public function ajax_get_hs_properties() {
		check_ajax_referer( 'bkx_hubspot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-hubspot' ) ) );
		}

		$object_type = sanitize_text_field( wp_unslash( $_POST['object_type'] ?? '' ) );

		if ( ! $object_type ) {
			wp_send_json_error( array( 'message' => __( 'Object type required', 'bkx-hubspot' ) ) );
		}

		$properties = $this->api->get_properties( $object_type );

		if ( is_wp_error( $properties ) ) {
			wp_send_json_error( array( 'message' => $properties->get_error_message() ) );
		}

		wp_send_json_success( array( 'properties' => $properties ) );
	}

	/**
	 * AJAX: Get deal pipelines.
	 */
	public function ajax_get_pipelines() {
		check_ajax_referer( 'bkx_hubspot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-hubspot' ) ) );
		}

		$pipelines = $this->api->get_pipelines();

		if ( is_wp_error( $pipelines ) ) {
			wp_send_json_error( array( 'message' => $pipelines->get_error_message() ) );
		}

		wp_send_json_success( array( 'pipelines' => $pipelines ) );
	}

	/**
	 * AJAX: Get contact lists.
	 */
	public function ajax_get_lists() {
		check_ajax_referer( 'bkx_hubspot_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-hubspot' ) ) );
		}

		$lists = $this->api->get_lists();

		if ( is_wp_error( $lists ) ) {
			wp_send_json_error( array( 'message' => $lists->get_error_message() ) );
		}

		wp_send_json_success( array( 'lists' => $lists ) );
	}

	/**
	 * On booking created hook.
	 *
	 * @param int   $booking_id   The booking ID.
	 * @param array $booking_data The booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		if ( empty( $this->settings['sync_on_booking'] ) ) {
			return;
		}

		// Queue the sync operation.
		$this->queue_sync( 'create', 'booking', $booking_id );
	}

	/**
	 * On booking status changed hook.
	 *
	 * @param int    $booking_id The booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function on_booking_status_changed( $booking_id, $old_status, $new_status ) {
		if ( empty( $this->settings['sync_on_status'] ) ) {
			return;
		}

		// Queue the sync operation.
		$this->queue_sync( 'update_status', 'booking', $booking_id, 5 );
	}

	/**
	 * On booking updated hook.
	 *
	 * @param int   $booking_id   The booking ID.
	 * @param array $booking_data The booking data.
	 */
	public function on_booking_updated( $booking_id, $booking_data ) {
		// Queue the sync operation.
		$this->queue_sync( 'update', 'booking', $booking_id );
	}

	/**
	 * Queue a sync operation.
	 *
	 * @param string $operation      Operation type.
	 * @param string $wp_object_type WordPress object type.
	 * @param int    $wp_object_id   WordPress object ID.
	 * @param int    $priority       Priority (lower = higher priority).
	 */
	private function queue_sync( $operation, $wp_object_type, $wp_object_id, $priority = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'operation'      => $operation,
				'wp_object_type' => $wp_object_type,
				'wp_object_id'   => $wp_object_id,
				'priority'       => $priority,
			),
			array( '%s', '%s', '%d', '%d' )
		);
	}

	/**
	 * Process sync queue (cron job).
	 */
	public function process_sync_queue() {
		$processor = new QueueProcessor( $this->api, $this->settings );
		$processor->process();
	}

	/**
	 * Refresh access token (cron job).
	 */
	public function refresh_access_token() {
		$this->api->refresh_token();
	}

	/**
	 * Run manual sync.
	 *
	 * @param string $sync_type Type of sync (contacts, deals, all).
	 * @param int    $limit     Maximum records to sync.
	 * @return array|WP_Error
	 */
	private function run_sync( $sync_type, $limit ) {
		$results = array(
			'contacts' => 0,
			'deals'    => 0,
			'errors'   => array(),
		);

		if ( 'contacts' === $sync_type || 'all' === $sync_type ) {
			$contact_sync = new ContactSync( $this->api, $this->settings );
			$result       = $contact_sync->sync_all( $limit );
			if ( is_wp_error( $result ) ) {
				$results['errors'][] = $result->get_error_message();
			} else {
				$results['contacts'] = $result;
			}
		}

		if ( 'deals' === $sync_type || 'all' === $sync_type ) {
			$deal_sync = new DealSync( $this->api, $this->settings );
			$result    = $deal_sync->sync_all( $limit );
			if ( is_wp_error( $result ) ) {
				$results['errors'][] = $result->get_error_message();
			} else {
				$results['deals'] = $result;
			}
		}

		return $results;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-hubspot/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => array( $this, 'verify_webhook' ),
			)
		);
	}

	/**
	 * Verify webhook request.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return bool
	 */
	public function verify_webhook( $request ) {
		// HubSpot uses client secret for webhook signature.
		$client_secret = $this->settings['client_secret'] ?? '';
		$signature     = $request->get_header( 'X-HubSpot-Signature-v3' );
		$timestamp     = $request->get_header( 'X-HubSpot-Request-Timestamp' );

		if ( empty( $client_secret ) || empty( $signature ) || empty( $timestamp ) ) {
			return false;
		}

		// Check timestamp is within 5 minutes.
		$current_time = time() * 1000;
		if ( abs( $current_time - (int) $timestamp ) > 300000 ) {
			return false;
		}

		$payload       = $request->get_body();
		$request_uri   = $request->get_route();
		$source_string = $request->get_method() . 'https://' . $_SERVER['HTTP_HOST'] . $request_uri . $payload . $timestamp;
		$hash          = hash_hmac( 'sha256', $source_string, $client_secret );

		return hash_equals( base64_encode( hex2bin( $hash ) ), $signature );
	}

	/**
	 * Handle incoming webhook from HubSpot.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$events = $request->get_json_params();

		if ( ! is_array( $events ) ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
		}

		foreach ( $events as $event ) {
			$this->process_webhook_event( $event );
		}

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Process a single webhook event.
	 *
	 * @param array $event Event data.
	 */
	private function process_webhook_event( $event ) {
		$object_type = $event['objectType'] ?? '';
		$event_type  = $event['subscriptionType'] ?? '';
		$object_id   = $event['objectId'] ?? '';

		// Log the webhook.
		$this->log_sync(
			'hs_to_wp',
			'webhook_received',
			null,
			null,
			$object_type,
			$object_id,
			'success',
			"Received {$event_type} for {$object_type}"
		);

		// Process based on object type and event.
		switch ( $object_type ) {
			case 'contact':
				$contact_sync = new ContactSync( $this->api, $this->settings );
				$contact_sync->process_webhook( $event_type, $object_id );
				break;

			case 'deal':
				$deal_sync = new DealSync( $this->api, $this->settings );
				$deal_sync->process_webhook( $event_type, $object_id );
				break;
		}
	}

	/**
	 * Log a sync operation.
	 *
	 * @param string      $direction      Sync direction (wp_to_hs, hs_to_wp).
	 * @param string      $action         Action performed.
	 * @param string|null $wp_object_type WP object type.
	 * @param int|null    $wp_object_id   WP object ID.
	 * @param string|null $hs_object_type HS object type.
	 * @param string|null $hs_object_id   HS object ID.
	 * @param string      $status         Status (success, error).
	 * @param string      $message        Log message.
	 * @param string|null $request_data   Request data JSON.
	 * @param string|null $response_data  Response data JSON.
	 */
	public function log_sync( $direction, $action, $wp_object_type, $wp_object_id, $hs_object_type, $hs_object_id, $status, $message, $request_data = null, $response_data = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_hs_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'direction'      => $direction,
				'action'         => $action,
				'wp_object_type' => $wp_object_type,
				'wp_object_id'   => $wp_object_id,
				'hs_object_type' => $hs_object_type,
				'hs_object_id'   => $hs_object_id,
				'status'         => $status,
				'message'        => $message,
				'request_data'   => $request_data,
				'response_data'  => $response_data,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Get API instance.
	 *
	 * @return HubSpotApi
	 */
	public function get_api() {
		return $this->api;
	}
}

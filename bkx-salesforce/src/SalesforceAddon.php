<?php
/**
 * Main Salesforce Addon class.
 *
 * @package BookingX\Salesforce
 */

namespace BookingX\Salesforce;

use BookingX\Salesforce\Services\SalesforceApi;
use BookingX\Salesforce\Services\ContactSync;
use BookingX\Salesforce\Services\LeadSync;
use BookingX\Salesforce\Services\OpportunitySync;
use BookingX\Salesforce\Services\QueueProcessor;

defined( 'ABSPATH' ) || exit;

/**
 * SalesforceAddon class.
 */
class SalesforceAddon {

	/**
	 * Singleton instance.
	 *
	 * @var SalesforceAddon
	 */
	private static $instance = null;

	/**
	 * Salesforce API instance.
	 *
	 * @var SalesforceApi
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
	 * @return SalesforceAddon
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
		$this->settings = get_option( 'bkx_salesforce_settings', array() );
		$this->api      = new SalesforceApi();

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
		add_action( 'bkx_settings_tab_salesforce', array( $this, 'render_settings_tab' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_sf_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_sf_connect', array( $this, 'ajax_connect' ) );
		add_action( 'wp_ajax_bkx_sf_disconnect', array( $this, 'ajax_disconnect' ) );
		add_action( 'wp_ajax_bkx_sf_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_bkx_sf_sync_now', array( $this, 'ajax_sync_now' ) );
		add_action( 'wp_ajax_bkx_sf_get_logs', array( $this, 'ajax_get_logs' ) );
		add_action( 'wp_ajax_bkx_sf_save_field_mapping', array( $this, 'ajax_save_field_mapping' ) );
		add_action( 'wp_ajax_bkx_sf_delete_field_mapping', array( $this, 'ajax_delete_field_mapping' ) );
		add_action( 'wp_ajax_bkx_sf_get_sf_fields', array( $this, 'ajax_get_sf_fields' ) );

		// OAuth callback.
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );

		// BookingX hooks for sync.
		if ( $this->is_connected() ) {
			add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
			add_action( 'bkx_booking_status_changed', array( $this, 'on_booking_status_changed' ), 10, 3 );
			add_action( 'bkx_booking_updated', array( $this, 'on_booking_updated' ), 10, 2 );
		}

		// Cron hooks.
		add_action( 'bkx_salesforce_sync_cron', array( $this, 'process_sync_queue' ) );
		add_action( 'bkx_salesforce_token_refresh', array( $this, 'refresh_access_token' ) );

		// REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Salesforce', 'bkx-salesforce' ),
			__( 'Salesforce', 'bkx-salesforce' ),
			'manage_options',
			'bkx-salesforce',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-salesforce' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-salesforce-admin',
			BKX_SALESFORCE_URL . 'assets/css/admin.css',
			array(),
			BKX_SALESFORCE_VERSION
		);

		wp_enqueue_script(
			'bkx-salesforce-admin',
			BKX_SALESFORCE_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_SALESFORCE_VERSION,
			true
		);

		wp_localize_script(
			'bkx-salesforce-admin',
			'bkxSalesforce',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'bkx_salesforce_nonce' ),
				'isConnected'  => $this->is_connected(),
				'strings'      => array(
					'connecting'    => __( 'Connecting...', 'bkx-salesforce' ),
					'syncing'       => __( 'Syncing...', 'bkx-salesforce' ),
					'success'       => __( 'Success!', 'bkx-salesforce' ),
					'error'         => __( 'Error occurred', 'bkx-salesforce' ),
					'confirmDelete' => __( 'Are you sure you want to delete this mapping?', 'bkx-salesforce' ),
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
		$tabs['salesforce'] = __( 'Salesforce', 'bkx-salesforce' );
		return $tabs;
	}

	/**
	 * Render settings tab content.
	 */
	public function render_settings_tab() {
		include BKX_SALESFORCE_PATH . 'templates/admin/settings-tab.php';
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		include BKX_SALESFORCE_PATH . 'templates/admin/page.php';
	}

	/**
	 * Check if connected to Salesforce.
	 *
	 * @return bool
	 */
	public function is_connected() {
		$credentials = get_option( 'bkx_salesforce_credentials', array() );
		return ! empty( $credentials['access_token'] ) && ! empty( $credentials['instance_url'] );
	}

	/**
	 * Handle OAuth callback.
	 */
	public function handle_oauth_callback() {
		if ( ! isset( $_GET['bkx_sf_oauth'] ) || ! isset( $_GET['code'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'bkx-salesforce' ) );
		}

		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$result = $this->api->exchange_code_for_token( $code );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'bkx-salesforce',
						'error'   => 'oauth_failed',
						'message' => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'edit.php?post_type=bkx_booking' )
				)
			);
			exit;
		}

		// Schedule token refresh.
		if ( ! wp_next_scheduled( 'bkx_salesforce_token_refresh' ) ) {
			wp_schedule_event( time() + 3600, 'hourly', 'bkx_salesforce_token_refresh' );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'bkx-salesforce',
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
		check_ajax_referer( 'bkx_salesforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-salesforce' ) ) );
		}

		$settings = array(
			'client_id'            => sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) ),
			'client_secret'        => sanitize_text_field( wp_unslash( $_POST['client_secret'] ?? '' ) ),
			'sandbox'              => ! empty( $_POST['sandbox'] ),
			'sync_contacts'        => ! empty( $_POST['sync_contacts'] ),
			'sync_leads'           => ! empty( $_POST['sync_leads'] ),
			'create_opportunities' => ! empty( $_POST['create_opportunities'] ),
			'sync_on_booking'      => ! empty( $_POST['sync_on_booking'] ),
			'sync_on_status'       => ! empty( $_POST['sync_on_status'] ),
			'default_lead_status'  => sanitize_text_field( wp_unslash( $_POST['default_lead_status'] ?? 'Open - Not Contacted' ) ),
			'default_opp_stage'    => sanitize_text_field( wp_unslash( $_POST['default_opp_stage'] ?? 'Prospecting' ) ),
		);

		update_option( 'bkx_salesforce_settings', $settings );
		$this->settings = $settings;

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully', 'bkx-salesforce' ) ) );
	}

	/**
	 * AJAX: Connect to Salesforce.
	 */
	public function ajax_connect() {
		check_ajax_referer( 'bkx_salesforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-salesforce' ) ) );
		}

		$auth_url = $this->api->get_authorization_url();

		if ( is_wp_error( $auth_url ) ) {
			wp_send_json_error( array( 'message' => $auth_url->get_error_message() ) );
		}

		wp_send_json_success( array( 'auth_url' => $auth_url ) );
	}

	/**
	 * AJAX: Disconnect from Salesforce.
	 */
	public function ajax_disconnect() {
		check_ajax_referer( 'bkx_salesforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-salesforce' ) ) );
		}

		// Revoke token.
		$this->api->revoke_token();

		// Clear credentials.
		delete_option( 'bkx_salesforce_credentials' );

		// Clear scheduled refresh.
		wp_clear_scheduled_hook( 'bkx_salesforce_token_refresh' );

		wp_send_json_success( array( 'message' => __( 'Disconnected from Salesforce', 'bkx-salesforce' ) ) );
	}

	/**
	 * AJAX: Test connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'bkx_salesforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-salesforce' ) ) );
		}

		$result = $this->api->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Connection successful!', 'bkx-salesforce' ),
				'org'     => $result,
			)
		);
	}

	/**
	 * AJAX: Manual sync.
	 */
	public function ajax_sync_now() {
		check_ajax_referer( 'bkx_salesforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-salesforce' ) ) );
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
		check_ajax_referer( 'bkx_salesforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-salesforce' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_logs';

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
				'logs'       => $logs,
				'total'      => (int) $total,
				'pages'      => ceil( $total / $per_page ),
				'current'    => $page,
			)
		);
	}

	/**
	 * AJAX: Save field mapping.
	 */
	public function ajax_save_field_mapping() {
		check_ajax_referer( 'bkx_salesforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-salesforce' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_field_mappings';

		$data = array(
			'object_type'    => sanitize_text_field( wp_unslash( $_POST['object_type'] ?? '' ) ),
			'wp_field'       => sanitize_text_field( wp_unslash( $_POST['wp_field'] ?? '' ) ),
			'sf_field'       => sanitize_text_field( wp_unslash( $_POST['sf_field'] ?? '' ) ),
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
				'message' => __( 'Field mapping saved', 'bkx-salesforce' ),
				'id'      => $id,
			)
		);
	}

	/**
	 * AJAX: Delete field mapping.
	 */
	public function ajax_delete_field_mapping() {
		check_ajax_referer( 'bkx_salesforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-salesforce' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_field_mappings';
		$id    = absint( $_POST['id'] ?? 0 );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid mapping ID', 'bkx-salesforce' ) ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $table, array( 'id' => $id ) );

		wp_send_json_success( array( 'message' => __( 'Field mapping deleted', 'bkx-salesforce' ) ) );
	}

	/**
	 * AJAX: Get Salesforce object fields.
	 */
	public function ajax_get_sf_fields() {
		check_ajax_referer( 'bkx_salesforce_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'bkx-salesforce' ) ) );
		}

		$object_type = sanitize_text_field( wp_unslash( $_POST['object_type'] ?? '' ) );

		if ( ! $object_type ) {
			wp_send_json_error( array( 'message' => __( 'Object type required', 'bkx-salesforce' ) ) );
		}

		$fields = $this->api->describe_object( $object_type );

		if ( is_wp_error( $fields ) ) {
			wp_send_json_error( array( 'message' => $fields->get_error_message() ) );
		}

		wp_send_json_success( array( 'fields' => $fields ) );
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
		$table = $wpdb->prefix . 'bkx_sf_queue';

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
	 * @param string $sync_type Type of sync (contacts, leads, opportunities, all).
	 * @param int    $limit     Maximum records to sync.
	 * @return array|WP_Error
	 */
	private function run_sync( $sync_type, $limit ) {
		$results = array(
			'contacts'      => 0,
			'leads'         => 0,
			'opportunities' => 0,
			'errors'        => array(),
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

		if ( 'leads' === $sync_type || 'all' === $sync_type ) {
			$lead_sync = new LeadSync( $this->api, $this->settings );
			$result    = $lead_sync->sync_all( $limit );
			if ( is_wp_error( $result ) ) {
				$results['errors'][] = $result->get_error_message();
			} else {
				$results['leads'] = $result;
			}
		}

		if ( 'opportunities' === $sync_type || 'all' === $sync_type ) {
			$opp_sync = new OpportunitySync( $this->api, $this->settings );
			$result   = $opp_sync->sync_all( $limit );
			if ( is_wp_error( $result ) ) {
				$results['errors'][] = $result->get_error_message();
			} else {
				$results['opportunities'] = $result;
			}
		}

		return $results;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-salesforce/v1',
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
		$secret    = $this->settings['webhook_secret'] ?? '';
		$signature = $request->get_header( 'X-SF-Signature' );

		if ( empty( $secret ) || empty( $signature ) ) {
			return false;
		}

		$payload = $request->get_body();
		$hash    = hash_hmac( 'sha256', $payload, $secret );

		return hash_equals( $hash, $signature );
	}

	/**
	 * Handle incoming webhook from Salesforce.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$payload = $request->get_json_params();

		if ( empty( $payload['sobject'] ) || empty( $payload['event'] ) ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
		}

		$sobject = $payload['sobject'];
		$event   = $payload['event'];
		$data    = $payload['data'] ?? array();

		// Log the webhook.
		$this->log_sync(
			'sf_to_wp',
			'webhook_received',
			null,
			null,
			$sobject,
			$data['Id'] ?? null,
			'success',
			"Received {$event} for {$sobject}",
			wp_json_encode( $payload )
		);

		// Process based on object type.
		switch ( $sobject ) {
			case 'Contact':
				$this->process_contact_webhook( $event, $data );
				break;
			case 'Lead':
				$this->process_lead_webhook( $event, $data );
				break;
			case 'Opportunity':
				$this->process_opportunity_webhook( $event, $data );
				break;
		}

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Process Contact webhook.
	 *
	 * @param string $event Event type.
	 * @param array  $data  Contact data.
	 */
	private function process_contact_webhook( $event, $data ) {
		// Implementation handled by ContactSync service.
		$contact_sync = new ContactSync( $this->api, $this->settings );
		$contact_sync->process_webhook( $event, $data );
	}

	/**
	 * Process Lead webhook.
	 *
	 * @param string $event Event type.
	 * @param array  $data  Lead data.
	 */
	private function process_lead_webhook( $event, $data ) {
		// Implementation handled by LeadSync service.
		$lead_sync = new LeadSync( $this->api, $this->settings );
		$lead_sync->process_webhook( $event, $data );
	}

	/**
	 * Process Opportunity webhook.
	 *
	 * @param string $event Event type.
	 * @param array  $data  Opportunity data.
	 */
	private function process_opportunity_webhook( $event, $data ) {
		// Implementation handled by OpportunitySync service.
		$opp_sync = new OpportunitySync( $this->api, $this->settings );
		$opp_sync->process_webhook( $event, $data );
	}

	/**
	 * Log a sync operation.
	 *
	 * @param string      $direction      Sync direction (wp_to_sf, sf_to_wp).
	 * @param string      $action         Action performed.
	 * @param string|null $wp_object_type WP object type.
	 * @param int|null    $wp_object_id   WP object ID.
	 * @param string|null $sf_object_type SF object type.
	 * @param string|null $sf_object_id   SF object ID.
	 * @param string      $status         Status (success, error).
	 * @param string      $message        Log message.
	 * @param string|null $request_data   Request data JSON.
	 * @param string|null $response_data  Response data JSON.
	 */
	public function log_sync( $direction, $action, $wp_object_type, $wp_object_id, $sf_object_type, $sf_object_id, $status, $message, $request_data = null, $response_data = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_sf_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'direction'      => $direction,
				'action'         => $action,
				'wp_object_type' => $wp_object_type,
				'wp_object_id'   => $wp_object_id,
				'sf_object_type' => $sf_object_type,
				'sf_object_id'   => $sf_object_id,
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
	 * @return SalesforceApi
	 */
	public function get_api() {
		return $this->api;
	}
}

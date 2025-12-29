<?php
/**
 * Main Reserve with Google Addon Class.
 *
 * @package BookingX\ReserveGoogle
 */

namespace BookingX\ReserveGoogle;

use BookingX\ReserveGoogle\Services\FeedGenerator;
use BookingX\ReserveGoogle\Services\BookingHandler;
use BookingX\ReserveGoogle\Services\AvailabilitySync;
use BookingX\ReserveGoogle\Services\MerchantManager;

defined( 'ABSPATH' ) || exit;

/**
 * ReserveGoogleAddon class.
 */
class ReserveGoogleAddon {

	/**
	 * Feed generator.
	 *
	 * @var FeedGenerator
	 */
	private $feed_generator;

	/**
	 * Booking handler.
	 *
	 * @var BookingHandler
	 */
	private $booking_handler;

	/**
	 * Availability sync.
	 *
	 * @var AvailabilitySync
	 */
	private $availability_sync;

	/**
	 * Merchant manager.
	 *
	 * @var MerchantManager
	 */
	private $merchant_manager;

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
		$this->merchant_manager  = new MerchantManager();
		$this->availability_sync = new AvailabilitySync();
		$this->feed_generator    = new FeedGenerator( $this->merchant_manager );
		$this->booking_handler   = new BookingHandler( $this->merchant_manager );
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// REST API endpoints for Reserve with Google.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_rwg_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_rwg_sync_services', array( $this, 'ajax_sync_services' ) );
		add_action( 'wp_ajax_bkx_rwg_test_feed', array( $this, 'ajax_test_feed' ) );
		add_action( 'wp_ajax_bkx_rwg_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_bkx_rwg_verify_merchant', array( $this, 'ajax_verify_merchant' ) );

		// BookingX hooks for sync.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_updated', array( $this, 'on_booking_updated' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this, 'on_booking_cancelled' ) );

		// Cron events.
		add_action( 'bkx_rwg_sync_availability', array( $this->availability_sync, 'sync_all' ) );
		add_action( 'bkx_rwg_cleanup_slots', array( $this->availability_sync, 'cleanup_old_slots' ) );

		// Schedule cron if not already scheduled.
		if ( ! wp_next_scheduled( 'bkx_rwg_sync_availability' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_rwg_sync_availability' );
		}

		if ( ! wp_next_scheduled( 'bkx_rwg_cleanup_slots' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_rwg_cleanup_slots' );
		}

		// Settings link.
		add_filter( 'plugin_action_links_' . BKX_RESERVE_GOOGLE_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Reserve with Google', 'bkx-reserve-google' ),
			__( 'Reserve with Google', 'bkx-reserve-google' ),
			'manage_options',
			'bkx-reserve-google',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-reserve-google' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-reserve-google-admin',
			BKX_RESERVE_GOOGLE_URL . 'assets/css/admin.css',
			array(),
			BKX_RESERVE_GOOGLE_VERSION
		);

		wp_enqueue_script(
			'bkx-reserve-google-admin',
			BKX_RESERVE_GOOGLE_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_RESERVE_GOOGLE_VERSION,
			true
		);

		wp_localize_script(
			'bkx-reserve-google-admin',
			'bkxRwg',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_reserve_google' ),
				'i18n'    => array(
					'saved'       => __( 'Settings saved!', 'bkx-reserve-google' ),
					'synced'      => __( 'Services synced successfully!', 'bkx-reserve-google' ),
					'feedValid'   => __( 'Feed is valid!', 'bkx-reserve-google' ),
					'feedInvalid' => __( 'Feed validation failed.', 'bkx-reserve-google' ),
					'verifying'   => __( 'Verifying...', 'bkx-reserve-google' ),
					'verified'    => __( 'Merchant verified!', 'bkx-reserve-google' ),
				),
			)
		);
	}

	/**
	 * Register REST routes for Reserve with Google API.
	 */
	public function register_rest_routes() {
		$namespace = 'bkx-rwg/v2';

		// Health check.
		register_rest_route(
			$namespace,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health_check' ),
				'permission_callback' => array( $this, 'verify_google_request' ),
			)
		);

		// Merchant feed.
		register_rest_route(
			$namespace,
			'/feeds/merchants',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->feed_generator, 'get_merchant_feed' ),
				'permission_callback' => array( $this, 'verify_google_request' ),
			)
		);

		// Services feed.
		register_rest_route(
			$namespace,
			'/feeds/services',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->feed_generator, 'get_services_feed' ),
				'permission_callback' => array( $this, 'verify_google_request' ),
			)
		);

		// Availability feed.
		register_rest_route(
			$namespace,
			'/feeds/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->feed_generator, 'get_availability_feed' ),
				'permission_callback' => array( $this, 'verify_google_request' ),
			)
		);

		// Check availability (real-time).
		register_rest_route(
			$namespace,
			'/CheckAvailability',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->booking_handler, 'check_availability' ),
				'permission_callback' => array( $this, 'verify_google_request' ),
			)
		);

		// Create booking.
		register_rest_route(
			$namespace,
			'/CreateBooking',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->booking_handler, 'create_booking' ),
				'permission_callback' => array( $this, 'verify_google_request' ),
			)
		);

		// Update booking.
		register_rest_route(
			$namespace,
			'/UpdateBooking',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->booking_handler, 'update_booking' ),
				'permission_callback' => array( $this, 'verify_google_request' ),
			)
		);

		// Get booking status.
		register_rest_route(
			$namespace,
			'/GetBookingStatus',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->booking_handler, 'get_booking_status' ),
				'permission_callback' => array( $this, 'verify_google_request' ),
			)
		);

		// List bookings.
		register_rest_route(
			$namespace,
			'/ListBookings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->booking_handler, 'list_bookings' ),
				'permission_callback' => array( $this, 'verify_google_request' ),
			)
		);
	}

	/**
	 * Verify Google request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function verify_google_request( $request ) {
		$settings = get_option( 'bkx_reserve_google_settings', array() );

		// In development mode, skip verification.
		if ( ! empty( $settings['dev_mode'] ) ) {
			return true;
		}

		// Verify API key.
		$api_key = $request->get_header( 'x-goog-api-key' );
		if ( ! empty( $settings['api_key'] ) && $api_key === $settings['api_key'] ) {
			return true;
		}

		// Check basic auth.
		$auth_header = $request->get_header( 'authorization' );
		if ( ! empty( $auth_header ) && ! empty( $settings['api_username'] ) && ! empty( $settings['api_password'] ) ) {
			if ( preg_match( '/Basic\s+(.+)/', $auth_header, $matches ) ) {
				$credentials = base64_decode( $matches[1] );
				$expected    = $settings['api_username'] . ':' . $settings['api_password'];
				if ( $credentials === $expected ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Health check endpoint.
	 *
	 * @return \WP_REST_Response
	 */
	public function health_check() {
		return rest_ensure_response( array(
			'status'    => 'healthy',
			'timestamp' => gmdate( 'c' ),
			'version'   => BKX_RESERVE_GOOGLE_VERSION,
		) );
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification
		include BKX_RESERVE_GOOGLE_PATH . 'templates/admin/page.php';
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_reserve_google', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-reserve-google' ) ) );
		}

		$settings = array(
			'enabled'           => isset( $_POST['enabled'] ) ? 1 : 0,
			'partner_id'        => isset( $_POST['partner_id'] ) ? sanitize_text_field( wp_unslash( $_POST['partner_id'] ) ) : '',
			'merchant_id'       => isset( $_POST['merchant_id'] ) ? sanitize_text_field( wp_unslash( $_POST['merchant_id'] ) ) : '',
			'place_id'          => isset( $_POST['place_id'] ) ? sanitize_text_field( wp_unslash( $_POST['place_id'] ) ) : '',
			'api_key'           => isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '',
			'api_username'      => isset( $_POST['api_username'] ) ? sanitize_text_field( wp_unslash( $_POST['api_username'] ) ) : '',
			'api_password'      => isset( $_POST['api_password'] ) ? sanitize_text_field( wp_unslash( $_POST['api_password'] ) ) : '',
			'business_name'     => isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '',
			'business_address'  => isset( $_POST['business_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['business_address'] ) ) : '',
			'business_phone'    => isset( $_POST['business_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['business_phone'] ) ) : '',
			'business_category' => isset( $_POST['business_category'] ) ? sanitize_text_field( wp_unslash( $_POST['business_category'] ) ) : '',
			'timezone'          => isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['timezone'] ) ) : wp_timezone_string(),
			'advance_booking_days' => isset( $_POST['advance_booking_days'] ) ? absint( $_POST['advance_booking_days'] ) : 30,
			'min_advance_hours' => isset( $_POST['min_advance_hours'] ) ? absint( $_POST['min_advance_hours'] ) : 1,
			'dev_mode'          => isset( $_POST['dev_mode'] ) ? 1 : 0,
		);

		update_option( 'bkx_reserve_google_settings', $settings );

		// Update merchant info.
		$this->merchant_manager->update_merchant( $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'bkx-reserve-google' ) ) );
	}

	/**
	 * AJAX: Sync services.
	 */
	public function ajax_sync_services() {
		check_ajax_referer( 'bkx_reserve_google', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-reserve-google' ) ) );
		}

		$synced = $this->merchant_manager->sync_services();

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: Number of services */
				__( '%d services synced.', 'bkx-reserve-google' ),
				$synced
			),
			'count' => $synced,
		) );
	}

	/**
	 * AJAX: Test feed.
	 */
	public function ajax_test_feed() {
		check_ajax_referer( 'bkx_reserve_google', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-reserve-google' ) ) );
		}

		$feed_type = isset( $_POST['feed_type'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_type'] ) ) : 'merchants';

		$request = new \WP_REST_Request( 'GET', '/bkx-rwg/v2/feeds/' . $feed_type );
		$feed    = null;

		switch ( $feed_type ) {
			case 'merchants':
				$feed = $this->feed_generator->get_merchant_feed( $request );
				break;
			case 'services':
				$feed = $this->feed_generator->get_services_feed( $request );
				break;
			case 'availability':
				$feed = $this->feed_generator->get_availability_feed( $request );
				break;
		}

		if ( $feed instanceof \WP_REST_Response ) {
			$data = $feed->get_data();
			wp_send_json_success( array(
				'feed'  => $data,
				'valid' => ! empty( $data ),
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Invalid feed type.', 'bkx-reserve-google' ) ) );
	}

	/**
	 * AJAX: Get stats.
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'bkx_reserve_google', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-reserve-google' ) ) );
		}

		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bkx_rwg_bookings';
		$services_table = $wpdb->prefix . 'bkx_rwg_services';
		$logs_table     = $wpdb->prefix . 'bkx_rwg_logs';

		$stats = array(
			'total_bookings'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table}" ), // phpcs:ignore
			'confirmed'       => $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'confirmed'" ), // phpcs:ignore
			'cancelled'       => $wpdb->get_var( "SELECT COUNT(*) FROM {$bookings_table} WHERE status = 'cancelled'" ), // phpcs:ignore
			'services_synced' => $wpdb->get_var( "SELECT COUNT(*) FROM {$services_table} WHERE enabled = 1" ), // phpcs:ignore
			'today_bookings'  => $wpdb->get_var( // phpcs:ignore
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$bookings_table} WHERE created_at >= %s",
					gmdate( 'Y-m-d 00:00:00' )
				)
			),
			'api_requests'    => $wpdb->get_var( // phpcs:ignore
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$logs_table} WHERE created_at >= %s",
					gmdate( 'Y-m-d 00:00:00' )
				)
			),
		);

		wp_send_json_success( $stats );
	}

	/**
	 * AJAX: Verify merchant.
	 */
	public function ajax_verify_merchant() {
		check_ajax_referer( 'bkx_reserve_google', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-reserve-google' ) ) );
		}

		$settings = get_option( 'bkx_reserve_google_settings', array() );

		if ( empty( $settings['place_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Place ID is required for verification.', 'bkx-reserve-google' ) ) );
		}

		// In a real implementation, this would verify with Google's API.
		// For now, we'll mark as verified if all required fields are present.
		$required = array( 'business_name', 'business_address', 'business_phone', 'place_id' );
		$missing  = array();

		foreach ( $required as $field ) {
			if ( empty( $settings[ $field ] ) ) {
				$missing[] = $field;
			}
		}

		if ( ! empty( $missing ) ) {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %s: Missing fields */
					__( 'Missing required fields: %s', 'bkx-reserve-google' ),
					implode( ', ', $missing )
				),
			) );
		}

		$this->merchant_manager->verify_merchant( $settings['merchant_id'] ?? '' );

		wp_send_json_success( array( 'message' => __( 'Merchant verified successfully.', 'bkx-reserve-google' ) ) );
	}

	/**
	 * Handle booking created in BookingX.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		// Check if this booking came from Reserve with Google.
		$source = get_post_meta( $booking_id, 'booking_source', true );
		if ( $source === 'reserve_with_google' ) {
			return; // Already synced.
		}

		// Optionally sync local bookings to Reserve with Google.
		// This would require implementing the booking notification API.
	}

	/**
	 * Handle booking updated in BookingX.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_updated( $booking_id, $booking_data ) {
		$this->booking_handler->sync_booking_status( $booking_id );
	}

	/**
	 * Handle booking cancelled in BookingX.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public function on_booking_cancelled( $booking_id ) {
		$this->booking_handler->sync_booking_status( $booking_id );
	}

	/**
	 * Add settings link.
	 *
	 * @param array $links Plugin links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=bkx-reserve-google' ) . '">' . __( 'Settings', 'bkx-reserve-google' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}

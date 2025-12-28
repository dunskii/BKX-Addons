<?php
/**
 * Customer Journey Addon Main Class.
 *
 * @package BookingX\CustomerJourney
 * @since   1.0.0
 */

namespace BookingX\CustomerJourney;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BookingX\CustomerJourney\Services\TouchpointTracker;
use BookingX\CustomerJourney\Services\LifecycleManager;
use BookingX\CustomerJourney\Services\JourneyMapper;
use BookingX\CustomerJourney\Services\AttributionService;

/**
 * CustomerJourneyAddon Class.
 */
class CustomerJourneyAddon {

	/**
	 * Services.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Initialize addon.
	 */
	public function init() {
		$this->init_services();
		$this->register_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['touchpoint_tracker'] = new TouchpointTracker();
		$this->services['lifecycle_manager']  = new LifecycleManager();
		$this->services['journey_mapper']     = new JourneyMapper();
		$this->services['attribution']        = new AttributionService();
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Frontend tracking.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_script' ) );

		// AJAX handlers (admin).
		add_action( 'wp_ajax_bkx_cj_get_journey_overview', array( $this, 'ajax_get_journey_overview' ) );
		add_action( 'wp_ajax_bkx_cj_get_touchpoint_analysis', array( $this, 'ajax_get_touchpoint_analysis' ) );
		add_action( 'wp_ajax_bkx_cj_get_lifecycle_data', array( $this, 'ajax_get_lifecycle_data' ) );
		add_action( 'wp_ajax_bkx_cj_get_customer_profile', array( $this, 'ajax_get_customer_profile' ) );
		add_action( 'wp_ajax_bkx_cj_get_attribution', array( $this, 'ajax_get_attribution' ) );

		// AJAX handlers (frontend tracking).
		add_action( 'wp_ajax_bkx_cj_track', array( $this, 'ajax_track_touchpoint' ) );
		add_action( 'wp_ajax_nopriv_bkx_cj_track', array( $this, 'ajax_track_touchpoint' ) );

		// BookingX hooks.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this, 'on_status_changed' ), 10, 3 );

		// Cron for lifecycle updates.
		add_action( 'bkx_cj_update_lifecycle', array( $this, 'update_all_lifecycles' ) );
		if ( ! wp_next_scheduled( 'bkx_cj_update_lifecycle' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_cj_update_lifecycle' );
		}

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Customer Journey', 'bkx-customer-journey' ),
			__( 'Customer Journey', 'bkx-customer-journey' ),
			'manage_options',
			'bkx-customer-journey',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bookingx_page_bkx-customer-journey' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );

		wp_enqueue_style(
			'bkx-customer-journey-admin',
			BKX_CUSTOMER_JOURNEY_URL . 'assets/css/admin.css',
			array(),
			BKX_CUSTOMER_JOURNEY_VERSION
		);

		wp_enqueue_script(
			'bkx-customer-journey-admin',
			BKX_CUSTOMER_JOURNEY_URL . 'assets/js/admin.js',
			array( 'jquery', 'chartjs', 'wp-util' ),
			BKX_CUSTOMER_JOURNEY_VERSION,
			true
		);

		wp_localize_script(
			'bkx-customer-journey-admin',
			'bkxCJ',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'bkx_cj_admin' ),
				'currencySymbol' => get_option( 'bkx_currency_symbol', '$' ),
				'i18n'           => array(
					'loading'    => __( 'Loading...', 'bkx-customer-journey' ),
					'noData'     => __( 'No data available', 'bkx-customer-journey' ),
					'lead'       => __( 'Lead', 'bkx-customer-journey' ),
					'prospect'   => __( 'Prospect', 'bkx-customer-journey' ),
					'customer'   => __( 'Customer', 'bkx-customer-journey' ),
					'loyal'      => __( 'Loyal', 'bkx-customer-journey' ),
					'champion'   => __( 'Champion', 'bkx-customer-journey' ),
					'at_risk'    => __( 'At Risk', 'bkx-customer-journey' ),
					'churned'    => __( 'Churned', 'bkx-customer-journey' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend tracking script.
	 */
	public function enqueue_tracking_script() {
		if ( ! $this->should_track() ) {
			return;
		}

		wp_enqueue_script(
			'bkx-customer-journey-tracker',
			BKX_CUSTOMER_JOURNEY_URL . 'assets/js/tracker.js',
			array( 'jquery' ),
			BKX_CUSTOMER_JOURNEY_VERSION,
			true
		);

		wp_localize_script(
			'bkx-customer-journey-tracker',
			'bkxCJTracker',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'bkx_cj_track' ),
				'sessionId' => $this->get_session_id(),
			)
		);
	}

	/**
	 * Check if should track.
	 *
	 * @return bool
	 */
	private function should_track() {
		// Don't track admin users or bots.
		if ( is_admin() || current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get or create session ID.
	 *
	 * @return string
	 */
	private function get_session_id() {
		if ( isset( $_COOKIE['bkx_cj_session'] ) ) {
			return sanitize_text_field( $_COOKIE['bkx_cj_session'] );
		}

		$session_id = wp_generate_uuid4();
		setcookie( 'bkx_cj_session', $session_id, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		return $session_id;
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_CUSTOMER_JOURNEY_PATH . 'templates/admin/journey.php';
	}

	/**
	 * AJAX: Get journey overview.
	 */
	public function ajax_get_journey_overview() {
		check_ajax_referer( 'bkx_cj_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$start_date = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end_date   = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );

		$mapper = $this->services['journey_mapper'];
		$data   = $mapper->get_overview( $start_date, $end_date );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get touchpoint analysis.
	 */
	public function ajax_get_touchpoint_analysis() {
		check_ajax_referer( 'bkx_cj_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$start_date = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end_date   = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );

		$tracker = $this->services['touchpoint_tracker'];
		$data    = $tracker->get_analysis( $start_date, $end_date );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get lifecycle data.
	 */
	public function ajax_get_lifecycle_data() {
		check_ajax_referer( 'bkx_cj_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$manager = $this->services['lifecycle_manager'];
		$data    = $manager->get_lifecycle_summary();

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get customer profile.
	 */
	public function ajax_get_customer_profile() {
		check_ajax_referer( 'bkx_cj_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

		$manager = $this->services['lifecycle_manager'];
		$mapper  = $this->services['journey_mapper'];

		$profile = $manager->get_customer_profile( $email );
		$journey = $mapper->get_customer_journey( $email );

		wp_send_json_success( array(
			'profile' => $profile,
			'journey' => $journey,
		) );
	}

	/**
	 * AJAX: Get attribution.
	 */
	public function ajax_get_attribution() {
		check_ajax_referer( 'bkx_cj_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$start_date = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end_date   = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
		$model      = sanitize_text_field( wp_unslash( $_POST['model'] ?? 'first_touch' ) );

		$service = $this->services['attribution'];
		$data    = $service->get_attribution( $start_date, $end_date, $model );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Track touchpoint.
	 */
	public function ajax_track_touchpoint() {
		check_ajax_referer( 'bkx_cj_track', 'nonce' );

		$session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
		$type       = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
		$data       = isset( $_POST['data'] ) ? array_map( 'sanitize_text_field', (array) $_POST['data'] ) : array();
		$page_url   = sanitize_url( wp_unslash( $_POST['page_url'] ?? '' ) );
		$referrer   = sanitize_url( wp_unslash( $_POST['referrer'] ?? '' ) );

		if ( empty( $session_id ) || empty( $type ) ) {
			wp_send_json_error();
		}

		$tracker = $this->services['touchpoint_tracker'];
		$result  = $tracker->track( $session_id, $type, $data, $page_url, $referrer );

		wp_send_json_success( array( 'tracked' => $result ) );
	}

	/**
	 * On booking created.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		$email = $booking_data['customer_email'] ?? '';
		if ( empty( $email ) ) {
			return;
		}

		// Update lifecycle.
		$manager = $this->services['lifecycle_manager'];
		$manager->update_customer_lifecycle( $email, $booking_id );

		// Complete journey.
		$session_id = isset( $_COOKIE['bkx_cj_session'] ) ? sanitize_text_field( $_COOKIE['bkx_cj_session'] ) : '';
		if ( $session_id ) {
			$mapper = $this->services['journey_mapper'];
			$mapper->complete_journey( $session_id, $email, $booking_id );
		}
	}

	/**
	 * On booking status changed.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function on_status_changed( $booking_id, $old_status, $new_status ) {
		$email = get_post_meta( $booking_id, 'customer_email', true );
		if ( ! $email ) {
			return;
		}

		$manager = $this->services['lifecycle_manager'];

		if ( 'bkx-completed' === $new_status ) {
			$manager->update_customer_lifecycle( $email, $booking_id );
		} elseif ( 'bkx-cancelled' === $new_status ) {
			$manager->record_cancellation( $email, $booking_id );
		}
	}

	/**
	 * Update all customer lifecycles.
	 */
	public function update_all_lifecycles() {
		$manager = $this->services['lifecycle_manager'];
		$manager->update_all_lifecycles();
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-cj/v1',
			'/customer/(?P<email>[^/]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_customer' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * REST: Get customer.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_customer( $request ) {
		$email   = sanitize_email( $request->get_param( 'email' ) );
		$manager = $this->services['lifecycle_manager'];
		$profile = $manager->get_customer_profile( $email );

		return rest_ensure_response( $profile );
	}
}

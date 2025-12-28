<?php
/**
 * Staff Performance Analytics Addon main class.
 *
 * @package BookingX\StaffAnalytics
 * @since   1.0.0
 */

namespace BookingX\StaffAnalytics;

use BookingX\StaffAnalytics\Services\PerformanceMetrics;
use BookingX\StaffAnalytics\Services\GoalTracker;
use BookingX\StaffAnalytics\Services\ReviewManager;
use BookingX\StaffAnalytics\Services\TimeTracker;
use BookingX\StaffAnalytics\Services\LeaderboardService;
use BookingX\StaffAnalytics\Services\ReportGenerator;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * StaffAnalyticsAddon Class.
 */
class StaffAnalyticsAddon {

	/**
	 * Instance.
	 *
	 * @var StaffAnalyticsAddon
	 */
	private static $instance = null;

	/**
	 * Services.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get instance.
	 *
	 * @return StaffAnalyticsAddon
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
		$this->services['metrics']     = new PerformanceMetrics();
		$this->services['goals']       = new GoalTracker();
		$this->services['reviews']     = new ReviewManager();
		$this->services['time']        = new TimeTracker();
		$this->services['leaderboard'] = new LeaderboardService();
		$this->services['reports']     = new ReportGenerator();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// BookingX hooks.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this, 'on_booking_status_changed' ), 10, 3 );
		add_action( 'transition_post_status', array( $this, 'on_post_status_change' ), 10, 3 );

		// Cron for daily metrics.
		add_action( 'bkx_staff_daily_metrics', array( $this, 'aggregate_daily_metrics' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_get_staff_metrics', array( $this, 'ajax_get_staff_metrics' ) );
		add_action( 'wp_ajax_bkx_get_leaderboard', array( $this, 'ajax_get_leaderboard' ) );
		add_action( 'wp_ajax_bkx_save_staff_goal', array( $this, 'ajax_save_goal' ) );
		add_action( 'wp_ajax_bkx_delete_staff_goal', array( $this, 'ajax_delete_goal' ) );
		add_action( 'wp_ajax_bkx_submit_review', array( $this, 'ajax_submit_review' ) );
		add_action( 'wp_ajax_nopriv_bkx_submit_review', array( $this, 'ajax_submit_review' ) );
		add_action( 'wp_ajax_bkx_approve_review', array( $this, 'ajax_approve_review' ) );
		add_action( 'wp_ajax_bkx_clock_in', array( $this, 'ajax_clock_in' ) );
		add_action( 'wp_ajax_bkx_clock_out', array( $this, 'ajax_clock_out' ) );
		add_action( 'wp_ajax_bkx_export_staff_report', array( $this, 'ajax_export_report' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Add staff profile tab.
		add_filter( 'bkx_seat_edit_tabs', array( $this, 'add_staff_analytics_tab' ) );
		add_action( 'bkx_seat_edit_tab_analytics', array( $this, 'render_staff_analytics_tab' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Staff Analytics', 'bkx-staff-analytics' ),
			__( 'Staff Analytics', 'bkx-staff-analytics' ),
			'manage_options',
			'bkx-staff-analytics',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bkx_booking_page_bkx-staff-analytics' !== $hook ) {
			return;
		}

		// Chart.js.
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
			array(),
			'4.4.1',
			true
		);

		wp_enqueue_style(
			'bkx-staff-analytics-admin',
			BKX_STAFF_ANALYTICS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_STAFF_ANALYTICS_VERSION
		);

		wp_enqueue_script(
			'bkx-staff-analytics-admin',
			BKX_STAFF_ANALYTICS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'chartjs' ),
			BKX_STAFF_ANALYTICS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-staff-analytics-admin',
			'bkxStaffAnalytics',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_staff_analytics' ),
				'i18n'    => array(
					'loading'      => __( 'Loading...', 'bkx-staff-analytics' ),
					'error'        => __( 'An error occurred', 'bkx-staff-analytics' ),
					'confirmDelete' => __( 'Are you sure you want to delete this goal?', 'bkx-staff-analytics' ),
					'saved'        => __( 'Saved successfully', 'bkx-staff-analytics' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		require_once BKX_STAFF_ANALYTICS_PLUGIN_DIR . 'templates/admin/analytics.php';
	}

	/**
	 * Handle booking created.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		$this->services['metrics']->record_booking( $booking_id );
	}

	/**
	 * Handle booking status changed.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 */
	public function on_booking_status_changed( $booking_id, $new_status, $old_status ) {
		$this->services['metrics']->update_booking_status( $booking_id, $new_status, $old_status );
	}

	/**
	 * Handle post status change for bookings.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 */
	public function on_post_status_change( $new_status, $old_status, $post ) {
		if ( 'bkx_booking' !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		$this->services['metrics']->update_booking_status( $post->ID, $new_status, $old_status );
	}

	/**
	 * Aggregate daily metrics.
	 */
	public function aggregate_daily_metrics() {
		$this->services['metrics']->aggregate_metrics( gmdate( 'Y-m-d', strtotime( '-1 day' ) ) );
	}

	/**
	 * AJAX: Get staff metrics.
	 */
	public function ajax_get_staff_metrics() {
		check_ajax_referer( 'bkx_staff_analytics', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-staff-analytics' ) ) );
		}

		$staff_id = isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : 0;
		$period   = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : 'month';

		$metrics = $this->services['metrics']->get_staff_metrics( $staff_id, $period );

		wp_send_json_success( $metrics );
	}

	/**
	 * AJAX: Get leaderboard.
	 */
	public function ajax_get_leaderboard() {
		check_ajax_referer( 'bkx_staff_analytics', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-staff-analytics' ) ) );
		}

		$metric = isset( $_POST['metric'] ) ? sanitize_text_field( wp_unslash( $_POST['metric'] ) ) : 'revenue';
		$period = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : 'month';
		$limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 10;

		$leaderboard = $this->services['leaderboard']->get_rankings( $metric, $period, $limit );

		wp_send_json_success( $leaderboard );
	}

	/**
	 * AJAX: Save goal.
	 */
	public function ajax_save_goal() {
		check_ajax_referer( 'bkx_staff_analytics', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-staff-analytics' ) ) );
		}

		$goal_data = array(
			'id'           => isset( $_POST['goal_id'] ) ? absint( $_POST['goal_id'] ) : 0,
			'staff_id'     => isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : 0,
			'goal_type'    => isset( $_POST['goal_type'] ) ? sanitize_text_field( wp_unslash( $_POST['goal_type'] ) ) : '',
			'goal_period'  => isset( $_POST['goal_period'] ) ? sanitize_text_field( wp_unslash( $_POST['goal_period'] ) ) : 'monthly',
			'target_value' => isset( $_POST['target_value'] ) ? floatval( $_POST['target_value'] ) : 0,
			'start_date'   => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
			'end_date'     => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '',
		);

		$result = $this->services['goals']->save_goal( $goal_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'goal_id' => $result ) );
	}

	/**
	 * AJAX: Delete goal.
	 */
	public function ajax_delete_goal() {
		check_ajax_referer( 'bkx_staff_analytics', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-staff-analytics' ) ) );
		}

		$goal_id = isset( $_POST['goal_id'] ) ? absint( $_POST['goal_id'] ) : 0;

		$result = $this->services['goals']->delete_goal( $goal_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete goal', 'bkx-staff-analytics' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Submit review.
	 */
	public function ajax_submit_review() {
		check_ajax_referer( 'bkx_staff_analytics', 'nonce' );

		$review_data = array(
			'staff_id'    => isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : 0,
			'booking_id'  => isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0,
			'customer_id' => get_current_user_id(),
			'rating'      => isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0,
			'review_text' => isset( $_POST['review_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_text'] ) ) : '',
		);

		$result = $this->services['reviews']->submit_review( $review_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'review_id' => $result ) );
	}

	/**
	 * AJAX: Approve review.
	 */
	public function ajax_approve_review() {
		check_ajax_referer( 'bkx_staff_analytics', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-staff-analytics' ) ) );
		}

		$review_id = isset( $_POST['review_id'] ) ? absint( $_POST['review_id'] ) : 0;
		$approved  = isset( $_POST['approved'] ) ? (bool) $_POST['approved'] : true;

		$result = $this->services['reviews']->set_approval( $review_id, $approved );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update review', 'bkx-staff-analytics' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Clock in.
	 */
	public function ajax_clock_in() {
		check_ajax_referer( 'bkx_staff_analytics', 'nonce' );

		$staff_id = isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : 0;
		$notes    = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		// Verify user is the staff member or admin.
		if ( ! current_user_can( 'manage_options' ) ) {
			$seat_user = get_post_meta( $staff_id, 'seat_user_id', true );
			if ( absint( $seat_user ) !== get_current_user_id() ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-staff-analytics' ) ) );
			}
		}

		$result = $this->services['time']->clock_in( $staff_id, $notes );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'log_id' => $result ) );
	}

	/**
	 * AJAX: Clock out.
	 */
	public function ajax_clock_out() {
		check_ajax_referer( 'bkx_staff_analytics', 'nonce' );

		$staff_id      = isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : 0;
		$break_minutes = isset( $_POST['break_minutes'] ) ? absint( $_POST['break_minutes'] ) : 0;

		// Verify user is the staff member or admin.
		if ( ! current_user_can( 'manage_options' ) ) {
			$seat_user = get_post_meta( $staff_id, 'seat_user_id', true );
			if ( absint( $seat_user ) !== get_current_user_id() ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-staff-analytics' ) ) );
			}
		}

		$result = $this->services['time']->clock_out( $staff_id, $break_minutes );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'hours' => $result ) );
	}

	/**
	 * AJAX: Export report.
	 */
	public function ajax_export_report() {
		check_ajax_referer( 'bkx_staff_analytics', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-staff-analytics' ) ) );
		}

		$staff_id   = isset( $_POST['staff_id'] ) ? absint( $_POST['staff_id'] ) : 0;
		$report_type = isset( $_POST['report_type'] ) ? sanitize_text_field( wp_unslash( $_POST['report_type'] ) ) : 'performance';
		$format     = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'csv';
		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';

		$url = $this->services['reports']->generate_export( $staff_id, $report_type, $format, $start_date, $end_date );

		if ( ! $url ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate report', 'bkx-staff-analytics' ) ) );
		}

		wp_send_json_success( array( 'download_url' => $url ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-staff-analytics/v1',
			'/metrics/(?P<staff_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_metrics' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'staff_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'period'   => array(
						'default'           => 'month',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'bkx-staff-analytics/v1',
			'/leaderboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_leaderboard' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'metric' => array(
						'default'           => 'revenue',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'period' => array(
						'default'           => 'month',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'  => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * REST: Get metrics.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_metrics( $request ) {
		$staff_id = $request->get_param( 'staff_id' );
		$period   = $request->get_param( 'period' );

		$metrics = $this->services['metrics']->get_staff_metrics( $staff_id, $period );

		return rest_ensure_response( $metrics );
	}

	/**
	 * REST: Get leaderboard.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_leaderboard( $request ) {
		$metric = $request->get_param( 'metric' );
		$period = $request->get_param( 'period' );
		$limit  = $request->get_param( 'limit' );

		$leaderboard = $this->services['leaderboard']->get_rankings( $metric, $period, $limit );

		return rest_ensure_response( $leaderboard );
	}

	/**
	 * Add staff analytics tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_staff_analytics_tab( $tabs ) {
		$tabs['analytics'] = __( 'Performance Analytics', 'bkx-staff-analytics' );
		return $tabs;
	}

	/**
	 * Render staff analytics tab.
	 *
	 * @param int $staff_id Staff ID.
	 */
	public function render_staff_analytics_tab( $staff_id ) {
		$metrics = $this->services['metrics']->get_staff_metrics( $staff_id, 'month' );
		$goals   = $this->services['goals']->get_staff_goals( $staff_id );
		$reviews = $this->services['reviews']->get_staff_reviews( $staff_id, array( 'limit' => 5 ) );

		require_once BKX_STAFF_ANALYTICS_PLUGIN_DIR . 'templates/admin/staff-tab.php';
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
}

<?php
/**
 * Advanced Analytics Addon Main Class.
 *
 * @package BookingX\AdvancedAnalytics
 * @since   1.0.0
 */

namespace BookingX\AdvancedAnalytics;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BookingX\AdvancedAnalytics\Services\BookingAnalyzer;
use BookingX\AdvancedAnalytics\Services\CohortAnalyzer;
use BookingX\AdvancedAnalytics\Services\ComparisonService;
use BookingX\AdvancedAnalytics\Services\SegmentationService;
use BookingX\AdvancedAnalytics\Services\PatternDetector;

/**
 * AdvancedAnalyticsAddon Class.
 */
class AdvancedAnalyticsAddon {

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
		$this->services['booking_analyzer']   = new BookingAnalyzer();
		$this->services['cohort_analyzer']    = new CohortAnalyzer();
		$this->services['comparison']         = new ComparisonService();
		$this->services['segmentation']       = new SegmentationService();
		$this->services['pattern_detector']   = new PatternDetector();
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_aa_get_booking_analysis', array( $this, 'ajax_get_booking_analysis' ) );
		add_action( 'wp_ajax_bkx_aa_get_cohort_analysis', array( $this, 'ajax_get_cohort_analysis' ) );
		add_action( 'wp_ajax_bkx_aa_get_comparison', array( $this, 'ajax_get_comparison' ) );
		add_action( 'wp_ajax_bkx_aa_get_segments', array( $this, 'ajax_get_segments' ) );
		add_action( 'wp_ajax_bkx_aa_get_patterns', array( $this, 'ajax_get_patterns' ) );
		add_action( 'wp_ajax_bkx_aa_save_analysis', array( $this, 'ajax_save_analysis' ) );
		add_action( 'wp_ajax_bkx_aa_export', array( $this, 'ajax_export' ) );

		// Track booking events.
		add_action( 'bkx_booking_created', array( $this, 'track_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this, 'track_status_change' ), 10, 3 );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Cron for cohort processing.
		add_action( 'bkx_aa_process_cohorts', array( $this, 'process_cohorts' ) );
		if ( ! wp_next_scheduled( 'bkx_aa_process_cohorts' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_aa_process_cohorts' );
		}
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Advanced Analytics', 'bkx-advanced-analytics' ),
			__( 'Advanced Analytics', 'bkx-advanced-analytics' ),
			'manage_options',
			'bkx-advanced-analytics',
			array( $this, 'render_analytics_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bookingx_page_bkx-advanced-analytics' !== $hook ) {
			return;
		}

		// Chart.js.
		wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );

		// Main CSS.
		wp_enqueue_style(
			'bkx-advanced-analytics',
			BKX_ADVANCED_ANALYTICS_URL . 'assets/css/admin.css',
			array(),
			BKX_ADVANCED_ANALYTICS_VERSION
		);

		// Main JS.
		wp_enqueue_script(
			'bkx-advanced-analytics',
			BKX_ADVANCED_ANALYTICS_URL . 'assets/js/admin.js',
			array( 'jquery', 'chartjs', 'wp-util' ),
			BKX_ADVANCED_ANALYTICS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-advanced-analytics',
			'bkxAA',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'bkx_aa_admin' ),
				'currencySymbol' => get_option( 'bkx_currency_symbol', '$' ),
				'i18n'           => array(
					'loading'        => __( 'Loading...', 'bkx-advanced-analytics' ),
					'noData'         => __( 'No data available', 'bkx-advanced-analytics' ),
					'analyzing'      => __( 'Analyzing...', 'bkx-advanced-analytics' ),
					'exportSuccess'  => __( 'Export successful', 'bkx-advanced-analytics' ),
					'saveSuccess'    => __( 'Analysis saved', 'bkx-advanced-analytics' ),
					'confirmDelete'  => __( 'Are you sure?', 'bkx-advanced-analytics' ),
					'revenue'        => __( 'Revenue', 'bkx-advanced-analytics' ),
					'bookings'       => __( 'Bookings', 'bkx-advanced-analytics' ),
					'customers'      => __( 'Customers', 'bkx-advanced-analytics' ),
					'retention'      => __( 'Retention', 'bkx-advanced-analytics' ),
					'growth'         => __( 'Growth', 'bkx-advanced-analytics' ),
				),
			)
		);
	}

	/**
	 * Render analytics page.
	 */
	public function render_analytics_page() {
		include BKX_ADVANCED_ANALYTICS_PATH . 'templates/admin/analytics.php';
	}

	/**
	 * AJAX: Get booking analysis.
	 */
	public function ajax_get_booking_analysis() {
		check_ajax_referer( 'bkx_aa_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-analytics' ) ) );
		}

		$start_date    = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end_date      = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
		$analysis_type = sanitize_text_field( wp_unslash( $_POST['analysis_type'] ?? 'overview' ) );

		$analyzer = $this->services['booking_analyzer'];

		switch ( $analysis_type ) {
			case 'conversion':
				$data = $analyzer->analyze_conversion_funnel( $start_date, $end_date );
				break;
			case 'timing':
				$data = $analyzer->analyze_booking_timing( $start_date, $end_date );
				break;
			case 'duration':
				$data = $analyzer->analyze_booking_duration( $start_date, $end_date );
				break;
			case 'cancellation':
				$data = $analyzer->analyze_cancellations( $start_date, $end_date );
				break;
			default:
				$data = $analyzer->get_overview( $start_date, $end_date );
		}

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get cohort analysis.
	 */
	public function ajax_get_cohort_analysis() {
		check_ajax_referer( 'bkx_aa_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-analytics' ) ) );
		}

		$cohort_type = sanitize_text_field( wp_unslash( $_POST['cohort_type'] ?? 'monthly' ) );
		$metric      = sanitize_text_field( wp_unslash( $_POST['metric'] ?? 'retention' ) );
		$periods     = absint( $_POST['periods'] ?? 12 );

		$analyzer = $this->services['cohort_analyzer'];
		$data     = $analyzer->get_cohort_analysis( $cohort_type, $metric, $periods );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get comparison.
	 */
	public function ajax_get_comparison() {
		check_ajax_referer( 'bkx_aa_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-analytics' ) ) );
		}

		$period_a_start = sanitize_text_field( wp_unslash( $_POST['period_a_start'] ?? '' ) );
		$period_a_end   = sanitize_text_field( wp_unslash( $_POST['period_a_end'] ?? '' ) );
		$period_b_start = sanitize_text_field( wp_unslash( $_POST['period_b_start'] ?? '' ) );
		$period_b_end   = sanitize_text_field( wp_unslash( $_POST['period_b_end'] ?? '' ) );
		$dimensions     = isset( $_POST['dimensions'] ) ? array_map( 'sanitize_text_field', (array) $_POST['dimensions'] ) : array( 'revenue', 'bookings' );

		$service = $this->services['comparison'];
		$data    = $service->compare_periods(
			array(
				'start' => $period_a_start,
				'end'   => $period_a_end,
			),
			array(
				'start' => $period_b_start,
				'end'   => $period_b_end,
			),
			$dimensions
		);

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get segments.
	 */
	public function ajax_get_segments() {
		check_ajax_referer( 'bkx_aa_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-analytics' ) ) );
		}

		$segment_type = sanitize_text_field( wp_unslash( $_POST['segment_type'] ?? 'customer' ) );
		$start_date   = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end_date     = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );

		$service = $this->services['segmentation'];

		switch ( $segment_type ) {
			case 'rfm':
				$data = $service->get_rfm_segments( $start_date, $end_date );
				break;
			case 'value':
				$data = $service->get_value_segments( $start_date, $end_date );
				break;
			case 'behavior':
				$data = $service->get_behavioral_segments( $start_date, $end_date );
				break;
			default:
				$data = $service->get_customer_segments( $start_date, $end_date );
		}

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get patterns.
	 */
	public function ajax_get_patterns() {
		check_ajax_referer( 'bkx_aa_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-analytics' ) ) );
		}

		$pattern_type = sanitize_text_field( wp_unslash( $_POST['pattern_type'] ?? 'seasonal' ) );
		$start_date   = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end_date     = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );

		$detector = $this->services['pattern_detector'];

		switch ( $pattern_type ) {
			case 'anomaly':
				$data = $detector->detect_anomalies( $start_date, $end_date );
				break;
			case 'trend':
				$data = $detector->detect_trends( $start_date, $end_date );
				break;
			default:
				$data = $detector->detect_seasonal_patterns( $start_date, $end_date );
		}

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Save analysis.
	 */
	public function ajax_save_analysis() {
		check_ajax_referer( 'bkx_aa_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-analytics' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_aa_analyses';

		$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$type    = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
		$config  = isset( $_POST['config'] ) ? wp_json_encode( $_POST['config'] ) : '{}';
		$results = isset( $_POST['results'] ) ? wp_json_encode( $_POST['results'] ) : '{}';

		$result = $wpdb->insert(
			$table,
			array(
				'analysis_name'    => $name,
				'analysis_type'    => $type,
				'analysis_config'  => $config,
				'analysis_results' => $results,
				'created_by'       => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		if ( $result ) {
			wp_send_json_success( array( 'id' => $wpdb->insert_id ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save analysis.', 'bkx-advanced-analytics' ) ) );
		}
	}

	/**
	 * AJAX: Export data.
	 */
	public function ajax_export() {
		check_ajax_referer( 'bkx_aa_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-analytics' ) ) );
		}

		$data   = isset( $_POST['data'] ) ? json_decode( stripslashes( $_POST['data'] ), true ) : array();
		$format = sanitize_text_field( wp_unslash( $_POST['format'] ?? 'csv' ) );

		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/bkx-aa-exports/';

		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		$filename = 'analysis-' . gmdate( 'Y-m-d-His' ) . '.' . $format;
		$filepath = $export_dir . $filename;

		if ( 'csv' === $format ) {
			$this->export_csv( $data, $filepath );
		} elseif ( 'json' === $format ) {
			file_put_contents( $filepath, wp_json_encode( $data, JSON_PRETTY_PRINT ) );
		}

		$url = $upload_dir['baseurl'] . '/bkx-aa-exports/' . $filename;

		wp_send_json_success( array( 'url' => $url ) );
	}

	/**
	 * Export to CSV.
	 *
	 * @param array  $data     Data to export.
	 * @param string $filepath File path.
	 */
	private function export_csv( $data, $filepath ) {
		$handle = fopen( $filepath, 'w' );

		// Add BOM for Excel.
		fwrite( $handle, "\xEF\xBB\xBF" );

		if ( ! empty( $data ) && is_array( $data ) ) {
			// Write headers from first row keys.
			$first_row = reset( $data );
			if ( is_array( $first_row ) ) {
				fputcsv( $handle, array_keys( $first_row ) );

				// Write data rows.
				foreach ( $data as $row ) {
					fputcsv( $handle, array_values( $row ) );
				}
			}
		}

		fclose( $handle );
	}

	/**
	 * Track booking created event.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function track_booking_created( $booking_id, $booking_data ) {
		$this->log_event(
			'booking_created',
			$booking_id,
			array(
				'service_id'  => $booking_data['base_id'] ?? null,
				'staff_id'    => $booking_data['seat_id'] ?? null,
				'customer_id' => $booking_data['customer_id'] ?? null,
				'total'       => $booking_data['booking_total'] ?? 0,
				'source'      => $booking_data['booking_source'] ?? 'direct',
			)
		);
	}

	/**
	 * Track status change event.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function track_status_change( $booking_id, $old_status, $new_status ) {
		$this->log_event(
			'status_changed',
			$booking_id,
			array(
				'old_status' => $old_status,
				'new_status' => $new_status,
			)
		);
	}

	/**
	 * Log analytics event.
	 *
	 * @param string   $event_type Event type.
	 * @param int|null $booking_id Booking ID.
	 * @param array    $data       Event data.
	 */
	private function log_event( $event_type, $booking_id, $data = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_aa_events';

		$wpdb->insert(
			$table,
			array(
				'event_type'  => $event_type,
				'booking_id'  => $booking_id,
				'customer_id' => $data['customer_id'] ?? null,
				'service_id'  => $data['service_id'] ?? null,
				'staff_id'    => $data['staff_id'] ?? null,
				'event_data'  => wp_json_encode( $data ),
				'event_date'  => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Process cohorts (cron job).
	 */
	public function process_cohorts() {
		$analyzer = $this->services['cohort_analyzer'];
		$analyzer->build_cohorts();
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-aa/v1',
			'/analysis',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_analysis' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * REST: Get analysis.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_analysis( $request ) {
		$type       = $request->get_param( 'type' );
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$analyzer = $this->services['booking_analyzer'];
		$data     = $analyzer->get_overview( $start_date, $end_date );

		return rest_ensure_response( $data );
	}
}

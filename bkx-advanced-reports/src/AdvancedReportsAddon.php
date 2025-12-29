<?php
/**
 * Main Advanced Booking Reports Addon Class.
 *
 * @package BookingX\AdvancedReports
 * @since   1.0.0
 */

namespace BookingX\AdvancedReports;

use BookingX\AdvancedReports\Services\RevenueReports;
use BookingX\AdvancedReports\Services\BookingReports;
use BookingX\AdvancedReports\Services\StaffReports;
use BookingX\AdvancedReports\Services\CustomerReports;
use BookingX\AdvancedReports\Services\SnapshotManager;
use BookingX\AdvancedReports\Services\ExportManager;

/**
 * AdvancedReportsAddon class.
 *
 * @since 1.0.0
 */
class AdvancedReportsAddon {

	/**
	 * Singleton instance.
	 *
	 * @var AdvancedReportsAddon
	 */
	private static $instance = null;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Services container.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return AdvancedReportsAddon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->settings = get_option( 'bkx_advanced_reports_settings', array() );
	}

	/**
	 * Initialize the addon.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		$this->init_services();
		$this->register_hooks();
	}

	/**
	 * Initialize services.
	 *
	 * @since 1.0.0
	 */
	private function init_services() {
		$this->services['revenue']   = new RevenueReports( $this->settings );
		$this->services['bookings']  = new BookingReports( $this->settings );
		$this->services['staff']     = new StaffReports( $this->settings );
		$this->services['customers'] = new CustomerReports( $this->settings );
		$this->services['snapshots'] = new SnapshotManager( $this->settings );
		$this->services['exports']   = new ExportManager( $this->settings );
	}

	/**
	 * Get a service.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks() {
		// Admin.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Dashboard widget.
		if ( ! empty( $this->settings['show_dashboard_widget'] ) ) {
			add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		}

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_get_report_data', array( $this, 'ajax_get_report_data' ) );
		add_action( 'wp_ajax_bkx_export_report', array( $this, 'ajax_export_report' ) );
		add_action( 'wp_ajax_bkx_get_export_status', array( $this, 'ajax_get_export_status' ) );
		add_action( 'wp_ajax_bkx_download_export', array( $this, 'ajax_download_export' ) );
		add_action( 'wp_ajax_bkx_save_report', array( $this, 'ajax_save_report' ) );
		add_action( 'wp_ajax_bkx_get_saved_reports', array( $this, 'ajax_get_saved_reports' ) );
		add_action( 'wp_ajax_bkx_delete_saved_report', array( $this, 'ajax_delete_saved_report' ) );
		add_action( 'wp_ajax_bkx_save_widget_config', array( $this, 'ajax_save_widget_config' ) );
		add_action( 'wp_ajax_bkx_save_report_settings', array( $this, 'ajax_save_settings' ) );

		// Cron events.
		add_action( 'bkx_generate_daily_snapshots', array( $this->services['snapshots'], 'generate_daily' ) );
		add_action( 'bkx_cleanup_old_exports', array( $this->services['exports'], 'cleanup_old_exports' ) );
		add_action( 'bkx_send_scheduled_reports', array( $this, 'send_scheduled_reports' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Reports & Analytics', 'bkx-advanced-reports' ),
			__( 'Reports', 'bkx-advanced-reports' ),
			'manage_options',
			'bkx-reports',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Dashboard widget styles.
		if ( 'index.php' === $hook && ! empty( $this->settings['show_dashboard_widget'] ) ) {
			wp_enqueue_style(
				'bkx-reports-widget',
				BKX_ADVANCED_REPORTS_URL . 'assets/css/widget.css',
				array(),
				BKX_ADVANCED_REPORTS_VERSION
			);
		}

		if ( 'bkx_booking_page_bkx-reports' !== $hook ) {
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

		// Date range picker.
		wp_enqueue_style(
			'daterangepicker',
			'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css',
			array(),
			'3.1'
		);

		wp_enqueue_script(
			'moment',
			'https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js',
			array(),
			'2.29.4',
			true
		);

		wp_enqueue_script(
			'daterangepicker',
			'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js',
			array( 'jquery', 'moment' ),
			'3.1',
			true
		);

		wp_enqueue_style(
			'bkx-advanced-reports-admin',
			BKX_ADVANCED_REPORTS_URL . 'assets/css/admin.css',
			array(),
			BKX_ADVANCED_REPORTS_VERSION
		);

		wp_enqueue_script(
			'bkx-advanced-reports-admin',
			BKX_ADVANCED_REPORTS_URL . 'assets/js/admin.js',
			array( 'jquery', 'chartjs', 'daterangepicker', 'wp-util' ),
			BKX_ADVANCED_REPORTS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-advanced-reports-admin',
			'bkxReports',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'bkx_reports_nonce' ),
				'settings'    => $this->settings,
				'chartColors' => $this->settings['chart_colors'] ?? array( '#0073aa', '#00a0d2', '#46b450', '#ffb900', '#dc3232' ),
				'currency'    => array(
					'symbol'   => $this->get_currency_symbol(),
					'position' => $this->settings['currency_position'] ?? 'before',
				),
				'dateFormat'  => get_option( 'date_format' ),
				'i18n'        => array(
					'loading'     => __( 'Loading...', 'bkx-advanced-reports' ),
					'noData'      => __( 'No data available for the selected period.', 'bkx-advanced-reports' ),
					'exporting'   => __( 'Exporting...', 'bkx-advanced-reports' ),
					'exported'    => __( 'Export complete!', 'bkx-advanced-reports' ),
					'error'       => __( 'An error occurred.', 'bkx-advanced-reports' ),
					'saved'       => __( 'Saved!', 'bkx-advanced-reports' ),
					'confirmDelete' => __( 'Are you sure you want to delete this report?', 'bkx-advanced-reports' ),
				),
			)
		);
	}

	/**
	 * Add dashboard widget.
	 *
	 * @since 1.0.0
	 */
	public function add_dashboard_widget() {
		wp_add_dashboard_widget(
			'bkx_reports_widget',
			__( 'Booking Overview', 'bkx-advanced-reports' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget.
	 *
	 * @since 1.0.0
	 */
	public function render_dashboard_widget() {
		$overview = $this->services['bookings']->get_overview( 'today' );
		include BKX_ADVANCED_REPORTS_PATH . 'templates/admin/widget.php';
	}

	/**
	 * Render admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {
		include BKX_ADVANCED_REPORTS_PATH . 'templates/admin/reports.php';
	}

	/**
	 * AJAX: Get report data.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_report_data() {
		check_ajax_referer( 'bkx_reports_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-reports' ) ) );
		}

		$report_type = isset( $_POST['report_type'] ) ? sanitize_text_field( wp_unslash( $_POST['report_type'] ) ) : 'overview';
		$date_from   = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to     = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
		$filters     = isset( $_POST['filters'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['filters'] ) ) : array();

		// Get data based on report type.
		$data = $this->get_report_data( $report_type, $date_from, $date_to, $filters );

		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ) );
		}

		wp_send_json_success( $data );
	}

	/**
	 * Get report data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $report_type Report type.
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @param array  $filters Additional filters.
	 * @return array|\WP_Error
	 */
	public function get_report_data( $report_type, $date_from, $date_to, $filters = array() ) {
		// Check cache first.
		if ( ! empty( $this->settings['enable_caching'] ) ) {
			$cache_key = 'bkx_report_' . md5( $report_type . $date_from . $date_to . wp_json_encode( $filters ) );
			$cached    = get_transient( $cache_key );

			if ( false !== $cached ) {
				return $cached;
			}
		}

		switch ( $report_type ) {
			case 'overview':
				$data = $this->get_overview_report( $date_from, $date_to );
				break;

			case 'revenue':
				$data = $this->services['revenue']->get_report( $date_from, $date_to, $filters );
				break;

			case 'bookings':
				$data = $this->services['bookings']->get_report( $date_from, $date_to, $filters );
				break;

			case 'staff':
				$data = $this->services['staff']->get_report( $date_from, $date_to, $filters );
				break;

			case 'customers':
				$data = $this->services['customers']->get_report( $date_from, $date_to, $filters );
				break;

			case 'services':
				$data = $this->services['bookings']->get_services_report( $date_from, $date_to, $filters );
				break;

			default:
				return new \WP_Error( 'invalid_type', __( 'Invalid report type.', 'bkx-advanced-reports' ) );
		}

		// Cache the result.
		if ( ! empty( $this->settings['enable_caching'] ) && ! is_wp_error( $data ) ) {
			$cache_hours = $this->settings['cache_duration_hours'] ?? 1;
			set_transient( $cache_key, $data, $cache_hours * HOUR_IN_SECONDS );
		}

		return $data;
	}

	/**
	 * Get overview report.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	private function get_overview_report( $date_from, $date_to ) {
		$revenue_data  = $this->services['revenue']->get_summary( $date_from, $date_to );
		$booking_data  = $this->services['bookings']->get_summary( $date_from, $date_to );
		$staff_data    = $this->services['staff']->get_top_performers( $date_from, $date_to, 5 );
		$customer_data = $this->services['customers']->get_summary( $date_from, $date_to );

		// Get comparison data.
		$days_diff      = ( strtotime( $date_to ) - strtotime( $date_from ) ) / DAY_IN_SECONDS;
		$prev_date_from = gmdate( 'Y-m-d', strtotime( $date_from . " -{$days_diff} days" ) );
		$prev_date_to   = gmdate( 'Y-m-d', strtotime( $date_from . ' -1 day' ) );

		$prev_revenue = $this->services['revenue']->get_summary( $prev_date_from, $prev_date_to );
		$prev_booking = $this->services['bookings']->get_summary( $prev_date_from, $prev_date_to );

		return array(
			'summary'     => array(
				'revenue'        => $revenue_data,
				'bookings'       => $booking_data,
				'customers'      => $customer_data,
				'revenue_change' => $this->calculate_change( $revenue_data['total'] ?? 0, $prev_revenue['total'] ?? 0 ),
				'booking_change' => $this->calculate_change( $booking_data['total'] ?? 0, $prev_booking['total'] ?? 0 ),
			),
			'charts'      => array(
				'revenue_trend' => $this->services['revenue']->get_trend( $date_from, $date_to ),
				'booking_trend' => $this->services['bookings']->get_trend( $date_from, $date_to ),
				'status_breakdown' => $this->services['bookings']->get_status_breakdown( $date_from, $date_to ),
			),
			'top_staff'   => $staff_data,
			'top_services' => $this->services['bookings']->get_top_services( $date_from, $date_to, 5 ),
		);
	}

	/**
	 * Calculate percentage change.
	 *
	 * @since 1.0.0
	 *
	 * @param float $current Current value.
	 * @param float $previous Previous value.
	 * @return array
	 */
	private function calculate_change( $current, $previous ) {
		if ( 0 === (float) $previous ) {
			return array(
				'value'     => $current > 0 ? 100 : 0,
				'direction' => $current > 0 ? 'up' : 'neutral',
			);
		}

		$change = ( ( $current - $previous ) / $previous ) * 100;

		return array(
			'value'     => round( abs( $change ), 1 ),
			'direction' => $change > 0 ? 'up' : ( $change < 0 ? 'down' : 'neutral' ),
		);
	}

	/**
	 * AJAX: Export report.
	 *
	 * @since 1.0.0
	 */
	public function ajax_export_report() {
		check_ajax_referer( 'bkx_reports_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-reports' ) ) );
		}

		$report_type = isset( $_POST['report_type'] ) ? sanitize_text_field( wp_unslash( $_POST['report_type'] ) ) : '';
		$format      = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'csv';
		$date_from   = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to     = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';
		$filters     = isset( $_POST['filters'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['filters'] ) ) : array();

		$export_id = $this->services['exports']->create_export(
			$report_type,
			$format,
			$date_from,
			$date_to,
			$filters
		);

		if ( is_wp_error( $export_id ) ) {
			wp_send_json_error( array( 'message' => $export_id->get_error_message() ) );
		}

		// Start processing in background.
		$this->services['exports']->process_export( $export_id );

		wp_send_json_success( array( 'export_id' => $export_id ) );
	}

	/**
	 * AJAX: Get export status.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_export_status() {
		check_ajax_referer( 'bkx_reports_nonce', 'nonce' );

		$export_id = isset( $_POST['export_id'] ) ? absint( $_POST['export_id'] ) : 0;
		$status    = $this->services['exports']->get_status( $export_id );

		if ( is_wp_error( $status ) ) {
			wp_send_json_error( array( 'message' => $status->get_error_message() ) );
		}

		wp_send_json_success( $status );
	}

	/**
	 * AJAX: Download export.
	 *
	 * @since 1.0.0
	 */
	public function ajax_download_export() {
		check_ajax_referer( 'bkx_reports_nonce', 'nonce' );

		$export_id = isset( $_GET['export_id'] ) ? absint( $_GET['export_id'] ) : 0;

		$this->services['exports']->download( $export_id );
	}

	/**
	 * AJAX: Save report.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_report() {
		check_ajax_referer( 'bkx_reports_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-reports' ) ) );
		}

		global $wpdb;

		$data = array(
			'user_id'          => get_current_user_id(),
			'name'             => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'report_type'      => isset( $_POST['report_type'] ) ? sanitize_text_field( wp_unslash( $_POST['report_type'] ) ) : '',
			'filters'          => isset( $_POST['filters'] ) ? wp_json_encode( wp_unslash( $_POST['filters'] ) ) : null,
			'columns'          => isset( $_POST['columns'] ) ? wp_json_encode( wp_unslash( $_POST['columns'] ) ) : null,
			'schedule'         => isset( $_POST['schedule'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule'] ) ) : null,
			'email_recipients' => isset( $_POST['email_recipients'] ) ? sanitize_text_field( wp_unslash( $_POST['email_recipients'] ) ) : null,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_saved_reports',
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save report.', 'bkx-advanced-reports' ) ) );
		}

		wp_send_json_success( array( 'id' => $wpdb->insert_id ) );
	}

	/**
	 * AJAX: Get saved reports.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_saved_reports() {
		check_ajax_referer( 'bkx_reports_nonce', 'nonce' );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reports = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE user_id = %d ORDER BY is_favorite DESC, name ASC",
				$wpdb->prefix . 'bkx_saved_reports',
				get_current_user_id()
			)
		);

		wp_send_json_success( array( 'reports' => $reports ) );
	}

	/**
	 * AJAX: Delete saved report.
	 *
	 * @since 1.0.0
	 */
	public function ajax_delete_saved_report() {
		check_ajax_referer( 'bkx_reports_nonce', 'nonce' );

		global $wpdb;

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'bkx_saved_reports',
			array(
				'id'      => $id,
				'user_id' => get_current_user_id(),
			),
			array( '%d', '%d' )
		);

		wp_send_json_success();
	}

	/**
	 * AJAX: Save widget config.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_widget_config() {
		check_ajax_referer( 'bkx_reports_nonce', 'nonce' );

		global $wpdb;

		$widgets = isset( $_POST['widgets'] ) ? wp_unslash( $_POST['widgets'] ) : array();

		// Delete existing.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'bkx_dashboard_widgets',
			array( 'user_id' => get_current_user_id() ),
			array( '%d' )
		);

		// Insert new.
		foreach ( $widgets as $position => $widget ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->prefix . 'bkx_dashboard_widgets',
				array(
					'user_id'       => get_current_user_id(),
					'widget_type'   => sanitize_text_field( $widget['type'] ),
					'widget_config' => wp_json_encode( $widget['config'] ?? array() ),
					'position'      => absint( $position ),
					'is_visible'    => ! empty( $widget['visible'] ) ? 1 : 0,
				),
				array( '%d', '%s', '%s', '%d', '%d' )
			);
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Save settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_reports_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-reports' ) ) );
		}

		$settings = array(
			'default_date_range'      => isset( $_POST['default_date_range'] ) ? sanitize_text_field( wp_unslash( $_POST['default_date_range'] ) ) : '30days',
			'currency'                => isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : 'USD',
			'currency_position'       => isset( $_POST['currency_position'] ) ? sanitize_text_field( wp_unslash( $_POST['currency_position'] ) ) : 'before',
			'decimal_places'          => isset( $_POST['decimal_places'] ) ? absint( $_POST['decimal_places'] ) : 2,
			'thousands_separator'     => isset( $_POST['thousands_separator'] ) ? sanitize_text_field( wp_unslash( $_POST['thousands_separator'] ) ) : ',',
			'decimal_separator'       => isset( $_POST['decimal_separator'] ) ? sanitize_text_field( wp_unslash( $_POST['decimal_separator'] ) ) : '.',
			'chart_colors'            => isset( $_POST['chart_colors'] ) ? array_map( 'sanitize_hex_color', (array) $_POST['chart_colors'] ) : array(),
			'enable_caching'          => isset( $_POST['enable_caching'] ),
			'cache_duration_hours'    => isset( $_POST['cache_duration_hours'] ) ? absint( $_POST['cache_duration_hours'] ) : 1,
			'snapshot_retention_days' => isset( $_POST['snapshot_retention_days'] ) ? absint( $_POST['snapshot_retention_days'] ) : 365,
			'export_retention_days'   => isset( $_POST['export_retention_days'] ) ? absint( $_POST['export_retention_days'] ) : 30,
			'enable_email_reports'    => isset( $_POST['enable_email_reports'] ),
			'email_report_time'       => isset( $_POST['email_report_time'] ) ? sanitize_text_field( wp_unslash( $_POST['email_report_time'] ) ) : '08:00',
			'show_dashboard_widget'   => isset( $_POST['show_dashboard_widget'] ),
			'default_report'          => isset( $_POST['default_report'] ) ? sanitize_text_field( wp_unslash( $_POST['default_report'] ) ) : 'overview',
		);

		update_option( 'bkx_advanced_reports_settings', $settings );
		$this->settings = $settings;

		wp_send_json_success();
	}

	/**
	 * Send scheduled reports.
	 *
	 * @since 1.0.0
	 */
	public function send_scheduled_reports() {
		global $wpdb;

		$now = current_time( 'H:i' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reports = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE schedule IS NOT NULL AND email_recipients IS NOT NULL",
				$wpdb->prefix . 'bkx_saved_reports'
			)
		);

		foreach ( $reports as $report ) {
			$should_send = $this->should_send_scheduled_report( $report );

			if ( $should_send ) {
				$this->send_scheduled_report( $report );
			}
		}
	}

	/**
	 * Check if scheduled report should be sent.
	 *
	 * @since 1.0.0
	 *
	 * @param object $report Report object.
	 * @return bool
	 */
	private function should_send_scheduled_report( $report ) {
		$schedule = $report->schedule;
		$last_run = $report->last_run;

		switch ( $schedule ) {
			case 'daily':
				return empty( $last_run ) || strtotime( $last_run ) < strtotime( 'today' );

			case 'weekly':
				return empty( $last_run ) || strtotime( $last_run ) < strtotime( 'monday this week' );

			case 'monthly':
				return empty( $last_run ) || strtotime( $last_run ) < strtotime( 'first day of this month' );

			default:
				return false;
		}
	}

	/**
	 * Send a scheduled report.
	 *
	 * @since 1.0.0
	 *
	 * @param object $report Report object.
	 */
	private function send_scheduled_report( $report ) {
		global $wpdb;

		// Calculate date range.
		$date_to   = current_time( 'Y-m-d' );
		$date_from = match ( $report->schedule ) {
			'daily'   => gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
			'weekly'  => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
			'monthly' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			default   => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
		};

		// Get report data.
		$filters = json_decode( $report->filters, true ) ?: array();
		$data    = $this->get_report_data( $report->report_type, $date_from, $date_to, $filters );

		if ( is_wp_error( $data ) ) {
			return;
		}

		// Generate PDF.
		$export_id = $this->services['exports']->create_export(
			$report->report_type,
			'pdf',
			$date_from,
			$date_to,
			$filters
		);

		$this->services['exports']->process_export( $export_id );

		// Get export file path.
		$export = $this->services['exports']->get_status( $export_id );

		// Send email.
		$recipients = explode( ',', $report->email_recipients );
		$subject    = sprintf(
			/* translators: %s: Report name */
			__( 'Scheduled Report: %s', 'bkx-advanced-reports' ),
			$report->name
		);

		$message = sprintf(
			/* translators: 1: Report name, 2: Date from, 3: Date to */
			__( "Your scheduled report '%1\$s' for %2\$s to %3\$s is attached.", 'bkx-advanced-reports' ),
			$report->name,
			$date_from,
			$date_to
		);

		$attachments = array();
		if ( ! empty( $export['file_path'] ) && file_exists( $export['file_path'] ) ) {
			$attachments[] = $export['file_path'];
		}

		foreach ( $recipients as $recipient ) {
			wp_mail( trim( $recipient ), $subject, $message, array(), $attachments );
		}

		// Update last run.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'bkx_saved_reports',
			array( 'last_run' => current_time( 'mysql' ) ),
			array( 'id' => $report->id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx/v1',
			'/reports/(?P<type>[a-z]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_report' ),
				'permission_callback' => array( $this, 'rest_check_permissions' ),
				'args'                => array(
					'type'      => array(
						'required' => true,
						'type'     => 'string',
					),
					'date_from' => array( 'type' => 'string' ),
					'date_to'   => array( 'type' => 'string' ),
				),
			)
		);
	}

	/**
	 * REST: Get report.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_report( $request ) {
		$type      = $request->get_param( 'type' );
		$date_from = $request->get_param( 'date_from' ) ?: gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$date_to   = $request->get_param( 'date_to' ) ?: current_time( 'Y-m-d' );

		$data = $this->get_report_data( $type, $date_from, $date_to );

		if ( is_wp_error( $data ) ) {
			return new \WP_REST_Response( array( 'error' => $data->get_error_message() ), 400 );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * REST: Check permissions.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function rest_check_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get currency symbol.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_currency_symbol() {
		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'JPY' => '¥',
			'AUD' => 'A$',
			'CAD' => 'C$',
			'INR' => '₹',
		);

		$currency = $this->settings['currency'] ?? 'USD';
		return $symbols[ $currency ] ?? $currency;
	}
}

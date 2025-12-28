<?php
/**
 * Main Business Intelligence Addon Class.
 *
 * @package BookingX\BusinessIntelligence
 * @since   1.0.0
 */

namespace BookingX\BusinessIntelligence;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BusinessIntelligenceAddon Class.
 */
class BusinessIntelligenceAddon {

	/**
	 * Instance.
	 *
	 * @var BusinessIntelligenceAddon
	 */
	private static $instance = null;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Services.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get instance.
	 *
	 * @return BusinessIntelligenceAddon
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
		$this->settings = get_option( 'bkx_bi_settings', array() );

		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['metrics']     = new Services\MetricsService();
		$this->services['kpi']         = new Services\KPIService();
		$this->services['trends']      = new Services\TrendsService();
		$this->services['forecasting'] = new Services\ForecastingService();
		$this->services['reports']     = new Services\ReportsService();
		$this->services['export']      = new Services\ExportService();
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

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Dashboard widgets.
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_bi_get_metrics', array( $this, 'ajax_get_metrics' ) );
		add_action( 'wp_ajax_bkx_bi_get_chart_data', array( $this, 'ajax_get_chart_data' ) );
		add_action( 'wp_ajax_bkx_bi_export_report', array( $this, 'ajax_export_report' ) );
		add_action( 'wp_ajax_bkx_bi_save_report', array( $this, 'ajax_save_report' ) );
		add_action( 'wp_ajax_bkx_bi_get_forecast', array( $this, 'ajax_get_forecast' ) );

		// Cron handlers.
		add_action( 'bkx_bi_aggregate_data', array( $this, 'aggregate_data' ) );
		add_action( 'bkx_bi_send_reports', array( $this, 'send_scheduled_reports' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		// Main BI Dashboard.
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Business Intelligence', 'bkx-business-intelligence' ),
			__( 'BI Dashboard', 'bkx-business-intelligence' ),
			'manage_options',
			'bkx-bi-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		// Reports.
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Reports', 'bkx-business-intelligence' ),
			__( 'Reports', 'bkx-business-intelligence' ),
			'manage_options',
			'bkx-bi-reports',
			array( $this, 'render_reports_page' )
		);

		// Settings.
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'BI Settings', 'bkx-business-intelligence' ),
			__( 'BI Settings', 'bkx-business-intelligence' ),
			'manage_options',
			'bkx-bi-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		$bi_pages = array(
			'bkx_booking_page_bkx-bi-dashboard',
			'bkx_booking_page_bkx-bi-reports',
			'bkx_booking_page_bkx-bi-settings',
		);

		if ( ! in_array( $hook, $bi_pages, true ) && 'index.php' !== $hook ) {
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

		// Plugin assets.
		wp_enqueue_style(
			'bkx-bi-admin',
			BKX_BI_URL . 'assets/css/admin.css',
			array(),
			BKX_BI_VERSION
		);

		wp_enqueue_script(
			'bkx-bi-admin',
			BKX_BI_URL . 'assets/js/admin.js',
			array( 'jquery', 'chartjs', 'daterangepicker' ),
			BKX_BI_VERSION,
			true
		);

		wp_localize_script(
			'bkx-bi-admin',
			'bkxBI',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'restUrl'   => rest_url( 'bkx-bi/v1' ),
				'nonce'     => wp_create_nonce( 'bkx_bi_nonce' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'currency'  => get_option( 'bkx_currency', 'USD' ),
				'dateFormat' => get_option( 'date_format' ),
				'i18n'      => array(
					'revenue'       => __( 'Revenue', 'bkx-business-intelligence' ),
					'bookings'      => __( 'Bookings', 'bkx-business-intelligence' ),
					'customers'     => __( 'Customers', 'bkx-business-intelligence' ),
					'avgValue'      => __( 'Avg. Booking Value', 'bkx-business-intelligence' ),
					'loading'       => __( 'Loading...', 'bkx-business-intelligence' ),
					'noData'        => __( 'No data available', 'bkx-business-intelligence' ),
					'exportSuccess' => __( 'Report exported successfully', 'bkx-business-intelligence' ),
					'error'         => __( 'An error occurred', 'bkx-business-intelligence' ),
				),
			)
		);
	}

	/**
	 * Add dashboard widgets.
	 */
	public function add_dashboard_widgets() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'bkx_bi_overview',
			__( 'BookingX Business Overview', 'bkx-business-intelligence' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render main dashboard widget.
	 */
	public function render_dashboard_widget() {
		$kpi = $this->services['kpi'];
		$today = $kpi->get_today_kpis();
		$week  = $kpi->get_period_kpis( 'week' );
		?>
		<div class="bkx-bi-widget">
			<div class="widget-grid">
				<div class="widget-stat">
					<span class="stat-label"><?php esc_html_e( 'Today\'s Revenue', 'bkx-business-intelligence' ); ?></span>
					<span class="stat-value"><?php echo esc_html( $this->format_currency( $today['revenue'] ) ); ?></span>
				</div>
				<div class="widget-stat">
					<span class="stat-label"><?php esc_html_e( 'Today\'s Bookings', 'bkx-business-intelligence' ); ?></span>
					<span class="stat-value"><?php echo esc_html( $today['bookings'] ); ?></span>
				</div>
				<div class="widget-stat">
					<span class="stat-label"><?php esc_html_e( 'This Week', 'bkx-business-intelligence' ); ?></span>
					<span class="stat-value"><?php echo esc_html( $this->format_currency( $week['revenue'] ) ); ?></span>
				</div>
				<div class="widget-stat">
					<span class="stat-label"><?php esc_html_e( 'Conversion Rate', 'bkx-business-intelligence' ); ?></span>
					<span class="stat-value"><?php echo esc_html( number_format( $week['conversion_rate'], 1 ) ); ?>%</span>
				</div>
			</div>
			<p class="widget-footer">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-bi-dashboard' ) ); ?>">
					<?php esc_html_e( 'View Full Dashboard', 'bkx-business-intelligence' ); ?> →
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard_page() {
		include BKX_BI_PATH . 'templates/admin/dashboard.php';
	}

	/**
	 * Render reports page.
	 */
	public function render_reports_page() {
		include BKX_BI_PATH . 'templates/admin/reports.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		include BKX_BI_PATH . 'templates/admin/settings.php';
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'bkx_bi_settings', 'bkx_bi_settings', array( $this, 'sanitize_settings' ) );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input values.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['enabled']            = ! empty( $input['enabled'] );
		$sanitized['dashboard_widgets']  = isset( $input['dashboard_widgets'] ) ? array_map( 'sanitize_text_field', (array) $input['dashboard_widgets'] ) : array();
		$sanitized['default_date_range'] = sanitize_text_field( $input['default_date_range'] ?? '30days' );
		$sanitized['cache_duration']     = absint( $input['cache_duration'] ?? 3600 );
		$sanitized['email_reports']      = ! empty( $input['email_reports'] );
		$sanitized['report_recipients']  = sanitize_text_field( $input['report_recipients'] ?? '' );
		$sanitized['report_frequency']   = in_array( $input['report_frequency'] ?? '', array( 'daily', 'weekly', 'monthly' ), true )
			? $input['report_frequency']
			: 'weekly';

		return $sanitized;
	}

	/**
	 * AJAX: Get metrics.
	 */
	public function ajax_get_metrics() {
		check_ajax_referer( 'bkx_bi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-business-intelligence' ) ) );
		}

		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : gmdate( 'Y-m-d' );
		$metric     = isset( $_POST['metric'] ) ? sanitize_text_field( wp_unslash( $_POST['metric'] ) ) : 'all';

		$metrics = $this->services['metrics']->get_metrics( $start_date, $end_date, $metric );
		$kpis    = $this->services['kpi']->get_kpis( $start_date, $end_date );

		wp_send_json_success( array(
			'metrics' => $metrics,
			'kpis'    => $kpis,
		) );
	}

	/**
	 * AJAX: Get chart data.
	 */
	public function ajax_get_chart_data() {
		check_ajax_referer( 'bkx_bi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-business-intelligence' ) ) );
		}

		$chart_type = isset( $_POST['chart_type'] ) ? sanitize_text_field( wp_unslash( $_POST['chart_type'] ) ) : 'revenue_trend';
		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : gmdate( 'Y-m-d' );

		$trends = $this->services['trends'];
		$data   = array();

		switch ( $chart_type ) {
			case 'revenue_trend':
				$data = $trends->get_revenue_trend( $start_date, $end_date );
				break;

			case 'booking_trend':
				$data = $trends->get_booking_trend( $start_date, $end_date );
				break;

			case 'service_breakdown':
				$data = $trends->get_service_breakdown( $start_date, $end_date );
				break;

			case 'staff_performance':
				$data = $trends->get_staff_performance( $start_date, $end_date );
				break;

			case 'hourly_distribution':
				$data = $trends->get_hourly_distribution( $start_date, $end_date );
				break;

			case 'day_of_week':
				$data = $trends->get_day_of_week_distribution( $start_date, $end_date );
				break;
		}

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Export report.
	 */
	public function ajax_export_report() {
		check_ajax_referer( 'bkx_bi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-business-intelligence' ) ) );
		}

		$report_type = isset( $_POST['report_type'] ) ? sanitize_text_field( wp_unslash( $_POST['report_type'] ) ) : 'summary';
		$format      = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'csv';
		$start_date  = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date    = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : gmdate( 'Y-m-d' );

		$result = $this->services['export']->export_report( $report_type, $format, $start_date, $end_date );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Save report.
	 */
	public function ajax_save_report() {
		check_ajax_referer( 'bkx_bi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-business-intelligence' ) ) );
		}

		$report_name   = isset( $_POST['report_name'] ) ? sanitize_text_field( wp_unslash( $_POST['report_name'] ) ) : '';
		$report_type   = isset( $_POST['report_type'] ) ? sanitize_text_field( wp_unslash( $_POST['report_type'] ) ) : '';
		$report_config = isset( $_POST['report_config'] ) ? wp_unslash( $_POST['report_config'] ) : '';

		if ( empty( $report_name ) || empty( $report_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Report name and type are required.', 'bkx-business-intelligence' ) ) );
		}

		$result = $this->services['reports']->save_report( $report_name, $report_type, $report_config );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'report_id' => $result ) );
	}

	/**
	 * AJAX: Get forecast.
	 */
	public function ajax_get_forecast() {
		check_ajax_referer( 'bkx_bi_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-business-intelligence' ) ) );
		}

		$forecast_type = isset( $_POST['forecast_type'] ) ? sanitize_text_field( wp_unslash( $_POST['forecast_type'] ) ) : 'revenue';
		$days_ahead    = isset( $_POST['days_ahead'] ) ? absint( $_POST['days_ahead'] ) : 30;

		$forecast = $this->services['forecasting']->get_forecast( $forecast_type, $days_ahead );

		wp_send_json_success( $forecast );
	}

	/**
	 * Aggregate data (cron job).
	 */
	public function aggregate_data() {
		$this->services['metrics']->aggregate_daily_metrics();
	}

	/**
	 * Send scheduled reports (cron job).
	 */
	public function send_scheduled_reports() {
		if ( ! $this->get_setting( 'email_reports', false ) ) {
			return;
		}

		$this->services['reports']->send_scheduled_reports();
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-bi/v1',
			'/metrics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_metrics' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			'bkx-bi/v1',
			'/kpis',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_kpis' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
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
		$start_date = $request->get_param( 'start_date' ) ?: gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date   = $request->get_param( 'end_date' ) ?: gmdate( 'Y-m-d' );

		$metrics = $this->services['metrics']->get_metrics( $start_date, $end_date );

		return rest_ensure_response( $metrics );
	}

	/**
	 * REST: Get KPIs.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_kpis( $request ) {
		$start_date = $request->get_param( 'start_date' ) ?: gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date   = $request->get_param( 'end_date' ) ?: gmdate( 'Y-m-d' );

		$kpis = $this->services['kpi']->get_kpis( $start_date, $end_date );

		return rest_ensure_response( $kpis );
	}

	/**
	 * Format currency.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private function format_currency( $amount ) {
		$currency = get_option( 'bkx_currency', 'USD' );
		$symbol   = '$';

		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'INR' => '₹',
			'AUD' => 'A$',
			'CAD' => 'C$',
		);

		if ( isset( $symbols[ $currency ] ) ) {
			$symbol = $symbols[ $currency ];
		}

		return $symbol . number_format( (float) $amount, 2 );
	}
}

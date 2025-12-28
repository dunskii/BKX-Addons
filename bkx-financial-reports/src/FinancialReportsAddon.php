<?php
/**
 * Financial Reports Addon Main Class.
 *
 * @package BookingX\FinancialReports
 * @since   1.0.0
 */

namespace BookingX\FinancialReports;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BookingX\FinancialReports\Services\RevenueAnalytics;
use BookingX\FinancialReports\Services\ProfitLossReport;
use BookingX\FinancialReports\Services\TaxReports;
use BookingX\FinancialReports\Services\CashFlowAnalysis;
use BookingX\FinancialReports\Services\ExpenseTracker;
use BookingX\FinancialReports\Services\ExportService;

/**
 * FinancialReportsAddon Class.
 */
class FinancialReportsAddon {

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
		$this->services['revenue']   = new RevenueAnalytics();
		$this->services['pnl']       = new ProfitLossReport();
		$this->services['tax']       = new TaxReports();
		$this->services['cashflow']  = new CashFlowAnalysis();
		$this->services['expenses']  = new ExpenseTracker();
		$this->services['export']    = new ExportService();
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_fin_get_revenue_data', array( $this, 'ajax_get_revenue_data' ) );
		add_action( 'wp_ajax_bkx_fin_get_pnl_report', array( $this, 'ajax_get_pnl_report' ) );
		add_action( 'wp_ajax_bkx_fin_get_tax_report', array( $this, 'ajax_get_tax_report' ) );
		add_action( 'wp_ajax_bkx_fin_get_cashflow', array( $this, 'ajax_get_cashflow' ) );
		add_action( 'wp_ajax_bkx_fin_get_expenses', array( $this, 'ajax_get_expenses' ) );
		add_action( 'wp_ajax_bkx_fin_save_expense', array( $this, 'ajax_save_expense' ) );
		add_action( 'wp_ajax_bkx_fin_delete_expense', array( $this, 'ajax_delete_expense' ) );
		add_action( 'wp_ajax_bkx_fin_export_report', array( $this, 'ajax_export_report' ) );
		add_action( 'wp_ajax_bkx_fin_get_dashboard', array( $this, 'ajax_get_dashboard' ) );

		// Daily snapshot cron.
		add_action( 'bkx_financial_daily_snapshot', array( $this, 'create_daily_snapshot' ) );
		if ( ! wp_next_scheduled( 'bkx_financial_daily_snapshot' ) ) {
			wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', 'bkx_financial_daily_snapshot' );
		}
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Financial Reports', 'bkx-financial-reports' ),
			__( 'Financial Reports', 'bkx-financial-reports' ),
			'manage_options',
			'bkx-financial-reports',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bookingx_page_bkx-financial-reports' !== $hook ) {
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
			'bkx-financial-admin',
			BKX_FINANCIAL_URL . 'assets/css/admin.css',
			array(),
			BKX_FINANCIAL_VERSION
		);

		wp_enqueue_script(
			'bkx-financial-admin',
			BKX_FINANCIAL_URL . 'assets/js/admin.js',
			array( 'jquery', 'chartjs' ),
			BKX_FINANCIAL_VERSION,
			true
		);

		wp_localize_script(
			'bkx-financial-admin',
			'bkxFinancial',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_financial_admin' ),
				'currency' => get_option( 'bkx_currency_symbol', '$' ),
				'i18n'     => array(
					'loading'  => __( 'Loading...', 'bkx-financial-reports' ),
					'error'    => __( 'An error occurred', 'bkx-financial-reports' ),
					'saved'    => __( 'Saved successfully', 'bkx-financial-reports' ),
					'deleted'  => __( 'Deleted successfully', 'bkx-financial-reports' ),
					'confirm'  => __( 'Are you sure?', 'bkx-financial-reports' ),
					'revenue'  => __( 'Revenue', 'bkx-financial-reports' ),
					'expenses' => __( 'Expenses', 'bkx-financial-reports' ),
					'profit'   => __( 'Profit', 'bkx-financial-reports' ),
				),
			)
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'bkx_financial', 'bkx_fin_fiscal_year_start' );
		register_setting( 'bkx_financial', 'bkx_fin_default_tax_rate' );
		register_setting( 'bkx_financial', 'bkx_fin_expense_categories' );
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_FINANCIAL_PATH . 'templates/admin/reports.php';
	}

	/**
	 * AJAX: Get revenue data.
	 */
	public function ajax_get_revenue_data() {
		check_ajax_referer( 'bkx_financial_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$period     = sanitize_text_field( $_POST['period'] ?? 'month' );
		$start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
		$end_date   = sanitize_text_field( $_POST['end_date'] ?? '' );

		$revenue = $this->services['revenue'];
		$data    = $revenue->get_revenue_data( $period, $start_date, $end_date );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get P&L report.
	 */
	public function ajax_get_pnl_report() {
		check_ajax_referer( 'bkx_financial_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$period     = sanitize_text_field( $_POST['period'] ?? 'month' );
		$start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
		$end_date   = sanitize_text_field( $_POST['end_date'] ?? '' );

		$pnl  = $this->services['pnl'];
		$data = $pnl->generate_report( $period, $start_date, $end_date );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get tax report.
	 */
	public function ajax_get_tax_report() {
		check_ajax_referer( 'bkx_financial_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$period     = sanitize_text_field( $_POST['period'] ?? 'quarter' );
		$start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
		$end_date   = sanitize_text_field( $_POST['end_date'] ?? '' );

		$tax  = $this->services['tax'];
		$data = $tax->generate_report( $period, $start_date, $end_date );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get cash flow.
	 */
	public function ajax_get_cashflow() {
		check_ajax_referer( 'bkx_financial_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$period = sanitize_text_field( $_POST['period'] ?? 'month' );

		$cashflow = $this->services['cashflow'];
		$data     = $cashflow->analyze( $period );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get expenses.
	 */
	public function ajax_get_expenses() {
		check_ajax_referer( 'bkx_financial_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$start_date = sanitize_text_field( $_POST['start_date'] ?? '' );
		$end_date   = sanitize_text_field( $_POST['end_date'] ?? '' );
		$category   = sanitize_text_field( $_POST['category'] ?? '' );

		$expenses = $this->services['expenses'];
		$data     = $expenses->get_expenses( $start_date, $end_date, $category );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Save expense.
	 */
	public function ajax_save_expense() {
		check_ajax_referer( 'bkx_financial_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$expense_data = array(
			'id'                  => absint( $_POST['expense_id'] ?? 0 ),
			'expense_date'        => sanitize_text_field( $_POST['expense_date'] ?? '' ),
			'category'            => sanitize_text_field( $_POST['category'] ?? '' ),
			'description'         => sanitize_text_field( $_POST['description'] ?? '' ),
			'amount'              => floatval( $_POST['amount'] ?? 0 ),
			'payment_method'      => sanitize_text_field( $_POST['payment_method'] ?? '' ),
			'vendor'              => sanitize_text_field( $_POST['vendor'] ?? '' ),
			'notes'               => sanitize_textarea_field( $_POST['notes'] ?? '' ),
			'is_recurring'        => absint( $_POST['is_recurring'] ?? 0 ),
			'recurring_frequency' => sanitize_text_field( $_POST['recurring_frequency'] ?? '' ),
		);

		$expenses = $this->services['expenses'];
		$result   = $expenses->save_expense( $expense_data );

		if ( $result ) {
			wp_send_json_success( array( 'expense_id' => $result ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to save expense' ) );
		}
	}

	/**
	 * AJAX: Delete expense.
	 */
	public function ajax_delete_expense() {
		check_ajax_referer( 'bkx_financial_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$expense_id = absint( $_POST['expense_id'] ?? 0 );

		if ( ! $expense_id ) {
			wp_send_json_error( array( 'message' => 'Invalid expense ID' ) );
		}

		$expenses = $this->services['expenses'];
		$result   = $expenses->delete_expense( $expense_id );

		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => 'Failed to delete expense' ) );
		}
	}

	/**
	 * AJAX: Export report.
	 */
	public function ajax_export_report() {
		check_ajax_referer( 'bkx_financial_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$report_type = sanitize_text_field( $_POST['report_type'] ?? 'revenue' );
		$format      = sanitize_text_field( $_POST['format'] ?? 'csv' );
		$start_date  = sanitize_text_field( $_POST['start_date'] ?? '' );
		$end_date    = sanitize_text_field( $_POST['end_date'] ?? '' );

		$export = $this->services['export'];
		$result = $export->export_report( $report_type, $format, $start_date, $end_date );

		if ( $result ) {
			wp_send_json_success( array( 'download_url' => $result ) );
		} else {
			wp_send_json_error( array( 'message' => 'Export failed' ) );
		}
	}

	/**
	 * AJAX: Get dashboard data.
	 */
	public function ajax_get_dashboard() {
		check_ajax_referer( 'bkx_financial_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$revenue  = $this->services['revenue'];
		$pnl      = $this->services['pnl'];
		$cashflow = $this->services['cashflow'];

		$data = array(
			'summary'        => $revenue->get_summary(),
			'revenue_chart'  => $revenue->get_chart_data( 'week' ),
			'top_services'   => $revenue->get_top_services(),
			'recent_bookings' => $revenue->get_recent_bookings( 5 ),
			'quick_stats'    => array(
				'mtd_revenue' => $revenue->get_mtd_revenue(),
				'ytd_revenue' => $revenue->get_ytd_revenue(),
				'avg_booking' => $revenue->get_average_booking_value(),
				'growth_rate' => $revenue->get_growth_rate(),
			),
		);

		wp_send_json_success( $data );
	}

	/**
	 * Create daily snapshot.
	 */
	public function create_daily_snapshot() {
		$revenue = $this->services['revenue'];
		$revenue->create_snapshot( gmdate( 'Y-m-d', strtotime( '-1 day' ) ) );
	}
}

<?php
/**
 * Marketing ROI Addon Main Class.
 *
 * @package BookingX\MarketingROI
 * @since   1.0.0
 */

namespace BookingX\MarketingROI;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BookingX\MarketingROI\Services\CampaignManager;
use BookingX\MarketingROI\Services\UTMTracker;
use BookingX\MarketingROI\Services\ROICalculator;
use BookingX\MarketingROI\Services\ReportGenerator;

/**
 * MarketingROIAddon Class.
 */
class MarketingROIAddon {

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
		$this->services['campaign_manager'] = new CampaignManager();
		$this->services['utm_tracker']      = new UTMTracker();
		$this->services['roi_calculator']   = new ROICalculator();
		$this->services['report_generator'] = new ReportGenerator();
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Frontend UTM tracking.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_script' ) );

		// AJAX handlers (admin).
		add_action( 'wp_ajax_bkx_roi_get_dashboard', array( $this, 'ajax_get_dashboard' ) );
		add_action( 'wp_ajax_bkx_roi_get_campaigns', array( $this, 'ajax_get_campaigns' ) );
		add_action( 'wp_ajax_bkx_roi_save_campaign', array( $this, 'ajax_save_campaign' ) );
		add_action( 'wp_ajax_bkx_roi_delete_campaign', array( $this, 'ajax_delete_campaign' ) );
		add_action( 'wp_ajax_bkx_roi_add_cost', array( $this, 'ajax_add_cost' ) );
		add_action( 'wp_ajax_bkx_roi_get_campaign_details', array( $this, 'ajax_get_campaign_details' ) );
		add_action( 'wp_ajax_bkx_roi_get_utm_report', array( $this, 'ajax_get_utm_report' ) );
		add_action( 'wp_ajax_bkx_roi_export_report', array( $this, 'ajax_export_report' ) );

		// AJAX handlers (frontend tracking).
		add_action( 'wp_ajax_bkx_roi_track', array( $this, 'ajax_track_visit' ) );
		add_action( 'wp_ajax_nopriv_bkx_roi_track', array( $this, 'ajax_track_visit' ) );

		// BookingX hooks.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Marketing ROI', 'bkx-marketing-roi' ),
			__( 'Marketing ROI', 'bkx-marketing-roi' ),
			'manage_options',
			'bkx-marketing-roi',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bookingx_page_bkx-marketing-roi' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', array(), '4.4.1', true );

		wp_enqueue_style(
			'bkx-marketing-roi-admin',
			BKX_MARKETING_ROI_URL . 'assets/css/admin.css',
			array(),
			BKX_MARKETING_ROI_VERSION
		);

		wp_enqueue_script(
			'bkx-marketing-roi-admin',
			BKX_MARKETING_ROI_URL . 'assets/js/admin.js',
			array( 'jquery', 'chartjs', 'wp-util' ),
			BKX_MARKETING_ROI_VERSION,
			true
		);

		wp_localize_script(
			'bkx-marketing-roi-admin',
			'bkxROI',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'bkx_roi_admin' ),
				'currencySymbol' => get_option( 'bkx_currency_symbol', '$' ),
				'i18n'           => array(
					'loading'         => __( 'Loading...', 'bkx-marketing-roi' ),
					'noData'          => __( 'No data available', 'bkx-marketing-roi' ),
					'confirmDelete'   => __( 'Are you sure you want to delete this campaign?', 'bkx-marketing-roi' ),
					'saved'           => __( 'Campaign saved successfully', 'bkx-marketing-roi' ),
					'deleted'         => __( 'Campaign deleted', 'bkx-marketing-roi' ),
					'error'           => __( 'An error occurred', 'bkx-marketing-roi' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend tracking script.
	 */
	public function enqueue_tracking_script() {
		// Check if UTM parameters present.
		if ( ! $this->has_utm_params() ) {
			return;
		}

		wp_enqueue_script(
			'bkx-marketing-roi-tracker',
			BKX_MARKETING_ROI_URL . 'assets/js/tracker.js',
			array( 'jquery' ),
			BKX_MARKETING_ROI_VERSION,
			true
		);

		wp_localize_script(
			'bkx-marketing-roi-tracker',
			'bkxROITracker',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'bkx_roi_track' ),
				'sessionId' => $this->get_or_create_session_id(),
			)
		);
	}

	/**
	 * Check if UTM parameters present.
	 *
	 * @return bool
	 */
	private function has_utm_params() {
		$utm_params = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' );

		foreach ( $utm_params as $param ) {
			if ( isset( $_GET[ $param ] ) && ! empty( $_GET[ $param ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get or create session ID.
	 *
	 * @return string
	 */
	private function get_or_create_session_id() {
		if ( isset( $_COOKIE['bkx_roi_session'] ) ) {
			return sanitize_text_field( $_COOKIE['bkx_roi_session'] );
		}

		$session_id = wp_generate_uuid4();
		setcookie( 'bkx_roi_session', $session_id, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		return $session_id;
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_MARKETING_ROI_PATH . 'templates/admin/roi.php';
	}

	/**
	 * AJAX: Get dashboard data.
	 */
	public function ajax_get_dashboard() {
		check_ajax_referer( 'bkx_roi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$start_date = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end_date   = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );

		$calculator = $this->services['roi_calculator'];
		$data       = $calculator->get_dashboard_data( $start_date, $end_date );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get campaigns.
	 */
	public function ajax_get_campaigns() {
		check_ajax_referer( 'bkx_roi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$manager   = $this->services['campaign_manager'];
		$campaigns = $manager->get_all_campaigns();

		wp_send_json_success( $campaigns );
	}

	/**
	 * AJAX: Save campaign.
	 */
	public function ajax_save_campaign() {
		check_ajax_referer( 'bkx_roi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$data = array(
			'id'           => isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0,
			'campaign_name' => sanitize_text_field( wp_unslash( $_POST['campaign_name'] ?? '' ) ),
			'utm_source'   => sanitize_text_field( wp_unslash( $_POST['utm_source'] ?? '' ) ),
			'utm_medium'   => sanitize_text_field( wp_unslash( $_POST['utm_medium'] ?? '' ) ),
			'utm_campaign' => sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ?? '' ) ),
			'utm_content'  => sanitize_text_field( wp_unslash( $_POST['utm_content'] ?? '' ) ),
			'utm_term'     => sanitize_text_field( wp_unslash( $_POST['utm_term'] ?? '' ) ),
			'budget'       => floatval( $_POST['budget'] ?? 0 ),
			'start_date'   => sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) ),
			'end_date'     => sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) ),
			'status'       => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
			'notes'        => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
		);

		$manager = $this->services['campaign_manager'];
		$result  = $manager->save_campaign( $data );

		if ( $result ) {
			wp_send_json_success( array( 'id' => $result ) );
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * AJAX: Delete campaign.
	 */
	public function ajax_delete_campaign() {
		check_ajax_referer( 'bkx_roi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$id = absint( $_POST['id'] ?? 0 );

		if ( ! $id ) {
			wp_send_json_error();
		}

		$manager = $this->services['campaign_manager'];
		$result  = $manager->delete_campaign( $id );

		wp_send_json_success( array( 'deleted' => $result ) );
	}

	/**
	 * AJAX: Add campaign cost.
	 */
	public function ajax_add_cost() {
		check_ajax_referer( 'bkx_roi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$data = array(
			'campaign_id' => absint( $_POST['campaign_id'] ?? 0 ),
			'cost_date'   => sanitize_text_field( wp_unslash( $_POST['cost_date'] ?? '' ) ),
			'amount'      => floatval( $_POST['amount'] ?? 0 ),
			'cost_type'   => sanitize_text_field( wp_unslash( $_POST['cost_type'] ?? 'ad_spend' ) ),
			'notes'       => sanitize_text_field( wp_unslash( $_POST['notes'] ?? '' ) ),
		);

		$manager = $this->services['campaign_manager'];
		$result  = $manager->add_cost( $data );

		wp_send_json_success( array( 'id' => $result ) );
	}

	/**
	 * AJAX: Get campaign details.
	 */
	public function ajax_get_campaign_details() {
		check_ajax_referer( 'bkx_roi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$id         = absint( $_POST['id'] ?? 0 );
		$start_date = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end_date   = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );

		$calculator = $this->services['roi_calculator'];
		$data       = $calculator->get_campaign_details( $id, $start_date, $end_date );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Get UTM report.
	 */
	public function ajax_get_utm_report() {
		check_ajax_referer( 'bkx_roi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$start_date = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end_date   = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
		$group_by   = sanitize_text_field( wp_unslash( $_POST['group_by'] ?? 'source' ) );

		$calculator = $this->services['roi_calculator'];
		$data       = $calculator->get_utm_report( $start_date, $end_date, $group_by );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Export report.
	 */
	public function ajax_export_report() {
		check_ajax_referer( 'bkx_roi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$report_type = sanitize_text_field( wp_unslash( $_POST['report_type'] ?? 'campaigns' ) );
		$start_date  = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end_date    = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );

		$generator = $this->services['report_generator'];
		$file_url  = $generator->export_csv( $report_type, $start_date, $end_date );

		if ( $file_url ) {
			wp_send_json_success( array( 'url' => $file_url ) );
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * AJAX: Track visit.
	 */
	public function ajax_track_visit() {
		check_ajax_referer( 'bkx_roi_track', 'nonce' );

		$session_id   = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
		$utm_source   = sanitize_text_field( wp_unslash( $_POST['utm_source'] ?? '' ) );
		$utm_medium   = sanitize_text_field( wp_unslash( $_POST['utm_medium'] ?? '' ) );
		$utm_campaign = sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ?? '' ) );
		$utm_content  = sanitize_text_field( wp_unslash( $_POST['utm_content'] ?? '' ) );
		$utm_term     = sanitize_text_field( wp_unslash( $_POST['utm_term'] ?? '' ) );
		$landing_page = sanitize_url( wp_unslash( $_POST['landing_page'] ?? '' ) );
		$referrer     = sanitize_url( wp_unslash( $_POST['referrer'] ?? '' ) );

		if ( empty( $session_id ) || empty( $utm_source ) ) {
			wp_send_json_error();
		}

		$tracker = $this->services['utm_tracker'];
		$result  = $tracker->track_visit(
			array(
				'session_id'   => $session_id,
				'utm_source'   => $utm_source,
				'utm_medium'   => $utm_medium,
				'utm_campaign' => $utm_campaign,
				'utm_content'  => $utm_content,
				'utm_term'     => $utm_term,
				'landing_page' => $landing_page,
				'referrer'     => $referrer,
			)
		);

		wp_send_json_success( array( 'tracked' => $result ) );
	}

	/**
	 * On booking created.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		$session_id = isset( $_COOKIE['bkx_roi_session'] ) ? sanitize_text_field( $_COOKIE['bkx_roi_session'] ) : '';

		if ( empty( $session_id ) ) {
			return;
		}

		$amount = $booking_data['total_amount'] ?? 0;

		$tracker = $this->services['utm_tracker'];
		$tracker->convert_visit( $session_id, $booking_id, $amount );
	}
}

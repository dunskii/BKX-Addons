<?php
/**
 * Sliding Pricing Addon main class.
 *
 * @package BookingX\SlidingPricing
 * @since   1.0.0
 */

namespace BookingX\SlidingPricing;

use BookingX\SlidingPricing\Services\PriceCalculator;
use BookingX\SlidingPricing\Services\RuleManager;
use BookingX\SlidingPricing\Services\SeasonManager;
use BookingX\SlidingPricing\Services\TimeslotManager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SlidingPricingAddon Class.
 */
class SlidingPricingAddon {

	/**
	 * Instance.
	 *
	 * @var SlidingPricingAddon
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
	 * @return SlidingPricingAddon
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
		$this->services['calculator'] = new PriceCalculator();
		$this->services['rules']      = new RuleManager();
		$this->services['seasons']    = new SeasonManager();
		$this->services['timeslots']  = new TimeslotManager();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Price calculation filters.
		add_filter( 'bkx_service_price', array( $this, 'apply_dynamic_pricing' ), 10, 4 );
		add_filter( 'bkx_booking_total', array( $this, 'apply_booking_pricing' ), 10, 3 );
		add_filter( 'bkx_get_slot_price', array( $this, 'get_slot_price' ), 10, 4 );

		// Frontend display.
		add_action( 'bkx_after_service_price', array( $this, 'display_price_badge' ), 10, 2 );
		add_filter( 'bkx_price_display', array( $this, 'format_price_display' ), 10, 3 );

		// Save pricing history.
		add_action( 'bkx_booking_created', array( $this, 'save_pricing_history' ), 10, 2 );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_save_pricing_rule', array( $this, 'ajax_save_rule' ) );
		add_action( 'wp_ajax_bkx_delete_pricing_rule', array( $this, 'ajax_delete_rule' ) );
		add_action( 'wp_ajax_bkx_save_season', array( $this, 'ajax_save_season' ) );
		add_action( 'wp_ajax_bkx_delete_season', array( $this, 'ajax_delete_season' ) );
		add_action( 'wp_ajax_bkx_save_timeslot', array( $this, 'ajax_save_timeslot' ) );
		add_action( 'wp_ajax_bkx_delete_timeslot', array( $this, 'ajax_delete_timeslot' ) );
		add_action( 'wp_ajax_bkx_preview_pricing', array( $this, 'ajax_preview_pricing' ) );
		add_action( 'wp_ajax_nopriv_bkx_get_dynamic_price', array( $this, 'ajax_get_dynamic_price' ) );
		add_action( 'wp_ajax_bkx_get_dynamic_price', array( $this, 'ajax_get_dynamic_price' ) );

		// Settings.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_sliding_pricing', array( $this, 'render_settings_tab' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Dynamic Pricing', 'bkx-sliding-pricing' ),
			__( 'Dynamic Pricing', 'bkx-sliding-pricing' ),
			'manage_options',
			'bkx-sliding-pricing',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bkx_booking_page_bkx-sliding-pricing' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-sliding-pricing-admin',
			BKX_SLIDING_PRICING_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_SLIDING_PRICING_VERSION
		);

		wp_enqueue_script(
			'bkx-sliding-pricing-admin',
			BKX_SLIDING_PRICING_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-datepicker', 'jquery-ui-sortable' ),
			BKX_SLIDING_PRICING_VERSION,
			true
		);

		wp_localize_script(
			'bkx-sliding-pricing-admin',
			'bkxSlidingPricing',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_sliding_pricing' ),
				'i18n'    => array(
					'loading'       => __( 'Loading...', 'bkx-sliding-pricing' ),
					'error'         => __( 'An error occurred', 'bkx-sliding-pricing' ),
					'confirmDelete' => __( 'Are you sure you want to delete this?', 'bkx-sliding-pricing' ),
					'saved'         => __( 'Saved successfully', 'bkx-sliding-pricing' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		require_once BKX_SLIDING_PRICING_PLUGIN_DIR . 'templates/admin/pricing.php';
	}

	/**
	 * Apply dynamic pricing to service price.
	 *
	 * @param float  $price       Original price.
	 * @param int    $service_id  Service ID.
	 * @param string $date        Booking date.
	 * @param string $time        Booking time.
	 * @return float Adjusted price.
	 */
	public function apply_dynamic_pricing( $price, $service_id, $date = '', $time = '' ) {
		if ( empty( $date ) ) {
			$date = gmdate( 'Y-m-d' );
		}

		return $this->services['calculator']->calculate_price( $price, $service_id, 0, $date, $time );
	}

	/**
	 * Apply pricing to booking total.
	 *
	 * @param float $total      Booking total.
	 * @param int   $booking_id Booking ID.
	 * @param array $data       Booking data.
	 * @return float Adjusted total.
	 */
	public function apply_booking_pricing( $total, $booking_id, $data ) {
		$service_id = isset( $data['base_id'] ) ? absint( $data['base_id'] ) : 0;
		$staff_id   = isset( $data['seat_id'] ) ? absint( $data['seat_id'] ) : 0;
		$date       = isset( $data['booking_date'] ) ? $data['booking_date'] : '';
		$time       = isset( $data['booking_time'] ) ? $data['booking_time'] : '';

		return $this->services['calculator']->calculate_price( $total, $service_id, $staff_id, $date, $time );
	}

	/**
	 * Get slot price for calendar display.
	 *
	 * @param float  $price      Base price.
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @param string $time       Time slot.
	 * @return float
	 */
	public function get_slot_price( $price, $service_id, $date, $time ) {
		return $this->services['calculator']->calculate_price( $price, $service_id, 0, $date, $time );
	}

	/**
	 * Display price badge (peak/off-peak/discount).
	 *
	 * @param int   $service_id Service ID.
	 * @param float $price      Current price.
	 */
	public function display_price_badge( $service_id, $price ) {
		$pricing_info = $this->services['calculator']->get_pricing_info( $service_id, gmdate( 'Y-m-d' ), gmdate( 'H:i' ) );

		if ( empty( $pricing_info['badges'] ) ) {
			return;
		}

		foreach ( $pricing_info['badges'] as $badge ) {
			printf(
				'<span class="bkx-price-badge bkx-badge-%s">%s</span>',
				esc_attr( $badge['type'] ),
				esc_html( $badge['label'] )
			);
		}
	}

	/**
	 * Format price display with original/savings.
	 *
	 * @param string $formatted  Formatted price.
	 * @param float  $price      Price.
	 * @param array  $context    Context data.
	 * @return string
	 */
	public function format_price_display( $formatted, $price, $context ) {
		if ( ! isset( $context['original_price'] ) || $context['original_price'] <= $price ) {
			return $formatted;
		}

		$show_original = get_option( 'bkx_sliding_pricing_show_original', 'yes' );
		$show_savings  = get_option( 'bkx_sliding_pricing_show_savings', 'yes' );

		$html = '';

		if ( 'yes' === $show_original ) {
			$html .= '<span class="bkx-original-price"><del>' . wc_price( $context['original_price'] ) . '</del></span> ';
		}

		$html .= '<span class="bkx-current-price">' . $formatted . '</span>';

		if ( 'yes' === $show_savings && $context['original_price'] > $price ) {
			$savings     = $context['original_price'] - $price;
			$savings_pct = round( ( $savings / $context['original_price'] ) * 100 );
			$html       .= sprintf(
				' <span class="bkx-savings">%s</span>',
				/* translators: %d: savings percentage */
				sprintf( __( 'Save %d%%', 'bkx-sliding-pricing' ), $savings_pct )
			);
		}

		return $html;
	}

	/**
	 * Save pricing history for booking.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function save_pricing_history( $booking_id, $booking_data ) {
		$this->services['calculator']->save_history( $booking_id, $booking_data );
	}

	/**
	 * AJAX: Save pricing rule.
	 */
	public function ajax_save_rule() {
		check_ajax_referer( 'bkx_sliding_pricing', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-sliding-pricing' ) ) );
		}

		$data = array(
			'id'               => isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0,
			'name'             => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'rule_type'        => isset( $_POST['rule_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_type'] ) ) : '',
			'applies_to'       => isset( $_POST['applies_to'] ) ? sanitize_text_field( wp_unslash( $_POST['applies_to'] ) ) : 'all',
			'service_ids'      => isset( $_POST['service_ids'] ) ? array_map( 'absint', (array) $_POST['service_ids'] ) : array(),
			'staff_ids'        => isset( $_POST['staff_ids'] ) ? array_map( 'absint', (array) $_POST['staff_ids'] ) : array(),
			'priority'         => isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 10,
			'adjustment_type'  => isset( $_POST['adjustment_type'] ) ? sanitize_text_field( wp_unslash( $_POST['adjustment_type'] ) ) : 'percentage',
			'adjustment_value' => isset( $_POST['adjustment_value'] ) ? floatval( $_POST['adjustment_value'] ) : 0,
			'conditions'       => isset( $_POST['conditions'] ) ? $this->sanitize_conditions( wp_unslash( $_POST['conditions'] ) ) : array(),
			'start_date'       => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : null,
			'end_date'         => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : null,
			'is_active'        => isset( $_POST['is_active'] ) ? 1 : 0,
		);

		$result = $this->services['rules']->save_rule( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'rule_id' => $result ) );
	}

	/**
	 * AJAX: Delete pricing rule.
	 */
	public function ajax_delete_rule() {
		check_ajax_referer( 'bkx_sliding_pricing', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-sliding-pricing' ) ) );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;

		if ( $this->services['rules']->delete_rule( $rule_id ) ) {
			wp_send_json_success();
		}

		wp_send_json_error( array( 'message' => __( 'Failed to delete rule', 'bkx-sliding-pricing' ) ) );
	}

	/**
	 * AJAX: Save season.
	 */
	public function ajax_save_season() {
		check_ajax_referer( 'bkx_sliding_pricing', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-sliding-pricing' ) ) );
		}

		$data = array(
			'id'               => isset( $_POST['season_id'] ) ? absint( $_POST['season_id'] ) : 0,
			'name'             => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'start_date'       => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
			'end_date'         => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '',
			'adjustment_type'  => isset( $_POST['adjustment_type'] ) ? sanitize_text_field( wp_unslash( $_POST['adjustment_type'] ) ) : 'percentage',
			'adjustment_value' => isset( $_POST['adjustment_value'] ) ? floatval( $_POST['adjustment_value'] ) : 0,
			'applies_to'       => isset( $_POST['applies_to'] ) ? sanitize_text_field( wp_unslash( $_POST['applies_to'] ) ) : 'all',
			'service_ids'      => isset( $_POST['service_ids'] ) ? array_map( 'absint', (array) $_POST['service_ids'] ) : array(),
			'recurs_yearly'    => isset( $_POST['recurs_yearly'] ) ? 1 : 0,
			'is_active'        => isset( $_POST['is_active'] ) ? 1 : 0,
		);

		$result = $this->services['seasons']->save_season( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'season_id' => $result ) );
	}

	/**
	 * AJAX: Delete season.
	 */
	public function ajax_delete_season() {
		check_ajax_referer( 'bkx_sliding_pricing', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-sliding-pricing' ) ) );
		}

		$season_id = isset( $_POST['season_id'] ) ? absint( $_POST['season_id'] ) : 0;

		if ( $this->services['seasons']->delete_season( $season_id ) ) {
			wp_send_json_success();
		}

		wp_send_json_error( array( 'message' => __( 'Failed to delete season', 'bkx-sliding-pricing' ) ) );
	}

	/**
	 * AJAX: Save timeslot.
	 */
	public function ajax_save_timeslot() {
		check_ajax_referer( 'bkx_sliding_pricing', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-sliding-pricing' ) ) );
		}

		$data = array(
			'id'               => isset( $_POST['timeslot_id'] ) ? absint( $_POST['timeslot_id'] ) : 0,
			'name'             => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'day_of_week'      => isset( $_POST['day_of_week'] ) ? sanitize_text_field( wp_unslash( $_POST['day_of_week'] ) ) : '',
			'start_time'       => isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '',
			'end_time'         => isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '',
			'adjustment_type'  => isset( $_POST['adjustment_type'] ) ? sanitize_text_field( wp_unslash( $_POST['adjustment_type'] ) ) : 'percentage',
			'adjustment_value' => isset( $_POST['adjustment_value'] ) ? floatval( $_POST['adjustment_value'] ) : 0,
			'applies_to'       => isset( $_POST['applies_to'] ) ? sanitize_text_field( wp_unslash( $_POST['applies_to'] ) ) : 'all',
			'service_ids'      => isset( $_POST['service_ids'] ) ? array_map( 'absint', (array) $_POST['service_ids'] ) : array(),
			'is_active'        => isset( $_POST['is_active'] ) ? 1 : 0,
		);

		$result = $this->services['timeslots']->save_timeslot( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'timeslot_id' => $result ) );
	}

	/**
	 * AJAX: Delete timeslot.
	 */
	public function ajax_delete_timeslot() {
		check_ajax_referer( 'bkx_sliding_pricing', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-sliding-pricing' ) ) );
		}

		$timeslot_id = isset( $_POST['timeslot_id'] ) ? absint( $_POST['timeslot_id'] ) : 0;

		if ( $this->services['timeslots']->delete_timeslot( $timeslot_id ) ) {
			wp_send_json_success();
		}

		wp_send_json_error( array( 'message' => __( 'Failed to delete timeslot', 'bkx-sliding-pricing' ) ) );
	}

	/**
	 * AJAX: Preview pricing.
	 */
	public function ajax_preview_pricing() {
		check_ajax_referer( 'bkx_sliding_pricing', 'nonce' );

		$service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
		$date       = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : gmdate( 'Y-m-d' );
		$time       = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '09:00';

		$base_price = get_post_meta( $service_id, 'base_price', true );
		$base_price = $base_price ? floatval( $base_price ) : 100;

		$result = $this->services['calculator']->calculate_price_with_breakdown( $base_price, $service_id, 0, $date, $time );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get dynamic price (public).
	 */
	public function ajax_get_dynamic_price() {
		$service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
		$date       = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$time       = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';

		$base_price = get_post_meta( $service_id, 'base_price', true );
		$base_price = $base_price ? floatval( $base_price ) : 0;

		$final_price = $this->services['calculator']->calculate_price( $base_price, $service_id, 0, $date, $time );
		$info        = $this->services['calculator']->get_pricing_info( $service_id, $date, $time );

		wp_send_json_success(
			array(
				'base_price'  => $base_price,
				'final_price' => $final_price,
				'badges'      => $info['badges'],
				'formatted'   => function_exists( 'wc_price' ) ? wc_price( $final_price ) : '$' . number_format( $final_price, 2 ),
			)
		);
	}

	/**
	 * Sanitize conditions array.
	 *
	 * @param mixed $conditions Raw conditions.
	 * @return array
	 */
	private function sanitize_conditions( $conditions ) {
		if ( ! is_array( $conditions ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $conditions as $condition ) {
			$sanitized[] = array(
				'type'     => isset( $condition['type'] ) ? sanitize_text_field( $condition['type'] ) : '',
				'operator' => isset( $condition['operator'] ) ? sanitize_text_field( $condition['operator'] ) : '',
				'value'    => isset( $condition['value'] ) ? sanitize_text_field( $condition['value'] ) : '',
			);
		}

		return $sanitized;
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['sliding_pricing'] = __( 'Dynamic Pricing', 'bkx-sliding-pricing' );
		return $tabs;
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab() {
		require_once BKX_SLIDING_PRICING_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-sliding-pricing/v1',
			'/price',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_price' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'service_id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'date'       => array(
						'default'           => gmdate( 'Y-m-d' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'time'       => array(
						'default'           => '09:00',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * REST: Get price.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_price( $request ) {
		$service_id = $request->get_param( 'service_id' );
		$date       = $request->get_param( 'date' );
		$time       = $request->get_param( 'time' );

		$base_price = get_post_meta( $service_id, 'base_price', true );
		$base_price = $base_price ? floatval( $base_price ) : 0;

		$result = $this->services['calculator']->calculate_price_with_breakdown( $base_price, $service_id, 0, $date, $time );

		return rest_ensure_response( $result );
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

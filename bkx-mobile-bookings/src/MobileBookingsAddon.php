<?php
/**
 * Main Mobile Bookings Addon Class.
 *
 * @package BookingX\MobileBookings
 * @since   1.0.0
 */

namespace BookingX\MobileBookings;

use BookingX\MobileBookings\Services\GoogleMapsService;
use BookingX\MobileBookings\Services\DistanceCalculator;
use BookingX\MobileBookings\Services\TravelTimeScheduler;
use BookingX\MobileBookings\Services\RouteOptimizer;
use BookingX\MobileBookings\Services\ServiceAreaManager;
use BookingX\MobileBookings\Services\DistancePricing;
use BookingX\MobileBookings\Services\LocationTracker;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MobileBookingsAddon Class.
 */
class MobileBookingsAddon {

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
	 * Initialize the addon.
	 */
	public function init() {
		$this->settings = get_option( 'bkx_mobile_bookings_settings', array() );

		$this->init_services();
		$this->register_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['google_maps']    = new GoogleMapsService( $this->settings );
		$this->services['distance']       = new DistanceCalculator( $this->services['google_maps'], $this->settings );
		$this->services['travel_time']    = new TravelTimeScheduler( $this->services['distance'], $this->settings );
		$this->services['route']          = new RouteOptimizer( $this->services['distance'], $this->settings );
		$this->services['service_areas']  = new ServiceAreaManager( $this->services['google_maps'], $this->settings );
		$this->services['pricing']        = new DistancePricing( $this->services['distance'], $this->settings );
		$this->services['tracker']        = new LocationTracker( $this->settings );
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register_hooks() {
		// Admin.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_calculate_distance', array( $this, 'ajax_calculate_distance' ) );
		add_action( 'wp_ajax_nopriv_bkx_calculate_distance', array( $this, 'ajax_calculate_distance' ) );
		add_action( 'wp_ajax_bkx_geocode_address', array( $this, 'ajax_geocode_address' ) );
		add_action( 'wp_ajax_nopriv_bkx_geocode_address', array( $this, 'ajax_geocode_address' ) );
		add_action( 'wp_ajax_bkx_check_service_area', array( $this, 'ajax_check_service_area' ) );
		add_action( 'wp_ajax_nopriv_bkx_check_service_area', array( $this, 'ajax_check_service_area' ) );
		add_action( 'wp_ajax_bkx_get_nearby_providers', array( $this, 'ajax_get_nearby_providers' ) );
		add_action( 'wp_ajax_nopriv_bkx_get_nearby_providers', array( $this, 'ajax_get_nearby_providers' ) );
		add_action( 'wp_ajax_bkx_gps_checkin', array( $this, 'ajax_gps_checkin' ) );
		add_action( 'wp_ajax_bkx_update_provider_location', array( $this, 'ajax_update_provider_location' ) );
		add_action( 'wp_ajax_bkx_optimize_route', array( $this, 'ajax_optimize_route' ) );
		add_action( 'wp_ajax_bkx_get_provider_route', array( $this, 'ajax_get_provider_route' ) );
		add_action( 'wp_ajax_bkx_save_service_area', array( $this, 'ajax_save_service_area' ) );
		add_action( 'wp_ajax_bkx_delete_service_area', array( $this, 'ajax_delete_service_area' ) );
		add_action( 'wp_ajax_bkx_get_service_area', array( $this, 'ajax_get_service_area' ) );
		add_action( 'wp_ajax_bkx_save_mobile_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_test_maps_api', array( $this, 'ajax_test_maps_api' ) );

		// Booking integration.
		add_filter( 'bkx_booking_price', array( $this, 'apply_distance_pricing' ), 20, 3 );
		add_filter( 'bkx_available_slots', array( $this, 'filter_slots_by_travel_time' ), 10, 4 );
		add_action( 'bkx_booking_created', array( $this, 'save_booking_location' ), 10, 2 );
		add_action( 'bkx_booking_completed', array( $this, 'record_travel_data' ), 10, 2 );

		// Cron.
		add_action( 'bkx_mobile_bookings_daily_cleanup', array( $this, 'daily_cleanup' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Mobile Bookings', 'bkx-mobile-bookings' ),
			__( 'Mobile Bookings', 'bkx-mobile-bookings' ),
			'manage_options',
			'bkx-mobile-bookings',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bookingx_page_bkx-mobile-bookings' !== $hook ) {
			return;
		}

		// Google Maps API.
		$api_key = $this->settings['google_maps_api_key'] ?? '';
		if ( $api_key ) {
			wp_enqueue_script(
				'google-maps-api',
				'https://maps.googleapis.com/maps/api/js?key=' . $api_key . '&libraries=places,geometry,drawing',
				array(),
				null,
				true
			);
		}

		wp_enqueue_style(
			'bkx-mobile-bookings-admin',
			BKX_MOBILE_BOOKINGS_URL . 'assets/css/admin.css',
			array(),
			BKX_MOBILE_BOOKINGS_VERSION
		);

		wp_enqueue_script(
			'bkx-mobile-bookings-admin',
			BKX_MOBILE_BOOKINGS_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			BKX_MOBILE_BOOKINGS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-mobile-bookings-admin',
			'bkxMobileBookings',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'bkx_mobile_bookings_nonce' ),
				'settings'        => $this->settings,
				'has_api_key'     => ! empty( $api_key ),
				'currency_symbol' => function_exists( 'bkx_get_currency_symbol' ) ? bkx_get_currency_symbol() : '$',
				'strings'         => array(
					'confirm_delete'   => __( 'Are you sure you want to delete this?', 'bkx-mobile-bookings' ),
					'error'            => __( 'An error occurred. Please try again.', 'bkx-mobile-bookings' ),
					'saved'            => __( 'Settings saved successfully.', 'bkx-mobile-bookings' ),
					'api_key_required' => __( 'Google Maps API key is required.', 'bkx-mobile-bookings' ),
					'api_test_success' => __( 'API connection successful!', 'bkx-mobile-bookings' ),
					'api_test_failed'  => __( 'API connection failed. Please check your API key.', 'bkx-mobile-bookings' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		if ( ! $this->should_load_frontend() ) {
			return;
		}

		$api_key = $this->settings['google_maps_api_key'] ?? '';
		if ( $api_key ) {
			wp_enqueue_script(
				'google-maps-api',
				'https://maps.googleapis.com/maps/api/js?key=' . $api_key . '&libraries=places,geometry',
				array(),
				null,
				true
			);
		}

		wp_enqueue_style(
			'bkx-mobile-bookings-frontend',
			BKX_MOBILE_BOOKINGS_URL . 'assets/css/frontend.css',
			array(),
			BKX_MOBILE_BOOKINGS_VERSION
		);

		wp_enqueue_script(
			'bkx-mobile-bookings-frontend',
			BKX_MOBILE_BOOKINGS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_MOBILE_BOOKINGS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-mobile-bookings-frontend',
			'bkxMobileBookingsFront',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'bkx_mobile_bookings_nonce' ),
				'settings'        => array(
					'enable_maps'        => $this->settings['enable_maps'] ?? 1,
					'default_zoom'       => $this->settings['default_map_zoom'] ?? 12,
					'default_center_lat' => $this->settings['default_center_lat'] ?? 40.7128,
					'default_center_lng' => $this->settings['default_center_lng'] ?? -74.0060,
					'distance_unit'      => $this->settings['distance_unit'] ?? 'miles',
				),
				'currency_symbol' => function_exists( 'bkx_get_currency_symbol' ) ? bkx_get_currency_symbol() : '$',
				'strings'         => array(
					'calculating'        => __( 'Calculating...', 'bkx-mobile-bookings' ),
					'error'              => __( 'Unable to calculate. Please try again.', 'bkx-mobile-bookings' ),
					'outside_area'       => __( 'This address is outside our service area.', 'bkx-mobile-bookings' ),
					'enter_address'      => __( 'Please enter your address.', 'bkx-mobile-bookings' ),
					'use_my_location'    => __( 'Use my location', 'bkx-mobile-bookings' ),
					'location_not_found' => __( 'Unable to get your location.', 'bkx-mobile-bookings' ),
				),
			)
		);
	}

	/**
	 * Check if frontend assets should be loaded.
	 *
	 * @return bool
	 */
	private function should_load_frontend() {
		global $post;

		if ( is_admin() ) {
			return false;
		}

		// Check if on booking page.
		if ( $post && has_shortcode( $post->post_content, 'bookingx' ) ) {
			return true;
		}

		// Check for BookingX-related post types.
		if ( is_singular( array( 'bkx_seat', 'bkx_base' ) ) ) {
			return true;
		}

		return apply_filters( 'bkx_mobile_bookings_load_frontend', false );
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_MOBILE_BOOKINGS_PATH . 'templates/admin/mobile-bookings.php';
	}

	/**
	 * AJAX: Calculate distance.
	 */
	public function ajax_calculate_distance() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		$from_address = sanitize_text_field( wp_unslash( $_POST['from_address'] ?? '' ) );
		$to_address   = sanitize_text_field( wp_unslash( $_POST['to_address'] ?? '' ) );
		$from_lat     = floatval( $_POST['from_lat'] ?? 0 );
		$from_lng     = floatval( $_POST['from_lng'] ?? 0 );
		$to_lat       = floatval( $_POST['to_lat'] ?? 0 );
		$to_lng       = floatval( $_POST['to_lng'] ?? 0 );

		if ( $from_lat && $from_lng && $to_lat && $to_lng ) {
			$result = $this->services['distance']->calculate_by_coordinates( $from_lat, $from_lng, $to_lat, $to_lng );
		} elseif ( $from_address && $to_address ) {
			$result = $this->services['distance']->calculate_by_addresses( $from_address, $to_address );
		} else {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bkx-mobile-bookings' ) ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Geocode address.
	 */
	public function ajax_geocode_address() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		$address = sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) );

		if ( empty( $address ) ) {
			wp_send_json_error( array( 'message' => __( 'Address is required.', 'bkx-mobile-bookings' ) ) );
		}

		$result = $this->services['google_maps']->geocode( $address );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Check service area.
	 */
	public function ajax_check_service_area() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		$address     = sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) );
		$lat         = floatval( $_POST['lat'] ?? 0 );
		$lng         = floatval( $_POST['lng'] ?? 0 );
		$service_id  = absint( $_POST['service_id'] ?? 0 );
		$provider_id = absint( $_POST['provider_id'] ?? 0 );

		if ( $lat && $lng ) {
			$result = $this->services['service_areas']->check_by_coordinates( $lat, $lng, $service_id, $provider_id );
		} elseif ( $address ) {
			$result = $this->services['service_areas']->check_by_address( $address, $service_id, $provider_id );
		} else {
			wp_send_json_error( array( 'message' => __( 'Address or coordinates required.', 'bkx-mobile-bookings' ) ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get nearby providers.
	 */
	public function ajax_get_nearby_providers() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		$lat        = floatval( $_POST['lat'] ?? 0 );
		$lng        = floatval( $_POST['lng'] ?? 0 );
		$radius     = floatval( $_POST['radius'] ?? ( $this->settings['search_radius_miles'] ?? 10 ) );
		$service_id = absint( $_POST['service_id'] ?? 0 );

		if ( ! $lat || ! $lng ) {
			wp_send_json_error( array( 'message' => __( 'Location required.', 'bkx-mobile-bookings' ) ) );
		}

		$providers = $this->services['tracker']->get_nearby_providers( $lat, $lng, $radius, $service_id );

		wp_send_json_success( array( 'providers' => $providers ) );
	}

	/**
	 * AJAX: GPS check-in.
	 */
	public function ajax_gps_checkin() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'bkx-mobile-bookings' ) ) );
		}

		$booking_id   = absint( $_POST['booking_id'] ?? 0 );
		$lat          = floatval( $_POST['lat'] ?? 0 );
		$lng          = floatval( $_POST['lng'] ?? 0 );
		$accuracy     = floatval( $_POST['accuracy'] ?? 0 );
		$checkin_type = sanitize_text_field( $_POST['checkin_type'] ?? 'arrival' );
		$notes        = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

		if ( ! $booking_id || ! $lat || ! $lng ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bkx-mobile-bookings' ) ) );
		}

		$result = $this->services['tracker']->record_checkin(
			$booking_id,
			get_current_user_id(),
			$lat,
			$lng,
			$accuracy,
			$checkin_type,
			$notes
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Update provider location.
	 */
	public function ajax_update_provider_location() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'bkx-mobile-bookings' ) ) );
		}

		$lat      = floatval( $_POST['lat'] ?? 0 );
		$lng      = floatval( $_POST['lng'] ?? 0 );
		$accuracy = floatval( $_POST['accuracy'] ?? 0 );
		$heading  = floatval( $_POST['heading'] ?? 0 );
		$speed    = floatval( $_POST['speed'] ?? 0 );

		if ( ! $lat || ! $lng ) {
			wp_send_json_error( array( 'message' => __( 'Invalid coordinates.', 'bkx-mobile-bookings' ) ) );
		}

		$result = $this->services['tracker']->update_provider_location(
			get_current_user_id(),
			$lat,
			$lng,
			$accuracy,
			$heading,
			$speed
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Location updated.', 'bkx-mobile-bookings' ) ) );
	}

	/**
	 * AJAX: Optimize route.
	 */
	public function ajax_optimize_route() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-mobile-bookings' ) ) );
		}

		$provider_id = absint( $_POST['provider_id'] ?? 0 );
		$date        = sanitize_text_field( $_POST['date'] ?? '' );

		if ( ! $provider_id || ! $date ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'bkx-mobile-bookings' ) ) );
		}

		$result = $this->services['route']->optimize_daily_route( $provider_id, $date );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get provider route.
	 */
	public function ajax_get_provider_route() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		$provider_id = absint( $_POST['provider_id'] ?? get_current_user_id() );
		$date        = sanitize_text_field( $_POST['date'] ?? gmdate( 'Y-m-d' ) );

		$route = $this->services['route']->get_daily_route( $provider_id, $date );

		wp_send_json_success( $route );
	}

	/**
	 * AJAX: Save service area.
	 */
	public function ajax_save_service_area() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-mobile-bookings' ) ) );
		}

		$data = array(
			'id'                       => absint( $_POST['id'] ?? 0 ),
			'name'                     => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'description'              => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'area_type'                => sanitize_text_field( $_POST['area_type'] ?? 'radius' ),
			'center_latitude'          => floatval( $_POST['center_latitude'] ?? 0 ),
			'center_longitude'         => floatval( $_POST['center_longitude'] ?? 0 ),
			'radius_miles'             => floatval( $_POST['radius_miles'] ?? 0 ),
			'zip_codes'                => sanitize_text_field( wp_unslash( $_POST['zip_codes'] ?? '' ) ),
			'polygon_coordinates'      => sanitize_text_field( wp_unslash( $_POST['polygon_coordinates'] ?? '' ) ),
			'service_id'               => absint( $_POST['service_id'] ?? 0 ),
			'provider_id'              => absint( $_POST['provider_id'] ?? 0 ),
			'distance_pricing_enabled' => isset( $_POST['distance_pricing_enabled'] ) ? 1 : 0,
			'base_travel_fee'          => floatval( $_POST['base_travel_fee'] ?? 0 ),
			'per_mile_rate'            => floatval( $_POST['per_mile_rate'] ?? 0 ),
			'min_distance'             => floatval( $_POST['min_distance'] ?? 0 ),
			'max_distance'             => floatval( $_POST['max_distance'] ?? 0 ),
			'status'                   => sanitize_text_field( $_POST['status'] ?? 'active' ),
		);

		$result = $this->services['service_areas']->save_area( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Service area saved.', 'bkx-mobile-bookings' ), 'id' => $result ) );
	}

	/**
	 * AJAX: Delete service area.
	 */
	public function ajax_delete_service_area() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-mobile-bookings' ) ) );
		}

		$area_id = absint( $_POST['area_id'] ?? 0 );

		if ( ! $area_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid area ID.', 'bkx-mobile-bookings' ) ) );
		}

		$result = $this->services['service_areas']->delete_area( $area_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete service area.', 'bkx-mobile-bookings' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Service area deleted.', 'bkx-mobile-bookings' ) ) );
	}

	/**
	 * AJAX: Get service area.
	 */
	public function ajax_get_service_area() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		$area_id = absint( $_POST['area_id'] ?? 0 );

		if ( ! $area_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid area ID.', 'bkx-mobile-bookings' ) ) );
		}

		$area = $this->services['service_areas']->get_area( $area_id );

		if ( ! $area ) {
			wp_send_json_error( array( 'message' => __( 'Service area not found.', 'bkx-mobile-bookings' ) ) );
		}

		wp_send_json_success( $area );
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-mobile-bookings' ) ) );
		}

		$settings = array(
			'google_maps_api_key'           => sanitize_text_field( $_POST['google_maps_api_key'] ?? '' ),
			'enable_maps'                   => isset( $_POST['enable_maps'] ) ? 1 : 0,
			'default_map_zoom'              => absint( $_POST['default_map_zoom'] ?? 12 ),
			'default_center_lat'            => floatval( $_POST['default_center_lat'] ?? 40.7128 ),
			'default_center_lng'            => floatval( $_POST['default_center_lng'] ?? -74.0060 ),
			'map_style'                     => sanitize_text_field( $_POST['map_style'] ?? 'roadmap' ),
			'enable_traffic_layer'          => isset( $_POST['enable_traffic_layer'] ) ? 1 : 0,
			'distance_unit'                 => sanitize_text_field( $_POST['distance_unit'] ?? 'miles' ),
			'calculation_method'            => sanitize_text_field( $_POST['calculation_method'] ?? 'google_maps' ),
			'include_traffic'               => isset( $_POST['include_traffic'] ) ? 1 : 0,
			'traffic_model'                 => sanitize_text_field( $_POST['traffic_model'] ?? 'best_guess' ),
			'cache_duration_minutes'        => absint( $_POST['cache_duration_minutes'] ?? 30 ),
			'add_travel_buffer'             => isset( $_POST['add_travel_buffer'] ) ? 1 : 0,
			'travel_buffer_percentage'      => absint( $_POST['travel_buffer_percentage'] ?? 20 ),
			'min_buffer_minutes'            => absint( $_POST['min_buffer_minutes'] ?? 10 ),
			'max_buffer_minutes'            => absint( $_POST['max_buffer_minutes'] ?? 60 ),
			'enforce_service_areas'         => isset( $_POST['enforce_service_areas'] ) ? 1 : 0,
			'show_coverage_map'             => isset( $_POST['show_coverage_map'] ) ? 1 : 0,
			'allow_outside_area_requests'   => isset( $_POST['allow_outside_area_requests'] ) ? 1 : 0,
			'default_radius_miles'          => floatval( $_POST['default_radius_miles'] ?? 25 ),
			'enable_distance_pricing'       => isset( $_POST['enable_distance_pricing'] ) ? 1 : 0,
			'base_travel_fee'               => floatval( $_POST['base_travel_fee'] ?? 0 ),
			'per_mile_rate'                 => floatval( $_POST['per_mile_rate'] ?? 0 ),
			'free_distance_miles'           => floatval( $_POST['free_distance_miles'] ?? 0 ),
			'max_distance_miles'            => floatval( $_POST['max_distance_miles'] ?? 50 ),
			'enable_route_optimization'     => isset( $_POST['enable_route_optimization'] ) ? 1 : 0,
			'auto_optimize_daily'           => isset( $_POST['auto_optimize_daily'] ) ? 1 : 0,
			'enable_gps_checkin'            => isset( $_POST['enable_gps_checkin'] ) ? 1 : 0,
			'verification_radius_meters'    => absint( $_POST['verification_radius_meters'] ?? 100 ),
			'require_gps_verification'      => isset( $_POST['require_gps_verification'] ) ? 1 : 0,
			'track_provider_location'       => isset( $_POST['track_provider_location'] ) ? 1 : 0,
			'location_update_interval'      => absint( $_POST['location_update_interval'] ?? 300 ),
			'enable_nearby_provider_search' => isset( $_POST['enable_nearby_provider_search'] ) ? 1 : 0,
			'search_radius_miles'           => floatval( $_POST['search_radius_miles'] ?? 10 ),
			'show_provider_eta'             => isset( $_POST['show_provider_eta'] ) ? 1 : 0,
			'notify_customer_on_route'      => isset( $_POST['notify_customer_on_route'] ) ? 1 : 0,
			'notify_eta_changes'            => isset( $_POST['notify_eta_changes'] ) ? 1 : 0,
			'delete_data_on_uninstall'      => isset( $_POST['delete_data_on_uninstall'] ) ? 1 : 0,
		);

		update_option( 'bkx_mobile_bookings_settings', $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'bkx-mobile-bookings' ) ) );
	}

	/**
	 * AJAX: Test Maps API.
	 */
	public function ajax_test_maps_api() {
		check_ajax_referer( 'bkx_mobile_bookings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-mobile-bookings' ) ) );
		}

		$api_key = sanitize_text_field( $_POST['api_key'] ?? '' );

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'API key is required.', 'bkx-mobile-bookings' ) ) );
		}

		// Test geocoding API.
		$test_service = new GoogleMapsService( array( 'google_maps_api_key' => $api_key ) );
		$result       = $test_service->geocode( '1600 Amphitheatre Parkway, Mountain View, CA' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'API connection successful!', 'bkx-mobile-bookings' ) ) );
	}

	/**
	 * Apply distance-based pricing to booking.
	 *
	 * @param float $price       Original price.
	 * @param int   $booking_id  Booking ID.
	 * @param array $booking_data Booking data.
	 * @return float
	 */
	public function apply_distance_pricing( $price, $booking_id, $booking_data ) {
		if ( empty( $this->settings['enable_distance_pricing'] ) ) {
			return $price;
		}

		$distance_fee = $this->services['pricing']->calculate_fee( $booking_data );

		if ( $distance_fee > 0 ) {
			$price += $distance_fee;
		}

		return $price;
	}

	/**
	 * Filter available slots by travel time.
	 *
	 * @param array  $slots       Available slots.
	 * @param int    $service_id  Service ID.
	 * @param int    $provider_id Provider ID.
	 * @param string $date        Date.
	 * @return array
	 */
	public function filter_slots_by_travel_time( $slots, $service_id, $provider_id, $date ) {
		if ( empty( $this->settings['add_travel_buffer'] ) ) {
			return $slots;
		}

		return $this->services['travel_time']->filter_slots_by_travel( $slots, $provider_id, $date );
	}

	/**
	 * Save booking location data.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function save_booking_location( $booking_id, $booking_data ) {
		if ( empty( $booking_data['customer_address'] ) ) {
			return;
		}

		$location_data = array(
			'booking_id'    => $booking_id,
			'customer_id'   => $booking_data['customer_id'] ?? 0,
			'location_type' => 'customer',
			'address'       => $booking_data['customer_address'],
		);

		// Geocode if coordinates not provided.
		if ( empty( $booking_data['customer_lat'] ) || empty( $booking_data['customer_lng'] ) ) {
			$geocoded = $this->services['google_maps']->geocode( $booking_data['customer_address'] );

			if ( ! is_wp_error( $geocoded ) ) {
				$location_data['latitude']          = $geocoded['lat'];
				$location_data['longitude']         = $geocoded['lng'];
				$location_data['formatted_address'] = $geocoded['formatted_address'];
				$location_data['place_id']          = $geocoded['place_id'] ?? '';
			}
		} else {
			$location_data['latitude']  = $booking_data['customer_lat'];
			$location_data['longitude'] = $booking_data['customer_lng'];
		}

		$this->services['tracker']->save_location( $location_data );
	}

	/**
	 * Record travel data after booking completion.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function record_travel_data( $booking_id, $booking_data ) {
		// Implementation for recording final travel data.
	}

	/**
	 * Daily cleanup task.
	 */
	public function daily_cleanup() {
		global $wpdb;

		// Clean old provider location data (older than 7 days).
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bkx_provider_locations WHERE last_updated < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) )
			)
		);

		// Clean old travel time cache.
		delete_transient( 'bkx_distance_cache' );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bookingx/v1',
			'/location/calculate-distance',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_calculate_distance' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bookingx/v1',
			'/location/check-service-area',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_check_service_area' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bookingx/v1',
			'/service-areas',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'rest_get_service_areas' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_create_service_area' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);

		register_rest_route(
			'bookingx/v1',
			'/provider-routes/(?P<provider_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_provider_route' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);

		register_rest_route(
			'bookingx/v1',
			'/mobile/check-in',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_gps_checkin' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			)
		);
	}

	/**
	 * REST: Calculate distance.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_calculate_distance( $request ) {
		$from_lat = $request->get_param( 'from_lat' );
		$from_lng = $request->get_param( 'from_lng' );
		$to_lat   = $request->get_param( 'to_lat' );
		$to_lng   = $request->get_param( 'to_lng' );

		$result = $this->services['distance']->calculate_by_coordinates( $from_lat, $from_lng, $to_lat, $to_lng );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * REST: Check service area.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_check_service_area( $request ) {
		$lat = $request->get_param( 'lat' );
		$lng = $request->get_param( 'lng' );

		$result = $this->services['service_areas']->check_by_coordinates( $lat, $lng );

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * REST: Get service areas.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_service_areas( $request ) {
		$areas = $this->services['service_areas']->get_areas();

		return new \WP_REST_Response( $areas, 200 );
	}

	/**
	 * REST: Create service area.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_create_service_area( $request ) {
		$data   = $request->get_params();
		$result = $this->services['service_areas']->save_area( $data );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}

		return new \WP_REST_Response( array( 'id' => $result ), 201 );
	}

	/**
	 * REST: Get provider route.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_provider_route( $request ) {
		$provider_id = $request->get_param( 'provider_id' );
		$date        = $request->get_param( 'date' ) ?? gmdate( 'Y-m-d' );

		$route = $this->services['route']->get_daily_route( $provider_id, $date );

		return new \WP_REST_Response( $route, 200 );
	}

	/**
	 * REST: GPS check-in.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_gps_checkin( $request ) {
		$result = $this->services['tracker']->record_checkin(
			$request->get_param( 'booking_id' ),
			get_current_user_id(),
			$request->get_param( 'lat' ),
			$request->get_param( 'lng' ),
			$request->get_param( 'accuracy' ),
			$request->get_param( 'checkin_type' ) ?? 'arrival',
			$request->get_param( 'notes' ) ?? ''
		);

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Get a service instance.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		return $this->settings;
	}
}

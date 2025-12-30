<?php
/**
 * Main Mobile Optimize Addon Class.
 *
 * @package BookingX\MobileOptimize
 */

namespace BookingX\MobileOptimize;

defined( 'ABSPATH' ) || exit;

/**
 * MobileOptimizeAddon class.
 */
class MobileOptimizeAddon {

	/**
	 * Singleton instance.
	 *
	 * @var MobileOptimizeAddon
	 */
	private static $instance = null;

	/**
	 * Settings.
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
	 * @return MobileOptimizeAddon
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
		$this->settings = get_option( 'bkx_mobile_optimize_settings', array() );
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['device_detector']   = new Services\DeviceDetector();
		$this->services['responsive_form']   = new Services\ResponsiveFormService();
		$this->services['touch_optimizer']   = new Services\TouchOptimizer();
		$this->services['mobile_ui']         = new Services\MobileUIService();
		$this->services['performance']       = new Services\PerformanceOptimizer();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin hooks.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
			add_action( 'bkx_settings_tab_mobile', array( $this, 'render_settings_tab' ) );
		}

		// Frontend hooks.
		if ( ! is_admin() && $this->is_enabled() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
			add_filter( 'body_class', array( $this, 'add_body_classes' ) );
			add_action( 'wp_head', array( $this, 'add_mobile_meta_tags' ) );
			add_action( 'wp_footer', array( $this, 'render_mobile_ui_components' ) );

			// Form modifications.
			add_filter( 'bkx_booking_form_attributes', array( $this, 'modify_form_attributes' ) );
			add_filter( 'bkx_form_field_attributes', array( $this, 'modify_field_attributes' ), 10, 2 );

			// Calendar modifications.
			add_filter( 'bkx_calendar_config', array( $this, 'modify_calendar_config' ) );
		}

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_mobile_one_tap_book', array( $this, 'ajax_one_tap_book' ) );
		add_action( 'wp_ajax_nopriv_bkx_mobile_one_tap_book', array( $this, 'ajax_one_tap_book' ) );
		add_action( 'wp_ajax_bkx_mobile_detect_location', array( $this, 'ajax_detect_location' ) );
		add_action( 'wp_ajax_nopriv_bkx_mobile_detect_location', array( $this, 'ajax_detect_location' ) );
	}

	/**
	 * Check if mobile optimization is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->settings['enabled'] );
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
	 * Get setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Mobile Optimization', 'bkx-mobile-optimize' ),
			__( 'Mobile', 'bkx-mobile-optimize' ),
			'manage_options',
			'bkx-mobile-optimize',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['mobile'] = __( 'Mobile', 'bkx-mobile-optimize' );
		return $tabs;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-mobile-optimize' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-mobile-optimize-admin',
			BKX_MOBILE_OPTIMIZE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_MOBILE_OPTIMIZE_VERSION
		);

		wp_enqueue_script(
			'bkx-mobile-optimize-admin',
			BKX_MOBILE_OPTIMIZE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_MOBILE_OPTIMIZE_VERSION,
			true
		);
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		$detector = $this->services['device_detector'];

		// Only load on mobile/tablet if not forced.
		if ( ! $detector->is_mobile() && ! $detector->is_tablet() ) {
			return;
		}

		// Main mobile styles.
		wp_enqueue_style(
			'bkx-mobile-optimize',
			BKX_MOBILE_OPTIMIZE_PLUGIN_URL . 'assets/css/mobile.css',
			array(),
			BKX_MOBILE_OPTIMIZE_VERSION
		);

		// Touch-friendly styles.
		if ( $this->get_setting( 'touch_friendly' ) ) {
			wp_enqueue_style(
				'bkx-mobile-touch',
				BKX_MOBILE_OPTIMIZE_PLUGIN_URL . 'assets/css/touch.css',
				array( 'bkx-mobile-optimize' ),
				BKX_MOBILE_OPTIMIZE_VERSION
			);
		}

		// Main mobile JavaScript.
		wp_enqueue_script(
			'bkx-mobile-optimize',
			BKX_MOBILE_OPTIMIZE_PLUGIN_URL . 'assets/js/mobile.js',
			array( 'jquery' ),
			BKX_MOBILE_OPTIMIZE_VERSION,
			true
		);

		// Touch gestures.
		if ( $this->get_setting( 'swipe_calendar' ) ) {
			wp_enqueue_script(
				'bkx-mobile-gestures',
				BKX_MOBILE_OPTIMIZE_PLUGIN_URL . 'assets/js/gestures.js',
				array( 'bkx-mobile-optimize' ),
				BKX_MOBILE_OPTIMIZE_VERSION,
				true
			);
		}

		wp_localize_script(
			'bkx-mobile-optimize',
			'bkxMobile',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'bkx_mobile_optimize' ),
				'isMobile'         => $detector->is_mobile(),
				'isTablet'         => $detector->is_tablet(),
				'isIos'            => $detector->is_ios(),
				'isAndroid'        => $detector->is_android(),
				'floatingCta'      => $this->get_setting( 'floating_cta', true ),
				'bottomSheet'      => $this->get_setting( 'bottom_sheet_picker', true ),
				'hapticFeedback'   => $this->get_setting( 'haptic_feedback', true ),
				'oneTapBooking'    => $this->get_setting( 'one_tap_booking', false ),
				'expressCheckout'  => $this->get_setting( 'express_checkout', true ),
				'smartAutofill'    => $this->get_setting( 'smart_autofill', true ),
				'skeletonLoading'  => $this->get_setting( 'skeleton_loading', true ),
				'reducedMotion'    => $this->get_setting( 'reduced_motion', true ),
				'mobileBreakpoint' => $this->get_setting( 'mobile_breakpoint', 768 ),
				'tabletBreakpoint' => $this->get_setting( 'tablet_breakpoint', 1024 ),
				'i18n'             => array(
					'loading'     => __( 'Loading...', 'bkx-mobile-optimize' ),
					'bookNow'     => __( 'Book Now', 'bkx-mobile-optimize' ),
					'selectDate'  => __( 'Select Date', 'bkx-mobile-optimize' ),
					'selectTime'  => __( 'Select Time', 'bkx-mobile-optimize' ),
					'confirm'     => __( 'Confirm', 'bkx-mobile-optimize' ),
					'back'        => __( 'Back', 'bkx-mobile-optimize' ),
					'next'        => __( 'Next', 'bkx-mobile-optimize' ),
				),
			)
		);
	}

	/**
	 * Add body classes for mobile detection.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function add_body_classes( $classes ) {
		$detector = $this->services['device_detector'];

		if ( $detector->is_mobile() ) {
			$classes[] = 'bkx-mobile';
		}

		if ( $detector->is_tablet() ) {
			$classes[] = 'bkx-tablet';
		}

		if ( $detector->is_ios() ) {
			$classes[] = 'bkx-ios';
		}

		if ( $detector->is_android() ) {
			$classes[] = 'bkx-android';
		}

		if ( $this->get_setting( 'touch_friendly' ) ) {
			$classes[] = 'bkx-touch-friendly';
		}

		return $classes;
	}

	/**
	 * Add mobile meta tags.
	 */
	public function add_mobile_meta_tags() {
		$detector = $this->services['device_detector'];

		if ( ! $detector->is_mobile() && ! $detector->is_tablet() ) {
			return;
		}

		// Disable zooming on forms for better UX.
		if ( $this->get_setting( 'keyboard_optimization' ) ) {
			echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">' . "\n";
		}

		// Format detection.
		echo '<meta name="format-detection" content="telephone=yes">' . "\n";
	}

	/**
	 * Modify form attributes for mobile.
	 *
	 * @param array $attributes Form attributes.
	 * @return array
	 */
	public function modify_form_attributes( $attributes ) {
		$detector = $this->services['device_detector'];

		if ( ! $detector->is_mobile() && ! $detector->is_tablet() ) {
			return $attributes;
		}

		$attributes['class']          = ( $attributes['class'] ?? '' ) . ' bkx-mobile-form';
		$attributes['data-mobile']    = 'true';
		$attributes['autocomplete']   = 'on';
		$attributes['data-autofill']  = $this->get_setting( 'smart_autofill' ) ? 'true' : 'false';

		return $attributes;
	}

	/**
	 * Modify form field attributes for mobile.
	 *
	 * @param array  $attributes Field attributes.
	 * @param string $field_type Field type.
	 * @return array
	 */
	public function modify_field_attributes( $attributes, $field_type ) {
		$detector = $this->services['device_detector'];

		if ( ! $detector->is_mobile() && ! $detector->is_tablet() ) {
			return $attributes;
		}

		// Add mobile-friendly input types and attributes.
		switch ( $field_type ) {
			case 'email':
				$attributes['inputmode']    = 'email';
				$attributes['autocomplete'] = 'email';
				break;

			case 'phone':
			case 'tel':
				$attributes['type']         = 'tel';
				$attributes['inputmode']    = 'tel';
				$attributes['autocomplete'] = 'tel';
				break;

			case 'number':
				$attributes['inputmode'] = 'numeric';
				break;

			case 'date':
				if ( $detector->is_mobile() ) {
					$attributes['type'] = 'date';
				}
				break;

			case 'time':
				if ( $detector->is_mobile() ) {
					$attributes['type'] = 'time';
				}
				break;
		}

		// Touch-friendly class.
		$attributes['class'] = ( $attributes['class'] ?? '' ) . ' bkx-mobile-input';

		return $attributes;
	}

	/**
	 * Modify calendar config for mobile.
	 *
	 * @param array $config Calendar config.
	 * @return array
	 */
	public function modify_calendar_config( $config ) {
		$detector = $this->services['device_detector'];

		if ( ! $detector->is_mobile() && ! $detector->is_tablet() ) {
			return $config;
		}

		// Enable swipe navigation.
		if ( $this->get_setting( 'swipe_calendar' ) ) {
			$config['enableSwipe'] = true;
		}

		// Larger touch targets.
		$config['daySize']       = 44; // Minimum 44px for touch.
		$config['showWeekNumber'] = false;

		return $config;
	}

	/**
	 * Render mobile UI components.
	 */
	public function render_mobile_ui_components() {
		$detector = $this->services['device_detector'];

		if ( ! $detector->is_mobile() && ! $detector->is_tablet() ) {
			return;
		}

		// Floating CTA.
		if ( $this->get_setting( 'floating_cta' ) ) {
			include BKX_MOBILE_OPTIMIZE_PLUGIN_DIR . 'templates/floating-cta.php';
		}

		// Bottom sheet picker.
		if ( $this->get_setting( 'bottom_sheet_picker' ) ) {
			include BKX_MOBILE_OPTIMIZE_PLUGIN_DIR . 'templates/bottom-sheet.php';
		}
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_MOBILE_OPTIMIZE_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab() {
		include BKX_MOBILE_OPTIMIZE_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * AJAX: One tap booking.
	 */
	public function ajax_one_tap_book() {
		check_ajax_referer( 'bkx_mobile_optimize', 'nonce' );

		$service_id  = absint( $_POST['service_id'] ?? 0 );
		$resource_id = absint( $_POST['resource_id'] ?? 0 );
		$date        = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
		$time        = sanitize_text_field( wp_unslash( $_POST['time'] ?? '' ) );

		if ( ! $service_id || ! $date || ! $time ) {
			wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'bkx-mobile-optimize' ) ) );
		}

		// Create quick booking.
		$booking_data = array(
			'service_id'  => $service_id,
			'resource_id' => $resource_id,
			'date'        => $date,
			'time'        => $time,
			'source'      => 'one_tap',
		);

		// Get user data if logged in.
		if ( is_user_logged_in() ) {
			$user                        = wp_get_current_user();
			$booking_data['customer_id'] = $user->ID;
			$booking_data['email']       = $user->user_email;
			$booking_data['name']        = $user->display_name;
		}

		// Create booking via BookingX.
		if ( class_exists( 'BkxBooking' ) ) {
			$booking = new \BkxBooking();
			$result  = $booking->create_booking( $booking_data );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			wp_send_json_success(
				array(
					'booking_id'  => $result,
					'redirect_url' => get_permalink( $result ),
				)
			);
		}

		wp_send_json_error( array( 'message' => __( 'Booking system unavailable.', 'bkx-mobile-optimize' ) ) );
	}

	/**
	 * AJAX: Detect location.
	 */
	public function ajax_detect_location() {
		check_ajax_referer( 'bkx_mobile_optimize', 'nonce' );

		$lat = floatval( $_POST['lat'] ?? 0 );
		$lng = floatval( $_POST['lng'] ?? 0 );

		if ( ! $lat || ! $lng ) {
			wp_send_json_error( array( 'message' => __( 'Invalid coordinates.', 'bkx-mobile-optimize' ) ) );
		}

		// Find nearest locations/resources.
		$nearest = $this->find_nearest_locations( $lat, $lng );

		wp_send_json_success( array( 'locations' => $nearest ) );
	}

	/**
	 * Find nearest locations.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return array
	 */
	private function find_nearest_locations( $lat, $lng ) {
		// Get all resources/locations with coordinates.
		$resources = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'     => '_location_lat',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$locations = array();

		foreach ( $resources as $resource ) {
			$resource_lat = floatval( get_post_meta( $resource->ID, '_location_lat', true ) );
			$resource_lng = floatval( get_post_meta( $resource->ID, '_location_lng', true ) );

			if ( ! $resource_lat || ! $resource_lng ) {
				continue;
			}

			$distance = $this->calculate_distance( $lat, $lng, $resource_lat, $resource_lng );

			$locations[] = array(
				'id'       => $resource->ID,
				'name'     => $resource->post_title,
				'distance' => $distance,
				'unit'     => 'km',
			);
		}

		// Sort by distance.
		usort(
			$locations,
			function ( $a, $b ) {
				return $a['distance'] <=> $b['distance'];
			}
		);

		return array_slice( $locations, 0, 5 );
	}

	/**
	 * Calculate distance between two coordinates.
	 *
	 * @param float $lat1 Latitude 1.
	 * @param float $lng1 Longitude 1.
	 * @param float $lat2 Latitude 2.
	 * @param float $lng2 Longitude 2.
	 * @return float Distance in km.
	 */
	private function calculate_distance( $lat1, $lng1, $lat2, $lng2 ) {
		$earth_radius = 6371; // km

		$lat_diff = deg2rad( $lat2 - $lat1 );
		$lng_diff = deg2rad( $lng2 - $lng1 );

		$a = sin( $lat_diff / 2 ) * sin( $lat_diff / 2 ) +
			 cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) *
			 sin( $lng_diff / 2 ) * sin( $lng_diff / 2 );

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return round( $earth_radius * $c, 2 );
	}
}

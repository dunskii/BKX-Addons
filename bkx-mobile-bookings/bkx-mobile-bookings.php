<?php
/**
 * Plugin Name: BookingX - Mobile Bookings Advanced
 * Plugin URI: https://bookingx.com/addons/mobile-bookings-advanced
 * Description: Advanced mobile booking features with Google Maps integration, travel time calculation, distance-based scheduling, and location-aware booking optimization.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-mobile-bookings
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\MobileBookings
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_MOBILE_BOOKINGS_VERSION', '1.0.0' );
define( 'BKX_MOBILE_BOOKINGS_FILE', __FILE__ );
define( 'BKX_MOBILE_BOOKINGS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_MOBILE_BOOKINGS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_MOBILE_BOOKINGS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if BookingX core plugin is active.
 *
 * @return bool
 */
function bkx_mobile_bookings_check_requirements() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_mobile_bookings_missing_core_notice' );
		return false;
	}
	return true;
}

/**
 * Admin notice for missing BookingX core.
 */
function bkx_mobile_bookings_missing_core_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX - Mobile Bookings Advanced requires BookingX core plugin to be installed and activated.', 'bkx-mobile-bookings' ); ?></p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_mobile_bookings_init() {
	if ( ! bkx_mobile_bookings_check_requirements() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_MOBILE_BOOKINGS_PATH . 'src/autoload.php';

	// Initialize the addon.
	$addon = new BookingX\MobileBookings\MobileBookingsAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_mobile_bookings_init', 20 );

/**
 * Activation hook.
 */
function bkx_mobile_bookings_activate() {
	// Create database tables.
	bkx_mobile_bookings_create_tables();

	// Set default options.
	bkx_mobile_bookings_set_defaults();

	// Schedule cron events.
	if ( ! wp_next_scheduled( 'bkx_mobile_bookings_daily_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_mobile_bookings_daily_cleanup' );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_mobile_bookings_activate' );

/**
 * Deactivation hook.
 */
function bkx_mobile_bookings_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_mobile_bookings_daily_cleanup' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_mobile_bookings_deactivate' );

/**
 * Create database tables.
 */
function bkx_mobile_bookings_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Locations table.
	$table_locations = $wpdb->prefix . 'bkx_locations';
	$sql_locations   = "CREATE TABLE {$table_locations} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		booking_id BIGINT(20) UNSIGNED DEFAULT NULL,
		customer_id BIGINT(20) UNSIGNED DEFAULT NULL,
		provider_id BIGINT(20) UNSIGNED DEFAULT NULL,
		location_type VARCHAR(50) NOT NULL DEFAULT 'customer',
		address VARCHAR(500) NOT NULL,
		address_line_2 VARCHAR(255) DEFAULT NULL,
		city VARCHAR(100) DEFAULT NULL,
		state VARCHAR(100) DEFAULT NULL,
		zip_code VARCHAR(20) DEFAULT NULL,
		country VARCHAR(100) DEFAULT NULL,
		latitude DECIMAL(10, 8) DEFAULT NULL,
		longitude DECIMAL(11, 8) DEFAULT NULL,
		formatted_address TEXT,
		place_id VARCHAR(255) DEFAULT NULL,
		location_notes TEXT,
		is_verified TINYINT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY booking_id_idx (booking_id),
		KEY customer_id_idx (customer_id),
		KEY provider_id_idx (provider_id),
		KEY location_type_idx (location_type)
	) {$charset_collate};";

	// Travel times table.
	$table_travel_times = $wpdb->prefix . 'bkx_travel_times';
	$sql_travel_times   = "CREATE TABLE {$table_travel_times} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		booking_id BIGINT(20) UNSIGNED NOT NULL,
		provider_id BIGINT(20) UNSIGNED NOT NULL,
		from_location_id BIGINT(20) UNSIGNED NOT NULL,
		to_location_id BIGINT(20) UNSIGNED NOT NULL,
		distance_km DECIMAL(10, 2) NOT NULL DEFAULT 0,
		distance_miles DECIMAL(10, 2) NOT NULL DEFAULT 0,
		duration_minutes INT NOT NULL DEFAULT 0,
		duration_in_traffic_minutes INT DEFAULT NULL,
		route_polyline TEXT,
		traffic_model VARCHAR(50) DEFAULT NULL,
		departure_time DATETIME DEFAULT NULL,
		calculated_at DATETIME NOT NULL,
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY booking_id_idx (booking_id),
		KEY provider_id_idx (provider_id),
		KEY from_location_idx (from_location_id),
		KEY to_location_idx (to_location_id)
	) {$charset_collate};";

	// Service areas table.
	$table_service_areas = $wpdb->prefix . 'bkx_service_areas';
	$sql_service_areas   = "CREATE TABLE {$table_service_areas} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(255) NOT NULL,
		description TEXT,
		service_id BIGINT(20) UNSIGNED DEFAULT NULL,
		provider_id BIGINT(20) UNSIGNED DEFAULT NULL,
		area_type VARCHAR(50) NOT NULL DEFAULT 'radius',
		center_latitude DECIMAL(10, 8) DEFAULT NULL,
		center_longitude DECIMAL(11, 8) DEFAULT NULL,
		radius_km DECIMAL(10, 2) DEFAULT NULL,
		radius_miles DECIMAL(10, 2) DEFAULT NULL,
		zip_codes TEXT,
		polygon_coordinates LONGTEXT,
		cities TEXT,
		states TEXT,
		distance_pricing_enabled TINYINT(1) NOT NULL DEFAULT 0,
		base_travel_fee DECIMAL(10, 2) NOT NULL DEFAULT 0,
		per_km_rate DECIMAL(10, 2) NOT NULL DEFAULT 0,
		per_mile_rate DECIMAL(10, 2) NOT NULL DEFAULT 0,
		min_distance DECIMAL(10, 2) NOT NULL DEFAULT 0,
		max_distance DECIMAL(10, 2) DEFAULT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'active',
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY service_id_idx (service_id),
		KEY provider_id_idx (provider_id),
		KEY status_idx (status)
	) {$charset_collate};";

	// Provider routes table.
	$table_provider_routes = $wpdb->prefix . 'bkx_provider_routes';
	$sql_provider_routes   = "CREATE TABLE {$table_provider_routes} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		provider_id BIGINT(20) UNSIGNED NOT NULL,
		route_date DATE NOT NULL,
		start_location_id BIGINT(20) UNSIGNED DEFAULT NULL,
		end_location_id BIGINT(20) UNSIGNED DEFAULT NULL,
		total_distance_km DECIMAL(10, 2) DEFAULT NULL,
		total_distance_miles DECIMAL(10, 2) DEFAULT NULL,
		total_travel_time_minutes INT DEFAULT NULL,
		total_bookings INT NOT NULL DEFAULT 0,
		route_order LONGTEXT,
		is_optimized TINYINT(1) NOT NULL DEFAULT 0,
		optimized_at DATETIME DEFAULT NULL,
		route_polyline TEXT,
		status VARCHAR(20) NOT NULL DEFAULT 'planned',
		created_at DATETIME NOT NULL,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY (id),
		KEY provider_id_idx (provider_id),
		KEY route_date_idx (route_date),
		KEY status_idx (status),
		UNIQUE KEY provider_date_idx (provider_id, route_date)
	) {$charset_collate};";

	// GPS check-ins table.
	$table_gps_checkins = $wpdb->prefix . 'bkx_gps_checkins';
	$sql_gps_checkins   = "CREATE TABLE {$table_gps_checkins} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		booking_id BIGINT(20) UNSIGNED NOT NULL,
		provider_id BIGINT(20) UNSIGNED NOT NULL,
		checkin_type VARCHAR(50) NOT NULL DEFAULT 'arrival',
		latitude DECIMAL(10, 8) NOT NULL,
		longitude DECIMAL(11, 8) NOT NULL,
		accuracy DECIMAL(10, 2) DEFAULT NULL,
		distance_from_location DECIMAL(10, 2) DEFAULT NULL,
		is_verified TINYINT(1) NOT NULL DEFAULT 0,
		verification_radius DECIMAL(10, 2) NOT NULL DEFAULT 100,
		checkin_time DATETIME NOT NULL,
		device_info TEXT,
		notes TEXT,
		PRIMARY KEY (id),
		KEY booking_id_idx (booking_id),
		KEY provider_id_idx (provider_id),
		KEY checkin_time_idx (checkin_time),
		KEY checkin_type_idx (checkin_type)
	) {$charset_collate};";

	// Provider locations (real-time tracking) table.
	$table_provider_locations = $wpdb->prefix . 'bkx_provider_locations';
	$sql_provider_locations   = "CREATE TABLE {$table_provider_locations} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		provider_id BIGINT(20) UNSIGNED NOT NULL,
		latitude DECIMAL(10, 8) NOT NULL,
		longitude DECIMAL(11, 8) NOT NULL,
		accuracy DECIMAL(10, 2) DEFAULT NULL,
		heading DECIMAL(5, 2) DEFAULT NULL,
		speed DECIMAL(10, 2) DEFAULT NULL,
		is_available TINYINT(1) NOT NULL DEFAULT 1,
		last_updated DATETIME NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY provider_id_idx (provider_id),
		KEY last_updated_idx (last_updated)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( $sql_locations );
	dbDelta( $sql_travel_times );
	dbDelta( $sql_service_areas );
	dbDelta( $sql_provider_routes );
	dbDelta( $sql_gps_checkins );
	dbDelta( $sql_provider_locations );

	update_option( 'bkx_mobile_bookings_db_version', BKX_MOBILE_BOOKINGS_VERSION );
}

/**
 * Set default options.
 */
function bkx_mobile_bookings_set_defaults() {
	$defaults = array(
		'google_maps_api_key'           => '',
		'enable_maps'                   => 1,
		'default_map_zoom'              => 12,
		'default_center_lat'            => 40.7128,
		'default_center_lng'            => -74.0060,
		'map_style'                     => 'roadmap',
		'enable_traffic_layer'          => 1,
		'distance_unit'                 => 'miles',
		'calculation_method'            => 'google_maps',
		'include_traffic'               => 1,
		'traffic_model'                 => 'best_guess',
		'cache_duration_minutes'        => 30,
		'add_travel_buffer'             => 1,
		'travel_buffer_percentage'      => 20,
		'min_buffer_minutes'            => 10,
		'max_buffer_minutes'            => 60,
		'enforce_service_areas'         => 0,
		'show_coverage_map'             => 1,
		'allow_outside_area_requests'   => 1,
		'default_radius_miles'          => 25,
		'enable_distance_pricing'       => 0,
		'base_travel_fee'               => 0,
		'per_mile_rate'                 => 0,
		'free_distance_miles'           => 0,
		'max_distance_miles'            => 50,
		'enable_route_optimization'     => 1,
		'auto_optimize_daily'           => 0,
		'enable_gps_checkin'            => 1,
		'verification_radius_meters'    => 100,
		'require_gps_verification'      => 0,
		'track_provider_location'       => 0,
		'location_update_interval'      => 300,
		'enable_nearby_provider_search' => 1,
		'search_radius_miles'           => 10,
		'show_provider_eta'             => 1,
		'notify_customer_on_route'      => 1,
		'notify_eta_changes'            => 1,
		'delete_data_on_uninstall'      => 0,
	);

	$existing = get_option( 'bkx_mobile_bookings_settings', array() );
	$settings = wp_parse_args( $existing, $defaults );

	update_option( 'bkx_mobile_bookings_settings', $settings );
}

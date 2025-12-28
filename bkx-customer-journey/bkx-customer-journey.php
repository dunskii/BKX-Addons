<?php
/**
 * Plugin Name:       BookingX Customer Journey Analytics
 * Plugin URI:        https://flavflavor.dev/bookingx/addons/customer-journey
 * Description:       Track and analyze customer journeys, touchpoints, and lifecycle stages for booking optimization.
 * Version:           1.0.0
 * Author:            flavflavor.dev
 * Author URI:        https://flavflavor.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bkx-customer-journey
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package BookingX\CustomerJourney
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'BKX_CUSTOMER_JOURNEY_VERSION', '1.0.0' );
define( 'BKX_CUSTOMER_JOURNEY_FILE', __FILE__ );
define( 'BKX_CUSTOMER_JOURNEY_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_CUSTOMER_JOURNEY_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check dependencies.
 */
function bkx_customer_journey_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'BookingX Customer Journey Analytics requires BookingX to be installed and activated.', 'bkx-customer-journey' );
			echo '</p></div>';
		} );
		return false;
	}
	return true;
}

/**
 * Initialize plugin.
 */
function bkx_customer_journey_init() {
	if ( ! bkx_customer_journey_check_dependencies() ) {
		return;
	}

	require_once BKX_CUSTOMER_JOURNEY_PATH . 'src/autoload.php';

	$addon = new \BookingX\CustomerJourney\CustomerJourneyAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_customer_journey_init' );

/**
 * Activation hook.
 */
function bkx_customer_journey_activate() {
	bkx_customer_journey_create_tables();
	update_option( 'bkx_customer_journey_version', BKX_CUSTOMER_JOURNEY_VERSION );
}
register_activation_hook( __FILE__, 'bkx_customer_journey_activate' );

/**
 * Create database tables.
 */
function bkx_customer_journey_create_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Touchpoints table.
	$table_touchpoints = $wpdb->prefix . 'bkx_cj_touchpoints';
	$sql_touchpoints   = "CREATE TABLE IF NOT EXISTS {$table_touchpoints} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		session_id varchar(64) NOT NULL,
		customer_id bigint(20) unsigned DEFAULT NULL,
		customer_email varchar(100) DEFAULT NULL,
		touchpoint_type varchar(50) NOT NULL,
		touchpoint_data longtext DEFAULT NULL,
		page_url varchar(500) DEFAULT NULL,
		referrer varchar(500) DEFAULT NULL,
		device_type varchar(20) DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY session_id (session_id),
		KEY customer_id (customer_id),
		KEY touchpoint_type (touchpoint_type),
		KEY created_at (created_at)
	) {$charset_collate};";

	// Customer lifecycle table.
	$table_lifecycle = $wpdb->prefix . 'bkx_cj_lifecycle';
	$sql_lifecycle   = "CREATE TABLE IF NOT EXISTS {$table_lifecycle} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned DEFAULT NULL,
		customer_email varchar(100) NOT NULL,
		lifecycle_stage varchar(30) NOT NULL DEFAULT 'lead',
		first_touch datetime DEFAULT NULL,
		first_booking datetime DEFAULT NULL,
		last_booking datetime DEFAULT NULL,
		total_bookings int(11) DEFAULT 0,
		total_revenue decimal(12,2) DEFAULT 0,
		avg_booking_value decimal(10,2) DEFAULT 0,
		days_since_last int(11) DEFAULT NULL,
		predicted_churn_risk decimal(5,2) DEFAULT NULL,
		ltv_score decimal(10,2) DEFAULT NULL,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY customer_email (customer_email),
		KEY lifecycle_stage (lifecycle_stage),
		KEY customer_id (customer_id)
	) {$charset_collate};";

	// Journey maps table.
	$table_journeys = $wpdb->prefix . 'bkx_cj_journeys';
	$sql_journeys   = "CREATE TABLE IF NOT EXISTS {$table_journeys} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		session_id varchar(64) NOT NULL,
		customer_email varchar(100) DEFAULT NULL,
		journey_start datetime NOT NULL,
		journey_end datetime DEFAULT NULL,
		journey_outcome varchar(30) DEFAULT NULL,
		touchpoint_count int(11) DEFAULT 0,
		duration_seconds int(11) DEFAULT NULL,
		booking_id bigint(20) unsigned DEFAULT NULL,
		attribution_source varchar(100) DEFAULT NULL,
		PRIMARY KEY (id),
		KEY session_id (session_id),
		KEY customer_email (customer_email),
		KEY journey_outcome (journey_outcome)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_touchpoints );
	dbDelta( $sql_lifecycle );
	dbDelta( $sql_journeys );
}

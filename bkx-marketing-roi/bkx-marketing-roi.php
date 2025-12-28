<?php
/**
 * Plugin Name:       BookingX Marketing ROI Tracker
 * Plugin URI:        https://flavflavor.dev/bookingx/addons/marketing-roi
 * Description:       Track marketing campaign ROI, UTM analytics, and campaign performance metrics for bookings.
 * Version:           1.0.0
 * Author:            flavflavor.dev
 * Author URI:        https://flavflavor.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bkx-marketing-roi
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package BookingX\MarketingROI
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'BKX_MARKETING_ROI_VERSION', '1.0.0' );
define( 'BKX_MARKETING_ROI_FILE', __FILE__ );
define( 'BKX_MARKETING_ROI_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_MARKETING_ROI_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check dependencies.
 */
function bkx_marketing_roi_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'BookingX Marketing ROI Tracker requires BookingX to be installed and activated.', 'bkx-marketing-roi' );
			echo '</p></div>';
		} );
		return false;
	}
	return true;
}

/**
 * Initialize plugin.
 */
function bkx_marketing_roi_init() {
	if ( ! bkx_marketing_roi_check_dependencies() ) {
		return;
	}

	require_once BKX_MARKETING_ROI_PATH . 'src/autoload.php';

	$addon = new \BookingX\MarketingROI\MarketingROIAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_marketing_roi_init' );

/**
 * Activation hook.
 */
function bkx_marketing_roi_activate() {
	bkx_marketing_roi_create_tables();
	update_option( 'bkx_marketing_roi_version', BKX_MARKETING_ROI_VERSION );
}
register_activation_hook( __FILE__, 'bkx_marketing_roi_activate' );

/**
 * Create database tables.
 */
function bkx_marketing_roi_create_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Campaigns table.
	$table_campaigns = $wpdb->prefix . 'bkx_roi_campaigns';
	$sql_campaigns   = "CREATE TABLE IF NOT EXISTS {$table_campaigns} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		campaign_name varchar(200) NOT NULL,
		utm_source varchar(100) DEFAULT NULL,
		utm_medium varchar(100) DEFAULT NULL,
		utm_campaign varchar(200) DEFAULT NULL,
		utm_content varchar(200) DEFAULT NULL,
		utm_term varchar(200) DEFAULT NULL,
		budget decimal(12,2) DEFAULT 0,
		start_date date DEFAULT NULL,
		end_date date DEFAULT NULL,
		status varchar(20) DEFAULT 'active',
		notes text DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY utm_source (utm_source),
		KEY utm_campaign (utm_campaign),
		KEY status (status)
	) {$charset_collate};";

	// Campaign visits table.
	$table_visits = $wpdb->prefix . 'bkx_roi_visits';
	$sql_visits   = "CREATE TABLE IF NOT EXISTS {$table_visits} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		session_id varchar(64) NOT NULL,
		campaign_id bigint(20) unsigned DEFAULT NULL,
		utm_source varchar(100) DEFAULT NULL,
		utm_medium varchar(100) DEFAULT NULL,
		utm_campaign varchar(200) DEFAULT NULL,
		utm_content varchar(200) DEFAULT NULL,
		utm_term varchar(200) DEFAULT NULL,
		landing_page varchar(500) DEFAULT NULL,
		referrer varchar(500) DEFAULT NULL,
		device_type varchar(20) DEFAULT NULL,
		converted tinyint(1) DEFAULT 0,
		booking_id bigint(20) unsigned DEFAULT NULL,
		revenue decimal(12,2) DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY session_id (session_id),
		KEY campaign_id (campaign_id),
		KEY utm_source (utm_source),
		KEY utm_campaign (utm_campaign),
		KEY converted (converted),
		KEY created_at (created_at)
	) {$charset_collate};";

	// Campaign costs table.
	$table_costs = $wpdb->prefix . 'bkx_roi_costs';
	$sql_costs   = "CREATE TABLE IF NOT EXISTS {$table_costs} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		campaign_id bigint(20) unsigned NOT NULL,
		cost_date date NOT NULL,
		amount decimal(12,2) NOT NULL,
		cost_type varchar(50) DEFAULT 'ad_spend',
		notes varchar(255) DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY campaign_id (campaign_id),
		KEY cost_date (cost_date)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_campaigns );
	dbDelta( $sql_visits );
	dbDelta( $sql_costs );
}

<?php
/**
 * Plugin Name:       BookingX Xero Integration
 * Plugin URI:        https://flavflavor.dev/bookingx/addons/xero
 * Description:       Sync bookings, invoices, and contacts with Xero for seamless accounting integration.
 * Version:           1.0.0
 * Author:            flavflavor.dev
 * Author URI:        https://flavflavor.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bkx-xero
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package BookingX\Xero
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'BKX_XERO_VERSION', '1.0.0' );
define( 'BKX_XERO_FILE', __FILE__ );
define( 'BKX_XERO_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_XERO_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check dependencies.
 */
function bkx_xero_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'BookingX Xero Integration requires BookingX to be installed and activated.', 'bkx-xero' );
			echo '</p></div>';
		} );
		return false;
	}
	return true;
}

/**
 * Initialize plugin.
 */
function bkx_xero_init() {
	if ( ! bkx_xero_check_dependencies() ) {
		return;
	}

	require_once BKX_XERO_PATH . 'src/autoload.php';

	$addon = new \BookingX\Xero\XeroAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_xero_init' );

/**
 * Activation hook.
 */
function bkx_xero_activate() {
	bkx_xero_create_tables();
	update_option( 'bkx_xero_version', BKX_XERO_VERSION );
}
register_activation_hook( __FILE__, 'bkx_xero_activate' );

/**
 * Create database tables.
 */
function bkx_xero_create_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Sync log table.
	$table_sync_log = $wpdb->prefix . 'bkx_xero_sync_log';
	$sql_sync_log   = "CREATE TABLE IF NOT EXISTS {$table_sync_log} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		entity_type varchar(50) NOT NULL,
		entity_id bigint(20) unsigned NOT NULL,
		xero_id varchar(100) DEFAULT NULL,
		sync_type varchar(30) NOT NULL,
		sync_status varchar(20) DEFAULT 'pending',
		sync_data longtext DEFAULT NULL,
		error_message text DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		synced_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY entity_type (entity_type),
		KEY entity_id (entity_id),
		KEY xero_id (xero_id),
		KEY sync_status (sync_status)
	) {$charset_collate};";

	// Entity mapping table.
	$table_mapping = $wpdb->prefix . 'bkx_xero_mapping';
	$sql_mapping   = "CREATE TABLE IF NOT EXISTS {$table_mapping} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		entity_type varchar(50) NOT NULL,
		bkx_id bigint(20) unsigned NOT NULL,
		xero_id varchar(100) NOT NULL,
		last_synced datetime DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY entity_mapping (entity_type, bkx_id),
		KEY xero_id (xero_id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_sync_log );
	dbDelta( $sql_mapping );
}

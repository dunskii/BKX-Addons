<?php
/**
 * Plugin Name:       BookingX QuickBooks Integration
 * Plugin URI:        https://flavflavor.dev/bookingx/addons/quickbooks
 * Description:       Sync bookings, invoices, and customers with QuickBooks Online for seamless accounting.
 * Version:           1.0.0
 * Author:            flavflavor.dev
 * Author URI:        https://flavflavor.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bkx-quickbooks
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package BookingX\QuickBooks
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'BKX_QUICKBOOKS_VERSION', '1.0.0' );
define( 'BKX_QUICKBOOKS_FILE', __FILE__ );
define( 'BKX_QUICKBOOKS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_QUICKBOOKS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check dependencies.
 */
function bkx_quickbooks_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'BookingX QuickBooks Integration requires BookingX to be installed and activated.', 'bkx-quickbooks' );
			echo '</p></div>';
		} );
		return false;
	}
	return true;
}

/**
 * Initialize plugin.
 */
function bkx_quickbooks_init() {
	if ( ! bkx_quickbooks_check_dependencies() ) {
		return;
	}

	require_once BKX_QUICKBOOKS_PATH . 'src/autoload.php';

	$addon = new \BookingX\QuickBooks\QuickBooksAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_quickbooks_init' );

/**
 * Activation hook.
 */
function bkx_quickbooks_activate() {
	bkx_quickbooks_create_tables();
	update_option( 'bkx_quickbooks_version', BKX_QUICKBOOKS_VERSION );
}
register_activation_hook( __FILE__, 'bkx_quickbooks_activate' );

/**
 * Create database tables.
 */
function bkx_quickbooks_create_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Sync log table.
	$table_sync_log = $wpdb->prefix . 'bkx_qb_sync_log';
	$sql_sync_log   = "CREATE TABLE IF NOT EXISTS {$table_sync_log} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		entity_type varchar(50) NOT NULL,
		entity_id bigint(20) unsigned NOT NULL,
		qb_id varchar(100) DEFAULT NULL,
		sync_type varchar(30) NOT NULL,
		sync_status varchar(20) DEFAULT 'pending',
		sync_data longtext DEFAULT NULL,
		error_message text DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		synced_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY entity_type (entity_type),
		KEY entity_id (entity_id),
		KEY qb_id (qb_id),
		KEY sync_status (sync_status)
	) {$charset_collate};";

	// Entity mapping table.
	$table_mapping = $wpdb->prefix . 'bkx_qb_mapping';
	$sql_mapping   = "CREATE TABLE IF NOT EXISTS {$table_mapping} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		entity_type varchar(50) NOT NULL,
		bkx_id bigint(20) unsigned NOT NULL,
		qb_id varchar(100) NOT NULL,
		qb_sync_token varchar(50) DEFAULT NULL,
		last_synced datetime DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY entity_mapping (entity_type, bkx_id),
		KEY qb_id (qb_id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_sync_log );
	dbDelta( $sql_mapping );
}

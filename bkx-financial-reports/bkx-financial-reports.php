<?php
/**
 * Plugin Name:       BookingX Financial Reporting Suite
 * Plugin URI:        https://flavflavor.dev/bookingx/addons/financial-reports
 * Description:       Comprehensive financial reporting including P&L, revenue analytics, tax reports, and cash flow analysis.
 * Version:           1.0.0
 * Author:            flavflavor.dev
 * Author URI:        https://flavflavor.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bkx-financial-reports
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package BookingX\FinancialReports
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'BKX_FINANCIAL_VERSION', '1.0.0' );
define( 'BKX_FINANCIAL_FILE', __FILE__ );
define( 'BKX_FINANCIAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_FINANCIAL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check dependencies.
 */
function bkx_financial_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'BookingX Financial Reporting Suite requires BookingX to be installed and activated.', 'bkx-financial-reports' );
			echo '</p></div>';
		} );
		return false;
	}
	return true;
}

/**
 * Initialize plugin.
 */
function bkx_financial_init() {
	if ( ! bkx_financial_check_dependencies() ) {
		return;
	}

	require_once BKX_FINANCIAL_PATH . 'src/autoload.php';

	$addon = new \BookingX\FinancialReports\FinancialReportsAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_financial_init' );

/**
 * Activation hook.
 */
function bkx_financial_activate() {
	bkx_financial_create_tables();
	update_option( 'bkx_financial_version', BKX_FINANCIAL_VERSION );
}
register_activation_hook( __FILE__, 'bkx_financial_activate' );

/**
 * Create database tables.
 */
function bkx_financial_create_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Revenue snapshots table (for historical comparisons).
	$table_snapshots = $wpdb->prefix . 'bkx_financial_snapshots';
	$sql_snapshots   = "CREATE TABLE IF NOT EXISTS {$table_snapshots} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		snapshot_date date NOT NULL,
		snapshot_type varchar(30) NOT NULL DEFAULT 'daily',
		total_revenue decimal(15,2) DEFAULT 0,
		total_bookings int DEFAULT 0,
		completed_bookings int DEFAULT 0,
		cancelled_bookings int DEFAULT 0,
		refunds decimal(15,2) DEFAULT 0,
		taxes_collected decimal(15,2) DEFAULT 0,
		service_revenue decimal(15,2) DEFAULT 0,
		extras_revenue decimal(15,2) DEFAULT 0,
		average_booking_value decimal(15,2) DEFAULT 0,
		new_customers int DEFAULT 0,
		returning_customers int DEFAULT 0,
		metadata longtext DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY snapshot_unique (snapshot_date, snapshot_type),
		KEY snapshot_type (snapshot_type),
		KEY snapshot_date (snapshot_date)
	) {$charset_collate};";

	// Expense tracking table.
	$table_expenses = $wpdb->prefix . 'bkx_financial_expenses';
	$sql_expenses   = "CREATE TABLE IF NOT EXISTS {$table_expenses} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		expense_date date NOT NULL,
		category varchar(50) NOT NULL,
		description varchar(255) NOT NULL,
		amount decimal(15,2) NOT NULL,
		payment_method varchar(50) DEFAULT NULL,
		vendor varchar(100) DEFAULT NULL,
		receipt_url varchar(255) DEFAULT NULL,
		is_recurring tinyint(1) DEFAULT 0,
		recurring_frequency varchar(20) DEFAULT NULL,
		notes text DEFAULT NULL,
		created_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY expense_date (expense_date),
		KEY category (category)
	) {$charset_collate};";

	// Tax configuration table.
	$table_taxes = $wpdb->prefix . 'bkx_financial_tax_rates';
	$sql_taxes   = "CREATE TABLE IF NOT EXISTS {$table_taxes} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(100) NOT NULL,
		rate decimal(6,4) NOT NULL,
		country varchar(2) DEFAULT NULL,
		state varchar(50) DEFAULT NULL,
		tax_type varchar(30) DEFAULT 'percentage',
		applies_to varchar(50) DEFAULT 'all',
		is_compound tinyint(1) DEFAULT 0,
		priority int DEFAULT 0,
		is_active tinyint(1) DEFAULT 1,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY country_state (country, state),
		KEY is_active (is_active)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_snapshots );
	dbDelta( $sql_expenses );
	dbDelta( $sql_taxes );
}

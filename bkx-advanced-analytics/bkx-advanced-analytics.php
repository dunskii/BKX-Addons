<?php
/**
 * Plugin Name:       BookingX Advanced Booking Analytics
 * Plugin URI:        https://flavflavor.dev/bookingx/addons/advanced-analytics
 * Description:       Deep analytics and insights for booking data with trend analysis, comparisons, and custom reports.
 * Version:           1.0.0
 * Author:            flavflavor.dev
 * Author URI:        https://flavflavor.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bkx-advanced-analytics
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package BookingX\AdvancedAnalytics
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'BKX_ADVANCED_ANALYTICS_VERSION', '1.0.0' );
define( 'BKX_ADVANCED_ANALYTICS_FILE', __FILE__ );
define( 'BKX_ADVANCED_ANALYTICS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_ADVANCED_ANALYTICS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_ADVANCED_ANALYTICS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if BookingX is active.
 *
 * @return bool
 */
function bkx_advanced_analytics_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_advanced_analytics_missing_dependency_notice' );
		return false;
	}
	return true;
}

/**
 * Display missing dependency notice.
 */
function bkx_advanced_analytics_missing_dependency_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'BookingX Advanced Analytics', 'bkx-advanced-analytics' ); ?></strong>
			<?php esc_html_e( 'requires BookingX to be installed and activated.', 'bkx-advanced-analytics' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Initialize plugin.
 */
function bkx_advanced_analytics_init() {
	if ( ! bkx_advanced_analytics_check_dependencies() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_ADVANCED_ANALYTICS_PATH . 'src/autoload.php';

	// Initialize addon.
	$addon = new \BookingX\AdvancedAnalytics\AdvancedAnalyticsAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_advanced_analytics_init' );

/**
 * Activation hook.
 */
function bkx_advanced_analytics_activate() {
	// Create database tables.
	bkx_advanced_analytics_create_tables();

	// Set version.
	update_option( 'bkx_advanced_analytics_version', BKX_ADVANCED_ANALYTICS_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_advanced_analytics_activate' );

/**
 * Deactivation hook.
 */
function bkx_advanced_analytics_deactivate() {
	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_aa_process_analytics' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_advanced_analytics_deactivate' );

/**
 * Create database tables.
 */
function bkx_advanced_analytics_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Analytics events table.
	$table_events = $wpdb->prefix . 'bkx_aa_events';
	$sql_events   = "CREATE TABLE IF NOT EXISTS {$table_events} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_type varchar(50) NOT NULL,
		booking_id bigint(20) unsigned DEFAULT NULL,
		customer_id bigint(20) unsigned DEFAULT NULL,
		service_id bigint(20) unsigned DEFAULT NULL,
		staff_id bigint(20) unsigned DEFAULT NULL,
		event_data longtext DEFAULT NULL,
		event_date datetime NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY event_type (event_type),
		KEY booking_id (booking_id),
		KEY customer_id (customer_id),
		KEY event_date (event_date)
	) {$charset_collate};";

	// Cohort analysis table.
	$table_cohorts = $wpdb->prefix . 'bkx_aa_cohorts';
	$sql_cohorts   = "CREATE TABLE IF NOT EXISTS {$table_cohorts} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		cohort_date date NOT NULL,
		cohort_type varchar(20) NOT NULL DEFAULT 'monthly',
		customer_count int(11) DEFAULT 0,
		period_0 decimal(10,2) DEFAULT 0,
		period_1 decimal(10,2) DEFAULT 0,
		period_2 decimal(10,2) DEFAULT 0,
		period_3 decimal(10,2) DEFAULT 0,
		period_4 decimal(10,2) DEFAULT 0,
		period_5 decimal(10,2) DEFAULT 0,
		period_6 decimal(10,2) DEFAULT 0,
		period_7 decimal(10,2) DEFAULT 0,
		period_8 decimal(10,2) DEFAULT 0,
		period_9 decimal(10,2) DEFAULT 0,
		period_10 decimal(10,2) DEFAULT 0,
		period_11 decimal(10,2) DEFAULT 0,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY cohort_key (cohort_date, cohort_type)
	) {$charset_collate};";

	// Saved analyses table.
	$table_analyses = $wpdb->prefix . 'bkx_aa_analyses';
	$sql_analyses   = "CREATE TABLE IF NOT EXISTS {$table_analyses} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		analysis_name varchar(255) NOT NULL,
		analysis_type varchar(50) NOT NULL,
		analysis_config longtext DEFAULT NULL,
		analysis_results longtext DEFAULT NULL,
		created_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY analysis_type (analysis_type)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_events );
	dbDelta( $sql_cohorts );
	dbDelta( $sql_analyses );
}

<?php
/**
 * Plugin Name: BookingX - Business Intelligence Dashboard
 * Plugin URI: https://developer.jeancplugins.tech/bkx-business-intelligence
 * Description: Comprehensive business intelligence dashboard with KPIs, trends, forecasting, and executive reports.
 * Version: 1.0.0
 * Author: JEANcp
 * Author URI: https://jeancplugins.tech
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-business-intelligence
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\BusinessIntelligence
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_BI_VERSION', '1.0.0' );
define( 'BKX_BI_FILE', __FILE__ );
define( 'BKX_BI_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_BI_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if dependencies are met.
 *
 * @return bool
 */
function bkx_bi_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'BookingX Business Intelligence Dashboard requires the BookingX plugin to be installed and activated.', 'bkx-business-intelligence' );
			echo '</p></div>';
		} );
		return false;
	}

	return true;
}

/**
 * Initialize the plugin.
 */
function bkx_bi_init() {
	if ( ! bkx_bi_check_dependencies() ) {
		return;
	}

	// Load text domain.
	load_plugin_textdomain( 'bkx-business-intelligence', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Load autoloader.
	require_once BKX_BI_PATH . 'src/autoload.php';

	// Initialize the addon.
	\BookingX\BusinessIntelligence\BusinessIntelligenceAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_bi_init', 20 );

/**
 * Plugin activation.
 */
function bkx_bi_activate() {
	if ( ! bkx_bi_check_dependencies() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'BookingX Business Intelligence Dashboard requires the BookingX plugin to be installed and activated.', 'bkx-business-intelligence' ),
			esc_html__( 'Plugin Activation Error', 'bkx-business-intelligence' ),
			array( 'back_link' => true )
		);
	}

	// Create database tables.
	bkx_bi_create_tables();

	// Set default options.
	$defaults = array(
		'enabled'              => true,
		'dashboard_widgets'    => array( 'revenue', 'bookings', 'customers', 'staff' ),
		'default_date_range'   => '30days',
		'cache_duration'       => 3600,
		'email_reports'        => false,
		'report_recipients'    => '',
		'report_frequency'     => 'weekly',
	);
	add_option( 'bkx_bi_settings', $defaults );
	add_option( 'bkx_bi_version', BKX_BI_VERSION );

	// Schedule cron jobs.
	if ( ! wp_next_scheduled( 'bkx_bi_aggregate_data' ) ) {
		wp_schedule_event( time(), 'hourly', 'bkx_bi_aggregate_data' );
	}
	if ( ! wp_next_scheduled( 'bkx_bi_send_reports' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_bi_send_reports' );
	}
}
register_activation_hook( __FILE__, 'bkx_bi_activate' );

/**
 * Create database tables.
 */
function bkx_bi_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Aggregated metrics table.
	$sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_bi_metrics (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		metric_date date NOT NULL,
		metric_type varchar(50) NOT NULL,
		metric_key varchar(100) NOT NULL,
		metric_value decimal(15,2) NOT NULL DEFAULT 0,
		dimension1 varchar(100) DEFAULT NULL,
		dimension2 varchar(100) DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY unique_metric (metric_date, metric_type, metric_key, dimension1, dimension2),
		KEY metric_date (metric_date),
		KEY metric_type (metric_type)
	) $charset_collate;";

	// Saved reports table.
	$sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_bi_reports (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		report_name varchar(200) NOT NULL,
		report_type varchar(50) NOT NULL,
		report_config longtext NOT NULL,
		created_by bigint(20) UNSIGNED NOT NULL,
		is_scheduled tinyint(1) NOT NULL DEFAULT 0,
		schedule_frequency varchar(20) DEFAULT NULL,
		last_run datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY report_type (report_type),
		KEY created_by (created_by)
	) $charset_collate;";

	// Forecasts table.
	$sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_bi_forecasts (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		forecast_date date NOT NULL,
		forecast_type varchar(50) NOT NULL,
		predicted_value decimal(15,2) NOT NULL,
		confidence_low decimal(15,2) DEFAULT NULL,
		confidence_high decimal(15,2) DEFAULT NULL,
		model_version varchar(20) NOT NULL DEFAULT '1.0',
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY unique_forecast (forecast_date, forecast_type),
		KEY forecast_date (forecast_date)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql1 );
	dbDelta( $sql2 );
	dbDelta( $sql3 );
}

/**
 * Plugin deactivation.
 */
function bkx_bi_deactivate() {
	wp_clear_scheduled_hook( 'bkx_bi_aggregate_data' );
	wp_clear_scheduled_hook( 'bkx_bi_send_reports' );
}
register_deactivation_hook( __FILE__, 'bkx_bi_deactivate' );

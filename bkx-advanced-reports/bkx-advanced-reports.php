<?php
/**
 * Plugin Name: BookingX - Advanced Booking Reports
 * Plugin URI: https://developer.jetonjobs.com/bookingx/addons/advanced-reports
 * Description: Comprehensive analytics and reporting for bookings, revenue, staff performance, and customer insights.
 * Version: 1.0.0
 * Author: Developer JetonJobs
 * Author URI: https://developer.jetonjobs.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-advanced-reports
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\AdvancedReports
 */

namespace BookingX\AdvancedReports;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_ADVANCED_REPORTS_VERSION', '1.0.0' );
define( 'BKX_ADVANCED_REPORTS_FILE', __FILE__ );
define( 'BKX_ADVANCED_REPORTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_ADVANCED_REPORTS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_ADVANCED_REPORTS_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_ADVANCED_REPORTS_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function init() {
	// Check for BookingX.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\dependency_notice' );
		return;
	}

	// Load text domain.
	load_plugin_textdomain(
		'bkx-advanced-reports',
		false,
		dirname( BKX_ADVANCED_REPORTS_BASENAME ) . '/languages'
	);

	// Initialize addon.
	$addon = AdvancedReportsAddon::get_instance();
	$addon->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init', 20 );

/**
 * Display dependency notice.
 *
 * @since 1.0.0
 */
function dependency_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-advanced-reports' ),
				'<strong>Advanced Booking Reports</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Activation hook.
 *
 * @since 1.0.0
 */
function activate() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	// Report snapshots table for caching daily/weekly/monthly aggregates.
	$table_snapshots = $wpdb->prefix . 'bkx_report_snapshots';
	$sql_snapshots   = "CREATE TABLE {$table_snapshots} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		report_type varchar(50) NOT NULL,
		snapshot_date date NOT NULL,
		period_type enum('day','week','month','year') NOT NULL DEFAULT 'day',
		data longtext NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY unique_snapshot (report_type, snapshot_date, period_type),
		KEY report_type (report_type),
		KEY snapshot_date (snapshot_date)
	) {$charset_collate};";

	// Saved reports table.
	$table_saved = $wpdb->prefix . 'bkx_saved_reports';
	$sql_saved   = "CREATE TABLE {$table_saved} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		name varchar(255) NOT NULL,
		report_type varchar(50) NOT NULL,
		filters text,
		columns text,
		schedule varchar(50) DEFAULT NULL,
		last_run datetime DEFAULT NULL,
		email_recipients text,
		is_favorite tinyint(1) DEFAULT 0,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY report_type (report_type),
		KEY is_favorite (is_favorite)
	) {$charset_collate};";

	// Export logs table.
	$table_exports = $wpdb->prefix . 'bkx_report_exports';
	$sql_exports   = "CREATE TABLE {$table_exports} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		report_type varchar(50) NOT NULL,
		export_format enum('csv','xlsx','pdf') NOT NULL,
		file_path varchar(500) DEFAULT NULL,
		status enum('pending','processing','completed','failed') DEFAULT 'pending',
		rows_exported int(11) DEFAULT 0,
		error_message text,
		started_at datetime DEFAULT NULL,
		completed_at datetime DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY status (status),
		KEY created_at (created_at)
	) {$charset_collate};";

	// Dashboard widgets config.
	$table_widgets = $wpdb->prefix . 'bkx_dashboard_widgets';
	$sql_widgets   = "CREATE TABLE {$table_widgets} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		widget_type varchar(50) NOT NULL,
		widget_config text,
		position int(11) DEFAULT 0,
		is_visible tinyint(1) DEFAULT 1,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY position (position)
	) {$charset_collate};";

	dbDelta( $sql_snapshots );
	dbDelta( $sql_saved );
	dbDelta( $sql_exports );
	dbDelta( $sql_widgets );

	// Set default options.
	$defaults = array(
		'default_date_range'      => '30days',
		'currency'                => 'USD',
		'currency_position'       => 'before',
		'decimal_places'          => 2,
		'thousands_separator'     => ',',
		'decimal_separator'       => '.',
		'chart_colors'            => array( '#0073aa', '#00a0d2', '#46b450', '#ffb900', '#dc3232' ),
		'enable_caching'          => true,
		'cache_duration_hours'    => 1,
		'snapshot_retention_days' => 365,
		'export_retention_days'   => 30,
		'enable_email_reports'    => true,
		'email_report_time'       => '08:00',
		'show_dashboard_widget'   => true,
		'default_report'          => 'overview',
	);

	if ( ! get_option( 'bkx_advanced_reports_settings' ) ) {
		update_option( 'bkx_advanced_reports_settings', $defaults );
	}

	// Store version.
	update_option( 'bkx_advanced_reports_version', BKX_ADVANCED_REPORTS_VERSION );

	// Schedule daily snapshot generation.
	if ( ! wp_next_scheduled( 'bkx_generate_daily_snapshots' ) ) {
		wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', 'bkx_generate_daily_snapshots' );
	}

	// Schedule export cleanup.
	if ( ! wp_next_scheduled( 'bkx_cleanup_old_exports' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_cleanup_old_exports' );
	}
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 */
function deactivate() {
	// Clear scheduled events.
	$cron_hooks = array(
		'bkx_generate_daily_snapshots',
		'bkx_cleanup_old_exports',
		'bkx_send_scheduled_reports',
	);

	foreach ( $cron_hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

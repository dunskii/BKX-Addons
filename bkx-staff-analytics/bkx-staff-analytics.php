<?php
/**
 * Plugin Name:       BookingX Staff Performance Analytics
 * Plugin URI:        https://developer.jetonit.com/add-ons/bkx-staff-analytics/
 * Description:       Comprehensive staff performance tracking and analytics for BookingX. Monitor productivity, customer satisfaction, revenue generation, and time management.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            JetOnIt
 * Author URI:        https://developer.jetonit.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bkx-staff-analytics
 * Domain Path:       /languages
 *
 * @package BookingX\StaffAnalytics
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'BKX_STAFF_ANALYTICS_VERSION', '1.0.0' );
define( 'BKX_STAFF_ANALYTICS_PLUGIN_FILE', __FILE__ );
define( 'BKX_STAFF_ANALYTICS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_STAFF_ANALYTICS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_STAFF_ANALYTICS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check for BookingX dependency.
 *
 * @since 1.0.0
 * @return bool True if BookingX is active.
 */
function bkx_staff_analytics_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_staff_analytics_missing_dependency_notice' );
		return false;
	}
	return true;
}

/**
 * Display missing dependency notice.
 *
 * @since 1.0.0
 */
function bkx_staff_analytics_missing_dependency_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-staff-analytics' ),
				'<strong>BookingX Staff Performance Analytics</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function bkx_staff_analytics_init() {
	if ( ! bkx_staff_analytics_check_dependencies() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_STAFF_ANALYTICS_PLUGIN_DIR . 'src/autoload.php';

	// Initialize addon.
	\BookingX\StaffAnalytics\StaffAnalyticsAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_staff_analytics_init', 20 );

/**
 * Activation hook.
 *
 * @since 1.0.0
 */
function bkx_staff_analytics_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Staff performance metrics table.
	$table_metrics = $wpdb->prefix . 'bkx_staff_metrics';
	$sql_metrics   = "CREATE TABLE {$table_metrics} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		staff_id bigint(20) unsigned NOT NULL,
		metric_date date NOT NULL,
		total_bookings int(11) NOT NULL DEFAULT 0,
		completed_bookings int(11) NOT NULL DEFAULT 0,
		cancelled_bookings int(11) NOT NULL DEFAULT 0,
		no_show_bookings int(11) NOT NULL DEFAULT 0,
		total_revenue decimal(12,2) NOT NULL DEFAULT 0.00,
		total_hours decimal(8,2) NOT NULL DEFAULT 0.00,
		avg_rating decimal(3,2) DEFAULT NULL,
		total_reviews int(11) NOT NULL DEFAULT 0,
		new_customers int(11) NOT NULL DEFAULT 0,
		returning_customers int(11) NOT NULL DEFAULT 0,
		utilization_rate decimal(5,2) DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY staff_date (staff_id, metric_date),
		KEY staff_id (staff_id),
		KEY metric_date (metric_date)
	) {$charset_collate};";

	// Staff goals table.
	$table_goals = $wpdb->prefix . 'bkx_staff_goals';
	$sql_goals   = "CREATE TABLE {$table_goals} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		staff_id bigint(20) unsigned NOT NULL,
		goal_type varchar(50) NOT NULL,
		goal_period varchar(20) NOT NULL DEFAULT 'monthly',
		target_value decimal(12,2) NOT NULL,
		start_date date NOT NULL,
		end_date date NOT NULL,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY staff_id (staff_id),
		KEY goal_type (goal_type),
		KEY is_active (is_active)
	) {$charset_collate};";

	// Staff reviews table.
	$table_reviews = $wpdb->prefix . 'bkx_staff_reviews';
	$sql_reviews   = "CREATE TABLE {$table_reviews} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		staff_id bigint(20) unsigned NOT NULL,
		booking_id bigint(20) unsigned NOT NULL,
		customer_id bigint(20) unsigned DEFAULT NULL,
		rating tinyint(1) NOT NULL,
		review_text text,
		is_approved tinyint(1) NOT NULL DEFAULT 0,
		reviewed_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY staff_id (staff_id),
		KEY booking_id (booking_id),
		KEY customer_id (customer_id),
		KEY rating (rating),
		KEY is_approved (is_approved)
	) {$charset_collate};";

	// Staff time tracking table.
	$table_time = $wpdb->prefix . 'bkx_staff_time_logs';
	$sql_time   = "CREATE TABLE {$table_time} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		staff_id bigint(20) unsigned NOT NULL,
		log_date date NOT NULL,
		clock_in datetime NOT NULL,
		clock_out datetime DEFAULT NULL,
		break_minutes int(11) NOT NULL DEFAULT 0,
		total_hours decimal(8,2) DEFAULT NULL,
		notes text,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY staff_id (staff_id),
		KEY log_date (log_date)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_metrics );
	dbDelta( $sql_goals );
	dbDelta( $sql_reviews );
	dbDelta( $sql_time );

	// Set version.
	update_option( 'bkx_staff_analytics_version', BKX_STAFF_ANALYTICS_VERSION );

	// Schedule daily metrics aggregation.
	if ( ! wp_next_scheduled( 'bkx_staff_daily_metrics' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 2:00 AM' ), 'daily', 'bkx_staff_daily_metrics' );
	}
}
register_activation_hook( __FILE__, 'bkx_staff_analytics_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 */
function bkx_staff_analytics_deactivate() {
	wp_clear_scheduled_hook( 'bkx_staff_daily_metrics' );
}
register_deactivation_hook( __FILE__, 'bkx_staff_analytics_deactivate' );

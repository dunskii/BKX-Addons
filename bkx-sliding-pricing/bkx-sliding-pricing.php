<?php
/**
 * Plugin Name:       BookingX Sliding Pricing
 * Plugin URI:        https://developer.jetonit.com/add-ons/bkx-sliding-pricing/
 * Description:       Dynamic pricing based on demand, time slots, and seasons. Implement peak/off-peak pricing, early bird discounts, and last-minute deals.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            JetOnIt
 * Author URI:        https://developer.jetonit.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bkx-sliding-pricing
 * Domain Path:       /languages
 *
 * @package BookingX\SlidingPricing
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'BKX_SLIDING_PRICING_VERSION', '1.0.0' );
define( 'BKX_SLIDING_PRICING_PLUGIN_FILE', __FILE__ );
define( 'BKX_SLIDING_PRICING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_SLIDING_PRICING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_SLIDING_PRICING_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check for BookingX dependency.
 *
 * @since 1.0.0
 * @return bool True if BookingX is active.
 */
function bkx_sliding_pricing_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_sliding_pricing_missing_dependency_notice' );
		return false;
	}
	return true;
}

/**
 * Display missing dependency notice.
 *
 * @since 1.0.0
 */
function bkx_sliding_pricing_missing_dependency_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-sliding-pricing' ),
				'<strong>BookingX Sliding Pricing</strong>'
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
function bkx_sliding_pricing_init() {
	if ( ! bkx_sliding_pricing_check_dependencies() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_SLIDING_PRICING_PLUGIN_DIR . 'src/autoload.php';

	// Initialize addon.
	\BookingX\SlidingPricing\SlidingPricingAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_sliding_pricing_init', 20 );

/**
 * Activation hook.
 *
 * @since 1.0.0
 */
function bkx_sliding_pricing_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Pricing rules table.
	$table_rules = $wpdb->prefix . 'bkx_pricing_rules';
	$sql_rules   = "CREATE TABLE {$table_rules} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		rule_type varchar(50) NOT NULL,
		applies_to varchar(50) NOT NULL DEFAULT 'all',
		service_ids text,
		staff_ids text,
		priority int(11) NOT NULL DEFAULT 10,
		adjustment_type varchar(20) NOT NULL DEFAULT 'percentage',
		adjustment_value decimal(10,2) NOT NULL,
		conditions longtext,
		start_date date DEFAULT NULL,
		end_date date DEFAULT NULL,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY rule_type (rule_type),
		KEY is_active (is_active),
		KEY priority (priority)
	) {$charset_collate};";

	// Seasonal pricing table.
	$table_seasons = $wpdb->prefix . 'bkx_pricing_seasons';
	$sql_seasons   = "CREATE TABLE {$table_seasons} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		start_date date NOT NULL,
		end_date date NOT NULL,
		adjustment_type varchar(20) NOT NULL DEFAULT 'percentage',
		adjustment_value decimal(10,2) NOT NULL,
		applies_to varchar(50) NOT NULL DEFAULT 'all',
		service_ids text,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		recurs_yearly tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY is_active (is_active),
		KEY start_date (start_date),
		KEY end_date (end_date)
	) {$charset_collate};";

	// Time-based pricing table.
	$table_timeslots = $wpdb->prefix . 'bkx_pricing_timeslots';
	$sql_timeslots   = "CREATE TABLE {$table_timeslots} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		day_of_week varchar(20) NOT NULL,
		start_time time NOT NULL,
		end_time time NOT NULL,
		adjustment_type varchar(20) NOT NULL DEFAULT 'percentage',
		adjustment_value decimal(10,2) NOT NULL,
		applies_to varchar(50) NOT NULL DEFAULT 'all',
		service_ids text,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY day_of_week (day_of_week),
		KEY is_active (is_active)
	) {$charset_collate};";

	// Pricing history/audit table.
	$table_history = $wpdb->prefix . 'bkx_pricing_history';
	$sql_history   = "CREATE TABLE {$table_history} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		booking_id bigint(20) unsigned NOT NULL,
		base_price decimal(10,2) NOT NULL,
		final_price decimal(10,2) NOT NULL,
		adjustments longtext,
		calculated_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY booking_id (booking_id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_rules );
	dbDelta( $sql_seasons );
	dbDelta( $sql_timeslots );
	dbDelta( $sql_history );

	// Set default options.
	add_option( 'bkx_sliding_pricing_show_original', 'yes' );
	add_option( 'bkx_sliding_pricing_show_savings', 'yes' );
	add_option( 'bkx_sliding_pricing_stack_rules', 'yes' );
	add_option( 'bkx_sliding_pricing_max_discount', 50 );

	// Set version.
	update_option( 'bkx_sliding_pricing_version', BKX_SLIDING_PRICING_VERSION );
}
register_activation_hook( __FILE__, 'bkx_sliding_pricing_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 */
function bkx_sliding_pricing_deactivate() {
	// Clean up transients.
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_pricing_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_pricing_%'" );
}
register_deactivation_hook( __FILE__, 'bkx_sliding_pricing_deactivate' );

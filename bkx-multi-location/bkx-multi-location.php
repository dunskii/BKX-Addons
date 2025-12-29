<?php
/**
 * Plugin Name: BookingX - Multi-Location Management
 * Plugin URI: https://developer.jetonx.com/addons/multi-location
 * Description: Manage bookings across multiple physical locations with location-specific staff, services, and schedules.
 * Version: 1.0.0
 * Author: JetonX
 * Author URI: https://developer.jetonx.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-multi-location
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\MultiLocation
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_ML_VERSION', '1.0.0' );
define( 'BKX_ML_PLUGIN_FILE', __FILE__ );
define( 'BKX_ML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_ML_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_ML_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if BookingX is active.
 *
 * @return bool
 */
function bkx_ml_is_bookingx_active() {
	return class_exists( 'Bookingx' ) || in_array( 'bookingx/bookingx.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
}

/**
 * Admin notice for missing BookingX.
 */
function bkx_ml_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-multi-location' ),
				'<strong>BookingX Multi-Location Management</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_ml_init() {
	if ( ! bkx_ml_is_bookingx_active() ) {
		add_action( 'admin_notices', 'bkx_ml_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_ML_PLUGIN_DIR . 'src/autoload.php';

	// Initialize main addon class.
	\BookingX\MultiLocation\MultiLocationAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_ml_init', 20 );

/**
 * Activation hook.
 */
function bkx_ml_activate() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	// Locations table.
	$table_locations = $wpdb->prefix . 'bkx_locations';
	$sql_locations   = "CREATE TABLE {$table_locations} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		slug varchar(255) NOT NULL,
		description text,
		address_line_1 varchar(255) DEFAULT '',
		address_line_2 varchar(255) DEFAULT '',
		city varchar(100) DEFAULT '',
		state varchar(100) DEFAULT '',
		postal_code varchar(20) DEFAULT '',
		country varchar(2) DEFAULT '',
		latitude decimal(10,8) DEFAULT NULL,
		longitude decimal(11,8) DEFAULT NULL,
		phone varchar(50) DEFAULT '',
		email varchar(255) DEFAULT '',
		timezone varchar(100) DEFAULT '',
		status varchar(20) DEFAULT 'active',
		settings longtext,
		sort_order int(11) DEFAULT 0,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug),
		KEY status (status),
		KEY sort_order (sort_order)
	) {$charset_collate};";

	dbDelta( $sql_locations );

	// Location hours table.
	$table_hours = $wpdb->prefix . 'bkx_location_hours';
	$sql_hours   = "CREATE TABLE {$table_hours} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		location_id bigint(20) unsigned NOT NULL,
		day_of_week tinyint(1) NOT NULL,
		is_open tinyint(1) DEFAULT 1,
		open_time time DEFAULT NULL,
		close_time time DEFAULT NULL,
		break_start time DEFAULT NULL,
		break_end time DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY location_id (location_id),
		KEY day_of_week (day_of_week),
		UNIQUE KEY location_day (location_id, day_of_week)
	) {$charset_collate};";

	dbDelta( $sql_hours );

	// Location holidays table.
	$table_holidays = $wpdb->prefix . 'bkx_location_holidays';
	$sql_holidays   = "CREATE TABLE {$table_holidays} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		location_id bigint(20) unsigned NOT NULL,
		name varchar(255) NOT NULL,
		date date NOT NULL,
		is_recurring tinyint(1) DEFAULT 0,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY location_id (location_id),
		KEY date (date)
	) {$charset_collate};";

	dbDelta( $sql_holidays );

	// Staff-Location assignments table.
	$table_staff = $wpdb->prefix . 'bkx_location_staff';
	$sql_staff   = "CREATE TABLE {$table_staff} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		location_id bigint(20) unsigned NOT NULL,
		seat_id bigint(20) unsigned NOT NULL,
		is_primary tinyint(1) DEFAULT 0,
		status varchar(20) DEFAULT 'active',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY location_seat (location_id, seat_id),
		KEY location_id (location_id),
		KEY seat_id (seat_id)
	) {$charset_collate};";

	dbDelta( $sql_staff );

	// Service-Location availability table.
	$table_services = $wpdb->prefix . 'bkx_location_services';
	$sql_services   = "CREATE TABLE {$table_services} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		location_id bigint(20) unsigned NOT NULL,
		base_id bigint(20) unsigned NOT NULL,
		is_available tinyint(1) DEFAULT 1,
		price_override decimal(10,2) DEFAULT NULL,
		duration_override int(11) DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY location_base (location_id, base_id),
		KEY location_id (location_id),
		KEY base_id (base_id)
	) {$charset_collate};";

	dbDelta( $sql_services );

	// Location resources table (rooms, equipment, etc.).
	$table_resources = $wpdb->prefix . 'bkx_location_resources';
	$sql_resources   = "CREATE TABLE {$table_resources} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		location_id bigint(20) unsigned NOT NULL,
		name varchar(255) NOT NULL,
		type varchar(50) DEFAULT 'room',
		capacity int(11) DEFAULT 1,
		description text,
		status varchar(20) DEFAULT 'active',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY location_id (location_id),
		KEY type (type),
		KEY status (status)
	) {$charset_collate};";

	dbDelta( $sql_resources );

	// Store database version.
	update_option( 'bkx_ml_db_version', BKX_ML_VERSION );

	// Create default location if none exists.
	bkx_ml_create_default_location();

	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_ml_activate' );

/**
 * Create default location on activation.
 */
function bkx_ml_create_default_location() {
	global $wpdb;

	$table = $wpdb->prefix . 'bkx_locations';

	// Check if any location exists.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

	if ( 0 === (int) $count ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'name'        => __( 'Main Location', 'bkx-multi-location' ),
				'slug'        => 'main-location',
				'description' => __( 'Default primary location', 'bkx-multi-location' ),
				'status'      => 'active',
				'sort_order'  => 0,
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		$location_id = $wpdb->insert_id;

		// Create default hours (9 AM - 5 PM, Mon-Fri).
		$hours_table = $wpdb->prefix . 'bkx_location_hours';
		for ( $day = 0; $day <= 6; $day++ ) {
			$is_open = ( $day >= 1 && $day <= 5 ) ? 1 : 0;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$hours_table,
				array(
					'location_id' => $location_id,
					'day_of_week' => $day,
					'is_open'     => $is_open,
					'open_time'   => $is_open ? '09:00:00' : null,
					'close_time'  => $is_open ? '17:00:00' : null,
				),
				array( '%d', '%d', '%d', '%s', '%s' )
			);
		}
	}
}

/**
 * Deactivation hook.
 */
function bkx_ml_deactivate() {
	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_ml_daily_cleanup' );
	wp_clear_scheduled_hook( 'bkx_ml_sync_google_places' );

	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_ml_deactivate' );

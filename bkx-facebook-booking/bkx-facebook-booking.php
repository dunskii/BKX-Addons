<?php
/**
 * Plugin Name: BookingX - Facebook Booking
 * Plugin URI: https://flavordeveloper.com/bookingx/addons/facebook-booking
 * Description: Enable booking directly from your Facebook Business Page. Add a Book Now button and sync appointments with your BookingX calendar.
 * Version: 1.0.0
 * Author: Flavor Developer
 * Author URI: https://flavordeveloper.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-facebook-booking
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0
 *
 * @package BookingX\FacebookBooking
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'BKX_FB_BOOKING_VERSION', '1.0.0' );
define( 'BKX_FB_BOOKING_FILE', __FILE__ );
define( 'BKX_FB_BOOKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_FB_BOOKING_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_FB_BOOKING_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check dependencies.
 *
 * @return bool
 */
function bkx_fb_booking_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_fb_booking_missing_bookingx_notice' );
		return false;
	}
	return true;
}

/**
 * Missing BookingX notice.
 */
function bkx_fb_booking_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires BookingX plugin to be installed and activated.', 'bkx-facebook-booking' ),
				'<strong>BookingX - Facebook Booking</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize plugin.
 */
function bkx_fb_booking_init() {
	if ( ! bkx_fb_booking_check_dependencies() ) {
		return;
	}

	require_once BKX_FB_BOOKING_PATH . 'src/autoload.php';

	$addon = new BookingX\FacebookBooking\FacebookBookingAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_fb_booking_init', 20 );

/**
 * Activation hook.
 */
function bkx_fb_booking_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Facebook pages table.
	$sql_pages = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_fb_pages (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		page_id varchar(255) NOT NULL,
		page_name varchar(255) NOT NULL,
		access_token text NOT NULL,
		token_expires datetime DEFAULT NULL,
		category varchar(100) DEFAULT NULL,
		page_url varchar(255) DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		connected_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		last_sync datetime DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY page_id (page_id),
		KEY status (status)
	) $charset_collate;";

	// Facebook bookings table.
	$sql_bookings = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_fb_bookings (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		fb_booking_id varchar(255) NOT NULL,
		bkx_booking_id bigint(20) unsigned DEFAULT NULL,
		page_id varchar(255) NOT NULL,
		service_id bigint(20) unsigned DEFAULT NULL,
		fb_user_id varchar(255) DEFAULT NULL,
		customer_name varchar(255) DEFAULT NULL,
		customer_email varchar(255) DEFAULT NULL,
		customer_phone varchar(50) DEFAULT NULL,
		booking_date date NOT NULL,
		start_time time NOT NULL,
		end_time time DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		source varchar(50) DEFAULT 'facebook_page',
		notes text DEFAULT NULL,
		raw_data longtext DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY fb_booking_id (fb_booking_id),
		KEY bkx_booking_id (bkx_booking_id),
		KEY page_id (page_id),
		KEY fb_user_id (fb_user_id),
		KEY status (status),
		KEY booking_date (booking_date)
	) $charset_collate;";

	// Webhook events log.
	$sql_webhooks = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_fb_webhooks (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_type varchar(100) NOT NULL,
		page_id varchar(255) DEFAULT NULL,
		payload longtext NOT NULL,
		processed tinyint(1) DEFAULT 0,
		error_message text DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY event_type (event_type),
		KEY page_id (page_id),
		KEY processed (processed),
		KEY created_at (created_at)
	) $charset_collate;";

	// Service mappings.
	$sql_services = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_fb_services (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		page_id varchar(255) NOT NULL,
		bkx_service_id bigint(20) unsigned NOT NULL,
		fb_service_id varchar(255) DEFAULT NULL,
		name varchar(255) NOT NULL,
		description text DEFAULT NULL,
		price decimal(10,2) DEFAULT NULL,
		duration_minutes int DEFAULT 60,
		enabled tinyint(1) DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY page_id (page_id),
		KEY bkx_service_id (bkx_service_id),
		KEY enabled (enabled)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_pages );
	dbDelta( $sql_bookings );
	dbDelta( $sql_webhooks );
	dbDelta( $sql_services );

	// Set version.
	update_option( 'bkx_fb_booking_db_version', BKX_FB_BOOKING_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_fb_booking_activate' );

/**
 * Deactivation hook.
 */
function bkx_fb_booking_deactivate() {
	wp_clear_scheduled_hook( 'bkx_fb_sync_bookings' );
	wp_clear_scheduled_hook( 'bkx_fb_refresh_tokens' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_fb_booking_deactivate' );

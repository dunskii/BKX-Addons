<?php
/**
 * Plugin Name: BookingX - Reserve with Google
 * Plugin URI: https://flavordeveloper.com/bookingx/addons/reserve-with-google
 * Description: Enable Reserve with Google integration for direct bookings from Google Maps, Search, and Assistant. Let customers book appointments directly from your Google Business Profile.
 * Version: 1.0.0
 * Author: Flavor Developer
 * Author URI: https://flavordeveloper.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-reserve-google
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0
 *
 * @package BookingX\ReserveGoogle
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'BKX_RESERVE_GOOGLE_VERSION', '1.0.0' );
define( 'BKX_RESERVE_GOOGLE_FILE', __FILE__ );
define( 'BKX_RESERVE_GOOGLE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_RESERVE_GOOGLE_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_RESERVE_GOOGLE_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check dependencies.
 *
 * @return bool
 */
function bkx_reserve_google_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_reserve_google_missing_bookingx_notice' );
		return false;
	}
	return true;
}

/**
 * Missing BookingX notice.
 */
function bkx_reserve_google_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires BookingX plugin to be installed and activated.', 'bkx-reserve-google' ),
				'<strong>BookingX - Reserve with Google</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize plugin.
 */
function bkx_reserve_google_init() {
	if ( ! bkx_reserve_google_check_dependencies() ) {
		return;
	}

	require_once BKX_RESERVE_GOOGLE_PATH . 'src/autoload.php';

	$addon = new BookingX\ReserveGoogle\ReserveGoogleAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_reserve_google_init', 20 );

/**
 * Activation hook.
 */
function bkx_reserve_google_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Merchant info table.
	$sql_merchants = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_rwg_merchants (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		merchant_id varchar(255) NOT NULL,
		name varchar(255) NOT NULL,
		place_id varchar(255) DEFAULT NULL,
		address text DEFAULT NULL,
		phone varchar(50) DEFAULT NULL,
		website varchar(255) DEFAULT NULL,
		category varchar(100) DEFAULT NULL,
		timezone varchar(100) DEFAULT 'UTC',
		status varchar(20) NOT NULL DEFAULT 'pending',
		verified_at datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY merchant_id (merchant_id),
		KEY place_id (place_id),
		KEY status (status)
	) $charset_collate;";

	// Service mappings table.
	$sql_services = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_rwg_services (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		merchant_id varchar(255) NOT NULL,
		bkx_service_id bigint(20) unsigned NOT NULL,
		rwg_service_id varchar(255) DEFAULT NULL,
		name varchar(255) NOT NULL,
		description text DEFAULT NULL,
		price decimal(10,2) DEFAULT NULL,
		currency varchar(3) DEFAULT 'USD',
		duration_minutes int NOT NULL DEFAULT 60,
		category varchar(100) DEFAULT NULL,
		prepayment_type varchar(20) DEFAULT 'NOT_SUPPORTED',
		require_credit_card tinyint(1) DEFAULT 0,
		enabled tinyint(1) DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY merchant_id (merchant_id),
		KEY bkx_service_id (bkx_service_id),
		KEY rwg_service_id (rwg_service_id),
		KEY enabled (enabled)
	) $charset_collate;";

	// Availability slots table.
	$sql_slots = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_rwg_slots (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		service_id bigint(20) unsigned NOT NULL,
		staff_id bigint(20) unsigned DEFAULT NULL,
		slot_date date NOT NULL,
		start_time time NOT NULL,
		end_time time NOT NULL,
		spots_total int NOT NULL DEFAULT 1,
		spots_open int NOT NULL DEFAULT 1,
		status varchar(20) NOT NULL DEFAULT 'available',
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY service_id (service_id),
		KEY staff_id (staff_id),
		KEY slot_date (slot_date),
		KEY status (status),
		KEY service_date (service_id, slot_date)
	) $charset_collate;";

	// Booking sync log.
	$sql_bookings = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_rwg_bookings (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		rwg_booking_id varchar(255) NOT NULL,
		bkx_booking_id bigint(20) unsigned DEFAULT NULL,
		merchant_id varchar(255) NOT NULL,
		service_id varchar(255) NOT NULL,
		slot_id bigint(20) unsigned DEFAULT NULL,
		customer_email varchar(255) DEFAULT NULL,
		customer_name varchar(255) DEFAULT NULL,
		customer_phone varchar(50) DEFAULT NULL,
		booking_date date NOT NULL,
		start_time time NOT NULL,
		end_time time NOT NULL,
		party_size int DEFAULT 1,
		status varchar(20) NOT NULL DEFAULT 'confirmed',
		payment_status varchar(20) DEFAULT NULL,
		payment_amount decimal(10,2) DEFAULT NULL,
		idempotency_token varchar(255) DEFAULT NULL,
		source varchar(50) DEFAULT 'google_maps',
		raw_request longtext DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY rwg_booking_id (rwg_booking_id),
		KEY bkx_booking_id (bkx_booking_id),
		KEY merchant_id (merchant_id),
		KEY slot_id (slot_id),
		KEY status (status),
		KEY booking_date (booking_date),
		KEY idempotency_token (idempotency_token)
	) $charset_collate;";

	// API request logs.
	$sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_rwg_logs (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		endpoint varchar(100) NOT NULL,
		method varchar(10) NOT NULL,
		request_payload longtext DEFAULT NULL,
		response_payload longtext DEFAULT NULL,
		response_code int DEFAULT NULL,
		processing_time int DEFAULT NULL,
		error_message text DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY endpoint (endpoint),
		KEY response_code (response_code),
		KEY created_at (created_at)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_merchants );
	dbDelta( $sql_services );
	dbDelta( $sql_slots );
	dbDelta( $sql_bookings );
	dbDelta( $sql_logs );

	// Set version.
	update_option( 'bkx_reserve_google_db_version', BKX_RESERVE_GOOGLE_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_reserve_google_activate' );

/**
 * Deactivation hook.
 */
function bkx_reserve_google_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_rwg_sync_availability' );
	wp_clear_scheduled_hook( 'bkx_rwg_cleanup_slots' );

	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_reserve_google_deactivate' );

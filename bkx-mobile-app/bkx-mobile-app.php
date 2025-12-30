<?php
/**
 * Plugin Name: BookingX - Native Mobile App Framework
 * Plugin URI: https://bookingx.com/add-ons/mobile-app
 * Description: Complete framework for native iOS and Android apps - REST API, push notifications, deep linking, and app configuration.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-mobile-app
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\MobileApp
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_MOBILE_APP_VERSION', '1.0.0' );
define( 'BKX_MOBILE_APP_PLUGIN_FILE', __FILE__ );
define( 'BKX_MOBILE_APP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_MOBILE_APP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_MOBILE_APP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Admin notice for missing BookingX.
 */
function bkx_mobile_app_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin link */
				esc_html__( 'BookingX Native Mobile App Framework requires the %s plugin to be installed and activated.', 'bkx-mobile-app' ),
				'<a href="https://bookingx.com" target="_blank">BookingX</a>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_mobile_app_init() {
	// Check for BookingX.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_mobile_app_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_MOBILE_APP_PLUGIN_DIR . 'src/autoload.php';

	// Initialize addon.
	\BookingX\MobileApp\MobileAppAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_mobile_app_init', 20 );

/**
 * Activation hook.
 */
function bkx_mobile_app_activate() {
	global $wpdb;

	// Create device tokens table.
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_mobile_devices (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id bigint(20) UNSIGNED DEFAULT NULL,
		device_token varchar(500) NOT NULL,
		device_type varchar(20) NOT NULL,
		device_name varchar(255) DEFAULT NULL,
		device_model varchar(100) DEFAULT NULL,
		os_version varchar(50) DEFAULT NULL,
		app_version varchar(20) DEFAULT NULL,
		push_enabled tinyint(1) DEFAULT 1,
		last_active datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY device_token (device_token(255)),
		KEY user_id (user_id),
		KEY device_type (device_type)
	) $charset_collate;";

	// Create API keys table.
	$sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_mobile_api_keys (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		key_name varchar(100) NOT NULL,
		api_key varchar(64) NOT NULL,
		api_secret varchar(64) NOT NULL,
		permissions text DEFAULT NULL,
		rate_limit int(11) DEFAULT 1000,
		is_active tinyint(1) DEFAULT 1,
		last_used datetime DEFAULT NULL,
		created_by bigint(20) UNSIGNED NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY api_key (api_key)
	) $charset_collate;";

	// Create push notification log table.
	$sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_mobile_push_log (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		device_id bigint(20) UNSIGNED DEFAULT NULL,
		user_id bigint(20) UNSIGNED DEFAULT NULL,
		notification_type varchar(50) NOT NULL,
		title varchar(255) NOT NULL,
		body text DEFAULT NULL,
		data longtext DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		error_message text DEFAULT NULL,
		sent_at datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY device_id (device_id),
		KEY user_id (user_id),
		KEY status (status),
		KEY notification_type (notification_type)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	// Set default options.
	$defaults = array(
		'enabled'               => true,
		'ios_enabled'           => true,
		'android_enabled'       => true,
		'fcm_server_key'        => '',
		'apns_key_id'           => '',
		'apns_team_id'          => '',
		'apns_bundle_id'        => '',
		'apns_key_file'         => '',
		'deep_link_scheme'      => 'bookingx',
		'app_store_url'         => '',
		'play_store_url'        => '',
		'push_booking_created'  => true,
		'push_booking_confirmed' => true,
		'push_booking_reminder' => true,
		'push_booking_cancelled' => true,
		'reminder_hours'        => 24,
	);

	if ( ! get_option( 'bkx_mobile_app_settings' ) ) {
		add_option( 'bkx_mobile_app_settings', $defaults );
	}

	// Schedule cron for push notification reminders.
	if ( ! wp_next_scheduled( 'bkx_mobile_app_send_reminders' ) ) {
		wp_schedule_event( time(), 'hourly', 'bkx_mobile_app_send_reminders' );
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_mobile_app_activate' );

/**
 * Deactivation hook.
 */
function bkx_mobile_app_deactivate() {
	wp_clear_scheduled_hook( 'bkx_mobile_app_send_reminders' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_mobile_app_deactivate' );

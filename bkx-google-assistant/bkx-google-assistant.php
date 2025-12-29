<?php
/**
 * Plugin Name: BookingX - Google Assistant Integration
 * Plugin URI: https://flavordeveloper.com/bookingx/addons/google-assistant
 * Description: Enable voice booking through Google Assistant. Let customers book appointments using voice commands on Google Home, Android phones, and smart displays.
 * Version: 1.0.0
 * Author: Flavor Developer
 * Author URI: https://flavordeveloper.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-google-assistant
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0
 *
 * @package BookingX\GoogleAssistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'BKX_GOOGLE_ASSISTANT_VERSION', '1.0.0' );
define( 'BKX_GOOGLE_ASSISTANT_FILE', __FILE__ );
define( 'BKX_GOOGLE_ASSISTANT_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_GOOGLE_ASSISTANT_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_GOOGLE_ASSISTANT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check dependencies.
 *
 * @return bool
 */
function bkx_google_assistant_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_google_assistant_missing_bookingx_notice' );
		return false;
	}
	return true;
}

/**
 * Missing BookingX notice.
 */
function bkx_google_assistant_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires BookingX plugin to be installed and activated.', 'bkx-google-assistant' ),
				'<strong>BookingX - Google Assistant Integration</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize plugin.
 */
function bkx_google_assistant_init() {
	if ( ! bkx_google_assistant_check_dependencies() ) {
		return;
	}

	require_once BKX_GOOGLE_ASSISTANT_PATH . 'src/autoload.php';

	$addon = new BookingX\GoogleAssistant\GoogleAssistantAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_google_assistant_init', 20 );

/**
 * Activation hook.
 */
function bkx_google_assistant_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Voice sessions table.
	$sql_sessions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_assistant_sessions (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		session_id varchar(255) NOT NULL,
		user_id varchar(255) DEFAULT NULL,
		context longtext DEFAULT NULL,
		intent varchar(100) DEFAULT NULL,
		booking_data longtext DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		expires_at datetime NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY session_id (session_id),
		KEY user_id (user_id),
		KEY status (status),
		KEY expires_at (expires_at)
	) $charset_collate;";

	// Voice bookings log.
	$sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_assistant_logs (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		session_id varchar(255) DEFAULT NULL,
		booking_id bigint(20) unsigned DEFAULT NULL,
		intent varchar(100) NOT NULL,
		request_payload longtext DEFAULT NULL,
		response_payload longtext DEFAULT NULL,
		user_query text DEFAULT NULL,
		response_text text DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'success',
		error_message text DEFAULT NULL,
		processing_time int DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY session_id (session_id),
		KEY booking_id (booking_id),
		KEY intent (intent),
		KEY status (status),
		KEY created_at (created_at)
	) $charset_collate;";

	// Linked accounts.
	$sql_accounts = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_assistant_accounts (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		google_user_id varchar(255) NOT NULL,
		wp_user_id bigint(20) unsigned DEFAULT NULL,
		customer_email varchar(255) DEFAULT NULL,
		customer_name varchar(255) DEFAULT NULL,
		access_token text DEFAULT NULL,
		refresh_token text DEFAULT NULL,
		token_expires datetime DEFAULT NULL,
		linked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		last_used datetime DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY google_user_id (google_user_id),
		KEY wp_user_id (wp_user_id),
		KEY customer_email (customer_email)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_sessions );
	dbDelta( $sql_logs );
	dbDelta( $sql_accounts );

	// Set version.
	update_option( 'bkx_google_assistant_db_version', BKX_GOOGLE_ASSISTANT_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_google_assistant_activate' );

/**
 * Deactivation hook.
 */
function bkx_google_assistant_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_google_assistant_deactivate' );

<?php
/**
 * Plugin Name: BookingX - Slack Integration
 * Plugin URI: https://flavordeveloper.com/bookingx/addons/slack
 * Description: Connect BookingX with Slack for real-time booking notifications, team coordination, and slash command booking management.
 * Version: 1.0.0
 * Author: Flavor Developer
 * Author URI: https://flavordeveloper.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-slack
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0
 *
 * @package BookingX\Slack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'BKX_SLACK_VERSION', '1.0.0' );
define( 'BKX_SLACK_FILE', __FILE__ );
define( 'BKX_SLACK_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_SLACK_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_SLACK_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check dependencies.
 *
 * @return bool
 */
function bkx_slack_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_slack_missing_bookingx_notice' );
		return false;
	}
	return true;
}

/**
 * Missing BookingX notice.
 */
function bkx_slack_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires BookingX plugin to be installed and activated.', 'bkx-slack' ),
				'<strong>BookingX - Slack Integration</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize plugin.
 */
function bkx_slack_init() {
	if ( ! bkx_slack_check_dependencies() ) {
		return;
	}

	require_once BKX_SLACK_PATH . 'src/autoload.php';

	$addon = new BookingX\Slack\SlackAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_slack_init', 20 );

/**
 * Activation hook.
 */
function bkx_slack_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Slack workspaces table.
	$sql_workspaces = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_slack_workspaces (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		team_id varchar(50) NOT NULL,
		team_name varchar(255) NOT NULL,
		access_token text NOT NULL,
		bot_user_id varchar(50) DEFAULT NULL,
		bot_access_token text DEFAULT NULL,
		scope text DEFAULT NULL,
		incoming_webhook_url text DEFAULT NULL,
		incoming_webhook_channel varchar(100) DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		connected_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		last_activity datetime DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY team_id (team_id),
		KEY status (status)
	) $charset_collate;";

	// Channel configurations table.
	$sql_channels = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_slack_channels (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		workspace_id bigint(20) unsigned NOT NULL,
		channel_id varchar(50) NOT NULL,
		channel_name varchar(100) NOT NULL,
		notification_types text DEFAULT NULL,
		is_default tinyint(1) DEFAULT 0,
		enabled tinyint(1) DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY workspace_id (workspace_id),
		KEY channel_id (channel_id),
		KEY enabled (enabled)
	) $charset_collate;";

	// Notification log table.
	$sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_slack_logs (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		workspace_id bigint(20) unsigned DEFAULT NULL,
		channel_id varchar(50) DEFAULT NULL,
		event_type varchar(50) NOT NULL,
		booking_id bigint(20) unsigned DEFAULT NULL,
		message text NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'sent',
		error_message text DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY workspace_id (workspace_id),
		KEY event_type (event_type),
		KEY booking_id (booking_id),
		KEY created_at (created_at)
	) $charset_collate;";

	// User mappings table (Slack user to WP user).
	$sql_users = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_slack_users (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		workspace_id bigint(20) unsigned NOT NULL,
		slack_user_id varchar(50) NOT NULL,
		wp_user_id bigint(20) unsigned DEFAULT NULL,
		staff_id bigint(20) unsigned DEFAULT NULL,
		display_name varchar(255) DEFAULT NULL,
		email varchar(255) DEFAULT NULL,
		notify_new_bookings tinyint(1) DEFAULT 1,
		notify_cancellations tinyint(1) DEFAULT 1,
		notify_reminders tinyint(1) DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY workspace_id (workspace_id),
		KEY slack_user_id (slack_user_id),
		KEY wp_user_id (wp_user_id),
		KEY staff_id (staff_id)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_workspaces );
	dbDelta( $sql_channels );
	dbDelta( $sql_logs );
	dbDelta( $sql_users );

	// Set version.
	update_option( 'bkx_slack_db_version', BKX_SLACK_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_slack_activate' );

/**
 * Deactivation hook.
 */
function bkx_slack_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_slack_deactivate' );

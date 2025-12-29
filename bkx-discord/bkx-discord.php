<?php
/**
 * Plugin Name: BookingX - Discord Notifications
 * Plugin URI: https://flavflavor.developer.com/discord-notifications/
 * Description: Send booking notifications to Discord channels via webhooks and bot integration.
 * Version: 1.0.0
 * Author: flavorflavor.developer@gmail.com
 * Author URI: https://flavorflavor.developer.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-discord
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 *
 * @package BookingX\Discord
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_DISCORD_VERSION', '1.0.0' );
define( 'BKX_DISCORD_PLUGIN_FILE', __FILE__ );
define( 'BKX_DISCORD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_DISCORD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_DISCORD_MIN_BOOKINGX_VERSION', '2.0.0' );

/**
 * Check if BookingX is active.
 *
 * @return bool
 */
function bkx_discord_is_bookingx_active() {
	return class_exists( 'Bookingx' ) || defined( 'STARTER_BKX_STARTER_PLUGIN_DIR_PATH' );
}

/**
 * Admin notice for missing BookingX.
 */
function bkx_discord_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX plugin to be installed and activated.', 'bkx-discord' ),
				'<strong>BookingX - Discord Notifications</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_discord_init() {
	if ( ! bkx_discord_is_bookingx_active() ) {
		add_action( 'admin_notices', 'bkx_discord_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_DISCORD_PLUGIN_DIR . 'src/autoload.php';

	// Initialize addon.
	\BookingX\Discord\DiscordAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_discord_init', 20 );

/**
 * Plugin activation.
 */
function bkx_discord_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Webhooks table.
	$webhooks_table = $wpdb->prefix . 'bkx_discord_webhooks';
	$sql_webhooks = "CREATE TABLE {$webhooks_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		webhook_url text NOT NULL,
		guild_id varchar(100) DEFAULT '',
		channel_id varchar(100) DEFAULT '',
		channel_name varchar(255) DEFAULT '',
		avatar_url text DEFAULT NULL,
		notify_events text DEFAULT NULL,
		is_active tinyint(1) DEFAULT 1,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY is_active (is_active)
	) {$charset_collate};";

	// Logs table.
	$logs_table = $wpdb->prefix . 'bkx_discord_logs';
	$sql_logs = "CREATE TABLE {$logs_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		webhook_id bigint(20) unsigned DEFAULT NULL,
		booking_id bigint(20) unsigned DEFAULT NULL,
		event_type varchar(50) NOT NULL,
		message_id varchar(100) DEFAULT NULL,
		status enum('sent','failed') NOT NULL,
		error_message text DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY webhook_id (webhook_id),
		KEY booking_id (booking_id),
		KEY event_type (event_type),
		KEY status (status),
		KEY created_at (created_at)
	) {$charset_collate};";

	// Bot connections table.
	$bots_table = $wpdb->prefix . 'bkx_discord_bots';
	$sql_bots = "CREATE TABLE {$bots_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		bot_token varchar(255) NOT NULL,
		application_id varchar(100) NOT NULL,
		public_key varchar(255) NOT NULL,
		guild_id varchar(100) NOT NULL,
		guild_name varchar(255) DEFAULT '',
		status enum('active','inactive','error') DEFAULT 'inactive',
		permissions bigint(20) unsigned DEFAULT 0,
		connected_at datetime DEFAULT NULL,
		last_ping datetime DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY guild_id (guild_id),
		KEY status (status)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_webhooks );
	dbDelta( $sql_logs );
	dbDelta( $sql_bots );

	// Set default options.
	$defaults = array(
		'embed_color'       => '#5865F2',
		'bot_username'      => 'BookingX',
		'include_customer'  => true,
		'include_staff'     => true,
		'include_price'     => true,
		'mention_role'      => '',
		'notify_new'        => true,
		'notify_cancelled'  => true,
		'notify_completed'  => true,
		'notify_rescheduled' => true,
	);

	add_option( 'bkx_discord_settings', $defaults );
	add_option( 'bkx_discord_db_version', BKX_DISCORD_VERSION );

	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_discord_activate' );

/**
 * Plugin deactivation.
 */
function bkx_discord_deactivate() {
	wp_clear_scheduled_hook( 'bkx_discord_cleanup_logs' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_discord_deactivate' );

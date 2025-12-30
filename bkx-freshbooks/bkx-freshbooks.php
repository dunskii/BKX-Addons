<?php
/**
 * Plugin Name: BookingX - FreshBooks Integration
 * Plugin URI: https://flavor-flavor-flavor.local/plugins/bkx-freshbooks
 * Description: Sync BookingX bookings with FreshBooks for invoicing, expenses, and client management.
 * Version: 1.0.0
 * Author: flavor-flavor-flavor.local
 * Author URI: https://flavor-flavor-flavor.local
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-freshbooks
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\FreshBooks
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_FRESHBOOKS_VERSION', '1.0.0' );
define( 'BKX_FRESHBOOKS_PLUGIN_FILE', __FILE__ );
define( 'BKX_FRESHBOOKS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_FRESHBOOKS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_FRESHBOOKS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if BookingX is active.
 *
 * @return bool
 */
function bkx_freshbooks_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_freshbooks_missing_bookingx_notice' );
		return false;
	}
	return true;
}

/**
 * Show notice if BookingX is not active.
 */
function bkx_freshbooks_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX plugin to be installed and activated.', 'bkx-freshbooks' ),
				'<strong>BookingX FreshBooks Integration</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_freshbooks_init() {
	if ( ! bkx_freshbooks_check_dependencies() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_FRESHBOOKS_PLUGIN_DIR . 'src/autoload.php';

	// Initialize addon.
	\BookingX\FreshBooks\FreshBooksAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_freshbooks_init', 20 );

/**
 * Plugin activation.
 */
function bkx_freshbooks_activate() {
	// Set default options.
	$defaults = array(
		'enabled'              => false,
		'client_id'            => '',
		'client_secret'        => '',
		'access_token'         => '',
		'refresh_token'        => '',
		'token_expires'        => 0,
		'account_id'           => '',
		'business_id'          => '',
		'auto_sync'            => true,
		'sync_clients'         => true,
		'sync_invoices'        => true,
		'sync_payments'        => true,
		'sync_expenses'        => false,
		'default_service_code' => '',
		'invoice_due_days'     => 14,
		'send_invoice_email'   => false,
		'sync_on_complete'     => true,
		'sync_on_payment'      => true,
	);

	if ( ! get_option( 'bkx_freshbooks_settings' ) ) {
		add_option( 'bkx_freshbooks_settings', $defaults );
	}

	// Create sync log table.
	bkx_freshbooks_create_tables();

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_freshbooks_activate' );

/**
 * Create database tables.
 */
function bkx_freshbooks_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$table_name      = $wpdb->prefix . 'bkx_freshbooks_sync_log';

	$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		booking_id bigint(20) UNSIGNED NOT NULL,
		fb_type varchar(50) NOT NULL,
		fb_id varchar(100) DEFAULT NULL,
		fb_number varchar(50) DEFAULT NULL,
		sync_status varchar(20) NOT NULL DEFAULT 'pending',
		sync_direction varchar(20) NOT NULL DEFAULT 'to_freshbooks',
		error_message text DEFAULT NULL,
		synced_at datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY booking_id (booking_id),
		KEY fb_id (fb_id),
		KEY sync_status (sync_status)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/**
 * Plugin deactivation.
 */
function bkx_freshbooks_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_freshbooks_sync_cron' );
	wp_clear_scheduled_hook( 'bkx_freshbooks_refresh_token' );

	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_freshbooks_deactivate' );

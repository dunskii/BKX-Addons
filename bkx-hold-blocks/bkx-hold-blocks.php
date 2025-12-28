<?php
/**
 * Plugin Name: BookingX - Hold Date/Time Blocks
 * Plugin URI: https://flavflavor.dev/bookingx/addons/hold-blocks
 * Description: Create blocked time periods for holidays, maintenance, breaks, and private events.
 * Version: 1.0.0
 * Author: FlavFlavor
 * Author URI: https://flavflavor.dev
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-hold-blocks
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\HoldBlocks
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_HOLD_BLOCKS_VERSION', '1.0.0' );
define( 'BKX_HOLD_BLOCKS_FILE', __FILE__ );
define( 'BKX_HOLD_BLOCKS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_HOLD_BLOCKS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_HOLD_BLOCKS_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_HOLD_BLOCKS_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_hold_blocks_init(): void {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_hold_blocks_missing_bookingx_notice' );
		return;
	}

	// Check if SDK is available.
	if ( ! class_exists( 'BookingX\\AddonSDK\\Abstracts\\AbstractAddon' ) ) {
		$sdk_path = dirname( __DIR__ ) . '/_shared/bkx-addon-sdk/src/autoload.php';
		if ( file_exists( $sdk_path ) ) {
			require_once $sdk_path;
		} else {
			add_action( 'admin_notices', 'bkx_hold_blocks_missing_sdk_notice' );
			return;
		}
	}

	// Boot the addon.
	\BookingX\HoldBlocks\HoldBlocksAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_hold_blocks_init' );

/**
 * Missing BookingX notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_hold_blocks_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX Hold Blocks requires BookingX to be installed and activated.', 'bkx-hold-blocks' ); ?></p>
	</div>
	<?php
}

/**
 * Missing SDK notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_hold_blocks_missing_sdk_notice(): void {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX Hold Blocks requires the BookingX Addon SDK.', 'bkx-hold-blocks' ); ?></p>
	</div>
	<?php
}

/**
 * Activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_hold_blocks_activate(): void {
	// Run migrations.
	if ( class_exists( 'BookingX\\HoldBlocks\\Migrations\\CreateBlocksTable' ) ) {
		$migration = new \BookingX\HoldBlocks\Migrations\CreateBlocksTable();
		$migration->up();
	}
}
register_activation_hook( __FILE__, 'bkx_hold_blocks_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_hold_blocks_deactivate(): void {
	// Clear any scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_hold_blocks_cleanup' );
}
register_deactivation_hook( __FILE__, 'bkx_hold_blocks_deactivate' );

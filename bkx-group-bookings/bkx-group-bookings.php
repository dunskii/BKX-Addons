<?php
/**
 * Plugin Name: BookingX - Group Bookings & Quantity
 * Plugin URI: https://flavflavor.dev/bookingx/addons/group-bookings
 * Description: Enable quantity-based bookings for groups, parties, and multi-person reservations.
 * Version: 1.0.0
 * Author: FlavFlavor
 * Author URI: https://flavflavor.dev
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-group-bookings
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\GroupBookings
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_GROUP_BOOKINGS_VERSION', '1.0.0' );
define( 'BKX_GROUP_BOOKINGS_FILE', __FILE__ );
define( 'BKX_GROUP_BOOKINGS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_GROUP_BOOKINGS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_GROUP_BOOKINGS_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_GROUP_BOOKINGS_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_group_bookings_init(): void {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_group_bookings_missing_bookingx_notice' );
		return;
	}

	// Check if SDK is available.
	if ( ! class_exists( 'BookingX\\AddonSDK\\Abstracts\\AbstractAddon' ) ) {
		$sdk_path = dirname( __DIR__ ) . '/_shared/bkx-addon-sdk/src/autoload.php';
		if ( file_exists( $sdk_path ) ) {
			require_once $sdk_path;
		} else {
			add_action( 'admin_notices', 'bkx_group_bookings_missing_sdk_notice' );
			return;
		}
	}

	// Boot the addon.
	\BookingX\GroupBookings\GroupBookingsAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_group_bookings_init' );

/**
 * Missing BookingX notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_group_bookings_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX Group Bookings requires BookingX to be installed and activated.', 'bkx-group-bookings' ); ?></p>
	</div>
	<?php
}

/**
 * Missing SDK notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_group_bookings_missing_sdk_notice(): void {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX Group Bookings requires the BookingX Addon SDK.', 'bkx-group-bookings' ); ?></p>
	</div>
	<?php
}

/**
 * Activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_group_bookings_activate(): void {
	// Run migrations.
	if ( class_exists( 'BookingX\\GroupBookings\\Migrations\\CreateGroupTables' ) ) {
		$migration = new \BookingX\GroupBookings\Migrations\CreateGroupTables();
		$migration->up();
	}
}
register_activation_hook( __FILE__, 'bkx_group_bookings_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_group_bookings_deactivate(): void {
	// Nothing to clean up on deactivation.
}
register_deactivation_hook( __FILE__, 'bkx_group_bookings_deactivate' );

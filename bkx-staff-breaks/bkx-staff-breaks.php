<?php
/**
 * Plugin Name: BookingX - Staff Breaks & Time Off
 * Plugin URI: https://flavflavor.dev/bookingx/addons/staff-breaks
 * Description: Manage staff breaks, time off, vacations, and blocked time slots.
 * Version: 1.0.0
 * Author: FlavFlavor
 * Author URI: https://flavflavor.dev
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-staff-breaks
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\StaffBreaks
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_STAFF_BREAKS_VERSION', '1.0.0' );
define( 'BKX_STAFF_BREAKS_FILE', __FILE__ );
define( 'BKX_STAFF_BREAKS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_STAFF_BREAKS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_STAFF_BREAKS_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_STAFF_BREAKS_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_staff_breaks_init(): void {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_staff_breaks_missing_bookingx_notice' );
		return;
	}

	// Check if SDK is available.
	if ( ! class_exists( 'BookingX\\AddonSDK\\Abstracts\\AbstractAddon' ) ) {
		$sdk_path = dirname( __DIR__ ) . '/_shared/bkx-addon-sdk/src/autoload.php';
		if ( file_exists( $sdk_path ) ) {
			require_once $sdk_path;
		} else {
			add_action( 'admin_notices', 'bkx_staff_breaks_missing_sdk_notice' );
			return;
		}
	}

	// Boot the addon.
	\BookingX\StaffBreaks\StaffBreaksAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_staff_breaks_init' );

/**
 * Missing BookingX notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_staff_breaks_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX Staff Breaks & Time Off requires BookingX to be installed and activated.', 'bkx-staff-breaks' ); ?></p>
	</div>
	<?php
}

/**
 * Missing SDK notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_staff_breaks_missing_sdk_notice(): void {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX Staff Breaks & Time Off requires the BookingX Addon SDK.', 'bkx-staff-breaks' ); ?></p>
	</div>
	<?php
}

/**
 * Activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_staff_breaks_activate(): void {
	// Run migrations.
	if ( class_exists( 'BookingX\\StaffBreaks\\Migrations\\CreateBreaksTables' ) ) {
		$migration = new \BookingX\StaffBreaks\Migrations\CreateBreaksTables();
		$migration->up();
	}
}
register_activation_hook( __FILE__, 'bkx_staff_breaks_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_staff_breaks_deactivate(): void {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_staff_breaks_cleanup' );
}
register_deactivation_hook( __FILE__, 'bkx_staff_breaks_deactivate' );

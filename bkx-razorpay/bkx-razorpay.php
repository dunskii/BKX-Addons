<?php
/**
 * Plugin Name: BookingX Razorpay
 * Plugin URI: https://developer.bookingx.com/addons/razorpay
 * Description: Razorpay payment gateway integration for BookingX with UPI, cards, netbanking, and wallet support.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-razorpay
 * Domain Path: /languages
 *
 * @package BookingX\Razorpay
 */

namespace BookingX\Razorpay;

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'BKX_RAZORPAY_VERSION', '1.0.0' );
define( 'BKX_RAZORPAY_FILE', __FILE__ );
define( 'BKX_RAZORPAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_RAZORPAY_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_RAZORPAY_BASENAME', plugin_basename( __FILE__ ) );

// Require Composer autoloader.
if ( file_exists( BKX_RAZORPAY_PATH . 'vendor/autoload.php' ) ) {
	require_once BKX_RAZORPAY_PATH . 'vendor/autoload.php';
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_razorpay_init(): void {
	// Check for BookingX core plugin.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\bkx_razorpay_missing_core_notice' );
		return;
	}

	// Check for SDK.
	if ( ! class_exists( 'BookingX\\AddonSDK\\Abstracts\\AbstractAddon' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\bkx_razorpay_missing_sdk_notice' );
		return;
	}

	// Initialize the addon.
	RazorpayAddon::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bkx_razorpay_init', 20 );

/**
 * Display missing BookingX core notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_razorpay_missing_core_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires BookingX core plugin to be installed and activated.', 'bkx-razorpay' ),
				'<strong>BookingX Razorpay</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Display missing SDK notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_razorpay_missing_sdk_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires the BookingX Add-on SDK to be installed.', 'bkx-razorpay' ),
				'<strong>BookingX Razorpay</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_razorpay_activate(): void {
	// Set activation flag for migration.
	update_option( 'bkx_razorpay_activated', true );
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\bkx_razorpay_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_razorpay_deactivate(): void {
	// Clean up scheduled events.
	wp_clear_scheduled_hook( 'bkx_razorpay_sync_payments' );
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\bkx_razorpay_deactivate' );

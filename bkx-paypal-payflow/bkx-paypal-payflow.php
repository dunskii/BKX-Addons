<?php
/**
 * Plugin Name: BookingX - PayPal Payflow Pro
 * Plugin URI: https://flavflavor.dev/bookingx/addons/paypal-payflow
 * Description: Accept payments via PayPal Payflow Pro gateway with advanced fraud protection and virtual terminal.
 * Version: 1.0.0
 * Author: FlavFlavor
 * Author URI: https://flavflavor.dev
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-paypal-payflow
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\PayPalPayflow
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_PAYPAL_PAYFLOW_VERSION', '1.0.0' );
define( 'BKX_PAYPAL_PAYFLOW_FILE', __FILE__ );
define( 'BKX_PAYPAL_PAYFLOW_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_PAYPAL_PAYFLOW_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_PAYPAL_PAYFLOW_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_PAYPAL_PAYFLOW_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_paypal_payflow_init(): void {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_paypal_payflow_missing_bookingx_notice' );
		return;
	}

	// Check if SDK is available.
	if ( ! class_exists( 'BookingX\\AddonSDK\\Abstracts\\AbstractPaymentGateway' ) ) {
		$sdk_path = dirname( __DIR__ ) . '/_shared/bkx-addon-sdk/src/autoload.php';
		if ( file_exists( $sdk_path ) ) {
			require_once $sdk_path;
		} else {
			add_action( 'admin_notices', 'bkx_paypal_payflow_missing_sdk_notice' );
			return;
		}
	}

	// Boot the addon.
	\BookingX\PayPalPayflow\PayflowAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_paypal_payflow_init' );

/**
 * Missing BookingX notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_paypal_payflow_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX PayPal Payflow Pro requires BookingX to be installed and activated.', 'bkx-paypal-payflow' ); ?></p>
	</div>
	<?php
}

/**
 * Missing SDK notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_paypal_payflow_missing_sdk_notice(): void {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX PayPal Payflow Pro requires the BookingX Addon SDK.', 'bkx-paypal-payflow' ); ?></p>
	</div>
	<?php
}

/**
 * Activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_paypal_payflow_activate(): void {
	// Set default options.
	if ( ! get_option( 'bkx_paypal_payflow_settings' ) ) {
		update_option(
			'bkx_paypal_payflow_settings',
			array(
				'enabled'     => 0,
				'sandbox'     => 1,
				'title'       => __( 'Credit Card (PayPal Payflow)', 'bkx-paypal-payflow' ),
				'description' => __( 'Pay securely with your credit card.', 'bkx-paypal-payflow' ),
			)
		);
	}
}
register_activation_hook( __FILE__, 'bkx_paypal_payflow_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_paypal_payflow_deactivate(): void {
	// Nothing to clean up on deactivation.
}
register_deactivation_hook( __FILE__, 'bkx_paypal_payflow_deactivate' );

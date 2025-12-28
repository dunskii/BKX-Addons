<?php
/**
 * Plugin Name: BookingX - Google Pay
 * Plugin URI: https://bookingx.developer.com/addons/google-pay
 * Description: Accept Google Pay payments for BookingX bookings with one-click checkout.
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://developer.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-google-pay
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\GooglePay
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_GOOGLE_PAY_VERSION', '1.0.0' );
define( 'BKX_GOOGLE_PAY_FILE', __FILE__ );
define( 'BKX_GOOGLE_PAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_GOOGLE_PAY_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_GOOGLE_PAY_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_GOOGLE_PAY_PATH . 'src/autoload.php';

/**
 * Initialize the addon.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_google_pay_init(): void {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_google_pay_missing_bookingx_notice' );
		return;
	}

	// Check if Addon SDK is available.
	if ( ! class_exists( 'BookingX\\AddonSDK\\Abstracts\\AbstractAddon' ) ) {
		add_action( 'admin_notices', 'bkx_google_pay_missing_sdk_notice' );
		return;
	}

	// Initialize the addon.
	\BookingX\GooglePay\GooglePayAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_google_pay_init', 20 );

/**
 * Display missing BookingX notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_google_pay_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-google-pay' ),
				'<strong>BookingX - Google Pay</strong>'
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
function bkx_google_pay_missing_sdk_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires the BookingX Addon SDK to be installed.', 'bkx-google-pay' ),
				'<strong>BookingX - Google Pay</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_google_pay_activate(): void {
	// Set default options.
	if ( false === get_option( 'bkx_google_pay_settings' ) ) {
		update_option( 'bkx_google_pay_settings', array(
			'enabled'           => 0,
			'environment'       => 'TEST',
			'merchant_id'       => '',
			'merchant_name'     => get_bloginfo( 'name' ),
			'gateway'           => 'stripe',
			'gateway_merchant_id' => '',
			'button_color'      => 'black',
			'button_type'       => 'pay',
			'button_locale'     => 'en',
			'allowed_cards'     => array( 'AMEX', 'MASTERCARD', 'VISA', 'DISCOVER' ),
		) );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_google_pay_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_google_pay_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_google_pay_deactivate' );

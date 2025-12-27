<?php
/**
 * BookingX Coupon Codes & Discounts
 *
 * @package           BookingX\CouponCodes
 * @author            Starter Dev
 * @copyright         2024 BookingX
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       BookingX Coupon Codes & Discounts
 * Plugin URI:        https://bookingx.com/addons/coupon-codes
 * Description:       Add promotional coupons and discount codes to your BookingX bookings.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            BookingX
 * Author URI:        https://bookingx.com
 * Text Domain:       bkx-coupon-codes
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace BookingX\CouponCodes;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version.
define( 'BKX_COUPON_CODES_VERSION', '1.0.0' );

// Plugin file.
define( 'BKX_COUPON_CODES_FILE', __FILE__ );

// Plugin path.
define( 'BKX_COUPON_CODES_PATH', plugin_dir_path( __FILE__ ) );

// Plugin URL.
define( 'BKX_COUPON_CODES_URL', plugin_dir_url( __FILE__ ) );

// Plugin basename.
define( 'BKX_COUPON_CODES_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if BookingX is active.
 *
 * @return bool
 */
function bkx_coupon_codes_check_requirements(): bool {
	return defined( 'STARTER_DEVELOPER_VERSION' ) || defined( 'BKX_VERSION' );
}

/**
 * Display admin notice if BookingX is not active.
 *
 * @return void
 */
function bkx_coupon_codes_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-coupon-codes' ),
				'<strong>BookingX Coupon Codes</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function bkx_coupon_codes_init(): void {
	// Check requirements.
	if ( ! bkx_coupon_codes_check_requirements() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\bkx_coupon_codes_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_COUPON_CODES_PATH . 'src/autoload.php';

	// Initialize addon.
	$addon = CouponCodesAddon::get_instance();
	$addon->init();
}

/**
 * Plugin activation hook.
 *
 * @return void
 */
function bkx_coupon_codes_activate(): void {
	if ( ! bkx_coupon_codes_check_requirements() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'BookingX Coupon Codes requires BookingX to be installed and activated.', 'bkx-coupon-codes' ),
			'Plugin dependency check',
			array( 'back_link' => true )
		);
	}

	// Run migrations.
	require_once BKX_COUPON_CODES_PATH . 'src/autoload.php';
	$addon = CouponCodesAddon::get_instance();
	$addon->run_migrations();

	// Set default options.
	$addon->set_default_settings();

	// Flush rewrite rules.
	flush_rewrite_rules();
}

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function bkx_coupon_codes_deactivate(): void {
	// Flush rewrite rules.
	flush_rewrite_rules();
}

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, __NAMESPACE__ . '\\bkx_coupon_codes_activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\bkx_coupon_codes_deactivate' );

// Initialize plugin after plugins loaded.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bkx_coupon_codes_init' );

<?php
/**
 * Plugin Name: BookingX - iCal Feed Export
 * Plugin URI: https://flavflavor.dev/bookingx/addons/ical-feed
 * Description: Export bookings as iCal feeds for calendar subscriptions.
 * Version: 1.0.0
 * Author: FlavFlavor
 * Author URI: https://flavflavor.dev
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-ical-feed
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\ICalFeed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_ICAL_FEED_VERSION', '1.0.0' );
define( 'BKX_ICAL_FEED_FILE', __FILE__ );
define( 'BKX_ICAL_FEED_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_ICAL_FEED_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_ICAL_FEED_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_ICAL_FEED_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_ical_feed_init(): void {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_ical_feed_missing_bookingx_notice' );
		return;
	}

	// Check if SDK is available.
	if ( ! class_exists( 'BookingX\\AddonSDK\\Abstracts\\AbstractAddon' ) ) {
		$sdk_path = dirname( __DIR__ ) . '/_shared/bkx-addon-sdk/src/autoload.php';
		if ( file_exists( $sdk_path ) ) {
			require_once $sdk_path;
		} else {
			add_action( 'admin_notices', 'bkx_ical_feed_missing_sdk_notice' );
			return;
		}
	}

	// Boot the addon.
	\BookingX\ICalFeed\ICalFeedAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_ical_feed_init' );

/**
 * Missing BookingX notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_ical_feed_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX iCal Feed Export requires BookingX to be installed and activated.', 'bkx-ical-feed' ); ?></p>
	</div>
	<?php
}

/**
 * Missing SDK notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_ical_feed_missing_sdk_notice(): void {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX iCal Feed Export requires the BookingX Addon SDK.', 'bkx-ical-feed' ); ?></p>
	</div>
	<?php
}

/**
 * Activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_ical_feed_activate(): void {
	// Generate default feed token.
	if ( ! get_option( 'bkx_ical_feed_token' ) ) {
		update_option( 'bkx_ical_feed_token', wp_generate_password( 32, false ) );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_ical_feed_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_ical_feed_deactivate(): void {
	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_ical_feed_deactivate' );

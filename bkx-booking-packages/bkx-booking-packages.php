<?php
/**
 * Plugin Name: BookingX - Booking Packages
 * Plugin URI: https://bookingx.com/addons/booking-packages
 * Description: Sell prepaid service packages, punch cards, and credit bundles with automatic redemption for BookingX.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * Text Domain: bkx-booking-packages
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BookingX\BookingPackages
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_PACKAGES_VERSION', '1.0.0' );
define( 'BKX_PACKAGES_FILE', __FILE__ );
define( 'BKX_PACKAGES_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_PACKAGES_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_PACKAGES_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements.
define( 'BKX_PACKAGES_MIN_PHP', '7.4' );
define( 'BKX_PACKAGES_MIN_WP', '5.8' );
define( 'BKX_PACKAGES_MIN_BKX', '2.0.0' );

/**
 * Check requirements.
 *
 * @return bool True if requirements are met.
 */
function bkx_packages_check_requirements() {
	$errors = array();

	// Check PHP version.
	if ( version_compare( PHP_VERSION, BKX_PACKAGES_MIN_PHP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required PHP version, 2: Current PHP version */
			__( 'BookingX Booking Packages requires PHP %1$s or higher. You are running PHP %2$s.', 'bkx-booking-packages' ),
			BKX_PACKAGES_MIN_PHP,
			PHP_VERSION
		);
	}

	// Check WordPress version.
	if ( version_compare( get_bloginfo( 'version' ), BKX_PACKAGES_MIN_WP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WordPress version, 2: Current WordPress version */
			__( 'BookingX Booking Packages requires WordPress %1$s or higher. You are running WordPress %2$s.', 'bkx-booking-packages' ),
			BKX_PACKAGES_MIN_WP,
			get_bloginfo( 'version' )
		);
	}

	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		$errors[] = __( 'BookingX Booking Packages requires the BookingX plugin to be installed and activated.', 'bkx-booking-packages' );
	}

	if ( ! empty( $errors ) ) {
		add_action(
			'admin_notices',
			function () use ( $errors ) {
				foreach ( $errors as $error ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
				}
			}
		);
		return false;
	}

	return true;
}

/**
 * Initialize the addon.
 *
 * @return void
 */
function bkx_packages_init() {
	if ( ! bkx_packages_check_requirements() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_PACKAGES_PATH . 'src/autoload.php';

	// Initialize addon.
	$addon = new BookingX\BookingPackages\BookingPackagesAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_packages_init', 20 );

/**
 * Activation hook.
 *
 * @return void
 */
function bkx_packages_activate() {
	if ( ! bkx_packages_check_requirements() ) {
		return;
	}

	require_once BKX_PACKAGES_PATH . 'src/autoload.php';

	// Run migrations.
	$migration = new BookingX\BookingPackages\Migrations\CreatePackageTables();
	$migration->up();

	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_packages_activate' );

/**
 * Deactivation hook.
 *
 * @return void
 */
function bkx_packages_deactivate() {
	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_packages_deactivate' );

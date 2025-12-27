<?php
/**
 * Plugin Name: BookingX - Square Payments
 * Plugin URI: https://bookingx.com/addons/square-payments
 * Description: Accept payments via Square for BookingX bookings with support for credit cards, Apple Pay, Google Pay, and Cash App Pay.
 * Version: 1.0.0
 * Author: Booking X
 * Author URI: https://bookingx.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-square-payments
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0.0
 *
 * @package BookingX\SquarePayments
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'BKX_SQUARE_VERSION', '1.0.0' );
define( 'BKX_SQUARE_FILE', __FILE__ );
define( 'BKX_SQUARE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_SQUARE_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_SQUARE_BASENAME', plugin_basename( __FILE__ ) );

// Require Composer autoloader.
require_once BKX_SQUARE_PATH . 'autoload.php';

/**
 * Initialize the Square Payments add-on.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_square_payments_init() {
	// Initialize the add-on.
	$addon = new BookingX\SquarePayments\SquarePayments( BKX_SQUARE_FILE );

	// Store instance globally for access.
	$GLOBALS['bkx_square_payments'] = $addon;

	// Initialize the add-on.
	$addon->init();
}

// Hook into plugins_loaded with high priority (after BookingX Core loads).
add_action( 'plugins_loaded', 'bkx_square_payments_init', 20 );

/**
 * Activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_square_payments_activate() {
	// Check dependencies before activation.
	if ( ! class_exists( 'Bookingx' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'Square Payments requires BookingX to be installed and activated.', 'bkx-square-payments' ),
			esc_html__( 'Plugin Activation Error', 'bkx-square-payments' ),
			array( 'back_link' => true )
		);
	}

	// Set default options on activation.
	add_option( 'bkx_square_payments_version', BKX_SQUARE_VERSION );

	// Trigger activation hook for add-on to run migrations.
	do_action( 'bkx_square_payments_activated' );
}
register_activation_hook( __FILE__, 'bkx_square_payments_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_square_payments_deactivate() {
	// Trigger deactivation hook.
	do_action( 'bkx_square_payments_deactivated' );

	// Clear scheduled hooks.
	wp_clear_scheduled_hook( 'bkx_square_payments_cleanup' );
}
register_deactivation_hook( __FILE__, 'bkx_square_payments_deactivate' );

/**
 * Get the main plugin instance.
 *
 * @since 1.0.0
 * @return BookingX\SquarePayments\SquarePayments|null
 */
function bkx_square_payments() {
	return $GLOBALS['bkx_square_payments'] ?? null;
}

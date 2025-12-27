<?php
/**
 * Plugin Name: BookingX - Recurring Bookings
 * Plugin URI: https://bookingx.com/addons/recurring-bookings
 * Description: Create recurring booking series with flexible patterns, automatic scheduling, and subscription payments for BookingX.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * Text Domain: bkx-recurring-bookings
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BookingX\RecurringBookings
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_RECURRING_VERSION', '1.0.0' );
define( 'BKX_RECURRING_FILE', __FILE__ );
define( 'BKX_RECURRING_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_RECURRING_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_RECURRING_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements.
define( 'BKX_RECURRING_MIN_PHP', '7.4' );
define( 'BKX_RECURRING_MIN_WP', '5.8' );
define( 'BKX_RECURRING_MIN_BKX', '2.0.0' );

/**
 * Check requirements.
 *
 * @return bool True if requirements are met.
 */
function bkx_recurring_check_requirements() {
	$errors = array();

	// Check PHP version.
	if ( version_compare( PHP_VERSION, BKX_RECURRING_MIN_PHP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required PHP version, 2: Current PHP version */
			__( 'BookingX Recurring Bookings requires PHP %1$s or higher. You are running PHP %2$s.', 'bkx-recurring-bookings' ),
			BKX_RECURRING_MIN_PHP,
			PHP_VERSION
		);
	}

	// Check WordPress version.
	if ( version_compare( get_bloginfo( 'version' ), BKX_RECURRING_MIN_WP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WordPress version, 2: Current WordPress version */
			__( 'BookingX Recurring Bookings requires WordPress %1$s or higher. You are running WordPress %2$s.', 'bkx-recurring-bookings' ),
			BKX_RECURRING_MIN_WP,
			get_bloginfo( 'version' )
		);
	}

	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		$errors[] = __( 'BookingX Recurring Bookings requires the BookingX plugin to be installed and activated.', 'bkx-recurring-bookings' );
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
function bkx_recurring_init() {
	if ( ! bkx_recurring_check_requirements() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_RECURRING_PATH . 'src/autoload.php';

	// Initialize addon.
	$addon = new BookingX\RecurringBookings\RecurringBookingsAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_recurring_init', 20 );

/**
 * Activation hook.
 *
 * @return void
 */
function bkx_recurring_activate() {
	if ( ! bkx_recurring_check_requirements() ) {
		return;
	}

	require_once BKX_RECURRING_PATH . 'src/autoload.php';

	// Run migrations.
	$migration = new BookingX\RecurringBookings\Migrations\CreateRecurringTables();
	$migration->up();

	// Schedule cron events.
	if ( ! wp_next_scheduled( 'bkx_recurring_generate_instances' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_recurring_generate_instances' );
	}

	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_recurring_activate' );

/**
 * Deactivation hook.
 *
 * @return void
 */
function bkx_recurring_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_recurring_generate_instances' );

	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_recurring_deactivate' );

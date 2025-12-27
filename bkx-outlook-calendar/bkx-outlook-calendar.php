<?php
/**
 * Plugin Name: BookingX - Outlook 365 Calendar
 * Plugin URI: https://bookingx.com/addons/outlook-calendar
 * Description: Two-way sync with Microsoft Outlook 365 Calendar. Sync bookings as events, check availability, and prevent double-bookings.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-outlook-calendar
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0.0
 *
 * @package BookingX\OutlookCalendar
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin constants.
 */
define( 'BKX_OUTLOOK_VERSION', '1.0.0' );
define( 'BKX_OUTLOOK_FILE', __FILE__ );
define( 'BKX_OUTLOOK_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_OUTLOOK_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_OUTLOOK_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the autoloader.
 */
require_once BKX_OUTLOOK_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_outlook_calendar_init() {
	// Load text domain for translations.
	load_plugin_textdomain( 'bkx-outlook-calendar', false, dirname( BKX_OUTLOOK_BASENAME ) . '/languages' );

	// Initialize the main addon class.
	$outlook = new \BookingX\OutlookCalendar\OutlookCalendarAddon( BKX_OUTLOOK_FILE );

	// Initialize the addon.
	if ( $outlook->init() ) {
		$GLOBALS['bkx_outlook_calendar'] = $outlook;
	}
}
add_action( 'plugins_loaded', 'bkx_outlook_calendar_init', 10 );

/**
 * Get the main addon instance.
 *
 * @since 1.0.0
 * @return \BookingX\OutlookCalendar\OutlookCalendarAddon|null
 */
function bkx_outlook() {
	return $GLOBALS['bkx_outlook_calendar'] ?? null;
}

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_outlook_calendar_activate() {
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( BKX_OUTLOOK_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Outlook 365 Calendar requires PHP 7.4 or higher.', 'bkx-outlook-calendar' ),
			esc_html__( 'Plugin Activation Error', 'bkx-outlook-calendar' ),
			array( 'back_link' => true )
		);
	}

	if ( ! function_exists( 'BKX' ) ) {
		deactivate_plugins( BKX_OUTLOOK_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Outlook 365 Calendar requires the BookingX plugin to be installed and activated.', 'bkx-outlook-calendar' ),
			esc_html__( 'Plugin Activation Error', 'bkx-outlook-calendar' ),
			array( 'back_link' => true )
		);
	}

	set_transient( 'bkx_outlook_activated', true, 30 );
}
register_activation_hook( BKX_OUTLOOK_FILE, 'bkx_outlook_calendar_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_outlook_calendar_deactivate() {
	wp_clear_scheduled_hook( 'bkx_outlook_sync' );
	delete_transient( 'bkx_outlook_activated' );
}
register_deactivation_hook( BKX_OUTLOOK_FILE, 'bkx_outlook_calendar_deactivate' );

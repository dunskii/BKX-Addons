<?php
/**
 * Plugin Name: BookingX - Multiple Services
 * Plugin URI: https://bookingx.com/addons/multiple-services
 * Description: Allow customers to book multiple services in a single appointment with bundle pricing and combined duration calculation.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-multiple-services
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0.0
 *
 * @package BookingX\MultipleServices
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin constants.
 */
define( 'BKX_MULTIPLE_SERVICES_VERSION', '1.0.0' );
define( 'BKX_MULTIPLE_SERVICES_FILE', __FILE__ );
define( 'BKX_MULTIPLE_SERVICES_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_MULTIPLE_SERVICES_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_MULTIPLE_SERVICES_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the autoloader.
 */
require_once BKX_MULTIPLE_SERVICES_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_multiple_services_init() {
	// Load text domain for translations
	load_plugin_textdomain( 'bkx-multiple-services', false, dirname( BKX_MULTIPLE_SERVICES_BASENAME ) . '/languages' );

	// Initialize the main addon class
	$addon = new \BookingX\MultipleServices\MultipleServicesAddon( BKX_MULTIPLE_SERVICES_FILE );

	// Initialize the addon
	if ( $addon->init() ) {
		// Store in global for access
		$GLOBALS['bkx_multiple_services'] = $addon;
	}
}
add_action( 'plugins_loaded', 'bkx_multiple_services_init', 10 );

/**
 * Get the main addon instance.
 *
 * @since 1.0.0
 * @return \BookingX\MultipleServices\MultipleServicesAddon|null
 */
function bkx_multiple_services() {
	return $GLOBALS['bkx_multiple_services'] ?? null;
}

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_multiple_services_activate() {
	// Check requirements before activation
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( BKX_MULTIPLE_SERVICES_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Multiple Services requires PHP 7.4 or higher.', 'bkx-multiple-services' ),
			esc_html__( 'Plugin Activation Error', 'bkx-multiple-services' ),
			array( 'back_link' => true )
		);
	}

	if ( ! function_exists( 'BKX' ) ) {
		deactivate_plugins( BKX_MULTIPLE_SERVICES_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Multiple Services requires the BookingX plugin to be installed and activated.', 'bkx-multiple-services' ),
			esc_html__( 'Plugin Activation Error', 'bkx-multiple-services' ),
			array( 'back_link' => true )
		);
	}

	// Set activation flag for migrations
	set_transient( 'bkx_multiple_services_activated', true, 30 );
}
register_activation_hook( BKX_MULTIPLE_SERVICES_FILE, 'bkx_multiple_services_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_multiple_services_deactivate() {
	// Clear any scheduled tasks
	wp_clear_scheduled_hook( 'bkx_multiple_services_cleanup' );

	// Clear caches
	delete_transient( 'bkx_multiple_services_activated' );
}
register_deactivation_hook( BKX_MULTIPLE_SERVICES_FILE, 'bkx_multiple_services_deactivate' );

<?php
/**
 * Plugin Name: BookingX - Zapier Integration
 * Plugin URI: https://bookingx.com/addons/zapier-integration
 * Description: Connect BookingX to 3,000+ apps via Zapier. Trigger workflows on booking events and perform actions via REST API.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-zapier-integration
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0.0
 *
 * @package BookingX\ZapierIntegration
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin constants.
 */
define( 'BKX_ZAPIER_VERSION', '1.0.0' );
define( 'BKX_ZAPIER_FILE', __FILE__ );
define( 'BKX_ZAPIER_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_ZAPIER_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_ZAPIER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the autoloader.
 */
require_once BKX_ZAPIER_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_zapier_integration_init() {
	// Load text domain for translations
	load_plugin_textdomain( 'bkx-zapier-integration', false, dirname( BKX_ZAPIER_BASENAME ) . '/languages' );

	// Initialize the main addon class
	$zapier_addon = new \BookingX\ZapierIntegration\ZapierIntegrationAddon( BKX_ZAPIER_FILE );

	// Initialize the addon
	if ( $zapier_addon->init() ) {
		// Store in global for access
		$GLOBALS['bkx_zapier_integration'] = $zapier_addon;
	}
}
add_action( 'plugins_loaded', 'bkx_zapier_integration_init', 10 );

/**
 * Get the main addon instance.
 *
 * @since 1.0.0
 * @return \BookingX\ZapierIntegration\ZapierIntegrationAddon|null
 */
function bkx_zapier() {
	return $GLOBALS['bkx_zapier_integration'] ?? null;
}

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_zapier_integration_activate() {
	// Check requirements before activation
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( BKX_ZAPIER_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Zapier Integration requires PHP 7.4 or higher.', 'bkx-zapier-integration' ),
			esc_html__( 'Plugin Activation Error', 'bkx-zapier-integration' ),
			array( 'back_link' => true )
		);
	}

	if ( ! function_exists( 'BKX' ) ) {
		deactivate_plugins( BKX_ZAPIER_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Zapier Integration requires the BookingX plugin to be installed and activated.', 'bkx-zapier-integration' ),
			esc_html__( 'Plugin Activation Error', 'bkx-zapier-integration' ),
			array( 'back_link' => true )
		);
	}

	// Generate initial API key
	if ( ! get_option( 'bkx_zapier_api_key' ) ) {
		$api_key = wp_generate_password( 32, false );
		update_option( 'bkx_zapier_api_key', $api_key );
	}

	set_transient( 'bkx_zapier_activated', true, 30 );
}
register_activation_hook( BKX_ZAPIER_FILE, 'bkx_zapier_integration_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_zapier_integration_deactivate() {
	// Clear caches
	delete_transient( 'bkx_zapier_activated' );
}
register_deactivation_hook( BKX_ZAPIER_FILE, 'bkx_zapier_integration_deactivate' );

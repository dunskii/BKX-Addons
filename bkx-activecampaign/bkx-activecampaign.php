<?php
/**
 * Plugin Name: BookingX - ActiveCampaign
 * Plugin URI: https://bookingx.com/addons/activecampaign
 * Description: Sync customers with ActiveCampaign, create deals, trigger automations, and use booking data for personalized campaigns.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-activecampaign
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0.0
 *
 * @package BookingX\ActiveCampaign
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin constants.
 */
define( 'BKX_ACTIVECAMPAIGN_VERSION', '1.0.0' );
define( 'BKX_ACTIVECAMPAIGN_FILE', __FILE__ );
define( 'BKX_ACTIVECAMPAIGN_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_ACTIVECAMPAIGN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_ACTIVECAMPAIGN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the autoloader.
 */
require_once BKX_ACTIVECAMPAIGN_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_activecampaign_init() {
	// Load text domain for translations
	load_plugin_textdomain( 'bkx-activecampaign', false, dirname( BKX_ACTIVECAMPAIGN_BASENAME ) . '/languages' );

	// Initialize the main addon class
	$activecampaign = new \BookingX\ActiveCampaign\ActiveCampaignAddon( BKX_ACTIVECAMPAIGN_FILE );

	// Initialize the addon
	if ( $activecampaign->init() ) {
		// Store in global for access
		$GLOBALS['bkx_activecampaign'] = $activecampaign;
	}
}
add_action( 'plugins_loaded', 'bkx_activecampaign_init', 10 );

/**
 * Get the main addon instance.
 *
 * @since 1.0.0
 * @return \BookingX\ActiveCampaign\ActiveCampaignAddon|null
 */
function bkx_activecampaign() {
	return $GLOBALS['bkx_activecampaign'] ?? null;
}

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_activecampaign_activate() {
	// Check requirements before activation
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( BKX_ACTIVECAMPAIGN_BASENAME );
		wp_die(
			esc_html__( 'BookingX - ActiveCampaign requires PHP 7.4 or higher.', 'bkx-activecampaign' ),
			esc_html__( 'Plugin Activation Error', 'bkx-activecampaign' ),
			array( 'back_link' => true )
		);
	}

	if ( ! function_exists( 'BKX' ) ) {
		deactivate_plugins( BKX_ACTIVECAMPAIGN_BASENAME );
		wp_die(
			esc_html__( 'BookingX - ActiveCampaign requires the BookingX plugin to be installed and activated.', 'bkx-activecampaign' ),
			esc_html__( 'Plugin Activation Error', 'bkx-activecampaign' ),
			array( 'back_link' => true )
		);
	}

	set_transient( 'bkx_activecampaign_activated', true, 30 );
}
register_activation_hook( BKX_ACTIVECAMPAIGN_FILE, 'bkx_activecampaign_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_activecampaign_deactivate() {
	// Clear scheduled events
	wp_clear_scheduled_hook( 'bkx_activecampaign_sync' );
	delete_transient( 'bkx_activecampaign_activated' );
}
register_deactivation_hook( BKX_ACTIVECAMPAIGN_FILE, 'bkx_activecampaign_deactivate' );

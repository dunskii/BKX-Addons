<?php
/**
 * Plugin Name: BookingX - Rewards Points
 * Plugin URI: https://bookingx.com/addons/rewards-points
 * Description: Customer loyalty points system - earn points on bookings, redeem for discounts, track point history, and set expiration policies.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-rewards-points
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0.0
 *
 * @package BookingX\RewardsPoints
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin constants.
 */
define( 'BKX_REWARDS_VERSION', '1.0.0' );
define( 'BKX_REWARDS_FILE', __FILE__ );
define( 'BKX_REWARDS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_REWARDS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_REWARDS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the autoloader.
 */
require_once BKX_REWARDS_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_rewards_points_init() {
	// Load text domain for translations
	load_plugin_textdomain( 'bkx-rewards-points', false, dirname( BKX_REWARDS_BASENAME ) . '/languages' );

	// Initialize the main addon class
	$rewards = new \BookingX\RewardsPoints\RewardsPointsAddon( BKX_REWARDS_FILE );

	// Initialize the addon
	if ( $rewards->init() ) {
		// Store in global for access
		$GLOBALS['bkx_rewards_points'] = $rewards;
	}
}
add_action( 'plugins_loaded', 'bkx_rewards_points_init', 10 );

/**
 * Get the main addon instance.
 *
 * @since 1.0.0
 * @return \BookingX\RewardsPoints\RewardsPointsAddon|null
 */
function bkx_rewards() {
	return $GLOBALS['bkx_rewards_points'] ?? null;
}

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_rewards_points_activate() {
	// Check requirements before activation
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( BKX_REWARDS_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Rewards Points requires PHP 7.4 or higher.', 'bkx-rewards-points' ),
			esc_html__( 'Plugin Activation Error', 'bkx-rewards-points' ),
			array( 'back_link' => true )
		);
	}

	if ( ! function_exists( 'BKX' ) ) {
		deactivate_plugins( BKX_REWARDS_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Rewards Points requires the BookingX plugin to be installed and activated.', 'bkx-rewards-points' ),
			esc_html__( 'Plugin Activation Error', 'bkx-rewards-points' ),
			array( 'back_link' => true )
		);
	}

	// Run migrations.
	$addon = new \BookingX\RewardsPoints\RewardsPointsAddon( BKX_REWARDS_FILE );
	$addon->run_migrations();

	set_transient( 'bkx_rewards_activated', true, 30 );
}
register_activation_hook( BKX_REWARDS_FILE, 'bkx_rewards_points_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_rewards_points_deactivate() {
	// Clear scheduled events
	wp_clear_scheduled_hook( 'bkx_rewards_expire_points' );
	delete_transient( 'bkx_rewards_activated' );
}
register_deactivation_hook( BKX_REWARDS_FILE, 'bkx_rewards_points_deactivate' );

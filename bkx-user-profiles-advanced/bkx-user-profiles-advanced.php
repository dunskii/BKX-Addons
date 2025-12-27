<?php
/**
 * Plugin Name: BookingX - User Profiles Advanced
 * Plugin URI: https://bookingx.com/addons/user-profiles-advanced
 * Description: Advanced customer profiles with booking history, favorites, loyalty points, and preferences for BookingX.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * Text Domain: bkx-user-profiles-advanced
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BookingX\UserProfilesAdvanced
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_USER_PROFILES_VERSION', '1.0.0' );
define( 'BKX_USER_PROFILES_FILE', __FILE__ );
define( 'BKX_USER_PROFILES_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_USER_PROFILES_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_USER_PROFILES_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements.
define( 'BKX_USER_PROFILES_MIN_PHP', '7.4' );
define( 'BKX_USER_PROFILES_MIN_WP', '5.8' );
define( 'BKX_USER_PROFILES_MIN_BKX', '2.0.0' );

/**
 * Check requirements.
 *
 * @return bool True if requirements are met.
 */
function bkx_user_profiles_check_requirements() {
	$errors = array();

	// Check PHP version.
	if ( version_compare( PHP_VERSION, BKX_USER_PROFILES_MIN_PHP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required PHP version, 2: Current PHP version */
			__( 'BookingX User Profiles Advanced requires PHP %1$s or higher. You are running PHP %2$s.', 'bkx-user-profiles-advanced' ),
			BKX_USER_PROFILES_MIN_PHP,
			PHP_VERSION
		);
	}

	// Check WordPress version.
	if ( version_compare( get_bloginfo( 'version' ), BKX_USER_PROFILES_MIN_WP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WordPress version, 2: Current WordPress version */
			__( 'BookingX User Profiles Advanced requires WordPress %1$s or higher. You are running WordPress %2$s.', 'bkx-user-profiles-advanced' ),
			BKX_USER_PROFILES_MIN_WP,
			get_bloginfo( 'version' )
		);
	}

	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		$errors[] = __( 'BookingX User Profiles Advanced requires the BookingX plugin to be installed and activated.', 'bkx-user-profiles-advanced' );
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
function bkx_user_profiles_init() {
	if ( ! bkx_user_profiles_check_requirements() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_USER_PROFILES_PATH . 'src/autoload.php';

	// Initialize addon.
	$addon = new BookingX\UserProfilesAdvanced\UserProfilesAdvancedAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_user_profiles_init', 20 );

/**
 * Activation hook.
 *
 * @return void
 */
function bkx_user_profiles_activate() {
	if ( ! bkx_user_profiles_check_requirements() ) {
		return;
	}

	require_once BKX_USER_PROFILES_PATH . 'src/autoload.php';

	// Run migrations.
	$migration = new BookingX\UserProfilesAdvanced\Migrations\CreateProfileTables();
	$migration->up();

	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_user_profiles_activate' );

/**
 * Deactivation hook.
 *
 * @return void
 */
function bkx_user_profiles_deactivate() {
	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_user_profiles_deactivate' );

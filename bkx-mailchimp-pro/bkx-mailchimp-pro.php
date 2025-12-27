<?php
/**
 * Plugin Name: BookingX - Mailchimp Pro
 * Plugin URI: https://bookingx.com/addons/mailchimp-pro
 * Description: Deep Mailchimp integration for booking-based email marketing. Sync customers, trigger automations, and segment by booking activity.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-mailchimp-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0.0
 *
 * @package BookingX\MailchimpPro
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin constants.
 */
define( 'BKX_MAILCHIMP_PRO_VERSION', '1.0.0' );
define( 'BKX_MAILCHIMP_PRO_FILE', __FILE__ );
define( 'BKX_MAILCHIMP_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_MAILCHIMP_PRO_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_MAILCHIMP_PRO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the autoloader.
 */
require_once BKX_MAILCHIMP_PRO_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_mailchimp_pro_init() {
	// Load text domain for translations
	load_plugin_textdomain( 'bkx-mailchimp-pro', false, dirname( BKX_MAILCHIMP_PRO_BASENAME ) . '/languages' );

	// Initialize the main addon class
	$mailchimp_addon = new \BookingX\MailchimpPro\MailchimpProAddon( BKX_MAILCHIMP_PRO_FILE );

	// Initialize the addon
	if ( $mailchimp_addon->init() ) {
		// Store in global for access
		$GLOBALS['bkx_mailchimp_pro'] = $mailchimp_addon;
	}
}
add_action( 'plugins_loaded', 'bkx_mailchimp_pro_init', 10 );

/**
 * Get the main addon instance.
 *
 * @since 1.0.0
 * @return \BookingX\MailchimpPro\MailchimpProAddon|null
 */
function bkx_mailchimp_pro() {
	return $GLOBALS['bkx_mailchimp_pro'] ?? null;
}

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_mailchimp_pro_activate() {
	// Check requirements before activation
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( BKX_MAILCHIMP_PRO_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Mailchimp Pro requires PHP 7.4 or higher.', 'bkx-mailchimp-pro' ),
			esc_html__( 'Plugin Activation Error', 'bkx-mailchimp-pro' ),
			array( 'back_link' => true )
		);
	}

	if ( ! function_exists( 'BKX' ) ) {
		deactivate_plugins( BKX_MAILCHIMP_PRO_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Mailchimp Pro requires the BookingX plugin to be installed and activated.', 'bkx-mailchimp-pro' ),
			esc_html__( 'Plugin Activation Error', 'bkx-mailchimp-pro' ),
			array( 'back_link' => true )
		);
	}

	// Set activation flag for migrations
	set_transient( 'bkx_mailchimp_pro_activated', true, 30 );
}
register_activation_hook( BKX_MAILCHIMP_PRO_FILE, 'bkx_mailchimp_pro_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_mailchimp_pro_deactivate() {
	// Clear any scheduled tasks
	wp_clear_scheduled_hook( 'bkx_mailchimp_pro_sync' );
	wp_clear_scheduled_hook( 'bkx_mailchimp_pro_cleanup' );

	// Clear caches
	delete_transient( 'bkx_mailchimp_pro_activated' );
}
register_deactivation_hook( BKX_MAILCHIMP_PRO_FILE, 'bkx_mailchimp_pro_deactivate' );

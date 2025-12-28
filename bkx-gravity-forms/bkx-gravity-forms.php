<?php
/**
 * Plugin Name: BookingX - Gravity Forms Integration
 * Plugin URI: https://developer.jeancplugins.tech/bkx-gravity-forms
 * Description: Integrate BookingX booking functionality with Gravity Forms for advanced custom booking forms.
 * Version: 1.0.0
 * Author: JEANcp
 * Author URI: https://jeancplugins.tech
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-gravity-forms
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx, gravityforms
 *
 * @package BookingX\GravityForms
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_GRAVITY_FORMS_VERSION', '1.0.0' );
define( 'BKX_GRAVITY_FORMS_FILE', __FILE__ );
define( 'BKX_GRAVITY_FORMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_GRAVITY_FORMS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if dependencies are met.
 *
 * @return bool
 */
function bkx_gravity_forms_check_dependencies() {
	$missing = array();

	// Check for BookingX.
	if ( ! class_exists( 'Bookingx' ) ) {
		$missing[] = 'BookingX';
	}

	// Check for Gravity Forms.
	if ( ! class_exists( 'GFForms' ) ) {
		$missing[] = 'Gravity Forms';
	}

	if ( ! empty( $missing ) ) {
		add_action( 'admin_notices', function() use ( $missing ) {
			$message = sprintf(
				/* translators: %s: List of missing plugins */
				__( 'BookingX Gravity Forms Integration requires the following plugins to be installed and activated: %s', 'bkx-gravity-forms' ),
				implode( ', ', $missing )
			);
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		} );
		return false;
	}

	return true;
}

/**
 * Initialize the plugin.
 */
function bkx_gravity_forms_init() {
	if ( ! bkx_gravity_forms_check_dependencies() ) {
		return;
	}

	// Load text domain.
	load_plugin_textdomain( 'bkx-gravity-forms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Load autoloader.
	require_once BKX_GRAVITY_FORMS_PATH . 'src/autoload.php';

	// Initialize the addon.
	\BookingX\GravityForms\GravityFormsAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_gravity_forms_init', 20 );

/**
 * Plugin activation.
 */
function bkx_gravity_forms_activate() {
	if ( ! bkx_gravity_forms_check_dependencies() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'BookingX Gravity Forms Integration requires BookingX and Gravity Forms to be installed and activated.', 'bkx-gravity-forms' ),
			esc_html__( 'Plugin Activation Error', 'bkx-gravity-forms' ),
			array( 'back_link' => true )
		);
	}

	// Set default options.
	$defaults = array(
		'auto_create_booking'    => true,
		'send_notifications'     => true,
		'allow_service_select'   => true,
		'allow_seat_select'      => true,
		'allow_extras_select'    => true,
		'confirmation_redirect'  => '',
	);
	add_option( 'bkx_gravity_forms_settings', $defaults );
	add_option( 'bkx_gravity_forms_version', BKX_GRAVITY_FORMS_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_gravity_forms_activate' );

/**
 * Plugin deactivation.
 */
function bkx_gravity_forms_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_gravity_forms_deactivate' );

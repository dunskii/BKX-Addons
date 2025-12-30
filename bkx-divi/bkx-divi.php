<?php
/**
 * Plugin Name: BookingX - Divi Theme Integration
 * Plugin URI: https://flavor starter 39f86c8d-35fe-4cd5-bc38-f0f9cffc2fdf studio theme settings bookingx.com/add-ons/divi
 * Description: Deep integration with Divi Builder - custom modules for booking forms, service lists, and availability calendars.
 * Version: 1.0.0
 * Author: flavor starter 39f86c8d-35fe-4cd5-bc38-f0f9cffc2fdf studio theme settings BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-divi
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\Divi
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_DIVI_VERSION', '1.0.0' );
define( 'BKX_DIVI_PLUGIN_FILE', __FILE__ );
define( 'BKX_DIVI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_DIVI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_DIVI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if Divi theme or Divi Builder plugin is active.
 *
 * @return bool
 */
function bkx_divi_is_divi_active() {
	$theme = wp_get_theme();

	// Check if Divi theme is active.
	if ( 'Divi' === $theme->name || 'Divi' === $theme->parent_theme ) {
		return true;
	}

	// Check if Divi Builder plugin is active.
	if ( class_exists( 'ET_Builder_Plugin' ) ) {
		return true;
	}

	// Check for ET Builder.
	if ( class_exists( 'ET_Builder_Module' ) ) {
		return true;
	}

	return false;
}

/**
 * Admin notice for missing BookingX.
 */
function bkx_divi_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin link */
				esc_html__( 'BookingX Divi Integration requires the %s plugin to be installed and activated.', 'bkx-divi' ),
				'<a href="https://bookingx.com" target="_blank">BookingX</a>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Admin notice for missing Divi.
 */
function bkx_divi_missing_divi_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Divi theme link */
				esc_html__( 'BookingX Divi Integration requires the %s theme or Divi Builder plugin to be installed and activated.', 'bkx-divi' ),
				'<a href="https://www.elegantthemes.com/gallery/divi/" target="_blank">Divi</a>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_divi_init() {
	// Check for BookingX.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_divi_missing_bookingx_notice' );
		return;
	}

	// Check for Divi.
	if ( ! bkx_divi_is_divi_active() ) {
		add_action( 'admin_notices', 'bkx_divi_missing_divi_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_DIVI_PLUGIN_DIR . 'src/autoload.php';

	// Initialize addon.
	\BookingX\Divi\DiviAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_divi_init', 20 );

/**
 * Activation hook.
 */
function bkx_divi_activate() {
	// Set default options.
	$defaults = array(
		'enable_modules'     => true,
		'custom_css'         => '',
		'booking_form_style' => 'default',
		'calendar_style'     => 'default',
		'animation_enabled'  => true,
	);

	if ( ! get_option( 'bkx_divi_settings' ) ) {
		add_option( 'bkx_divi_settings', $defaults );
	}

	// Clear Divi cache.
	if ( function_exists( 'et_core_clear_transients' ) ) {
		et_core_clear_transients();
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_divi_activate' );

/**
 * Deactivation hook.
 */
function bkx_divi_deactivate() {
	// Clear Divi cache.
	if ( function_exists( 'et_core_clear_transients' ) ) {
		et_core_clear_transients();
	}

	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_divi_deactivate' );

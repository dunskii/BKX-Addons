<?php
/**
 * Plugin Name: BookingX - Progressive Web App
 * Plugin URI: https://flavor-flavor-flavor.dev/bookingx/addons/pwa
 * Description: Transform your booking site into an installable Progressive Web App with offline capabilities, push notifications, and app-like experience.
 * Version: 1.0.0
 * Author: flavor-flavor-flavor.dev
 * Author URI: https://flavor-flavor-flavor.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-pwa
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\PWA
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_PWA_VERSION', '1.0.0' );
define( 'BKX_PWA_PLUGIN_FILE', __FILE__ );
define( 'BKX_PWA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_PWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_PWA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_PWA_PLUGIN_DIR . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function bkx_pwa_init() {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_pwa_missing_bookingx_notice' );
		return;
	}

	// Initialize the addon.
	\BookingX\PWA\PWAAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_pwa_init', 20 );

/**
 * Missing BookingX notice.
 *
 * @return void
 */
function bkx_pwa_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'BookingX - Progressive Web App', 'bkx-pwa' ); ?></strong>
			<?php esc_html_e( 'requires the BookingX plugin to be installed and activated.', 'bkx-pwa' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Plugin activation.
 *
 * @return void
 */
function bkx_pwa_activate() {
	// Create default settings.
	$default_settings = array(
		'enabled'               => true,
		'app_name'              => get_bloginfo( 'name' ),
		'app_short_name'        => substr( get_bloginfo( 'name' ), 0, 12 ),
		'app_description'       => get_bloginfo( 'description' ),
		'theme_color'           => '#2563eb',
		'background_color'      => '#ffffff',
		'display'               => 'standalone',
		'orientation'           => 'any',
		'start_url'             => '/',
		'offline_page'          => '',
		'cache_strategy'        => 'network-first',
		'cache_expiry'          => 86400,
		'offline_bookings'      => true,
		'push_notifications'    => false,
		'install_prompt'        => true,
		'install_prompt_delay'  => 30,
		'ios_splash_screens'    => true,
		'precache_pages'        => array(),
	);

	if ( ! get_option( 'bkx_pwa_settings' ) ) {
		update_option( 'bkx_pwa_settings', $default_settings );
	}

	// Store version for upgrades.
	update_option( 'bkx_pwa_version', BKX_PWA_VERSION );

	// Flush rewrite rules for manifest and service worker routes.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_pwa_activate' );

/**
 * Plugin deactivation.
 *
 * @return void
 */
function bkx_pwa_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_pwa_deactivate' );

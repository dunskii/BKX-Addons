<?php
/**
 * Plugin Name: BookingX - Beauty & Wellness Suite
 * Plugin URI: https://bookingx.developer.com/addons/beauty-wellness
 * Description: Complete solution for spas, salons, and wellness businesses with treatment menus, client preferences, service add-ons, and beauty-specific features.
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://developer.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-beauty-wellness
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\BeautyWellness
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_BEAUTY_WELLNESS_VERSION', '1.0.0' );
define( 'BKX_BEAUTY_WELLNESS_FILE', __FILE__ );
define( 'BKX_BEAUTY_WELLNESS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_BEAUTY_WELLNESS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_BEAUTY_WELLNESS_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_BEAUTY_WELLNESS_PATH . 'src/autoload.php';

/**
 * Initialize the addon.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_beauty_wellness_init(): void {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_beauty_wellness_missing_bookingx_notice' );
		return;
	}

	// Check if Addon SDK is available.
	if ( ! class_exists( 'BookingX\\AddonSDK\\Abstracts\\AbstractAddon' ) ) {
		add_action( 'admin_notices', 'bkx_beauty_wellness_missing_sdk_notice' );
		return;
	}

	// Initialize the addon.
	\BookingX\BeautyWellness\BeautyWellnessAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_beauty_wellness_init', 20 );

/**
 * Display missing BookingX notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_beauty_wellness_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-beauty-wellness' ),
				'<strong>BookingX - Beauty & Wellness Suite</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Display missing SDK notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_beauty_wellness_missing_sdk_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires the BookingX Addon SDK to be installed.', 'bkx-beauty-wellness' ),
				'<strong>BookingX - Beauty & Wellness Suite</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_beauty_wellness_activate(): void {
	// Run migrations.
	if ( class_exists( 'BookingX\\BeautyWellness\\Migrations\\CreateBeautyTables' ) ) {
		$migration = new \BookingX\BeautyWellness\Migrations\CreateBeautyTables();
		$migration->up();
	}

	// Set default options.
	if ( false === get_option( 'bkx_beauty_wellness_settings' ) ) {
		update_option( 'bkx_beauty_wellness_settings', array(
			'enabled'                  => 1,
			'enable_treatment_menu'    => 1,
			'enable_client_preferences'=> 1,
			'enable_service_addons'    => 1,
			'enable_stylist_portfolio' => 1,
			'enable_consultation_form' => 1,
			'skin_type_tracking'       => 1,
			'allergy_alerts'           => 1,
			'product_recommendations'  => 1,
			'before_after_photos'      => 1,
		) );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_beauty_wellness_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_beauty_wellness_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_beauty_wellness_deactivate' );

<?php
/**
 * Plugin Name: BookingX - Fitness & Sports Management
 * Plugin URI: https://bookingx.developer.com/addons/fitness-sports
 * Description: Complete solution for gyms, personal trainers, sports clubs, and fitness centers with class scheduling, membership management, equipment booking, and performance tracking.
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://developer.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-fitness-sports
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\FitnessSports
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_FITNESS_SPORTS_VERSION', '1.0.0' );
define( 'BKX_FITNESS_SPORTS_FILE', __FILE__ );
define( 'BKX_FITNESS_SPORTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_FITNESS_SPORTS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_FITNESS_SPORTS_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_FITNESS_SPORTS_PATH . 'src/autoload.php';

/**
 * Initialize the addon.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_fitness_sports_init(): void {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_fitness_sports_missing_bookingx_notice' );
		return;
	}

	// Check if Addon SDK is available.
	if ( ! class_exists( 'BookingX\\AddonSDK\\Abstracts\\AbstractAddon' ) ) {
		add_action( 'admin_notices', 'bkx_fitness_sports_missing_sdk_notice' );
		return;
	}

	// Initialize the addon.
	\BookingX\FitnessSports\FitnessSportsAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_fitness_sports_init', 20 );

/**
 * Display missing BookingX notice.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_fitness_sports_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-fitness-sports' ),
				'<strong>BookingX - Fitness & Sports Management</strong>'
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
function bkx_fitness_sports_missing_sdk_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires the BookingX Addon SDK to be installed.', 'bkx-fitness-sports' ),
				'<strong>BookingX - Fitness & Sports Management</strong>'
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
function bkx_fitness_sports_activate(): void {
	// Run migrations.
	if ( class_exists( 'BookingX\\FitnessSports\\Migrations\\CreateFitnessTables' ) ) {
		$migration = new \BookingX\FitnessSports\Migrations\CreateFitnessTables();
		$migration->up();
	}

	// Set default options.
	if ( false === get_option( 'bkx_fitness_sports_settings' ) ) {
		update_option( 'bkx_fitness_sports_settings', array(
			'enabled'                  => 1,
			'enable_class_scheduling'  => 1,
			'enable_membership'        => 1,
			'enable_equipment_booking' => 1,
			'enable_trainer_profiles'  => 1,
			'enable_performance_tracking' => 1,
			'enable_waitlist'          => 1,
			'max_class_size'           => 20,
			'booking_window_days'      => 14,
			'cancellation_hours'       => 4,
		) );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_fitness_sports_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_fitness_sports_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_fitness_sports_deactivate' );

<?php
/**
 * BookingX SMS Notifications Pro
 *
 * @package           BookingX\SmsNotificationsPro
 * @author            Starter Dev
 * @copyright         2024 BookingX
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       BookingX SMS Notifications Pro
 * Plugin URI:        https://bookingx.com/addons/sms-notifications-pro
 * Description:       Advanced SMS notifications with multiple providers (Twilio, Nexmo, MessageBird) for bookings.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            BookingX
 * Author URI:        https://bookingx.com
 * Text Domain:       bkx-sms-notifications-pro
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace BookingX\SmsNotificationsPro;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version.
define( 'BKX_SMS_PRO_VERSION', '1.0.0' );

// Plugin file.
define( 'BKX_SMS_PRO_FILE', __FILE__ );

// Plugin path.
define( 'BKX_SMS_PRO_PATH', plugin_dir_path( __FILE__ ) );

// Plugin URL.
define( 'BKX_SMS_PRO_URL', plugin_dir_url( __FILE__ ) );

// Plugin basename.
define( 'BKX_SMS_PRO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if BookingX is active.
 *
 * @return bool
 */
function bkx_sms_pro_check_requirements(): bool {
	return defined( 'STARTER_DEVELOPER_VERSION' ) || defined( 'BKX_VERSION' );
}

/**
 * Display admin notice if BookingX is not active.
 *
 * @return void
 */
function bkx_sms_pro_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-sms-notifications-pro' ),
				'<strong>BookingX SMS Notifications Pro</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function bkx_sms_pro_init(): void {
	// Check requirements.
	if ( ! bkx_sms_pro_check_requirements() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\bkx_sms_pro_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_SMS_PRO_PATH . 'src/autoload.php';

	// Initialize addon.
	$addon = SmsNotificationsProAddon::get_instance();
	$addon->init();
}

/**
 * Plugin activation hook.
 *
 * @return void
 */
function bkx_sms_pro_activate(): void {
	if ( ! bkx_sms_pro_check_requirements() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'BookingX SMS Notifications Pro requires BookingX to be installed and activated.', 'bkx-sms-notifications-pro' ),
			'Plugin dependency check',
			array( 'back_link' => true )
		);
	}

	// Run migrations.
	require_once BKX_SMS_PRO_PATH . 'src/autoload.php';
	$addon = SmsNotificationsProAddon::get_instance();
	$addon->run_migrations();

	// Set default options.
	$addon->set_default_settings();

	// Flush rewrite rules.
	flush_rewrite_rules();
}

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function bkx_sms_pro_deactivate(): void {
	// Flush rewrite rules.
	flush_rewrite_rules();
}

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, __NAMESPACE__ . '\\bkx_sms_pro_activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\bkx_sms_pro_deactivate' );

// Initialize plugin after plugins loaded.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bkx_sms_pro_init' );

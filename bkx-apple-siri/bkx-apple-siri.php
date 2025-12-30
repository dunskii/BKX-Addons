<?php
/**
 * Plugin Name: BookingX Apple Siri Integration
 * Plugin URI: https://flavflavor.dev/bookingx/addons/apple-siri
 * Description: Enable voice booking through Apple Siri using SiriKit Intents and Shortcuts integration.
 * Version: 1.0.0
 * Author: flavflavor.dev
 * Author URI: https://flavflavor.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-apple-siri
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\AppleSiri
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_APPLE_SIRI_VERSION', '1.0.0' );
define( 'BKX_APPLE_SIRI_PLUGIN_FILE', __FILE__ );
define( 'BKX_APPLE_SIRI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_APPLE_SIRI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_APPLE_SIRI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements.
define( 'BKX_APPLE_SIRI_MIN_PHP_VERSION', '7.4' );
define( 'BKX_APPLE_SIRI_MIN_WP_VERSION', '5.8' );
define( 'BKX_APPLE_SIRI_MIN_BKX_VERSION', '2.0.0' );

/**
 * Check if requirements are met before loading the plugin.
 *
 * @return bool
 */
function bkx_apple_siri_requirements_met() {
	if ( version_compare( PHP_VERSION, BKX_APPLE_SIRI_MIN_PHP_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'bkx_apple_siri_php_notice' );
		return false;
	}

	if ( version_compare( get_bloginfo( 'version' ), BKX_APPLE_SIRI_MIN_WP_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'bkx_apple_siri_wp_notice' );
		return false;
	}

	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_apple_siri_bookingx_notice' );
		return false;
	}

	return true;
}

/**
 * PHP version notice.
 */
function bkx_apple_siri_php_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Required PHP version, 2: Current PHP version */
				esc_html__( 'BookingX Apple Siri Integration requires PHP %1$s or higher. You are running PHP %2$s.', 'bkx-apple-siri' ),
				esc_html( BKX_APPLE_SIRI_MIN_PHP_VERSION ),
				esc_html( PHP_VERSION )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * WordPress version notice.
 */
function bkx_apple_siri_wp_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Required WP version */
				esc_html__( 'BookingX Apple Siri Integration requires WordPress %1$s or higher.', 'bkx-apple-siri' ),
				esc_html( BKX_APPLE_SIRI_MIN_WP_VERSION )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * BookingX dependency notice.
 */
function bkx_apple_siri_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Required BookingX version */
				esc_html__( 'BookingX Apple Siri Integration requires BookingX %1$s or higher to be installed and active.', 'bkx-apple-siri' ),
				esc_html( BKX_APPLE_SIRI_MIN_BKX_VERSION )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_apple_siri_init() {
	if ( ! bkx_apple_siri_requirements_met() ) {
		return;
	}

	require_once BKX_APPLE_SIRI_PLUGIN_DIR . 'src/autoload.php';

	\BookingX\AppleSiri\AppleSiriAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_apple_siri_init', 15 );

/**
 * Activation hook.
 */
function bkx_apple_siri_activate() {
	if ( false === get_option( 'bkx_apple_siri_settings' ) ) {
		$defaults = array(
			'enabled'                    => false,
			'app_id'                     => '',
			'team_id'                    => '',
			'key_id'                     => '',
			'private_key'                => '',
			'bundle_identifier'          => '',
			'intent_types'               => array( 'book', 'reschedule', 'cancel', 'check_availability' ),
			'default_service_id'         => 0,
			'require_confirmation'       => true,
			'send_booking_to_reminders'  => true,
			'shortcuts_enabled'          => true,
			'voice_phrases'              => array(
				'book'              => 'Book an appointment with {business_name}',
				'reschedule'        => 'Reschedule my {service_name} appointment',
				'cancel'            => 'Cancel my appointment at {business_name}',
				'check_availability' => 'Check availability at {business_name}',
			),
			'webhook_secret'             => wp_generate_password( 32, false ),
			'log_requests'               => false,
		);
		add_option( 'bkx_apple_siri_settings', $defaults );
	}

	update_option( 'bkx_apple_siri_version', BKX_APPLE_SIRI_VERSION );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_apple_siri_activate' );

/**
 * Deactivation hook.
 */
function bkx_apple_siri_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_apple_siri_deactivate' );

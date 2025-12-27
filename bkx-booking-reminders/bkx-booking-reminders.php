<?php
/**
 * Plugin Name: BookingX - Booking Reminders
 * Plugin URI: https://developer.jetonyx.com/booking-reminders
 * Description: Automated email and SMS reminders to reduce no-shows and improve customer experience.
 * Version: 1.0.0
 * Author: JetOnyx
 * Author URI: https://jetonyx.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-booking-reminders
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\BookingReminders
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_BOOKING_REMINDERS_VERSION', '1.0.0' );
define( 'BKX_BOOKING_REMINDERS_FILE', __FILE__ );
define( 'BKX_BOOKING_REMINDERS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_BOOKING_REMINDERS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_BOOKING_REMINDERS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if BookingX core plugin is active.
 *
 * @since 1.0.0
 * @return bool
 */
function bkx_booking_reminders_check_core() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_booking_reminders_core_missing_notice' );
		return false;
	}
	return true;
}

/**
 * Admin notice for missing core plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_booking_reminders_core_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires the BookingX plugin to be installed and activated.', 'bkx-booking-reminders' ),
				'<strong>BookingX Booking Reminders</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Check if Add-on SDK is available.
 *
 * @since 1.0.0
 * @return bool
 */
function bkx_booking_reminders_check_sdk() {
	$sdk_path = dirname( __DIR__ ) . '/_shared/bkx-addon-sdk/bkx-addon-sdk.php';
	if ( ! file_exists( $sdk_path ) ) {
		add_action( 'admin_notices', 'bkx_booking_reminders_sdk_missing_notice' );
		return false;
	}
	require_once $sdk_path;
	return true;
}

/**
 * Admin notice for missing SDK.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_booking_reminders_sdk_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: SDK name */
				esc_html__( '%s requires the BookingX Add-on SDK.', 'bkx-booking-reminders' ),
				'<strong>BookingX Booking Reminders</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_booking_reminders_init() {
	if ( ! bkx_booking_reminders_check_core() ) {
		return;
	}

	if ( ! bkx_booking_reminders_check_sdk() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_BOOKING_REMINDERS_PATH . 'src/autoload.php';

	// Initialize the addon.
	\BookingX\BookingReminders\BookingRemindersAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_booking_reminders_init', 20 );

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_booking_reminders_activate() {
	// Set flag for database migrations.
	update_option( 'bkx_booking_reminders_activated', true );

	// Schedule the reminder cron job.
	if ( ! wp_next_scheduled( 'bkx_booking_reminders_process' ) ) {
		wp_schedule_event( time(), 'every_fifteen_minutes', 'bkx_booking_reminders_process' );
	}
}
register_activation_hook( __FILE__, 'bkx_booking_reminders_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_booking_reminders_deactivate() {
	// Clear scheduled cron job.
	$timestamp = wp_next_scheduled( 'bkx_booking_reminders_process' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'bkx_booking_reminders_process' );
	}
}
register_deactivation_hook( __FILE__, 'bkx_booking_reminders_deactivate' );

/**
 * Add custom cron schedule for 15-minute intervals.
 *
 * @since 1.0.0
 * @param array $schedules Existing schedules.
 * @return array Modified schedules.
 */
function bkx_booking_reminders_cron_schedules( array $schedules ): array {
	$schedules['every_fifteen_minutes'] = array(
		'interval' => 15 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every 15 Minutes', 'bkx-booking-reminders' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'bkx_booking_reminders_cron_schedules' );

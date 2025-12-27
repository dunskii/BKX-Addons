<?php
/**
 * BookingX Google Calendar Integration
 *
 * @package           BookingX\GoogleCalendar
 * @author            Starter Dev
 * @copyright         2024 BookingX
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       BookingX Google Calendar Integration
 * Plugin URI:        https://bookingx.com/addons/google-calendar
 * Description:       Two-way sync between BookingX bookings and Google Calendar for staff and customers.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            BookingX
 * Author URI:        https://bookingx.com
 * Text Domain:       bkx-google-calendar
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace BookingX\GoogleCalendar;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version.
define( 'BKX_GOOGLE_CALENDAR_VERSION', '1.0.0' );

// Plugin file.
define( 'BKX_GOOGLE_CALENDAR_FILE', __FILE__ );

// Plugin path.
define( 'BKX_GOOGLE_CALENDAR_PATH', plugin_dir_path( __FILE__ ) );

// Plugin URL.
define( 'BKX_GOOGLE_CALENDAR_URL', plugin_dir_url( __FILE__ ) );

// Plugin basename.
define( 'BKX_GOOGLE_CALENDAR_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if BookingX is active.
 *
 * @return bool
 */
function bkx_google_calendar_check_requirements(): bool {
	return defined( 'STARTER_DEVELOPER_VERSION' ) || defined( 'BKX_VERSION' );
}

/**
 * Display admin notice if BookingX is not active.
 *
 * @return void
 */
function bkx_google_calendar_missing_bookingx_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-google-calendar' ),
				'<strong>BookingX Google Calendar Integration</strong>'
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
function bkx_google_calendar_init(): void {
	// Check requirements.
	if ( ! bkx_google_calendar_check_requirements() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\bkx_google_calendar_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_GOOGLE_CALENDAR_PATH . 'src/autoload.php';

	// Initialize addon.
	$addon = GoogleCalendarAddon::get_instance();
	$addon->init();
}

/**
 * Plugin activation hook.
 *
 * @return void
 */
function bkx_google_calendar_activate(): void {
	if ( ! bkx_google_calendar_check_requirements() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'BookingX Google Calendar Integration requires BookingX to be installed and activated.', 'bkx-google-calendar' ),
			'Plugin dependency check',
			array( 'back_link' => true )
		);
	}

	// Run migrations.
	require_once BKX_GOOGLE_CALENDAR_PATH . 'src/autoload.php';
	$addon = GoogleCalendarAddon::get_instance();
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
function bkx_google_calendar_deactivate(): void {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_google_calendar_sync' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, __NAMESPACE__ . '\\bkx_google_calendar_activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\bkx_google_calendar_deactivate' );

// Initialize plugin after plugins loaded.
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bkx_google_calendar_init' );

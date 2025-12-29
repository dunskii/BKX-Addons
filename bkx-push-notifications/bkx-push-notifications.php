<?php
/**
 * Plugin Name: BookingX - Push Notifications
 * Plugin URI: https://flavordeveloper.com/bookingx/addons/push-notifications
 * Description: Send browser push notifications to customers and staff for booking updates, reminders, and confirmations.
 * Version: 1.0.0
 * Author: Flavor Developer
 * Author URI: https://flavordeveloper.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-push-notifications
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0
 *
 * @package BookingX\PushNotifications
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'BKX_PUSH_VERSION', '1.0.0' );
define( 'BKX_PUSH_FILE', __FILE__ );
define( 'BKX_PUSH_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_PUSH_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_PUSH_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check dependencies.
 *
 * @return bool
 */
function bkx_push_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_push_missing_bookingx_notice' );
		return false;
	}
	return true;
}

/**
 * Missing BookingX notice.
 */
function bkx_push_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires BookingX plugin to be installed and activated.', 'bkx-push-notifications' ),
				'<strong>BookingX - Push Notifications</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize plugin.
 */
function bkx_push_init() {
	if ( ! bkx_push_check_dependencies() ) {
		return;
	}

	require_once BKX_PUSH_PATH . 'src/autoload.php';

	$addon = new BookingX\PushNotifications\PushNotificationsAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_push_init', 20 );

/**
 * Activation hook.
 */
function bkx_push_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Push subscriptions table.
	$sql_subscriptions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_push_subscriptions (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned DEFAULT NULL,
		endpoint varchar(500) NOT NULL,
		p256dh varchar(255) NOT NULL,
		auth varchar(255) NOT NULL,
		user_agent varchar(500) DEFAULT NULL,
		device_type varchar(50) DEFAULT 'desktop',
		is_active tinyint(1) NOT NULL DEFAULT 1,
		last_used datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY endpoint (endpoint(191)),
		KEY user_id (user_id),
		KEY is_active (is_active)
	) $charset_collate;";

	// Push notifications log.
	$sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_push_logs (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		subscription_id bigint(20) unsigned DEFAULT NULL,
		booking_id bigint(20) unsigned DEFAULT NULL,
		notification_type varchar(50) NOT NULL,
		title varchar(255) NOT NULL,
		body text NOT NULL,
		data longtext DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		error_message text DEFAULT NULL,
		sent_at datetime DEFAULT NULL,
		delivered_at datetime DEFAULT NULL,
		clicked_at datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY subscription_id (subscription_id),
		KEY booking_id (booking_id),
		KEY notification_type (notification_type),
		KEY status (status),
		KEY sent_at (sent_at)
	) $charset_collate;";

	// Notification templates.
	$sql_templates = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_push_templates (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		slug varchar(100) NOT NULL,
		trigger_event varchar(100) NOT NULL,
		title varchar(255) NOT NULL,
		body text NOT NULL,
		icon varchar(500) DEFAULT NULL,
		badge varchar(500) DEFAULT NULL,
		url varchar(500) DEFAULT NULL,
		data longtext DEFAULT NULL,
		target_audience varchar(50) NOT NULL DEFAULT 'customer',
		status varchar(20) NOT NULL DEFAULT 'active',
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug),
		KEY trigger_event (trigger_event),
		KEY status (status)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_subscriptions );
	dbDelta( $sql_logs );
	dbDelta( $sql_templates );

	// Generate VAPID keys if not exist.
	bkx_push_generate_vapid_keys();

	// Create default templates.
	bkx_push_create_default_templates();

	// Set version.
	update_option( 'bkx_push_db_version', BKX_PUSH_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_push_activate' );

/**
 * Generate VAPID keys.
 */
function bkx_push_generate_vapid_keys() {
	$settings = get_option( 'bkx_push_settings', array() );

	if ( ! empty( $settings['vapid_public_key'] ) && ! empty( $settings['vapid_private_key'] ) ) {
		return;
	}

	// Generate keys using openssl.
	if ( function_exists( 'openssl_pkey_new' ) ) {
		$config = array(
			'curve_name'       => 'prime256v1',
			'private_key_type' => OPENSSL_KEYTYPE_EC,
		);

		$key = openssl_pkey_new( $config );

		if ( $key ) {
			$details = openssl_pkey_get_details( $key );

			if ( isset( $details['ec']['x'] ) && isset( $details['ec']['y'] ) && isset( $details['ec']['d'] ) ) {
				// Encode public key (uncompressed point format: 0x04 + x + y).
				$public_key = "\x04" . $details['ec']['x'] . $details['ec']['y'];
				$public_key = rtrim( strtr( base64_encode( $public_key ), '+/', '-_' ), '=' );

				// Encode private key.
				$private_key = rtrim( strtr( base64_encode( $details['ec']['d'] ), '+/', '-_' ), '=' );

				$settings['vapid_public_key']  = $public_key;
				$settings['vapid_private_key'] = $private_key;

				update_option( 'bkx_push_settings', $settings );
			}
		}
	}
}

/**
 * Create default notification templates.
 */
function bkx_push_create_default_templates() {
	global $wpdb;

	$table = $wpdb->prefix . 'bkx_push_templates';

	// Check if defaults exist.
	$exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
	if ( $exists > 0 ) {
		return;
	}

	$defaults = array(
		array(
			'name'            => 'Booking Confirmed',
			'slug'            => 'booking-confirmed',
			'trigger_event'   => 'bkx_booking_confirmed',
			'title'           => 'Booking Confirmed!',
			'body'            => 'Your booking on {{booking_date}} at {{booking_time}} has been confirmed.',
			'target_audience' => 'customer',
			'status'          => 'active',
		),
		array(
			'name'            => 'Booking Reminder',
			'slug'            => 'booking-reminder',
			'trigger_event'   => 'bkx_booking_reminder',
			'title'           => 'Upcoming Appointment',
			'body'            => 'Reminder: You have an appointment tomorrow at {{booking_time}}.',
			'target_audience' => 'customer',
			'status'          => 'active',
		),
		array(
			'name'            => 'Booking Cancelled',
			'slug'            => 'booking-cancelled',
			'trigger_event'   => 'bkx_booking_cancelled',
			'title'           => 'Booking Cancelled',
			'body'            => 'Your booking #{{booking_id}} has been cancelled.',
			'target_audience' => 'customer',
			'status'          => 'active',
		),
		array(
			'name'            => 'New Booking (Staff)',
			'slug'            => 'staff-new-booking',
			'trigger_event'   => 'bkx_booking_created',
			'title'           => 'New Booking Received',
			'body'            => 'New booking from {{customer_name}} on {{booking_date}} at {{booking_time}}.',
			'target_audience' => 'staff',
			'status'          => 'active',
		),
		array(
			'name'            => 'Booking Rescheduled',
			'slug'            => 'booking-rescheduled',
			'trigger_event'   => 'bkx_booking_rescheduled',
			'title'           => 'Booking Rescheduled',
			'body'            => 'Your booking has been rescheduled to {{booking_date}} at {{booking_time}}.',
			'target_audience' => 'customer',
			'status'          => 'active',
		),
	);

	foreach ( $defaults as $template ) {
		$template['created_at'] = current_time( 'mysql' );
		$template['updated_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $template ); // phpcs:ignore
	}
}

/**
 * Deactivation hook.
 */
function bkx_push_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_push_deactivate' );

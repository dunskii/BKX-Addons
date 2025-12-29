<?php
/**
 * Plugin Name: BookingX - WhatsApp Business Integration
 * Plugin URI: https://bookingx.com/add-ons/whatsapp-business
 * Description: Send booking notifications via WhatsApp Business API with automated reminders, two-way messaging, and template support.
 * Version: 1.0.0
 * Author: Starter Dev Studio
 * Author URI: https://bookingx.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-whatsapp-business
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\WhatsAppBusiness
 */

defined( 'ABSPATH' ) || exit;

// Define constants.
define( 'BKX_WHATSAPP_VERSION', '1.0.0' );
define( 'BKX_WHATSAPP_PLUGIN_FILE', __FILE__ );
define( 'BKX_WHATSAPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_WHATSAPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_WHATSAPP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function bkx_whatsapp_business_init() {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_whatsapp_business_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_WHATSAPP_PLUGIN_DIR . 'src/autoload.php';

	// Initialize addon.
	$addon = new BookingX\WhatsAppBusiness\WhatsAppBusinessAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_whatsapp_business_init', 20 );

/**
 * Admin notice for missing BookingX.
 *
 * @since 1.0.0
 */
function bkx_whatsapp_business_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-whatsapp-business' ),
				'<strong>BookingX - WhatsApp Business Integration</strong>'
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
 */
function bkx_whatsapp_business_activate() {
	// Create database tables.
	bkx_whatsapp_business_create_tables();

	// Set default options.
	$defaults = array(
		'enabled'               => false,
		'api_provider'          => 'cloud_api', // cloud_api, twilio, 360dialog.
		'phone_number_id'       => '',
		'business_account_id'   => '',
		'access_token'          => '',
		'webhook_verify_token'  => wp_generate_password( 32, false ),
		'send_booking_confirmation' => true,
		'send_booking_reminder' => true,
		'reminder_hours'        => 24,
		'send_booking_cancelled' => true,
		'send_booking_rescheduled' => true,
		'enable_two_way_chat'   => true,
		'auto_reply_enabled'    => true,
		'auto_reply_message'    => __( 'Thank you for your message. We will get back to you shortly.', 'bkx-whatsapp-business' ),
		'business_hours_start'  => '09:00',
		'business_hours_end'    => '18:00',
		'outside_hours_message' => __( 'We are currently outside business hours. We will respond when we reopen.', 'bkx-whatsapp-business' ),
	);

	add_option( 'bkx_whatsapp_business_settings', $defaults );
	add_option( 'bkx_whatsapp_business_version', BKX_WHATSAPP_VERSION );

	// Schedule cron jobs.
	if ( ! wp_next_scheduled( 'bkx_whatsapp_send_reminders' ) ) {
		wp_schedule_event( time(), 'hourly', 'bkx_whatsapp_send_reminders' );
	}

	if ( ! wp_next_scheduled( 'bkx_whatsapp_cleanup_old_messages' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_whatsapp_cleanup_old_messages' );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_whatsapp_business_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 */
function bkx_whatsapp_business_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_whatsapp_send_reminders' );
	wp_clear_scheduled_hook( 'bkx_whatsapp_cleanup_old_messages' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_whatsapp_business_deactivate' );

/**
 * Create database tables.
 *
 * @since 1.0.0
 */
function bkx_whatsapp_business_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Messages table.
	$table_messages = $wpdb->prefix . 'bkx_whatsapp_messages';
	$sql_messages = "CREATE TABLE {$table_messages} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		message_id varchar(100) NOT NULL,
		booking_id bigint(20) UNSIGNED DEFAULT NULL,
		phone_number varchar(20) NOT NULL,
		direction enum('inbound','outbound') NOT NULL DEFAULT 'outbound',
		message_type varchar(20) NOT NULL DEFAULT 'text',
		content longtext NOT NULL,
		template_name varchar(100) DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		error_message text DEFAULT NULL,
		sent_at datetime DEFAULT NULL,
		delivered_at datetime DEFAULT NULL,
		read_at datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY message_id (message_id),
		KEY booking_id (booking_id),
		KEY phone_number (phone_number),
		KEY status (status),
		KEY created_at (created_at)
	) {$charset_collate};";

	// Conversations table.
	$table_conversations = $wpdb->prefix . 'bkx_whatsapp_conversations';
	$sql_conversations = "CREATE TABLE {$table_conversations} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		phone_number varchar(20) NOT NULL,
		customer_name varchar(200) DEFAULT NULL,
		customer_id bigint(20) UNSIGNED DEFAULT NULL,
		last_message_id bigint(20) UNSIGNED DEFAULT NULL,
		last_message_at datetime DEFAULT NULL,
		unread_count int(11) NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'active',
		assigned_to bigint(20) UNSIGNED DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY phone_number (phone_number),
		KEY customer_id (customer_id),
		KEY status (status),
		KEY assigned_to (assigned_to)
	) {$charset_collate};";

	// Templates table.
	$table_templates = $wpdb->prefix . 'bkx_whatsapp_templates';
	$sql_templates = "CREATE TABLE {$table_templates} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		template_id varchar(100) DEFAULT NULL,
		name varchar(100) NOT NULL,
		category varchar(50) NOT NULL DEFAULT 'UTILITY',
		language varchar(10) NOT NULL DEFAULT 'en',
		status varchar(20) NOT NULL DEFAULT 'pending',
		header_type varchar(20) DEFAULT NULL,
		header_content text DEFAULT NULL,
		body_content text NOT NULL,
		footer_content varchar(200) DEFAULT NULL,
		buttons longtext DEFAULT NULL,
		variables longtext DEFAULT NULL,
		rejection_reason text DEFAULT NULL,
		synced_at datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY name (name),
		KEY template_id (template_id),
		KEY status (status),
		KEY category (category)
	) {$charset_collate};";

	// Quick replies table.
	$table_quick_replies = $wpdb->prefix . 'bkx_whatsapp_quick_replies';
	$sql_quick_replies = "CREATE TABLE {$table_quick_replies} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		shortcut varchar(50) NOT NULL,
		title varchar(200) NOT NULL,
		content text NOT NULL,
		category varchar(50) DEFAULT NULL,
		use_count int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY shortcut (shortcut),
		KEY category (category)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_messages );
	dbDelta( $sql_conversations );
	dbDelta( $sql_templates );
	dbDelta( $sql_quick_replies );

	// Store DB version.
	update_option( 'bkx_whatsapp_business_db_version', '1.0.0' );
}

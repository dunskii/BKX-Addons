<?php
/**
 * Plugin Name: BookingX - Live Chat Integration
 * Plugin URI: https://bookingx.com/add-ons/live-chat
 * Description: Real-time live chat for booking inquiries with operator dashboard, canned responses, file sharing, and chat routing.
 * Version: 1.0.0
 * Author: Starter Dev Studio
 * Author URI: https://bookingx.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-live-chat
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\LiveChat
 */

defined( 'ABSPATH' ) || exit;

// Define constants.
define( 'BKX_LIVECHAT_VERSION', '1.0.0' );
define( 'BKX_LIVECHAT_PLUGIN_FILE', __FILE__ );
define( 'BKX_LIVECHAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_LIVECHAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_LIVECHAT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function bkx_live_chat_init() {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_live_chat_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_LIVECHAT_PLUGIN_DIR . 'src/autoload.php';

	// Initialize addon.
	$addon = new BookingX\LiveChat\LiveChatAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_live_chat_init', 20 );

/**
 * Admin notice for missing BookingX.
 *
 * @since 1.0.0
 */
function bkx_live_chat_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-live-chat' ),
				'<strong>BookingX - Live Chat Integration</strong>'
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
function bkx_live_chat_activate() {
	// Create database tables.
	bkx_live_chat_create_tables();

	// Set default options.
	$defaults = array(
		'enabled'               => true,
		'widget_position'       => 'bottom-right',
		'widget_color'          => '#2196f3',
		'widget_title'          => __( 'Chat with us', 'bkx-live-chat' ),
		'welcome_message'       => __( 'Hello! How can we help you today?', 'bkx-live-chat' ),
		'offline_message'       => __( 'We are currently offline. Please leave a message.', 'bkx-live-chat' ),
		'require_email'         => true,
		'require_name'          => true,
		'show_on_pages'         => array(),
		'hide_on_pages'         => array(),
		'business_hours_enabled' => false,
		'business_hours'        => array(),
		'auto_assign_enabled'   => true,
		'typing_indicator'      => true,
		'sound_notifications'   => true,
		'email_transcripts'     => true,
		'file_sharing'          => true,
		'max_file_size'         => 5, // MB.
		'allowed_file_types'    => 'jpg,jpeg,png,gif,pdf,doc,docx',
		'idle_timeout'          => 30, // minutes.
		'satisfaction_survey'   => true,
	);

	add_option( 'bkx_live_chat_settings', $defaults );
	add_option( 'bkx_live_chat_version', BKX_LIVECHAT_VERSION );

	// Create default canned responses.
	bkx_live_chat_create_default_responses();

	// Schedule cron jobs.
	if ( ! wp_next_scheduled( 'bkx_livechat_cleanup_sessions' ) ) {
		wp_schedule_event( time(), 'hourly', 'bkx_livechat_cleanup_sessions' );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_live_chat_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 */
function bkx_live_chat_deactivate() {
	wp_clear_scheduled_hook( 'bkx_livechat_cleanup_sessions' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_live_chat_deactivate' );

/**
 * Create database tables.
 *
 * @since 1.0.0
 */
function bkx_live_chat_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Chats table.
	$table_chats = $wpdb->prefix . 'bkx_livechat_chats';
	$sql_chats = "CREATE TABLE {$table_chats} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id varchar(64) NOT NULL,
		visitor_name varchar(200) DEFAULT NULL,
		visitor_email varchar(200) DEFAULT NULL,
		visitor_ip varchar(45) DEFAULT NULL,
		visitor_user_agent text DEFAULT NULL,
		page_url text DEFAULT NULL,
		booking_id bigint(20) UNSIGNED DEFAULT NULL,
		operator_id bigint(20) UNSIGNED DEFAULT NULL,
		department varchar(100) DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		rating tinyint(1) DEFAULT NULL,
		feedback text DEFAULT NULL,
		started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		ended_at datetime DEFAULT NULL,
		last_message_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY session_id (session_id),
		KEY operator_id (operator_id),
		KEY status (status),
		KEY started_at (started_at)
	) {$charset_collate};";

	// Messages table.
	$table_messages = $wpdb->prefix . 'bkx_livechat_messages';
	$sql_messages = "CREATE TABLE {$table_messages} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		chat_id bigint(20) UNSIGNED NOT NULL,
		sender_type enum('visitor','operator','system') NOT NULL DEFAULT 'visitor',
		sender_id bigint(20) UNSIGNED DEFAULT NULL,
		sender_name varchar(200) DEFAULT NULL,
		message_type varchar(20) NOT NULL DEFAULT 'text',
		content longtext NOT NULL,
		attachment_url text DEFAULT NULL,
		attachment_name varchar(255) DEFAULT NULL,
		is_read tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY chat_id (chat_id),
		KEY created_at (created_at)
	) {$charset_collate};";

	// Canned responses table.
	$table_responses = $wpdb->prefix . 'bkx_livechat_responses';
	$sql_responses = "CREATE TABLE {$table_responses} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		shortcut varchar(50) NOT NULL,
		title varchar(200) NOT NULL,
		content text NOT NULL,
		category varchar(100) DEFAULT NULL,
		operator_id bigint(20) UNSIGNED DEFAULT NULL,
		is_global tinyint(1) NOT NULL DEFAULT 1,
		use_count int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY shortcut (shortcut),
		KEY category (category),
		KEY operator_id (operator_id)
	) {$charset_collate};";

	// Operators table.
	$table_operators = $wpdb->prefix . 'bkx_livechat_operators';
	$sql_operators = "CREATE TABLE {$table_operators} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id bigint(20) UNSIGNED NOT NULL,
		display_name varchar(200) DEFAULT NULL,
		avatar_url text DEFAULT NULL,
		departments longtext DEFAULT NULL,
		max_chats int(11) NOT NULL DEFAULT 5,
		status varchar(20) NOT NULL DEFAULT 'offline',
		status_changed_at datetime DEFAULT NULL,
		active_chats int(11) NOT NULL DEFAULT 0,
		total_chats int(11) NOT NULL DEFAULT 0,
		avg_rating decimal(3,2) DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY user_id (user_id),
		KEY status (status)
	) {$charset_collate};";

	// Visitor tracking table.
	$table_visitors = $wpdb->prefix . 'bkx_livechat_visitors';
	$sql_visitors = "CREATE TABLE {$table_visitors} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id varchar(64) NOT NULL,
		visitor_name varchar(200) DEFAULT NULL,
		visitor_email varchar(200) DEFAULT NULL,
		visitor_ip varchar(45) DEFAULT NULL,
		user_agent text DEFAULT NULL,
		current_page text DEFAULT NULL,
		referrer text DEFAULT NULL,
		country varchar(100) DEFAULT NULL,
		city varchar(100) DEFAULT NULL,
		is_returning tinyint(1) NOT NULL DEFAULT 0,
		visits int(11) NOT NULL DEFAULT 1,
		last_seen datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY session_id (session_id),
		KEY last_seen (last_seen)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_chats );
	dbDelta( $sql_messages );
	dbDelta( $sql_responses );
	dbDelta( $sql_operators );
	dbDelta( $sql_visitors );

	update_option( 'bkx_live_chat_db_version', '1.0.0' );
}

/**
 * Create default canned responses.
 *
 * @since 1.0.0
 */
function bkx_live_chat_create_default_responses() {
	global $wpdb;

	$table = $wpdb->prefix . 'bkx_livechat_responses';

	// Check if already populated.
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
	if ( $count > 0 ) {
		return;
	}

	$responses = array(
		array(
			'shortcut' => 'hello',
			'title'    => 'Greeting',
			'content'  => 'Hello! Thank you for contacting us. How can I help you today?',
			'category' => 'General',
		),
		array(
			'shortcut' => 'thanks',
			'title'    => 'Thank You',
			'content'  => 'Thank you for chatting with us today! Is there anything else I can help you with?',
			'category' => 'General',
		),
		array(
			'shortcut' => 'bye',
			'title'    => 'Goodbye',
			'content'  => 'Thank you for contacting us. Have a great day!',
			'category' => 'General',
		),
		array(
			'shortcut' => 'hours',
			'title'    => 'Business Hours',
			'content'  => 'Our business hours are Monday to Friday, 9 AM to 6 PM. We are closed on weekends and holidays.',
			'category' => 'Info',
		),
		array(
			'shortcut' => 'book',
			'title'    => 'Booking Help',
			'content'  => 'I\'d be happy to help you with your booking. Could you please tell me what service you\'re interested in?',
			'category' => 'Booking',
		),
		array(
			'shortcut' => 'cancel',
			'title'    => 'Cancellation',
			'content'  => 'To cancel your booking, please provide your booking confirmation number and I\'ll assist you right away.',
			'category' => 'Booking',
		),
		array(
			'shortcut' => 'wait',
			'title'    => 'Please Wait',
			'content'  => 'Please hold on for a moment while I look into this for you.',
			'category' => 'General',
		),
	);

	foreach ( $responses as $response ) {
		$wpdb->insert( $table, $response ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

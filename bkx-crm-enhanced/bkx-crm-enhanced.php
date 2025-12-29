<?php
/**
 * Plugin Name: BookingX - CRM Enhanced
 * Plugin URI: https://flavflavor.developer.com/crm-enhanced/
 * Description: Advanced customer relationship management with segments, tags, notes, communication history, and automated follow-ups.
 * Version: 1.0.0
 * Author: flavorflavor.developer@gmail.com
 * Author URI: https://flavorflavor.developer.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-crm
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 *
 * @package BookingX\CRM
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_CRM_VERSION', '1.0.0' );
define( 'BKX_CRM_PLUGIN_FILE', __FILE__ );
define( 'BKX_CRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_CRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_CRM_MIN_BOOKINGX_VERSION', '2.0.0' );

/**
 * Check if BookingX is active.
 *
 * @return bool
 */
function bkx_crm_is_bookingx_active() {
	return class_exists( 'Bookingx' ) || defined( 'STARTER_BKX_STARTER_PLUGIN_DIR_PATH' );
}

/**
 * Admin notice for missing BookingX.
 */
function bkx_crm_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX plugin to be installed and activated.', 'bkx-crm' ),
				'<strong>BookingX - CRM Enhanced</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_crm_init() {
	if ( ! bkx_crm_is_bookingx_active() ) {
		add_action( 'admin_notices', 'bkx_crm_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_CRM_PLUGIN_DIR . 'src/autoload.php';

	// Initialize addon.
	\BookingX\CRM\CRMAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_crm_init', 20 );

/**
 * Plugin activation.
 */
function bkx_crm_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Customers table (enhanced profile data).
	$customers_table = $wpdb->prefix . 'bkx_crm_customers';
	$sql_customers = "CREATE TABLE {$customers_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned DEFAULT NULL,
		email varchar(255) NOT NULL,
		first_name varchar(100) DEFAULT '',
		last_name varchar(100) DEFAULT '',
		phone varchar(50) DEFAULT '',
		company varchar(255) DEFAULT '',
		address_1 varchar(255) DEFAULT '',
		address_2 varchar(255) DEFAULT '',
		city varchar(100) DEFAULT '',
		state varchar(100) DEFAULT '',
		postcode varchar(20) DEFAULT '',
		country varchar(2) DEFAULT '',
		date_of_birth date DEFAULT NULL,
		gender enum('male','female','other','prefer_not_to_say') DEFAULT NULL,
		source varchar(50) DEFAULT 'manual',
		lifetime_value decimal(10,2) DEFAULT 0.00,
		total_bookings int(11) DEFAULT 0,
		last_booking_date datetime DEFAULT NULL,
		customer_since datetime DEFAULT CURRENT_TIMESTAMP,
		status enum('active','inactive','blocked') DEFAULT 'active',
		custom_fields longtext DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY email (email),
		KEY user_id (user_id),
		KEY status (status),
		KEY lifetime_value (lifetime_value),
		KEY total_bookings (total_bookings)
	) {$charset_collate};";

	// Tags table.
	$tags_table = $wpdb->prefix . 'bkx_crm_tags';
	$sql_tags = "CREATE TABLE {$tags_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(100) NOT NULL,
		slug varchar(100) NOT NULL,
		color varchar(7) DEFAULT '#3b82f6',
		description text DEFAULT NULL,
		count int(11) DEFAULT 0,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug)
	) {$charset_collate};";

	// Customer-Tag relationships.
	$customer_tags_table = $wpdb->prefix . 'bkx_crm_customer_tags';
	$sql_customer_tags = "CREATE TABLE {$customer_tags_table} (
		customer_id bigint(20) unsigned NOT NULL,
		tag_id bigint(20) unsigned NOT NULL,
		added_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (customer_id, tag_id),
		KEY tag_id (tag_id)
	) {$charset_collate};";

	// Segments table.
	$segments_table = $wpdb->prefix . 'bkx_crm_segments';
	$sql_segments = "CREATE TABLE {$segments_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		description text DEFAULT NULL,
		conditions longtext NOT NULL,
		is_dynamic tinyint(1) DEFAULT 1,
		customer_count int(11) DEFAULT 0,
		created_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) {$charset_collate};";

	// Notes table.
	$notes_table = $wpdb->prefix . 'bkx_crm_notes';
	$sql_notes = "CREATE TABLE {$notes_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		booking_id bigint(20) unsigned DEFAULT NULL,
		note_type enum('general','booking','followup','complaint','compliment') DEFAULT 'general',
		content longtext NOT NULL,
		is_private tinyint(1) DEFAULT 0,
		created_by bigint(20) unsigned NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY customer_id (customer_id),
		KEY booking_id (booking_id),
		KEY note_type (note_type)
	) {$charset_collate};";

	// Communication log.
	$communications_table = $wpdb->prefix . 'bkx_crm_communications';
	$sql_communications = "CREATE TABLE {$communications_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		booking_id bigint(20) unsigned DEFAULT NULL,
		channel enum('email','sms','phone','whatsapp','in_person','other') NOT NULL,
		direction enum('inbound','outbound') NOT NULL,
		subject varchar(255) DEFAULT '',
		content longtext DEFAULT NULL,
		status enum('sent','delivered','read','replied','failed') DEFAULT 'sent',
		sent_by bigint(20) unsigned DEFAULT NULL,
		external_id varchar(255) DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY customer_id (customer_id),
		KEY booking_id (booking_id),
		KEY channel (channel),
		KEY created_at (created_at)
	) {$charset_collate};";

	// Follow-ups table.
	$followups_table = $wpdb->prefix . 'bkx_crm_followups';
	$sql_followups = "CREATE TABLE {$followups_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		booking_id bigint(20) unsigned DEFAULT NULL,
		followup_type enum('reminder','thankyou','feedback','reengagement','birthday','custom') NOT NULL,
		channel enum('email','sms') DEFAULT 'email',
		scheduled_at datetime NOT NULL,
		sent_at datetime DEFAULT NULL,
		status enum('pending','sent','cancelled','failed') DEFAULT 'pending',
		template_id bigint(20) unsigned DEFAULT NULL,
		custom_message longtext DEFAULT NULL,
		created_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY customer_id (customer_id),
		KEY status (status),
		KEY scheduled_at (scheduled_at)
	) {$charset_collate};";

	// Activity log.
	$activities_table = $wpdb->prefix . 'bkx_crm_activities';
	$sql_activities = "CREATE TABLE {$activities_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		activity_type varchar(50) NOT NULL,
		description text NOT NULL,
		metadata longtext DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY customer_id (customer_id),
		KEY activity_type (activity_type),
		KEY created_at (created_at)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_customers );
	dbDelta( $sql_tags );
	dbDelta( $sql_customer_tags );
	dbDelta( $sql_segments );
	dbDelta( $sql_notes );
	dbDelta( $sql_communications );
	dbDelta( $sql_followups );
	dbDelta( $sql_activities );

	// Set default options.
	$defaults = array(
		'auto_sync_users'       => true,
		'auto_followup_enabled' => true,
		'thankyou_delay'        => 24, // hours after booking.
		'feedback_delay'        => 72, // hours after booking.
		'reengagement_days'     => 30, // days since last booking.
		'birthday_enabled'      => true,
		'birthday_days_before'  => 7,
		'default_email_template' => 'default',
	);

	add_option( 'bkx_crm_settings', $defaults );
	add_option( 'bkx_crm_db_version', BKX_CRM_VERSION );

	// Create default tags.
	$wpdb->insert( $tags_table, array(
		'name'        => 'VIP',
		'slug'        => 'vip',
		'color'       => '#f59e0b',
		'description' => 'High-value customers',
	) );

	$wpdb->insert( $tags_table, array(
		'name'        => 'New Customer',
		'slug'        => 'new-customer',
		'color'       => '#22c55e',
		'description' => 'First-time customers',
	) );

	$wpdb->insert( $tags_table, array(
		'name'        => 'At Risk',
		'slug'        => 'at-risk',
		'color'       => '#ef4444',
		'description' => 'Customers who might churn',
	) );

	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_crm_activate' );

/**
 * Plugin deactivation.
 */
function bkx_crm_deactivate() {
	wp_clear_scheduled_hook( 'bkx_crm_process_followups' );
	wp_clear_scheduled_hook( 'bkx_crm_check_birthdays' );
	wp_clear_scheduled_hook( 'bkx_crm_sync_customers' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_crm_deactivate' );

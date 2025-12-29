<?php
/**
 * Plugin Name: BookingX - Advanced Email Templates
 * Plugin URI: https://flavflavor.developer.com/bookingx/addons/advanced-email-templates
 * Description: Create beautiful, customizable email templates with a drag-and-drop builder for all booking notifications.
 * Version: 1.0.0
 * Author: Flavor Developer
 * Author URI: https://flavordeveloper.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-advanced-email-templates
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0
 *
 * @package BookingX\AdvancedEmailTemplates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'BKX_EMAIL_TEMPLATES_VERSION', '1.0.0' );
define( 'BKX_EMAIL_TEMPLATES_FILE', __FILE__ );
define( 'BKX_EMAIL_TEMPLATES_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_EMAIL_TEMPLATES_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_EMAIL_TEMPLATES_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check dependencies.
 *
 * @return bool
 */
function bkx_email_templates_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_email_templates_missing_bookingx_notice' );
		return false;
	}
	return true;
}

/**
 * Missing BookingX notice.
 */
function bkx_email_templates_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name */
				esc_html__( '%s requires BookingX plugin to be installed and activated.', 'bkx-advanced-email-templates' ),
				'<strong>BookingX - Advanced Email Templates</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize plugin.
 */
function bkx_email_templates_init() {
	if ( ! bkx_email_templates_check_dependencies() ) {
		return;
	}

	require_once BKX_EMAIL_TEMPLATES_PATH . 'src/autoload.php';

	$addon = new BookingX\AdvancedEmailTemplates\EmailTemplatesAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_email_templates_init', 20 );

/**
 * Activation hook.
 */
function bkx_email_templates_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Email templates table.
	$sql_templates = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_email_templates (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		slug varchar(100) NOT NULL,
		subject varchar(255) NOT NULL,
		preheader varchar(255) DEFAULT '',
		content longtext NOT NULL,
		design_data longtext DEFAULT NULL,
		template_type varchar(50) NOT NULL DEFAULT 'custom',
		trigger_event varchar(100) DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'active',
		is_default tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		created_by bigint(20) unsigned DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug),
		KEY template_type (template_type),
		KEY trigger_event (trigger_event),
		KEY status (status)
	) $charset_collate;";

	// Email logs table.
	$sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_email_logs (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		template_id bigint(20) unsigned DEFAULT NULL,
		booking_id bigint(20) unsigned DEFAULT NULL,
		recipient varchar(255) NOT NULL,
		subject varchar(255) NOT NULL,
		body longtext NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'sent',
		error_message text DEFAULT NULL,
		opened_at datetime DEFAULT NULL,
		clicked_at datetime DEFAULT NULL,
		sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY template_id (template_id),
		KEY booking_id (booking_id),
		KEY recipient (recipient),
		KEY status (status),
		KEY sent_at (sent_at)
	) $charset_collate;";

	// Email blocks table (reusable content blocks).
	$sql_blocks = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_email_blocks (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		type varchar(50) NOT NULL,
		content longtext NOT NULL,
		settings longtext DEFAULT NULL,
		is_global tinyint(1) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY type (type),
		KEY is_global (is_global)
	) $charset_collate;";

	// Email attachments table.
	$sql_attachments = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_email_attachments (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		template_id bigint(20) unsigned NOT NULL,
		attachment_type varchar(50) NOT NULL,
		file_path varchar(500) DEFAULT NULL,
		dynamic_source varchar(100) DEFAULT NULL,
		settings longtext DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY template_id (template_id)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_templates );
	dbDelta( $sql_logs );
	dbDelta( $sql_blocks );
	dbDelta( $sql_attachments );

	// Set version.
	update_option( 'bkx_email_templates_db_version', BKX_EMAIL_TEMPLATES_VERSION );

	// Create default templates.
	bkx_email_templates_create_defaults();

	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_email_templates_activate' );

/**
 * Create default email templates.
 */
function bkx_email_templates_create_defaults() {
	global $wpdb;

	$table = $wpdb->prefix . 'bkx_email_templates';

	// Check if defaults already exist.
	$exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_default = 1" ); // phpcs:ignore
	if ( $exists > 0 ) {
		return;
	}

	$defaults = array(
		array(
			'name'          => 'Booking Confirmation',
			'slug'          => 'booking-confirmation',
			'subject'       => 'Booking Confirmed - {{booking_id}}',
			'preheader'     => 'Your booking has been confirmed',
			'template_type' => 'notification',
			'trigger_event' => 'bkx_booking_confirmed',
			'is_default'    => 1,
		),
		array(
			'name'          => 'Booking Reminder',
			'slug'          => 'booking-reminder',
			'subject'       => 'Reminder: Your appointment is coming up',
			'preheader'     => 'Don\'t forget your upcoming appointment',
			'template_type' => 'notification',
			'trigger_event' => 'bkx_booking_reminder',
			'is_default'    => 1,
		),
		array(
			'name'          => 'Booking Cancelled',
			'slug'          => 'booking-cancelled',
			'subject'       => 'Booking Cancelled - {{booking_id}}',
			'preheader'     => 'Your booking has been cancelled',
			'template_type' => 'notification',
			'trigger_event' => 'bkx_booking_cancelled',
			'is_default'    => 1,
		),
		array(
			'name'          => 'Booking Rescheduled',
			'slug'          => 'booking-rescheduled',
			'subject'       => 'Booking Rescheduled - {{booking_id}}',
			'preheader'     => 'Your booking has been rescheduled',
			'template_type' => 'notification',
			'trigger_event' => 'bkx_booking_rescheduled',
			'is_default'    => 1,
		),
		array(
			'name'          => 'Admin New Booking',
			'slug'          => 'admin-new-booking',
			'subject'       => 'New Booking Received - {{booking_id}}',
			'preheader'     => 'A new booking has been made',
			'template_type' => 'admin',
			'trigger_event' => 'bkx_booking_created',
			'is_default'    => 1,
		),
	);

	foreach ( $defaults as $template ) {
		$template['content']    = bkx_email_templates_get_default_content( $template['slug'] );
		$template['status']     = 'active';
		$template['created_at'] = current_time( 'mysql' );
		$template['updated_at'] = current_time( 'mysql' );

		$wpdb->insert( $table, $template ); // phpcs:ignore
	}
}

/**
 * Get default template content.
 *
 * @param string $slug Template slug.
 * @return string
 */
function bkx_email_templates_get_default_content( $slug ) {
	$content = '';

	switch ( $slug ) {
		case 'booking-confirmation':
			$content = '
<h2>Booking Confirmed!</h2>
<p>Hi {{customer_name}},</p>
<p>Your booking has been confirmed. Here are the details:</p>

<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Booking ID:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_id}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Service:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{service_name}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Date:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_date}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Time:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_time}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Staff:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{staff_name}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Total:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_total}}</td>
	</tr>
</table>

<p>{{#if location_address}}
<strong>Location:</strong><br>
{{location_address}}
{{/if}}</p>

<p>If you need to make any changes, please contact us.</p>

<p>Thank you for your booking!</p>
';
			break;

		case 'booking-reminder':
			$content = '
<h2>Appointment Reminder</h2>
<p>Hi {{customer_name}},</p>
<p>This is a friendly reminder that you have an upcoming appointment:</p>

<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Service:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{service_name}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Date:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_date}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Time:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_time}}</td>
	</tr>
</table>

<p>We look forward to seeing you!</p>
';
			break;

		case 'booking-cancelled':
			$content = '
<h2>Booking Cancelled</h2>
<p>Hi {{customer_name}},</p>
<p>Your booking has been cancelled. Here are the details:</p>

<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Booking ID:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_id}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Service:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{service_name}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Original Date:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_date}}</td>
	</tr>
</table>

<p>{{#if cancellation_reason}}
<strong>Reason:</strong> {{cancellation_reason}}
{{/if}}</p>

<p>If you have any questions, please contact us.</p>
';
			break;

		case 'booking-rescheduled':
			$content = '
<h2>Booking Rescheduled</h2>
<p>Hi {{customer_name}},</p>
<p>Your booking has been rescheduled. Here are the new details:</p>

<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Booking ID:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_id}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Service:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{service_name}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>New Date:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_date}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>New Time:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_time}}</td>
	</tr>
</table>

<p>If you need to make further changes, please contact us.</p>
';
			break;

		case 'admin-new-booking':
			$content = '
<h2>New Booking Received</h2>
<p>A new booking has been made. Here are the details:</p>

<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Booking ID:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_id}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Customer:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{customer_name}} ({{customer_email}})</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Service:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{service_name}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Date:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_date}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Time:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_time}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Staff:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{staff_name}}</td>
	</tr>
	<tr>
		<td style="padding: 10px; border: 1px solid #ddd;"><strong>Total:</strong></td>
		<td style="padding: 10px; border: 1px solid #ddd;">{{booking_total}}</td>
	</tr>
</table>

<p><a href="{{admin_booking_url}}" style="display: inline-block; padding: 10px 20px; background: #2271b1; color: #fff; text-decoration: none; border-radius: 4px;">View Booking</a></p>
';
			break;
	}

	return $content;
}

/**
 * Deactivation hook.
 */
function bkx_email_templates_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_email_templates_deactivate' );

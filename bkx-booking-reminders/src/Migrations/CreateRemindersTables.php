<?php
/**
 * Create Reminders Tables Migration
 *
 * Creates database tables for storing reminder schedules and logs.
 *
 * @package BookingX\BookingReminders\Migrations
 * @since   1.0.0
 */

namespace BookingX\BookingReminders\Migrations;

use BookingX\AddonSDK\Database\Migration;
use BookingX\AddonSDK\Database\Schema;

/**
 * Create reminders tables migration class.
 *
 * @since 1.0.0
 */
class CreateRemindersTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Scheduled reminders table.
		$table_reminders = $wpdb->prefix . 'bkx_scheduled_reminders';

		$sql_reminders = "CREATE TABLE {$table_reminders} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			reminder_type varchar(50) NOT NULL DEFAULT 'email',
			reminder_number tinyint(3) unsigned NOT NULL DEFAULT 1,
			channel varchar(20) NOT NULL DEFAULT 'email',
			recipient varchar(255) NOT NULL,
			scheduled_at datetime NOT NULL,
			sent_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			last_error text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY booking_id (booking_id),
			KEY status_scheduled (status, scheduled_at),
			KEY reminder_type (reminder_type),
			KEY channel (channel)
		) {$charset_collate};";

		// Reminder logs table (for tracking sent reminders).
		$table_logs = $wpdb->prefix . 'bkx_reminder_logs';

		$sql_logs = "CREATE TABLE {$table_logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reminder_id bigint(20) unsigned DEFAULT NULL,
			booking_id bigint(20) unsigned NOT NULL,
			channel varchar(20) NOT NULL,
			recipient varchar(255) NOT NULL,
			subject varchar(255) DEFAULT NULL,
			message_preview text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'sent',
			provider_response text DEFAULT NULL,
			sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY reminder_id (reminder_id),
			KEY booking_id (booking_id),
			KEY status (status),
			KEY sent_at (sent_at)
		) {$charset_collate};";

		// Email templates table.
		$table_templates = $wpdb->prefix . 'bkx_reminder_templates';

		$sql_templates = "CREATE TABLE {$table_templates} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			slug varchar(100) NOT NULL,
			template_type varchar(50) NOT NULL DEFAULT 'reminder',
			channel varchar(20) NOT NULL DEFAULT 'email',
			subject varchar(255) DEFAULT NULL,
			content longtext NOT NULL,
			is_default tinyint(1) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY template_type (template_type),
			KEY channel (channel),
			KEY is_active (is_active)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_reminders );
		dbDelta( $sql_logs );
		dbDelta( $sql_templates );

		// Insert default templates.
		$this->insert_default_templates();
	}

	/**
	 * Reverse the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		// Use %i placeholder for table identifiers - SECURITY.
		$tables = array(
			$wpdb->prefix . 'bkx_scheduled_reminders',
			$wpdb->prefix . 'bkx_reminder_logs',
			$wpdb->prefix . 'bkx_reminder_templates',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table )
			);
		}
	}

	/**
	 * Insert default email templates.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function insert_default_templates(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_reminder_templates';

		// Check if default templates exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE is_default = 1',
				$table
			)
		);

		if ( $exists > 0 ) {
			return;
		}

		$templates = array(
			array(
				'name'          => 'Default Email Reminder',
				'slug'          => 'default-email-reminder',
				'template_type' => 'reminder',
				'channel'       => 'email',
				'subject'       => __( 'Reminder: Your appointment on {booking_date}', 'bkx-booking-reminders' ),
				'content'       => $this->get_default_email_template(),
				'is_default'    => 1,
				'is_active'     => 1,
			),
			array(
				'name'          => 'Default SMS Reminder',
				'slug'          => 'default-sms-reminder',
				'template_type' => 'reminder',
				'channel'       => 'sms',
				'subject'       => null,
				'content'       => $this->get_default_sms_template(),
				'is_default'    => 1,
				'is_active'     => 1,
			),
			array(
				'name'          => 'Default Follow-up Email',
				'slug'          => 'default-followup-email',
				'template_type' => 'followup',
				'channel'       => 'email',
				'subject'       => __( 'Thank you for your visit!', 'bkx-booking-reminders' ),
				'content'       => $this->get_default_followup_template(),
				'is_default'    => 1,
				'is_active'     => 1,
			),
		);

		foreach ( $templates as $template ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $table, $template );
		}
	}

	/**
	 * Get default email template content.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_default_email_template(): string {
		return '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>{subject}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
	<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
		<h2 style="color: #2c3e50;">Appointment Reminder</h2>

		<p>Hello {customer_name},</p>

		<p>This is a friendly reminder about your upcoming appointment:</p>

		<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
			<p style="margin: 5px 0;"><strong>Service:</strong> {service_name}</p>
			<p style="margin: 5px 0;"><strong>Date:</strong> {booking_date}</p>
			<p style="margin: 5px 0;"><strong>Time:</strong> {booking_time}</p>
			<p style="margin: 5px 0;"><strong>Duration:</strong> {duration}</p>
			{if_staff}<p style="margin: 5px 0;"><strong>With:</strong> {staff_name}</p>{/if_staff}
			{if_location}<p style="margin: 5px 0;"><strong>Location:</strong> {location}</p>{/if_location}
		</div>

		<p>If you need to reschedule or cancel, please contact us as soon as possible.</p>

		<p>We look forward to seeing you!</p>

		<p>Best regards,<br>{business_name}</p>

		<hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
		<p style="font-size: 12px; color: #888;">
			{business_name}<br>
			{business_address}<br>
			{business_phone}
		</p>
	</div>
</body>
</html>';
	}

	/**
	 * Get default SMS template content.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_default_sms_template(): string {
		return 'Hi {customer_first_name}, reminder: {service_name} on {booking_date} at {booking_time}. Reply STOP to unsubscribe. - {business_name}';
	}

	/**
	 * Get default follow-up template content.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_default_followup_template(): string {
		return '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>{subject}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
	<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
		<h2 style="color: #2c3e50;">Thank You!</h2>

		<p>Hello {customer_name},</p>

		<p>Thank you for your recent visit. We hope you had a great experience!</p>

		<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
			<p style="margin: 5px 0;"><strong>Service:</strong> {service_name}</p>
			<p style="margin: 5px 0;"><strong>Date:</strong> {booking_date}</p>
		</div>

		{if_review}
		<p>We would love to hear your feedback! Please take a moment to leave us a review:</p>
		<p style="text-align: center; margin: 20px 0;">
			<a href="{review_link}" style="display: inline-block; padding: 12px 24px; background: #3498db; color: #fff; text-decoration: none; border-radius: 5px;">Leave a Review</a>
		</p>
		{/if_review}

		<p>We look forward to seeing you again!</p>

		<p>Best regards,<br>{business_name}</p>
	</div>
</body>
</html>';
	}
}

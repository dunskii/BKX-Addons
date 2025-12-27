<?php
/**
 * Create SMS Tables Migration
 *
 * @package BookingX\SmsNotificationsPro\Migrations
 * @since   1.0.0
 */

namespace BookingX\SmsNotificationsPro\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Create SMS tables migration class.
 *
 * @since 1.0.0
 */
class CreateSmsTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// SMS log table.
		$log_table = $wpdb->prefix . 'bkx_sms_log';

		$sql = "CREATE TABLE IF NOT EXISTS {$log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned DEFAULT NULL,
			message_type varchar(50) NOT NULL,
			recipient_type varchar(20) NOT NULL,
			recipient varchar(50) NOT NULL,
			message text NOT NULL,
			provider varchar(50) NOT NULL,
			provider_message_id varchar(100) DEFAULT NULL,
			status varchar(20) DEFAULT 'pending',
			error_message text,
			cost decimal(10,4) DEFAULT NULL,
			sent_at datetime DEFAULT CURRENT_TIMESTAMP,
			delivered_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY booking_id (booking_id),
			KEY status (status),
			KEY sent_at (sent_at),
			KEY provider_message_id (provider_message_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// SMS templates table.
		$templates_table = $wpdb->prefix . 'bkx_sms_templates';

		$sql = "CREATE TABLE IF NOT EXISTS {$templates_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			template_key varchar(50) NOT NULL,
			name varchar(100) NOT NULL,
			recipient_type varchar(20) NOT NULL,
			content text NOT NULL,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY template_key_recipient (template_key, recipient_type),
			KEY is_active (is_active)
		) {$charset_collate};";

		dbDelta( $sql );

		// Insert default templates.
		$this->insert_default_templates();

		// Store migration version.
		update_option( 'bkx_sms_pro_db_version', '1.0.0' );
	}

	/**
	 * Insert default SMS templates.
	 *
	 * @return void
	 */
	protected function insert_default_templates(): void {
		global $wpdb;

		$table     = $wpdb->prefix . 'bkx_sms_templates';
		$templates = array(
			// Customer templates.
			array(
				'template_key'   => 'booking_created',
				'name'           => 'Booking Confirmation',
				'recipient_type' => 'customer',
				'content'        => 'Hi {customer_first_name}, your booking for {service_name} on {booking_date} at {booking_time} has been received. Confirmation #{booking_id}',
			),
			array(
				'template_key'   => 'booking_confirmed',
				'name'           => 'Booking Confirmed',
				'recipient_type' => 'customer',
				'content'        => 'Great news! Your booking #{booking_id} for {service_name} has been confirmed. See you on {booking_date}!',
			),
			array(
				'template_key'   => 'booking_cancelled',
				'name'           => 'Booking Cancelled',
				'recipient_type' => 'customer',
				'content'        => 'Your booking #{booking_id} for {service_name} on {booking_date} has been cancelled.',
			),
			array(
				'template_key'   => 'booking_reminder',
				'name'           => 'Booking Reminder',
				'recipient_type' => 'customer',
				'content'        => 'Reminder: Your {service_name} appointment is tomorrow at {booking_time}. See you soon!',
			),
			array(
				'template_key'   => 'booking_completed',
				'name'           => 'Booking Complete',
				'recipient_type' => 'customer',
				'content'        => 'Thank you for visiting us! We hope you enjoyed your {service_name}. Book again at {site_url}',
			),
			array(
				'template_key'   => 'booking_updated',
				'name'           => 'Booking Updated',
				'recipient_type' => 'customer',
				'content'        => 'Your booking #{booking_id} has been updated. New date/time: {booking_date} at {booking_time}.',
			),

			// Staff templates.
			array(
				'template_key'   => 'booking_created',
				'name'           => 'New Booking (Staff)',
				'recipient_type' => 'staff',
				'content'        => 'New booking: {customer_name} - {service_name} on {booking_date} at {booking_time}. #{booking_id}',
			),
			array(
				'template_key'   => 'booking_cancelled',
				'name'           => 'Booking Cancelled (Staff)',
				'recipient_type' => 'staff',
				'content'        => 'Cancelled: {customer_name} - {service_name} on {booking_date}. #{booking_id}',
			),

			// Admin templates.
			array(
				'template_key'   => 'booking_created',
				'name'           => 'New Booking (Admin)',
				'recipient_type' => 'admin',
				'content'        => 'New booking #{booking_id}: {customer_name} - {service_name} on {booking_date} at {booking_time}',
			),
		);

		foreach ( $templates as $template ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $table, $template );
		}
	}

	/**
	 * Reverse the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_sms_templates" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_sms_log" );

		delete_option( 'bkx_sms_pro_db_version' );
	}
}

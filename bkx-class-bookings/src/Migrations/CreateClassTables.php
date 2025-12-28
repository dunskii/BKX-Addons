<?php
/**
 * Create Class Tables Migration
 *
 * @package BookingX\ClassBookings\Migrations
 * @since   1.0.0
 */

namespace BookingX\ClassBookings\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Migration to create class bookings tables.
 *
 * @since 1.0.0
 */
class CreateClassTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Class schedules table - recurring schedule slots.
		$table_schedules = $wpdb->prefix . 'bkx_class_schedules';
		$sql_schedules   = "CREATE TABLE {$table_schedules} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL,
			seat_id bigint(20) unsigned DEFAULT NULL,
			day_of_week tinyint(1) NOT NULL COMMENT '0=Sunday, 6=Saturday',
			start_time time NOT NULL,
			end_time time NOT NULL,
			capacity int(11) NOT NULL DEFAULT 10,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY class_id (class_id),
			KEY seat_id (seat_id),
			KEY day_of_week (day_of_week),
			KEY is_active (is_active)
		) {$charset_collate};";

		// Class sessions table - actual scheduled instances.
		$table_sessions = $wpdb->prefix . 'bkx_class_sessions';
		$sql_sessions   = "CREATE TABLE {$table_sessions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_id bigint(20) unsigned NOT NULL,
			schedule_id bigint(20) unsigned DEFAULT NULL,
			seat_id bigint(20) unsigned DEFAULT NULL,
			session_date date NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			capacity int(11) NOT NULL DEFAULT 10,
			booked_count int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'scheduled',
			notes text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY class_id (class_id),
			KEY schedule_id (schedule_id),
			KEY seat_id (seat_id),
			KEY session_date (session_date),
			KEY status (status),
			UNIQUE KEY unique_session (class_id, session_date, start_time)
		) {$charset_collate};";

		// Class bookings table - individual registrations.
		$table_bookings = $wpdb->prefix . 'bkx_class_bookings';
		$sql_bookings   = "CREATE TABLE {$table_bookings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id bigint(20) unsigned NOT NULL,
			class_id bigint(20) unsigned NOT NULL,
			booking_id bigint(20) unsigned DEFAULT NULL COMMENT 'Links to bkx_booking post',
			customer_id bigint(20) unsigned DEFAULT NULL,
			customer_name varchar(255) NOT NULL,
			customer_email varchar(255) NOT NULL,
			customer_phone varchar(50) DEFAULT NULL,
			quantity int(11) NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'registered',
			checked_in_at datetime DEFAULT NULL,
			notes text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY class_id (class_id),
			KEY booking_id (booking_id),
			KEY customer_id (customer_id),
			KEY customer_email (customer_email),
			KEY status (status)
		) {$charset_collate};";

		// Attendance table - check-in/check-out records.
		$table_attendance = $wpdb->prefix . 'bkx_class_attendance';
		$sql_attendance   = "CREATE TABLE {$table_attendance} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			class_booking_id bigint(20) unsigned NOT NULL,
			session_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'present',
			check_in_time datetime DEFAULT NULL,
			check_out_time datetime DEFAULT NULL,
			marked_by bigint(20) unsigned DEFAULT NULL,
			notes text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY class_booking_id (class_booking_id),
			KEY session_id (session_id),
			KEY status (status),
			UNIQUE KEY unique_attendance (class_booking_id, session_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_schedules );
		dbDelta( $sql_sessions );
		dbDelta( $sql_bookings );
		dbDelta( $sql_attendance );

		update_option( 'bkx_class_bookings_db_version', '1.0.0' );
	}

	/**
	 * Reverse the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_class_attendance" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_class_bookings" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_class_sessions" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_class_schedules" );
		// phpcs:enable

		delete_option( 'bkx_class_bookings_db_version' );
	}
}

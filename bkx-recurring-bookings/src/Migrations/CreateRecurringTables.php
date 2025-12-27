<?php
/**
 * Create Recurring Tables Migration
 *
 * @package BookingX\RecurringBookings\Migrations
 * @since   1.0.0
 */

namespace BookingX\RecurringBookings\Migrations;

use BookingX\AddonSDK\Database\Migration;
use BookingX\AddonSDK\Database\Schema;

/**
 * Migration to create recurring booking tables.
 *
 * @since 1.0.0
 */
class CreateRecurringTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Create recurring series table.
		$series_table = $wpdb->prefix . 'bkx_recurring_series';

		$series_sql = "CREATE TABLE IF NOT EXISTS {$series_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			master_booking_id bigint(20) UNSIGNED NOT NULL,
			customer_id bigint(20) UNSIGNED NOT NULL,
			seat_id bigint(20) UNSIGNED NOT NULL,
			base_id bigint(20) UNSIGNED NOT NULL,
			pattern varchar(50) NOT NULL DEFAULT 'weekly',
			pattern_options longtext DEFAULT NULL,
			start_date date NOT NULL,
			end_date date DEFAULT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			timezone varchar(100) NOT NULL DEFAULT 'UTC',
			max_occurrences int(11) UNSIGNED DEFAULT NULL,
			total_occurrences int(11) UNSIGNED NOT NULL DEFAULT 0,
			completed_occurrences int(11) UNSIGNED NOT NULL DEFAULT 0,
			status varchar(50) NOT NULL DEFAULT 'active',
			recurring_discount decimal(10,2) NOT NULL DEFAULT 0.00,
			metadata longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY master_booking_id (master_booking_id),
			KEY customer_id (customer_id),
			KEY seat_id (seat_id),
			KEY base_id (base_id),
			KEY status (status),
			KEY start_date (start_date),
			KEY pattern (pattern)
		) {$charset_collate};";

		// Create instances table.
		$instances_table = $wpdb->prefix . 'bkx_recurring_instances';

		$instances_sql = "CREATE TABLE IF NOT EXISTS {$instances_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			series_id bigint(20) UNSIGNED NOT NULL,
			booking_id bigint(20) UNSIGNED DEFAULT NULL,
			instance_number int(11) UNSIGNED NOT NULL,
			scheduled_date date NOT NULL,
			scheduled_time time NOT NULL,
			original_date date DEFAULT NULL,
			original_time time DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'scheduled',
			skip_reason varchar(255) DEFAULT NULL,
			reschedule_reason varchar(255) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY series_id (series_id),
			KEY booking_id (booking_id),
			KEY scheduled_date (scheduled_date),
			KEY status (status),
			KEY series_date (series_id, scheduled_date),
			CONSTRAINT fk_instances_series FOREIGN KEY (series_id) REFERENCES {$series_table} (id) ON DELETE CASCADE
		) {$charset_collate};";

		// Create exclusions table.
		$exclusions_table = $wpdb->prefix . 'bkx_recurring_exclusions';

		$exclusions_sql = "CREATE TABLE IF NOT EXISTS {$exclusions_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			series_id bigint(20) UNSIGNED NOT NULL,
			exclusion_type varchar(50) NOT NULL DEFAULT 'date',
			exclusion_date date DEFAULT NULL,
			day_of_week tinyint(1) UNSIGNED DEFAULT NULL,
			start_date date DEFAULT NULL,
			end_date date DEFAULT NULL,
			reason varchar(255) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY series_id (series_id),
			KEY exclusion_date (exclusion_date),
			KEY exclusion_type (exclusion_type),
			CONSTRAINT fk_exclusions_series FOREIGN KEY (series_id) REFERENCES {$series_table} (id) ON DELETE CASCADE
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $series_sql );
		dbDelta( $instances_sql );
		dbDelta( $exclusions_sql );

		// Store migration version.
		update_option( 'bkx_recurring_db_version', '1.0.0' );
	}

	/**
	 * Reverse the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		// Drop tables in reverse order due to foreign keys.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_recurring_exclusions" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_recurring_instances" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_recurring_series" );

		delete_option( 'bkx_recurring_db_version' );
	}
}

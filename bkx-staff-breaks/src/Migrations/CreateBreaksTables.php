<?php
/**
 * Create breaks tables migration
 *
 * @package BookingX\StaffBreaks
 * @since   1.0.0
 */

namespace BookingX\StaffBreaks\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Class CreateBreaksTables
 *
 * Creates the breaks and time off tables.
 *
 * @since 1.0.0
 */
class CreateBreaksTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Breaks table (recurring daily breaks like lunch).
		$breaks_table = $wpdb->prefix . 'bkx_staff_breaks';
		$sql_breaks   = "CREATE TABLE {$breaks_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			seat_id bigint(20) unsigned NOT NULL,
			day varchar(10) NOT NULL COMMENT 'monday, tuesday, etc. or all',
			start_time time NOT NULL,
			end_time time NOT NULL,
			label varchar(100) DEFAULT '',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY seat_id (seat_id),
			KEY day (day)
		) {$charset_collate};";

		// Time off table (specific dates: vacation, sick leave, etc.).
		$timeoff_table = $wpdb->prefix . 'bkx_staff_timeoff';
		$sql_timeoff   = "CREATE TABLE {$timeoff_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			seat_id bigint(20) unsigned NOT NULL,
			start_date date NOT NULL,
			end_date date NOT NULL,
			start_time time DEFAULT '00:00:00',
			end_time time DEFAULT '23:59:59',
			all_day tinyint(1) DEFAULT 1,
			type varchar(50) NOT NULL DEFAULT 'vacation' COMMENT 'vacation, sick, personal, blocked, holiday',
			reason text,
			recurring varchar(20) DEFAULT '' COMMENT 'none, yearly',
			status varchar(20) DEFAULT 'approved' COMMENT 'pending, approved, rejected',
			created_by bigint(20) unsigned DEFAULT 0,
			approved_by bigint(20) unsigned DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY seat_id (seat_id),
			KEY start_date (start_date),
			KEY end_date (end_date),
			KEY status (status),
			KEY type (type)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_breaks );
		dbDelta( $sql_timeoff );

		// Store migration version.
		update_option( 'bkx_staff_breaks_db_version', '1.0.0' );
	}

	/**
	 * Reverse the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_staff_breaks" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_staff_timeoff" );

		delete_option( 'bkx_staff_breaks_db_version' );
	}
}

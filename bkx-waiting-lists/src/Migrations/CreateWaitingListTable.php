<?php
/**
 * Create waiting list table migration
 *
 * @package BookingX\WaitingLists
 * @since   1.0.0
 */

namespace BookingX\WaitingLists\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Class CreateWaitingListTable
 *
 * Creates the waiting list table.
 *
 * @since 1.0.0
 */
class CreateWaitingListTable extends Migration {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table           = $wpdb->prefix . 'bkx_waiting_list';

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			seat_id bigint(20) unsigned DEFAULT 0,
			service_id bigint(20) unsigned DEFAULT 0,
			booking_date date NOT NULL,
			booking_time time NOT NULL,
			user_id bigint(20) unsigned DEFAULT 0,
			customer_name varchar(200) DEFAULT '',
			customer_email varchar(200) NOT NULL,
			customer_phone varchar(50) DEFAULT '',
			position int(10) unsigned NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'waiting' COMMENT 'waiting, notified, offered, accepted, declined, expired, cancelled',
			token varchar(64) NOT NULL,
			notified_at datetime DEFAULT NULL,
			offer_expires_at datetime DEFAULT NULL,
			accepted_at datetime DEFAULT NULL,
			booking_id bigint(20) unsigned DEFAULT 0 COMMENT 'Created booking ID if accepted',
			notes text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY seat_id (seat_id),
			KEY service_id (service_id),
			KEY booking_date (booking_date),
			KEY booking_time (booking_time),
			KEY customer_email (customer_email),
			KEY status (status),
			KEY token (token),
			KEY user_id (user_id),
			UNIQUE KEY unique_waitlist_entry (seat_id, service_id, booking_date, booking_time, customer_email)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'bkx_waiting_lists_db_version', '1.0.0' );
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
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_waiting_list" );

		delete_option( 'bkx_waiting_lists_db_version' );
	}
}

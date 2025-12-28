<?php
/**
 * Create Blocks Table Migration
 *
 * @package BookingX\HoldBlocks\Migrations
 * @since   1.0.0
 */

namespace BookingX\HoldBlocks\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Migration to create hold blocks table.
 *
 * @since 1.0.0
 */
class CreateBlocksTable extends Migration {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table = $wpdb->prefix . 'bkx_hold_blocks';

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			seat_id bigint(20) unsigned DEFAULT NULL COMMENT 'NULL means all seats',
			start_date date NOT NULL,
			end_date date DEFAULT NULL,
			start_time time DEFAULT NULL,
			end_time time DEFAULT NULL,
			all_day tinyint(1) NOT NULL DEFAULT 0,
			block_type varchar(50) NOT NULL DEFAULT 'hold',
			reason text,
			recurring varchar(20) DEFAULT NULL COMMENT 'none, daily, weekly, monthly, yearly',
			recurring_end_date date DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY seat_id (seat_id),
			KEY start_date (start_date),
			KEY end_date (end_date),
			KEY block_type (block_type),
			KEY recurring (recurring)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'bkx_hold_blocks_db_version', '1.0.0' );
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
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_hold_blocks" );

		delete_option( 'bkx_hold_blocks_db_version' );
	}
}

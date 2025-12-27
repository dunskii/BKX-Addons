<?php
/**
 * Create Points Tables Migration
 *
 * @package BookingX\RewardsPoints
 * @since   1.0.0
 */

namespace BookingX\RewardsPoints\Migrations;

use BookingX\AddonSDK\Database\Migration;
use BookingX\AddonSDK\Database\Schema;

/**
 * Class CreatePointsTables
 *
 * @since 1.0.0
 */
class CreatePointsTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Points balance table.
		$table_balance = $wpdb->prefix . 'bkx_points_balance';
		$sql_balance   = "CREATE TABLE IF NOT EXISTS {$table_balance} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			balance int(11) NOT NULL DEFAULT 0,
			lifetime_earned int(11) NOT NULL DEFAULT 0,
			lifetime_redeemed int(11) NOT NULL DEFAULT 0,
			last_activity datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id),
			KEY balance (balance)
		) {$charset_collate};";

		// Points transactions table.
		$table_transactions = $wpdb->prefix . 'bkx_points_transactions';
		$sql_transactions   = "CREATE TABLE IF NOT EXISTS {$table_transactions} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			points int(11) NOT NULL,
			type varchar(50) NOT NULL,
			description varchar(255) DEFAULT NULL,
			reference_type varchar(50) DEFAULT NULL,
			reference_id bigint(20) UNSIGNED DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY type (type),
			KEY reference (reference_type, reference_id),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		// Redemptions table.
		$table_redemptions = $wpdb->prefix . 'bkx_points_redemptions';
		$sql_redemptions   = "CREATE TABLE IF NOT EXISTS {$table_redemptions} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			transaction_id bigint(20) UNSIGNED NOT NULL,
			points_used int(11) NOT NULL,
			discount_value decimal(10,2) NOT NULL,
			booking_id bigint(20) UNSIGNED DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY booking_id (booking_id),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_balance );
		dbDelta( $sql_transactions );
		dbDelta( $sql_redemptions );

		// Store version.
		update_option( 'bkx_rewards_db_version', '1.0.0' );
	}

	/**
	 * Reverse the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_points_redemptions" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_points_transactions" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_points_balance" );

		delete_option( 'bkx_rewards_db_version' );
	}
}

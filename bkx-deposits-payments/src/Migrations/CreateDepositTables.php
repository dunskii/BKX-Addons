<?php
/**
 * Create Deposit Tables Migration
 *
 * @package BookingX\DepositsPayments
 * @since   1.0.0
 */

namespace BookingX\DepositsPayments\Migrations;

/**
 * Create deposits table migration.
 *
 * @since 1.0.0
 */
class CreateDepositTables {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_deposits';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			total_price decimal(10,2) NOT NULL,
			deposit_amount decimal(10,2) NOT NULL,
			balance_amount decimal(10,2) NOT NULL,
			deposit_status varchar(20) NOT NULL DEFAULT 'pending',
			balance_status varchar(20) NOT NULL DEFAULT 'pending',
			paid_in_full tinyint(1) NOT NULL DEFAULT 0,
			deposit_paid_at datetime DEFAULT NULL,
			balance_paid_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY deposit_status (deposit_status),
			KEY balance_status (balance_status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Reverse the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_deposits';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}

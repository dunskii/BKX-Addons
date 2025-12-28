<?php
/**
 * Create Group Tables Migration
 *
 * @package BookingX\GroupBookings\Migrations
 * @since   1.0.0
 */

namespace BookingX\GroupBookings\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Migration to create group bookings tables.
 *
 * @since 1.0.0
 */
class CreateGroupTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Group participants table.
		$table_participants = $wpdb->prefix . 'bkx_group_participants';
		$sql_participants   = "CREATE TABLE {$table_participants} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			participant_name varchar(255) NOT NULL,
			participant_email varchar(255) DEFAULT NULL,
			participant_phone varchar(50) DEFAULT NULL,
			notes text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY booking_id (booking_id),
			KEY participant_email (participant_email)
		) {$charset_collate};";

		// Tiered pricing table.
		$table_tiers = $wpdb->prefix . 'bkx_group_pricing_tiers';
		$sql_tiers   = "CREATE TABLE {$table_tiers} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			base_id bigint(20) unsigned NOT NULL,
			min_quantity int(11) NOT NULL,
			max_quantity int(11) NOT NULL,
			price_type varchar(20) NOT NULL DEFAULT 'per_person',
			price decimal(10,2) NOT NULL,
			discount_type varchar(20) DEFAULT NULL,
			discount_value decimal(10,2) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY base_id (base_id),
			KEY min_quantity (min_quantity),
			KEY max_quantity (max_quantity)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_participants );
		dbDelta( $sql_tiers );

		update_option( 'bkx_group_bookings_db_version', '1.0.0' );
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
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_group_pricing_tiers" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_group_participants" );
		// phpcs:enable

		delete_option( 'bkx_group_bookings_db_version' );
	}
}

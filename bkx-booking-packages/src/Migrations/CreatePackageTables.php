<?php
/**
 * Create Package Tables Migration
 *
 * @package BookingX\BookingPackages\Migrations
 * @since   1.0.0
 */

namespace BookingX\BookingPackages\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Migration to create package tables.
 *
 * @since 1.0.0
 */
class CreatePackageTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Customer packages table (purchased packages).
		$customer_packages_table = $wpdb->prefix . 'bkx_customer_packages';

		$customer_packages_sql = "CREATE TABLE IF NOT EXISTS {$customer_packages_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) UNSIGNED NOT NULL,
			package_id bigint(20) UNSIGNED NOT NULL,
			order_id bigint(20) UNSIGNED DEFAULT NULL,
			total_uses int(11) UNSIGNED NOT NULL DEFAULT 0,
			uses_remaining int(11) UNSIGNED NOT NULL DEFAULT 0,
			purchase_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			expiry_date datetime DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			notes text DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY customer_id (customer_id),
			KEY package_id (package_id),
			KEY order_id (order_id),
			KEY status (status),
			KEY expiry_date (expiry_date),
			KEY customer_status (customer_id, status)
		) {$charset_collate};";

		// Package redemptions table.
		$redemptions_table = $wpdb->prefix . 'bkx_package_redemptions';

		$redemptions_sql = "CREATE TABLE IF NOT EXISTS {$redemptions_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_package_id bigint(20) UNSIGNED NOT NULL,
			booking_id bigint(20) UNSIGNED NOT NULL,
			uses_applied int(11) UNSIGNED NOT NULL DEFAULT 1,
			status varchar(50) NOT NULL DEFAULT 'pending',
			redeemed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY customer_package_id (customer_package_id),
			KEY booking_id (booking_id),
			KEY status (status),
			CONSTRAINT fk_redemptions_package FOREIGN KEY (customer_package_id) REFERENCES {$customer_packages_table} (id) ON DELETE CASCADE
		) {$charset_collate};";

		// Package services table (which services package applies to).
		$package_services_table = $wpdb->prefix . 'bkx_package_services';

		$package_services_sql = "CREATE TABLE IF NOT EXISTS {$package_services_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			package_id bigint(20) UNSIGNED NOT NULL,
			service_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY package_service (package_id, service_id),
			KEY package_id (package_id),
			KEY service_id (service_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $customer_packages_sql );
		dbDelta( $redemptions_sql );
		dbDelta( $package_services_sql );

		// Store migration version.
		update_option( 'bkx_packages_db_version', '1.0.0' );
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
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_package_services" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_package_redemptions" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_customer_packages" );

		delete_option( 'bkx_packages_db_version' );
	}
}

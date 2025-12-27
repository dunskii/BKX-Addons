<?php
/**
 * Create Coupon Tables Migration
 *
 * @package BookingX\CouponCodes\Migrations
 * @since   1.0.0
 */

namespace BookingX\CouponCodes\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Create coupon tables migration class.
 *
 * @since 1.0.0
 */
class CreateCouponTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Coupons table.
		$coupons_table = $wpdb->prefix . 'bkx_coupons';

		$sql = "CREATE TABLE IF NOT EXISTS {$coupons_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(50) NOT NULL,
			description text,
			discount_type varchar(20) NOT NULL DEFAULT 'percentage',
			discount_value decimal(10,2) NOT NULL DEFAULT 0,
			min_booking_amount decimal(10,2) DEFAULT 0,
			max_discount decimal(10,2) DEFAULT 0,
			usage_limit int(11) DEFAULT 0,
			usage_count int(11) DEFAULT 0,
			per_user_limit int(11) DEFAULT 0,
			start_date datetime DEFAULT NULL,
			end_date datetime DEFAULT NULL,
			is_active tinyint(1) DEFAULT 1,
			services longtext,
			seats longtext,
			excluded_services longtext,
			user_roles longtext,
			first_booking_only tinyint(1) DEFAULT 0,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY code (code),
			KEY is_active (is_active),
			KEY start_date (start_date),
			KEY end_date (end_date)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Coupon usage table.
		$usage_table = $wpdb->prefix . 'bkx_coupon_usage';

		$sql = "CREATE TABLE IF NOT EXISTS {$usage_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			coupon_id bigint(20) unsigned NOT NULL,
			booking_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			discount_amount decimal(10,2) NOT NULL DEFAULT 0,
			used_at datetime DEFAULT CURRENT_TIMESTAMP,
			status varchar(20) DEFAULT 'active',
			PRIMARY KEY (id),
			KEY coupon_id (coupon_id),
			KEY booking_id (booking_id),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );

		// Coupon restrictions table (for complex rules).
		$restrictions_table = $wpdb->prefix . 'bkx_coupon_restrictions';

		$sql = "CREATE TABLE IF NOT EXISTS {$restrictions_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			coupon_id bigint(20) unsigned NOT NULL,
			restriction_type varchar(50) NOT NULL,
			restriction_value longtext NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY coupon_id (coupon_id),
			KEY restriction_type (restriction_type)
		) {$charset_collate};";

		dbDelta( $sql );

		// Store migration version.
		update_option( 'bkx_coupon_codes_db_version', '1.0.0' );
	}

	/**
	 * Reverse the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_coupon_restrictions" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_coupon_usage" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_coupons" );

		delete_option( 'bkx_coupon_codes_db_version' );
	}
}

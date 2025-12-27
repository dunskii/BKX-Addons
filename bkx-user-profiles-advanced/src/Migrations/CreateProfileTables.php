<?php
/**
 * Create Profile Tables Migration
 *
 * @package BookingX\UserProfilesAdvanced\Migrations
 * @since   1.0.0
 */

namespace BookingX\UserProfilesAdvanced\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Create profile tables migration class.
 *
 * @since 1.0.0
 */
class CreateProfileTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Customer profiles table.
		$profiles_table = $wpdb->prefix . 'bkx_customer_profiles';

		$sql = "CREATE TABLE IF NOT EXISTS {$profiles_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			phone varchar(50) DEFAULT NULL,
			preferred_time varchar(50) DEFAULT NULL,
			communication_preference varchar(20) DEFAULT 'email',
			notes text,
			total_bookings int(11) DEFAULT 0,
			total_spent decimal(10,2) DEFAULT 0.00,
			cancellation_count int(11) DEFAULT 0,
			last_booking_id bigint(20) unsigned DEFAULT NULL,
			last_booking_date datetime DEFAULT NULL,
			sms_optin tinyint(1) DEFAULT 0,
			email_optin tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id),
			KEY last_booking_date (last_booking_date),
			KEY total_spent (total_spent)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Loyalty points table.
		$points_table = $wpdb->prefix . 'bkx_loyalty_points';

		$sql = "CREATE TABLE IF NOT EXISTS {$points_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			points int(11) NOT NULL,
			type varchar(50) NOT NULL,
			reference_id bigint(20) unsigned DEFAULT NULL,
			description text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY type (type),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		dbDelta( $sql );

		// Loyalty balance table (cached totals).
		$balance_table = $wpdb->prefix . 'bkx_loyalty_balance';

		$sql = "CREATE TABLE IF NOT EXISTS {$balance_table} (
			user_id bigint(20) unsigned NOT NULL,
			available_points int(11) DEFAULT 0,
			lifetime_earned int(11) DEFAULT 0,
			lifetime_redeemed int(11) DEFAULT 0,
			pending_discount decimal(10,2) DEFAULT 0.00,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (user_id)
		) {$charset_collate};";

		dbDelta( $sql );

		// Favorites table.
		$favorites_table = $wpdb->prefix . 'bkx_favorites';

		$sql = "CREATE TABLE IF NOT EXISTS {$favorites_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			item_id bigint(20) unsigned NOT NULL,
			item_type varchar(20) NOT NULL DEFAULT 'service',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_item (user_id, item_id, item_type),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql );

		// Referrals table.
		$referrals_table = $wpdb->prefix . 'bkx_referrals';

		$sql = "CREATE TABLE IF NOT EXISTS {$referrals_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			referrer_id bigint(20) unsigned NOT NULL,
			referred_id bigint(20) unsigned NOT NULL,
			referral_code varchar(50) NOT NULL,
			status varchar(20) DEFAULT 'pending',
			points_awarded int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY referred_id (referred_id),
			KEY referrer_id (referrer_id),
			KEY referral_code (referral_code)
		) {$charset_collate};";

		dbDelta( $sql );

		// Store migration version.
		update_option( 'bkx_user_profiles_db_version', '1.0.0' );
	}

	/**
	 * Reverse the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_referrals" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_favorites" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_loyalty_balance" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_loyalty_points" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_customer_profiles" );

		delete_option( 'bkx_user_profiles_db_version' );
	}
}

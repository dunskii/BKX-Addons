<?php
/**
 * Create Sync Tables Migration
 *
 * @package BookingX\GoogleCalendar\Migrations
 * @since   1.0.0
 */

namespace BookingX\GoogleCalendar\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Create sync tables migration class.
 *
 * @since 1.0.0
 */
class CreateSyncTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Calendar connections table (for staff and main account).
		$connections_table = $wpdb->prefix . 'bkx_google_connections';

		$sql = "CREATE TABLE IF NOT EXISTS {$connections_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			staff_id bigint(20) unsigned DEFAULT NULL,
			access_token text NOT NULL,
			refresh_token text NOT NULL,
			token_expires_at datetime DEFAULT NULL,
			google_email varchar(255) DEFAULT NULL,
			calendar_id varchar(255) DEFAULT NULL,
			sync_token varchar(255) DEFAULT NULL,
			last_sync_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY staff_id (staff_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Synced events table (mapping between bookings and Google events).
		$events_table = $wpdb->prefix . 'bkx_google_events';

		$sql = "CREATE TABLE IF NOT EXISTS {$events_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			google_event_id varchar(255) NOT NULL,
			calendar_id varchar(255) NOT NULL,
			staff_id bigint(20) unsigned DEFAULT NULL,
			event_status varchar(50) DEFAULT 'confirmed',
			sync_direction varchar(20) DEFAULT 'to_google',
			last_synced_at datetime DEFAULT CURRENT_TIMESTAMP,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY booking_id (booking_id),
			KEY google_event_id (google_event_id),
			KEY staff_id (staff_id)
		) {$charset_collate};";

		dbDelta( $sql );

		// Sync log table.
		$log_table = $wpdb->prefix . 'bkx_google_sync_log';

		$sql = "CREATE TABLE IF NOT EXISTS {$log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sync_type varchar(50) NOT NULL,
			direction varchar(20) DEFAULT 'to_google',
			items_synced int(11) DEFAULT 0,
			items_failed int(11) DEFAULT 0,
			error_message text,
			started_at datetime NOT NULL,
			completed_at datetime DEFAULT NULL,
			duration_seconds int(11) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY sync_type (sync_type),
			KEY started_at (started_at)
		) {$charset_collate};";

		dbDelta( $sql );

		// Store migration version.
		update_option( 'bkx_google_calendar_db_version', '1.0.0' );
	}

	/**
	 * Reverse the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_google_sync_log" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_google_events" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_google_connections" );

		delete_option( 'bkx_google_calendar_db_version' );
	}
}

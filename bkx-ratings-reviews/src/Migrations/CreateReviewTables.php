<?php
/**
 * Create Review Tables Migration
 *
 * @package BookingX\RatingsReviews
 * @since   1.0.0
 */

namespace BookingX\RatingsReviews\Migrations;

/**
 * Create reviews table migration.
 *
 * @since 1.0.0
 */
class CreateReviewTables {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_reviews';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned NOT NULL,
			seat_id bigint(20) unsigned DEFAULT NULL,
			customer_email varchar(255) NOT NULL,
			rating tinyint(1) NOT NULL,
			review_text text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			helpful_count int(11) NOT NULL DEFAULT 0,
			not_helpful_count int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY service_id (service_id),
			KEY seat_id (seat_id),
			KEY status (status),
			KEY rating (rating)
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

		$table_name = $wpdb->prefix . 'bkx_reviews';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}

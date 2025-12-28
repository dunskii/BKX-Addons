<?php
/**
 * Create Beauty Tables Migration
 *
 * Creates database tables for Beauty & Wellness addon.
 *
 * @package BookingX\BeautyWellness\Migrations
 * @since   1.0.0
 */

namespace BookingX\BeautyWellness\Migrations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CreateBeautyTables
 *
 * @since 1.0.0
 */
class CreateBeautyTables {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Client preferences table.
		$table_name = $wpdb->prefix . 'bkx_client_preferences';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			skin_type varchar(50) DEFAULT '',
			skin_concerns text,
			allergies text,
			sensitivities text,
			product_preferences text,
			pressure_preference varchar(20) DEFAULT 'medium',
			temperature_preference varchar(20) DEFAULT 'warm',
			music_preference varchar(100) DEFAULT '',
			aromatherapy_preference varchar(100) DEFAULT '',
			preferred_stylist bigint(20) UNSIGNED DEFAULT 0,
			hair_type varchar(50) DEFAULT '',
			hair_concerns text,
			nail_shape_preference varchar(50) DEFAULT '',
			notes text,
			consultation_completed tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_id (user_id),
			KEY skin_type (skin_type),
			KEY preferred_stylist (preferred_stylist)
		) {$charset_collate};";

		dbDelta( $sql );

		// Stylist portfolio table.
		$table_name = $wpdb->prefix . 'bkx_stylist_portfolio';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			stylist_id bigint(20) UNSIGNED NOT NULL,
			title varchar(255) NOT NULL,
			description text,
			type varchar(20) DEFAULT 'single',
			category varchar(100) DEFAULT '',
			before_image_id bigint(20) UNSIGNED DEFAULT 0,
			after_image_id bigint(20) UNSIGNED DEFAULT 0,
			image_id bigint(20) UNSIGNED DEFAULT 0,
			video_url varchar(255) DEFAULT '',
			treatment_ids text,
			products_used text,
			techniques text,
			client_testimonial text,
			is_featured tinyint(1) DEFAULT 0,
			is_public tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY stylist_id (stylist_id),
			KEY type (type),
			KEY category (category),
			KEY is_featured (is_featured),
			KEY is_public (is_public)
		) {$charset_collate};";

		dbDelta( $sql );

		// Consultation forms table.
		$table_name = $wpdb->prefix . 'bkx_consultation_forms';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) UNSIGNED DEFAULT 0,
			user_id bigint(20) UNSIGNED NOT NULL,
			treatment_id bigint(20) UNSIGNED NOT NULL,
			form_data longtext NOT NULL,
			consent_given tinyint(1) DEFAULT 0,
			consent_timestamp datetime DEFAULT NULL,
			signature_data text,
			status varchar(20) DEFAULT 'pending',
			reviewed_by bigint(20) UNSIGNED DEFAULT 0,
			reviewed_at datetime DEFAULT NULL,
			notes text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY booking_id (booking_id),
			KEY user_id (user_id),
			KEY treatment_id (treatment_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );

		// Treatment history tracking table (supplements booking post meta).
		$table_name = $wpdb->prefix . 'bkx_treatment_history';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) UNSIGNED NOT NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			stylist_id bigint(20) UNSIGNED NOT NULL,
			treatment_id bigint(20) UNSIGNED NOT NULL,
			variations_used text,
			addons_used text,
			products_used text,
			color_formula text,
			notes text,
			before_photo_id bigint(20) UNSIGNED DEFAULT 0,
			after_photo_id bigint(20) UNSIGNED DEFAULT 0,
			satisfaction_rating tinyint(1) DEFAULT 0,
			follow_up_recommended tinyint(1) DEFAULT 0,
			follow_up_date date DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY booking_id (booking_id),
			KEY user_id (user_id),
			KEY stylist_id (stylist_id),
			KEY treatment_id (treatment_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		// Update version option.
		update_option( 'bkx_beauty_wellness_db_version', '1.0.0' );
	}

	/**
	 * Reverse the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'bkx_client_preferences',
			$wpdb->prefix . 'bkx_stylist_portfolio',
			$wpdb->prefix . 'bkx_consultation_forms',
			$wpdb->prefix . 'bkx_treatment_history',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constructed internally.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( 'bkx_beauty_wellness_db_version' );
	}
}

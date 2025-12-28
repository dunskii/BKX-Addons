<?php
/**
 * Healthcare Tables Migration.
 *
 * Creates database tables for healthcare functionality.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

namespace BookingX\HealthcarePractice\Migrations;

/**
 * Create Healthcare Tables migration class.
 *
 * @since 1.0.0
 */
class CreateHealthcareTables {

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

		// Patient intakes table.
		$table_name = $wpdb->prefix . 'bkx_patient_intakes';
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			form_id bigint(20) unsigned NOT NULL,
			booking_id bigint(20) unsigned DEFAULT NULL,
			form_data longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY form_id (form_id),
			KEY booking_id (booking_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql );

		// Patient consents table.
		$table_name = $wpdb->prefix . 'bkx_patient_consents';
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			form_id bigint(20) unsigned NOT NULL,
			form_version varchar(20) NOT NULL DEFAULT '1.0',
			form_content_hash varchar(64) NOT NULL,
			signature longtext DEFAULT NULL,
			ip_address varchar(45) NOT NULL,
			user_agent text DEFAULT NULL,
			consented_at datetime NOT NULL,
			expires_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			revoked_at datetime DEFAULT NULL,
			revoked_by bigint(20) unsigned DEFAULT NULL,
			revoke_reason text DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY form_id (form_id),
			KEY status (status),
			KEY expires_at (expires_at)
		) {$charset_collate};";
		dbDelta( $sql );

		// HIPAA audit log table.
		$table_name = $wpdb->prefix . 'bkx_hipaa_audit_log';
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL,
			object_id bigint(20) unsigned DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			ip_address varchar(45) NOT NULL,
			user_agent text DEFAULT NULL,
			event_data longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY object_id (object_id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql );

		// Insurance verifications table.
		$table_name = $wpdb->prefix . 'bkx_insurance_verifications';
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			provider varchar(100) NOT NULL,
			member_id varchar(100) NOT NULL,
			group_id varchar(100) DEFAULT NULL,
			dob date NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			eligibility_data longtext DEFAULT NULL,
			verified_by bigint(20) unsigned DEFAULT NULL,
			verified_at datetime DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql );

		// Telemedicine session logs.
		$table_name = $wpdb->prefix . 'bkx_telemedicine_logs';
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			event varchar(50) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			event_data longtext DEFAULT NULL,
			ip_address varchar(45) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY event (event),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql );

		// Patient documents table.
		$table_name = $wpdb->prefix . 'bkx_patient_documents';
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL,
			type varchar(50) NOT NULL,
			file_path varchar(500) NOT NULL,
			url varchar(500) NOT NULL,
			uploaded_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY type (type),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql );

		// Patient messages table.
		$table_name = $wpdb->prefix . 'bkx_patient_messages';
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			recipient_id bigint(20) unsigned NOT NULL,
			subject varchar(255) DEFAULT NULL,
			content longtext NOT NULL,
			sender_name varchar(100) DEFAULT NULL,
			read_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY recipient_id (recipient_id),
			KEY read_at (read_at),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql );

		// Clinical notes table.
		$table_name = $wpdb->prefix . 'bkx_clinical_notes';
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			patient_id bigint(20) unsigned NOT NULL,
			booking_id bigint(20) unsigned DEFAULT NULL,
			provider_id bigint(20) unsigned NOT NULL,
			note_type varchar(50) NOT NULL DEFAULT 'general',
			content longtext NOT NULL,
			is_private tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY patient_id (patient_id),
			KEY booking_id (booking_id),
			KEY provider_id (provider_id),
			KEY note_type (note_type),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql );

		// Store current DB version.
		update_option( 'bkx_healthcare_db_version', BKX_HEALTHCARE_VERSION );
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
			$wpdb->prefix . 'bkx_patient_intakes',
			$wpdb->prefix . 'bkx_patient_consents',
			$wpdb->prefix . 'bkx_hipaa_audit_log',
			$wpdb->prefix . 'bkx_insurance_verifications',
			$wpdb->prefix . 'bkx_telemedicine_logs',
			$wpdb->prefix . 'bkx_patient_documents',
			$wpdb->prefix . 'bkx_patient_messages',
			$wpdb->prefix . 'bkx_clinical_notes',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( 'bkx_healthcare_db_version' );
	}
}

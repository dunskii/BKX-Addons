<?php
/**
 * Uninstall script for Healthcare Practice Management
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'bkx_healthcare_settings' );
delete_option( 'bkx_healthcare_practice_license_key' );
delete_option( 'bkx_healthcare_practice_license_status' );
delete_option( 'bkx_healthcare_db_version' );

// Delete custom tables.
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
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constructed internally.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete post meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_provider_%'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_consent_%'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_intake_%'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_telemedicine_%'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'patient_intake_id'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'consent_ids'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'telemedicine_enabled'" );

// Delete user meta.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_bkx_patient_%'" );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_bkx_insurance_%'" );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = '_bkx_date_of_birth'" );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = '_bkx_address'" );
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = '_bkx_emergency_contact'" );

// Delete custom post type posts.
$post_types = array( 'bkx_consent_form', 'bkx_intake_form', 'bkx_provider' );

foreach ( $post_types as $post_type ) {
	$posts = get_posts( array(
		'post_type'      => $post_type,
		'posts_per_page' => -1,
		'post_status'    => 'any',
	) );

	foreach ( $posts as $post ) {
		wp_delete_post( $post->ID, true );
	}
}

// Delete custom taxonomies terms.
$taxonomies = array( 'bkx_medical_specialty', 'bkx_appointment_type', 'bkx_insurance_network' );

foreach ( $taxonomies as $taxonomy ) {
	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
	) );

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			wp_delete_term( $term->term_id, $taxonomy );
		}
	}
}

// Clear any transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_healthcare_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_healthcare_%'" );

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_healthcare_send_reminders' );
wp_clear_scheduled_hook( 'bkx_healthcare_data_retention' );
wp_clear_scheduled_hook( 'bkx_healthcare_consent_expiry_check' );

<?php
/**
 * Uninstall script for Beauty & Wellness Suite
 *
 * @package BookingX\BeautyWellness
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'bkx_beauty_wellness_settings' );
delete_option( 'bkx_beauty_wellness_license_key' );
delete_option( 'bkx_beauty_wellness_license_status' );
delete_option( 'bkx_beauty_wellness_db_version' );

// Delete custom tables.
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

// Delete post meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_beauty_%'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_treatment_%'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_featured_treatment'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_consultation_required'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_contraindications'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_aftercare_notes'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_prep_instructions'" );

// Delete custom taxonomies terms.
$taxonomies = array( 'bkx_treatment_category', 'bkx_skin_type', 'bkx_specialization' );

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
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_beauty_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_beauty_%'" );

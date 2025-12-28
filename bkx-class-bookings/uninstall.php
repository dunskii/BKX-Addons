<?php
/**
 * Uninstall script for Class Bookings
 *
 * @package BookingX\ClassBookings
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'bkx_class_bookings_settings' );
delete_option( 'bkx_class_bookings_db_version' );
delete_option( 'bkx_class_bookings_license_key' );
delete_option( 'bkx_class_bookings_license_status' );

// Delete all class posts and their meta.
$class_posts = get_posts(
	array(
		'post_type'      => 'bkx_class',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $class_posts as $post_id ) {
	wp_delete_post( $post_id, true );
}

// Drop custom tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_class_attendance" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_class_bookings" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_class_sessions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bkx_class_schedules" );
// phpcs:enable

// Delete taxonomy terms.
$terms = get_terms(
	array(
		'taxonomy'   => 'bkx_class_category',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( ! is_wp_error( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( $term_id, 'bkx_class_category' );
	}
}

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_class_generate_sessions' );
wp_clear_scheduled_hook( 'bkx_class_send_reminders' );
wp_clear_scheduled_hook( 'bkx_class_cleanup_expired' );

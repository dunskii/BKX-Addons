<?php
/**
 * Uninstall script for Fitness & Sports Management
 *
 * @package BookingX\FitnessSports
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'bkx_fitness_sports_settings' );
delete_option( 'bkx_fitness_sports_license_key' );
delete_option( 'bkx_fitness_sports_license_status' );
delete_option( 'bkx_fitness_sports_db_version' );

// Delete custom tables.
$tables = array(
	$wpdb->prefix . 'bkx_class_schedules',
	$wpdb->prefix . 'bkx_class_bookings',
	$wpdb->prefix . 'bkx_class_waitlist',
	$wpdb->prefix . 'bkx_user_memberships',
	$wpdb->prefix . 'bkx_equipment_bookings',
	$wpdb->prefix . 'bkx_workout_logs',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constructed internally.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete post meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_class_%'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_trainer_%'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_is_trainer'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_membership_%'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_plan_%'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_equipment_%'" );

// Delete custom post type posts.
$post_types = array( 'bkx_fitness_class', 'bkx_equipment', 'bkx_membership' );

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
$taxonomies = array( 'bkx_class_category', 'bkx_difficulty_level', 'bkx_equipment_category', 'bkx_trainer_specialty' );

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
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_fitness_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_fitness_%'" );

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_fitness_class_reminders' );
wp_clear_scheduled_hook( 'bkx_fitness_check_memberships' );

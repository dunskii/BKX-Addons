<?php
/**
 * Uninstall BookingX Staff Performance Analytics.
 *
 * @package BookingX\StaffAnalytics
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete database tables.
$tables = array(
	$wpdb->prefix . 'bkx_staff_metrics',
	$wpdb->prefix . 'bkx_staff_goals',
	$wpdb->prefix . 'bkx_staff_reviews',
	$wpdb->prefix . 'bkx_staff_time_logs',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete options.
$options = array(
	'bkx_staff_analytics_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Delete staff meta.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta}
	WHERE meta_key IN ('_bkx_avg_rating', '_bkx_review_count')"
);

// Delete export files.
$upload_dir = wp_upload_dir();
$export_dir = $upload_dir['basedir'] . '/bkx-staff-reports/';

if ( is_dir( $export_dir ) ) {
	$files = glob( $export_dir . '*' );
	foreach ( $files as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
	rmdir( $export_dir );
}

// Remove scheduled cron.
$timestamp = wp_next_scheduled( 'bkx_staff_daily_metrics' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'bkx_staff_daily_metrics' );
}

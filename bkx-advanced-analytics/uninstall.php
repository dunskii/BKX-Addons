<?php
/**
 * Uninstall BookingX Advanced Analytics.
 *
 * @package BookingX\AdvancedAnalytics
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete database tables.
$tables = array(
	$wpdb->prefix . 'bkx_aa_events',
	$wpdb->prefix . 'bkx_aa_cohorts',
	$wpdb->prefix . 'bkx_aa_analyses',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete options.
delete_option( 'bkx_advanced_analytics_version' );

// Clear scheduled hooks.
wp_clear_scheduled_hook( 'bkx_aa_process_cohorts' );

// Delete export files.
$upload_dir = wp_upload_dir();
$export_dir = $upload_dir['basedir'] . '/bkx-aa-exports/';

if ( is_dir( $export_dir ) ) {
	$files = glob( $export_dir . '*' );
	foreach ( $files as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
	rmdir( $export_dir );
}

<?php
/**
 * Uninstall BookingX Marketing ROI Tracker.
 *
 * @package BookingX\MarketingROI
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete database tables.
$tables = array(
	$wpdb->prefix . 'bkx_roi_campaigns',
	$wpdb->prefix . 'bkx_roi_visits',
	$wpdb->prefix . 'bkx_roi_costs',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete options.
delete_option( 'bkx_marketing_roi_version' );

// Delete export files.
$upload_dir = wp_upload_dir();
$export_dir = $upload_dir['basedir'] . '/bkx-roi-exports/';

if ( is_dir( $export_dir ) ) {
	$files = glob( $export_dir . '*' );
	foreach ( $files as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
	rmdir( $export_dir );
}

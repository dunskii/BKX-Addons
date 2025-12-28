<?php
/**
 * Uninstall BookingX Financial Reporting Suite.
 *
 * @package BookingX\FinancialReports
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete database tables.
$tables = array(
	$wpdb->prefix . 'bkx_financial_snapshots',
	$wpdb->prefix . 'bkx_financial_expenses',
	$wpdb->prefix . 'bkx_financial_tax_rates',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete options.
$options = array(
	'bkx_fin_fiscal_year_start',
	'bkx_fin_default_tax_rate',
	'bkx_fin_expense_categories',
	'bkx_financial_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Delete export files.
$upload_dir = wp_upload_dir();
$export_dir = $upload_dir['basedir'] . '/bkx-financial-exports/';

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
$timestamp = wp_next_scheduled( 'bkx_financial_daily_snapshot' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'bkx_financial_daily_snapshot' );
}

<?php
/**
 * Uninstall script for Advanced Reports.
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\AdvancedReports
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data on uninstall.
 */
function bkx_advanced_reports_uninstall() {
	global $wpdb;

	// Only clean up if the option is set.
	$settings = get_option( 'bkx_advanced_reports_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Delete custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_report_snapshots',
		$wpdb->prefix . 'bkx_saved_reports',
		$wpdb->prefix . 'bkx_report_exports',
		$wpdb->prefix . 'bkx_dashboard_widgets',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	// Delete options.
	$options = array(
		'bkx_advanced_reports_settings',
		'bkx_advanced_reports_version',
		'bkx_advanced_reports_license_key',
		'bkx_advanced_reports_license_status',
		'bkx_advanced_reports_license_expires',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete transients.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'%_transient_bkx_report_%',
			'%_transient_timeout_bkx_report_%'
		)
	);

	// Delete user meta.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			'bkx_report_%'
		)
	);

	// Unschedule cron events.
	$cron_hooks = array(
		'bkx_generate_daily_snapshots',
		'bkx_cleanup_old_exports',
		'bkx_send_scheduled_reports',
	);

	foreach ( $cron_hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}

	// Clear scheduled actions if Action Scheduler is available.
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'bkx_generate_daily_snapshots' );
		as_unschedule_all_actions( 'bkx_process_export_queue' );
		as_unschedule_all_actions( 'bkx_send_scheduled_report' );
	}

	// Delete export files.
	$upload_dir  = wp_upload_dir();
	$export_path = $upload_dir['basedir'] . '/bkx-reports/';

	if ( is_dir( $export_path ) ) {
		bkx_advanced_reports_delete_directory( $export_path );
	}

	// Clean up multisite if applicable.
	if ( is_multisite() ) {
		$sites = get_sites( array( 'fields' => 'ids' ) );

		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );

			// Delete site-specific options.
			foreach ( $options as $option ) {
				delete_option( $option );
			}

			// Delete site-specific tables.
			$site_tables = array(
				$wpdb->prefix . 'bkx_report_snapshots',
				$wpdb->prefix . 'bkx_saved_reports',
				$wpdb->prefix . 'bkx_report_exports',
				$wpdb->prefix . 'bkx_dashboard_widgets',
			);

			foreach ( $site_tables as $table ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
			}

			restore_current_blog();
		}
	}
}

/**
 * Recursively delete a directory.
 *
 * @param string $dir Directory path.
 * @return bool True on success, false on failure.
 */
function bkx_advanced_reports_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . DIRECTORY_SEPARATOR . $file;

		if ( is_dir( $path ) ) {
			bkx_advanced_reports_delete_directory( $path );
		} else {
			wp_delete_file( $path );
		}
	}

	return rmdir( $dir );
}

// Run uninstall.
bkx_advanced_reports_uninstall();

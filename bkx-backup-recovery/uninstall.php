<?php
/**
 * Uninstall script for BKX Backup & Recovery.
 *
 * @package BookingX\BackupRecovery
 */

// Exit if not called from WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Clean up all plugin data.
 */
function bkx_backup_recovery_uninstall() {
	global $wpdb;

	// Only delete data if option is set.
	$settings = get_option( 'bkx_backup_recovery_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Delete database tables.
	$tables = array(
		$wpdb->prefix . 'bkx_backup_history',
		$wpdb->prefix . 'bkx_restore_history',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	// Delete options.
	$options = array(
		'bkx_backup_recovery_settings',
		'bkx_backup_recovery_version',
		'bkx_backup_recovery_db_version',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete backup files.
	$upload_dir = wp_upload_dir();
	$backup_dir = $upload_dir['basedir'] . '/bkx-backups/';
	$export_dir = $upload_dir['basedir'] . '/bkx-exports/';

	bkx_backup_delete_directory( $backup_dir );
	bkx_backup_delete_directory( $export_dir );

	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_backup_scheduled_backup' );
	wp_clear_scheduled_hook( 'bkx_backup_cleanup_old_backups' );

	// Delete transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_bkx_backup_%'
		OR option_name LIKE '_transient_timeout_bkx_backup_%'"
	);

	// Clear any cached data.
	wp_cache_flush();
}

/**
 * Recursively delete a directory.
 *
 * @param string $dir Directory path.
 */
function bkx_backup_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . $file;
		if ( is_dir( $path ) ) {
			bkx_backup_delete_directory( $path . '/' );
		} else {
			wp_delete_file( $path );
		}
	}

	rmdir( $dir );
}

// Run uninstall.
bkx_backup_recovery_uninstall();

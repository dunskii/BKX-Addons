<?php
/**
 * Uninstall script for BKX PCI DSS Compliance Tools.
 *
 * @package BookingX\PCICompliance
 */

// Exit if not called from WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Clean up all plugin data.
 */
function bkx_pci_compliance_uninstall() {
	global $wpdb;

	// Only delete data if option is set.
	$settings = get_option( 'bkx_pci_compliance_settings', array() );
	if ( empty( $settings['delete_data_on_uninstall'] ) ) {
		return;
	}

	// Delete database tables.
	$tables = array(
		$wpdb->prefix . 'bkx_pci_audit_log',
		$wpdb->prefix . 'bkx_pci_scan_results',
		$wpdb->prefix . 'bkx_pci_data_access',
		$wpdb->prefix . 'bkx_pci_key_rotation',
		$wpdb->prefix . 'bkx_pci_vulnerabilities',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	// Delete options.
	$options = array(
		'bkx_pci_compliance_settings',
		'bkx_pci_compliance_version',
		'bkx_pci_compliance_db_version',
		'bkx_pci_encryption_key',
		'bkx_pci_tokenization_key',
		'bkx_pci_api_key',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete user meta.
	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_bkx_session_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_bkx_last_activity'" );

	// Delete report files.
	$upload_dir = wp_upload_dir();
	$dirs       = array(
		$upload_dir['basedir'] . '/bkx-pci-exports/',
		$upload_dir['basedir'] . '/bkx-pci-reports/',
	);

	foreach ( $dirs as $dir ) {
		bkx_pci_delete_directory( $dir );
	}

	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_pci_compliance_scan' );
	wp_clear_scheduled_hook( 'bkx_pci_cleanup_logs' );

	// Delete transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE '_transient_bkx_pci_%'
		OR option_name LIKE '_transient_timeout_bkx_pci_%'"
	);

	// Clear any cached data.
	wp_cache_flush();
}

/**
 * Recursively delete a directory.
 *
 * @param string $dir Directory path.
 */
function bkx_pci_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . $file;
		if ( is_dir( $path ) ) {
			bkx_pci_delete_directory( $path . '/' );
		} else {
			wp_delete_file( $path );
		}
	}

	rmdir( $dir );
}

// Run uninstall.
bkx_pci_compliance_uninstall();

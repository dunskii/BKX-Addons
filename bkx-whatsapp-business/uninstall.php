<?php
/**
 * Uninstall WhatsApp Business Add-on.
 *
 * @package BookingX\WhatsAppBusiness
 * @since   1.0.0
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data.
 *
 * @since 1.0.0
 */
function bkx_whatsapp_business_uninstall() {
	global $wpdb;

	// Check if we should preserve data.
	$settings = get_option( 'bkx_whatsapp_business_settings', array() );
	if ( ! empty( $settings['preserve_data_on_uninstall'] ) ) {
		return;
	}

	// Drop custom tables.
	$tables = array(
		$wpdb->prefix . 'bkx_whatsapp_messages',
		$wpdb->prefix . 'bkx_whatsapp_conversations',
		$wpdb->prefix . 'bkx_whatsapp_templates',
		$wpdb->prefix . 'bkx_whatsapp_quick_replies',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// Delete options.
	$options = array(
		'bkx_whatsapp_business_settings',
		'bkx_whatsapp_business_version',
		'bkx_whatsapp_business_db_version',
		'bkx_whatsapp_business_license_key',
		'bkx_whatsapp_business_license_status',
		'bkx_whatsapp_business_license_expires',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete post meta.
	$wpdb->query(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bkx_whatsapp_%'"
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// Delete transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_whatsapp_%' OR option_name LIKE '_transient_timeout_bkx_whatsapp_%'"
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// Clear scheduled cron events.
	wp_clear_scheduled_hook( 'bkx_whatsapp_send_reminders' );
	wp_clear_scheduled_hook( 'bkx_whatsapp_cleanup_old_messages' );

	// Delete log files.
	$upload_dir = wp_upload_dir();
	$log_dir    = $upload_dir['basedir'] . '/bkx-whatsapp-logs';

	if ( is_dir( $log_dir ) ) {
		bkx_whatsapp_delete_directory( $log_dir );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}

/**
 * Recursively delete a directory.
 *
 * @since 1.0.0
 *
 * @param string $dir Directory path.
 * @return bool True on success.
 */
function bkx_whatsapp_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . '/' . $file;

		if ( is_dir( $path ) ) {
			bkx_whatsapp_delete_directory( $path );
		} else {
			wp_delete_file( $path );
		}
	}

	return rmdir( $dir );
}

// Run uninstall.
bkx_whatsapp_business_uninstall();

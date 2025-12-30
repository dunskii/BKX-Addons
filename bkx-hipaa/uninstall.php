<?php
/**
 * HIPAA Compliance Uninstall
 *
 * @package BookingX\HIPAA
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete options.
delete_option( 'bkx_hipaa_settings' );
delete_option( 'bkx_hipaa_encryption_key' );

// Delete user meta.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = '_bkx_hipaa_last_activity'" );

// Drop tables.
$tables = array(
	$wpdb->prefix . 'bkx_hipaa_audit_log',
	$wpdb->prefix . 'bkx_hipaa_baa',
	$wpdb->prefix . 'bkx_hipaa_access_control',
);

foreach ( $tables as $table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
}

// Clear scheduled events.
wp_clear_scheduled_hook( 'bkx_hipaa_audit_cleanup' );
wp_clear_scheduled_hook( 'bkx_hipaa_access_review' );
wp_clear_scheduled_hook( 'bkx_hipaa_baa_expiry_check' );

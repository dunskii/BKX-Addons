<?php
/**
 * Mobile Optimization Uninstall
 *
 * @package BookingX\MobileOptimize
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove options.
delete_option( 'bkx_mobile_optimize_settings' );
delete_option( 'bkx_mobile_optimize_version' );

// Remove transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_mobile_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_mobile_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

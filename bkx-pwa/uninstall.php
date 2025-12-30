<?php
/**
 * PWA Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\PWA
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove options.
$options_to_delete = array(
	'bkx_pwa_settings',
	'bkx_pwa_version',
	'bkx_pwa_cache_version',
	'bkx_pwa_install_stats',
	'bkx_pwa_license_key',
	'bkx_pwa_license_status',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// Remove transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_pwa_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_pwa_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Flush rewrite rules.
flush_rewrite_rules();

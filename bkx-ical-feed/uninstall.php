<?php
/**
 * Uninstall script for iCal Feed Export
 *
 * @package BookingX\ICalFeed
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'bkx_ical_feed_settings' );
delete_option( 'bkx_ical_feed_token' );
delete_option( 'bkx_ical_feed_license_key' );
delete_option( 'bkx_ical_feed_license_status' );

// Delete user meta.
global $wpdb;
$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => '_bkx_ical_token' ) );

// Delete post meta.
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_bkx_ical_token' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_ical_sequence' ) );

// Flush rewrite rules.
flush_rewrite_rules();

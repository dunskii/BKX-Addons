<?php
/**
 * Uninstall script for Yoast SEO Integration.
 *
 * @package BookingX\YoastSeo
 * @since   1.0.0
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check user has permission.
if ( ! current_user_can( 'delete_plugins' ) ) {
	exit;
}

/**
 * Delete plugin data on uninstall.
 */
function bkx_yoast_uninstall() {
	// Delete options.
	$options = array(
		'bkx_yoast_settings',
		'bkx_yoast_license_key',
		'bkx_yoast_license_status',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete post meta related to SEO status tracking.
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_bkx_last_status'" );

	// Flush rewrite rules.
	flush_rewrite_rules();
}

// Run uninstall.
bkx_yoast_uninstall();

<?php
/**
 * Uninstall script for BKX White Label Solution.
 *
 * @package BookingX\WhiteLabel
 */

// Exit if not called from WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data.
 */
function bkx_white_label_uninstall() {
	// Delete options.
	$options = array(
		'bkx_white_label_settings',
		'bkx_white_label_version',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete transients.
	delete_transient( 'bkx_white_label_custom_css' );

	// Clear any cached data.
	wp_cache_flush();
}

// Run uninstall.
bkx_white_label_uninstall();

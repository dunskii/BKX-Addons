<?php
/**
 * BookingX Elementor Integration Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\Elementor
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data on uninstall.
 */
function bkx_elementor_uninstall() {
	// Only clean up if user has opted in or if complete removal is requested.
	$settings = get_option( 'bkx_elementor_settings', array() );
	$remove_data = isset( $settings['remove_data_on_uninstall'] ) ? $settings['remove_data_on_uninstall'] : false;

	if ( ! $remove_data ) {
		return;
	}

	// Delete plugin options.
	delete_option( 'bkx_elementor_settings' );
	delete_option( 'bkx_elementor_version' );

	// Delete transients.
	delete_transient( 'bkx_elementor_services_cache' );
	delete_transient( 'bkx_elementor_seats_cache' );

	// Clean up any orphaned Elementor data.
	global $wpdb;

	// Remove Elementor-specific meta that we may have added.
	$wpdb->query(
		"DELETE FROM {$wpdb->postmeta}
		WHERE meta_key LIKE '_elementor_bkx_%'"
	);

	// Clear any cached Elementor CSS that may contain our styles.
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
}

bkx_elementor_uninstall();

<?php
/**
 * BookingX Gravity Forms Integration Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package BookingX\GravityForms
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data on uninstall.
 */
function bkx_gravity_forms_uninstall() {
	// Only clean up if user has opted in or if complete removal is requested.
	$settings = get_option( 'bkx_gravity_forms_settings', array() );
	$remove_data = isset( $settings['remove_data_on_uninstall'] ) ? $settings['remove_data_on_uninstall'] : false;

	if ( ! $remove_data ) {
		return;
	}

	// Delete plugin options.
	delete_option( 'bkx_gravity_forms_settings' );
	delete_option( 'bkx_gravity_forms_version' );

	// Clean up entry meta.
	if ( class_exists( 'GFAPI' ) ) {
		global $wpdb;

		// Delete BookingX-related entry meta.
		$wpdb->query(
			"DELETE FROM {$wpdb->prefix}gf_entry_meta
			WHERE meta_key LIKE 'bkx_%'"
		);
	}
}

bkx_gravity_forms_uninstall();

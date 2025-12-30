<?php
/**
 * Divi Integration Uninstall
 *
 * @package BookingX\Divi
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'bkx_divi_settings' );

// Clear Divi cache.
if ( function_exists( 'et_core_clear_transients' ) ) {
	et_core_clear_transients();
}

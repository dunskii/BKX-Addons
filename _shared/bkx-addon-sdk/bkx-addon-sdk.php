<?php
/**
 * BookingX Add-on SDK
 *
 * SDK for building BookingX add-ons with standardized architecture,
 * common utilities, and integration with the BookingX Framework.
 *
 * @package    BookingX\AddonSDK
 * @version    1.0.0
 * @author     Booking X
 * @license    GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

// SDK Version
define( 'BKX_ADDON_SDK_VERSION', '1.0.0' );
define( 'BKX_ADDON_SDK_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_ADDON_SDK_URL', plugin_dir_url( __FILE__ ) );

// Autoloader
require_once BKX_ADDON_SDK_PATH . 'autoload.php';

/**
 * Initialize the SDK.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_addon_sdk_init(): void {
    // Ensure BookingX is loaded first
    if ( ! function_exists( 'BKX' ) ) {
        return;
    }

    // Fire SDK loaded action
    do_action( 'bkx_addon_sdk_loaded', BKX_ADDON_SDK_VERSION );
}
add_action( 'plugins_loaded', 'bkx_addon_sdk_init', 5 );

/**
 * Get SDK version.
 *
 * @since 1.0.0
 * @return string
 */
function bkx_addon_sdk_version(): string {
    return BKX_ADDON_SDK_VERSION;
}

/**
 * Check if SDK meets minimum version requirement.
 *
 * @since 1.0.0
 * @param string $min_version Minimum required version.
 * @return bool
 */
function bkx_addon_sdk_meets_requirement( string $min_version ): bool {
    return version_compare( BKX_ADDON_SDK_VERSION, $min_version, '>=' );
}

<?php
/**
 * PSR-4 Autoloader for BookingX Add-on SDK
 *
 * @package BookingX\AddonSDK
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register( function ( $class ) {
    // Base namespace
    $namespace = 'BookingX\\AddonSDK\\';

    // Check if the class uses our namespace
    if ( strpos( $class, $namespace ) !== 0 ) {
        return;
    }

    // Get the relative class name
    $relative_class = substr( $class, strlen( $namespace ) );

    // Replace namespace separators with directory separators
    $file = BKX_ADDON_SDK_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

    // If the file exists, require it
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

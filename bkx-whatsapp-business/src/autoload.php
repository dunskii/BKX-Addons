<?php
/**
 * Autoloader for WhatsApp Business Integration.
 *
 * @package BookingX\WhatsAppBusiness
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	function ( $class ) {
		$prefix = 'BookingX\\WhatsAppBusiness\\';

		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file = BKX_WHATSAPP_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

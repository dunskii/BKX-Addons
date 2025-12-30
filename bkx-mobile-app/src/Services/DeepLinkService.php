<?php
/**
 * Deep Link Service for Mobile App Framework.
 *
 * @package BookingX\MobileApp\Services
 */

namespace BookingX\MobileApp\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DeepLinkService class.
 */
class DeepLinkService {

	/**
	 * Generate booking deep link.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	public function generate_booking_link( $booking_id ) {
		$addon  = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$scheme = $addon->get_setting( 'deep_link_scheme', 'bookingx' );

		return sprintf( '%s://bookings/%d', $scheme, $booking_id );
	}

	/**
	 * Generate service deep link.
	 *
	 * @param int $service_id Service ID.
	 * @return string
	 */
	public function generate_service_link( $service_id ) {
		$addon  = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$scheme = $addon->get_setting( 'deep_link_scheme', 'bookingx' );

		return sprintf( '%s://services/%d', $scheme, $service_id );
	}

	/**
	 * Generate resource deep link.
	 *
	 * @param int $resource_id Resource ID.
	 * @return string
	 */
	public function generate_resource_link( $resource_id ) {
		$addon  = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$scheme = $addon->get_setting( 'deep_link_scheme', 'bookingx' );

		return sprintf( '%s://resources/%d', $scheme, $resource_id );
	}

	/**
	 * Generate book now deep link.
	 *
	 * @param int|null $service_id  Service ID.
	 * @param int|null $resource_id Resource ID.
	 * @param string   $date        Date.
	 * @return string
	 */
	public function generate_book_link( $service_id = null, $resource_id = null, $date = '' ) {
		$addon  = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$scheme = $addon->get_setting( 'deep_link_scheme', 'bookingx' );

		$params = array();

		if ( $service_id ) {
			$params[] = 'service_id=' . $service_id;
		}

		if ( $resource_id ) {
			$params[] = 'resource_id=' . $resource_id;
		}

		if ( $date ) {
			$params[] = 'date=' . $date;
		}

		$query = ! empty( $params ) ? '?' . implode( '&', $params ) : '';

		return sprintf( '%s://book%s', $scheme, $query );
	}

	/**
	 * Generate universal link.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public function generate_universal_link( $path ) {
		return home_url( '/app/' . ltrim( $path, '/' ) );
	}

	/**
	 * Parse deep link.
	 *
	 * @param string $url Deep link URL.
	 * @return array|false
	 */
	public function parse_deep_link( $url ) {
		$addon  = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$scheme = $addon->get_setting( 'deep_link_scheme', 'bookingx' );

		// Check if it's our scheme.
		if ( strpos( $url, $scheme . '://' ) !== 0 ) {
			return false;
		}

		$path = substr( $url, strlen( $scheme . '://' ) );
		$parts = explode( '?', $path );
		$route = trim( $parts[0], '/' );
		$query_string = isset( $parts[1] ) ? $parts[1] : '';

		parse_str( $query_string, $params );

		$segments = explode( '/', $route );

		return array(
			'route'    => $route,
			'type'     => isset( $segments[0] ) ? $segments[0] : '',
			'id'       => isset( $segments[1] ) ? (int) $segments[1] : 0,
			'params'   => $params,
			'segments' => $segments,
		);
	}

	/**
	 * Get app links association file content.
	 *
	 * @return array
	 */
	public function get_apple_app_site_association() {
		$addon     = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$bundle_id = $addon->get_setting( 'apns_bundle_id', '' );
		$team_id   = $addon->get_setting( 'apns_team_id', '' );

		return array(
			'applinks' => array(
				'apps'    => array(),
				'details' => array(
					array(
						'appID' => $team_id . '.' . $bundle_id,
						'paths' => array(
							'/app/*',
							'/booking/*',
						),
					),
				),
			),
		);
	}

	/**
	 * Get Android asset links content.
	 *
	 * @param string $package_name Package name.
	 * @param string $sha256_cert  SHA256 certificate fingerprint.
	 * @return array
	 */
	public function get_android_asset_links( $package_name, $sha256_cert ) {
		return array(
			array(
				'relation' => array( 'delegate_permission/common.handle_all_urls' ),
				'target'   => array(
					'namespace'                => 'android_app',
					'package_name'             => $package_name,
					'sha256_cert_fingerprints' => array( $sha256_cert ),
				),
			),
		);
	}
}

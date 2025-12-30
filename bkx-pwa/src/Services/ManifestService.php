<?php
/**
 * Web App Manifest Service.
 *
 * @package BookingX\PWA\Services
 */

namespace BookingX\PWA\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ManifestService class.
 */
class ManifestService {

	/**
	 * Get manifest data.
	 *
	 * @return array
	 */
	public function get_manifest() {
		$addon    = \BookingX\PWA\PWAAddon::get_instance();
		$settings = get_option( 'bkx_pwa_settings', array() );

		$manifest = array(
			'name'             => $addon->get_setting( 'app_name', get_bloginfo( 'name' ) ),
			'short_name'       => $addon->get_setting( 'app_short_name', substr( get_bloginfo( 'name' ), 0, 12 ) ),
			'description'      => $addon->get_setting( 'app_description', get_bloginfo( 'description' ) ),
			'start_url'        => $addon->get_setting( 'start_url', '/' ) . '?utm_source=pwa',
			'scope'            => '/',
			'display'          => $addon->get_setting( 'display', 'standalone' ),
			'orientation'      => $addon->get_setting( 'orientation', 'any' ),
			'theme_color'      => $addon->get_setting( 'theme_color', '#2563eb' ),
			'background_color' => $addon->get_setting( 'background_color', '#ffffff' ),
			'lang'             => get_locale(),
			'dir'              => is_rtl() ? 'rtl' : 'ltr',
			'categories'       => array( 'business', 'lifestyle', 'productivity' ),
			'icons'            => $this->get_icons(),
			'screenshots'      => $this->get_screenshots(),
			'shortcuts'        => $this->get_shortcuts(),
			'related_applications' => $this->get_related_apps(),
			'prefer_related_applications' => false,
		);

		// Add share target if supported.
		$manifest['share_target'] = array(
			'action'  => home_url( '/book/' ),
			'method'  => 'GET',
			'params'  => array(
				'title' => 'title',
				'text'  => 'text',
				'url'   => 'url',
			),
		);

		return rest_ensure_response( $manifest );
	}

	/**
	 * Serve manifest.json file.
	 */
	public function serve_manifest() {
		$manifest = $this->get_manifest();

		header( 'Content-Type: application/manifest+json' );
		header( 'Cache-Control: public, max-age=86400' );

		echo wp_json_encode( $manifest->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Get icons array.
	 *
	 * @return array
	 */
	private function get_icons() {
		$addon = \BookingX\PWA\PWAAddon::get_instance();
		$icons = array();

		$icon_sizes = array(
			'72'  => 'icon_72',
			'96'  => 'icon_96',
			'128' => 'icon_128',
			'144' => 'icon_144',
			'152' => 'icon_152',
			'192' => 'icon_192',
			'384' => 'icon_384',
			'512' => 'icon_512',
		);

		foreach ( $icon_sizes as $size => $setting ) {
			$icon_url = $addon->get_setting( $setting );
			if ( $icon_url ) {
				$icons[] = array(
					'src'     => $icon_url,
					'sizes'   => "{$size}x{$size}",
					'type'    => 'image/png',
					'purpose' => 'any maskable',
				);
			}
		}

		// Fallback to site icon.
		if ( empty( $icons ) ) {
			$site_icon = get_site_icon_url( 512 );
			if ( $site_icon ) {
				$default_sizes = array( 72, 96, 128, 144, 152, 192, 384, 512 );
				foreach ( $default_sizes as $size ) {
					$icons[] = array(
						'src'     => get_site_icon_url( $size ),
						'sizes'   => "{$size}x{$size}",
						'type'    => 'image/png',
						'purpose' => 'any',
					);
				}
			}
		}

		return $icons;
	}

	/**
	 * Get screenshots.
	 *
	 * @return array
	 */
	private function get_screenshots() {
		$addon       = \BookingX\PWA\PWAAddon::get_instance();
		$screenshots = array();

		$screenshot_settings = array(
			'screenshot_wide'   => array( '1280x720', 'wide' ),
			'screenshot_narrow' => array( '720x1280', 'narrow' ),
		);

		foreach ( $screenshot_settings as $setting => $info ) {
			$url = $addon->get_setting( $setting );
			if ( $url ) {
				$screenshots[] = array(
					'src'        => $url,
					'sizes'      => $info[0],
					'type'       => 'image/png',
					'form_factor' => $info[1],
					'label'      => __( 'BookingX Booking Interface', 'bkx-pwa' ),
				);
			}
		}

		return $screenshots;
	}

	/**
	 * Get shortcuts.
	 *
	 * @return array
	 */
	private function get_shortcuts() {
		$shortcuts = array(
			array(
				'name'        => __( 'Book Now', 'bkx-pwa' ),
				'short_name'  => __( 'Book', 'bkx-pwa' ),
				'description' => __( 'Start a new booking', 'bkx-pwa' ),
				'url'         => home_url( '/book/' ),
				'icons'       => array(
					array(
						'src'   => BKX_PWA_PLUGIN_URL . 'assets/icons/shortcut-book.png',
						'sizes' => '96x96',
					),
				),
			),
			array(
				'name'        => __( 'My Bookings', 'bkx-pwa' ),
				'short_name'  => __( 'Bookings', 'bkx-pwa' ),
				'description' => __( 'View your bookings', 'bkx-pwa' ),
				'url'         => home_url( '/my-account/bookings/' ),
				'icons'       => array(
					array(
						'src'   => BKX_PWA_PLUGIN_URL . 'assets/icons/shortcut-bookings.png',
						'sizes' => '96x96',
					),
				),
			),
		);

		return apply_filters( 'bkx_pwa_manifest_shortcuts', $shortcuts );
	}

	/**
	 * Get related applications.
	 *
	 * @return array
	 */
	private function get_related_apps() {
		$addon = \BookingX\PWA\PWAAddon::get_instance();
		$apps  = array();

		$play_store_url = $addon->get_setting( 'play_store_url' );
		if ( $play_store_url ) {
			$apps[] = array(
				'platform' => 'play',
				'url'      => $play_store_url,
			);
		}

		$app_store_url = $addon->get_setting( 'app_store_url' );
		if ( $app_store_url ) {
			$apps[] = array(
				'platform' => 'itunes',
				'url'      => $app_store_url,
			);
		}

		return $apps;
	}
}

<?php
/**
 * Cache Manager Service.
 *
 * @package BookingX\PWA\Services
 */

namespace BookingX\PWA\Services;

defined( 'ABSPATH' ) || exit;

/**
 * CacheManager class.
 */
class CacheManager {

	/**
	 * Get precache URLs.
	 *
	 * @return array
	 */
	public function get_precache_urls() {
		$addon = \BookingX\PWA\PWAAddon::get_instance();

		$urls = array(
			home_url( '/' ),
			$addon->get_setting( 'offline_page' ) ?: home_url( '/offline/' ),
		);

		// Add custom precache pages.
		$custom_pages = $addon->get_setting( 'precache_pages', array() );
		if ( ! empty( $custom_pages ) ) {
			$urls = array_merge( $urls, $custom_pages );
		}

		// Add booking pages.
		$booking_page = get_option( 'bkx_booking_page' );
		if ( $booking_page ) {
			$urls[] = get_permalink( $booking_page );
		}

		// Add service listing pages.
		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => 20,
				'post_status'    => 'publish',
			)
		);

		foreach ( $services as $service ) {
			$urls[] = get_permalink( $service->ID );
		}

		// Add essential assets.
		$urls = array_merge( $urls, $this->get_essential_assets() );

		// Filter and deduplicate.
		$urls = array_unique( array_filter( $urls ) );

		return apply_filters( 'bkx_pwa_precache_urls', $urls );
	}

	/**
	 * Get essential assets to cache.
	 *
	 * @return array
	 */
	private function get_essential_assets() {
		$assets = array();

		// PWA assets.
		$assets[] = BKX_PWA_PLUGIN_URL . 'assets/css/offline.css';
		$assets[] = BKX_PWA_PLUGIN_URL . 'assets/js/pwa-app.js';

		// Icons.
		$addon = \BookingX\PWA\PWAAddon::get_instance();
		$icon_settings = array( 'icon_192', 'icon_512' );
		foreach ( $icon_settings as $setting ) {
			$icon = $addon->get_setting( $setting );
			if ( $icon ) {
				$assets[] = $icon;
			}
		}

		// Site icon fallback.
		$site_icon = get_site_icon_url( 192 );
		if ( $site_icon ) {
			$assets[] = $site_icon;
		}

		return $assets;
	}

	/**
	 * Get cache URLs for REST API.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_cache_urls() {
		return rest_ensure_response(
			array(
				'precache' => $this->get_precache_urls(),
				'runtime'  => $this->get_runtime_cache_config(),
			)
		);
	}

	/**
	 * Get runtime cache configuration.
	 *
	 * @return array
	 */
	private function get_runtime_cache_config() {
		return array(
			array(
				'urlPattern' => '/wp-content/uploads/.*',
				'handler'    => 'CacheFirst',
				'options'    => array(
					'cacheName'       => 'images',
					'expiration'      => array(
						'maxEntries'    => 50,
						'maxAgeSeconds' => 86400 * 30,
					),
				),
			),
			array(
				'urlPattern' => '/wp-json/bkx/.*',
				'handler'    => 'NetworkFirst',
				'options'    => array(
					'cacheName'          => 'api-responses',
					'networkTimeoutSeconds' => 10,
				),
			),
			array(
				'urlPattern' => '.*\\.(?:css|js)$',
				'handler'    => 'StaleWhileRevalidate',
				'options'    => array(
					'cacheName' => 'static-assets',
				),
			),
		);
	}

	/**
	 * Clear all PWA caches.
	 *
	 * @return bool
	 */
	public function clear_cache() {
		// This would typically be handled client-side via service worker
		// But we can set a flag to trigger cache invalidation.
		update_option( 'bkx_pwa_cache_version', time() );
		return true;
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array
	 */
	public function get_cache_stats() {
		// Statistics are primarily client-side.
		// This provides server-side metrics.
		return array(
			'precache_count'  => count( $this->get_precache_urls() ),
			'cache_version'   => get_option( 'bkx_pwa_cache_version', BKX_PWA_VERSION ),
			'last_updated'    => get_option( 'bkx_pwa_cache_updated', '' ),
		);
	}
}

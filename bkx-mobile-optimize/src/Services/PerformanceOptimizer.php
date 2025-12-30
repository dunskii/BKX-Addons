<?php
/**
 * Performance Optimization Service.
 *
 * @package BookingX\MobileOptimize\Services
 */

namespace BookingX\MobileOptimize\Services;

defined( 'ABSPATH' ) || exit;

/**
 * PerformanceOptimizer class.
 */
class PerformanceOptimizer {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		$addon = \BookingX\MobileOptimize\MobileOptimizeAddon::get_instance();

		// Lazy load images.
		if ( $addon->get_setting( 'lazy_load_images', true ) ) {
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'add_lazy_loading' ), 10, 2 );
		}

		// Defer non-critical CSS.
		add_action( 'wp_head', array( $this, 'add_critical_css' ), 1 );

		// Prefetch resources.
		add_action( 'wp_head', array( $this, 'add_resource_hints' ), 2 );
	}

	/**
	 * Add lazy loading to images.
	 *
	 * @param array $attributes Image attributes.
	 * @param mixed $attachment Attachment object or ID.
	 * @return array
	 */
	public function add_lazy_loading( $attributes, $attachment ) {
		$detector = \BookingX\MobileOptimize\MobileOptimizeAddon::get_instance()->get_service( 'device_detector' );

		if ( ! $detector->is_mobile() && ! $detector->is_tablet() ) {
			return $attributes;
		}

		$attributes['loading'] = 'lazy';
		$attributes['decoding'] = 'async';

		return $attributes;
	}

	/**
	 * Add critical CSS inline.
	 */
	public function add_critical_css() {
		$detector = \BookingX\MobileOptimize\MobileOptimizeAddon::get_instance()->get_service( 'device_detector' );

		if ( ! $detector->is_mobile() && ! $detector->is_tablet() ) {
			return;
		}

		$critical_css = $this->get_critical_css();
		if ( $critical_css ) {
			echo '<style id="bkx-critical-css">' . $critical_css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Get critical CSS for mobile.
	 *
	 * @return string
	 */
	private function get_critical_css() {
		return '
			/* Critical Mobile Styles */
			.bkx-booking-form { opacity: 1; }
			.bkx-loading { display: flex; align-items: center; justify-content: center; }
			.bkx-skeleton { animation: bkx-pulse 1.5s ease-in-out infinite; }
			@keyframes bkx-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
		';
	}

	/**
	 * Add resource hints for performance.
	 */
	public function add_resource_hints() {
		$detector = \BookingX\MobileOptimize\MobileOptimizeAddon::get_instance()->get_service( 'device_detector' );

		if ( ! $detector->is_mobile() && ! $detector->is_tablet() ) {
			return;
		}

		// DNS prefetch.
		$dns_prefetch = array(
			'fonts.googleapis.com',
			'fonts.gstatic.com',
		);

		foreach ( $dns_prefetch as $domain ) {
			echo '<link rel="dns-prefetch" href="//' . esc_attr( $domain ) . '">' . "\n";
		}

		// Preconnect to critical origins.
		echo '<link rel="preconnect" href="' . esc_url( site_url() ) . '" crossorigin>' . "\n";
	}

	/**
	 * Get optimized image srcset for mobile.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public function get_mobile_srcset( $attachment_id ) {
		$sizes = array(
			'thumbnail' => '150w',
			'medium'    => '300w',
			'large'     => '768w',
		);

		$srcset_parts = array();

		foreach ( $sizes as $size => $descriptor ) {
			$image = wp_get_attachment_image_src( $attachment_id, $size );
			if ( $image ) {
				$srcset_parts[] = $image[0] . ' ' . $descriptor;
			}
		}

		return implode( ', ', $srcset_parts );
	}

	/**
	 * Get performance metrics.
	 *
	 * @return array
	 */
	public function get_metrics() {
		return array(
			'critical_css_size' => strlen( $this->get_critical_css() ),
			'lazy_loading'      => true,
			'resource_hints'    => true,
		);
	}

	/**
	 * Optimize script loading.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script source.
	 * @return string
	 */
	public function optimize_script_loading( $tag, $handle, $src ) {
		// Add async/defer to non-critical scripts.
		$async_scripts = array(
			'bkx-mobile-gestures',
		);

		$defer_scripts = array(
			'bkx-mobile-optimize',
		);

		if ( in_array( $handle, $async_scripts, true ) ) {
			return str_replace( ' src=', ' async src=', $tag );
		}

		if ( in_array( $handle, $defer_scripts, true ) ) {
			return str_replace( ' src=', ' defer src=', $tag );
		}

		return $tag;
	}

	/**
	 * Get connection-aware configuration.
	 *
	 * @return array
	 */
	public function get_connection_config() {
		return array(
			'save_data'       => true, // Respect Save-Data header.
			'slow_connection' => array(
				'disable_animations'  => true,
				'lower_image_quality' => true,
				'disable_videos'      => true,
			),
		);
	}
}

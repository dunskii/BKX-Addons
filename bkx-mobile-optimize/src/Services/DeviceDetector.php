<?php
/**
 * Device Detection Service.
 *
 * @package BookingX\MobileOptimize\Services
 */

namespace BookingX\MobileOptimize\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DeviceDetector class.
 */
class DeviceDetector {

	/**
	 * User agent.
	 *
	 * @var string
	 */
	private $user_agent;

	/**
	 * Detection cache.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}

	/**
	 * Check if mobile device.
	 *
	 * @return bool
	 */
	public function is_mobile() {
		if ( isset( $this->cache['is_mobile'] ) ) {
			return $this->cache['is_mobile'];
		}

		$mobile_patterns = array(
			'iphone',
			'ipod',
			'android.*mobile',
			'windows.*phone',
			'blackberry',
			'bb10',
			'opera mini',
			'opera mobi',
			'mobile',
		);

		$pattern = '/' . implode( '|', $mobile_patterns ) . '/i';
		$result  = (bool) preg_match( $pattern, $this->user_agent );

		// Exclude tablets.
		if ( $result && $this->is_tablet() ) {
			$result = false;
		}

		$this->cache['is_mobile'] = $result;
		return $result;
	}

	/**
	 * Check if tablet device.
	 *
	 * @return bool
	 */
	public function is_tablet() {
		if ( isset( $this->cache['is_tablet'] ) ) {
			return $this->cache['is_tablet'];
		}

		$tablet_patterns = array(
			'ipad',
			'android(?!.*mobile)',
			'tablet',
			'kindle',
			'silk',
			'playbook',
		);

		$pattern = '/' . implode( '|', $tablet_patterns ) . '/i';
		$result  = (bool) preg_match( $pattern, $this->user_agent );

		$this->cache['is_tablet'] = $result;
		return $result;
	}

	/**
	 * Check if iOS device.
	 *
	 * @return bool
	 */
	public function is_ios() {
		if ( isset( $this->cache['is_ios'] ) ) {
			return $this->cache['is_ios'];
		}

		$result = (bool) preg_match( '/iphone|ipad|ipod/i', $this->user_agent );

		$this->cache['is_ios'] = $result;
		return $result;
	}

	/**
	 * Check if Android device.
	 *
	 * @return bool
	 */
	public function is_android() {
		if ( isset( $this->cache['is_android'] ) ) {
			return $this->cache['is_android'];
		}

		$result = (bool) preg_match( '/android/i', $this->user_agent );

		$this->cache['is_android'] = $result;
		return $result;
	}

	/**
	 * Check if touch device.
	 *
	 * @return bool
	 */
	public function is_touch_device() {
		return $this->is_mobile() || $this->is_tablet();
	}

	/**
	 * Get device type.
	 *
	 * @return string
	 */
	public function get_device_type() {
		if ( $this->is_mobile() ) {
			return 'mobile';
		}

		if ( $this->is_tablet() ) {
			return 'tablet';
		}

		return 'desktop';
	}

	/**
	 * Get operating system.
	 *
	 * @return string
	 */
	public function get_os() {
		if ( $this->is_ios() ) {
			return 'ios';
		}

		if ( $this->is_android() ) {
			return 'android';
		}

		if ( preg_match( '/windows/i', $this->user_agent ) ) {
			return 'windows';
		}

		if ( preg_match( '/mac/i', $this->user_agent ) ) {
			return 'macos';
		}

		if ( preg_match( '/linux/i', $this->user_agent ) ) {
			return 'linux';
		}

		return 'unknown';
	}

	/**
	 * Get browser info.
	 *
	 * @return array
	 */
	public function get_browser() {
		$browsers = array(
			'Chrome'  => '/chrome/i',
			'Safari'  => '/safari/i',
			'Firefox' => '/firefox/i',
			'Edge'    => '/edg/i',
			'Opera'   => '/opera|opr/i',
			'IE'      => '/msie|trident/i',
		);

		foreach ( $browsers as $name => $pattern ) {
			if ( preg_match( $pattern, $this->user_agent ) ) {
				return array(
					'name'      => $name,
					'is_mobile' => $this->is_mobile(),
				);
			}
		}

		return array(
			'name'      => 'Unknown',
			'is_mobile' => $this->is_mobile(),
		);
	}

	/**
	 * Check if PWA mode.
	 *
	 * @return bool
	 */
	public function is_pwa_mode() {
		// Check for display-mode: standalone.
		// This is typically detected via JavaScript and passed as a parameter.
		return isset( $_GET['pwa'] ) || isset( $_GET['utm_source'] ) && 'pwa' === $_GET['utm_source'];
	}

	/**
	 * Get device capabilities.
	 *
	 * @return array
	 */
	public function get_capabilities() {
		return array(
			'touch'          => $this->is_touch_device(),
			'geolocation'    => true, // Assume modern browsers support.
			'notifications'  => true,
			'camera'         => $this->is_mobile() || $this->is_tablet(),
			'vibration'      => $this->is_mobile(),
			'offline'        => true,
			'share'          => true,
		);
	}
}

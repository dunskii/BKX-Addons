<?php
/**
 * Touch Optimization Service.
 *
 * @package BookingX\MobileOptimize\Services
 */

namespace BookingX\MobileOptimize\Services;

defined( 'ABSPATH' ) || exit;

/**
 * TouchOptimizer class.
 */
class TouchOptimizer {

	/**
	 * Minimum touch target size (px).
	 *
	 * @var int
	 */
	const MIN_TOUCH_TARGET = 44;

	/**
	 * Get touch-optimized CSS.
	 *
	 * @return string
	 */
	public function get_touch_css() {
		$min_size = self::MIN_TOUCH_TARGET;

		return "
			/* Touch-optimized buttons */
			.bkx-mobile-form button,
			.bkx-mobile-form .button,
			.bkx-mobile-form input[type='submit'] {
				min-height: {$min_size}px;
				min-width: {$min_size}px;
				padding: 12px 24px;
				font-size: 16px;
				touch-action: manipulation;
			}

			/* Touch-optimized inputs */
			.bkx-mobile-form input,
			.bkx-mobile-form select,
			.bkx-mobile-form textarea {
				min-height: {$min_size}px;
				font-size: 16px; /* Prevents iOS zoom */
				padding: 12px;
				touch-action: manipulation;
			}

			/* Touch-optimized links */
			.bkx-mobile-form a {
				min-height: {$min_size}px;
				display: inline-flex;
				align-items: center;
			}

			/* Increase spacing for touch */
			.bkx-mobile-form .form-row {
				margin-bottom: 16px;
			}

			/* Remove hover states on touch */
			@media (hover: none) {
				.bkx-mobile-form button:hover,
				.bkx-mobile-form .button:hover {
					background: inherit;
				}
			}

			/* Active states for touch feedback */
			.bkx-mobile-form button:active,
			.bkx-mobile-form .button:active {
				transform: scale(0.98);
				opacity: 0.9;
			}
		";
	}

	/**
	 * Get touch gesture configuration.
	 *
	 * @return array
	 */
	public function get_gesture_config() {
		return array(
			'swipe_threshold'   => 50,
			'swipe_velocity'    => 0.3,
			'tap_delay'         => 300,
			'double_tap_delay'  => 500,
			'long_press_delay'  => 500,
			'passive_events'    => true,
		);
	}

	/**
	 * Get swipe calendar configuration.
	 *
	 * @return array
	 */
	public function get_swipe_calendar_config() {
		return array(
			'enabled'          => true,
			'direction'        => 'horizontal',
			'threshold'        => 50,
			'animation'        => 'slide',
			'animation_duration' => 300,
			'resistance'       => 0.8,
			'snap'             => true,
		);
	}

	/**
	 * Get haptic feedback configuration.
	 *
	 * @return array
	 */
	public function get_haptic_config() {
		$addon = \BookingX\MobileOptimize\MobileOptimizeAddon::get_instance();

		return array(
			'enabled'     => $addon->get_setting( 'haptic_feedback', true ),
			'on_tap'      => 'light',
			'on_success'  => 'success',
			'on_error'    => 'error',
			'on_warning'  => 'warning',
			'on_select'   => 'selection',
		);
	}

	/**
	 * Get pull to refresh configuration.
	 *
	 * @return array
	 */
	public function get_pull_to_refresh_config() {
		return array(
			'enabled'        => true,
			'threshold'      => 60,
			'max_distance'   => 120,
			'resistance'     => 2.5,
			'animation'      => 'bounce',
		);
	}

	/**
	 * Apply touch optimizations to element attributes.
	 *
	 * @param array $attributes Element attributes.
	 * @return array
	 */
	public function apply_touch_optimizations( $attributes ) {
		$attributes['style'] = ( $attributes['style'] ?? '' ) . sprintf(
			'min-height: %dpx; min-width: %dpx;',
			self::MIN_TOUCH_TARGET,
			self::MIN_TOUCH_TARGET
		);

		$attributes['class'] = ( $attributes['class'] ?? '' ) . ' bkx-touch-target';

		return $attributes;
	}

	/**
	 * Get touch scrolling configuration.
	 *
	 * @return array
	 */
	public function get_scroll_config() {
		return array(
			'smooth'           => true,
			'momentum'         => true,
			'overscroll'       => 'contain',
			'snap_to_elements' => false,
			'scroll_indicator' => true,
		);
	}
}

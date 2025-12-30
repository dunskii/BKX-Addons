<?php
/**
 * Mobile UI Service.
 *
 * @package BookingX\MobileOptimize\Services
 */

namespace BookingX\MobileOptimize\Services;

defined( 'ABSPATH' ) || exit;

/**
 * MobileUIService class.
 */
class MobileUIService {

	/**
	 * Get floating CTA configuration.
	 *
	 * @return array
	 */
	public function get_floating_cta_config() {
		$addon = \BookingX\MobileOptimize\MobileOptimizeAddon::get_instance();

		return array(
			'enabled'    => $addon->get_setting( 'floating_cta', true ),
			'position'   => 'bottom-right',
			'icon'       => 'calendar',
			'text'       => __( 'Book Now', 'bkx-mobile-optimize' ),
			'show_on_scroll' => true,
			'hide_on_form'   => true,
			'animation'  => 'bounce',
		);
	}

	/**
	 * Get bottom sheet configuration.
	 *
	 * @return array
	 */
	public function get_bottom_sheet_config() {
		return array(
			'snap_points'      => array( 0.3, 0.6, 0.9 ),
			'initial_snap'     => 0.6,
			'backdrop'         => true,
			'backdrop_dismiss' => true,
			'swipe_dismiss'    => true,
			'animation_duration' => 300,
			'border_radius'    => 16,
		);
	}

	/**
	 * Get step indicator configuration.
	 *
	 * @return array
	 */
	public function get_step_indicator_config() {
		return array(
			'style'           => 'dots',
			'show_labels'     => true,
			'clickable'       => true,
			'show_progress'   => true,
			'animation'       => 'fade',
		);
	}

	/**
	 * Get skeleton loading configuration.
	 *
	 * @return array
	 */
	public function get_skeleton_config() {
		return array(
			'enabled'        => true,
			'animation'      => 'pulse',
			'base_color'     => '#e0e0e0',
			'highlight_color' => '#f5f5f5',
			'duration'       => 1.5,
		);
	}

	/**
	 * Get toast notification configuration.
	 *
	 * @return array
	 */
	public function get_toast_config() {
		return array(
			'position'    => 'bottom',
			'duration'    => 3000,
			'swipe_dismiss' => true,
			'animation'   => 'slide-up',
		);
	}

	/**
	 * Get action sheet configuration.
	 *
	 * @return array
	 */
	public function get_action_sheet_config() {
		return array(
			'style'           => 'ios',
			'cancel_text'     => __( 'Cancel', 'bkx-mobile-optimize' ),
			'backdrop_dismiss' => true,
		);
	}

	/**
	 * Render loading skeleton.
	 *
	 * @param string $type Type of skeleton.
	 * @return string
	 */
	public function render_skeleton( $type = 'card' ) {
		$templates = array(
			'card'     => '<div class="bkx-skeleton bkx-skeleton-card"><div class="bkx-skeleton-image"></div><div class="bkx-skeleton-text"></div><div class="bkx-skeleton-text short"></div></div>',
			'list'     => '<div class="bkx-skeleton bkx-skeleton-list"><div class="bkx-skeleton-avatar"></div><div class="bkx-skeleton-content"><div class="bkx-skeleton-text"></div><div class="bkx-skeleton-text short"></div></div></div>',
			'form'     => '<div class="bkx-skeleton bkx-skeleton-form"><div class="bkx-skeleton-input"></div><div class="bkx-skeleton-input"></div><div class="bkx-skeleton-button"></div></div>',
			'calendar' => '<div class="bkx-skeleton bkx-skeleton-calendar"><div class="bkx-skeleton-header"></div><div class="bkx-skeleton-grid"></div></div>',
		);

		return $templates[ $type ] ?? $templates['card'];
	}

	/**
	 * Get mobile navigation configuration.
	 *
	 * @return array
	 */
	public function get_navigation_config() {
		return array(
			'style'           => 'bottom-tab',
			'show_labels'     => true,
			'icon_size'       => 24,
			'active_indicator' => 'line',
			'haptic_feedback' => true,
		);
	}

	/**
	 * Get date/time picker configuration for mobile.
	 *
	 * @return array
	 */
	public function get_picker_config() {
		return array(
			'date' => array(
				'style'        => 'native',
				'format'       => get_option( 'date_format' ),
				'min_date'     => 'today',
				'max_date'     => '+90 days',
				'week_start'   => get_option( 'start_of_week' ),
			),
			'time' => array(
				'style'        => 'native',
				'format'       => get_option( 'time_format' ),
				'interval'     => 30,
				'min_time'     => '08:00',
				'max_time'     => '20:00',
			),
		);
	}

	/**
	 * Get swipe actions configuration.
	 *
	 * @return array
	 */
	public function get_swipe_actions_config() {
		return array(
			'left' => array(
				array(
					'icon'   => 'phone',
					'label'  => __( 'Call', 'bkx-mobile-optimize' ),
					'action' => 'call',
					'color'  => '#22c55e',
				),
			),
			'right' => array(
				array(
					'icon'   => 'x',
					'label'  => __( 'Cancel', 'bkx-mobile-optimize' ),
					'action' => 'cancel',
					'color'  => '#ef4444',
				),
			),
		);
	}
}

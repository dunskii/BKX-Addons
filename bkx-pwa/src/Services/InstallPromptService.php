<?php
/**
 * Install Prompt Service.
 *
 * @package BookingX\PWA\Services
 */

namespace BookingX\PWA\Services;

defined( 'ABSPATH' ) || exit;

/**
 * InstallPromptService class.
 */
class InstallPromptService {

	/**
	 * Check if install prompt should be shown.
	 *
	 * @return bool
	 */
	public function should_show_prompt() {
		$addon = \BookingX\PWA\PWAAddon::get_instance();

		if ( ! $addon->get_setting( 'install_prompt', true ) ) {
			return false;
		}

		// Check cookie for dismissed prompt.
		if ( isset( $_COOKIE['bkx_pwa_prompt_dismissed'] ) ) {
			return false;
		}

		// Don't show on admin pages.
		if ( is_admin() ) {
			return false;
		}

		return true;
	}

	/**
	 * Get prompt configuration.
	 *
	 * @return array
	 */
	public function get_prompt_config() {
		$addon = \BookingX\PWA\PWAAddon::get_instance();

		return array(
			'title'        => $addon->get_setting( 'install_prompt_title', __( 'Install Our App', 'bkx-pwa' ) ),
			'description'  => $addon->get_setting( 'install_prompt_description', __( 'Install our app for a better booking experience!', 'bkx-pwa' ) ),
			'install_text' => $addon->get_setting( 'install_button_text', __( 'Install', 'bkx-pwa' ) ),
			'dismiss_text' => $addon->get_setting( 'dismiss_button_text', __( 'Not Now', 'bkx-pwa' ) ),
			'delay'        => $addon->get_setting( 'install_prompt_delay', 30 ) * 1000,
			'position'     => $addon->get_setting( 'install_prompt_position', 'bottom' ),
			'theme_color'  => $addon->get_setting( 'theme_color', '#2563eb' ),
		);
	}

	/**
	 * Get iOS-specific install instructions.
	 *
	 * @return array
	 */
	public function get_ios_instructions() {
		return array(
			'title' => __( 'Install on iOS', 'bkx-pwa' ),
			'steps' => array(
				__( 'Tap the Share button', 'bkx-pwa' ),
				__( 'Scroll down and tap "Add to Home Screen"', 'bkx-pwa' ),
				__( 'Tap "Add" to confirm', 'bkx-pwa' ),
			),
		);
	}

	/**
	 * Track install events.
	 *
	 * @param string $event  Event type (prompt_shown, install_accepted, install_dismissed).
	 * @param array  $data   Additional data.
	 */
	public function track_event( $event, $data = array() ) {
		$stats = get_option( 'bkx_pwa_install_stats', array() );

		if ( ! isset( $stats[ $event ] ) ) {
			$stats[ $event ] = 0;
		}

		$stats[ $event ]++;
		$stats['last_' . $event] = current_time( 'mysql' );

		update_option( 'bkx_pwa_install_stats', $stats );

		// Fire action for external tracking.
		do_action( 'bkx_pwa_install_event', $event, $data );
	}

	/**
	 * Get install statistics.
	 *
	 * @return array
	 */
	public function get_stats() {
		return get_option(
			'bkx_pwa_install_stats',
			array(
				'prompt_shown'       => 0,
				'install_accepted'   => 0,
				'install_dismissed'  => 0,
				'last_prompt_shown'  => null,
				'last_install'       => null,
			)
		);
	}
}

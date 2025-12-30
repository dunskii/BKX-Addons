<?php
/**
 * App Config Service for Mobile App Framework.
 *
 * @package BookingX\MobileApp\Services
 */

namespace BookingX\MobileApp\Services;

defined( 'ABSPATH' ) || exit;

/**
 * AppConfigService class.
 */
class AppConfigService {

	/**
	 * Get app configuration.
	 *
	 * @return array
	 */
	public function get_config() {
		$addon    = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$settings = get_option( 'bkx_mobile_app_settings', array() );

		return array(
			'app'       => $this->get_app_info(),
			'api'       => $this->get_api_config(),
			'features'  => $this->get_features_config(),
			'branding'  => $this->get_branding_config(),
			'booking'   => $this->get_booking_config(),
			'push'      => $this->get_push_config(),
			'links'     => $this->get_links_config(),
		);
	}

	/**
	 * Get app info.
	 *
	 * @return array
	 */
	private function get_app_info() {
		return array(
			'name'               => get_bloginfo( 'name' ),
			'version'            => BKX_MOBILE_APP_VERSION,
			'minimum_version'    => '1.0.0',
			'force_update'       => false,
			'maintenance_mode'   => false,
			'maintenance_message' => '',
		);
	}

	/**
	 * Get API config.
	 *
	 * @return array
	 */
	private function get_api_config() {
		return array(
			'base_url'    => rest_url( 'bkx-mobile/v1' ),
			'timeout'     => 30,
			'retry_count' => 3,
		);
	}

	/**
	 * Get features config.
	 *
	 * @return array
	 */
	private function get_features_config() {
		return array(
			'registration_enabled' => get_option( 'users_can_register' ),
			'guest_booking'        => true,
			'multiple_bookings'    => true,
			'booking_cancellation' => true,
			'rescheduling'         => true,
			'payments_enabled'     => true,
			'reviews_enabled'      => true,
			'favorites_enabled'    => true,
			'notifications_enabled' => true,
			'location_services'    => false,
		);
	}

	/**
	 * Get branding config.
	 *
	 * @return array
	 */
	private function get_branding_config() {
		$logo_id  = get_theme_mod( 'custom_logo' );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

		return array(
			'logo_url'       => $logo_url,
			'primary_color'  => get_option( 'bkx_primary_color', '#2563eb' ),
			'secondary_color' => get_option( 'bkx_secondary_color', '#1e40af' ),
			'accent_color'   => get_option( 'bkx_accent_color', '#10b981' ),
		);
	}

	/**
	 * Get booking config.
	 *
	 * @return array
	 */
	private function get_booking_config() {
		return array(
			'timezone'                => wp_timezone_string(),
			'date_format'             => get_option( 'date_format' ),
			'time_format'             => get_option( 'time_format' ),
			'week_starts_on'          => get_option( 'start_of_week' ),
			'currency'                => get_option( 'bkx_currency', 'USD' ),
			'currency_symbol'         => get_option( 'bkx_currency_symbol', '$' ),
			'advance_booking_days'    => get_option( 'bkx_advance_booking_days', 30 ),
			'min_booking_notice_hours' => get_option( 'bkx_min_booking_notice', 2 ),
			'cancellation_hours'      => get_option( 'bkx_cancellation_hours', 24 ),
		);
	}

	/**
	 * Get push config.
	 *
	 * @return array
	 */
	private function get_push_config() {
		$addon    = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$settings = get_option( 'bkx_mobile_app_settings', array() );

		return array(
			'enabled'           => $addon->get_setting( 'enabled', true ),
			'booking_created'   => $addon->get_setting( 'push_booking_created', true ),
			'booking_confirmed' => $addon->get_setting( 'push_booking_confirmed', true ),
			'booking_reminder'  => $addon->get_setting( 'push_booking_reminder', true ),
			'booking_cancelled' => $addon->get_setting( 'push_booking_cancelled', true ),
			'reminder_hours'    => $addon->get_setting( 'reminder_hours', 24 ),
		);
	}

	/**
	 * Get links config.
	 *
	 * @return array
	 */
	private function get_links_config() {
		$addon = \BookingX\MobileApp\MobileAppAddon::get_instance();

		return array(
			'terms_url'    => get_privacy_policy_url() ?: home_url( '/terms' ),
			'privacy_url'  => get_privacy_policy_url(),
			'support_url'  => home_url( '/support' ),
			'faq_url'      => home_url( '/faq' ),
			'app_store'    => $addon->get_setting( 'app_store_url', '' ),
			'play_store'   => $addon->get_setting( 'play_store_url', '' ),
			'website'      => home_url(),
		);
	}

	/**
	 * Get localization config.
	 *
	 * @return array
	 */
	public function get_localization() {
		return array(
			'default_language' => get_locale(),
			'available_languages' => array(
				'en_US' => 'English',
			),
			'strings' => $this->get_app_strings(),
		);
	}

	/**
	 * Get app strings for localization.
	 *
	 * @return array
	 */
	private function get_app_strings() {
		return array(
			'booking' => array(
				'book_now'       => __( 'Book Now', 'bkx-mobile-app' ),
				'select_service' => __( 'Select Service', 'bkx-mobile-app' ),
				'select_date'    => __( 'Select Date', 'bkx-mobile-app' ),
				'select_time'    => __( 'Select Time', 'bkx-mobile-app' ),
				'confirm'        => __( 'Confirm Booking', 'bkx-mobile-app' ),
				'cancel'         => __( 'Cancel Booking', 'bkx-mobile-app' ),
				'reschedule'     => __( 'Reschedule', 'bkx-mobile-app' ),
			),
			'status' => array(
				'pending'   => __( 'Pending', 'bkx-mobile-app' ),
				'confirmed' => __( 'Confirmed', 'bkx-mobile-app' ),
				'completed' => __( 'Completed', 'bkx-mobile-app' ),
				'cancelled' => __( 'Cancelled', 'bkx-mobile-app' ),
			),
			'general' => array(
				'loading'   => __( 'Loading...', 'bkx-mobile-app' ),
				'error'     => __( 'An error occurred', 'bkx-mobile-app' ),
				'retry'     => __( 'Retry', 'bkx-mobile-app' ),
				'save'      => __( 'Save', 'bkx-mobile-app' ),
				'submit'    => __( 'Submit', 'bkx-mobile-app' ),
			),
		);
	}
}

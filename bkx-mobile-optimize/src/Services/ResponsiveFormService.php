<?php
/**
 * Responsive Form Service.
 *
 * @package BookingX\MobileOptimize\Services
 */

namespace BookingX\MobileOptimize\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ResponsiveFormService class.
 */
class ResponsiveFormService {

	/**
	 * Get mobile-optimized form configuration.
	 *
	 * @return array
	 */
	public function get_form_config() {
		$addon = \BookingX\MobileOptimize\MobileOptimizeAddon::get_instance();

		return array(
			'layout'           => 'stepper',
			'step_indicator'   => $addon->get_setting( 'form_step_indicator', true ),
			'auto_advance'     => true,
			'keyboard_nav'     => $addon->get_setting( 'keyboard_optimization', true ),
			'field_validation' => 'inline',
			'error_position'   => 'below',
			'submit_position'  => 'sticky',
		);
	}

	/**
	 * Get form steps for mobile.
	 *
	 * @return array
	 */
	public function get_form_steps() {
		return array(
			array(
				'id'     => 'service',
				'title'  => __( 'Service', 'bkx-mobile-optimize' ),
				'icon'   => 'list',
				'fields' => array( 'service_id', 'extras' ),
			),
			array(
				'id'     => 'datetime',
				'title'  => __( 'Date & Time', 'bkx-mobile-optimize' ),
				'icon'   => 'calendar',
				'fields' => array( 'date', 'time', 'resource_id' ),
			),
			array(
				'id'     => 'details',
				'title'  => __( 'Details', 'bkx-mobile-optimize' ),
				'icon'   => 'user',
				'fields' => array( 'name', 'email', 'phone', 'notes' ),
			),
			array(
				'id'     => 'confirm',
				'title'  => __( 'Confirm', 'bkx-mobile-optimize' ),
				'icon'   => 'check',
				'fields' => array( 'summary', 'payment' ),
			),
		);
	}

	/**
	 * Get mobile input configuration.
	 *
	 * @param string $field_type Field type.
	 * @return array
	 */
	public function get_input_config( $field_type ) {
		$configs = array(
			'text'     => array(
				'autocapitalize' => 'sentences',
				'autocorrect'    => 'on',
				'spellcheck'     => 'true',
			),
			'email'    => array(
				'inputmode'    => 'email',
				'autocomplete' => 'email',
				'autocapitalize' => 'none',
			),
			'phone'    => array(
				'type'         => 'tel',
				'inputmode'    => 'tel',
				'autocomplete' => 'tel',
			),
			'name'     => array(
				'autocomplete'   => 'name',
				'autocapitalize' => 'words',
			),
			'number'   => array(
				'inputmode' => 'numeric',
				'pattern'   => '[0-9]*',
			),
			'date'     => array(
				'type' => 'date',
			),
			'time'     => array(
				'type' => 'time',
			),
			'textarea' => array(
				'rows'           => 3,
				'autocapitalize' => 'sentences',
			),
		);

		return $configs[ $field_type ] ?? array();
	}

	/**
	 * Generate mobile-friendly form HTML.
	 *
	 * @param array $fields Form fields.
	 * @return string
	 */
	public function render_mobile_form( $fields ) {
		$steps  = $this->get_form_steps();
		$config = $this->get_form_config();

		ob_start();
		include BKX_MOBILE_OPTIMIZE_PLUGIN_DIR . 'templates/mobile-form.php';
		return ob_get_clean();
	}

	/**
	 * Get autofill suggestions based on user history.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_autofill_suggestions( $user_id ) {
		if ( ! $user_id ) {
			return array();
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		// Get last booking data.
		$last_booking = $this->get_last_booking( $user_id );

		return array(
			'name'  => $user->display_name,
			'email' => $user->user_email,
			'phone' => get_user_meta( $user_id, 'phone', true ),
			'last_service' => $last_booking['service_id'] ?? null,
			'last_resource' => $last_booking['resource_id'] ?? null,
			'preferred_time' => $last_booking['time'] ?? null,
		);
	}

	/**
	 * Get user's last booking.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_last_booking( $user_id ) {
		$bookings = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'posts_per_page' => 1,
				'author'         => $user_id,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( empty( $bookings ) ) {
			return array();
		}

		$booking = $bookings[0];

		return array(
			'service_id'  => get_post_meta( $booking->ID, 'base_id', true ),
			'resource_id' => get_post_meta( $booking->ID, 'seat_id', true ),
			'date'        => get_post_meta( $booking->ID, 'booking_date', true ),
			'time'        => get_post_meta( $booking->ID, 'booking_time', true ),
		);
	}
}

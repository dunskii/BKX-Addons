<?php
/**
 * Variable Service.
 *
 * @package BookingX\AdvancedEmailTemplates
 */

namespace BookingX\AdvancedEmailTemplates\Services;

defined( 'ABSPATH' ) || exit;

/**
 * VariableService class.
 */
class VariableService {

	/**
	 * Get all available variables.
	 *
	 * @return array
	 */
	public function get_all_variables() {
		$variables = array(
			'booking'  => array(
				'label'     => __( 'Booking', 'bkx-advanced-email-templates' ),
				'variables' => array(
					'booking_id'     => __( 'Booking ID', 'bkx-advanced-email-templates' ),
					'booking_date'   => __( 'Booking Date', 'bkx-advanced-email-templates' ),
					'booking_time'   => __( 'Booking Time', 'bkx-advanced-email-templates' ),
					'booking_total'  => __( 'Booking Total', 'bkx-advanced-email-templates' ),
					'booking_status' => __( 'Booking Status', 'bkx-advanced-email-templates' ),
				),
			),
			'customer' => array(
				'label'     => __( 'Customer', 'bkx-advanced-email-templates' ),
				'variables' => array(
					'customer_name'  => __( 'Customer Name', 'bkx-advanced-email-templates' ),
					'customer_email' => __( 'Customer Email', 'bkx-advanced-email-templates' ),
					'customer_phone' => __( 'Customer Phone', 'bkx-advanced-email-templates' ),
				),
			),
			'service'  => array(
				'label'     => __( 'Service', 'bkx-advanced-email-templates' ),
				'variables' => array(
					'service_name'     => __( 'Service Name', 'bkx-advanced-email-templates' ),
					'service_duration' => __( 'Service Duration', 'bkx-advanced-email-templates' ),
					'service_price'    => __( 'Service Price', 'bkx-advanced-email-templates' ),
				),
			),
			'staff'    => array(
				'label'     => __( 'Staff', 'bkx-advanced-email-templates' ),
				'variables' => array(
					'staff_name'  => __( 'Staff Name', 'bkx-advanced-email-templates' ),
					'staff_email' => __( 'Staff Email', 'bkx-advanced-email-templates' ),
				),
			),
			'location' => array(
				'label'     => __( 'Location', 'bkx-advanced-email-templates' ),
				'variables' => array(
					'location_name'    => __( 'Location Name', 'bkx-advanced-email-templates' ),
					'location_address' => __( 'Location Address', 'bkx-advanced-email-templates' ),
				),
			),
			'site'     => array(
				'label'     => __( 'Site', 'bkx-advanced-email-templates' ),
				'variables' => array(
					'site_name'  => __( 'Site Name', 'bkx-advanced-email-templates' ),
					'site_url'   => __( 'Site URL', 'bkx-advanced-email-templates' ),
					'admin_email' => __( 'Admin Email', 'bkx-advanced-email-templates' ),
				),
			),
			'links'    => array(
				'label'     => __( 'Links', 'bkx-advanced-email-templates' ),
				'variables' => array(
					'admin_booking_url'    => __( 'Admin Booking URL', 'bkx-advanced-email-templates' ),
					'customer_booking_url' => __( 'Customer Booking URL', 'bkx-advanced-email-templates' ),
					'cancel_url'           => __( 'Cancellation URL', 'bkx-advanced-email-templates' ),
					'reschedule_url'       => __( 'Reschedule URL', 'bkx-advanced-email-templates' ),
				),
			),
			'payment'  => array(
				'label'     => __( 'Payment', 'bkx-advanced-email-templates' ),
				'variables' => array(
					'payment_method'     => __( 'Payment Method', 'bkx-advanced-email-templates' ),
					'payment_status'     => __( 'Payment Status', 'bkx-advanced-email-templates' ),
					'payment_amount'     => __( 'Payment Amount', 'bkx-advanced-email-templates' ),
					'transaction_id'     => __( 'Transaction ID', 'bkx-advanced-email-templates' ),
				),
			),
			'other'    => array(
				'label'     => __( 'Other', 'bkx-advanced-email-templates' ),
				'variables' => array(
					'cancellation_reason' => __( 'Cancellation Reason', 'bkx-advanced-email-templates' ),
					'current_date'        => __( 'Current Date', 'bkx-advanced-email-templates' ),
					'current_time'        => __( 'Current Time', 'bkx-advanced-email-templates' ),
				),
			),
		);

		/**
		 * Filter available email template variables.
		 *
		 * @param array $variables Variables.
		 */
		return apply_filters( 'bkx_email_template_variables', $variables );
	}

	/**
	 * Replace variables in content.
	 *
	 * @param string $content Content with variables.
	 * @param array  $data    Data for replacement.
	 * @return string
	 */
	public function replace_variables( $content, $data ) {
		// Add site variables.
		$data['site_name']   = get_bloginfo( 'name' );
		$data['site_url']    = home_url();
		$data['admin_email'] = get_option( 'admin_email' );
		$data['current_date'] = wp_date( get_option( 'date_format' ) );
		$data['current_time'] = wp_date( get_option( 'time_format' ) );

		// Replace {{variable}} syntax.
		$pattern = '/\{\{(\w+)\}\}/';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $data ) {
				$variable = $matches[1];

				if ( isset( $data[ $variable ] ) ) {
					return esc_html( $data[ $variable ] );
				}

				// Return empty for undefined variables.
				return '';
			},
			$content
		);
	}

	/**
	 * Get sample data for previews.
	 *
	 * @return array
	 */
	public function get_sample_data() {
		return array(
			'booking_id'          => '1234',
			'booking_date'        => wp_date( get_option( 'date_format' ), strtotime( '+3 days' ) ),
			'booking_time'        => '10:00 AM',
			'booking_total'       => '$75.00',
			'booking_status'      => 'Confirmed',
			'customer_name'       => 'John Smith',
			'customer_email'      => 'john@example.com',
			'customer_phone'      => '(555) 123-4567',
			'service_name'        => 'Standard Consultation',
			'service_duration'    => '60 minutes',
			'service_price'       => '$75.00',
			'staff_name'          => 'Jane Doe',
			'staff_email'         => 'jane@example.com',
			'location_name'       => 'Main Office',
			'location_address'    => '123 Main St, City, State 12345',
			'site_name'           => get_bloginfo( 'name' ),
			'site_url'            => home_url(),
			'admin_email'         => get_option( 'admin_email' ),
			'admin_booking_url'   => admin_url( 'post.php?post=1234&action=edit' ),
			'customer_booking_url' => home_url( '/my-bookings/1234' ),
			'cancel_url'          => home_url( '/cancel-booking/1234' ),
			'reschedule_url'      => home_url( '/reschedule/1234' ),
			'payment_method'      => 'Credit Card',
			'payment_status'      => 'Paid',
			'payment_amount'      => '$75.00',
			'transaction_id'      => 'TXN-123456789',
			'cancellation_reason' => 'Customer request',
			'current_date'        => wp_date( get_option( 'date_format' ) ),
			'current_time'        => wp_date( get_option( 'time_format' ) ),
		);
	}

	/**
	 * Validate content for missing variables.
	 *
	 * @param string $content Content to validate.
	 * @return array List of undefined variables.
	 */
	public function validate_variables( $content ) {
		$pattern = '/\{\{(\w+)\}\}/';
		preg_match_all( $pattern, $content, $matches );

		$used_variables = $matches[1] ?? array();
		$all_variables  = $this->get_flat_variables();
		$undefined      = array();

		foreach ( $used_variables as $variable ) {
			if ( ! in_array( $variable, $all_variables, true ) ) {
				$undefined[] = $variable;
			}
		}

		return array_unique( $undefined );
	}

	/**
	 * Get flat list of variable names.
	 *
	 * @return array
	 */
	private function get_flat_variables() {
		$all       = $this->get_all_variables();
		$variables = array();

		foreach ( $all as $group ) {
			$variables = array_merge( $variables, array_keys( $group['variables'] ) );
		}

		return $variables;
	}
}

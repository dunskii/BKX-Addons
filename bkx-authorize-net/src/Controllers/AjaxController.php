<?php
/**
 * AJAX Controller
 *
 * Handles AJAX requests for payment processing.
 *
 * @package BookingX\AuthorizeNet\Controllers
 * @since   1.0.0
 */

namespace BookingX\AuthorizeNet\Controllers;

use BookingX\AuthorizeNet\AuthorizeNet;

/**
 * AJAX controller class.
 *
 * @since 1.0.0
 */
class AjaxController {

	/**
	 * Addon instance.
	 *
	 * @var AuthorizeNet
	 */
	protected AuthorizeNet $addon;

	/**
	 * Constructor.
	 *
	 * @param AuthorizeNet $addon Addon instance.
	 */
	public function __construct( AuthorizeNet $addon ) {
		$this->addon = $addon;
		$this->register_handlers();
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function register_handlers(): void {
		add_action( 'wp_ajax_bkx_authorize_net_process_payment', array( $this, 'process_payment' ) );
		add_action( 'wp_ajax_nopriv_bkx_authorize_net_process_payment', array( $this, 'process_payment' ) );
	}

	/**
	 * Process payment AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function process_payment(): void {
		// Verify nonce - SECURITY CRITICAL.
		if ( ! isset( $_POST['nonce'] ) ||
			 ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'bkx_authorize_net_payment' ) ) {
			wp_send_json_error(
				array( 'error' => __( 'Security check failed. Please refresh the page and try again.', 'bkx-authorize-net' ) ),
				403
			);
		}

		// Get and validate booking ID.
		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		if ( ! $booking_id ) {
			wp_send_json_error(
				array( 'error' => __( 'Invalid booking ID.', 'bkx-authorize-net' ) ),
				400
			);
		}

		// Verify booking exists.
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			wp_send_json_error(
				array( 'error' => __( 'Booking not found.', 'bkx-authorize-net' ) ),
				404
			);
		}

		// Get opaque data from Accept.js.
		$payment_data = array(
			'opaque_data_descriptor' => isset( $_POST['opaque_data_descriptor'] )
				? sanitize_text_field( wp_unslash( $_POST['opaque_data_descriptor'] ) )
				: '',
			'opaque_data_value' => isset( $_POST['opaque_data_value'] )
				? sanitize_text_field( wp_unslash( $_POST['opaque_data_value'] ) )
				: '',
		);

		// Validate opaque data.
		if ( empty( $payment_data['opaque_data_descriptor'] ) || empty( $payment_data['opaque_data_value'] ) ) {
			wp_send_json_error(
				array( 'error' => __( 'Invalid payment token. Please try again.', 'bkx-authorize-net' ) ),
				400
			);
		}

		// Process payment through gateway.
		$gateway = $this->addon->get_gateway();
		if ( null === $gateway ) {
			wp_send_json_error(
				array( 'error' => __( 'Payment gateway is not available.', 'bkx-authorize-net' ) ),
				500
			);
		}

		$result = $gateway->process_payment( $booking_id, $payment_data );

		if ( ! $result['success'] ) {
			wp_send_json_error(
				array( 'error' => $result['error'] ?? __( 'Payment failed.', 'bkx-authorize-net' ) ),
				400
			);
		}

		// Get redirect URL for confirmation page.
		$redirect_url = apply_filters(
			'bkx_authorize_net_payment_redirect_url',
			add_query_arg(
				array(
					'booking_id' => $booking_id,
					'payment'    => 'success',
				),
				home_url( '/booking-confirmation/' )
			),
			$booking_id,
			$result
		);

		wp_send_json_success(
			array(
				'message'        => $result['message'] ?? __( 'Payment processed successfully.', 'bkx-authorize-net' ),
				'transaction_id' => $result['transaction_id'] ?? '',
				'redirect_url'   => $redirect_url,
			)
		);
	}
}

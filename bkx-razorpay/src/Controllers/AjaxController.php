<?php
/**
 * AJAX Controller
 *
 * Handles AJAX requests for payment processing.
 *
 * @package BookingX\Razorpay\Controllers
 * @since   1.0.0
 */

namespace BookingX\Razorpay\Controllers;

use BookingX\Razorpay\RazorpayAddon;

/**
 * AJAX controller class.
 *
 * @since 1.0.0
 */
class AjaxController {

	/**
	 * Addon instance.
	 *
	 * @var RazorpayAddon
	 */
	protected RazorpayAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param RazorpayAddon $addon Addon instance.
	 */
	public function __construct( RazorpayAddon $addon ) {
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
		// Create order.
		add_action( 'wp_ajax_bkx_razorpay_create_order', array( $this, 'create_order' ) );
		add_action( 'wp_ajax_nopriv_bkx_razorpay_create_order', array( $this, 'create_order' ) );

		// Verify payment.
		add_action( 'wp_ajax_bkx_razorpay_verify_payment', array( $this, 'verify_payment' ) );
		add_action( 'wp_ajax_nopriv_bkx_razorpay_verify_payment', array( $this, 'verify_payment' ) );
	}

	/**
	 * Create Razorpay order AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function create_order(): void {
		// Verify nonce - SECURITY CRITICAL.
		if ( ! isset( $_POST['nonce'] ) ||
			 ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'bkx_razorpay_payment' ) ) {
			wp_send_json_error(
				array( 'error' => __( 'Security check failed. Please refresh the page and try again.', 'bkx-razorpay' ) ),
				403
			);
		}

		// Get and validate booking ID.
		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		if ( ! $booking_id ) {
			wp_send_json_error(
				array( 'error' => __( 'Invalid booking ID.', 'bkx-razorpay' ) ),
				400
			);
		}

		// Verify booking exists.
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			wp_send_json_error(
				array( 'error' => __( 'Booking not found.', 'bkx-razorpay' ) ),
				404
			);
		}

		// Create order through gateway.
		$gateway = $this->addon->get_gateway();
		if ( null === $gateway ) {
			wp_send_json_error(
				array( 'error' => __( 'Payment gateway is not available.', 'bkx-razorpay' ) ),
				500
			);
		}

		$result = $gateway->create_order( $booking_id );

		if ( ! $result['success'] ) {
			wp_send_json_error(
				array( 'error' => $result['error'] ?? __( 'Failed to create order.', 'bkx-razorpay' ) ),
				400
			);
		}

		// Get customer info for prefill.
		$customer_name = get_post_meta( $booking_id, 'customer_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_phone = get_post_meta( $booking_id, 'customer_phone', true );

		wp_send_json_success(
			array(
				'order_id'        => $result['order_id'],
				'amount'          => $result['amount'],
				'amount_in_paise' => $result['amount_in_paise'],
				'currency'        => $result['currency'],
				'key_id'          => $result['key_id'],
				'prefill'         => array(
					'name'    => $customer_name ?: '',
					'email'   => $customer_email ?: '',
					'contact' => $customer_phone ?: '',
				),
			)
		);
	}

	/**
	 * Verify payment AJAX request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function verify_payment(): void {
		// Verify nonce - SECURITY CRITICAL.
		if ( ! isset( $_POST['nonce'] ) ||
			 ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'bkx_razorpay_payment' ) ) {
			wp_send_json_error(
				array( 'error' => __( 'Security check failed. Please refresh the page and try again.', 'bkx-razorpay' ) ),
				403
			);
		}

		// Get and validate booking ID.
		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		if ( ! $booking_id ) {
			wp_send_json_error(
				array( 'error' => __( 'Invalid booking ID.', 'bkx-razorpay' ) ),
				400
			);
		}

		// Get payment data from Razorpay Checkout.
		$payment_data = array(
			'razorpay_payment_id' => isset( $_POST['razorpay_payment_id'] )
				? sanitize_text_field( wp_unslash( $_POST['razorpay_payment_id'] ) )
				: '',
			'razorpay_order_id'   => isset( $_POST['razorpay_order_id'] )
				? sanitize_text_field( wp_unslash( $_POST['razorpay_order_id'] ) )
				: '',
			'razorpay_signature'  => isset( $_POST['razorpay_signature'] )
				? sanitize_text_field( wp_unslash( $_POST['razorpay_signature'] ) )
				: '',
		);

		// Validate payment data.
		if ( empty( $payment_data['razorpay_payment_id'] ) ||
			 empty( $payment_data['razorpay_order_id'] ) ||
			 empty( $payment_data['razorpay_signature'] ) ) {
			wp_send_json_error(
				array( 'error' => __( 'Invalid payment data.', 'bkx-razorpay' ) ),
				400
			);
		}

		// Verify payment through gateway.
		$gateway = $this->addon->get_gateway();
		if ( null === $gateway ) {
			wp_send_json_error(
				array( 'error' => __( 'Payment gateway is not available.', 'bkx-razorpay' ) ),
				500
			);
		}

		$result = $gateway->process_payment( $booking_id, $payment_data );

		if ( ! $result['success'] ) {
			wp_send_json_error(
				array( 'error' => $result['error'] ?? __( 'Payment verification failed.', 'bkx-razorpay' ) ),
				400
			);
		}

		// Get redirect URL for confirmation page.
		$redirect_url = apply_filters(
			'bkx_razorpay_payment_redirect_url',
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
				'message'      => $result['message'] ?? __( 'Payment verified successfully.', 'bkx-razorpay' ),
				'payment_id'   => $result['payment_id'] ?? '',
				'redirect_url' => $redirect_url,
			)
		);
	}
}

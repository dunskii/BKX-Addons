<?php
/**
 * Razorpay API Service Class.
 *
 * @package BookingX\Razorpay
 * @since   1.0.0
 */

namespace BookingX\Razorpay;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ApiService Class.
 */
class ApiService {

	/**
	 * API URL.
	 *
	 * @var string
	 */
	private $api_url = BKX_RAZORPAY_API_URL;

	/**
	 * Get gateway instance.
	 *
	 * @return RazorpayGateway
	 */
	private function get_gateway() {
		return RazorpayGateway::get_instance();
	}

	/**
	 * Make API request.
	 *
	 * @param string $endpoint Endpoint.
	 * @param string $method   HTTP method.
	 * @param array  $data     Request data.
	 * @return array|\WP_Error
	 */
	private function request( $endpoint, $method = 'GET', $data = array() ) {
		$gateway    = $this->get_gateway();
		$key_id     = $gateway->get_key_id();
		$key_secret = $gateway->get_key_secret();

		if ( empty( $key_id ) || empty( $key_secret ) ) {
			return new \WP_Error( 'missing_credentials', __( 'API credentials are not configured.', 'bkx-razorpay' ) );
		}

		$url = $this->api_url . $endpoint;

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $key_id . ':' . $key_secret ),
			),
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$error_message = $data['error']['description'] ?? __( 'Unknown API error', 'bkx-razorpay' );
			$error_code    = $data['error']['code'] ?? 'api_error';

			return new \WP_Error( $error_code, $error_message );
		}

		return $data;
	}

	/**
	 * Create order.
	 *
	 * @param array $data Order data.
	 * @return array|\WP_Error
	 */
	public function create_order( $data ) {
		$defaults = array(
			'amount'   => 0,
			'currency' => 'INR',
			'receipt'  => '',
			'notes'    => array(),
		);

		$order_data = wp_parse_args( $data, $defaults );

		// Validate required fields.
		if ( empty( $order_data['amount'] ) ) {
			return new \WP_Error( 'invalid_amount', __( 'Amount is required.', 'bkx-razorpay' ) );
		}

		return $this->request( '/orders', 'POST', $order_data );
	}

	/**
	 * Get order.
	 *
	 * @param string $order_id Order ID.
	 * @return array|\WP_Error
	 */
	public function get_order( $order_id ) {
		if ( empty( $order_id ) ) {
			return new \WP_Error( 'invalid_order_id', __( 'Order ID is required.', 'bkx-razorpay' ) );
		}

		return $this->request( '/orders/' . $order_id );
	}

	/**
	 * Get payment.
	 *
	 * @param string $payment_id Payment ID.
	 * @return array|\WP_Error
	 */
	public function get_payment( $payment_id ) {
		if ( empty( $payment_id ) ) {
			return new \WP_Error( 'invalid_payment_id', __( 'Payment ID is required.', 'bkx-razorpay' ) );
		}

		return $this->request( '/payments/' . $payment_id );
	}

	/**
	 * Capture payment.
	 *
	 * @param string $payment_id Payment ID.
	 * @param int    $amount     Amount in smallest currency unit.
	 * @param string $currency   Currency code.
	 * @return array|\WP_Error
	 */
	public function capture_payment( $payment_id, $amount, $currency = 'INR' ) {
		if ( empty( $payment_id ) ) {
			return new \WP_Error( 'invalid_payment_id', __( 'Payment ID is required.', 'bkx-razorpay' ) );
		}

		if ( empty( $amount ) ) {
			return new \WP_Error( 'invalid_amount', __( 'Amount is required.', 'bkx-razorpay' ) );
		}

		return $this->request(
			'/payments/' . $payment_id . '/capture',
			'POST',
			array(
				'amount'   => $amount,
				'currency' => $currency,
			)
		);
	}

	/**
	 * Create refund.
	 *
	 * @param string $payment_id Payment ID.
	 * @param int    $amount     Amount in smallest currency unit (optional for full refund).
	 * @param array  $options    Additional options.
	 * @return array|\WP_Error
	 */
	public function create_refund( $payment_id, $amount = null, $options = array() ) {
		if ( empty( $payment_id ) ) {
			return new \WP_Error( 'invalid_payment_id', __( 'Payment ID is required.', 'bkx-razorpay' ) );
		}

		$data = array();

		if ( $amount !== null ) {
			$data['amount'] = absint( $amount );
		}

		if ( ! empty( $options['speed'] ) && in_array( $options['speed'], array( 'normal', 'optimum' ), true ) ) {
			$data['speed'] = $options['speed'];
		}

		if ( ! empty( $options['notes'] ) ) {
			$data['notes'] = $options['notes'];
		}

		if ( ! empty( $options['receipt'] ) ) {
			$data['receipt'] = sanitize_text_field( $options['receipt'] );
		}

		return $this->request( '/payments/' . $payment_id . '/refund', 'POST', $data );
	}

	/**
	 * Get refund.
	 *
	 * @param string $payment_id Payment ID.
	 * @param string $refund_id  Refund ID.
	 * @return array|\WP_Error
	 */
	public function get_refund( $payment_id, $refund_id ) {
		if ( empty( $payment_id ) || empty( $refund_id ) ) {
			return new \WP_Error( 'invalid_ids', __( 'Payment ID and Refund ID are required.', 'bkx-razorpay' ) );
		}

		return $this->request( '/payments/' . $payment_id . '/refunds/' . $refund_id );
	}

	/**
	 * Verify payment signature.
	 *
	 * @param string $order_id   Order ID.
	 * @param string $payment_id Payment ID.
	 * @param string $signature  Signature.
	 * @return bool
	 */
	public function verify_signature( $order_id, $payment_id, $signature ) {
		$gateway    = $this->get_gateway();
		$key_secret = $gateway->get_key_secret();

		if ( empty( $key_secret ) ) {
			return false;
		}

		$generated_signature = hash_hmac( 'sha256', $order_id . '|' . $payment_id, $key_secret );

		return hash_equals( $generated_signature, $signature );
	}

	/**
	 * Verify webhook signature.
	 *
	 * @param string $payload        Webhook payload (raw body).
	 * @param string $signature      Signature from header.
	 * @param string $webhook_secret Webhook secret.
	 * @return bool
	 */
	public function verify_webhook_signature( $payload, $signature, $webhook_secret ) {
		if ( empty( $webhook_secret ) ) {
			return false;
		}

		$expected_signature = hash_hmac( 'sha256', $payload, $webhook_secret );

		return hash_equals( $expected_signature, $signature );
	}
}

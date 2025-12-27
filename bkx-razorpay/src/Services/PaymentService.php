<?php
/**
 * Payment Service
 *
 * Handles payment verification and processing.
 *
 * @package BookingX\Razorpay\Services
 * @since   1.0.0
 */

namespace BookingX\Razorpay\Services;

use BookingX\Razorpay\Api\RazorpayClient;
use BookingX\Razorpay\Gateway\RazorpayGateway;

/**
 * Payment service class.
 *
 * @since 1.0.0
 */
class PaymentService {

	/**
	 * API client.
	 *
	 * @var RazorpayClient
	 */
	protected RazorpayClient $client;

	/**
	 * Gateway instance.
	 *
	 * @var RazorpayGateway
	 */
	protected RazorpayGateway $gateway;

	/**
	 * Constructor.
	 *
	 * @param RazorpayClient  $client API client.
	 * @param RazorpayGateway $gateway Gateway instance.
	 */
	public function __construct( RazorpayClient $client, RazorpayGateway $gateway ) {
		$this->client = $client;
		$this->gateway = $gateway;
	}

	/**
	 * Verify payment signature from Razorpay Checkout.
	 *
	 * @since 1.0.0
	 * @param string $order_id Razorpay order ID.
	 * @param string $payment_id Razorpay payment ID.
	 * @param string $signature Signature from checkout.
	 * @return bool Whether signature is valid.
	 */
	public function verify_payment_signature( string $order_id, string $payment_id, string $signature ): bool {
		return $this->client->verify_payment_signature( $order_id, $payment_id, $signature );
	}

	/**
	 * Capture an authorized payment.
	 *
	 * @since 1.0.0
	 * @param string $payment_id Payment ID.
	 * @param int    $amount Amount in paise.
	 * @return array Result.
	 */
	public function capture_payment( string $payment_id, int $amount ): array {
		$currency = $this->gateway->get_currency();
		return $this->client->capture_payment( $payment_id, $amount, $currency );
	}

	/**
	 * Fetch payment details.
	 *
	 * @since 1.0.0
	 * @param string $payment_id Payment ID.
	 * @return array Result with payment details.
	 */
	public function fetch_payment( string $payment_id ): array {
		return $this->client->fetch_payment( $payment_id );
	}

	/**
	 * Update transaction status in database.
	 *
	 * @since 1.0.0
	 * @param string $order_id Razorpay order ID.
	 * @param string $payment_id Razorpay payment ID.
	 * @param string $status New status.
	 * @return void
	 */
	public function update_transaction_status( string $order_id, string $payment_id, string $status ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_razorpay_transactions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'razorpay_payment_id' => $payment_id,
				'status'              => $status,
				'updated_at'          => current_time( 'mysql' ),
			),
			array( 'razorpay_order_id' => $order_id ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);
	}
}

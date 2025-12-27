<?php
/**
 * Razorpay API Client
 *
 * Wrapper for the Razorpay PHP SDK.
 *
 * @package BookingX\Razorpay\Api
 * @since   1.0.0
 */

namespace BookingX\Razorpay\Api;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

/**
 * Razorpay API client class.
 *
 * @since 1.0.0
 */
class RazorpayClient {

	/**
	 * Key ID.
	 *
	 * @var string
	 */
	protected string $key_id;

	/**
	 * Key Secret.
	 *
	 * @var string
	 */
	protected string $key_secret;

	/**
	 * Razorpay API instance.
	 *
	 * @var Api|null
	 */
	protected ?Api $api = null;

	/**
	 * Constructor.
	 *
	 * @param string $key_id Key ID.
	 * @param string $key_secret Key Secret.
	 */
	public function __construct( string $key_id, string $key_secret ) {
		$this->key_id = $key_id;
		$this->key_secret = $key_secret;
	}

	/**
	 * Get API instance.
	 *
	 * @since 1.0.0
	 * @return Api
	 */
	public function get_api(): Api {
		if ( null === $this->api ) {
			$this->api = new Api( $this->key_id, $this->key_secret );
		}
		return $this->api;
	}

	/**
	 * Create an order.
	 *
	 * @since 1.0.0
	 * @param int    $amount Amount in smallest currency unit (paise for INR).
	 * @param string $currency Currency code.
	 * @param string $receipt Receipt/reference ID.
	 * @param array  $notes Optional notes.
	 * @return array Result with order details.
	 */
	public function create_order( int $amount, string $currency, string $receipt, array $notes = array() ): array {
		try {
			$order_data = array(
				'amount'   => $amount,
				'currency' => $currency,
				'receipt'  => $receipt,
			);

			if ( ! empty( $notes ) ) {
				$order_data['notes'] = $notes;
			}

			$order = $this->get_api()->order->create( $order_data );

			return array(
				'success'  => true,
				'order_id' => $order->id,
				'amount'   => $order->amount,
				'currency' => $order->currency,
				'status'   => $order->status,
				'data'     => $order->toArray(),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Fetch an order.
	 *
	 * @since 1.0.0
	 * @param string $order_id Order ID.
	 * @return array Result with order details.
	 */
	public function fetch_order( string $order_id ): array {
		try {
			$order = $this->get_api()->order->fetch( $order_id );

			return array(
				'success' => true,
				'data'    => $order->toArray(),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Fetch a payment.
	 *
	 * @since 1.0.0
	 * @param string $payment_id Payment ID.
	 * @return array Result with payment details.
	 */
	public function fetch_payment( string $payment_id ): array {
		try {
			$payment = $this->get_api()->payment->fetch( $payment_id );

			return array(
				'success' => true,
				'data'    => $payment->toArray(),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Capture a payment.
	 *
	 * @since 1.0.0
	 * @param string $payment_id Payment ID.
	 * @param int    $amount Amount to capture in paise.
	 * @param string $currency Currency code.
	 * @return array Result.
	 */
	public function capture_payment( string $payment_id, int $amount, string $currency = 'INR' ): array {
		try {
			$payment = $this->get_api()->payment->fetch( $payment_id );
			$captured = $payment->capture( array(
				'amount'   => $amount,
				'currency' => $currency,
			) );

			return array(
				'success' => true,
				'data'    => $captured->toArray(),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Create a refund.
	 *
	 * @since 1.0.0
	 * @param string $payment_id Payment ID.
	 * @param int    $amount Amount to refund in paise.
	 * @param array  $options Optional refund options.
	 * @return array Result with refund details.
	 */
	public function create_refund( string $payment_id, int $amount, array $options = array() ): array {
		try {
			$refund_data = array(
				'amount' => $amount,
			);

			if ( ! empty( $options['speed'] ) ) {
				$refund_data['speed'] = $options['speed']; // 'normal' or 'optimum'.
			}

			if ( ! empty( $options['notes'] ) ) {
				$refund_data['notes'] = $options['notes'];
			}

			$payment = $this->get_api()->payment->fetch( $payment_id );
			$refund = $payment->refund( $refund_data );

			return array(
				'success'   => true,
				'refund_id' => $refund->id,
				'amount'    => $refund->amount / 100,
				'status'    => $refund->status,
				'data'      => $refund->toArray(),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Fetch a refund.
	 *
	 * @since 1.0.0
	 * @param string $refund_id Refund ID.
	 * @return array Result with refund details.
	 */
	public function fetch_refund( string $refund_id ): array {
		try {
			$refund = $this->get_api()->refund->fetch( $refund_id );

			return array(
				'success' => true,
				'data'    => $refund->toArray(),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Verify payment signature.
	 *
	 * @since 1.0.0
	 * @param string $order_id Razorpay order ID.
	 * @param string $payment_id Razorpay payment ID.
	 * @param string $signature Signature from checkout.
	 * @return bool Whether signature is valid.
	 */
	public function verify_payment_signature( string $order_id, string $payment_id, string $signature ): bool {
		try {
			$attributes = array(
				'razorpay_order_id'   => $order_id,
				'razorpay_payment_id' => $payment_id,
				'razorpay_signature'  => $signature,
			);

			$this->get_api()->utility->verifyPaymentSignature( $attributes );

			return true;

		} catch ( SignatureVerificationError $e ) {
			return false;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Verify webhook signature.
	 *
	 * @since 1.0.0
	 * @param string $webhook_body Raw webhook body.
	 * @param string $webhook_signature X-Razorpay-Signature header.
	 * @param string $webhook_secret Webhook secret.
	 * @return bool Whether signature is valid.
	 */
	public function verify_webhook_signature( string $webhook_body, string $webhook_signature, string $webhook_secret ): bool {
		try {
			$this->get_api()->utility->verifyWebhookSignature( $webhook_body, $webhook_signature, $webhook_secret );

			return true;

		} catch ( SignatureVerificationError $e ) {
			return false;
		} catch ( \Exception $e ) {
			return false;
		}
	}
}

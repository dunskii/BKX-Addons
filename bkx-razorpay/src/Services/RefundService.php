<?php
/**
 * Refund Service
 *
 * Handles refund processing.
 *
 * @package BookingX\Razorpay\Services
 * @since   1.0.0
 */

namespace BookingX\Razorpay\Services;

use BookingX\Razorpay\Api\RazorpayClient;
use BookingX\Razorpay\Gateway\RazorpayGateway;

/**
 * Refund service class.
 *
 * @since 1.0.0
 */
class RefundService {

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
	 * Create a refund.
	 *
	 * @since 1.0.0
	 * @param string $payment_id Razorpay payment ID.
	 * @param float  $amount Amount to refund in main currency unit.
	 * @param string $reason Refund reason.
	 * @return array Result with refund details.
	 */
	public function create_refund( string $payment_id, float $amount, string $reason = '' ): array {
		$this->gateway->log( sprintf( 'Creating refund for payment %s, amount: %s', $payment_id, $amount ) );

		// Convert to paise.
		$amount_in_paise = (int) round( $amount * 100 );

		// Prepare refund options.
		$options = array();

		if ( ! empty( $reason ) ) {
			$options['notes'] = array(
				'reason' => $reason,
			);
		}

		// Create refund via API.
		$result = $this->client->create_refund( $payment_id, $amount_in_paise, $options );

		if ( ! $result['success'] ) {
			$this->gateway->log( sprintf( 'Refund failed: %s', $result['error'] ?? 'Unknown error' ), 'error' );
		}

		return $result;
	}

	/**
	 * Fetch refund details.
	 *
	 * @since 1.0.0
	 * @param string $refund_id Refund ID.
	 * @return array Result with refund details.
	 */
	public function fetch_refund( string $refund_id ): array {
		return $this->client->fetch_refund( $refund_id );
	}

	/**
	 * Check if payment can be refunded.
	 *
	 * @since 1.0.0
	 * @param string $payment_id Payment ID.
	 * @return array Status check result.
	 */
	public function can_refund( string $payment_id ): array {
		$payment = $this->client->fetch_payment( $payment_id );

		if ( ! $payment['success'] ) {
			return array(
				'can_refund' => false,
				'error'      => $payment['error'],
			);
		}

		$status = $payment['data']['status'] ?? '';
		$refundable_statuses = array( 'captured' );

		if ( in_array( $status, $refundable_statuses, true ) ) {
			// Check if already fully refunded.
			$amount_refunded = $payment['data']['amount_refunded'] ?? 0;
			$amount = $payment['data']['amount'] ?? 0;

			if ( $amount_refunded >= $amount ) {
				return array(
					'can_refund' => false,
					'status'     => $status,
					'error'      => __( 'Payment has already been fully refunded.', 'bkx-razorpay' ),
				);
			}

			return array(
				'can_refund'       => true,
				'status'           => $status,
				'amount'           => $amount / 100,
				'amount_refunded'  => $amount_refunded / 100,
				'refundable_amount' => ( $amount - $amount_refunded ) / 100,
			);
		}

		return array(
			'can_refund' => false,
			'status'     => $status,
			'error'      => sprintf(
				/* translators: %s: payment status */
				__( 'Payment cannot be refunded in its current state: %s', 'bkx-razorpay' ),
				$status
			),
		);
	}
}

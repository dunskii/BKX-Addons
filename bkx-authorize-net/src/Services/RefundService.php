<?php
/**
 * Refund Service
 *
 * Handles refund processing logic.
 *
 * @package BookingX\AuthorizeNet\Services
 * @since   1.0.0
 */

namespace BookingX\AuthorizeNet\Services;

use BookingX\AuthorizeNet\Api\AuthorizeNetClient;
use BookingX\AuthorizeNet\Gateway\AuthorizeNetGateway;

/**
 * Refund service class.
 *
 * @since 1.0.0
 */
class RefundService {

	/**
	 * API client.
	 *
	 * @var AuthorizeNetClient
	 */
	protected AuthorizeNetClient $client;

	/**
	 * Gateway instance.
	 *
	 * @var AuthorizeNetGateway
	 */
	protected AuthorizeNetGateway $gateway;

	/**
	 * Constructor.
	 *
	 * @param AuthorizeNetClient  $client API client.
	 * @param AuthorizeNetGateway $gateway Gateway instance.
	 */
	public function __construct( AuthorizeNetClient $client, AuthorizeNetGateway $gateway ) {
		$this->client = $client;
		$this->gateway = $gateway;
	}

	/**
	 * Process a refund.
	 *
	 * If the transaction is unsettled, it will be voided.
	 * If the transaction is settled, it will be refunded.
	 *
	 * @since 1.0.0
	 * @param string $transaction_id Original transaction ID.
	 * @param float  $amount Amount to refund.
	 * @param string $last_four Last four digits of card.
	 * @param string $expiration Card expiration.
	 * @param string $reason Refund reason.
	 * @return array Result with success status.
	 */
	public function process_refund(
		string $transaction_id,
		float $amount,
		string $last_four,
		string $expiration = 'XXXX',
		string $reason = ''
	): array {
		$this->gateway->log( sprintf( 'Processing refund for transaction %s, amount: %s', $transaction_id, $amount ) );

		// First, check transaction status to determine if we need to void or refund.
		$details = $this->client->get_transaction_details( $transaction_id );

		if ( ! $details['success'] ) {
			return $details;
		}

		$status = $details['status'] ?? '';

		// If transaction is unsettled, void it instead.
		if ( in_array( $status, array( 'authorizedPendingCapture', 'capturedPendingSettlement' ), true ) ) {
			$this->gateway->log( sprintf( 'Transaction %s is unsettled, voiding instead of refunding', $transaction_id ) );
			return $this->void_transaction( $transaction_id );
		}

		// If transaction is settled, process refund.
		if ( 'settledSuccessfully' === $status ) {
			return $this->refund_settled_transaction( $transaction_id, $amount, $last_four, $expiration );
		}

		// Transaction is in an unrefundable state.
		return array(
			'success' => false,
			'error'   => sprintf(
				/* translators: %s: transaction status */
				__( 'Transaction cannot be refunded in its current state: %s', 'bkx-authorize-net' ),
				$status
			),
		);
	}

	/**
	 * Void an unsettled transaction.
	 *
	 * @since 1.0.0
	 * @param string $transaction_id Transaction ID to void.
	 * @return array Result with success status.
	 */
	protected function void_transaction( string $transaction_id ): array {
		$result = $this->client->void_transaction( $transaction_id );

		if ( $result['success'] ) {
			$result['refund_id'] = $result['transaction_id'];
			$result['is_void'] = true;
		}

		return $result;
	}

	/**
	 * Refund a settled transaction.
	 *
	 * @since 1.0.0
	 * @param string $transaction_id Transaction ID.
	 * @param float  $amount Amount to refund.
	 * @param string $last_four Last four digits of card.
	 * @param string $expiration Card expiration.
	 * @return array Result with success status.
	 */
	protected function refund_settled_transaction(
		string $transaction_id,
		float $amount,
		string $last_four,
		string $expiration
	): array {
		// Format last four - Authorize.net expects the card number ending.
		$card_number = str_pad( $last_four, 4, '0', STR_PAD_LEFT );

		$result = $this->client->refund_transaction(
			$transaction_id,
			$amount,
			$card_number,
			$expiration
		);

		if ( $result['success'] ) {
			$result['amount'] = $amount;
			$result['is_void'] = false;
		}

		return $result;
	}

	/**
	 * Check if a transaction can be refunded.
	 *
	 * @since 1.0.0
	 * @param string $transaction_id Transaction ID.
	 * @return array Status check result.
	 */
	public function can_refund( string $transaction_id ): array {
		$details = $this->client->get_transaction_details( $transaction_id );

		if ( ! $details['success'] ) {
			return array(
				'can_refund' => false,
				'error'      => $details['error'],
			);
		}

		$status = $details['status'] ?? '';
		$refundable_statuses = array(
			'authorizedPendingCapture',
			'capturedPendingSettlement',
			'settledSuccessfully',
		);

		if ( in_array( $status, $refundable_statuses, true ) ) {
			return array(
				'can_refund'  => true,
				'status'      => $status,
				'is_settled'  => 'settledSuccessfully' === $status,
				'needs_void'  => in_array( $status, array( 'authorizedPendingCapture', 'capturedPendingSettlement' ), true ),
			);
		}

		return array(
			'can_refund' => false,
			'status'     => $status,
			'error'      => sprintf(
				/* translators: %s: transaction status */
				__( 'Transaction cannot be refunded in its current state: %s', 'bkx-authorize-net' ),
				$status
			),
		);
	}
}

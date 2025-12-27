<?php
/**
 * Refund Processing Service
 *
 * @package BookingX\SquarePayments\Services
 */

namespace BookingX\SquarePayments\Services;

use BookingX\SquarePayments\Gateway\SquareGateway;
use BookingX\SquarePayments\Api\SquareClient;
use Square\Models\RefundPaymentRequest;
use Square\Models\Money;

/**
 * Refund service class.
 *
 * @since 1.0.0
 */
class RefundService {

	/**
	 * Gateway instance.
	 *
	 * @var SquareGateway
	 */
	protected $gateway;

	/**
	 * Square client.
	 *
	 * @var SquareClient
	 */
	protected $client;

	/**
	 * Constructor.
	 *
	 * @param SquareGateway $gateway Gateway instance.
	 * @param SquareClient  $client  Square client.
	 */
	public function __construct( SquareGateway $gateway, SquareClient $client ) {
		$this->gateway = $gateway;
		$this->client  = $client;
	}

	/**
	 * Process a refund.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id     Booking ID.
	 * @param float  $amount         Refund amount.
	 * @param string $reason         Refund reason.
	 * @param string $transaction_id Transaction ID.
	 * @return array
	 * @throws \Exception On refund failure.
	 */
	public function process_refund( int $booking_id, float $amount, string $reason = '', string $transaction_id = '' ): array {
		global $wpdb;

		// Validate client initialization.
		if ( ! $this->client->is_initialized() ) {
			throw new \Exception( __( 'Square API client is not initialized.', 'bkx-square-payments' ) );
		}

		// Get original transaction.
		$transaction = $this->get_transaction( $booking_id, $transaction_id );

		if ( ! $transaction ) {
			throw new \Exception( __( 'Original transaction not found.', 'bkx-square-payments' ) );
		}

		// Get Square payment ID.
		$square_payment_id = $transaction->square_payment_id;

		if ( empty( $square_payment_id ) ) {
			throw new \Exception( __( 'Square payment ID not found.', 'bkx-square-payments' ) );
		}

		// Generate idempotency key.
		$idempotency_key = wp_generate_password( 32, false );

		// Format amount.
		$currency = $transaction->currency;
		$amount_money = $this->format_money( $amount, $currency );

		// Prepare refund request.
		$refund_request = new RefundPaymentRequest(
			$idempotency_key,
			$amount_money
		);

		$refund_request->setPaymentId( $square_payment_id );

		if ( ! empty( $reason ) ) {
			$refund_request->setReason( $reason );
		}

		// Make API request.
		$refunds_api = $this->client->get_refunds_api();
		$api_response = $refunds_api->refundPayment( $refund_request );

		if ( $api_response->isError() ) {
			$errors = $api_response->getErrors();
			$error_message = ! empty( $errors ) ? $errors[0]->getDetail() : __( 'Refund failed.', 'bkx-square-payments' );
			throw new \Exception( $error_message );
		}

		// Get refund object.
		$refund = $api_response->getResult()->getRefund();

		// Store refund in database.
		$refund_id = $this->store_refund( $transaction->id, $refund, $reason );

		// Update booking meta.
		update_post_meta( $booking_id, '_square_refund_id', $refund->getId() );
		update_post_meta( $booking_id, '_refund_status', 'completed' );

		// Trigger action hook.
		do_action( 'bkx_square_refund_completed', $booking_id, $refund, $refund_id );

		return array(
			'success' => true,
			'data'    => array(
				'refund_id'          => $refund->getId(),
				'transaction_id'     => $transaction->id,
				'amount'             => $amount,
				'currency'           => $currency,
				'status'             => $refund->getStatus(),
			),
		);
	}

	/**
	 * Get transaction from database.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id     Booking ID.
	 * @param string $transaction_id Transaction ID (optional).
	 * @return object|null
	 */
	protected function get_transaction( int $booking_id, string $transaction_id = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_square_transactions';

		if ( ! empty( $transaction_id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$transaction_id
				)
			);
		}

		// Get most recent transaction for booking.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE booking_id = %d ORDER BY created_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$booking_id
			)
		);
	}

	/**
	 * Format money for Square API.
	 *
	 * @since 1.0.0
	 * @param float  $amount   Amount in dollars.
	 * @param string $currency Currency code.
	 * @return Money
	 */
	protected function format_money( float $amount, string $currency ): Money {
		// Zero-decimal currencies.
		$zero_decimal = array( 'JPY', 'KRW' );

		$amount_int = in_array( strtoupper( $currency ), $zero_decimal, true )
			? (int) round( $amount )
			: (int) round( $amount * 100 );

		$money = new Money();
		$money->setAmount( $amount_int );
		$money->setCurrency( strtoupper( $currency ) );

		return $money;
	}

	/**
	 * Store refund in database.
	 *
	 * @since 1.0.0
	 * @param int    $transaction_id Transaction ID.
	 * @param object $refund         Square refund object.
	 * @param string $reason         Refund reason.
	 * @return int Refund ID.
	 */
	protected function store_refund( int $transaction_id, $refund, string $reason ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_square_refunds';

		// Prepare data.
		$data = array(
			'transaction_id'   => $transaction_id,
			'square_refund_id' => $refund->getId(),
			'amount_money'     => $refund->getAmountMoney()->getAmount(),
			'reason'           => $reason,
			'status'           => $refund->getStatus(),
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );

		return (int) $wpdb->insert_id;
	}
}

<?php
/**
 * Payment Processing Service
 *
 * @package BookingX\SquarePayments\Services
 */

namespace BookingX\SquarePayments\Services;

use BookingX\SquarePayments\Gateway\SquareGateway;
use BookingX\SquarePayments\Api\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;

/**
 * Payment service class.
 *
 * @since 1.0.0
 */
class PaymentService {

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
	 * Process a payment.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $payment_data Payment data.
	 * @return array
	 * @throws \Exception On payment failure.
	 */
	public function process_payment( int $booking_id, array $payment_data ): array {
		global $wpdb;

		// Validate client initialization.
		if ( ! $this->client->is_initialized() ) {
			throw new \Exception( __( 'Square API client is not initialized.', 'bkx-square-payments' ) );
		}

		// Get booking details.
		$booking = get_post( $booking_id );
		if ( ! $booking ) {
			throw new \Exception( __( 'Invalid booking ID.', 'bkx-square-payments' ) );
		}

		// Get amount and currency.
		$amount = isset( $payment_data['amount'] ) ? floatval( $payment_data['amount'] ) : 0;
		$currency = bkx_square_payments()->get_setting( 'currency', 'USD' );

		if ( $amount <= 0 ) {
			throw new \Exception( __( 'Invalid payment amount.', 'bkx-square-payments' ) );
		}

		// Generate idempotency key.
		$idempotency_key = wp_generate_password( 32, false );

		// Format amount to smallest currency unit (cents).
		$amount_money = $this->format_money( $amount, $currency );

		// Prepare payment request.
		$payment_request = new CreatePaymentRequest(
			$payment_data['source_id'],
			$idempotency_key
		);

		$payment_request->setAmountMoney( $amount_money );
		$payment_request->setLocationId( $this->client->get_location_id() );

		// Add reference ID (booking ID).
		$payment_request->setReferenceId( 'BKX-' . $booking_id );

		// Add note.
		$payment_request->setNote(
			sprintf(
				/* translators: %d: Booking ID */
				__( 'BookingX Booking #%d', 'bkx-square-payments' ),
				$booking_id
			)
		);

		// Add verification token if provided (for SCA/3DS).
		if ( ! empty( $payment_data['verification_token'] ) ) {
			$payment_request->setVerificationToken( $payment_data['verification_token'] );
		}

		// Create customer if enabled and customer data provided.
		if ( bkx_square_payments()->get_setting( 'enable_customer_sync', false ) && ! empty( $payment_data['customer_email'] ) ) {
			$customer_service = new CustomerService( $this->gateway, $this->client );
			$square_customer_id = $customer_service->get_or_create_customer( $payment_data );

			if ( $square_customer_id ) {
				$payment_request->setCustomerId( $square_customer_id );
			}
		}

		// Make API request.
		$payments_api = $this->client->get_payments_api();
		$api_response = $payments_api->createPayment( $payment_request );

		if ( $api_response->isError() ) {
			$errors = $api_response->getErrors();
			$error_message = ! empty( $errors ) ? $errors[0]->getDetail() : __( 'Payment failed.', 'bkx-square-payments' );
			throw new \Exception( $error_message );
		}

		// Get payment object.
		$payment = $api_response->getResult()->getPayment();

		// Store transaction in database.
		$transaction_id = $this->store_transaction( $booking_id, $payment, $payment_data );

		// Update booking meta.
		update_post_meta( $booking_id, '_square_payment_id', $payment->getId() );
		update_post_meta( $booking_id, '_square_transaction_id', $transaction_id );
		update_post_meta( $booking_id, '_payment_status', 'completed' );

		// Trigger action hook.
		do_action( 'bkx_square_payment_completed', $booking_id, $payment, $transaction_id );

		return array(
			'success'        => true,
			'data'           => array(
				'payment_id'     => $payment->getId(),
				'transaction_id' => $transaction_id,
				'amount'         => $amount,
				'currency'       => $currency,
				'status'         => $payment->getStatus(),
				'receipt_url'    => $payment->getReceiptUrl(),
			),
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
	 * Store transaction in database.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id   Booking ID.
	 * @param object $payment      Square payment object.
	 * @param array  $payment_data Payment data.
	 * @return int Transaction ID.
	 */
	protected function store_transaction( int $booking_id, $payment, array $payment_data ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_square_transactions';

		// Get card details if available.
		$card_details = $payment->getCardDetails();
		$card_brand = $card_details ? $card_details->getCard()->getCardBrand() : '';
		$last_4 = $card_details ? $card_details->getCard()->getLast4() : '';

		// Prepare data.
		$data = array(
			'booking_id'         => $booking_id,
			'square_payment_id'  => $payment->getId(),
			'square_order_id'    => $payment->getOrderId(),
			'square_customer_id' => $payment->getCustomerId(),
			'amount_money'       => $payment->getAmountMoney()->getAmount(),
			'currency'           => $payment->getAmountMoney()->getCurrency(),
			'status'             => $payment->getStatus(),
			'source_type'        => $payment->getSourceType(),
			'card_brand'         => $card_brand,
			'last_4'             => $last_4,
			'receipt_url'        => $payment->getReceiptUrl(),
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );

		return (int) $wpdb->insert_id;
	}
}

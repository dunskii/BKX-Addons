<?php
/**
 * Payment Service Class
 *
 * Handles Stripe PaymentIntent creation and processing.
 *
 * @package BookingX\StripePayments\Services
 * @since   1.0.0
 */

namespace BookingX\StripePayments\Services;

use BookingX\StripePayments\StripePayments;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;

/**
 * Payment processing service.
 *
 * @since 1.0.0
 */
class PaymentService {

	/**
	 * Parent addon instance.
	 *
	 * @var StripePayments
	 */
	protected StripePayments $addon;

	/**
	 * Constructor.
	 *
	 * @param StripePayments $addon Parent addon instance.
	 */
	public function __construct( StripePayments $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Create a PaymentIntent for a booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array Result with client_secret or error.
	 */
	public function create_payment_intent( int $booking_id ): array {
		try {
			// Get booking details
			$booking = get_post( $booking_id );

			if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
				throw new \Exception( __( 'Invalid booking.', 'bkx-stripe-payments' ) );
			}

			// Get booking amount
			$amount   = (float) get_post_meta( $booking_id, 'booking_price', true );
			$currency = $this->addon->get_setting( 'currency', 'USD' );

			if ( $amount <= 0 ) {
				throw new \Exception( __( 'Invalid booking amount.', 'bkx-stripe-payments' ) );
			}

			// Get or create Stripe customer
			$customer_id = $this->addon->get_customer_service()->get_or_create_customer( $booking_id );

			// Prepare PaymentIntent data
			$intent_data = array(
				'amount'               => $this->format_amount( $amount, $currency ),
				'currency'             => strtolower( $currency ),
				'customer'             => $customer_id,
				'capture_method'       => $this->addon->get_setting( 'capture_method', 'automatic' ),
				'statement_descriptor' => $this->addon->get_setting( 'statement_descriptor', get_bloginfo( 'name' ) ),
				'metadata'             => array(
					'booking_id'   => $booking_id,
					'booking_date' => get_post_meta( $booking_id, 'booking_date', true ),
					'site_url'     => home_url(),
				),
			);

			// Enable 3D Secure if configured
			if ( $this->addon->get_setting( 'enable_3d_secure', true ) ) {
				$intent_data['payment_method_options'] = array(
					'card' => array(
						'request_three_d_secure' => 'automatic',
					),
				);
			}

			// Create PaymentIntent
			$stripe = $this->addon->get_gateway()->get_stripe_client();
			$intent = $stripe->paymentIntents->create( $intent_data );

			// Store transaction record
			$this->store_transaction( $booking_id, $intent );

			$this->addon->get_logger()->info(
				'PaymentIntent created',
				array(
					'booking_id'       => $booking_id,
					'payment_intent_id' => $intent->id,
					'amount'           => $amount,
				)
			);

			return array(
				'client_secret'     => $intent->client_secret,
				'payment_intent_id' => $intent->id,
			);

		} catch ( ApiErrorException $e ) {
			$this->addon->get_logger()->error(
				'Stripe API error creating PaymentIntent',
				array(
					'booking_id' => $booking_id,
					'error'      => $e->getMessage(),
				)
			);

			return array( 'error' => $e->getMessage() );

		} catch ( \Exception $e ) {
			$this->addon->get_logger()->error(
				'Error creating PaymentIntent',
				array(
					'booking_id' => $booking_id,
					'error'      => $e->getMessage(),
				)
			);

			return array( 'error' => $e->getMessage() );
		}
	}

	/**
	 * Process a payment with payment method.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $payment_data Payment data.
	 * @return array Result array.
	 */
	public function process_payment( int $booking_id, array $payment_data ): array {
		try {
			$stripe = $this->addon->get_gateway()->get_stripe_client();

			// If payment_intent_id is provided, confirm it
			if ( ! empty( $payment_data['payment_intent_id'] ) ) {
				$intent = $stripe->paymentIntents->retrieve( $payment_data['payment_intent_id'] );

				if ( 'succeeded' === $intent->status ) {
					// Payment already succeeded
					$this->update_transaction_status( $booking_id, $intent );
					$this->update_booking_status( $booking_id, 'bkx-ack' );

					return array(
						'payment_intent_id' => $intent->id,
						'status'            => $intent->status,
					);
				}
			}

			// Create new PaymentIntent if not provided
			if ( empty( $payment_data['payment_intent_id'] ) ) {
				$result = $this->create_payment_intent( $booking_id );

				if ( isset( $result['error'] ) ) {
					return $result;
				}

				$intent = $stripe->paymentIntents->retrieve( $result['payment_intent_id'] );
			}

			// Confirm PaymentIntent with payment method
			if ( ! empty( $payment_data['payment_method_id'] ) ) {
				$intent = $stripe->paymentIntents->confirm(
					$intent->id,
					array(
						'payment_method' => $payment_data['payment_method_id'],
					)
				);
			}

			// Update transaction with final status
			$this->update_transaction_status( $booking_id, $intent );

			// Update booking status based on payment status
			if ( 'succeeded' === $intent->status ) {
				$this->update_booking_status( $booking_id, 'bkx-ack' );
			} elseif ( 'requires_action' === $intent->status ) {
				// 3D Secure authentication required
				return array(
					'requires_action'   => true,
					'payment_intent_id' => $intent->id,
					'client_secret'     => $intent->client_secret,
				);
			}

			return array(
				'payment_intent_id' => $intent->id,
				'status'            => $intent->status,
			);

		} catch ( ApiErrorException $e ) {
			return array( 'error' => $e->getMessage() );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}

	/**
	 * Capture a previously authorized payment.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array Result array.
	 */
	public function capture_payment( int $booking_id ): array {
		try {
			global $wpdb;

			$table = $this->addon->get_table_name( 'stripe_transactions' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$transaction = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE booking_id = %d AND status = %s ORDER BY created_at DESC LIMIT 1",
					$table,
					$booking_id,
					'requires_capture'
				)
			);

			if ( ! $transaction ) {
				throw new \Exception( __( 'No authorized payment found for this booking.', 'bkx-stripe-payments' ) );
			}

			$stripe = $this->addon->get_gateway()->get_stripe_client();
			$intent = $stripe->paymentIntents->capture( $transaction->stripe_payment_intent_id );

			// Update transaction status
			$this->update_transaction_status( $booking_id, $intent );

			$this->addon->get_logger()->info(
				'Payment captured',
				array(
					'booking_id'        => $booking_id,
					'payment_intent_id' => $intent->id,
				)
			);

			return array(
				'success'           => true,
				'payment_intent_id' => $intent->id,
				'status'            => $intent->status,
			);

		} catch ( \Exception $e ) {
			$this->addon->get_logger()->error(
				'Error capturing payment',
				array(
					'booking_id' => $booking_id,
					'error'      => $e->getMessage(),
				)
			);

			return array( 'error' => $e->getMessage() );
		}
	}

	/**
	 * Store transaction record in database.
	 *
	 * @since 1.0.0
	 * @param int            $booking_id Booking ID.
	 * @param PaymentIntent  $intent     Stripe PaymentIntent.
	 * @return int|false Transaction ID or false on failure.
	 */
	protected function store_transaction( int $booking_id, PaymentIntent $intent ) {
		global $wpdb;

		$table = $this->addon->get_table_name( 'stripe_transactions' );

		$data = array(
			'booking_id'               => $booking_id,
			'stripe_payment_intent_id' => $intent->id,
			'stripe_transaction_id'    => $intent->charges->data[0]->id ?? '',
			'stripe_customer_id'       => $intent->customer ?? '',
			'amount'                   => $this->unformat_amount( $intent->amount, $intent->currency ),
			'currency'                 => strtoupper( $intent->currency ),
			'status'                   => $intent->status,
			'payment_method_type'      => $intent->charges->data[0]->payment_method_details->type ?? 'card',
			'metadata'                 => wp_json_encode( $intent->metadata ),
			'created_at'               => current_time( 'mysql' ),
			'updated_at'               => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update transaction status.
	 *
	 * @since 1.0.0
	 * @param int           $booking_id Booking ID.
	 * @param PaymentIntent $intent     Stripe PaymentIntent.
	 * @return bool Success status.
	 */
	protected function update_transaction_status( int $booking_id, PaymentIntent $intent ): bool {
		global $wpdb;

		$table = $this->addon->get_table_name( 'stripe_transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$table,
			array(
				'status'     => $intent->status,
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'stripe_payment_intent_id' => $intent->id,
			)
		);
	}

	/**
	 * Update booking status.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $status     New status.
	 * @return bool Success status.
	 */
	protected function update_booking_status( int $booking_id, string $status ): bool {
		$result = wp_update_post(
			array(
				'ID'          => $booking_id,
				'post_status' => $status,
			),
			true
		);

		return ! is_wp_error( $result );
	}

	/**
	 * Format amount for Stripe (dollars to cents).
	 *
	 * @since 1.0.0
	 * @param float  $amount   Amount in dollars.
	 * @param string $currency Currency code.
	 * @return int Amount in smallest currency unit.
	 */
	protected function format_amount( float $amount, string $currency = 'USD' ): int {
		// Zero-decimal currencies
		$zero_decimal = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

		if ( in_array( strtoupper( $currency ), $zero_decimal, true ) ) {
			return (int) round( $amount );
		}

		return (int) round( $amount * 100 );
	}

	/**
	 * Format amount from Stripe (cents to dollars).
	 *
	 * @since 1.0.0
	 * @param int    $amount   Amount in smallest currency unit.
	 * @param string $currency Currency code.
	 * @return float Amount in dollars.
	 */
	protected function unformat_amount( int $amount, string $currency = 'USD' ): float {
		$zero_decimal = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

		if ( in_array( strtoupper( $currency ), $zero_decimal, true ) ) {
			return (float) $amount;
		}

		return (float) $amount / 100;
	}
}

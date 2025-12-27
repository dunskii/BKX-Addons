<?php
/**
 * Refund Service Class
 *
 * Handles Stripe refund processing.
 *
 * @package BookingX\StripePayments\Services
 * @since   1.0.0
 */

namespace BookingX\StripePayments\Services;

use BookingX\StripePayments\StripePayments;
use Stripe\Exception\ApiErrorException;

/**
 * Refund processing service.
 *
 * @since 1.0.0
 */
class RefundService {

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
	 * Process a refund.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id     Booking ID.
	 * @param float  $amount         Refund amount (optional, full refund if not provided).
	 * @param string $reason         Refund reason.
	 * @param string $transaction_id Transaction ID (optional).
	 * @return array Result array.
	 */
	public function process_refund( int $booking_id, float $amount = 0, string $reason = '', string $transaction_id = '' ): array {
		try {
			global $wpdb;

			$table = $this->addon->get_table_name( 'stripe_transactions' );

			// Get transaction
			if ( $transaction_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$transaction = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM %i WHERE stripe_transaction_id = %s",
						$table,
						$transaction_id
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$transaction = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM %i WHERE booking_id = %d AND status = %s ORDER BY created_at DESC LIMIT 1",
						$table,
						$booking_id,
						'succeeded'
					)
				);
			}

			if ( ! $transaction ) {
				throw new \Exception( __( 'No successful payment found for this booking.', 'bkx-stripe-payments' ) );
			}

			// Prepare refund data
			$refund_data = array(
				'payment_intent' => $transaction->stripe_payment_intent_id,
				'reason'         => $reason ? $this->map_refund_reason( $reason ) : 'requested_by_customer',
				'metadata'       => array(
					'booking_id' => $booking_id,
					'site_url'   => home_url(),
				),
			);

			// Add amount if partial refund
			if ( $amount > 0 && $amount < $transaction->amount ) {
				$refund_data['amount'] = $this->format_amount( $amount, $transaction->currency );
			}

			// Create refund in Stripe
			$stripe = $this->addon->get_gateway()->get_stripe_client();
			$refund = $stripe->refunds->create( $refund_data );

			// Store refund record
			$this->store_refund( $booking_id, $refund, $transaction );

			$this->addon->get_logger()->info(
				'Refund processed',
				array(
					'booking_id' => $booking_id,
					'refund_id'  => $refund->id,
					'amount'     => $amount ?: $transaction->amount,
				)
			);

			return array(
				'success'   => true,
				'refund_id' => $refund->id,
				'status'    => $refund->status,
				'amount'    => $this->unformat_amount( $refund->amount, $refund->currency ),
			);

		} catch ( ApiErrorException $e ) {
			$this->addon->get_logger()->error(
				'Stripe API error processing refund',
				array(
					'booking_id' => $booking_id,
					'error'      => $e->getMessage(),
				)
			);

			return array( 'error' => $e->getMessage() );

		} catch ( \Exception $e ) {
			$this->addon->get_logger()->error(
				'Error processing refund',
				array(
					'booking_id' => $booking_id,
					'error'      => $e->getMessage(),
				)
			);

			return array( 'error' => $e->getMessage() );
		}
	}

	/**
	 * Process automatic refund on booking cancellation.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array Result array.
	 */
	public function process_automatic_refund( int $booking_id ): array {
		return $this->process_refund( $booking_id, 0, 'Booking cancelled by customer' );
	}

	/**
	 * Store refund record in database.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id  Booking ID.
	 * @param object $refund      Stripe Refund object.
	 * @param object $transaction Original transaction.
	 * @return int|false Refund record ID or false on failure.
	 */
	protected function store_refund( int $booking_id, object $refund, object $transaction ) {
		global $wpdb;

		$table = $this->addon->get_table_name( 'stripe_refunds' );

		$data = array(
			'booking_id'            => $booking_id,
			'transaction_id'        => $transaction->id,
			'stripe_refund_id'      => $refund->id,
			'stripe_payment_intent_id' => $refund->payment_intent,
			'amount'                => $this->unformat_amount( $refund->amount, $refund->currency ),
			'currency'              => strtoupper( $refund->currency ),
			'status'                => $refund->status,
			'reason'                => $refund->reason,
			'metadata'              => wp_json_encode( $refund->metadata ),
			'created_at'            => current_time( 'mysql' ),
			'updated_at'            => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Map refund reason to Stripe-accepted values.
	 *
	 * @since 1.0.0
	 * @param string $reason Reason text.
	 * @return string Stripe reason code.
	 */
	protected function map_refund_reason( string $reason ): string {
		$reason_lower = strtolower( $reason );

		if ( strpos( $reason_lower, 'duplicate' ) !== false ) {
			return 'duplicate';
		}

		if ( strpos( $reason_lower, 'fraud' ) !== false ) {
			return 'fraudulent';
		}

		return 'requested_by_customer';
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

<?php
/**
 * Deposit Service
 *
 * Handles deposit calculations and management.
 *
 * @package BookingX\DepositsPayments
 * @since   1.0.0
 */

namespace BookingX\DepositsPayments\Services;

use BookingX\DepositsPayments\DepositsPaymentsAddon;

/**
 * Deposit Service class.
 *
 * @since 1.0.0
 */
class DepositService {

	/**
	 * Addon instance.
	 *
	 * @var DepositsPaymentsAddon
	 */
	protected DepositsPaymentsAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param DepositsPaymentsAddon $addon Addon instance.
	 */
	public function __construct( DepositsPaymentsAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Calculate deposit amount.
	 *
	 * @since 1.0.0
	 * @param float  $total_price      Total booking price.
	 * @param string $deposit_type     Type (percentage or fixed).
	 * @param float  $deposit_value    Deposit value.
	 * @param float  $minimum_deposit  Minimum deposit amount.
	 * @return float Calculated deposit.
	 */
	public function calculate_deposit( float $total_price, string $deposit_type, float $deposit_value, float $minimum_deposit ): float {
		if ( 'percentage' === $deposit_type ) {
			$deposit = $total_price * ( $deposit_value / 100 );
		} else {
			$deposit = $deposit_value;
		}

		// Apply minimum
		$deposit = max( $deposit, $minimum_deposit );

		// Ensure deposit doesn't exceed total
		$deposit = min( $deposit, $total_price );

		return round( $deposit, 2 );
	}

	/**
	 * Create deposit record in database.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id     Booking ID.
	 * @param float $total_price    Total price.
	 * @param float $deposit_amount Deposit amount.
	 * @param float $balance_amount Balance amount.
	 * @param bool  $paid_in_full   Whether paid in full.
	 * @return int|false Record ID or false on failure.
	 */
	public function create_deposit_record( int $booking_id, float $total_price, float $deposit_amount, float $balance_amount, bool $paid_in_full ) {
		global $wpdb;

		$data = array(
			'booking_id'      => $booking_id,
			'total_price'     => $total_price,
			'deposit_amount'  => $deposit_amount,
			'balance_amount'  => $balance_amount,
			'deposit_status'  => $paid_in_full ? 'paid' : 'pending',
			'balance_status'  => $paid_in_full ? 'paid' : 'pending',
			'paid_in_full'    => $paid_in_full ? 1 : 0,
			'created_at'      => current_time( 'mysql' ),
		);

		return $this->addon->insert( 'deposits', $data );
	}

	/**
	 * Get deposit information for a booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return object|null Deposit info or null.
	 */
	public function get_deposit_info( int $booking_id ) {
		global $wpdb;

		$table = $this->addon->get_table_name( 'deposits' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE booking_id = %d LIMIT 1",
				$table,
				$booking_id
			)
		);
	}

	/**
	 * Mark payment as completed.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id   Booking ID.
	 * @param string $payment_type Payment type (deposit or balance).
	 * @return bool Success.
	 */
	public function mark_payment_completed( int $booking_id, string $payment_type ): bool {
		$field = 'deposit' === $payment_type ? 'deposit_status' : 'balance_status';

		return $this->addon->update(
			'deposits',
			array( $field => 'paid' ),
			array( 'booking_id' => $booking_id )
		) !== false;
	}

	/**
	 * Calculate refund amount based on policy.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return float Refund amount.
	 */
	public function calculate_refund_amount( int $booking_id ): float {
		$deposit_info = $this->get_deposit_info( $booking_id );

		if ( ! $deposit_info || 'paid' !== $deposit_info->deposit_status ) {
			return 0;
		}

		$refund_policy = $this->addon->get_setting( 'refund_policy', 'percentage' );

		if ( 'none' === $refund_policy ) {
			return 0;
		}

		if ( 'full' === $refund_policy ) {
			return floatval( $deposit_info->deposit_amount );
		}

		$refund_percentage = $this->addon->get_setting( 'refund_percentage', 50 );
		return floatval( $deposit_info->deposit_amount ) * ( $refund_percentage / 100 );
	}
}

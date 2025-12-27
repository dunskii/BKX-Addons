<?php
/**
 * Balance Service
 *
 * Handles balance payment processing and reminders.
 *
 * @package BookingX\DepositsPayments
 * @since   1.0.0
 */

namespace BookingX\DepositsPayments\Services;

use BookingX\DepositsPayments\DepositsPaymentsAddon;

/**
 * Balance Service class.
 *
 * @since 1.0.0
 */
class BalanceService {

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
	 * Process balance payment for a booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array Result with success and message.
	 */
	public function process_balance_payment( int $booking_id ): array {
		$deposit_info = $this->addon->get_deposit_service()->get_deposit_info( $booking_id );

		if ( ! $deposit_info ) {
			return array(
				'success' => false,
				'message' => __( 'Deposit information not found.', 'bkx-deposits-payments' ),
			);
		}

		if ( 'paid' === $deposit_info->balance_status ) {
			return array(
				'success' => false,
				'message' => __( 'Balance has already been paid.', 'bkx-deposits-payments' ),
			);
		}

		// Process payment through gateway
		// This would integrate with payment gateway
		// For now, just mark as paid
		$this->addon->get_deposit_service()->mark_payment_completed( $booking_id, 'balance' );

		return array(
			'success' => true,
			'message' => __( 'Balance payment processed successfully.', 'bkx-deposits-payments' ),
		);
	}

	/**
	 * Send balance due reminders.
	 *
	 * @since 1.0.0
	 * @param array $reminder_days Days before to send reminders.
	 * @return int Number of reminders sent.
	 */
	public function send_pending_reminders( array $reminder_days ): int {
		global $wpdb;

		$deposits_table = $this->addon->get_table_name( 'deposits' );
		$sent_count = 0;

		foreach ( $reminder_days as $days ) {
			// Find bookings with balance due in X days
			$target_date = gmdate( 'Y-m-d', strtotime( "+{$days} days" ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT d.*, p.post_date
					FROM %i d
					INNER JOIN {$wpdb->posts} p ON d.booking_id = p.ID
					WHERE d.balance_status = 'pending'
					AND DATE(p.post_date) = %s",
					$deposits_table,
					$target_date
				)
			);

			foreach ( $results as $deposit ) {
				$this->send_balance_reminder( $deposit->booking_id, $days );
				$sent_count++;
			}
		}

		return $sent_count;
	}

	/**
	 * Send balance reminder email.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @param int $days_before Days before due date.
	 * @return bool Success.
	 */
	protected function send_balance_reminder( int $booking_id, int $days_before ): bool {
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );

		if ( ! $customer_email ) {
			return false;
		}

		$deposit_info = $this->addon->get_deposit_service()->get_deposit_info( $booking_id );

		$subject = sprintf(
			/* translators: %d: days before */
			__( 'Balance Due in %d Days - Booking Reminder', 'bkx-deposits-payments' ),
			$days_before
		);

		$message = sprintf(
			/* translators: 1: days, 2: balance amount */
			__( 'Your booking balance of $%2$s is due in %1$d days. Please complete your payment to confirm your appointment.', 'bkx-deposits-payments' ),
			$days_before,
			number_format( $deposit_info->balance_amount, 2 )
		);

		return wp_mail( $customer_email, $subject, $message );
	}

	/**
	 * Get balance due date for booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return string Due date.
	 */
	public function get_balance_due_date( int $booking_id ): string {
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$balance_due_days = $this->addon->get_setting( 'balance_due_days', 7 );

		$due_date = strtotime( $booking_date . ' -' . $balance_due_days . ' days' );

		return gmdate( 'Y-m-d', $due_date );
	}
}

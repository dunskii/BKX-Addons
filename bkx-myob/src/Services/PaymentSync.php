<?php
/**
 * Payment Sync Service for MYOB Integration.
 *
 * Handles syncing payments between BookingX and MYOB.
 *
 * @package BookingX\MYOB\Services
 */

namespace BookingX\MYOB\Services;

defined( 'ABSPATH' ) || exit;

/**
 * PaymentSync class.
 */
class PaymentSync {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\MYOB\MYOBAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\MYOB\MYOBAddon $addon Parent addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Sync payment to MYOB.
	 *
	 * @param int    $booking_id     Booking ID.
	 * @param float  $amount         Payment amount.
	 * @param string $transaction_id Transaction ID.
	 * @return array|\WP_Error
	 */
	public function sync_payment( $booking_id, $amount, $transaction_id ) {
		if ( ! $this->addon->get_setting( 'sync_payments', true ) ) {
			return new \WP_Error( 'disabled', __( 'Payment sync is disabled.', 'bkx-myob' ) );
		}

		// Check if already synced.
		$myob_payment_id = get_post_meta( $booking_id, '_myob_payment_id', true );
		if ( ! empty( $myob_payment_id ) ) {
			return array( 'UID' => $myob_payment_id, 'already_synced' => true );
		}

		// Ensure invoice exists.
		$invoice_sync = $this->addon->get_service( 'invoice_sync' );
		$myob_invoice_id = $invoice_sync->get_myob_invoice_id( $booking_id );

		if ( empty( $myob_invoice_id ) ) {
			// Sync invoice first.
			$invoice_result = $invoice_sync->sync_invoice( $booking_id );
			if ( is_wp_error( $invoice_result ) ) {
				return $invoice_result;
			}
			$myob_invoice_id = $invoice_result['UID'] ?? $invoice_result['Id'] ?? null;
		}

		if ( empty( $myob_invoice_id ) ) {
			return new \WP_Error( 'no_invoice', __( 'Could not sync invoice to MYOB.', 'bkx-myob' ) );
		}

		// Get customer ID.
		$myob_customer_id = get_post_meta( $booking_id, '_myob_customer_id', true );
		if ( empty( $myob_customer_id ) ) {
			return new \WP_Error( 'no_customer', __( 'Customer not synced.', 'bkx-myob' ) );
		}

		// Create payment.
		$payment_data = $this->prepare_payment_data( $booking_id, $myob_customer_id, $myob_invoice_id, $amount, $transaction_id );
		$result       = $this->create_payment( $payment_data );

		if ( is_wp_error( $result ) ) {
			$this->log_sync( $booking_id, 'payment', null, 'failed', $result->get_error_message() );
			return $result;
		}

		// Store MYOB payment ID.
		$myob_payment_id = $result['UID'] ?? $result['Id'] ?? null;
		if ( $myob_payment_id ) {
			update_post_meta( $booking_id, '_myob_payment_id', $myob_payment_id );
			update_post_meta( $booking_id, '_myob_payment_synced', current_time( 'mysql' ) );

			$this->log_sync( $booking_id, 'payment', $myob_payment_id, 'success' );
		}

		/**
		 * Action after payment synced to MYOB.
		 *
		 * @param int    $booking_id     Booking ID.
		 * @param string $myob_payment_id MYOB payment ID.
		 * @param float  $amount         Payment amount.
		 * @param array  $result         API response.
		 */
		do_action( 'bkx_myob_payment_synced', $booking_id, $myob_payment_id, $amount, $result );

		return $result;
	}

	/**
	 * Prepare payment data.
	 *
	 * @param int    $booking_id       Booking ID.
	 * @param string $myob_customer_id MYOB customer ID.
	 * @param string $myob_invoice_id  MYOB invoice ID.
	 * @param float  $amount           Payment amount.
	 * @param string $transaction_id   Transaction ID.
	 * @return array
	 */
	private function prepare_payment_data( $booking_id, $myob_customer_id, $myob_invoice_id, $amount, $transaction_id ) {
		$api_type       = $this->addon->get_setting( 'api_type', 'essentials' );
		$payment_method = $this->addon->get_setting( 'payment_method' );

		if ( 'essentials' === $api_type ) {
			return $this->prepare_essentials_payment( $booking_id, $myob_customer_id, $myob_invoice_id, $amount, $transaction_id );
		}

		return $this->prepare_accountright_payment( $booking_id, $myob_customer_id, $myob_invoice_id, $amount, $transaction_id, $payment_method );
	}

	/**
	 * Prepare payment data for MYOB Essentials.
	 *
	 * @param int    $booking_id       Booking ID.
	 * @param string $myob_customer_id MYOB customer ID.
	 * @param string $myob_invoice_id  MYOB invoice ID.
	 * @param float  $amount           Payment amount.
	 * @param string $transaction_id   Transaction ID.
	 * @return array
	 */
	private function prepare_essentials_payment( $booking_id, $myob_customer_id, $myob_invoice_id, $amount, $transaction_id ) {
		return array(
			'contact'        => array(
				'uid' => $myob_customer_id,
			),
			'paymentDate'    => gmdate( 'Y-m-d' ),
			'invoices'       => array(
				array(
					'uid'    => $myob_invoice_id,
					'amount' => $amount,
				),
			),
			'referenceNumber' => $transaction_id,
			'notes'          => sprintf(
				/* translators: %s: transaction ID */
				__( 'Payment via BookingX - Transaction: %s', 'bkx-myob' ),
				$transaction_id
			),
		);
	}

	/**
	 * Prepare payment data for MYOB AccountRight.
	 *
	 * @param int    $booking_id       Booking ID.
	 * @param string $myob_customer_id MYOB customer ID.
	 * @param string $myob_invoice_id  MYOB invoice ID.
	 * @param float  $amount           Payment amount.
	 * @param string $transaction_id   Transaction ID.
	 * @param string $payment_method   Payment method UID.
	 * @return array
	 */
	private function prepare_accountright_payment( $booking_id, $myob_customer_id, $myob_invoice_id, $amount, $transaction_id, $payment_method ) {
		$data = array(
			'DepositTo'       => 'Account',
			'Customer'        => array(
				'UID' => $myob_customer_id,
			),
			'ReceiptNumber'   => $transaction_id,
			'Date'            => gmdate( 'Y-m-d\TH:i:s' ),
			'AmountReceived'  => $amount,
			'Invoices'        => array(
				array(
					'UID'           => $myob_invoice_id,
					'AmountApplied' => $amount,
					'Type'          => 'Invoice',
				),
			),
			'Memo'            => sprintf(
				/* translators: %s: transaction ID */
				__( 'Payment via BookingX - Transaction: %s', 'bkx-myob' ),
				$transaction_id
			),
		);

		if ( ! empty( $payment_method ) ) {
			$data['PaymentMethod'] = array(
				'UID' => $payment_method,
			);
		}

		return $data;
	}

	/**
	 * Create payment in MYOB.
	 *
	 * @param array $data Payment data.
	 * @return array|\WP_Error
	 */
	private function create_payment( $data ) {
		$api = $this->addon->get_service( 'api_client' );
		return $api->create_payment( $data );
	}

	/**
	 * Check if payment has been synced.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	public function is_payment_synced( $booking_id ) {
		$myob_payment_id = get_post_meta( $booking_id, '_myob_payment_id', true );
		return ! empty( $myob_payment_id );
	}

	/**
	 * Get MYOB payment ID for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|null
	 */
	public function get_myob_payment_id( $booking_id ) {
		return get_post_meta( $booking_id, '_myob_payment_id', true ) ?: null;
	}

	/**
	 * Log sync operation.
	 *
	 * @param int         $booking_id Booking ID.
	 * @param string      $type       Sync type.
	 * @param string|null $myob_id    MYOB ID.
	 * @param string      $status     Sync status.
	 * @param string|null $error      Error message.
	 */
	private function log_sync( $booking_id, $type, $myob_id, $status, $error = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_myob_sync_log';

		$wpdb->insert(
			$table_name,
			array(
				'booking_id'    => $booking_id,
				'myob_type'     => $type,
				'myob_id'       => $myob_id,
				'sync_status'   => $status,
				'error_message' => $error,
				'synced_at'     => 'success' === $status ? current_time( 'mysql' ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}

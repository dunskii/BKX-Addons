<?php
/**
 * Payment Sync Service for FreshBooks Integration.
 *
 * @package BookingX\FreshBooks\Services
 */

namespace BookingX\FreshBooks\Services;

defined( 'ABSPATH' ) || exit;

/**
 * PaymentSync class.
 */
class PaymentSync {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\FreshBooks\FreshBooksAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\FreshBooks\FreshBooksAddon $addon Parent addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Sync payment to FreshBooks.
	 *
	 * @param int    $booking_id     Booking ID.
	 * @param float  $amount         Payment amount.
	 * @param string $transaction_id Transaction ID.
	 * @return array|\WP_Error
	 */
	public function sync_payment( $booking_id, $amount, $transaction_id ) {
		if ( ! $this->addon->get_setting( 'sync_payments', true ) ) {
			return new \WP_Error( 'disabled', __( 'Payment sync is disabled.', 'bkx-freshbooks' ) );
		}

		// Check if already synced.
		$fb_payment_id = get_post_meta( $booking_id, '_freshbooks_payment_id', true );
		if ( ! empty( $fb_payment_id ) ) {
			return array( 'id' => $fb_payment_id, 'already_synced' => true );
		}

		// Ensure invoice exists.
		$invoice_sync  = $this->addon->get_service( 'invoice_sync' );
		$fb_invoice_id = $invoice_sync->get_fb_invoice_id( $booking_id );

		if ( empty( $fb_invoice_id ) ) {
			$invoice_result = $invoice_sync->sync_invoice( $booking_id );
			if ( is_wp_error( $invoice_result ) ) {
				return $invoice_result;
			}
			$fb_invoice_id = $invoice_result['response']['result']['invoice']['id'] ?? null;
		}

		if ( empty( $fb_invoice_id ) ) {
			return new \WP_Error( 'no_invoice', __( 'Could not sync invoice to FreshBooks.', 'bkx-freshbooks' ) );
		}

		// Get client ID.
		$fb_client_id = get_post_meta( $booking_id, '_freshbooks_client_id', true );
		if ( empty( $fb_client_id ) ) {
			return new \WP_Error( 'no_client', __( 'Client not synced.', 'bkx-freshbooks' ) );
		}

		// Create payment.
		$payment_data = $this->prepare_payment_data( $booking_id, $fb_client_id, $fb_invoice_id, $amount, $transaction_id );
		$result       = $this->create_payment( $payment_data );

		if ( is_wp_error( $result ) ) {
			$this->log_sync( $booking_id, 'payment', null, 'failed', $result->get_error_message() );
			return $result;
		}

		// Store FreshBooks payment ID.
		$fb_payment_id = $result['response']['result']['payment']['id'] ?? null;
		if ( $fb_payment_id ) {
			update_post_meta( $booking_id, '_freshbooks_payment_id', $fb_payment_id );
			update_post_meta( $booking_id, '_freshbooks_payment_synced', current_time( 'mysql' ) );

			$this->log_sync( $booking_id, 'payment', $fb_payment_id, 'success' );
		}

		/**
		 * Action after payment synced to FreshBooks.
		 *
		 * @param int    $booking_id    Booking ID.
		 * @param int    $fb_payment_id FreshBooks payment ID.
		 * @param float  $amount        Payment amount.
		 * @param array  $result        API response.
		 */
		do_action( 'bkx_freshbooks_payment_synced', $booking_id, $fb_payment_id, $amount, $result );

		return $result;
	}

	/**
	 * Prepare payment data.
	 *
	 * @param int    $booking_id     Booking ID.
	 * @param int    $fb_client_id   FreshBooks client ID.
	 * @param int    $fb_invoice_id  FreshBooks invoice ID.
	 * @param float  $amount         Payment amount.
	 * @param string $transaction_id Transaction ID.
	 * @return array
	 */
	private function prepare_payment_data( $booking_id, $fb_client_id, $fb_invoice_id, $amount, $transaction_id ) {
		return array(
			'clientid'   => $fb_client_id,
			'invoiceid'  => $fb_invoice_id,
			'amount'     => array(
				'amount' => number_format( $amount, 2, '.', '' ),
			),
			'date'       => gmdate( 'Y-m-d' ),
			'type'       => 'Credit Card',
			'note'       => sprintf(
				/* translators: %s: transaction ID */
				__( 'Payment via BookingX - Transaction: %s', 'bkx-freshbooks' ),
				$transaction_id
			),
		);
	}

	/**
	 * Create payment in FreshBooks.
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
		$fb_payment_id = get_post_meta( $booking_id, '_freshbooks_payment_id', true );
		return ! empty( $fb_payment_id );
	}

	/**
	 * Log sync operation.
	 *
	 * @param int         $booking_id Booking ID.
	 * @param string      $type       Sync type.
	 * @param int|null    $fb_id      FreshBooks ID.
	 * @param string      $status     Sync status.
	 * @param string|null $error      Error message.
	 */
	private function log_sync( $booking_id, $type, $fb_id, $status, $error = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_freshbooks_sync_log';

		$wpdb->insert(
			$table_name,
			array(
				'booking_id'    => $booking_id,
				'fb_type'       => $type,
				'fb_id'         => $fb_id,
				'sync_status'   => $status,
				'error_message' => $error,
				'synced_at'     => 'success' === $status ? current_time( 'mysql' ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}

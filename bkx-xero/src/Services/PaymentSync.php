<?php
/**
 * Xero Payment Sync Service.
 *
 * @package BookingX\Xero\Services
 * @since   1.0.0
 */

namespace BookingX\Xero\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PaymentSync Class.
 */
class PaymentSync {

	/**
	 * OAuth service.
	 *
	 * @var OAuthService
	 */
	private $oauth;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->oauth = new OAuthService();
	}

	/**
	 * Sync payment to Xero.
	 *
	 * @param int   $booking_id   BookingX booking ID.
	 * @param array $payment_data Payment data.
	 * @return array|false Result or false.
	 */
	public function sync_payment( $booking_id, $payment_data = array() ) {
		// Get invoice ID from mapping.
		$xero_invoice_id = $this->get_xero_invoice_id( $booking_id );

		if ( ! $xero_invoice_id ) {
			// Try to sync invoice first.
			$invoice_sync   = new InvoiceSync();
			$invoice_result = $invoice_sync->sync_booking( $booking_id );

			if ( ! $invoice_result || ! isset( $invoice_result['xero_id'] ) ) {
				$this->log_sync_error( $booking_id, 'payment', 'Could not find or create invoice' );
				return false;
			}

			$xero_invoice_id = $invoice_result['xero_id'];
		}

		// Get invoice details for payment.
		$invoice = $this->get_xero_invoice( $xero_invoice_id );

		if ( ! $invoice ) {
			$this->log_sync_error( $booking_id, 'payment', 'Could not get invoice details' );
			return false;
		}

		// Build payment data.
		$xero_payment = $this->build_xero_payment(
			$booking_id,
			$xero_invoice_id,
			$payment_data,
			$invoice
		);

		// Check if payment already exists.
		$existing_xero_id = $this->get_xero_payment_id( $booking_id );

		if ( $existing_xero_id ) {
			// Xero payments cannot be updated, so skip.
			return array(
				'xero_id' => $existing_xero_id,
				'synced'  => true,
				'skipped' => true,
			);
		}

		$result = $this->create_xero_payment( $xero_payment );

		if ( $result && isset( $result['PaymentID'] ) ) {
			$this->save_mapping( $booking_id, $result['PaymentID'] );
			$this->log_sync_success( $booking_id, 'payment', $result['PaymentID'] );

			// Store Xero payment ID in booking meta.
			update_post_meta( $booking_id, '_bkx_xero_payment_id', $result['PaymentID'] );

			return array(
				'xero_id' => $result['PaymentID'],
				'synced'  => true,
			);
		}

		return false;
	}

	/**
	 * Build Xero payment object.
	 *
	 * @param int    $booking_id    Booking ID.
	 * @param string $invoice_id    Xero invoice ID.
	 * @param array  $payment_data  Payment data from BookingX.
	 * @param array  $invoice       Xero invoice data.
	 * @return array Xero payment object.
	 */
	private function build_xero_payment( $booking_id, $invoice_id, $payment_data, $invoice ) {
		// Get payment amount.
		$amount = 0;

		if ( ! empty( $payment_data['amount'] ) ) {
			$amount = floatval( $payment_data['amount'] );
		} elseif ( isset( $invoice['AmountDue'] ) ) {
			$amount = floatval( $invoice['AmountDue'] );
		} else {
			$amount = floatval( get_post_meta( $booking_id, 'total_amount', true ) );
		}

		// Get bank account.
		$bank_account = $this->get_bank_account();

		$payment = array(
			'Invoice' => array( 'InvoiceID' => $invoice_id ),
			'Account' => array( 'Code' => $bank_account ),
			'Amount'  => $amount,
			'Date'    => ! empty( $payment_data['date'] )
				? gmdate( 'Y-m-d', strtotime( $payment_data['date'] ) )
				: current_time( 'Y-m-d' ),
		);

		// Add reference if available.
		if ( ! empty( $payment_data['transaction_id'] ) ) {
			$payment['Reference'] = sanitize_text_field( $payment_data['transaction_id'] );
		} else {
			$payment['Reference'] = 'BKX-PAY-' . $booking_id;
		}

		return $payment;
	}

	/**
	 * Get bank account code.
	 *
	 * @return string Bank account code.
	 */
	private function get_bank_account() {
		$account = get_option( 'bkx_xero_bank_account' );

		if ( $account ) {
			return $account;
		}

		// Try to find a bank account.
		$response = $this->oauth->api_request( 'Accounts?where=Type=="BANK"' );

		if ( $response && isset( $response['Accounts'][0]['Code'] ) ) {
			$account_code = $response['Accounts'][0]['Code'];
			update_option( 'bkx_xero_bank_account', $account_code );
			return $account_code;
		}

		return '090'; // Default bank account code.
	}

	/**
	 * Create payment in Xero.
	 *
	 * @param array $payment Payment data.
	 * @return array|false Response or false.
	 */
	private function create_xero_payment( $payment ) {
		$response = $this->oauth->api_request(
			'Payments',
			'PUT',
			array( 'Payments' => array( $payment ) )
		);

		if ( $response && isset( $response['Payments'][0] ) ) {
			return $response['Payments'][0];
		}

		return false;
	}

	/**
	 * Get invoice from Xero.
	 *
	 * @param string $xero_id Xero invoice ID.
	 * @return array|false Invoice data or false.
	 */
	private function get_xero_invoice( $xero_id ) {
		$response = $this->oauth->api_request( "Invoices/{$xero_id}" );

		if ( $response && isset( $response['Invoices'][0] ) ) {
			return $response['Invoices'][0];
		}

		return false;
	}

	/**
	 * Delete payment in Xero (set to DELETED status).
	 *
	 * @param int $booking_id BookingX booking ID.
	 * @return bool Success.
	 */
	public function delete_payment( $booking_id ) {
		$xero_id = $this->get_xero_payment_id( $booking_id );

		if ( ! $xero_id ) {
			return false;
		}

		$payment = array(
			'PaymentID' => $xero_id,
			'Status'    => 'DELETED',
		);

		$response = $this->oauth->api_request(
			'Payments/' . $xero_id,
			'POST',
			array( 'Payments' => array( $payment ) )
		);

		if ( $response && isset( $response['Payments'][0] ) ) {
			$this->log_sync_success( $booking_id, 'payment_delete', $xero_id );
			return true;
		}

		return false;
	}

	/**
	 * Get Xero invoice ID from mapping.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|false Xero invoice ID or false.
	 */
	private function get_xero_invoice_id( $booking_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_mapping';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT xero_id FROM %i WHERE entity_type = 'invoice' AND bkx_id = %d",
				$table,
				$booking_id
			)
		);
	}

	/**
	 * Get Xero payment ID from mapping.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|false Xero payment ID or false.
	 */
	private function get_xero_payment_id( $booking_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_mapping';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT xero_id FROM %i WHERE entity_type = 'payment' AND bkx_id = %d",
				$table,
				$booking_id
			)
		);
	}

	/**
	 * Save payment mapping.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $xero_id    Xero payment ID.
	 */
	private function save_mapping( $booking_id, $xero_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_mapping';

		$wpdb->replace(
			$table,
			array(
				'entity_type' => 'payment',
				'bkx_id'      => $booking_id,
				'xero_id'     => $xero_id,
				'last_synced' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Log sync success.
	 *
	 * @param int    $entity_id Entity ID.
	 * @param string $type      Sync type.
	 * @param string $xero_id   Xero ID.
	 */
	private function log_sync_success( $entity_id, $type, $xero_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_sync_log';

		$wpdb->insert(
			$table,
			array(
				'entity_type' => $type,
				'entity_id'   => $entity_id,
				'xero_id'     => $xero_id,
				'sync_type'   => 'create_or_update',
				'sync_status' => 'success',
				'synced_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Log sync error.
	 *
	 * @param int    $entity_id Entity ID.
	 * @param string $type      Entity type.
	 * @param string $message   Error message.
	 */
	private function log_sync_error( $entity_id, $type, $message ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_sync_log';

		$wpdb->insert(
			$table,
			array(
				'entity_type'   => $type,
				'entity_id'     => $entity_id,
				'sync_type'     => 'create_or_update',
				'sync_status'   => 'failed',
				'error_message' => $message,
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);

		if ( class_exists( 'BKX_Error_Logger' ) ) {
			\BKX_Error_Logger::log( "Xero Payment Sync Error ({$entity_id}): {$message}", 'error' );
		}
	}
}

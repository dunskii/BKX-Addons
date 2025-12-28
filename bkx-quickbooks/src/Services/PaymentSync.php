<?php
/**
 * QuickBooks Payment Sync Service.
 *
 * @package BookingX\QuickBooks\Services
 * @since   1.0.0
 */

namespace BookingX\QuickBooks\Services;

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
	 * Sync payment to QuickBooks.
	 *
	 * @param int   $booking_id   BookingX booking ID.
	 * @param array $payment_data Payment data.
	 * @return array|false Result or false.
	 */
	public function sync_payment( $booking_id, $payment_data = array() ) {
		// Get invoice ID from mapping.
		$qb_invoice_id = $this->get_qb_invoice_id( $booking_id );

		if ( ! $qb_invoice_id ) {
			// Try to sync invoice first.
			$invoice_sync = new InvoiceSync();
			$invoice_result = $invoice_sync->sync_booking( $booking_id );

			if ( ! $invoice_result || ! isset( $invoice_result['qb_id'] ) ) {
				$this->log_sync_error( $booking_id, 'payment', 'Could not find or create invoice' );
				return false;
			}

			$qb_invoice_id = $invoice_result['qb_id'];
		}

		// Get customer from invoice.
		$invoice = $this->get_qb_invoice( $qb_invoice_id );

		if ( ! $invoice || ! isset( $invoice['CustomerRef']['value'] ) ) {
			$this->log_sync_error( $booking_id, 'payment', 'Could not get invoice details' );
			return false;
		}

		$customer_id = $invoice['CustomerRef']['value'];

		// Build payment data.
		$qb_payment = $this->build_qb_payment(
			$booking_id,
			$qb_invoice_id,
			$customer_id,
			$payment_data,
			$invoice
		);

		// Check if payment already exists.
		$existing_qb_id = $this->get_qb_payment_id( $booking_id );

		if ( $existing_qb_id ) {
			$result = $this->update_qb_payment( $existing_qb_id, $qb_payment );
		} else {
			$result = $this->create_qb_payment( $qb_payment );
		}

		if ( $result && isset( $result['Id'] ) ) {
			$this->save_mapping( $booking_id, $result['Id'], $result['SyncToken'] ?? null );
			$this->log_sync_success( $booking_id, 'payment', $result['Id'] );

			// Store QB payment ID in booking meta.
			update_post_meta( $booking_id, '_bkx_qb_payment_id', $result['Id'] );

			return array(
				'qb_id'  => $result['Id'],
				'synced' => true,
			);
		}

		return false;
	}

	/**
	 * Build QuickBooks payment object.
	 *
	 * @param int    $booking_id    Booking ID.
	 * @param string $invoice_id    QB invoice ID.
	 * @param string $customer_id   QB customer ID.
	 * @param array  $payment_data  Payment data from BookingX.
	 * @param array  $invoice       QB invoice data.
	 * @return array QB payment object.
	 */
	private function build_qb_payment( $booking_id, $invoice_id, $customer_id, $payment_data, $invoice ) {
		// Get payment amount.
		$amount = 0;

		if ( ! empty( $payment_data['amount'] ) ) {
			$amount = floatval( $payment_data['amount'] );
		} elseif ( isset( $invoice['TotalAmt'] ) ) {
			$amount = floatval( $invoice['TotalAmt'] );
		} else {
			$amount = floatval( get_post_meta( $booking_id, 'total_amount', true ) );
		}

		// Get payment method.
		$payment_method = $payment_data['payment_method'] ?? get_post_meta( $booking_id, 'payment_method', true );
		$qb_payment_method_id = $this->map_payment_method( $payment_method );

		$payment = array(
			'CustomerRef' => array( 'value' => $customer_id ),
			'TotalAmt'    => $amount,
			'Line'        => array(
				array(
					'Amount'    => $amount,
					'LinkedTxn' => array(
						array(
							'TxnId'   => $invoice_id,
							'TxnType' => 'Invoice',
						),
					),
				),
			),
			'PrivateNote' => sprintf(
				'Payment for BookingX Booking #%d via %s',
				$booking_id,
				$payment_method ?: 'Unknown'
			),
		);

		// Add payment method if mapped.
		if ( $qb_payment_method_id ) {
			$payment['PaymentMethodRef'] = array( 'value' => $qb_payment_method_id );
		}

		// Add payment reference number if available.
		if ( ! empty( $payment_data['transaction_id'] ) ) {
			$payment['PaymentRefNum'] = sanitize_text_field( $payment_data['transaction_id'] );
		}

		// Add payment date.
		$payment['TxnDate'] = ! empty( $payment_data['date'] )
			? gmdate( 'Y-m-d', strtotime( $payment_data['date'] ) )
			: current_time( 'Y-m-d' );

		return $payment;
	}

	/**
	 * Map BookingX payment method to QuickBooks.
	 *
	 * @param string $bkx_method BookingX payment method.
	 * @return string|null QB payment method ID or null.
	 */
	private function map_payment_method( $bkx_method ) {
		// Get custom mappings from settings.
		$mappings = get_option( 'bkx_qb_payment_method_mappings', array() );

		if ( isset( $mappings[ $bkx_method ] ) ) {
			return $mappings[ $bkx_method ];
		}

		// Try to find or create payment method.
		$qb_method = $this->find_or_create_payment_method( $bkx_method );

		if ( $qb_method ) {
			$mappings[ $bkx_method ] = $qb_method;
			update_option( 'bkx_qb_payment_method_mappings', $mappings );
			return $qb_method;
		}

		return null;
	}

	/**
	 * Find or create payment method in QuickBooks.
	 *
	 * @param string $method_name Method name.
	 * @return string|null QB payment method ID.
	 */
	private function find_or_create_payment_method( $method_name ) {
		if ( empty( $method_name ) ) {
			return null;
		}

		// Standard method name mapping.
		$standard_names = array(
			'stripe'         => 'Credit Card',
			'paypal'         => 'PayPal',
			'square'         => 'Credit Card',
			'authorize_net'  => 'Credit Card',
			'razorpay'       => 'Credit Card',
			'cash'           => 'Cash',
			'check'          => 'Check',
			'bank_transfer'  => 'Bank Transfer',
		);

		$search_name = $standard_names[ strtolower( $method_name ) ] ?? ucwords( str_replace( '_', ' ', $method_name ) );

		// Search for existing payment method.
		$query    = "SELECT * FROM PaymentMethod WHERE Name = '{$search_name}'";
		$response = $this->oauth->api_request( 'query?query=' . rawurlencode( $query ) );

		if ( $response && isset( $response['QueryResponse']['PaymentMethod'][0]['Id'] ) ) {
			return $response['QueryResponse']['PaymentMethod'][0]['Id'];
		}

		// Create new payment method.
		$new_method = array(
			'Name'   => $search_name,
			'Type'   => $this->get_payment_method_type( $method_name ),
			'Active' => true,
		);

		$create_response = $this->oauth->api_request( 'paymentmethod', 'POST', $new_method );

		if ( $create_response && isset( $create_response['PaymentMethod']['Id'] ) ) {
			return $create_response['PaymentMethod']['Id'];
		}

		return null;
	}

	/**
	 * Get payment method type for QuickBooks.
	 *
	 * @param string $method Method name.
	 * @return string QB payment method type.
	 */
	private function get_payment_method_type( $method ) {
		$card_methods = array( 'stripe', 'paypal', 'square', 'authorize_net', 'razorpay', 'credit_card' );

		if ( in_array( strtolower( $method ), $card_methods, true ) ) {
			return 'CREDIT_CARD';
		}

		return 'NON_CREDIT_CARD';
	}

	/**
	 * Create payment in QuickBooks.
	 *
	 * @param array $payment Payment data.
	 * @return array|false Response or false.
	 */
	private function create_qb_payment( $payment ) {
		$response = $this->oauth->api_request( 'payment', 'POST', $payment );

		if ( $response && isset( $response['Payment'] ) ) {
			return $response['Payment'];
		}

		return false;
	}

	/**
	 * Update payment in QuickBooks.
	 *
	 * @param string $qb_id   QuickBooks payment ID.
	 * @param array  $payment Payment data.
	 * @return array|false Response or false.
	 */
	private function update_qb_payment( $qb_id, $payment ) {
		$current = $this->get_qb_payment( $qb_id );

		if ( ! $current ) {
			return false;
		}

		$payment['Id']        = $qb_id;
		$payment['SyncToken'] = $current['SyncToken'];
		$payment['sparse']    = true;

		$response = $this->oauth->api_request( 'payment', 'POST', $payment );

		if ( $response && isset( $response['Payment'] ) ) {
			return $response['Payment'];
		}

		return false;
	}

	/**
	 * Get payment from QuickBooks.
	 *
	 * @param string $qb_id QuickBooks payment ID.
	 * @return array|false Payment data or false.
	 */
	public function get_qb_payment( $qb_id ) {
		$response = $this->oauth->api_request( "payment/{$qb_id}" );

		if ( $response && isset( $response['Payment'] ) ) {
			return $response['Payment'];
		}

		return false;
	}

	/**
	 * Void payment in QuickBooks.
	 *
	 * @param int $booking_id BookingX booking ID.
	 * @return bool Success.
	 */
	public function void_payment( $booking_id ) {
		$qb_id = $this->get_qb_payment_id( $booking_id );

		if ( ! $qb_id ) {
			return false;
		}

		$current = $this->get_qb_payment( $qb_id );

		if ( ! $current ) {
			return false;
		}

		$void_data = array(
			'Id'        => $qb_id,
			'SyncToken' => $current['SyncToken'],
		);

		$response = $this->oauth->api_request( 'payment?operation=void', 'POST', $void_data );

		if ( $response && isset( $response['Payment'] ) ) {
			$this->log_sync_success( $booking_id, 'payment_void', $qb_id );
			return true;
		}

		return false;
	}

	/**
	 * Get invoice from QuickBooks.
	 *
	 * @param string $qb_id QuickBooks invoice ID.
	 * @return array|false Invoice data or false.
	 */
	private function get_qb_invoice( $qb_id ) {
		$response = $this->oauth->api_request( "invoice/{$qb_id}" );

		if ( $response && isset( $response['Invoice'] ) ) {
			return $response['Invoice'];
		}

		return false;
	}

	/**
	 * Get QB invoice ID from mapping.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|false QB invoice ID or false.
	 */
	private function get_qb_invoice_id( $booking_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_mapping';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT qb_id FROM %i WHERE entity_type = 'invoice' AND bkx_id = %d",
				$table,
				$booking_id
			)
		);
	}

	/**
	 * Get QB payment ID from mapping.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|false QB payment ID or false.
	 */
	private function get_qb_payment_id( $booking_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_mapping';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT qb_id FROM %i WHERE entity_type = 'payment' AND bkx_id = %d",
				$table,
				$booking_id
			)
		);
	}

	/**
	 * Save payment mapping.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $qb_id      QuickBooks payment ID.
	 * @param string $sync_token QB sync token.
	 */
	private function save_mapping( $booking_id, $qb_id, $sync_token = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_mapping';

		$wpdb->replace(
			$table,
			array(
				'entity_type'   => 'payment',
				'bkx_id'        => $booking_id,
				'qb_id'         => $qb_id,
				'qb_sync_token' => $sync_token,
				'last_synced'   => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Log sync success.
	 *
	 * @param int    $entity_id Entity ID.
	 * @param string $type      Sync type.
	 * @param string $qb_id     QuickBooks ID.
	 */
	private function log_sync_success( $entity_id, $type, $qb_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_sync_log';

		$wpdb->insert(
			$table,
			array(
				'entity_type' => $type,
				'entity_id'   => $entity_id,
				'qb_id'       => $qb_id,
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
		$table = $wpdb->prefix . 'bkx_qb_sync_log';

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
			\BKX_Error_Logger::log( "QB Payment Sync Error ({$entity_id}): {$message}", 'error' );
		}
	}
}

<?php
/**
 * Invoice Sync Service for MYOB Integration.
 *
 * Handles syncing invoices between BookingX and MYOB.
 *
 * @package BookingX\MYOB\Services
 */

namespace BookingX\MYOB\Services;

defined( 'ABSPATH' ) || exit;

/**
 * InvoiceSync class.
 */
class InvoiceSync {

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
	 * Sync invoice from booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array|\WP_Error
	 */
	public function sync_invoice( $booking_id ) {
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error( 'invalid_booking', __( 'Invalid booking.', 'bkx-myob' ) );
		}

		// Check if already synced.
		$myob_invoice_id = get_post_meta( $booking_id, '_myob_invoice_id', true );
		if ( ! empty( $myob_invoice_id ) ) {
			// Update existing invoice.
			return $this->update_invoice( $booking_id, $myob_invoice_id );
		}

		// Ensure customer is synced first.
		$customer_sync = $this->addon->get_service( 'customer_sync' );
		$myob_customer_id = $customer_sync->get_myob_customer_id( $booking_id );

		if ( empty( $myob_customer_id ) ) {
			// Sync customer first.
			$customer_result = $customer_sync->sync_customer( $booking_id );
			if ( is_wp_error( $customer_result ) ) {
				return $customer_result;
			}
			$myob_customer_id = $customer_result['UID'] ?? $customer_result['Id'] ?? null;
		}

		if ( empty( $myob_customer_id ) ) {
			return new \WP_Error( 'no_customer', __( 'Could not sync customer to MYOB.', 'bkx-myob' ) );
		}

		// Create invoice.
		$invoice_data = $this->prepare_invoice_data( $booking_id, $myob_customer_id );
		$result       = $this->create_invoice( $invoice_data );

		if ( is_wp_error( $result ) ) {
			$this->log_sync( $booking_id, 'invoice', null, null, 'failed', $result->get_error_message() );
			return $result;
		}

		// Store MYOB invoice ID.
		$myob_id     = $result['UID'] ?? $result['Id'] ?? null;
		$myob_number = $result['Number'] ?? $result['InvoiceNumber'] ?? null;

		if ( $myob_id ) {
			update_post_meta( $booking_id, '_myob_invoice_id', $myob_id );
			update_post_meta( $booking_id, '_myob_invoice_number', $myob_number );
			update_post_meta( $booking_id, '_myob_synced', current_time( 'mysql' ) );

			$this->log_sync( $booking_id, 'invoice', $myob_id, $myob_number, 'success' );
		}

		/**
		 * Action after invoice synced to MYOB.
		 *
		 * @param int    $booking_id Booking ID.
		 * @param string $myob_id    MYOB invoice ID.
		 * @param array  $result     API response.
		 */
		do_action( 'bkx_myob_invoice_synced', $booking_id, $myob_id, $result );

		return $result;
	}

	/**
	 * Prepare invoice data from booking.
	 *
	 * @param int    $booking_id       Booking ID.
	 * @param string $myob_customer_id MYOB customer ID.
	 * @return array
	 */
	private function prepare_invoice_data( $booking_id, $myob_customer_id ) {
		$total_amount   = (float) get_post_meta( $booking_id, 'total_amount', true );
		$booking_date   = get_post_meta( $booking_id, 'booking_date', true );
		$base_id        = get_post_meta( $booking_id, 'base_id', true );
		$invoice_prefix = $this->addon->get_setting( 'invoice_prefix', 'BKX-' );
		$income_account = $this->addon->get_setting( 'default_income_account' );
		$tax_code       = $this->addon->get_setting( 'default_tax_code' );

		// Get service name.
		$service_name = __( 'Booking Service', 'bkx-myob' );
		if ( $base_id ) {
			$service = get_post( $base_id );
			if ( $service ) {
				$service_name = $service->post_title;
			}
		}

		$api_type = $this->addon->get_setting( 'api_type', 'essentials' );

		if ( 'essentials' === $api_type ) {
			return $this->prepare_essentials_invoice( $booking_id, $myob_customer_id, array(
				'total_amount'   => $total_amount,
				'booking_date'   => $booking_date,
				'service_name'   => $service_name,
				'invoice_prefix' => $invoice_prefix,
				'income_account' => $income_account,
				'tax_code'       => $tax_code,
			) );
		}

		return $this->prepare_accountright_invoice( $booking_id, $myob_customer_id, array(
			'total_amount'   => $total_amount,
			'booking_date'   => $booking_date,
			'service_name'   => $service_name,
			'invoice_prefix' => $invoice_prefix,
			'income_account' => $income_account,
			'tax_code'       => $tax_code,
		) );
	}

	/**
	 * Prepare invoice data for MYOB Essentials.
	 *
	 * @param int    $booking_id       Booking ID.
	 * @param string $myob_customer_id MYOB customer ID.
	 * @param array  $params           Invoice parameters.
	 * @return array
	 */
	private function prepare_essentials_invoice( $booking_id, $myob_customer_id, $params ) {
		$invoice_date = ! empty( $params['booking_date'] )
			? gmdate( 'Y-m-d', strtotime( $params['booking_date'] ) )
			: gmdate( 'Y-m-d' );

		$due_date = gmdate( 'Y-m-d', strtotime( $invoice_date . ' +14 days' ) );

		return array(
			'contact'     => array(
				'uid' => $myob_customer_id,
			),
			'number'      => $params['invoice_prefix'] . $booking_id,
			'issueDate'   => $invoice_date,
			'dueDate'     => $due_date,
			'status'      => 'Open',
			'lines'       => array(
				array(
					'description' => $params['service_name'],
					'quantity'    => 1,
					'unitPrice'   => $params['total_amount'],
					'account'     => ! empty( $params['income_account'] ) ? array( 'uid' => $params['income_account'] ) : null,
					'taxCode'     => ! empty( $params['tax_code'] ) ? array( 'uid' => $params['tax_code'] ) : null,
				),
			),
			'notes'       => sprintf(
				/* translators: %d: booking ID */
				__( 'Booking #%d', 'bkx-myob' ),
				$booking_id
			),
		);
	}

	/**
	 * Prepare invoice data for MYOB AccountRight.
	 *
	 * @param int    $booking_id       Booking ID.
	 * @param string $myob_customer_id MYOB customer ID.
	 * @param array  $params           Invoice parameters.
	 * @return array
	 */
	private function prepare_accountright_invoice( $booking_id, $myob_customer_id, $params ) {
		$invoice_date = ! empty( $params['booking_date'] )
			? gmdate( 'Y-m-d\TH:i:s', strtotime( $params['booking_date'] ) )
			: gmdate( 'Y-m-d\TH:i:s' );

		return array(
			'Customer'        => array(
				'UID' => $myob_customer_id,
			),
			'Number'          => $params['invoice_prefix'] . $booking_id,
			'Date'            => $invoice_date,
			'CustomerPurchaseOrderNumber' => 'BKX-' . $booking_id,
			'Lines'           => array(
				array(
					'Type'        => 'Transaction',
					'Description' => $params['service_name'],
					'Total'       => $params['total_amount'],
					'Account'     => ! empty( $params['income_account'] ) ? array( 'UID' => $params['income_account'] ) : null,
					'TaxCode'     => ! empty( $params['tax_code'] ) ? array( 'UID' => $params['tax_code'] ) : null,
				),
			),
			'Comment'         => sprintf(
				/* translators: %d: booking ID */
				__( 'Booking #%d', 'bkx-myob' ),
				$booking_id
			),
			'ShippingMethod'  => '',
			'JournalMemo'     => 'BookingX Invoice',
			'IsTaxInclusive'  => true,
		);
	}

	/**
	 * Create invoice in MYOB.
	 *
	 * @param array $data Invoice data.
	 * @return array|\WP_Error
	 */
	private function create_invoice( $data ) {
		$api = $this->addon->get_service( 'api_client' );
		return $api->create_invoice( $data );
	}

	/**
	 * Update invoice in MYOB.
	 *
	 * @param int    $booking_id      Booking ID.
	 * @param string $myob_invoice_id MYOB invoice ID.
	 * @return array|\WP_Error
	 */
	private function update_invoice( $booking_id, $myob_invoice_id ) {
		$myob_customer_id = get_post_meta( $booking_id, '_myob_customer_id', true );
		if ( empty( $myob_customer_id ) ) {
			return new \WP_Error( 'no_customer', __( 'Customer not synced.', 'bkx-myob' ) );
		}

		$invoice_data = $this->prepare_invoice_data( $booking_id, $myob_customer_id );

		$api    = $this->addon->get_service( 'api_client' );
		$result = $api->update_invoice( $myob_invoice_id, $invoice_data );

		if ( is_wp_error( $result ) ) {
			$this->log_sync( $booking_id, 'invoice', $myob_invoice_id, null, 'failed', $result->get_error_message() );
			return $result;
		}

		update_post_meta( $booking_id, '_myob_synced', current_time( 'mysql' ) );
		$this->log_sync( $booking_id, 'invoice', $myob_invoice_id, null, 'success' );

		return $result;
	}

	/**
	 * Get MYOB invoice ID for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|null
	 */
	public function get_myob_invoice_id( $booking_id ) {
		return get_post_meta( $booking_id, '_myob_invoice_id', true ) ?: null;
	}

	/**
	 * Log sync operation.
	 *
	 * @param int         $booking_id   Booking ID.
	 * @param string      $type         Sync type.
	 * @param string|null $myob_id      MYOB ID.
	 * @param string|null $myob_number  MYOB number.
	 * @param string      $status       Sync status.
	 * @param string|null $error        Error message.
	 */
	private function log_sync( $booking_id, $type, $myob_id, $myob_number, $status, $error = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_myob_sync_log';

		$wpdb->insert(
			$table_name,
			array(
				'booking_id'    => $booking_id,
				'myob_type'     => $type,
				'myob_id'       => $myob_id,
				'myob_number'   => $myob_number,
				'sync_status'   => $status,
				'error_message' => $error,
				'synced_at'     => 'success' === $status ? current_time( 'mysql' ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}

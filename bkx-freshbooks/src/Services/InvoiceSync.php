<?php
/**
 * Invoice Sync Service for FreshBooks Integration.
 *
 * @package BookingX\FreshBooks\Services
 */

namespace BookingX\FreshBooks\Services;

defined( 'ABSPATH' ) || exit;

/**
 * InvoiceSync class.
 */
class InvoiceSync {

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
	 * Sync invoice from booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array|\WP_Error
	 */
	public function sync_invoice( $booking_id ) {
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error( 'invalid_booking', __( 'Invalid booking.', 'bkx-freshbooks' ) );
		}

		// Check if already synced.
		$fb_invoice_id = get_post_meta( $booking_id, '_freshbooks_invoice_id', true );
		if ( ! empty( $fb_invoice_id ) ) {
			return $this->update_invoice( $booking_id, $fb_invoice_id );
		}

		// Ensure client is synced.
		$client_sync  = $this->addon->get_service( 'client_sync' );
		$fb_client_id = $client_sync->get_fb_client_id( $booking_id );

		if ( empty( $fb_client_id ) ) {
			$client_result = $client_sync->sync_client( $booking_id );
			if ( is_wp_error( $client_result ) ) {
				return $client_result;
			}
			$fb_client_id = $client_result['response']['result']['client']['id'] ?? null;
		}

		if ( empty( $fb_client_id ) ) {
			return new \WP_Error( 'no_client', __( 'Could not sync client to FreshBooks.', 'bkx-freshbooks' ) );
		}

		// Create invoice.
		$invoice_data = $this->prepare_invoice_data( $booking_id, $fb_client_id );
		$result       = $this->create_invoice( $invoice_data );

		if ( is_wp_error( $result ) ) {
			$this->log_sync( $booking_id, 'invoice', null, null, 'failed', $result->get_error_message() );
			return $result;
		}

		// Store FreshBooks invoice ID.
		$fb_id     = $result['response']['result']['invoice']['id'] ?? null;
		$fb_number = $result['response']['result']['invoice']['invoice_number'] ?? null;

		if ( $fb_id ) {
			update_post_meta( $booking_id, '_freshbooks_invoice_id', $fb_id );
			update_post_meta( $booking_id, '_freshbooks_invoice_number', $fb_number );
			update_post_meta( $booking_id, '_freshbooks_synced', current_time( 'mysql' ) );

			$this->log_sync( $booking_id, 'invoice', $fb_id, $fb_number, 'success' );

			// Send invoice email if enabled.
			if ( $this->addon->get_setting( 'send_invoice_email', false ) ) {
				$api = $this->addon->get_service( 'api_client' );
				$api->send_invoice( $fb_id );
			}
		}

		/**
		 * Action after invoice synced to FreshBooks.
		 *
		 * @param int    $booking_id Booking ID.
		 * @param int    $fb_id      FreshBooks invoice ID.
		 * @param array  $result     API response.
		 */
		do_action( 'bkx_freshbooks_invoice_synced', $booking_id, $fb_id, $result );

		return $result;
	}

	/**
	 * Prepare invoice data from booking.
	 *
	 * @param int $booking_id   Booking ID.
	 * @param int $fb_client_id FreshBooks client ID.
	 * @return array
	 */
	private function prepare_invoice_data( $booking_id, $fb_client_id ) {
		$total_amount = (float) get_post_meta( $booking_id, 'total_amount', true );
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$base_id      = get_post_meta( $booking_id, 'base_id', true );
		$due_days     = $this->addon->get_setting( 'invoice_due_days', 14 );

		// Get service name.
		$service_name = __( 'Booking Service', 'bkx-freshbooks' );
		if ( $base_id ) {
			$service = get_post( $base_id );
			if ( $service ) {
				$service_name = $service->post_title;
			}
		}

		$create_date = ! empty( $booking_date )
			? gmdate( 'Y-m-d', strtotime( $booking_date ) )
			: gmdate( 'Y-m-d' );

		$due_date = gmdate( 'Y-m-d', strtotime( $create_date . " +{$due_days} days" ) );

		return array(
			'customerid'   => $fb_client_id,
			'create_date'  => $create_date,
			'due_offset_days' => $due_days,
			'lines'        => array(
				array(
					'type'        => 0,
					'name'        => $service_name,
					'description' => sprintf(
						/* translators: %d: booking ID */
						__( 'Booking #%d', 'bkx-freshbooks' ),
						$booking_id
					),
					'qty'         => 1,
					'unit_cost'   => array(
						'amount' => number_format( $total_amount, 2, '.', '' ),
					),
				),
			),
			'notes'        => sprintf(
				/* translators: %d: booking ID */
				__( 'Invoice for Booking #%d', 'bkx-freshbooks' ),
				$booking_id
			),
		);
	}

	/**
	 * Create invoice in FreshBooks.
	 *
	 * @param array $data Invoice data.
	 * @return array|\WP_Error
	 */
	private function create_invoice( $data ) {
		$api = $this->addon->get_service( 'api_client' );
		return $api->create_invoice( $data );
	}

	/**
	 * Update invoice in FreshBooks.
	 *
	 * @param int $booking_id    Booking ID.
	 * @param int $fb_invoice_id FreshBooks invoice ID.
	 * @return array|\WP_Error
	 */
	private function update_invoice( $booking_id, $fb_invoice_id ) {
		$fb_client_id = get_post_meta( $booking_id, '_freshbooks_client_id', true );
		if ( empty( $fb_client_id ) ) {
			return new \WP_Error( 'no_client', __( 'Client not synced.', 'bkx-freshbooks' ) );
		}

		$invoice_data = $this->prepare_invoice_data( $booking_id, $fb_client_id );

		$api    = $this->addon->get_service( 'api_client' );
		$result = $api->update_invoice( $fb_invoice_id, $invoice_data );

		if ( is_wp_error( $result ) ) {
			$this->log_sync( $booking_id, 'invoice', $fb_invoice_id, null, 'failed', $result->get_error_message() );
			return $result;
		}

		update_post_meta( $booking_id, '_freshbooks_synced', current_time( 'mysql' ) );
		$this->log_sync( $booking_id, 'invoice', $fb_invoice_id, null, 'success' );

		return $result;
	}

	/**
	 * Get FreshBooks invoice ID for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return int|null
	 */
	public function get_fb_invoice_id( $booking_id ) {
		$fb_id = get_post_meta( $booking_id, '_freshbooks_invoice_id', true );
		return ! empty( $fb_id ) ? (int) $fb_id : null;
	}

	/**
	 * Log sync operation.
	 *
	 * @param int         $booking_id Booking ID.
	 * @param string      $type       Sync type.
	 * @param int|null    $fb_id      FreshBooks ID.
	 * @param string|null $fb_number  FreshBooks number.
	 * @param string      $status     Sync status.
	 * @param string|null $error      Error message.
	 */
	private function log_sync( $booking_id, $type, $fb_id, $fb_number, $status, $error = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_freshbooks_sync_log';

		$wpdb->insert(
			$table_name,
			array(
				'booking_id'    => $booking_id,
				'fb_type'       => $type,
				'fb_id'         => $fb_id,
				'fb_number'     => $fb_number,
				'sync_status'   => $status,
				'error_message' => $error,
				'synced_at'     => 'success' === $status ? current_time( 'mysql' ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}

<?php
/**
 * PayPal Refund Service
 *
 * Handles refund processing.
 *
 * @package BookingX\PayPalPro\Services
 * @since   1.0.0
 */

namespace BookingX\PayPalPro\Services;

use BookingX\PayPalPro\Api\PayPalClient;
use BookingX\PayPalPro\Gateway\PayPalGateway;

/**
 * Refund service class.
 *
 * @since 1.0.0
 */
class RefundService {

	/**
	 * PayPal API client.
	 *
	 * @var PayPalClient
	 */
	protected PayPalClient $client;

	/**
	 * Gateway instance.
	 *
	 * @var PayPalGateway
	 */
	protected PayPalGateway $gateway;

	/**
	 * Constructor.
	 *
	 * @param PayPalClient  $client  PayPal client.
	 * @param PayPalGateway $gateway Gateway instance.
	 */
	public function __construct( PayPalClient $client, PayPalGateway $gateway ) {
		$this->client  = $client;
		$this->gateway = $gateway;
	}

	/**
	 * Refund a captured payment.
	 *
	 * @since 1.0.0
	 * @param string $capture_id PayPal capture ID.
	 * @param float  $amount     Refund amount (empty for full refund).
	 * @param string $reason     Refund reason.
	 * @return array Result with 'success' and 'data' or 'error'.
	 */
	public function refund_capture( string $capture_id, float $amount = 0.0, string $reason = '' ): array {
		try {
			// Get capture details to validate amount.
			$capture_response = $this->client->get_capture( $capture_id );

			if ( ! $capture_response['success'] ) {
				throw new \Exception( $capture_response['error'] ?? __( 'Failed to retrieve capture details.', 'bkx-paypal-pro' ) );
			}

			$capture  = $capture_response['data'];
			$currency = $capture['amount']['currency_code'] ?? 'USD';
			$max_amount = (float) ( $capture['amount']['value'] ?? 0.0 );

			// Build refund data.
			$refund_data = array();

			if ( $amount > 0 ) {
				if ( $amount > $max_amount ) {
					throw new \Exception(
						sprintf(
							/* translators: 1: requested amount, 2: maximum amount */
							__( 'Refund amount (%1$s) exceeds captured amount (%2$s).', 'bkx-paypal-pro' ),
							$amount,
							$max_amount
						)
					);
				}

				$refund_data['amount'] = array(
					'currency_code' => $currency,
					'value'         => number_format( $amount, 2, '.', '' ),
				);
			}

			if ( ! empty( $reason ) ) {
				$refund_data['note_to_payer'] = sanitize_text_field( $reason );
			}

			// Allow filtering of refund data.
			$refund_data = apply_filters( 'bkx_paypal_pro_refund_data', $refund_data, $capture_id, $amount, $reason );

			// Process the refund.
			$response = $this->client->refund_capture( $capture_id, $refund_data );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? __( 'Failed to process refund.', 'bkx-paypal-pro' ) );
			}

			$refund = $response['data'];

			// Save refund record.
			$this->save_refund_record( $capture_id, $refund );

			return array(
				'success' => true,
				'data'    => array(
					'refund_id'  => $refund['id'] ?? '',
					'status'     => $refund['status'] ?? 'PENDING',
					'amount'     => $refund['amount']['value'] ?? '',
					'currency'   => $refund['amount']['currency_code'] ?? '',
					'create_time' => $refund['create_time'] ?? '',
				),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Save refund record to database.
	 *
	 * @since 1.0.0
	 * @param string $capture_id Capture ID.
	 * @param array  $refund     Refund data from PayPal.
	 * @return int|false Insert ID or false on failure.
	 */
	protected function save_refund_record( string $capture_id, array $refund ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_paypal_transactions';

		// Get booking ID from capture.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$booking_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT booking_id FROM {$table} WHERE capture_id = %s LIMIT 1",
				$capture_id
			)
		);

		if ( ! $booking_id ) {
			return false;
		}

		$data = array(
			'booking_id'      => $booking_id,
			'paypal_order_id' => 'refund_' . ( $refund['id'] ?? uniqid() ),
			'capture_id'      => $capture_id,
			'amount'          => '-' . ( $refund['amount']['value'] ?? '0.00' ),
			'currency'        => $refund['amount']['currency_code'] ?? '',
			'status'          => 'refunded',
			'payment_source'  => wp_json_encode( array( 'refund_id' => $refund['id'] ?? '' ) ),
			'metadata'        => wp_json_encode( $refund ),
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );

		// Update original transaction.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'status' => 'refunded', 'updated_at' => current_time( 'mysql' ) ),
			array( 'capture_id' => $capture_id )
		);

		return $wpdb->insert_id;
	}
}

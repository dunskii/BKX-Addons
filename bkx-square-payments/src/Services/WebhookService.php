<?php
/**
 * Webhook Processing Service
 *
 * @package BookingX\SquarePayments\Services
 */

namespace BookingX\SquarePayments\Services;

use BookingX\SquarePayments\Gateway\SquareGateway;
use BookingX\SquarePayments\Api\SquareClient;

/**
 * Webhook service class.
 *
 * @since 1.0.0
 */
class WebhookService {

	/**
	 * Gateway instance.
	 *
	 * @var SquareGateway
	 */
	protected $gateway;

	/**
	 * Square client.
	 *
	 * @var SquareClient
	 */
	protected $client;

	/**
	 * Constructor.
	 *
	 * @param SquareGateway $gateway Gateway instance.
	 * @param SquareClient  $client  Square client.
	 */
	public function __construct( SquareGateway $gateway, SquareClient $client ) {
		$this->gateway = $gateway;
		$this->client  = $client;
	}

	/**
	 * Process webhook event.
	 *
	 * @since 1.0.0
	 * @param array $payload Webhook payload.
	 * @return array
	 */
	public function process_webhook( array $payload ): array {
		// Get event type.
		$event_type = $payload['type'] ?? '';

		if ( empty( $event_type ) ) {
			return array(
				'success' => false,
				'message' => __( 'Missing event type.', 'bkx-square-payments' ),
			);
		}

		// Log webhook event.
		$this->log_webhook_event( $payload );

		// Route to appropriate handler.
		switch ( $event_type ) {
			case 'payment.created':
			case 'payment.updated':
				return $this->handle_payment_event( $payload );

			case 'refund.created':
			case 'refund.updated':
				return $this->handle_refund_event( $payload );

			default:
				return array(
					'success' => true,
					'message' => sprintf(
						/* translators: %s: Event type */
						__( 'Event type %s not handled.', 'bkx-square-payments' ),
						$event_type
					),
				);
		}
	}

	/**
	 * Handle payment webhook event.
	 *
	 * @since 1.0.0
	 * @param array $payload Webhook payload.
	 * @return array
	 */
	protected function handle_payment_event( array $payload ): array {
		global $wpdb;

		// Get payment data from payload.
		$payment_data = $payload['data']['object']['payment'] ?? null;

		if ( ! $payment_data ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid payment data.', 'bkx-square-payments' ),
			);
		}

		$square_payment_id = $payment_data['id'] ?? '';
		$status = $payment_data['status'] ?? '';

		if ( empty( $square_payment_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Missing payment ID.', 'bkx-square-payments' ),
			);
		}

		// Find transaction in database.
		$table = $wpdb->prefix . 'bkx_square_transactions';

		// Use %i placeholder for table identifier - SECURITY.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transaction = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE square_payment_id = %s',
				$table,
				$square_payment_id
			)
		);

		if ( ! $transaction ) {
			return array(
				'success' => false,
				'message' => __( 'Transaction not found.', 'bkx-square-payments' ),
			);
		}

		// Update transaction status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $transaction->id )
		);

		// Update booking meta.
		update_post_meta( $transaction->booking_id, '_square_payment_status', $status );

		// Trigger action hook.
		do_action( 'bkx_square_payment_webhook', $transaction->booking_id, $status, $payment_data );

		return array(
			'success' => true,
			'message' => __( 'Payment webhook processed.', 'bkx-square-payments' ),
		);
	}

	/**
	 * Handle refund webhook event.
	 *
	 * @since 1.0.0
	 * @param array $payload Webhook payload.
	 * @return array
	 */
	protected function handle_refund_event( array $payload ): array {
		global $wpdb;

		// Get refund data from payload.
		$refund_data = $payload['data']['object']['refund'] ?? null;

		if ( ! $refund_data ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid refund data.', 'bkx-square-payments' ),
			);
		}

		$square_refund_id = $refund_data['id'] ?? '';
		$status = $refund_data['status'] ?? '';

		if ( empty( $square_refund_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Missing refund ID.', 'bkx-square-payments' ),
			);
		}

		// Find refund in database.
		$table = $wpdb->prefix . 'bkx_square_refunds';

		// Use %i placeholder for table identifier - SECURITY.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$refund = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE square_refund_id = %s',
				$table,
				$square_refund_id
			)
		);

		if ( ! $refund ) {
			return array(
				'success' => false,
				'message' => __( 'Refund not found.', 'bkx-square-payments' ),
			);
		}

		// Update refund status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $refund->id )
		);

		// Get booking ID from transaction.
		$transactions_table = $wpdb->prefix . 'bkx_square_transactions';

		// Use %i placeholder for table identifier - SECURITY.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transaction = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$transactions_table,
				$refund->transaction_id
			)
		);

		if ( $transaction ) {
			update_post_meta( $transaction->booking_id, '_square_refund_status', $status );

			// Trigger action hook.
			do_action( 'bkx_square_refund_webhook', $transaction->booking_id, $status, $refund_data );
		}

		return array(
			'success' => true,
			'message' => __( 'Refund webhook processed.', 'bkx-square-payments' ),
		);
	}

	/**
	 * Log webhook event.
	 *
	 * @since 1.0.0
	 * @param array $payload Webhook payload.
	 * @return void
	 */
	protected function log_webhook_event( array $payload ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_square_webhook_events';

		$event_id = $payload['event_id'] ?? '';
		$event_type = $payload['type'] ?? '';

		// Check if already processed (prevent duplicates).
		if ( ! empty( $event_id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE square_event_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$event_id
				)
			);

			if ( $exists ) {
				return; // Already processed.
			}
		}

		// Store webhook event.
		$data = array(
			'square_event_id' => $event_id,
			'event_type'      => $event_type,
			'payload'         => wp_json_encode( $payload ),
			'processed_at'    => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );
	}
}

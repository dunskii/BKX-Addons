<?php
/**
 * PayPal Webhook Service
 *
 * Handles webhook event processing.
 *
 * @package BookingX\PayPalPro\Services
 * @since   1.0.0
 */

namespace BookingX\PayPalPro\Services;

use BookingX\PayPalPro\Api\PayPalClient;
use BookingX\PayPalPro\Gateway\PayPalGateway;

/**
 * Webhook service class.
 *
 * @since 1.0.0
 */
class WebhookService {

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
	 * Process a webhook event.
	 *
	 * @since 1.0.0
	 * @param array  $event   Webhook event data.
	 * @param array  $headers Webhook headers.
	 * @param string $payload Raw payload.
	 * @return array Processing result.
	 */
	public function process_event( array $event, array $headers, string $payload ): array {
		try {
			// Verify webhook signature.
			if ( ! $this->verify_signature( $headers, $payload ) ) {
				throw new \Exception( __( 'Invalid webhook signature.', 'bkx-paypal-pro' ) );
			}

			$event_type = $event['event_type'] ?? '';
			$resource   = $event['resource'] ?? array();

			// Log the event.
			$this->log_event( $event, 'received' );

			// Process based on event type.
			$result = $this->handle_event_type( $event_type, $resource, $event );

			// Log successful processing.
			$this->log_event( $event, 'processed' );

			return array(
				'success' => true,
				'message' => $result['message'] ?? __( 'Event processed successfully.', 'bkx-paypal-pro' ),
			);

		} catch ( \Exception $e ) {
			$this->log_event( $event, 'error', $e->getMessage() );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Handle specific event types.
	 *
	 * @since 1.0.0
	 * @param string $event_type Event type.
	 * @param array  $resource   Event resource data.
	 * @param array  $full_event Full event data.
	 * @return array Processing result.
	 */
	protected function handle_event_type( string $event_type, array $resource, array $full_event ): array {
		switch ( $event_type ) {
			case 'PAYMENT.CAPTURE.COMPLETED':
				return $this->handle_capture_completed( $resource );

			case 'PAYMENT.CAPTURE.DENIED':
				return $this->handle_capture_denied( $resource );

			case 'PAYMENT.CAPTURE.REFUNDED':
				return $this->handle_capture_refunded( $resource );

			case 'CHECKOUT.ORDER.APPROVED':
				return $this->handle_order_approved( $resource );

			case 'CHECKOUT.ORDER.COMPLETED':
				return $this->handle_order_completed( $resource );

			default:
				return array(
					'message' => sprintf(
						/* translators: %s: event type */
						__( 'Event type %s not handled.', 'bkx-paypal-pro' ),
						$event_type
					),
				);
		}
	}

	/**
	 * Handle PAYMENT.CAPTURE.COMPLETED event.
	 *
	 * @since 1.0.0
	 * @param array $resource Event resource.
	 * @return array Result.
	 */
	protected function handle_capture_completed( array $resource ): array {
		$capture_id = $resource['id'] ?? '';
		$booking_id = $this->get_booking_id_from_capture( $capture_id );

		if ( ! $booking_id ) {
			return array(
				'message' => __( 'Booking not found for this capture.', 'bkx-paypal-pro' ),
			);
		}

		// Update booking status.
		update_post_meta( $booking_id, '_paypal_capture_status', 'COMPLETED' );
		update_post_meta( $booking_id, '_payment_complete', true );

		// Trigger booking completion action.
		do_action( 'bkx_booking_payment_complete', $booking_id, $resource );

		return array(
			'message' => sprintf(
				/* translators: %d: booking ID */
				__( 'Payment completed for booking #%d.', 'bkx-paypal-pro' ),
				$booking_id
			),
		);
	}

	/**
	 * Handle PAYMENT.CAPTURE.DENIED event.
	 *
	 * @since 1.0.0
	 * @param array $resource Event resource.
	 * @return array Result.
	 */
	protected function handle_capture_denied( array $resource ): array {
		$capture_id = $resource['id'] ?? '';
		$booking_id = $this->get_booking_id_from_capture( $capture_id );

		if ( ! $booking_id ) {
			return array(
				'message' => __( 'Booking not found for this capture.', 'bkx-paypal-pro' ),
			);
		}

		// Update booking status.
		update_post_meta( $booking_id, '_paypal_capture_status', 'DENIED' );
		update_post_meta( $booking_id, '_payment_failed', true );

		// Change booking status to failed.
		wp_update_post( array(
			'ID'          => $booking_id,
			'post_status' => 'bkx-cancelled',
		) );

		// Trigger payment failed action.
		do_action( 'bkx_booking_payment_failed', $booking_id, $resource );

		return array(
			'message' => sprintf(
				/* translators: %d: booking ID */
				__( 'Payment denied for booking #%d.', 'bkx-paypal-pro' ),
				$booking_id
			),
		);
	}

	/**
	 * Handle PAYMENT.CAPTURE.REFUNDED event.
	 *
	 * @since 1.0.0
	 * @param array $resource Event resource.
	 * @return array Result.
	 */
	protected function handle_capture_refunded( array $resource ): array {
		$capture_id = $resource['id'] ?? '';
		$booking_id = $this->get_booking_id_from_capture( $capture_id );

		if ( ! $booking_id ) {
			return array(
				'message' => __( 'Booking not found for this capture.', 'bkx-paypal-pro' ),
			);
		}

		// Update booking meta.
		update_post_meta( $booking_id, '_paypal_refund_status', 'COMPLETED' );
		update_post_meta( $booking_id, '_payment_refunded', true );

		// Trigger refund action.
		do_action( 'bkx_booking_refunded', $booking_id, $resource );

		return array(
			'message' => sprintf(
				/* translators: %d: booking ID */
				__( 'Refund processed for booking #%d.', 'bkx-paypal-pro' ),
				$booking_id
			),
		);
	}

	/**
	 * Handle CHECKOUT.ORDER.APPROVED event.
	 *
	 * @since 1.0.0
	 * @param array $resource Event resource.
	 * @return array Result.
	 */
	protected function handle_order_approved( array $resource ): array {
		$order_id   = $resource['id'] ?? '';
		$booking_id = $this->get_booking_id_from_order( $order_id );

		if ( ! $booking_id ) {
			return array(
				'message' => __( 'Booking not found for this order.', 'bkx-paypal-pro' ),
			);
		}

		// Update order status.
		update_post_meta( $booking_id, '_paypal_order_status', 'APPROVED' );

		return array(
			'message' => sprintf(
				/* translators: %d: booking ID */
				__( 'Order approved for booking #%d.', 'bkx-paypal-pro' ),
				$booking_id
			),
		);
	}

	/**
	 * Handle CHECKOUT.ORDER.COMPLETED event.
	 *
	 * @since 1.0.0
	 * @param array $resource Event resource.
	 * @return array Result.
	 */
	protected function handle_order_completed( array $resource ): array {
		$order_id   = $resource['id'] ?? '';
		$booking_id = $this->get_booking_id_from_order( $order_id );

		if ( ! $booking_id ) {
			return array(
				'message' => __( 'Booking not found for this order.', 'bkx-paypal-pro' ),
			);
		}

		// Update order status.
		update_post_meta( $booking_id, '_paypal_order_status', 'COMPLETED' );

		return array(
			'message' => sprintf(
				/* translators: %d: booking ID */
				__( 'Order completed for booking #%d.', 'bkx-paypal-pro' ),
				$booking_id
			),
		);
	}

	/**
	 * Verify webhook signature.
	 *
	 * SECURITY CRITICAL: Never skip signature verification in production.
	 * This prevents attackers from sending fake webhook events.
	 *
	 * @since 1.0.0
	 * @param array  $headers Webhook headers.
	 * @param string $payload Raw payload.
	 * @return bool Whether signature is valid.
	 */
	protected function verify_signature( array $headers, string $payload ): bool {
		$webhook_id = $this->gateway->get_setting( 'paypal_webhook_id', '' );

		// SECURITY: Never skip verification - reject if not configured.
		if ( empty( $webhook_id ) ) {
			$this->log_event(
				array( 'id' => 'unknown', 'event_type' => 'verification_failed' ),
				'error',
				'Webhook ID not configured - verification required for security'
			);
			return false;
		}

		$response = $this->client->verify_webhook_signature( $headers, $payload );

		if ( ! $response['success'] ) {
			return false;
		}

		$verification_status = $response['data']['verification_status'] ?? 'FAILURE';

		return 'SUCCESS' === $verification_status;
	}

	/**
	 * Get booking ID from capture ID.
	 *
	 * @since 1.0.0
	 * @param string $capture_id PayPal capture ID.
	 * @return int|false Booking ID or false.
	 */
	protected function get_booking_id_from_capture( string $capture_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_paypal_transactions';

		// Use %i placeholder for table identifier - SECURITY.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT booking_id FROM %i WHERE capture_id = %s LIMIT 1',
				$table,
				$capture_id
			)
		);
	}

	/**
	 * Get booking ID from order ID.
	 *
	 * @since 1.0.0
	 * @param string $order_id PayPal order ID.
	 * @return int|false Booking ID or false.
	 */
	protected function get_booking_id_from_order( string $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_paypal_transactions';

		// Use %i placeholder for table identifier - SECURITY.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT booking_id FROM %i WHERE paypal_order_id = %s LIMIT 1',
				$table,
				$order_id
			)
		);
	}

	/**
	 * Log webhook event to database.
	 *
	 * @since 1.0.0
	 * @param array  $event   Event data.
	 * @param string $status  Event status (received, processed, error).
	 * @param string $message Optional error message.
	 * @return void
	 */
	protected function log_event( array $event, string $status, string $message = '' ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_paypal_webhook_events';

		$event_id   = $event['id'] ?? '';
		$event_type = $event['event_type'] ?? '';

		$data = array(
			'paypal_event_id' => $event_id,
			'event_type'      => $event_type,
			'payload'         => wp_json_encode( $event ),
			'status'          => $status,
			'message'         => $message,
			'processed_at'    => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );
	}
}

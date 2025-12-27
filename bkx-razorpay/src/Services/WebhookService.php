<?php
/**
 * Webhook Service
 *
 * Handles webhook event processing.
 *
 * @package BookingX\Razorpay\Services
 * @since   1.0.0
 */

namespace BookingX\Razorpay\Services;

use BookingX\Razorpay\RazorpayAddon;
use BookingX\Razorpay\Gateway\RazorpayGateway;
use BookingX\Razorpay\Api\RazorpayClient;

/**
 * Webhook service class.
 *
 * @since 1.0.0
 */
class WebhookService {

	/**
	 * Addon instance.
	 *
	 * @var RazorpayAddon
	 */
	protected RazorpayAddon $addon;

	/**
	 * Gateway instance.
	 *
	 * @var RazorpayGateway
	 */
	protected RazorpayGateway $gateway;

	/**
	 * API client.
	 *
	 * @var RazorpayClient
	 */
	protected RazorpayClient $client;

	/**
	 * Constructor.
	 *
	 * @param RazorpayAddon   $addon Addon instance.
	 * @param RazorpayGateway $gateway Gateway instance.
	 */
	public function __construct( RazorpayAddon $addon, RazorpayGateway $gateway ) {
		$this->addon = $addon;
		$this->gateway = $gateway;
		$this->client = $gateway->get_client();
	}

	/**
	 * Process a webhook event.
	 *
	 * @since 1.0.0
	 * @param array  $event Webhook event data.
	 * @param string $raw_payload Raw payload for signature verification.
	 * @param string $signature X-Razorpay-Signature header value.
	 * @return array Processing result.
	 */
	public function process_event( array $event, string $raw_payload, string $signature ): array {
		try {
			// Verify webhook signature - SECURITY CRITICAL.
			if ( ! $this->verify_signature( $raw_payload, $signature ) ) {
				throw new \Exception( __( 'Invalid webhook signature.', 'bkx-razorpay' ) );
			}

			$event_id = $event['event_id'] ?? '';
			$event_type = $event['event'] ?? '';

			// Log the event.
			$this->log_event( $event_id, $event_type, $event, 'received' );

			// Check for duplicate events using x-razorpay-event-id.
			if ( $this->is_duplicate_event( $event_id ) ) {
				return array(
					'success' => true,
					'message' => __( 'Duplicate event ignored.', 'bkx-razorpay' ),
				);
			}

			// Process the event through the gateway.
			$result = $this->gateway->handle_webhook( $event );

			// Log successful processing.
			$status = $result['success'] ? 'processed' : 'error';
			$message = $result['error'] ?? $result['message'] ?? '';
			$this->log_event( $event_id, $event_type, $event, $status, $message );

			return $result;

		} catch ( \Exception $e ) {
			$event_id = $event['event_id'] ?? 'unknown';
			$event_type = $event['event'] ?? 'unknown';

			$this->log_event( $event_id, $event_type, $event, 'error', $e->getMessage() );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Verify webhook signature.
	 *
	 * SECURITY CRITICAL: Never skip signature verification.
	 * This prevents attackers from sending fake webhook events.
	 *
	 * @since 1.0.0
	 * @param string $raw_payload Raw webhook payload.
	 * @param string $signature X-Razorpay-Signature header value.
	 * @return bool Whether signature is valid.
	 */
	protected function verify_signature( string $raw_payload, string $signature ): bool {
		$webhook_secret = $this->addon->get_setting( 'webhook_secret', '' );

		// SECURITY: Never skip verification - reject if not configured.
		if ( empty( $webhook_secret ) ) {
			$this->gateway->log(
				'Webhook secret not configured - verification required for security',
				'error'
			);
			return false;
		}

		// SECURITY: Reject if no signature provided.
		if ( empty( $signature ) ) {
			$this->gateway->log(
				'Webhook signature missing from request',
				'error'
			);
			return false;
		}

		// Use Razorpay SDK for verification.
		return $this->client->verify_webhook_signature( $raw_payload, $signature, $webhook_secret );
	}

	/**
	 * Check if event is a duplicate.
	 *
	 * Uses x-razorpay-event-id to detect duplicates.
	 *
	 * @since 1.0.0
	 * @param string $event_id Event ID.
	 * @return bool Whether this is a duplicate event.
	 */
	protected function is_duplicate_event( string $event_id ): bool {
		if ( empty( $event_id ) ) {
			return false;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'bkx_razorpay_webhook_events';

		// Use %i placeholder for table identifier - SECURITY.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE event_id = %s AND status = %s LIMIT 1',
				$table,
				$event_id,
				'processed'
			)
		);

		return null !== $existing;
	}

	/**
	 * Log webhook event to database.
	 *
	 * @since 1.0.0
	 * @param string $event_id Event ID.
	 * @param string $event_type Event type.
	 * @param array  $event Full event data.
	 * @param string $status Event status (received, processed, error).
	 * @param string $message Optional message.
	 * @return void
	 */
	protected function log_event(
		string $event_id,
		string $event_type,
		array $event,
		string $status,
		string $message = ''
	): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_razorpay_webhook_events';

		$data = array(
			'event_id'     => $event_id,
			'event_type'   => $event_type,
			'payload'      => wp_json_encode( $event ),
			'status'       => $status,
			'message'      => $message,
			'processed_at' => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );

		// Also log to file for debugging.
		$this->gateway->log(
			sprintf( 'Webhook event: %s (%s) - Status: %s', $event_type, $event_id, $status )
		);
	}
}

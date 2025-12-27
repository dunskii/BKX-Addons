<?php
/**
 * Webhook Service
 *
 * Handles webhook event processing.
 *
 * @package BookingX\AuthorizeNet\Services
 * @since   1.0.0
 */

namespace BookingX\AuthorizeNet\Services;

use BookingX\AuthorizeNet\AuthorizeNet;
use BookingX\AuthorizeNet\Gateway\AuthorizeNetGateway;

/**
 * Webhook service class.
 *
 * @since 1.0.0
 */
class WebhookService {

	/**
	 * Addon instance.
	 *
	 * @var AuthorizeNet
	 */
	protected AuthorizeNet $addon;

	/**
	 * Gateway instance.
	 *
	 * @var AuthorizeNetGateway
	 */
	protected AuthorizeNetGateway $gateway;

	/**
	 * Constructor.
	 *
	 * @param AuthorizeNet        $addon Addon instance.
	 * @param AuthorizeNetGateway $gateway Gateway instance.
	 */
	public function __construct( AuthorizeNet $addon, AuthorizeNetGateway $gateway ) {
		$this->addon = $addon;
		$this->gateway = $gateway;
	}

	/**
	 * Process a webhook event.
	 *
	 * @since 1.0.0
	 * @param array  $event Webhook event data.
	 * @param string $raw_payload Raw payload for signature verification.
	 * @param string $signature X-ANET-Signature header value.
	 * @return array Processing result.
	 */
	public function process_event( array $event, string $raw_payload, string $signature ): array {
		try {
			// Verify webhook signature - SECURITY CRITICAL.
			if ( ! $this->verify_signature( $raw_payload, $signature ) ) {
				throw new \Exception( __( 'Invalid webhook signature.', 'bkx-authorize-net' ) );
			}

			$notification_id = $event['notificationId'] ?? '';
			$event_type = $event['eventType'] ?? '';
			$payload = $event['payload'] ?? array();

			// Log the event.
			$this->log_event( $notification_id, $event_type, $event, 'received' );

			// Check for duplicate events.
			if ( $this->is_duplicate_event( $notification_id ) ) {
				return array(
					'success' => true,
					'message' => __( 'Duplicate event ignored.', 'bkx-authorize-net' ),
				);
			}

			// Process the event through the gateway.
			$result = $this->gateway->handle_webhook( $event );

			// Log successful processing.
			$status = $result['success'] ? 'processed' : 'error';
			$message = $result['error'] ?? $result['message'] ?? '';
			$this->log_event( $notification_id, $event_type, $event, $status, $message );

			return $result;

		} catch ( \Exception $e ) {
			$notification_id = $event['notificationId'] ?? 'unknown';
			$event_type = $event['eventType'] ?? 'unknown';

			$this->log_event( $notification_id, $event_type, $event, 'error', $e->getMessage() );

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
	 * @param string $signature X-ANET-Signature header value.
	 * @return bool Whether signature is valid.
	 */
	protected function verify_signature( string $raw_payload, string $signature ): bool {
		$signature_key = $this->addon->get_setting( 'signature_key', '' );

		// SECURITY: Never skip verification - reject if not configured.
		if ( empty( $signature_key ) ) {
			$this->gateway->log(
				'Webhook signature key not configured - verification required for security',
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

		// Authorize.net sends signature as "sha512=HASH".
		$signature_parts = explode( '=', $signature, 2 );
		if ( count( $signature_parts ) !== 2 || 'sha512' !== strtolower( $signature_parts[0] ) ) {
			$this->gateway->log(
				'Invalid webhook signature format',
				'error'
			);
			return false;
		}

		$provided_hash = strtoupper( $signature_parts[1] );

		// Calculate expected hash using HMAC-SHA512.
		$expected_hash = strtoupper(
			hash_hmac( 'sha512', $raw_payload, $signature_key )
		);

		// Use timing-safe comparison to prevent timing attacks.
		return hash_equals( $expected_hash, $provided_hash );
	}

	/**
	 * Check if event is a duplicate.
	 *
	 * @since 1.0.0
	 * @param string $notification_id Notification ID.
	 * @return bool Whether this is a duplicate event.
	 */
	protected function is_duplicate_event( string $notification_id ): bool {
		if ( empty( $notification_id ) ) {
			return false;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'bkx_authnet_webhook_events';

		// Use %i placeholder for table identifier - SECURITY.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE notification_id = %s AND status = %s LIMIT 1',
				$table,
				$notification_id,
				'processed'
			)
		);

		return null !== $existing;
	}

	/**
	 * Log webhook event to database.
	 *
	 * @since 1.0.0
	 * @param string $notification_id Notification ID.
	 * @param string $event_type Event type.
	 * @param array  $event Full event data.
	 * @param string $status Event status (received, processed, error).
	 * @param string $message Optional message.
	 * @return void
	 */
	protected function log_event(
		string $notification_id,
		string $event_type,
		array $event,
		string $status,
		string $message = ''
	): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_authnet_webhook_events';

		$data = array(
			'notification_id' => $notification_id,
			'event_type'      => $event_type,
			'payload'         => wp_json_encode( $event ),
			'status'          => $status,
			'message'         => $message,
			'processed_at'    => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );

		// Also log to file for debugging.
		$this->gateway->log(
			sprintf( 'Webhook event: %s (%s) - Status: %s', $event_type, $notification_id, $status )
		);
	}
}

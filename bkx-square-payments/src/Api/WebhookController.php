<?php
/**
 * Webhook Controller
 *
 * @package BookingX\SquarePayments\Api
 */

namespace BookingX\SquarePayments\Api;

use BookingX\SquarePayments\SquarePayments;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Webhook controller class.
 *
 * @since 1.0.0
 */
class WebhookController {

	/**
	 * Add-on instance.
	 *
	 * @var SquarePayments
	 */
	protected $addon;

	/**
	 * Constructor.
	 *
	 * @param SquarePayments $addon Add-on instance.
	 */
	public function __construct( SquarePayments $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'bookingx/v1',
			'/webhooks/square',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true', // Webhooks handle their own auth.
			)
		);
	}

	/**
	 * Handle incoming webhook request.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		// Get raw body for signature verification.
		$raw_body = $request->get_body();

		// Get signature header.
		$signature_header = $request->get_header( 'x-square-signature' );

		// Verify webhook signature.
		if ( ! $this->verify_webhook_signature( $raw_body, $signature_header ) ) {
			return new WP_Error(
				'invalid_signature',
				__( 'Webhook signature verification failed.', 'bkx-square-payments' ),
				array( 'status' => 401 )
			);
		}

		// Parse JSON payload.
		$payload = json_decode( $raw_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'invalid_json',
				__( 'Invalid JSON payload.', 'bkx-square-payments' ),
				array( 'status' => 400 )
			);
		}

		// Process webhook via gateway.
		$gateway = $this->addon->get_gateway();

		if ( ! $gateway ) {
			return new WP_Error(
				'gateway_not_found',
				__( 'Payment gateway not initialized.', 'bkx-square-payments' ),
				array( 'status' => 500 )
			);
		}

		$result = $gateway->handle_webhook( $payload );

		if ( $result['success'] ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => $result['message'] ?? __( 'Webhook processed.', 'bkx-square-payments' ),
				),
				200
			);
		}

		return new WP_Error(
			'webhook_processing_failed',
			$result['message'] ?? __( 'Webhook processing failed.', 'bkx-square-payments' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * Verify webhook signature using HMAC-SHA256.
	 *
	 * SECURITY CRITICAL: Never skip signature verification.
	 * This prevents attackers from sending fake webhook events.
	 *
	 * @since 1.0.0
	 * @param string $payload   Raw payload.
	 * @param string $signature Signature from header.
	 * @return bool
	 */
	protected function verify_webhook_signature( string $payload, string $signature ): bool {
		// Get webhook signature key from settings.
		$signature_key = $this->addon->get_setting( 'square_webhook_signature_key', '' );

		// SECURITY: Never skip signature verification - reject if not configured.
		if ( empty( $signature_key ) ) {
			$this->addon->get_gateway()->log(
				'Webhook signature key not configured - verification required for security',
				'error'
			);
			return false;
		}

		// SECURITY: Reject if no signature provided.
		if ( empty( $signature ) ) {
			$this->addon->get_gateway()->log(
				'Webhook signature missing from request',
				'error'
			);
			return false;
		}

		// Get webhook URL.
		$webhook_url = rest_url( 'bookingx/v1/webhooks/square' );

		// Concatenate webhook URL and payload body.
		$payload_to_verify = $webhook_url . $payload;

		// Calculate HMAC-SHA256.
		$expected_signature = base64_encode(
			hash_hmac( 'sha256', $payload_to_verify, $signature_key, true )
		);

		// Compare signatures using timing-safe comparison.
		return hash_equals( $expected_signature, $signature );
	}
}

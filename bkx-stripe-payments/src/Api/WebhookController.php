<?php
/**
 * Webhook Controller
 *
 * Handles REST API endpoint for Stripe webhooks.
 *
 * @package BookingX\StripePayments\Api
 * @since   1.0.0
 */

namespace BookingX\StripePayments\Api;

use BookingX\StripePayments\StripePayments;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Webhook REST API controller.
 *
 * @since 1.0.0
 */
class WebhookController {

	/**
	 * Parent addon instance.
	 *
	 * @var StripePayments
	 */
	protected StripePayments $addon;

	/**
	 * Constructor.
	 *
	 * @param StripePayments $addon Parent addon instance.
	 */
	public function __construct( StripePayments $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Handle webhook request.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		// Get RAW payload - CRITICAL for Stripe signature verification.
		// Stripe signs the raw request body, so we must use it for verification.
		$raw_payload = $request->get_body();

		if ( empty( $raw_payload ) ) {
			return new WP_Error(
				'invalid_payload',
				__( 'Invalid webhook payload.', 'bkx-stripe-payments' ),
				array( 'status' => 400 )
			);
		}

		// Process webhook via service - pass raw payload for signature verification
		$result = $this->addon->get_webhook_service()->handle_webhook( $raw_payload );

		if ( ! $result['success'] ) {
			return new WP_Error(
				'webhook_processing_failed',
				$result['error'] ?? __( 'Webhook processing failed.', 'bkx-stripe-payments' ),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Webhook processed successfully.', 'bkx-stripe-payments' ),
			),
			200
		);
	}
}

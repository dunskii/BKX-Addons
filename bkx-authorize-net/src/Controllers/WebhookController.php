<?php
/**
 * Webhook Controller
 *
 * Handles incoming webhook requests from Authorize.net.
 *
 * @package BookingX\AuthorizeNet\Controllers
 * @since   1.0.0
 */

namespace BookingX\AuthorizeNet\Controllers;

use BookingX\AuthorizeNet\AuthorizeNet;
use BookingX\AuthorizeNet\Services\WebhookService;
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
	 * Addon instance.
	 *
	 * @var AuthorizeNet
	 */
	protected AuthorizeNet $addon;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected string $namespace = 'bookingx/v1';

	/**
	 * Constructor.
	 *
	 * @param AuthorizeNet $addon Addon instance.
	 */
	public function __construct( AuthorizeNet $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/webhooks/authorize-net',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true', // Webhooks are verified by signature.
			)
		);
	}

	/**
	 * Handle incoming webhook.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error Response.
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$gateway = $this->addon->get_gateway();

		// CRITICAL: Check gateway before using it - don't try to log on null.
		if ( null === $gateway ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- emergency logging
			error_log( '[BookingX Authorize.net] Webhook received but gateway not available' );
			return new WP_REST_Response(
				array( 'error' => 'Gateway not available' ),
				500
			);
		}

		$gateway->log( 'Webhook request received from Authorize.net' );

		// Get raw payload for signature verification.
		$raw_payload = $request->get_body();

		// Get signature from header.
		$signature = $request->get_header( 'X-ANET-Signature' );

		// Sanitize signature header.
		$signature = ! empty( $signature ) ? sanitize_text_field( $signature ) : '';

		// Parse the JSON payload.
		$event = $request->get_json_params();

		if ( empty( $event ) ) {
			$gateway->log( 'Webhook received with empty or invalid JSON payload', 'error' );
			return new WP_REST_Response(
				array( 'error' => 'Invalid payload' ),
				400
			);
		}

		// Process the webhook.
		$webhook_service = new WebhookService( $this->addon, $gateway );
		$result = $webhook_service->process_event( $event, $raw_payload, $signature );

		if ( ! $result['success'] ) {
			$gateway->log( sprintf( 'Webhook processing failed: %s', $result['error'] ?? 'Unknown error' ), 'error' );

			// Return 200 for signature failures to prevent retries of fake events.
			// Return 500 for processing failures so Authorize.net retries.
			$is_signature_failure = strpos( $result['error'] ?? '', 'signature' ) !== false;
			$status_code = $is_signature_failure ? 200 : 500;

			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result['error'] ?? __( 'Processing failed', 'bkx-authorize-net' ),
				),
				$status_code
			);
		}

		$gateway->log( sprintf( 'Webhook processed successfully: %s', $result['message'] ?? '' ) );

		// Always return 200 OK for successfully processed webhooks.
		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $result['message'] ?? __( 'Webhook processed', 'bkx-authorize-net' ),
			),
			200
		);
	}

	/**
	 * Get the webhook URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_webhook_url(): string {
		return rest_url( 'bookingx/v1/webhooks/authorize-net' );
	}
}

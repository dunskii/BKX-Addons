<?php
/**
 * PayPal Webhook Controller
 *
 * Handles incoming webhook requests via REST API.
 *
 * @package BookingX\PayPalPro\Api
 * @since   1.0.0
 */

namespace BookingX\PayPalPro\Api;

use BookingX\PayPalPro\PayPalPro;
use BookingX\PayPalPro\Services\WebhookService;
use BookingX\PayPalPro\Gateway\PayPalGateway;

/**
 * Webhook controller class.
 *
 * @since 1.0.0
 */
class WebhookController {

	/**
	 * Addon instance.
	 *
	 * @var PayPalPro
	 */
	protected PayPalPro $addon;

	/**
	 * Webhook service.
	 *
	 * @var WebhookService|null
	 */
	protected ?WebhookService $webhook_service = null;

	/**
	 * Constructor.
	 *
	 * @param PayPalPro $addon Addon instance.
	 */
	public function __construct( PayPalPro $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Handle webhook request.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_webhook( \WP_REST_Request $request ) {
		try {
			// Get raw body and headers.
			$raw_body = $request->get_body();
			$headers  = $this->get_webhook_headers();

			// Parse payload.
			$event = json_decode( $raw_body, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new \WP_Error(
					'invalid_json',
					__( 'Invalid JSON payload.', 'bkx-paypal-pro' ),
					array( 'status' => 400 )
				);
			}

			// Initialize webhook service if needed.
			if ( ! $this->webhook_service ) {
				$gateway = new PayPalGateway();
				$client  = $gateway->get_client();
				$this->webhook_service = new WebhookService( $client, $gateway );
			}

			// Process the event.
			$result = $this->webhook_service->process_event( $event, $headers, $raw_body );

			if ( ! $result['success'] ) {
				return new \WP_Error(
					'webhook_processing_failed',
					$result['error'] ?? __( 'Failed to process webhook.', 'bkx-paypal-pro' ),
					array( 'status' => 500 )
				);
			}

			return new \WP_REST_Response(
				array(
					'success' => true,
					'message' => $result['message'] ?? __( 'Webhook processed successfully.', 'bkx-paypal-pro' ),
				),
				200
			);

		} catch ( \Exception $e ) {
			return new \WP_Error(
				'webhook_exception',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get webhook headers from request.
	 *
	 * @since 1.0.0
	 * @return array Webhook headers.
	 */
	protected function get_webhook_headers(): array {
		$headers = array();

		$required_headers = array(
			'PAYPAL-AUTH-ALGO',
			'PAYPAL-CERT-URL',
			'PAYPAL-TRANSMISSION-ID',
			'PAYPAL-TRANSMISSION-SIG',
			'PAYPAL-TRANSMISSION-TIME',
		);

		foreach ( $required_headers as $header ) {
			$server_key = 'HTTP_' . str_replace( '-', '_', $header );

			if ( isset( $_SERVER[ $server_key ] ) ) {
				$headers[ $header ] = sanitize_text_field( wp_unslash( $_SERVER[ $server_key ] ) );
			}
		}

		return $headers;
	}
}

<?php
/**
 * PayPal REST API Client
 *
 * Custom implementation using WordPress HTTP API (no deprecated PayPal SDK).
 *
 * @package BookingX\PayPalPro\Api
 * @since   1.0.0
 */

namespace BookingX\PayPalPro\Api;

use BookingX\PayPalPro\Gateway\PayPalGateway;

/**
 * PayPal API client class.
 *
 * @since 1.0.0
 */
class PayPalClient {

	/**
	 * Gateway instance.
	 *
	 * @var PayPalGateway
	 */
	protected PayPalGateway $gateway;

	/**
	 * PayPal API base URL.
	 *
	 * @var string
	 */
	protected string $api_base;

	/**
	 * Access token cache key.
	 *
	 * @var string
	 */
	protected string $token_cache_key = 'bkx_paypal_pro_access_token';

	/**
	 * Constructor.
	 *
	 * @param PayPalGateway $gateway Gateway instance.
	 */
	public function __construct( PayPalGateway $gateway ) {
		$this->gateway = $gateway;

		$mode = $gateway->get_setting( 'paypal_mode', 'sandbox' );

		if ( 'live' === $mode ) {
			$this->api_base = 'https://api-m.paypal.com';
		} else {
			$this->api_base = 'https://api-m.sandbox.paypal.com';
		}
	}

	/**
	 * Get OAuth 2.0 access token.
	 *
	 * @since 1.0.0
	 * @return string|false Access token or false on failure.
	 */
	public function get_access_token() {
		// Check cache first.
		$cached_token = get_transient( $this->token_cache_key );

		if ( $cached_token ) {
			return $cached_token;
		}

		// Get credentials.
		$mode = $this->gateway->get_setting( 'paypal_mode', 'sandbox' );

		if ( 'live' === $mode ) {
			$client_id     = $this->gateway->get_setting( 'paypal_live_client_id', '' );
			$client_secret = $this->gateway->get_setting( 'paypal_live_client_secret', '' );
		} else {
			$client_id     = $this->gateway->get_setting( 'paypal_sandbox_client_id', '' );
			$client_secret = $this->gateway->get_setting( 'paypal_sandbox_client_secret', '' );
		}

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			$this->log( 'PayPal API credentials not configured.', 'error' );
			return false;
		}

		// Request token.
		$response = wp_remote_post(
			$this->api_base . '/v1/oauth2/token',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type' => 'client_credentials',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'Failed to get access token: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['access_token'] ) ) {
			$this->log( 'Invalid token response: ' . $body, 'error' );
			return false;
		}

		// Cache token for 8 hours (PayPal tokens expire in 9 hours).
		$expires_in = ! empty( $data['expires_in'] ) ? (int) $data['expires_in'] - 3600 : 28800;
		set_transient( $this->token_cache_key, $data['access_token'], $expires_in );

		$this->log( 'Access token obtained successfully.', 'debug' );

		return $data['access_token'];
	}

	/**
	 * Make an API request.
	 *
	 * @since 1.0.0
	 * @param string $endpoint API endpoint (e.g., '/v2/checkout/orders').
	 * @param string $method   HTTP method (GET, POST, PATCH, DELETE).
	 * @param array  $data     Request data.
	 * @param array  $headers  Additional headers.
	 * @return array Response data or error.
	 */
	public function request( string $endpoint, string $method = 'GET', array $data = array(), array $headers = array() ) {
		$access_token = $this->get_access_token();

		if ( ! $access_token ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to authenticate with PayPal.', 'bkx-paypal-pro' ),
			);
		}

		$url = $this->api_base . $endpoint;

		$default_headers = array(
			'Authorization' => 'Bearer ' . $access_token,
			'Content-Type'  => 'application/json',
		);

		$headers = array_merge( $default_headers, $headers );

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 30,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PATCH', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$this->log( "API Request: {$method} {$endpoint}", 'debug', $data );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'API request failed: ' . $response->get_error_message(), 'error' );
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		$this->log( "API Response: {$status_code}", 'debug', $data );

		// Check for errors.
		if ( $status_code >= 400 ) {
			$error_message = $data['message'] ?? $data['error_description'] ?? __( 'Unknown PayPal API error.', 'bkx-paypal-pro' );

			return array(
				'success'     => false,
				'error'       => $error_message,
				'status_code' => $status_code,
				'data'        => $data,
			);
		}

		return array(
			'success'     => true,
			'data'        => $data,
			'status_code' => $status_code,
		);
	}

	/**
	 * Create an order.
	 *
	 * @since 1.0.0
	 * @param array $order_data Order data.
	 * @return array Response.
	 */
	public function create_order( array $order_data ): array {
		return $this->request( '/v2/checkout/orders', 'POST', $order_data );
	}

	/**
	 * Get order details.
	 *
	 * @since 1.0.0
	 * @param string $order_id PayPal order ID.
	 * @return array Response.
	 */
	public function get_order( string $order_id ): array {
		return $this->request( "/v2/checkout/orders/{$order_id}", 'GET' );
	}

	/**
	 * Capture an order.
	 *
	 * @since 1.0.0
	 * @param string $order_id PayPal order ID.
	 * @param array  $data     Additional capture data.
	 * @return array Response.
	 */
	public function capture_order( string $order_id, array $data = array() ): array {
		return $this->request( "/v2/checkout/orders/{$order_id}/capture", 'POST', $data );
	}

	/**
	 * Authorize an order.
	 *
	 * @since 1.0.0
	 * @param string $order_id PayPal order ID.
	 * @param array  $data     Additional authorization data.
	 * @return array Response.
	 */
	public function authorize_order( string $order_id, array $data = array() ): array {
		return $this->request( "/v2/checkout/orders/{$order_id}/authorize", 'POST', $data );
	}

	/**
	 * Capture an authorization.
	 *
	 * @since 1.0.0
	 * @param string $authorization_id PayPal authorization ID.
	 * @param array  $data             Capture data.
	 * @return array Response.
	 */
	public function capture_authorization( string $authorization_id, array $data = array() ): array {
		return $this->request( "/v2/payments/authorizations/{$authorization_id}/capture", 'POST', $data );
	}

	/**
	 * Refund a captured payment.
	 *
	 * @since 1.0.0
	 * @param string $capture_id PayPal capture ID.
	 * @param array  $data       Refund data.
	 * @return array Response.
	 */
	public function refund_capture( string $capture_id, array $data = array() ): array {
		return $this->request( "/v2/payments/captures/{$capture_id}/refund", 'POST', $data );
	}

	/**
	 * Get capture details.
	 *
	 * @since 1.0.0
	 * @param string $capture_id PayPal capture ID.
	 * @return array Response.
	 */
	public function get_capture( string $capture_id ): array {
		return $this->request( "/v2/payments/captures/{$capture_id}", 'GET' );
	}

	/**
	 * Get refund details.
	 *
	 * @since 1.0.0
	 * @param string $refund_id PayPal refund ID.
	 * @return array Response.
	 */
	public function get_refund( string $refund_id ): array {
		return $this->request( "/v2/payments/refunds/{$refund_id}", 'GET' );
	}

	/**
	 * Verify webhook signature.
	 *
	 * @since 1.0.0
	 * @param array $headers Webhook headers.
	 * @param string $payload Raw webhook payload.
	 * @return array Verification result.
	 */
	public function verify_webhook_signature( array $headers, string $payload ): array {
		$webhook_id = $this->gateway->get_setting( 'paypal_webhook_id', '' );

		if ( empty( $webhook_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Webhook ID not configured.', 'bkx-paypal-pro' ),
			);
		}

		$verification_data = array(
			'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'] ?? '',
			'cert_url'          => $headers['PAYPAL-CERT-URL'] ?? '',
			'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
			'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
			'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
			'webhook_id'        => $webhook_id,
			'webhook_event'     => json_decode( $payload, true ),
		);

		return $this->request( '/v1/notifications/verify-webhook-signature', 'POST', $verification_data );
	}

	/**
	 * Log a message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param string $level   Log level.
	 * @param array  $context Additional context.
	 * @return void
	 */
	protected function log( string $message, string $level = 'info', array $context = array() ): void {
		if ( ! $this->gateway->get_setting( 'debug_log', false ) && 'debug' === $level ) {
			return;
		}

		do_action( 'bkx_gateway_log', 'paypal_pro', $message, $level, $context );

		// Also log to WordPress debug.log if WP_DEBUG is enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[PayPal Pro] [%s] %s', strtoupper( $level ), $message ) );
		}
	}
}

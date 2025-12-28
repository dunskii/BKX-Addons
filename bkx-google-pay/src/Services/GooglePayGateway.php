<?php
/**
 * Google Pay Gateway
 *
 * @package BookingX\GooglePay\Services
 * @since   1.0.0
 */

namespace BookingX\GooglePay\Services;

use BookingX\AddonSDK\Abstracts\AbstractPaymentGateway;

/**
 * Google Pay payment gateway.
 *
 * @since 1.0.0
 */
class GooglePayGateway extends AbstractPaymentGateway {

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	protected string $id = 'google_pay';

	/**
	 * Supported payment gateways for tokenization.
	 *
	 * @var array
	 */
	private const SUPPORTED_GATEWAYS = array(
		'stripe'       => array(
			'name'          => 'stripe',
			'token_type'    => 'PAYMENT_GATEWAY',
			'auth_methods'  => array( 'PAN_ONLY', 'CRYPTOGRAM_3DS' ),
		),
		'braintree'    => array(
			'name'          => 'braintree',
			'token_type'    => 'PAYMENT_GATEWAY',
			'auth_methods'  => array( 'PAN_ONLY', 'CRYPTOGRAM_3DS' ),
		),
		'square'       => array(
			'name'          => 'square',
			'token_type'    => 'PAYMENT_GATEWAY',
			'auth_methods'  => array( 'PAN_ONLY', 'CRYPTOGRAM_3DS' ),
		),
		'adyen'        => array(
			'name'          => 'adyen',
			'token_type'    => 'PAYMENT_GATEWAY',
			'auth_methods'  => array( 'PAN_ONLY', 'CRYPTOGRAM_3DS' ),
		),
		'cybersource'  => array(
			'name'          => 'cybersource',
			'token_type'    => 'PAYMENT_GATEWAY',
			'auth_methods'  => array( 'PAN_ONLY', 'CRYPTOGRAM_3DS' ),
		),
	);

	/**
	 * Process payment.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking post ID.
	 * @param array $payment_data Payment data from Google Pay.
	 * @return array Result with success/error.
	 */
	public function process_payment( int $booking_id, array $payment_data ): array {
		$settings = get_option( 'bkx_google_pay_settings', array() );

		// Validate payment data structure.
		if ( empty( $payment_data['paymentMethodData'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid Google Pay response.', 'bkx-google-pay' ),
			);
		}

		$payment_method = $payment_data['paymentMethodData'];
		$token_data     = json_decode( $payment_method['tokenizationData']['token'], true );

		if ( ! $token_data ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to parse payment token.', 'bkx-google-pay' ),
			);
		}

		// Get booking amount.
		$amount = get_post_meta( $booking_id, 'booking_total', true );

		if ( ! $amount || $amount <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid booking amount.', 'bkx-google-pay' ),
			);
		}

		// Process through the configured gateway.
		$gateway = $settings['gateway'] ?? 'stripe';
		$result  = $this->process_with_gateway( $gateway, $booking_id, $amount, $token_data, $settings );

		if ( $result['success'] ) {
			// Update booking meta.
			update_post_meta( $booking_id, '_bkx_transaction_id', $result['transaction_id'] );
			update_post_meta( $booking_id, '_bkx_payment_method', 'google_pay' );
			update_post_meta( $booking_id, '_bkx_payment_gateway', $gateway );
			update_post_meta( $booking_id, '_bkx_payment_status', 'completed' );

			// Log transaction.
			$this->log_transaction( $booking_id, 'sale', array(
				'gateway'        => $gateway,
				'transaction_id' => $result['transaction_id'],
				'amount'         => $amount,
			) );
		}

		return $result;
	}

	/**
	 * Process payment through configured gateway.
	 *
	 * @since 1.0.0
	 * @param string $gateway    Gateway identifier.
	 * @param int    $booking_id Booking ID.
	 * @param float  $amount     Payment amount.
	 * @param array  $token_data Token data from Google Pay.
	 * @param array  $settings   Gateway settings.
	 * @return array
	 */
	private function process_with_gateway( string $gateway, int $booking_id, float $amount, array $token_data, array $settings ): array {
		// Route to appropriate gateway processor.
		switch ( $gateway ) {
			case 'stripe':
				return $this->process_stripe( $booking_id, $amount, $token_data, $settings );

			case 'braintree':
				return $this->process_braintree( $booking_id, $amount, $token_data, $settings );

			case 'square':
				return $this->process_square( $booking_id, $amount, $token_data, $settings );

			default:
				// Allow third-party gateway handling.
				$result = apply_filters(
					'bkx_google_pay_process_gateway',
					array(
						'success' => false,
						'message' => __( 'Unsupported payment gateway.', 'bkx-google-pay' ),
					),
					$gateway,
					$booking_id,
					$amount,
					$token_data,
					$settings
				);
				return $result;
		}
	}

	/**
	 * Process payment through Stripe.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param float $amount     Payment amount.
	 * @param array $token_data Token data.
	 * @param array $settings   Settings.
	 * @return array
	 */
	private function process_stripe( int $booking_id, float $amount, array $token_data, array $settings ): array {
		// Check if Stripe add-on is available.
		if ( ! class_exists( 'BookingX\\StripePayments\\StripePaymentsAddon' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Stripe add-on is required to process Google Pay payments.', 'bkx-google-pay' ),
			);
		}

		// Get Stripe settings.
		$stripe_settings = get_option( 'bkx_stripe_payments_settings', array() );
		$is_sandbox      = ! empty( $stripe_settings['sandbox'] );
		$secret_key      = $is_sandbox
			? ( $stripe_settings['test_secret_key'] ?? '' )
			: ( $stripe_settings['live_secret_key'] ?? '' );

		if ( empty( $secret_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Stripe is not configured.', 'bkx-google-pay' ),
			);
		}

		// Create payment intent with the Google Pay token.
		$response = wp_remote_post(
			'https://api.stripe.com/v1/payment_intents',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'amount'               => absint( $amount * 100 ), // Convert to cents.
					'currency'             => strtolower( get_option( 'bkx_currency', 'usd' ) ),
					'payment_method_data'  => array(
						'type'     => 'card',
						'card'     => array(
							'token' => $token_data['id'] ?? '',
						),
					),
					'confirm'              => 'true',
					'description'          => sprintf( 'BookingX Booking #%d', $booking_id ),
					'metadata'             => array(
						'booking_id' => $booking_id,
						'source'     => 'google_pay',
					),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return array(
				'success' => false,
				'message' => $body['error']['message'] ?? __( 'Payment failed.', 'bkx-google-pay' ),
			);
		}

		if ( 'succeeded' === ( $body['status'] ?? '' ) ) {
			return array(
				'success'        => true,
				'transaction_id' => $body['id'],
				'message'        => __( 'Payment successful.', 'bkx-google-pay' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Payment was not completed.', 'bkx-google-pay' ),
		);
	}

	/**
	 * Process payment through Braintree.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param float $amount     Payment amount.
	 * @param array $token_data Token data.
	 * @param array $settings   Settings.
	 * @return array
	 */
	private function process_braintree( int $booking_id, float $amount, array $token_data, array $settings ): array {
		// Braintree processing would go here.
		// For now, return not implemented.
		return apply_filters(
			'bkx_google_pay_braintree_process',
			array(
				'success' => false,
				'message' => __( 'Braintree integration requires additional configuration.', 'bkx-google-pay' ),
			),
			$booking_id,
			$amount,
			$token_data
		);
	}

	/**
	 * Process payment through Square.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param float $amount     Payment amount.
	 * @param array $token_data Token data.
	 * @param array $settings   Settings.
	 * @return array
	 */
	private function process_square( int $booking_id, float $amount, array $token_data, array $settings ): array {
		// Check if Square add-on is available.
		if ( ! class_exists( 'BookingX\\SquarePayments\\SquarePaymentsAddon' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Square add-on is required to process Google Pay payments.', 'bkx-google-pay' ),
			);
		}

		// Square processing through their SDK.
		return apply_filters(
			'bkx_google_pay_square_process',
			array(
				'success' => false,
				'message' => __( 'Square integration requires additional configuration.', 'bkx-google-pay' ),
			),
			$booking_id,
			$amount,
			$token_data
		);
	}

	/**
	 * Process refund.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking post ID.
	 * @param float  $amount     Amount to refund.
	 * @param string $reason     Refund reason.
	 * @return array Result.
	 */
	public function process_refund( int $booking_id, float $amount, string $reason = '' ): array {
		$gateway = get_post_meta( $booking_id, '_bkx_payment_gateway', true );

		if ( empty( $gateway ) ) {
			return array(
				'success' => false,
				'message' => __( 'Unable to determine original payment gateway.', 'bkx-google-pay' ),
			);
		}

		// Route to appropriate gateway for refund.
		switch ( $gateway ) {
			case 'stripe':
				return $this->refund_stripe( $booking_id, $amount, $reason );

			default:
				return apply_filters(
					'bkx_google_pay_refund_gateway',
					array(
						'success' => false,
						'message' => __( 'Refunds not supported for this gateway.', 'bkx-google-pay' ),
					),
					$gateway,
					$booking_id,
					$amount,
					$reason
				);
		}
	}

	/**
	 * Refund through Stripe.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param float  $amount     Refund amount.
	 * @param string $reason     Reason.
	 * @return array
	 */
	private function refund_stripe( int $booking_id, float $amount, string $reason ): array {
		$transaction_id = get_post_meta( $booking_id, '_bkx_transaction_id', true );

		if ( empty( $transaction_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'No transaction ID found.', 'bkx-google-pay' ),
			);
		}

		$stripe_settings = get_option( 'bkx_stripe_payments_settings', array() );
		$is_sandbox      = ! empty( $stripe_settings['sandbox'] );
		$secret_key      = $is_sandbox
			? ( $stripe_settings['test_secret_key'] ?? '' )
			: ( $stripe_settings['live_secret_key'] ?? '' );

		if ( empty( $secret_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Stripe is not configured.', 'bkx-google-pay' ),
			);
		}

		$response = wp_remote_post(
			'https://api.stripe.com/v1/refunds',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'payment_intent' => $transaction_id,
					'amount'         => absint( $amount * 100 ),
					'reason'         => 'requested_by_customer',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return array(
				'success' => false,
				'message' => $body['error']['message'] ?? __( 'Refund failed.', 'bkx-google-pay' ),
			);
		}

		if ( 'succeeded' === ( $body['status'] ?? '' ) ) {
			update_post_meta( $booking_id, '_bkx_refund_id', $body['id'] );
			update_post_meta( $booking_id, '_bkx_refund_amount', $amount );
			update_post_meta( $booking_id, '_bkx_payment_status', 'refunded' );

			$this->log_transaction( $booking_id, 'refund', array(
				'refund_id' => $body['id'],
				'amount'    => $amount,
				'reason'    => $reason,
			) );

			return array(
				'success'   => true,
				'refund_id' => $body['id'],
				'message'   => __( 'Refund processed successfully.', 'bkx-google-pay' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Refund was not completed.', 'bkx-google-pay' ),
		);
	}

	/**
	 * Log transaction.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $type       Transaction type.
	 * @param array  $data       Transaction data.
	 * @return void
	 */
	private function log_transaction( int $booking_id, string $type, array $data ): void {
		$log = get_post_meta( $booking_id, '_bkx_payment_log', true );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'type'      => $type,
			'gateway'   => 'google_pay',
			'timestamp' => current_time( 'mysql' ),
			'data'      => $data,
		);

		update_post_meta( $booking_id, '_bkx_payment_log', $log );
	}

	/**
	 * Render payment form fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_payment_fields(): void {
		$settings = get_option( 'bkx_google_pay_settings', array() );
		?>
		<div class="bkx-google-pay-container" id="bkx-google-pay-container">
			<div id="bkx-google-pay-button"></div>
			<div class="bkx-google-pay-message" id="bkx-google-pay-message"></div>
		</div>
		<?php
	}

	/**
	 * Get gateway configuration for JavaScript.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_js_config(): array {
		$settings = get_option( 'bkx_google_pay_settings', array() );
		$gateway  = $settings['gateway'] ?? 'stripe';

		$config = array(
			'environment'   => $settings['environment'] ?? 'TEST',
			'merchantInfo'  => array(
				'merchantId'   => $settings['merchant_id'] ?? '',
				'merchantName' => $settings['merchant_name'] ?? get_bloginfo( 'name' ),
			),
			'allowedCards'  => $settings['allowed_cards'] ?? array( 'AMEX', 'MASTERCARD', 'VISA', 'DISCOVER' ),
			'buttonOptions' => array(
				'buttonColor'  => $settings['button_color'] ?? 'black',
				'buttonType'   => $settings['button_type'] ?? 'pay',
				'buttonLocale' => $settings['button_locale'] ?? 'en',
			),
		);

		if ( isset( self::SUPPORTED_GATEWAYS[ $gateway ] ) ) {
			$config['gateway'] = array(
				'type'              => 'PAYMENT_GATEWAY',
				'parameters'        => array(
					'gateway'           => self::SUPPORTED_GATEWAYS[ $gateway ]['name'],
					'gatewayMerchantId' => $settings['gateway_merchant_id'] ?? '',
				),
				'allowedAuthMethods' => self::SUPPORTED_GATEWAYS[ $gateway ]['auth_methods'],
			);
		}

		return $config;
	}
}

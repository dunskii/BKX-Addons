<?php
/**
 * Payflow Gateway
 *
 * @package BookingX\PayPalPayflow\Services
 * @since   1.0.0
 */

namespace BookingX\PayPalPayflow\Services;

use BookingX\AddonSDK\Abstracts\AbstractPaymentGateway;

/**
 * PayPal Payflow Pro payment gateway.
 *
 * @since 1.0.0
 */
class PayflowGateway extends AbstractPaymentGateway {

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	protected string $id = 'paypal_payflow';

	/**
	 * Live API endpoint.
	 *
	 * @var string
	 */
	private const LIVE_ENDPOINT = 'https://payflowpro.paypal.com';

	/**
	 * Sandbox API endpoint.
	 *
	 * @var string
	 */
	private const SANDBOX_ENDPOINT = 'https://pilot-payflowpro.paypal.com';

	/**
	 * Process payment.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking post ID.
	 * @param array $payment_data Payment data.
	 * @return array Result with success/error.
	 */
	public function process_payment( int $booking_id, array $payment_data ): array {
		$settings = get_option( 'bkx_paypal_payflow_settings', array() );

		// Validate required fields.
		$required = array( 'card_number', 'card_expiry', 'card_cvv' );
		foreach ( $required as $field ) {
			if ( empty( $payment_data[ $field ] ) ) {
				return array(
					'success' => false,
					'message' => __( 'Missing required payment information.', 'bkx-paypal-payflow' ),
				);
			}
		}

		// Build transaction request.
		$request = $this->build_sale_request( $booking_id, $payment_data, $settings );

		// Send to Payflow.
		$response = $this->send_request( $request, $settings );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		// Parse response.
		$parsed = $this->parse_response( $response );

		if ( '0' === $parsed['RESULT'] ) {
			// Success.
			update_post_meta( $booking_id, '_bkx_transaction_id', $parsed['PNREF'] );
			update_post_meta( $booking_id, '_bkx_payment_method', 'paypal_payflow' );
			update_post_meta( $booking_id, '_bkx_payment_status', 'completed' );

			if ( ! empty( $parsed['AUTHCODE'] ) ) {
				update_post_meta( $booking_id, '_bkx_auth_code', $parsed['AUTHCODE'] );
			}

			// Log transaction.
			$this->log_transaction( $booking_id, 'sale', $parsed );

			return array(
				'success'        => true,
				'transaction_id' => $parsed['PNREF'],
				'message'        => __( 'Payment successful.', 'bkx-paypal-payflow' ),
			);
		}

		// Failed.
		$error_message = $this->get_error_message( $parsed['RESULT'], $parsed['RESPMSG'] ?? '' );

		return array(
			'success' => false,
			'message' => $error_message,
			'code'    => $parsed['RESULT'],
		);
	}

	/**
	 * Process refund.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking post ID.
	 * @param float $amount     Amount to refund.
	 * @param string $reason    Refund reason.
	 * @return array Result.
	 */
	public function process_refund( int $booking_id, float $amount, string $reason = '' ): array {
		$settings       = get_option( 'bkx_paypal_payflow_settings', array() );
		$transaction_id = get_post_meta( $booking_id, '_bkx_transaction_id', true );

		if ( ! $transaction_id ) {
			return array(
				'success' => false,
				'message' => __( 'No transaction ID found for this booking.', 'bkx-paypal-payflow' ),
			);
		}

		$request = $this->build_refund_request( $transaction_id, $amount, $settings );
		$response = $this->send_request( $request, $settings );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$parsed = $this->parse_response( $response );

		if ( '0' === $parsed['RESULT'] ) {
			update_post_meta( $booking_id, '_bkx_refund_id', $parsed['PNREF'] );
			update_post_meta( $booking_id, '_bkx_refund_amount', $amount );
			update_post_meta( $booking_id, '_bkx_payment_status', 'refunded' );

			$this->log_transaction( $booking_id, 'refund', $parsed );

			return array(
				'success'   => true,
				'refund_id' => $parsed['PNREF'],
				'message'   => __( 'Refund processed successfully.', 'bkx-paypal-payflow' ),
			);
		}

		return array(
			'success' => false,
			'message' => $parsed['RESPMSG'] ?? __( 'Refund failed.', 'bkx-paypal-payflow' ),
		);
	}

	/**
	 * Build sale request.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $payment_data Payment data.
	 * @param array $settings     Gateway settings.
	 * @return array
	 */
	private function build_sale_request( int $booking_id, array $payment_data, array $settings ): array {
		$is_sandbox = ! empty( $settings['sandbox'] );

		// Get credentials.
		$partner  = $is_sandbox ? ( $settings['sandbox_partner'] ?? '' ) : ( $settings['partner'] ?? '' );
		$vendor   = $is_sandbox ? ( $settings['sandbox_vendor'] ?? '' ) : ( $settings['vendor'] ?? '' );
		$user     = $is_sandbox ? ( $settings['sandbox_user'] ?? '' ) : ( $settings['user'] ?? '' );
		$password = $is_sandbox ? ( $settings['sandbox_password'] ?? '' ) : ( $settings['password'] ?? '' );

		// Get booking data.
		$booking = get_post( $booking_id );
		$amount  = get_post_meta( $booking_id, 'booking_total', true );
		$email   = get_post_meta( $booking_id, 'customer_email', true );
		$name    = get_post_meta( $booking_id, 'customer_name', true );

		// Parse card expiry.
		$expiry = str_replace( array( '/', ' ' ), '', $payment_data['card_expiry'] );

		$request = array(
			'PARTNER'     => $partner,
			'VENDOR'      => $vendor,
			'USER'        => $user,
			'PWD'         => $password,
			'TRXTYPE'     => $settings['transaction_type'] ?? 'S',
			'TENDER'      => 'C', // Credit card.
			'ACCT'        => preg_replace( '/\D/', '', $payment_data['card_number'] ),
			'EXPDATE'     => $expiry,
			'CVV2'        => $payment_data['card_cvv'],
			'AMT'         => number_format( (float) $amount, 2, '.', '' ),
			'CURRENCY'    => 'USD',
			'INVNUM'      => 'BKX-' . $booking_id,
			'COMMENT1'    => 'BookingX Booking #' . $booking_id,
			'VERBOSITY'   => $settings['verbosity'] ?? 'MEDIUM',
		);

		// Add customer info if available.
		if ( $email ) {
			$request['EMAIL'] = $email;
		}

		if ( $name ) {
			$name_parts = explode( ' ', $name, 2 );
			$request['FIRSTNAME'] = $name_parts[0];
			if ( isset( $name_parts[1] ) ) {
				$request['LASTNAME'] = $name_parts[1];
			}
		}

		// Add billing address if provided.
		if ( ! empty( $payment_data['billing_address'] ) ) {
			$request['STREET']  = $payment_data['billing_address'];
			$request['CITY']    = $payment_data['billing_city'] ?? '';
			$request['STATE']   = $payment_data['billing_state'] ?? '';
			$request['ZIP']     = $payment_data['billing_zip'] ?? '';
			$request['COUNTRY'] = $payment_data['billing_country'] ?? 'US';
		}

		return $request;
	}

	/**
	 * Build refund request.
	 *
	 * @since 1.0.0
	 * @param string $transaction_id Original transaction ID.
	 * @param float  $amount         Refund amount.
	 * @param array  $settings       Gateway settings.
	 * @return array
	 */
	private function build_refund_request( string $transaction_id, float $amount, array $settings ): array {
		$is_sandbox = ! empty( $settings['sandbox'] );

		$partner  = $is_sandbox ? ( $settings['sandbox_partner'] ?? '' ) : ( $settings['partner'] ?? '' );
		$vendor   = $is_sandbox ? ( $settings['sandbox_vendor'] ?? '' ) : ( $settings['vendor'] ?? '' );
		$user     = $is_sandbox ? ( $settings['sandbox_user'] ?? '' ) : ( $settings['user'] ?? '' );
		$password = $is_sandbox ? ( $settings['sandbox_password'] ?? '' ) : ( $settings['password'] ?? '' );

		return array(
			'PARTNER'   => $partner,
			'VENDOR'    => $vendor,
			'USER'      => $user,
			'PWD'       => $password,
			'TRXTYPE'   => 'C', // Credit/Refund.
			'TENDER'    => 'C',
			'ORIGID'    => $transaction_id,
			'AMT'       => number_format( $amount, 2, '.', '' ),
			'VERBOSITY' => $settings['verbosity'] ?? 'MEDIUM',
		);
	}

	/**
	 * Send request to Payflow.
	 *
	 * @since 1.0.0
	 * @param array $request  Request data.
	 * @param array $settings Gateway settings.
	 * @return string|\WP_Error
	 */
	private function send_request( array $request, array $settings ) {
		$is_sandbox = ! empty( $settings['sandbox'] );
		$endpoint   = $is_sandbox ? self::SANDBOX_ENDPOINT : self::LIVE_ENDPOINT;

		// Build NVP string.
		$nvp_string = '';
		foreach ( $request as $key => $value ) {
			$nvp_string .= $key . '[' . strlen( $value ) . ']=' . $value . '&';
		}
		$nvp_string = rtrim( $nvp_string, '&' );

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'   => 60,
				'headers'   => array(
					'Content-Type' => 'text/namevalue',
				),
				'body'      => $nvp_string,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error( 'api_error', __( 'Payment gateway communication error.', 'bkx-paypal-payflow' ) );
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Parse NVP response.
	 *
	 * @since 1.0.0
	 * @param string $response Raw response.
	 * @return array
	 */
	private function parse_response( string $response ): array {
		$parsed = array();
		$pairs  = explode( '&', $response );

		foreach ( $pairs as $pair ) {
			$parts = explode( '=', $pair, 2 );
			if ( count( $parts ) === 2 ) {
				$parsed[ $parts[0] ] = $parts[1];
			}
		}

		return $parsed;
	}

	/**
	 * Get human-readable error message.
	 *
	 * @since 1.0.0
	 * @param string $code    Error code.
	 * @param string $message Raw message.
	 * @return string
	 */
	private function get_error_message( string $code, string $message ): string {
		$errors = array(
			'1'   => __( 'User authentication failed.', 'bkx-paypal-payflow' ),
			'4'   => __( 'Invalid amount.', 'bkx-paypal-payflow' ),
			'12'  => __( 'Declined. Please use a different card.', 'bkx-paypal-payflow' ),
			'13'  => __( 'Referral. Contact your card issuer.', 'bkx-paypal-payflow' ),
			'23'  => __( 'Invalid account number.', 'bkx-paypal-payflow' ),
			'24'  => __( 'Invalid expiration date.', 'bkx-paypal-payflow' ),
			'50'  => __( 'Insufficient funds.', 'bkx-paypal-payflow' ),
			'112' => __( 'Address verification failed.', 'bkx-paypal-payflow' ),
			'114' => __( 'CVV2 verification failed.', 'bkx-paypal-payflow' ),
			'125' => __( 'Fraud protection triggered.', 'bkx-paypal-payflow' ),
		);

		if ( isset( $errors[ $code ] ) ) {
			return $errors[ $code ];
		}

		if ( ! empty( $message ) ) {
			return $message;
		}

		return __( 'Payment failed. Please try again.', 'bkx-paypal-payflow' );
	}

	/**
	 * Log transaction.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $type       Transaction type.
	 * @param array  $data       Response data.
	 * @return void
	 */
	private function log_transaction( int $booking_id, string $type, array $data ): void {
		$log = get_post_meta( $booking_id, '_bkx_payment_log', true );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'type'      => $type,
			'gateway'   => 'paypal_payflow',
			'timestamp' => current_time( 'mysql' ),
			'pnref'     => $data['PNREF'] ?? '',
			'result'    => $data['RESULT'] ?? '',
			'respmsg'   => $data['RESPMSG'] ?? '',
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
		$settings = get_option( 'bkx_paypal_payflow_settings', array() );
		?>
		<div class="bkx-payflow-fields">
			<?php if ( ! empty( $settings['description'] ) ) : ?>
				<p class="bkx-payment-description"><?php echo esc_html( $settings['description'] ); ?></p>
			<?php endif; ?>

			<div class="bkx-card-icons">
				<?php
				$card_types = $settings['card_types'] ?? array( 'visa', 'mastercard', 'amex', 'discover' );
				foreach ( $card_types as $type ) :
					?>
					<span class="bkx-card-icon bkx-card-<?php echo esc_attr( $type ); ?>"></span>
				<?php endforeach; ?>
			</div>

			<div class="bkx-form-row">
				<label for="bkx_card_number"><?php esc_html_e( 'Card Number', 'bkx-paypal-payflow' ); ?> <span class="required">*</span></label>
				<input type="text" id="bkx_card_number" name="card_number"
					   placeholder="1234 5678 9012 3456"
					   autocomplete="cc-number"
					   maxlength="19"
					   required>
			</div>

			<div class="bkx-form-row bkx-form-inline">
				<div>
					<label for="bkx_card_expiry"><?php esc_html_e( 'Expiry', 'bkx-paypal-payflow' ); ?> <span class="required">*</span></label>
					<input type="text" id="bkx_card_expiry" name="card_expiry"
						   placeholder="MM/YY"
						   autocomplete="cc-exp"
						   maxlength="5"
						   required>
				</div>
				<div>
					<label for="bkx_card_cvv"><?php esc_html_e( 'CVV', 'bkx-paypal-payflow' ); ?> <span class="required">*</span></label>
					<input type="text" id="bkx_card_cvv" name="card_cvv"
						   placeholder="123"
						   autocomplete="cc-csc"
						   maxlength="4"
						   required>
				</div>
			</div>
		</div>
		<?php
	}
}

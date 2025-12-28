<?php
/**
 * SEPA Gateway (Europe)
 *
 * @package BookingX\RegionalPayments\Gateways
 * @since   1.0.0
 */

namespace BookingX\RegionalPayments\Gateways;

/**
 * SEPA Direct Debit payment gateway for Europe.
 *
 * @since 1.0.0
 */
class SEPAGateway extends AbstractRegionalGateway {

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	protected string $id = 'sepa';

	/**
	 * Supported countries.
	 *
	 * @var array
	 */
	protected array $countries = array(
		'AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES',
		'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IS', 'IT', 'LI',
		'LT', 'LU', 'LV', 'MC', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO',
		'SE', 'SI', 'SK', 'SM', 'VA',
	);

	/**
	 * Supported currencies.
	 *
	 * @var array
	 */
	protected array $currencies = array( 'EUR' );

	/**
	 * Process payment.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking post ID.
	 * @param array $payment_data Payment data.
	 * @return array Result with success/error.
	 */
	public function process_payment( int $booking_id, array $payment_data ): array {
		$settings = $this->get_gateway_settings();
		$amount   = get_post_meta( $booking_id, 'booking_total', true );

		if ( ! $amount || $amount <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid booking amount.', 'bkx-regional-payments' ),
			);
		}

		// Validate IBAN.
		$iban = $payment_data['iban'] ?? '';
		if ( ! $this->validate_iban( $iban ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid IBAN.', 'bkx-regional-payments' ),
			);
		}

		// Get customer info.
		$customer_name  = get_post_meta( $booking_id, 'customer_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );

		// Create SEPA mandate via Stripe.
		$result = $this->create_sepa_mandate( array(
			'amount'      => $amount,
			'currency'    => 'EUR',
			'booking_id'  => $booking_id,
			'iban'        => $iban,
			'customer'    => array(
				'name'  => $customer_name,
				'email' => $customer_email,
			),
			'description' => sprintf( 'BookingX Booking #%d', $booking_id ),
		), $settings );

		if ( ! $result['success'] ) {
			return $result;
		}

		// Store SEPA data.
		update_post_meta( $booking_id, '_bkx_sepa_mandate_id', $result['mandate_id'] );
		update_post_meta( $booking_id, '_bkx_transaction_id', $result['payment_id'] );
		update_post_meta( $booking_id, '_bkx_payment_method', 'sepa' );
		update_post_meta( $booking_id, '_bkx_payment_status', 'processing' );

		$this->log_transaction( $booking_id, 'sepa_mandate_created', $result );

		return array(
			'success' => true,
			'pending' => true,
			'message' => __( 'SEPA Direct Debit initiated. Payment will be collected within 1-3 business days.', 'bkx-regional-payments' ),
		);
	}

	/**
	 * Create SEPA mandate via Stripe.
	 *
	 * @since 1.0.0
	 * @param array $data     Payment data.
	 * @param array $settings Settings.
	 * @return array
	 */
	private function create_sepa_mandate( array $data, array $settings ): array {
		$stripe_settings = get_option( 'bkx_stripe_payments_settings', array() );
		$is_sandbox      = ! empty( $stripe_settings['sandbox'] );
		$secret_key      = $is_sandbox
			? ( $stripe_settings['test_secret_key'] ?? '' )
			: ( $stripe_settings['live_secret_key'] ?? '' );

		if ( empty( $secret_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Stripe is not configured.', 'bkx-regional-payments' ),
			);
		}

		// Create payment method.
		$pm_response = wp_remote_post(
			'https://api.stripe.com/v1/payment_methods',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'type'                    => 'sepa_debit',
					'sepa_debit[iban]'        => $data['iban'],
					'billing_details[name]'   => $data['customer']['name'],
					'billing_details[email]'  => $data['customer']['email'],
				),
			)
		);

		if ( is_wp_error( $pm_response ) ) {
			return array(
				'success' => false,
				'message' => $pm_response->get_error_message(),
			);
		}

		$pm_body = json_decode( wp_remote_retrieve_body( $pm_response ), true );

		if ( isset( $pm_body['error'] ) ) {
			return array(
				'success' => false,
				'message' => $pm_body['error']['message'] ?? __( 'Payment method creation failed.', 'bkx-regional-payments' ),
			);
		}

		// Create payment intent.
		$pi_response = wp_remote_post(
			'https://api.stripe.com/v1/payment_intents',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'amount'                 => $this->format_amount( $data['amount'], 'EUR' ),
					'currency'               => 'eur',
					'payment_method_types[]' => 'sepa_debit',
					'payment_method'         => $pm_body['id'],
					'confirm'                => 'true',
					'mandate_data[customer_acceptance][type]' => 'online',
					'mandate_data[customer_acceptance][online][ip_address]' => $_SERVER['REMOTE_ADDR'] ?? '',
					'mandate_data[customer_acceptance][online][user_agent]' => $_SERVER['HTTP_USER_AGENT'] ?? '',
					'description'            => $data['description'],
					'metadata[booking_id]'   => $data['booking_id'],
				),
			)
		);

		if ( is_wp_error( $pi_response ) ) {
			return array(
				'success' => false,
				'message' => $pi_response->get_error_message(),
			);
		}

		$pi_body = json_decode( wp_remote_retrieve_body( $pi_response ), true );

		if ( isset( $pi_body['error'] ) ) {
			return array(
				'success' => false,
				'message' => $pi_body['error']['message'] ?? __( 'Payment failed.', 'bkx-regional-payments' ),
			);
		}

		return array(
			'success'    => true,
			'payment_id' => $pi_body['id'],
			'mandate_id' => $pi_body['charges']['data'][0]['payment_method_details']['sepa_debit']['mandate'] ?? '',
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
		$payment_id = get_post_meta( $booking_id, '_bkx_transaction_id', true );

		if ( ! $payment_id ) {
			return array(
				'success' => false,
				'message' => __( 'No SEPA transaction found.', 'bkx-regional-payments' ),
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
				'message' => __( 'Stripe is not configured.', 'bkx-regional-payments' ),
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
					'payment_intent' => $payment_id,
					'amount'         => $this->format_amount( $amount, 'EUR' ),
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
				'message' => $body['error']['message'] ?? __( 'Refund failed.', 'bkx-regional-payments' ),
			);
		}

		if ( 'succeeded' === ( $body['status'] ?? '' ) ) {
			update_post_meta( $booking_id, '_bkx_refund_id', $body['id'] );
			update_post_meta( $booking_id, '_bkx_refund_amount', $amount );
			update_post_meta( $booking_id, '_bkx_payment_status', 'refunded' );

			return array(
				'success'   => true,
				'refund_id' => $body['id'],
				'message'   => __( 'Refund processed successfully.', 'bkx-regional-payments' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Refund was not completed.', 'bkx-regional-payments' ),
		);
	}

	/**
	 * Validate IBAN.
	 *
	 * @since 1.0.0
	 * @param string $iban IBAN to validate.
	 * @return bool
	 */
	private function validate_iban( string $iban ): bool {
		// Remove spaces and convert to uppercase.
		$iban = strtoupper( preg_replace( '/\s+/', '', $iban ) );

		// Check length (varies by country, 15-34 characters).
		if ( strlen( $iban ) < 15 || strlen( $iban ) > 34 ) {
			return false;
		}

		// Check format: 2 letters, 2 digits, then alphanumeric.
		if ( ! preg_match( '/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban ) ) {
			return false;
		}

		// Move first 4 characters to end.
		$rearranged = substr( $iban, 4 ) . substr( $iban, 0, 4 );

		// Replace letters with numbers (A=10, B=11, ..., Z=35).
		$numeric = '';
		for ( $i = 0; $i < strlen( $rearranged ); $i++ ) {
			$char = $rearranged[ $i ];
			if ( ctype_alpha( $char ) ) {
				$numeric .= ( ord( $char ) - 55 );
			} else {
				$numeric .= $char;
			}
		}

		// Perform mod-97 check.
		$remainder = bcmod( $numeric, '97' );

		return '1' === $remainder;
	}

	/**
	 * Render payment form fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_payment_fields(): void {
		?>
		<div class="bkx-sepa-fields">
			<p class="bkx-payment-description">
				<?php esc_html_e( 'Pay by SEPA Direct Debit. By providing your IBAN, you authorize us to debit your account.', 'bkx-regional-payments' ); ?>
			</p>

			<div class="bkx-form-row">
				<label for="bkx_sepa_iban"><?php esc_html_e( 'IBAN', 'bkx-regional-payments' ); ?> <span class="required">*</span></label>
				<input type="text" id="bkx_sepa_iban" name="iban"
					   placeholder="DE89370400440532013000"
					   maxlength="34"
					   required>
			</div>

			<div class="bkx-sepa-mandate">
				<p class="bkx-mandate-text">
					<?php
					printf(
						/* translators: %s: Company name */
						esc_html__( 'By providing your IBAN and confirming this payment, you are authorizing %s and Stripe, our payment service provider, to send instructions to your bank to debit your account.', 'bkx-regional-payments' ),
						esc_html( get_bloginfo( 'name' ) )
					);
					?>
				</p>
			</div>

			<div class="bkx-sepa-notice">
				<p><?php esc_html_e( 'SEPA Direct Debit payments typically take 1-3 business days to process.', 'bkx-regional-payments' ); ?></p>
			</div>
		</div>
		<?php
	}
}

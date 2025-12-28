<?php
/**
 * Przelewy24 Gateway (Poland)
 *
 * @package BookingX\RegionalPayments\Gateways
 * @since   1.0.0
 */

namespace BookingX\RegionalPayments\Gateways;

/**
 * Przelewy24 payment gateway for Poland.
 *
 * @since 1.0.0
 */
class Przelewy24Gateway extends AbstractRegionalGateway {

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	protected string $id = 'przelewy24';

	/**
	 * Supported countries.
	 *
	 * @var array
	 */
	protected array $countries = array( 'PL' );

	/**
	 * Supported currencies.
	 *
	 * @var array
	 */
	protected array $currencies = array( 'PLN', 'EUR' );

	/**
	 * Uses redirect flow.
	 *
	 * @var bool
	 */
	protected bool $uses_redirect = true;

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

		$customer_email = get_post_meta( $booking_id, 'customer_email', true );

		// Create Przelewy24 payment via Stripe.
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

		// Note: P24 requires PLN or EUR currency.
		$currency = get_option( 'bkx_currency', 'PLN' );
		if ( ! in_array( $currency, array( 'PLN', 'EUR' ), true ) ) {
			$currency = 'PLN';
		}

		$response = wp_remote_post(
			'https://api.stripe.com/v1/payment_intents',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'amount'                 => $this->format_amount( $amount, $currency ),
					'currency'               => strtolower( $currency ),
					'payment_method_types[]' => 'p24',
					'description'            => sprintf( 'BookingX Booking #%d', $booking_id ),
					'metadata[booking_id]'   => $booking_id,
					'confirm'                => 'true',
					'payment_method_data[type]' => 'p24',
					'payment_method_data[billing_details][email]' => $customer_email,
					'return_url'             => $this->get_return_url( $booking_id ),
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
				'message' => $body['error']['message'] ?? __( 'Payment failed.', 'bkx-regional-payments' ),
			);
		}

		$redirect_url = $body['next_action']['redirect_to_url']['url'] ?? '';

		if ( empty( $redirect_url ) ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to get redirect URL.', 'bkx-regional-payments' ),
			);
		}

		update_post_meta( $booking_id, '_bkx_transaction_id', $body['id'] );
		update_post_meta( $booking_id, '_bkx_payment_method', 'przelewy24' );
		update_post_meta( $booking_id, '_bkx_payment_status', 'pending' );

		$this->log_transaction( $booking_id, 'p24_initiated', array(
			'payment_id' => $body['id'],
		) );

		return array(
			'success'      => true,
			'redirect'     => true,
			'redirect_url' => $redirect_url,
			'message'      => __( 'Redirecting to Przelewy24...', 'bkx-regional-payments' ),
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
				'message' => __( 'No Przelewy24 transaction found.', 'bkx-regional-payments' ),
			);
		}

		$stripe_settings = get_option( 'bkx_stripe_payments_settings', array() );
		$is_sandbox      = ! empty( $stripe_settings['sandbox'] );
		$secret_key      = $is_sandbox
			? ( $stripe_settings['test_secret_key'] ?? '' )
			: ( $stripe_settings['live_secret_key'] ?? '' );

		$currency = get_option( 'bkx_currency', 'PLN' );

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
					'amount'         => $this->format_amount( $amount, $currency ),
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

		if ( ! empty( $body['id'] ) ) {
			update_post_meta( $booking_id, '_bkx_refund_id', $body['id'] );
			update_post_meta( $booking_id, '_bkx_payment_status', 'refunded' );

			return array(
				'success'   => true,
				'refund_id' => $body['id'],
				'message'   => __( 'Refund processed successfully.', 'bkx-regional-payments' ),
			);
		}

		return array(
			'success' => false,
			'message' => $body['error']['message'] ?? __( 'Refund failed.', 'bkx-regional-payments' ),
		);
	}

	/**
	 * Render payment form fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_payment_fields(): void {
		?>
		<div class="bkx-p24-fields">
			<p class="bkx-payment-description">
				<?php esc_html_e( 'Pay securely with Przelewy24. You will be redirected to select your bank.', 'bkx-regional-payments' ); ?>
			</p>
			<div class="bkx-p24-logo">
				<img src="<?php echo esc_url( BKX_REGIONAL_PAYMENTS_URL . 'assets/images/przelewy24.svg' ); ?>"
					 alt="Przelewy24" style="max-width: 120px;">
			</div>
		</div>
		<?php
	}
}

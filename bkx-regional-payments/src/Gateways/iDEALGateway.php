<?php
/**
 * iDEAL Gateway (Netherlands)
 *
 * @package BookingX\RegionalPayments\Gateways
 * @since   1.0.0
 */

namespace BookingX\RegionalPayments\Gateways;

/**
 * iDEAL payment gateway for Netherlands.
 *
 * @since 1.0.0
 */
class iDEALGateway extends AbstractRegionalGateway {

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	protected string $id = 'ideal';

	/**
	 * Supported countries.
	 *
	 * @var array
	 */
	protected array $countries = array( 'NL' );

	/**
	 * Supported currencies.
	 *
	 * @var array
	 */
	protected array $currencies = array( 'EUR' );

	/**
	 * Uses redirect flow.
	 *
	 * @var bool
	 */
	protected bool $uses_redirect = true;

	/**
	 * Dutch banks supporting iDEAL.
	 *
	 * @var array
	 */
	private const BANKS = array(
		'abn_amro'      => 'ABN AMRO',
		'asn_bank'      => 'ASN Bank',
		'bunq'          => 'bunq',
		'ing'           => 'ING',
		'knab'          => 'Knab',
		'rabobank'      => 'Rabobank',
		'regiobank'     => 'RegioBank',
		'revolut'       => 'Revolut',
		'sns_bank'      => 'SNS Bank',
		'triodos_bank'  => 'Triodos Bank',
		'van_lanschot'  => 'Van Lanschot',
	);

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
		$bank     = $payment_data['bank'] ?? '';

		if ( ! $amount || $amount <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid booking amount.', 'bkx-regional-payments' ),
			);
		}

		if ( ! array_key_exists( $bank, self::BANKS ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please select your bank.', 'bkx-regional-payments' ),
			);
		}

		// Create iDEAL payment via Stripe.
		$result = $this->create_ideal_payment( array(
			'amount'      => $amount,
			'currency'    => 'EUR',
			'booking_id'  => $booking_id,
			'bank'        => $bank,
			'description' => sprintf( 'BookingX Booking #%d', $booking_id ),
			'return_url'  => $this->get_return_url( $booking_id ),
		), $settings );

		if ( ! $result['success'] ) {
			return $result;
		}

		// Store payment data.
		update_post_meta( $booking_id, '_bkx_transaction_id', $result['payment_id'] );
		update_post_meta( $booking_id, '_bkx_payment_method', 'ideal' );
		update_post_meta( $booking_id, '_bkx_payment_status', 'pending' );

		$this->log_transaction( $booking_id, 'ideal_initiated', $result );

		return array(
			'success'      => true,
			'redirect'     => true,
			'redirect_url' => $result['redirect_url'],
			'message'      => __( 'Redirecting to your bank...', 'bkx-regional-payments' ),
		);
	}

	/**
	 * Create iDEAL payment via Stripe.
	 *
	 * @since 1.0.0
	 * @param array $data     Payment data.
	 * @param array $settings Settings.
	 * @return array
	 */
	private function create_ideal_payment( array $data, array $settings ): array {
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
					'payment_method_types[]' => 'ideal',
					'description'            => $data['description'],
					'metadata[booking_id]'   => $data['booking_id'],
					'confirm'                => 'true',
					'payment_method_data[type]' => 'ideal',
					'payment_method_data[ideal][bank]' => $data['bank'],
					'return_url'             => $data['return_url'],
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

		return array(
			'success'      => true,
			'payment_id'   => $body['id'],
			'redirect_url' => $redirect_url,
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
		// iDEAL refunds go through Stripe.
		$payment_id = get_post_meta( $booking_id, '_bkx_transaction_id', true );

		if ( ! $payment_id ) {
			return array(
				'success' => false,
				'message' => __( 'No iDEAL transaction found.', 'bkx-regional-payments' ),
			);
		}

		// Use same refund logic as SEPA (through Stripe).
		$stripe_settings = get_option( 'bkx_stripe_payments_settings', array() );
		$is_sandbox      = ! empty( $stripe_settings['sandbox'] );
		$secret_key      = $is_sandbox
			? ( $stripe_settings['test_secret_key'] ?? '' )
			: ( $stripe_settings['live_secret_key'] ?? '' );

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
		<div class="bkx-ideal-fields">
			<p class="bkx-payment-description">
				<?php esc_html_e( 'Pay securely with iDEAL. You will be redirected to your bank to complete the payment.', 'bkx-regional-payments' ); ?>
			</p>

			<div class="bkx-form-row">
				<label for="bkx_ideal_bank"><?php esc_html_e( 'Select your bank', 'bkx-regional-payments' ); ?> <span class="required">*</span></label>
				<select id="bkx_ideal_bank" name="bank" required>
					<option value=""><?php esc_html_e( '-- Select bank --', 'bkx-regional-payments' ); ?></option>
					<?php foreach ( self::BANKS as $code => $name ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<?php
	}
}

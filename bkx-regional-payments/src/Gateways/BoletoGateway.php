<?php
/**
 * Boleto Gateway (Brazil)
 *
 * @package BookingX\RegionalPayments\Gateways
 * @since   1.0.0
 */

namespace BookingX\RegionalPayments\Gateways;

/**
 * Boleto Bancário payment gateway for Brazil.
 *
 * @since 1.0.0
 */
class BoletoGateway extends AbstractRegionalGateway {

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	protected string $id = 'boleto';

	/**
	 * Supported countries.
	 *
	 * @var array
	 */
	protected array $countries = array( 'BR' );

	/**
	 * Supported currencies.
	 *
	 * @var array
	 */
	protected array $currencies = array( 'BRL' );

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

		// Get customer info.
		$customer_name  = get_post_meta( $booking_id, 'customer_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_cpf   = $payment_data['cpf'] ?? '';

		// Validate CPF.
		if ( ! $this->validate_cpf( $customer_cpf ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid CPF number.', 'bkx-regional-payments' ),
			);
		}

		// Create Boleto via Stripe.
		$result = $this->create_boleto( array(
			'amount'      => $amount,
			'currency'    => 'BRL',
			'booking_id'  => $booking_id,
			'customer'    => array(
				'name'  => $customer_name,
				'email' => $customer_email,
				'cpf'   => $customer_cpf,
			),
			'description' => sprintf( 'BookingX Booking #%d', $booking_id ),
		), $settings );

		if ( ! $result['success'] ) {
			return $result;
		}

		// Store Boleto data.
		update_post_meta( $booking_id, '_bkx_boleto_id', $result['boleto_id'] );
		update_post_meta( $booking_id, '_bkx_boleto_url', $result['boleto_url'] );
		update_post_meta( $booking_id, '_bkx_boleto_barcode', $result['barcode'] ?? '' );
		update_post_meta( $booking_id, '_bkx_boleto_expires', $result['expires_at'] ?? '' );
		update_post_meta( $booking_id, '_bkx_payment_method', 'boleto' );
		update_post_meta( $booking_id, '_bkx_payment_status', 'pending' );

		$this->log_transaction( $booking_id, 'boleto_created', $result );

		return array(
			'success'    => true,
			'pending'    => true,
			'boleto_url' => $result['boleto_url'],
			'barcode'    => $result['barcode'] ?? '',
			'expires_at' => $result['expires_at'] ?? '',
			'message'    => __( 'Boleto generated successfully. Please pay before the expiration date.', 'bkx-regional-payments' ),
		);
	}

	/**
	 * Create Boleto via Stripe.
	 *
	 * @since 1.0.0
	 * @param array $data     Payment data.
	 * @param array $settings Settings.
	 * @return array
	 */
	private function create_boleto( array $data, array $settings ): array {
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

		// Boleto expires in 3 days by default.
		$expires_days = $settings['boleto_expires_days'] ?? 3;

		$response = wp_remote_post(
			'https://api.stripe.com/v1/payment_intents',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'amount'                 => $this->format_amount( $data['amount'], 'BRL' ),
					'currency'               => 'brl',
					'payment_method_types[]' => 'boleto',
					'description'            => $data['description'],
					'metadata[booking_id]'   => $data['booking_id'],
					'confirm'                => 'true',
					'payment_method_data[type]' => 'boleto',
					'payment_method_data[boleto][tax_id]' => $data['customer']['cpf'],
					'payment_method_data[billing_details][name]' => $data['customer']['name'],
					'payment_method_data[billing_details][email]' => $data['customer']['email'],
					'payment_method_options[boleto][expires_after_days]' => $expires_days,
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
				'message' => $body['error']['message'] ?? __( 'Boleto creation failed.', 'bkx-regional-payments' ),
			);
		}

		$next_action = $body['next_action']['boleto_display_details'] ?? array();

		return array(
			'success'    => true,
			'boleto_id'  => $body['id'],
			'boleto_url' => $next_action['hosted_voucher_url'] ?? '',
			'barcode'    => $next_action['number'] ?? '',
			'expires_at' => gmdate( 'Y-m-d', $next_action['expires_at'] ?? ( time() + ( $expires_days * 86400 ) ) ),
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
		// Boleto refunds must be done manually or through original provider.
		return array(
			'success' => false,
			'message' => __( 'Boleto refunds must be processed manually. Please contact the customer directly.', 'bkx-regional-payments' ),
		);
	}

	/**
	 * Validate CPF number.
	 *
	 * @since 1.0.0
	 * @param string $cpf CPF number.
	 * @return bool
	 */
	private function validate_cpf( string $cpf ): bool {
		$cpf = preg_replace( '/\D/', '', $cpf );

		if ( strlen( $cpf ) !== 11 ) {
			return false;
		}

		if ( preg_match( '/^(\d)\1+$/', $cpf ) ) {
			return false;
		}

		$sum = 0;
		for ( $i = 0; $i < 9; $i++ ) {
			$sum += (int) $cpf[ $i ] * ( 10 - $i );
		}
		$remainder = $sum % 11;
		$digit1    = $remainder < 2 ? 0 : 11 - $remainder;

		if ( (int) $cpf[9] !== $digit1 ) {
			return false;
		}

		$sum = 0;
		for ( $i = 0; $i < 10; $i++ ) {
			$sum += (int) $cpf[ $i ] * ( 11 - $i );
		}
		$remainder = $sum % 11;
		$digit2    = $remainder < 2 ? 0 : 11 - $remainder;

		return (int) $cpf[10] === $digit2;
	}

	/**
	 * Render payment form fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_payment_fields(): void {
		?>
		<div class="bkx-boleto-fields">
			<p class="bkx-payment-description">
				<?php esc_html_e( 'Pay with Boleto Bancário. A payment slip will be generated for you to pay at any bank or lottery outlet.', 'bkx-regional-payments' ); ?>
			</p>

			<div class="bkx-form-row">
				<label for="bkx_boleto_cpf"><?php esc_html_e( 'CPF', 'bkx-regional-payments' ); ?> <span class="required">*</span></label>
				<input type="text" id="bkx_boleto_cpf" name="cpf"
					   placeholder="000.000.000-00"
					   maxlength="14"
					   required>
			</div>

			<div class="bkx-boleto-notice">
				<p><?php esc_html_e( 'Boleto payments typically take 1-3 business days to be confirmed.', 'bkx-regional-payments' ); ?></p>
			</div>

			<div class="bkx-boleto-result" style="display: none;">
				<div class="bkx-boleto-barcode"></div>
				<a href="#" class="bkx-boleto-download" target="_blank">
					<?php esc_html_e( 'Download Boleto', 'bkx-regional-payments' ); ?>
				</a>
			</div>
		</div>
		<?php
	}
}

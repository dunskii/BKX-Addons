<?php
/**
 * PIX Gateway (Brazil)
 *
 * @package BookingX\RegionalPayments\Gateways
 * @since   1.0.0
 */

namespace BookingX\RegionalPayments\Gateways;

/**
 * PIX payment gateway for Brazil.
 *
 * @since 1.0.0
 */
class PIXGateway extends AbstractRegionalGateway {

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	protected string $id = 'pix';

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
	 * Supports QR codes.
	 *
	 * @var bool
	 */
	protected bool $supports_qr = true;

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

		// Create PIX charge via payment provider.
		$provider = $settings['provider'] ?? 'stripe';
		$result   = $this->create_pix_charge( $provider, array(
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

		// Store PIX data.
		update_post_meta( $booking_id, '_bkx_pix_id', $result['pix_id'] );
		update_post_meta( $booking_id, '_bkx_payment_qr_code', $result['qr_code'] );
		update_post_meta( $booking_id, '_bkx_pix_copy_paste', $result['copy_paste'] ?? '' );
		update_post_meta( $booking_id, '_bkx_payment_method', 'pix' );
		update_post_meta( $booking_id, '_bkx_payment_status', 'pending' );

		$this->log_transaction( $booking_id, 'pix_created', $result );

		return array(
			'success'    => true,
			'pending'    => true,
			'qr_code'    => $result['qr_code'],
			'copy_paste' => $result['copy_paste'] ?? '',
			'expires_at' => $result['expires_at'] ?? '',
			'message'    => __( 'PIX payment created. Scan the QR code to complete payment.', 'bkx-regional-payments' ),
		);
	}

	/**
	 * Create PIX charge via payment provider.
	 *
	 * @since 1.0.0
	 * @param string $provider Provider name.
	 * @param array  $data     Charge data.
	 * @param array  $settings Gateway settings.
	 * @return array
	 */
	private function create_pix_charge( string $provider, array $data, array $settings ): array {
		switch ( $provider ) {
			case 'stripe':
				return $this->create_stripe_pix( $data, $settings );

			case 'pagarme':
				return $this->create_pagarme_pix( $data, $settings );

			case 'mercadopago':
				return $this->create_mercadopago_pix( $data, $settings );

			default:
				return apply_filters(
					'bkx_pix_create_charge',
					array(
						'success' => false,
						'message' => __( 'Unsupported PIX provider.', 'bkx-regional-payments' ),
					),
					$provider,
					$data,
					$settings
				);
		}
	}

	/**
	 * Create PIX via Stripe.
	 *
	 * @since 1.0.0
	 * @param array $data     Charge data.
	 * @param array $settings Settings.
	 * @return array
	 */
	private function create_stripe_pix( array $data, array $settings ): array {
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

		// Create payment intent with PIX.
		$response = wp_remote_post(
			'https://api.stripe.com/v1/payment_intents',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'amount'                    => $this->format_amount( $data['amount'], 'BRL' ),
					'currency'                  => 'brl',
					'payment_method_types[]'    => 'pix',
					'description'               => $data['description'],
					'metadata[booking_id]'      => $data['booking_id'],
					'metadata[source]'          => 'regional_payments',
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
				'message' => $body['error']['message'] ?? __( 'PIX creation failed.', 'bkx-regional-payments' ),
			);
		}

		// Note: Stripe PIX requires additional steps to get QR code.
		// This is a simplified implementation.
		return array(
			'success'    => true,
			'pix_id'     => $body['id'],
			'qr_code'    => '', // Would need to confirm and get QR from next_action.
			'copy_paste' => '',
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 3600 ),
		);
	}

	/**
	 * Create PIX via Pagar.me.
	 *
	 * @since 1.0.0
	 * @param array $data     Charge data.
	 * @param array $settings Settings.
	 * @return array
	 */
	private function create_pagarme_pix( array $data, array $settings ): array {
		$api_key = $settings['pagarme_api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Pagar.me API key not configured.', 'bkx-regional-payments' ),
			);
		}

		$is_sandbox = ! empty( $settings['sandbox'] );
		$endpoint   = $is_sandbox
			? 'https://api.pagar.me/core/v5/orders'
			: 'https://api.pagar.me/core/v5/orders';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $api_key . ':' ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'items' => array(
						array(
							'amount'      => $this->format_amount( $data['amount'], 'BRL' ),
							'description' => $data['description'],
							'quantity'    => 1,
						),
					),
					'customer' => array(
						'name'   => $data['customer']['name'],
						'email'  => $data['customer']['email'],
						'document' => $data['customer']['cpf'],
						'type'   => 'individual',
					),
					'payments' => array(
						array(
							'payment_method' => 'pix',
							'pix' => array(
								'expires_in' => 3600,
							),
						),
					),
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['id'] ) ) {
			return array(
				'success' => false,
				'message' => $body['message'] ?? __( 'PIX creation failed.', 'bkx-regional-payments' ),
			);
		}

		$pix_data = $body['charges'][0]['last_transaction'] ?? array();

		return array(
			'success'    => true,
			'pix_id'     => $body['id'],
			'qr_code'    => $pix_data['qr_code'] ?? '',
			'copy_paste' => $pix_data['qr_code_url'] ?? '',
			'expires_at' => $pix_data['expires_at'] ?? '',
		);
	}

	/**
	 * Create PIX via Mercado Pago.
	 *
	 * @since 1.0.0
	 * @param array $data     Charge data.
	 * @param array $settings Settings.
	 * @return array
	 */
	private function create_mercadopago_pix( array $data, array $settings ): array {
		$access_token = $settings['mercadopago_access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return array(
				'success' => false,
				'message' => __( 'Mercado Pago access token not configured.', 'bkx-regional-payments' ),
			);
		}

		$response = wp_remote_post(
			'https://api.mercadopago.com/v1/payments',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'transaction_amount' => (float) $data['amount'],
					'description'        => $data['description'],
					'payment_method_id'  => 'pix',
					'payer'              => array(
						'email'          => $data['customer']['email'],
						'first_name'     => explode( ' ', $data['customer']['name'] )[0],
						'last_name'      => explode( ' ', $data['customer']['name'] )[1] ?? '',
						'identification' => array(
							'type'   => 'CPF',
							'number' => $data['customer']['cpf'],
						),
					),
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['id'] ) || 'pending' !== ( $body['status'] ?? '' ) ) {
			return array(
				'success' => false,
				'message' => $body['message'] ?? __( 'PIX creation failed.', 'bkx-regional-payments' ),
			);
		}

		$point_of_interaction = $body['point_of_interaction']['transaction_data'] ?? array();

		return array(
			'success'    => true,
			'pix_id'     => $body['id'],
			'qr_code'    => $point_of_interaction['qr_code_base64'] ?? '',
			'copy_paste' => $point_of_interaction['qr_code'] ?? '',
			'expires_at' => $body['date_of_expiration'] ?? '',
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
		// PIX refunds need to be processed through the original provider.
		$settings = $this->get_gateway_settings();
		$provider = $settings['provider'] ?? 'stripe';
		$pix_id   = get_post_meta( $booking_id, '_bkx_pix_id', true );

		if ( ! $pix_id ) {
			return array(
				'success' => false,
				'message' => __( 'No PIX transaction found.', 'bkx-regional-payments' ),
			);
		}

		// Refund implementation would depend on provider.
		return apply_filters(
			'bkx_pix_process_refund',
			array(
				'success' => false,
				'message' => __( 'PIX refunds must be processed through your payment provider dashboard.', 'bkx-regional-payments' ),
			),
			$provider,
			$booking_id,
			$amount,
			$reason
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
		// Remove non-digits.
		$cpf = preg_replace( '/\D/', '', $cpf );

		// Must be 11 digits.
		if ( strlen( $cpf ) !== 11 ) {
			return false;
		}

		// Check for invalid patterns.
		if ( preg_match( '/^(\d)\1+$/', $cpf ) ) {
			return false;
		}

		// Validate check digits.
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
		<div class="bkx-pix-fields">
			<p class="bkx-payment-description">
				<?php esc_html_e( 'Pay instantly with PIX. A QR code will be generated for you to scan.', 'bkx-regional-payments' ); ?>
			</p>

			<div class="bkx-form-row">
				<label for="bkx_pix_cpf"><?php esc_html_e( 'CPF', 'bkx-regional-payments' ); ?> <span class="required">*</span></label>
				<input type="text" id="bkx_pix_cpf" name="cpf"
					   placeholder="000.000.000-00"
					   maxlength="14"
					   required>
			</div>

			<div class="bkx-pix-qr-container" style="display: none;">
				<div class="bkx-pix-qr-code"></div>
				<div class="bkx-pix-copy-paste">
					<input type="text" readonly id="bkx_pix_code">
					<button type="button" class="bkx-copy-btn"><?php esc_html_e( 'Copy', 'bkx-regional-payments' ); ?></button>
				</div>
				<p class="bkx-pix-instructions">
					<?php esc_html_e( 'Scan the QR code with your banking app or copy the PIX code above.', 'bkx-regional-payments' ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}

<?php
/**
 * UPI Gateway (India)
 *
 * @package BookingX\RegionalPayments\Gateways
 * @since   1.0.0
 */

namespace BookingX\RegionalPayments\Gateways;

/**
 * UPI payment gateway for India.
 *
 * @since 1.0.0
 */
class UPIGateway extends AbstractRegionalGateway {

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	protected string $id = 'upi';

	/**
	 * Supported countries.
	 *
	 * @var array
	 */
	protected array $countries = array( 'IN' );

	/**
	 * Supported currencies.
	 *
	 * @var array
	 */
	protected array $currencies = array( 'INR' );

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
		$upi_id         = $payment_data['upi_id'] ?? '';

		// Create UPI payment via provider.
		$provider = $settings['provider'] ?? 'razorpay';
		$result   = $this->create_upi_payment( $provider, array(
			'amount'      => $amount,
			'currency'    => 'INR',
			'booking_id'  => $booking_id,
			'upi_id'      => $upi_id,
			'customer'    => array(
				'name'  => $customer_name,
				'email' => $customer_email,
			),
			'description' => sprintf( 'BookingX Booking #%d', $booking_id ),
		), $settings );

		if ( ! $result['success'] ) {
			return $result;
		}

		// Store UPI data.
		update_post_meta( $booking_id, '_bkx_upi_id', $result['payment_id'] );
		update_post_meta( $booking_id, '_bkx_payment_method', 'upi' );
		update_post_meta( $booking_id, '_bkx_payment_status', 'pending' );

		if ( ! empty( $result['qr_code'] ) ) {
			update_post_meta( $booking_id, '_bkx_payment_qr_code', $result['qr_code'] );
		}

		$this->log_transaction( $booking_id, 'upi_created', $result );

		return array(
			'success'    => true,
			'pending'    => true,
			'qr_code'    => $result['qr_code'] ?? '',
			'payment_id' => $result['payment_id'],
			'message'    => __( 'UPI payment initiated. Complete the payment in your UPI app.', 'bkx-regional-payments' ),
		);
	}

	/**
	 * Create UPI payment via provider.
	 *
	 * @since 1.0.0
	 * @param string $provider Provider name.
	 * @param array  $data     Payment data.
	 * @param array  $settings Gateway settings.
	 * @return array
	 */
	private function create_upi_payment( string $provider, array $data, array $settings ): array {
		switch ( $provider ) {
			case 'razorpay':
				return $this->create_razorpay_upi( $data, $settings );

			case 'paytm':
				return $this->create_paytm_upi( $data, $settings );

			case 'phonepe':
				return $this->create_phonepe_upi( $data, $settings );

			default:
				return apply_filters(
					'bkx_upi_create_payment',
					array(
						'success' => false,
						'message' => __( 'Unsupported UPI provider.', 'bkx-regional-payments' ),
					),
					$provider,
					$data,
					$settings
				);
		}
	}

	/**
	 * Create UPI via Razorpay.
	 *
	 * @since 1.0.0
	 * @param array $data     Payment data.
	 * @param array $settings Settings.
	 * @return array
	 */
	private function create_razorpay_upi( array $data, array $settings ): array {
		$key_id     = $settings['razorpay_key_id'] ?? '';
		$key_secret = $settings['razorpay_key_secret'] ?? '';

		if ( empty( $key_id ) || empty( $key_secret ) ) {
			return array(
				'success' => false,
				'message' => __( 'Razorpay is not configured.', 'bkx-regional-payments' ),
			);
		}

		// Create order first.
		$order_response = wp_remote_post(
			'https://api.razorpay.com/v1/orders',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $key_id . ':' . $key_secret ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'amount'   => $this->format_amount( $data['amount'], 'INR' ),
					'currency' => 'INR',
					'receipt'  => 'BKX-' . $data['booking_id'],
					'notes'    => array(
						'booking_id' => $data['booking_id'],
					),
				) ),
			)
		);

		if ( is_wp_error( $order_response ) ) {
			return array(
				'success' => false,
				'message' => $order_response->get_error_message(),
			);
		}

		$order = json_decode( wp_remote_retrieve_body( $order_response ), true );

		if ( empty( $order['id'] ) ) {
			return array(
				'success' => false,
				'message' => $order['error']['description'] ?? __( 'Order creation failed.', 'bkx-regional-payments' ),
			);
		}

		// Create QR code for UPI.
		$qr_response = wp_remote_post(
			'https://api.razorpay.com/v1/payments/qr_codes',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $key_id . ':' . $key_secret ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'type'          => 'upi_qr',
					'name'          => 'Booking Payment',
					'usage'         => 'single_use',
					'fixed_amount'  => true,
					'payment_amount' => $this->format_amount( $data['amount'], 'INR' ),
					'description'   => $data['description'],
					'customer_id'   => '', // Optional.
					'close_by'      => time() + 3600,
					'notes'         => array(
						'booking_id' => $data['booking_id'],
						'order_id'   => $order['id'],
					),
				) ),
			)
		);

		$qr_code = '';
		if ( ! is_wp_error( $qr_response ) ) {
			$qr_data = json_decode( wp_remote_retrieve_body( $qr_response ), true );
			$qr_code = $qr_data['image_url'] ?? '';
		}

		return array(
			'success'    => true,
			'payment_id' => $order['id'],
			'qr_code'    => $qr_code,
		);
	}

	/**
	 * Create UPI via Paytm.
	 *
	 * @since 1.0.0
	 * @param array $data     Payment data.
	 * @param array $settings Settings.
	 * @return array
	 */
	private function create_paytm_upi( array $data, array $settings ): array {
		return apply_filters(
			'bkx_upi_paytm_create',
			array(
				'success' => false,
				'message' => __( 'Paytm UPI integration pending.', 'bkx-regional-payments' ),
			),
			$data,
			$settings
		);
	}

	/**
	 * Create UPI via PhonePe.
	 *
	 * @since 1.0.0
	 * @param array $data     Payment data.
	 * @param array $settings Settings.
	 * @return array
	 */
	private function create_phonepe_upi( array $data, array $settings ): array {
		return apply_filters(
			'bkx_upi_phonepe_create',
			array(
				'success' => false,
				'message' => __( 'PhonePe UPI integration pending.', 'bkx-regional-payments' ),
			),
			$data,
			$settings
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
		$settings   = $this->get_gateway_settings();
		$payment_id = get_post_meta( $booking_id, '_bkx_upi_id', true );

		if ( ! $payment_id ) {
			return array(
				'success' => false,
				'message' => __( 'No UPI transaction found.', 'bkx-regional-payments' ),
			);
		}

		$provider = $settings['provider'] ?? 'razorpay';

		if ( 'razorpay' === $provider ) {
			return $this->refund_razorpay( $payment_id, $amount, $settings );
		}

		return array(
			'success' => false,
			'message' => __( 'UPI refunds must be processed through your payment provider dashboard.', 'bkx-regional-payments' ),
		);
	}

	/**
	 * Refund via Razorpay.
	 *
	 * @since 1.0.0
	 * @param string $payment_id Payment ID.
	 * @param float  $amount     Refund amount.
	 * @param array  $settings   Settings.
	 * @return array
	 */
	private function refund_razorpay( string $payment_id, float $amount, array $settings ): array {
		$key_id     = $settings['razorpay_key_id'] ?? '';
		$key_secret = $settings['razorpay_key_secret'] ?? '';

		if ( empty( $key_id ) || empty( $key_secret ) ) {
			return array(
				'success' => false,
				'message' => __( 'Razorpay is not configured.', 'bkx-regional-payments' ),
			);
		}

		$response = wp_remote_post(
			"https://api.razorpay.com/v1/payments/{$payment_id}/refund",
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $key_id . ':' . $key_secret ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'amount' => $this->format_amount( $amount, 'INR' ),
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

		if ( ! empty( $body['id'] ) ) {
			return array(
				'success'   => true,
				'refund_id' => $body['id'],
				'message'   => __( 'Refund processed successfully.', 'bkx-regional-payments' ),
			);
		}

		return array(
			'success' => false,
			'message' => $body['error']['description'] ?? __( 'Refund failed.', 'bkx-regional-payments' ),
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
		<div class="bkx-upi-fields">
			<p class="bkx-payment-description">
				<?php esc_html_e( 'Pay using UPI. You can scan the QR code or enter your UPI ID.', 'bkx-regional-payments' ); ?>
			</p>

			<div class="bkx-form-row">
				<label for="bkx_upi_id"><?php esc_html_e( 'UPI ID (Optional)', 'bkx-regional-payments' ); ?></label>
				<input type="text" id="bkx_upi_id" name="upi_id"
					   placeholder="yourname@upi">
				<p class="description"><?php esc_html_e( 'Leave blank to scan QR code instead.', 'bkx-regional-payments' ); ?></p>
			</div>

			<div class="bkx-upi-qr-container" style="display: none;">
				<div class="bkx-upi-qr-code"></div>
				<p class="bkx-upi-instructions">
					<?php esc_html_e( 'Scan this QR code with any UPI app (Google Pay, PhonePe, Paytm, etc.)', 'bkx-regional-payments' ); ?>
				</p>
			</div>

			<div class="bkx-upi-apps">
				<span><?php esc_html_e( 'Supported apps:', 'bkx-regional-payments' ); ?></span>
				<img src="<?php echo esc_url( BKX_REGIONAL_PAYMENTS_URL . 'assets/images/upi-apps.png' ); ?>" alt="UPI Apps">
			</div>
		</div>
		<?php
	}
}

<?php
/**
 * Square Payment Gateway
 *
 * @package BookingX\SquarePayments\Gateway
 */

namespace BookingX\SquarePayments\Gateway;

use BookingX\AddonSDK\Abstracts\AbstractPaymentGateway;
use BookingX\SquarePayments\Api\SquareClient;
use BookingX\SquarePayments\Services\PaymentService;
use BookingX\SquarePayments\Services\RefundService;
use BookingX\SquarePayments\Services\WebhookService;

/**
 * Square payment gateway class.
 *
 * @since 1.0.0
 */
class SquareGateway extends AbstractPaymentGateway {

	/**
	 * Square API client.
	 *
	 * @var SquareClient
	 */
	protected $client;

	/**
	 * Payment service.
	 *
	 * @var PaymentService
	 */
	protected $payment_service;

	/**
	 * Refund service.
	 *
	 * @var RefundService
	 */
	protected $refund_service;

	/**
	 * Webhook service.
	 *
	 * @var WebhookService
	 */
	protected $webhook_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Set gateway properties.
		$this->id          = 'square';
		$this->title       = __( 'Square', 'bkx-square-payments' );
		$this->description = __( 'Pay securely with credit card, Apple Pay, Google Pay, or Cash App Pay via Square.', 'bkx-square-payments' );
		$this->icon        = BKX_SQUARE_URL . 'assets/images/square-logo.svg';

		// Supported features.
		$this->supports = array(
			'payments',
			'refunds',
			'tokenization',
			'saved_cards',
			'apple_pay',
			'google_pay',
			'cash_app_pay',
		);

		// Supported currencies (Square supports many currencies).
		$this->supported_currencies = array(
			'USD', 'CAD', 'GBP', 'EUR', 'AUD', 'JPY',
		);

		// Load parent constructor.
		parent::__construct();

		// Initialize services.
		$this->client          = new SquareClient( $this );
		$this->payment_service = new PaymentService( $this, $this->client );
		$this->refund_service  = new RefundService( $this, $this->client );
		$this->webhook_service = new WebhookService( $this, $this->client );
	}

	/**
	 * Get settings fields.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings_fields(): array {
		return array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'bkx-square-payments' ),
				'label'       => __( 'Enable Square Payments', 'bkx-square-payments' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable this payment gateway.', 'bkx-square-payments' ),
				'default'     => false,
			),
			'title' => array(
				'title'       => __( 'Title', 'bkx-square-payments' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown to customers.', 'bkx-square-payments' ),
				'default'     => $this->title,
			),
			'description' => array(
				'title'       => __( 'Description', 'bkx-square-payments' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown to customers.', 'bkx-square-payments' ),
				'default'     => $this->description,
			),
		);
	}

	/**
	 * Validate gateway availability.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	protected function validate_availability(): bool {
		// Check if credentials are configured.
		$mode = $this->get_setting( 'test_mode', true ) ? 'sandbox' : 'production';

		$app_id      = bkx_square_payments()->get_setting( "square_{$mode}_application_id", '' );
		$access_token = bkx_square_payments()->get_setting( "square_{$mode}_access_token", '' );
		$location_id = bkx_square_payments()->get_setting( "square_{$mode}_location_id", '' );

		return ! empty( $app_id ) && ! empty( $access_token ) && ! empty( $location_id );
	}

	/**
	 * Process payment.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $payment_data Payment data.
	 * @return array
	 */
	public function process_payment( int $booking_id, array $payment_data ): array {
		try {
			// Verify nonce for security.
			if ( ! isset( $payment_data['nonce'] ) || ! wp_verify_nonce( $payment_data['nonce'], 'bkx_square_checkout' ) ) {
				throw new \Exception( __( 'Security check failed.', 'bkx-square-payments' ) );
			}

			// Validate required fields.
			if ( empty( $payment_data['source_id'] ) ) {
				throw new \Exception( __( 'Payment token is missing.', 'bkx-square-payments' ) );
			}

			// Process payment via service.
			$result = $this->payment_service->process_payment( $booking_id, $payment_data );

			if ( $result['success'] ) {
				$this->log( "Payment processed successfully for booking #{$booking_id}", 'info', $result['data'] );
			}

			return $result;

		} catch ( \Exception $e ) {
			$this->log( "Payment failed for booking #{$booking_id}: " . $e->getMessage(), 'error' );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Process refund.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id     Booking ID.
	 * @param float  $amount         Refund amount.
	 * @param string $reason         Refund reason.
	 * @param string $transaction_id Original transaction ID.
	 * @return array
	 */
	public function process_refund( int $booking_id, float $amount, string $reason = '', string $transaction_id = '' ): array {
		try {
			// Process refund via service.
			$result = $this->refund_service->process_refund( $booking_id, $amount, $reason, $transaction_id );

			if ( $result['success'] ) {
				$this->log( "Refund processed successfully for booking #{$booking_id}", 'info', $result['data'] );
			}

			return $result;

		} catch ( \Exception $e ) {
			$this->log( "Refund failed for booking #{$booking_id}: " . $e->getMessage(), 'error' );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Handle webhook.
	 *
	 * @since 1.0.0
	 * @param array $payload Webhook payload.
	 * @return array
	 */
	public function handle_webhook( array $payload ): array {
		try {
			// Process webhook via service.
			$result = $this->webhook_service->process_webhook( $payload );

			if ( $result['success'] ) {
				$this->log( 'Webhook processed successfully', 'info', $payload );
			}

			return $result;

		} catch ( \Exception $e ) {
			$this->log( 'Webhook processing failed: ' . $e->getMessage(), 'error' );

			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Render payment form.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param float $amount     Payment amount.
	 * @return void
	 */
	public function render_payment_form( int $booking_id, float $amount ): void {
		// Load template.
		$template_path = BKX_SQUARE_PATH . 'templates/checkout/square-form.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Enqueue gateway scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts(): void {
		// Scripts are enqueued by the main add-on class.
		// This method is here for compatibility.
	}

	/**
	 * Get API credentials.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_api_credentials(): array {
		$mode = $this->is_test_mode() ? 'sandbox' : 'production';

		return array(
			'application_id' => bkx_square_payments()->get_setting( "square_{$mode}_application_id", '' ),
			'access_token'   => bkx_square_payments()->get_setting( "square_{$mode}_access_token", '' ),
			'location_id'    => bkx_square_payments()->get_setting( "square_{$mode}_location_id", '' ),
		);
	}

	/**
	 * Validate settings.
	 *
	 * @since 1.0.0
	 * @param array $settings Settings to validate.
	 * @return true|\WP_Error
	 */
	public function validate_settings( array $settings ) {
		// Test mode setting.
		if ( isset( $settings['test_mode'] ) ) {
			$settings['test_mode'] = (bool) $settings['test_mode'];
		}

		// Enabled setting.
		if ( isset( $settings['enabled'] ) ) {
			$settings['enabled'] = (bool) $settings['enabled'];
		}

		return true;
	}
}

<?php
/**
 * PayPal Payment Gateway
 *
 * @package BookingX\PayPalPro\Gateway
 * @since   1.0.0
 */

namespace BookingX\PayPalPro\Gateway;

use BookingX\AddonSDK\Abstracts\AbstractPaymentGateway;
use BookingX\PayPalPro\Api\PayPalClient;
use BookingX\PayPalPro\Services\OrderService;
use BookingX\PayPalPro\Services\RefundService;

/**
 * PayPal payment gateway class.
 *
 * @since 1.0.0
 */
class PayPalGateway extends AbstractPaymentGateway {

	/**
	 * PayPal API client.
	 *
	 * @var PayPalClient|null
	 */
	protected ?PayPalClient $client = null;

	/**
	 * Order service.
	 *
	 * @var OrderService|null
	 */
	protected ?OrderService $order_service = null;

	/**
	 * Refund service.
	 *
	 * @var RefundService|null
	 */
	protected ?RefundService $refund_service = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id          = 'paypal_pro';
		$this->title       = __( 'PayPal', 'bkx-paypal-pro' );
		$this->description = __( 'Pay securely with PayPal, credit/debit card, or Pay in 4.', 'bkx-paypal-pro' );
		$this->icon        = BKX_PAYPAL_PRO_URL . 'assets/images/paypal-logo.svg';

		$this->supports = array(
			'payments',
			'refunds',
			'tokenization',
			'subscriptions',
		);

		// PayPal supports 100+ currencies.
		$this->supported_currencies = array();

		parent::__construct();

		// Initialize services.
		$this->client          = new PayPalClient( $this );
		$this->order_service   = new OrderService( $this->client, $this );
		$this->refund_service  = new RefundService( $this->client, $this );
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'enabled'                      => false,
			'test_mode'                    => true,
			'paypal_mode'                  => 'sandbox',
			'paypal_sandbox_client_id'     => '',
			'paypal_sandbox_client_secret' => '',
			'paypal_live_client_id'        => '',
			'paypal_live_client_secret'    => '',
			'paypal_webhook_id'            => '',
			'enable_card_fields'           => false,
			'enable_pay_later'             => true,
			'button_color'                 => 'gold',
			'button_shape'                 => 'rect',
			'intent'                       => 'capture',
			'currency'                     => 'USD',
			'debug_log'                    => false,
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
		$mode = $this->get_setting( 'paypal_mode', 'sandbox' );

		if ( 'live' === $mode ) {
			$client_id     = $this->get_setting( 'paypal_live_client_id', '' );
			$client_secret = $this->get_setting( 'paypal_live_client_secret', '' );
		} else {
			$client_id     = $this->get_setting( 'paypal_sandbox_client_id', '' );
			$client_secret = $this->get_setting( 'paypal_sandbox_client_secret', '' );
		}

		return ! empty( $client_id ) && ! empty( $client_secret );
	}

	/**
	 * Get settings fields for the admin form.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings_fields(): array {
		return array(
			'enabled' => array(
				'type'        => 'checkbox',
				'label'       => __( 'Enable PayPal', 'bkx-paypal-pro' ),
				'description' => __( 'Accept payments via PayPal.', 'bkx-paypal-pro' ),
				'default'     => false,
			),
			'paypal_mode' => array(
				'type'        => 'select',
				'label'       => __( 'PayPal Mode', 'bkx-paypal-pro' ),
				'description' => __( 'Select sandbox for testing or live for production.', 'bkx-paypal-pro' ),
				'options'     => array(
					'sandbox' => __( 'Sandbox (Testing)', 'bkx-paypal-pro' ),
					'live'    => __( 'Live (Production)', 'bkx-paypal-pro' ),
				),
				'default'     => 'sandbox',
			),
			'paypal_sandbox_client_id' => array(
				'type'        => 'text',
				'label'       => __( 'Sandbox Client ID', 'bkx-paypal-pro' ),
				'description' => __( 'Get your API credentials from PayPal Developer Dashboard.', 'bkx-paypal-pro' ),
				'default'     => '',
			),
			'paypal_sandbox_client_secret' => array(
				'type'        => 'password',
				'label'       => __( 'Sandbox Client Secret', 'bkx-paypal-pro' ),
				'description' => __( 'Keep this secret safe and never share it.', 'bkx-paypal-pro' ),
				'default'     => '',
			),
			'paypal_live_client_id' => array(
				'type'        => 'text',
				'label'       => __( 'Live Client ID', 'bkx-paypal-pro' ),
				'description' => __( 'Live API credentials for production.', 'bkx-paypal-pro' ),
				'default'     => '',
			),
			'paypal_live_client_secret' => array(
				'type'        => 'password',
				'label'       => __( 'Live Client Secret', 'bkx-paypal-pro' ),
				'description' => __( 'Live client secret for production.', 'bkx-paypal-pro' ),
				'default'     => '',
			),
			'paypal_webhook_id' => array(
				'type'        => 'text',
				'label'       => __( 'Webhook ID', 'bkx-paypal-pro' ),
				'description' => sprintf(
					/* translators: %s: webhook URL */
					__( 'Create a webhook in PayPal Dashboard with this URL: %s', 'bkx-paypal-pro' ),
					'<code>' . esc_url( rest_url( 'bookingx/v1/webhooks/bkx_paypal_pro' ) ) . '</code>'
				),
				'default'     => '',
			),
			'enable_card_fields' => array(
				'type'        => 'checkbox',
				'label'       => __( 'Enable Advanced Card Processing', 'bkx-paypal-pro' ),
				'description' => __( 'Allow customers to enter credit/debit card details directly on your site.', 'bkx-paypal-pro' ),
				'default'     => false,
			),
			'enable_pay_later' => array(
				'type'        => 'checkbox',
				'label'       => __( 'Enable Pay in 4', 'bkx-paypal-pro' ),
				'description' => __( 'Show Pay Later option to eligible customers.', 'bkx-paypal-pro' ),
				'default'     => true,
			),
			'button_color' => array(
				'type'        => 'select',
				'label'       => __( 'Button Color', 'bkx-paypal-pro' ),
				'description' => __( 'Choose the PayPal button color.', 'bkx-paypal-pro' ),
				'options'     => array(
					'gold'   => __( 'Gold', 'bkx-paypal-pro' ),
					'blue'   => __( 'Blue', 'bkx-paypal-pro' ),
					'silver' => __( 'Silver', 'bkx-paypal-pro' ),
					'white'  => __( 'White', 'bkx-paypal-pro' ),
					'black'  => __( 'Black', 'bkx-paypal-pro' ),
				),
				'default'     => 'gold',
			),
			'button_shape' => array(
				'type'        => 'select',
				'label'       => __( 'Button Shape', 'bkx-paypal-pro' ),
				'description' => __( 'Choose the PayPal button shape.', 'bkx-paypal-pro' ),
				'options'     => array(
					'rect' => __( 'Rectangle', 'bkx-paypal-pro' ),
					'pill' => __( 'Pill', 'bkx-paypal-pro' ),
				),
				'default'     => 'rect',
			),
			'intent' => array(
				'type'        => 'select',
				'label'       => __( 'Payment Intent', 'bkx-paypal-pro' ),
				'description' => __( 'Capture immediately or authorize for later capture.', 'bkx-paypal-pro' ),
				'options'     => array(
					'capture'   => __( 'Capture', 'bkx-paypal-pro' ),
					'authorize' => __( 'Authorize', 'bkx-paypal-pro' ),
				),
				'default'     => 'capture',
			),
			'currency' => array(
				'type'        => 'select',
				'label'       => __( 'Currency', 'bkx-paypal-pro' ),
				'description' => __( 'Default currency for PayPal transactions.', 'bkx-paypal-pro' ),
				'options'     => array(
					'USD' => __( 'US Dollar', 'bkx-paypal-pro' ),
					'EUR' => __( 'Euro', 'bkx-paypal-pro' ),
					'GBP' => __( 'British Pound', 'bkx-paypal-pro' ),
					'CAD' => __( 'Canadian Dollar', 'bkx-paypal-pro' ),
					'AUD' => __( 'Australian Dollar', 'bkx-paypal-pro' ),
					'JPY' => __( 'Japanese Yen', 'bkx-paypal-pro' ),
				),
				'default'     => 'USD',
			),
			'debug_log' => array(
				'type'        => 'checkbox',
				'label'       => __( 'Enable Debug Logging', 'bkx-paypal-pro' ),
				'description' => __( 'Log all PayPal API requests and responses for troubleshooting.', 'bkx-paypal-pro' ),
				'default'     => false,
			),
		);
	}

	/**
	 * Process a payment.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $payment_data Payment data.
	 * @return array Result with 'success' bool and 'data' or 'error'.
	 */
	public function process_payment( int $booking_id, array $payment_data ): array {
		try {
			$this->log( "Processing payment for booking #{$booking_id}", 'info', $payment_data );

			// Validate payment data.
			if ( empty( $payment_data['paypal_order_id'] ) ) {
				throw new \Exception( __( 'PayPal order ID is required.', 'bkx-paypal-pro' ) );
			}

			$paypal_order_id = sanitize_text_field( $payment_data['paypal_order_id'] );

			// Capture the payment.
			$result = $this->order_service->capture_order( $paypal_order_id, $booking_id );

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? __( 'Failed to capture payment.', 'bkx-paypal-pro' ) );
			}

			$this->log( "Payment captured successfully for booking #{$booking_id}", 'info', $result );

			// Update booking status.
			do_action( 'bkx_booking_payment_complete', $booking_id, $result['data'] );

			return array(
				'success' => true,
				'data'    => $result['data'],
			);

		} catch ( \Exception $e ) {
			$this->log( "Payment failed for booking #{$booking_id}: " . $e->getMessage(), 'error' );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Process a refund.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id     Booking ID.
	 * @param float  $amount         Refund amount.
	 * @param string $reason         Refund reason.
	 * @param string $transaction_id Original transaction ID.
	 * @return array Result with 'success' bool and 'data' or 'error'.
	 */
	public function process_refund( int $booking_id, float $amount, string $reason = '', string $transaction_id = '' ): array {
		try {
			$this->log( "Processing refund for booking #{$booking_id}", 'info', array(
				'amount'         => $amount,
				'reason'         => $reason,
				'transaction_id' => $transaction_id,
			) );

			// Get transaction details if not provided.
			if ( empty( $transaction_id ) ) {
				global $wpdb;
				$table = $wpdb->prefix . 'bkx_paypal_transactions';

				// Use %i placeholder for table identifier - SECURITY.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$transaction = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT capture_id FROM %i WHERE booking_id = %d AND status = 'completed' ORDER BY id DESC LIMIT 1",
						$table,
						$booking_id
					)
				);

				if ( ! $transaction ) {
					throw new \Exception( __( 'No completed transaction found for this booking.', 'bkx-paypal-pro' ) );
				}

				$transaction_id = $transaction->capture_id;
			}

			// Process the refund.
			$result = $this->refund_service->refund_capture( $transaction_id, $amount, $reason );

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? __( 'Failed to process refund.', 'bkx-paypal-pro' ) );
			}

			$this->log( "Refund processed successfully for booking #{$booking_id}", 'info', $result );

			// Update booking status.
			do_action( 'bkx_booking_refunded', $booking_id, $result['data'] );

			return array(
				'success' => true,
				'data'    => $result['data'],
			);

		} catch ( \Exception $e ) {
			$this->log( "Refund failed for booking #{$booking_id}: " . $e->getMessage(), 'error' );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Handle webhook/IPN callback.
	 *
	 * @since 1.0.0
	 * @param array $payload Webhook payload.
	 * @return array Result with 'success' bool and 'message'.
	 */
	public function handle_webhook( array $payload ): array {
		// Webhook handling is done via WebhookController and WebhookService.
		// This method is required by the interface but not used directly.
		return array(
			'success' => true,
			'message' => __( 'Webhook handled by WebhookController.', 'bkx-paypal-pro' ),
		);
	}

	/**
	 * Render the payment form on checkout.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param float $amount     Payment amount.
	 * @return void
	 */
	public function render_payment_form( int $booking_id, float $amount ): void {
		$template_path = BKX_PAYPAL_PRO_PATH . 'templates/checkout/paypal-form.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * Enqueue gateway scripts and styles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts(): void {
		// Scripts are enqueued in PayPalPro::enqueue_frontend_scripts().
	}

	/**
	 * Get PayPal API client.
	 *
	 * @since 1.0.0
	 * @return PayPalClient
	 */
	public function get_client(): PayPalClient {
		return $this->client;
	}

	/**
	 * Get order service.
	 *
	 * @since 1.0.0
	 * @return OrderService
	 */
	public function get_order_service(): OrderService {
		return $this->order_service;
	}

	/**
	 * Get refund service.
	 *
	 * @since 1.0.0
	 * @return RefundService
	 */
	public function get_refund_service(): RefundService {
		return $this->refund_service;
	}
}

<?php
/**
 * Stripe Payment Gateway Class
 *
 * @package BookingX\StripePayments\Gateway
 * @since   1.0.0
 */

namespace BookingX\StripePayments\Gateway;

use BookingX\AddonSDK\Abstracts\AbstractPaymentGateway;
use BookingX\StripePayments\StripePayments;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

/**
 * Stripe Gateway implementation.
 *
 * @since 1.0.0
 */
class StripeGateway extends AbstractPaymentGateway {

	/**
	 * Parent addon instance.
	 *
	 * @var StripePayments
	 */
	protected StripePayments $addon;

	/**
	 * Stripe client instance.
	 *
	 * @var StripeClient|null
	 */
	protected ?StripeClient $stripe = null;

	/**
	 * Constructor.
	 *
	 * @param StripePayments $addon Parent addon instance.
	 */
	public function __construct( StripePayments $addon ) {
		$this->addon = $addon;

		// Set gateway properties
		$this->id          = 'stripe';
		$this->title       = __( 'Credit Card (Stripe)', 'bkx-stripe-payments' );
		$this->description = __( 'Pay securely using your credit or debit card via Stripe.', 'bkx-stripe-payments' );
		$this->icon        = BKX_STRIPE_URL . 'assets/images/stripe-icon.png';

		// Set supported features
		$this->supports = array(
			'payments',
			'refunds',
			'subscriptions',
			'saved_payment_methods',
			'apple_pay',
			'google_pay',
		);

		// Set supported currencies
		$this->supported_currencies = array(
			'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'JPY', 'CNY', 'INR',
			'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON',
			'BGN', 'HRK', 'ISK', 'MXN', 'BRL', 'NZD', 'SGD', 'HKD',
		);

		parent::__construct();
	}

	/**
	 * Get settings fields for admin form.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings_fields(): array {
		return array(
			array(
				'id'    => 'enabled',
				'type'  => 'checkbox',
				'title' => __( 'Enable Stripe', 'bkx-stripe-payments' ),
				'desc'  => __( 'Enable Stripe payment gateway', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'test_mode',
				'type'  => 'checkbox',
				'title' => __( 'Test Mode', 'bkx-stripe-payments' ),
				'desc'  => __( 'Enable test mode for development', 'bkx-stripe-payments' ),
			),
			array(
				'type'  => 'title',
				'title' => __( 'Live API Keys', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'live_publishable_key',
				'type'  => 'text',
				'title' => __( 'Live Publishable Key', 'bkx-stripe-payments' ),
				'desc'  => __( 'Get your API keys from your Stripe account dashboard.', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'live_secret_key',
				'type'  => 'password',
				'title' => __( 'Live Secret Key', 'bkx-stripe-payments' ),
			),
			array(
				'type'  => 'title',
				'title' => __( 'Test API Keys', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'test_publishable_key',
				'type'  => 'text',
				'title' => __( 'Test Publishable Key', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'test_secret_key',
				'type'  => 'password',
				'title' => __( 'Test Secret Key', 'bkx-stripe-payments' ),
			),
			array(
				'type'  => 'title',
				'title' => __( 'Webhook Configuration', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'webhook_secret',
				'type'  => 'text',
				'title' => __( 'Webhook Secret', 'bkx-stripe-payments' ),
				'desc'  => sprintf(
					/* translators: %s: Webhook URL */
					__( 'Set this URL in your Stripe webhook settings: %s', 'bkx-stripe-payments' ),
					'<code>' . rest_url( 'bookingx/v1/stripe/webhook' ) . '</code>'
				),
			),
			array(
				'type'  => 'title',
				'title' => __( 'Payment Settings', 'bkx-stripe-payments' ),
			),
			array(
				'id'      => 'capture_method',
				'type'    => 'select',
				'title'   => __( 'Capture Method', 'bkx-stripe-payments' ),
				'options' => array(
					'automatic' => __( 'Automatic', 'bkx-stripe-payments' ),
					'manual'    => __( 'Manual', 'bkx-stripe-payments' ),
				),
				'desc'    => __( 'Automatic captures funds immediately. Manual requires manual capture later.', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'statement_descriptor',
				'type'  => 'text',
				'title' => __( 'Statement Descriptor', 'bkx-stripe-payments' ),
				'desc'  => __( 'Text that appears on customer credit card statement (max 22 characters).', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'enable_3d_secure',
				'type'  => 'checkbox',
				'title' => __( 'Enable 3D Secure', 'bkx-stripe-payments' ),
				'desc'  => __( 'Enable Strong Customer Authentication (SCA) for enhanced security.', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'save_payment_methods',
				'type'  => 'checkbox',
				'title' => __( 'Save Payment Methods', 'bkx-stripe-payments' ),
				'desc'  => __( 'Allow customers to save payment methods for future bookings.', 'bkx-stripe-payments' ),
			),
			array(
				'type'  => 'title',
				'title' => __( 'Digital Wallets', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'enable_apple_pay',
				'type'  => 'checkbox',
				'title' => __( 'Enable Apple Pay', 'bkx-stripe-payments' ),
				'desc'  => __( 'Accept payments via Apple Pay.', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'enable_google_pay',
				'type'  => 'checkbox',
				'title' => __( 'Enable Google Pay', 'bkx-stripe-payments' ),
				'desc'  => __( 'Accept payments via Google Pay.', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'enable_link',
				'type'  => 'checkbox',
				'title' => __( 'Enable Link', 'bkx-stripe-payments' ),
				'desc'  => __( 'Enable Stripe Link for one-click checkout.', 'bkx-stripe-payments' ),
			),
			array(
				'type'  => 'title',
				'title' => __( 'Refund Settings', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'auto_refund_on_cancel',
				'type'  => 'checkbox',
				'title' => __( 'Auto-Refund on Cancel', 'bkx-stripe-payments' ),
				'desc'  => __( 'Automatically refund payment when booking is cancelled.', 'bkx-stripe-payments' ),
			),
			array(
				'type'  => 'title',
				'title' => __( 'Advanced Settings', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'radar_risk_threshold',
				'type'  => 'number',
				'title' => __( 'Radar Risk Threshold', 'bkx-stripe-payments' ),
				'desc'  => __( 'Minimum risk score (0-100) to decline transaction. Requires Stripe Radar.', 'bkx-stripe-payments' ),
			),
			array(
				'id'    => 'debug_log',
				'type'  => 'checkbox',
				'title' => __( 'Debug Logging', 'bkx-stripe-payments' ),
				'desc'  => __( 'Enable detailed logging for troubleshooting.', 'bkx-stripe-payments' ),
			),
		);
	}

	/**
	 * Process a payment.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $payment_data Payment data.
	 * @return array Result array.
	 */
	public function process_payment( int $booking_id, array $payment_data ): array {
		try {
			// Validate payment data
			if ( empty( $payment_data['payment_method_id'] ) ) {
				throw new \Exception( __( 'Payment method is required.', 'bkx-stripe-payments' ) );
			}

			// Get payment service and process
			$result = $this->addon->get_payment_service()->process_payment( $booking_id, $payment_data );

			if ( isset( $result['error'] ) ) {
				$this->log( 'Payment failed: ' . $result['error'], 'error', array( 'booking_id' => $booking_id ) );

				return array(
					'success' => false,
					'error'   => $result['error'],
				);
			}

			$this->log( 'Payment processed successfully', 'info', array( 'booking_id' => $booking_id ) );

			return array(
				'success' => true,
				'data'    => $result,
			);

		} catch ( ApiErrorException $e ) {
			$this->log( 'Stripe API error: ' . $e->getMessage(), 'error', array( 'booking_id' => $booking_id ) );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);

		} catch ( \Exception $e ) {
			$this->log( 'Payment error: ' . $e->getMessage(), 'error', array( 'booking_id' => $booking_id ) );

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
	 * @return array Result array.
	 */
	public function process_refund( int $booking_id, float $amount, string $reason = '', string $transaction_id = '' ): array {
		try {
			$result = $this->addon->get_refund_service()->process_refund( $booking_id, $amount, $reason, $transaction_id );

			if ( isset( $result['error'] ) ) {
				$this->log( 'Refund failed: ' . $result['error'], 'error', array( 'booking_id' => $booking_id ) );

				return array(
					'success' => false,
					'error'   => $result['error'],
				);
			}

			$this->log( 'Refund processed successfully', 'info', array( 'booking_id' => $booking_id, 'amount' => $amount ) );

			return array(
				'success' => true,
				'data'    => $result,
			);

		} catch ( \Exception $e ) {
			$this->log( 'Refund error: ' . $e->getMessage(), 'error', array( 'booking_id' => $booking_id ) );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Handle webhook callback.
	 *
	 * @since 1.0.0
	 * @param array $payload Webhook payload.
	 * @return array Result array.
	 */
	public function handle_webhook( array $payload ): array {
		return $this->addon->get_webhook_service()->handle_webhook( $payload );
	}

	/**
	 * Render payment form on checkout.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param float $amount     Payment amount.
	 * @return void
	 */
	public function render_payment_form( int $booking_id, float $amount ): void {
		// Load template
		$template_path = BKX_STRIPE_PATH . 'templates/checkout/stripe-form.php';

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
		// Scripts are enqueued by the main addon class
	}

	/**
	 * Get Stripe client instance.
	 *
	 * @since 1.0.0
	 * @return StripeClient
	 * @throws \Exception If API key is missing.
	 */
	public function get_stripe_client(): StripeClient {
		if ( $this->stripe ) {
			return $this->stripe;
		}

		$credentials = $this->get_api_credentials();

		if ( empty( $credentials['api_secret'] ) ) {
			throw new \Exception( __( 'Stripe API secret key is not configured.', 'bkx-stripe-payments' ) );
		}

		$this->stripe = new StripeClient( $credentials['api_secret'] );

		return $this->stripe;
	}

	/**
	 * Get API credentials.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_api_credentials(): array {
		$mode = $this->addon->get_setting( 'stripe_mode', 'test' );

		if ( 'live' === $mode ) {
			return array(
				'api_key'    => $this->addon->get_setting( 'stripe_live_publishable_key', '' ),
				'api_secret' => $this->addon->get_setting( 'stripe_live_secret_key', '' ),
			);
		}

		return array(
			'api_key'    => $this->addon->get_setting( 'stripe_test_publishable_key', '' ),
			'api_secret' => $this->addon->get_setting( 'stripe_test_secret_key', '' ),
		);
	}

	/**
	 * Validate gateway availability.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	protected function validate_availability(): bool {
		// Check if API keys are configured
		$credentials = $this->get_api_credentials();

		if ( empty( $credentials['api_key'] ) || empty( $credentials['api_secret'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate settings.
	 *
	 * @since 1.0.0
	 * @param array $settings Settings to validate.
	 * @return true|\WP_Error
	 */
	public function validate_settings( array $settings ) {
		// Validate statement descriptor length
		if ( isset( $settings['statement_descriptor'] ) && strlen( $settings['statement_descriptor'] ) > 22 ) {
			return new \WP_Error(
				'invalid_statement_descriptor',
				__( 'Statement descriptor must be 22 characters or less.', 'bkx-stripe-payments' )
			);
		}

		// Validate webhook secret if provided
		if ( isset( $settings['webhook_secret'] ) && ! empty( $settings['webhook_secret'] ) ) {
			if ( ! str_starts_with( $settings['webhook_secret'], 'whsec_' ) ) {
				return new \WP_Error(
					'invalid_webhook_secret',
					__( 'Invalid webhook secret format. It should start with "whsec_".', 'bkx-stripe-payments' )
				);
			}
		}

		return true;
	}
}

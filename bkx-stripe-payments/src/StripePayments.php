<?php
/**
 * Main Stripe Payments Addon Class
 *
 * @package BookingX\StripePayments
 * @since   1.0.0
 */

namespace BookingX\StripePayments;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasRestApi;
use BookingX\AddonSDK\Traits\HasWebhooks;
use BookingX\StripePayments\Gateway\StripeGateway;
use BookingX\StripePayments\Admin\SettingsPage;
use BookingX\StripePayments\Api\WebhookController;
use BookingX\StripePayments\Services\PaymentService;
use BookingX\StripePayments\Services\RefundService;
use BookingX\StripePayments\Services\CustomerService;
use BookingX\StripePayments\Services\WebhookService;
use BookingX\StripePayments\Migrations\CreateStripeTables;

/**
 * Main addon class for Stripe Payments.
 *
 * @since 1.0.0
 */
class StripePayments extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasRestApi;
	use HasWebhooks;

	/**
	 * Stripe Gateway instance.
	 *
	 * @var StripeGateway
	 */
	protected ?StripeGateway $gateway = null;

	/**
	 * Payment service instance.
	 *
	 * @var PaymentService
	 */
	protected ?PaymentService $payment_service = null;

	/**
	 * Refund service instance.
	 *
	 * @var RefundService
	 */
	protected ?RefundService $refund_service = null;

	/**
	 * Customer service instance.
	 *
	 * @var CustomerService
	 */
	protected ?CustomerService $customer_service = null;

	/**
	 * Webhook service instance.
	 *
	 * @var WebhookService
	 */
	protected ?WebhookService $webhook_service = null;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Path to the main plugin file.
	 */
	public function __construct( string $plugin_file ) {
		// Set addon properties
		$this->addon_id       = 'bkx_stripe_payments';
		$this->addon_name     = __( 'BookingX - Stripe Payments', 'bkx-stripe-payments' );
		$this->version        = BKX_STRIPE_VERSION;
		$this->text_domain    = 'bkx-stripe-payments';
		$this->min_bkx_version = '2.0.0';
		$this->min_php_version = '7.4';
		$this->min_wp_version  = '5.8';

		parent::__construct( $plugin_file );
	}

	/**
	 * Register with BookingX Framework.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function register_with_framework(): void {
		// Initialize gateway instance
		$this->gateway = new StripeGateway( $this );

		// Register payment gateway with BookingX
		add_filter( 'bkx_payment_gateways', array( $this, 'register_gateway' ) );

		// Register settings tab
		add_filter( 'bkx_settings_tabs', array( $this, 'register_settings_tab' ) );

		// Register this addon as active
		add_filter( "bookingx_addon_{$this->addon_id}_active", '__return_true' );
	}

	/**
	 * Register Stripe gateway with BookingX.
	 *
	 * @since 1.0.0
	 * @param array $gateways Existing gateways.
	 * @return array
	 */
	public function register_gateway( array $gateways ): array {
		$gateways['stripe'] = $this->gateway;
		return $gateways;
	}

	/**
	 * Register settings tab.
	 *
	 * @since 1.0.0
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function register_settings_tab( array $tabs ): array {
		$tabs['stripe_payments'] = __( 'Stripe Payments', 'bkx-stripe-payments' );
		return $tabs;
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_hooks(): void {
		// Enqueue scripts on frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

		// Handle booking status changes
		add_action( 'bkx_booking_status_changed', array( $this, 'handle_booking_status_change' ), 10, 3 );

		// Handle booking cancellation
		add_action( 'bkx_booking_cancelled', array( $this, 'handle_booking_cancelled' ), 10, 1 );

		// Add payment meta to booking
		add_action( 'bkx_booking_created', array( $this, 'save_payment_metadata' ), 10, 2 );
	}

	/**
	 * Initialize admin functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_admin(): void {
		// Initialize settings page
		$settings_page = new SettingsPage( $this );

		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add meta boxes to booking edit screen
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );

		// Add action links to plugin list
		add_filter( 'plugin_action_links_' . BKX_STRIPE_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Initialize frontend functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_frontend(): void {
		// Gateway handles frontend rendering
		if ( $this->gateway ) {
			add_action( 'bkx_payment_form_stripe', array( $this->gateway, 'render_payment_form' ), 10, 2 );
		}
	}

	/**
	 * Initialize REST API endpoints.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_rest_api(): void {
		$this->register_rest_routes();
		$this->register_webhook_endpoint();
	}

	/**
	 * Get REST routes.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_rest_routes(): array {
		$webhook_controller = new WebhookController( $this );

		return array(
			'/stripe/webhook' => array(
				'methods'             => 'POST',
				'callback'            => array( $webhook_controller, 'handle_webhook' ),
				'permission_callback' => '__return_true', // Stripe webhook handles its own auth
			),
			'/stripe/create-payment-intent' => array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_payment_intent_endpoint' ),
				'permission_callback' => '__return_true', // Public endpoint with nonce verification
				'args'                => array(
					'booking_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'nonce' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
		);
	}

	/**
	 * Get database migrations.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_migrations(): array {
		return array(
			'1.0.0' => array(
				CreateStripeTables::class,
			),
		);
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'stripe_mode'                  => 'test',
			'stripe_live_publishable_key'  => '',
			'stripe_live_secret_key'       => '',
			'stripe_test_publishable_key'  => '',
			'stripe_test_secret_key'       => '',
			'stripe_webhook_secret'        => '',
			'enable_apple_pay'             => false,
			'enable_google_pay'            => false,
			'enable_link'                  => false,
			'statement_descriptor'         => get_bloginfo( 'name' ),
			'capture_method'               => 'automatic',
			'enable_3d_secure'             => true,
			'radar_risk_threshold'         => 75,
			'auto_refund_on_cancel'        => false,
			'save_payment_methods'         => true,
			'currency'                     => 'USD',
			'supported_currencies'         => array( 'USD', 'EUR', 'GBP' ),
			'debug_log'                    => false,
		);
	}

	/**
	 * Get settings fields.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_settings_fields(): array {
		return array(
			'stripe_mode'                  => array( 'type' => 'select', 'options' => array( 'test' => 'Test', 'live' => 'Live' ) ),
			'stripe_live_publishable_key'  => array( 'type' => 'text', 'sensitive' => true ),
			'stripe_live_secret_key'       => array( 'type' => 'encrypted', 'sensitive' => true ),
			'stripe_test_publishable_key'  => array( 'type' => 'text', 'sensitive' => true ),
			'stripe_test_secret_key'       => array( 'type' => 'encrypted', 'sensitive' => true ),
			'stripe_webhook_secret'        => array( 'type' => 'encrypted', 'sensitive' => true ),
			'enable_apple_pay'             => array( 'type' => 'checkbox' ),
			'enable_google_pay'            => array( 'type' => 'checkbox' ),
			'enable_link'                  => array( 'type' => 'checkbox' ),
			'statement_descriptor'         => array( 'type' => 'text' ),
			'capture_method'               => array( 'type' => 'select', 'options' => array( 'automatic' => 'Automatic', 'manual' => 'Manual' ) ),
			'enable_3d_secure'             => array( 'type' => 'checkbox' ),
			'radar_risk_threshold'         => array( 'type' => 'integer' ),
			'auto_refund_on_cancel'        => array( 'type' => 'checkbox' ),
			'save_payment_methods'         => array( 'type' => 'checkbox' ),
			'currency'                     => array( 'type' => 'text' ),
			'supported_currencies'         => array( 'type' => 'multiselect' ),
			'debug_log'                    => array( 'type' => 'checkbox' ),
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		// Only on settings page
		if ( 'bkx_booking_page_bkx_settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-stripe-admin',
			BKX_STRIPE_URL . 'assets/css/admin.css',
			array(),
			BKX_STRIPE_VERSION
		);

		wp_enqueue_script(
			'bkx-stripe-admin',
			BKX_STRIPE_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_STRIPE_VERSION,
			true
		);
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_scripts(): void {
		// Only enqueue on booking pages
		if ( ! is_page() && ! is_singular( 'bkx_booking' ) ) {
			return;
		}

		// Enqueue Stripe.js
		wp_enqueue_script(
			'stripe-js',
			'https://js.stripe.com/v3/',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			true
		);

		wp_enqueue_style(
			'bkx-stripe-checkout',
			BKX_STRIPE_URL . 'assets/css/stripe-checkout.css',
			array(),
			BKX_STRIPE_VERSION
		);

		wp_enqueue_script(
			'bkx-stripe-checkout',
			BKX_STRIPE_URL . 'assets/js/stripe-checkout.js',
			array( 'jquery', 'stripe-js' ),
			BKX_STRIPE_VERSION,
			true
		);

		// Get publishable key
		$mode = $this->get_setting( 'stripe_mode', 'test' );
		$publishable_key = $this->get_setting( "stripe_{$mode}_publishable_key", '' );

		wp_localize_script(
			'bkx-stripe-checkout',
			'bkxStripe',
			array(
				'publishableKey' => $publishable_key,
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'restUrl'        => rest_url( 'bookingx/v1/stripe/' ),
				'nonce'          => wp_create_nonce( 'bkx_stripe_nonce' ),
				'i18n'           => array(
					'processing' => __( 'Processing payment...', 'bkx-stripe-payments' ),
					'error'      => __( 'Payment failed. Please try again.', 'bkx-stripe-payments' ),
				),
			)
		);
	}

	/**
	 * Register meta boxes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_meta_boxes(): void {
		add_meta_box(
			'bkx_stripe_payment_details',
			__( 'Stripe Payment Details', 'bkx-stripe-payments' ),
			array( $this, 'render_payment_details_meta_box' ),
			'bkx_booking',
			'side',
			'high'
		);
	}

	/**
	 * Render payment details meta box.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_payment_details_meta_box( \WP_Post $post ): void {
		global $wpdb;

		$table = $this->get_table_name( 'stripe_transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transaction = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE booking_id = %d ORDER BY created_at DESC LIMIT 1",
				$table,
				$post->ID
			)
		);

		if ( ! $transaction ) {
			echo '<p>' . esc_html__( 'No Stripe transaction found.', 'bkx-stripe-payments' ) . '</p>';
			return;
		}

		// Properly format and escape output values - SECURITY
		$formatted_amount = number_format( (float) $transaction->amount, 2 );
		$currency         = strtoupper( sanitize_text_field( $transaction->currency ) );

		echo '<table class="widefat">';
		echo '<tr><th>' . esc_html__( 'Transaction ID:', 'bkx-stripe-payments' ) . '</th><td>' . esc_html( $transaction->stripe_transaction_id ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Status:', 'bkx-stripe-payments' ) . '</th><td><strong>' . esc_html( ucfirst( $transaction->status ) ) . '</strong></td></tr>';
		echo '<tr><th>' . esc_html__( 'Amount:', 'bkx-stripe-payments' ) . '</th><td>' . esc_html( $formatted_amount . ' ' . $currency ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Payment Method:', 'bkx-stripe-payments' ) . '</th><td>' . esc_html( $transaction->payment_method_type ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Created:', 'bkx-stripe-payments' ) . '</th><td>' . esc_html( $transaction->created_at ) . '</td></tr>';
		echo '</table>';

		if ( 'succeeded' === $transaction->status ) {
			// Build URL with proper escaping - use esc_url for href attributes
			$dashboard_url = 'https://dashboard.stripe.com/payments/' . rawurlencode( $transaction->stripe_payment_intent_id );
			echo '<p><a href="' . esc_url( $dashboard_url ) . '" target="_blank" class="button">' . esc_html__( 'View in Stripe Dashboard', 'bkx-stripe-payments' ) . '</a></p>';
		}
	}

	/**
	 * Add action links to plugin list.
	 *
	 * @since 1.0.0
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'edit.php?post_type=bkx_booking&page=bkx_settings&tab=stripe_payments' ),
			__( 'Settings', 'bkx-stripe-payments' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Handle booking status change.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @return void
	 */
	public function handle_booking_status_change( int $booking_id, string $old_status, string $new_status ): void {
		// Auto-capture if manual capture is enabled
		if ( 'bkx-ack' === $new_status && 'manual' === $this->get_setting( 'capture_method' ) ) {
			$this->get_payment_service()->capture_payment( $booking_id );
		}
	}

	/**
	 * Handle booking cancellation.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function handle_booking_cancelled( int $booking_id ): void {
		if ( $this->get_setting( 'auto_refund_on_cancel', false ) ) {
			$this->get_refund_service()->process_automatic_refund( $booking_id );
		}
	}

	/**
	 * Save payment metadata.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function save_payment_metadata( int $booking_id, array $booking_data ): void {
		if ( isset( $booking_data['payment_gateway'] ) && 'stripe' === $booking_data['payment_gateway'] ) {
			update_post_meta( $booking_id, '_bkx_payment_gateway', 'stripe' );
		}
	}

	/**
	 * Create payment intent REST endpoint.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_payment_intent_endpoint( \WP_REST_Request $request ) {
		// Verify nonce - SECURITY CHECK
		if ( ! wp_verify_nonce( $request->get_param( 'nonce' ), 'bkx_stripe_nonce' ) ) {
			return $this->error_response(
				'invalid_nonce',
				__( 'Security check failed.', 'bkx-stripe-payments' ),
				403
			);
		}

		$booking_id = $request->get_param( 'booking_id' );

		$result = $this->get_payment_service()->create_payment_intent( $booking_id );

		if ( isset( $result['error'] ) ) {
			return $this->error_response(
				'payment_intent_failed',
				$result['error'],
				400
			);
		}

		return $this->success_response( $result );
	}

	/**
	 * Get payment service instance.
	 *
	 * @since 1.0.0
	 * @return PaymentService
	 */
	public function get_payment_service(): PaymentService {
		if ( ! $this->payment_service ) {
			$this->payment_service = new PaymentService( $this );
		}

		return $this->payment_service;
	}

	/**
	 * Get refund service instance.
	 *
	 * @since 1.0.0
	 * @return RefundService
	 */
	public function get_refund_service(): RefundService {
		if ( ! $this->refund_service ) {
			$this->refund_service = new RefundService( $this );
		}

		return $this->refund_service;
	}

	/**
	 * Get customer service instance.
	 *
	 * @since 1.0.0
	 * @return CustomerService
	 */
	public function get_customer_service(): CustomerService {
		if ( ! $this->customer_service ) {
			$this->customer_service = new CustomerService( $this );
		}

		return $this->customer_service;
	}

	/**
	 * Get webhook service instance.
	 *
	 * @since 1.0.0
	 * @return WebhookService
	 */
	public function get_webhook_service(): WebhookService {
		if ( ! $this->webhook_service ) {
			$this->webhook_service = new WebhookService( $this );
		}

		return $this->webhook_service;
	}

	/**
	 * Get Stripe gateway instance.
	 *
	 * @since 1.0.0
	 * @return StripeGateway|null
	 */
	public function get_gateway(): ?StripeGateway {
		return $this->gateway;
	}
}

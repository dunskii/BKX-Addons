<?php
/**
 * Main Square Payments Add-on Class
 *
 * @package BookingX\SquarePayments
 */

namespace BookingX\SquarePayments;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasWebhooks;
use BookingX\SquarePayments\Gateway\SquareGateway;
use BookingX\SquarePayments\Admin\SettingsPage;
use BookingX\SquarePayments\Api\WebhookController;
use BookingX\SquarePayments\Migrations\CreateSquareTables;

/**
 * Main Square Payments class.
 *
 * @since 1.0.0
 */
class SquarePayments extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasWebhooks;

	/**
	 * Gateway instance.
	 *
	 * @var SquareGateway
	 */
	protected $gateway;

	/**
	 * Settings page instance.
	 *
	 * @var SettingsPage
	 */
	protected $settings_page;

	/**
	 * Webhook controller instance.
	 *
	 * @var WebhookController
	 */
	protected $webhook_controller;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Path to main plugin file.
	 */
	public function __construct( string $plugin_file ) {
		// Set add-on properties.
		$this->addon_id      = 'bkx_square_payments';
		$this->addon_name    = __( 'Square Payments', 'bkx-square-payments' );
		$this->version       = BKX_SQUARE_VERSION;
		$this->text_domain   = 'bkx-square-payments';

		// Call parent constructor.
		parent::__construct( $plugin_file );
	}

	/**
	 * Register with BookingX Framework.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function register_with_framework(): void {
		// Register payment gateway with BookingX.
		add_filter( 'bkx_payment_gateways', array( $this, 'register_gateway' ) );

		// Signal that this add-on is active.
		add_filter( 'bookingx_addon_bkx_square_payments_active', '__return_true' );
	}

	/**
	 * Register the Square payment gateway.
	 *
	 * @since 1.0.0
	 * @param array $gateways Existing gateways.
	 * @return array
	 */
	public function register_gateway( $gateways ) {
		$gateways['square'] = SquareGateway::class;
		return $gateways;
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_hooks(): void {
		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Initialize gateway.
		$this->gateway = new SquareGateway();

		// Initialize webhook controller.
		$this->webhook_controller = new WebhookController( $this );

		// Register webhooks.
		$this->register_webhook_endpoint();
	}

	/**
	 * Initialize admin functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_admin(): void {
		// Initialize settings page.
		$this->settings_page = new SettingsPage( $this );

		// Add settings link to plugins page.
		add_filter(
			'plugin_action_links_' . plugin_basename( $this->plugin_file ),
			array( $this, 'add_settings_link' )
		);

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Initialize frontend functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_frontend(): void {
		// Enqueue frontend assets when gateway is active.
		if ( $this->gateway && $this->gateway->is_enabled() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		}
	}

	/**
	 * Initialize REST API endpoints.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_rest_api(): void {
		// Webhook controller registers its own routes.
		if ( $this->webhook_controller ) {
			$this->webhook_controller->register_routes();
		}
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
				CreateSquareTables::class,
			),
		);
	}

	/**
	 * Load plugin text domain.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'bkx-square-payments',
			false,
			dirname( plugin_basename( $this->plugin_file ) ) . '/languages'
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ): void {
		// Only load on BookingX settings pages.
		if ( false === strpos( $hook, 'bkx_settings' ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-square-admin',
			$this->plugin_url . 'assets/css/square-admin.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'bkx-square-admin',
			$this->plugin_url . 'assets/js/square-admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'bkx-square-admin',
			'bkxSquareAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_square_admin' ),
				'i18n'    => array(
					'testConnection' => __( 'Testing connection...', 'bkx-square-payments' ),
					'connected'      => __( 'Connected successfully!', 'bkx-square-payments' ),
					'error'          => __( 'Connection failed. Please check your credentials.', 'bkx-square-payments' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		// Only load on booking pages.
		if ( ! is_singular( 'bkx_booking' ) && ! is_page() ) {
			return;
		}

		// Get application ID based on mode.
		$mode = $this->get_setting( 'square_mode', 'sandbox' );
		$app_id = $this->get_setting( "square_{$mode}_application_id", '' );

		if ( empty( $app_id ) ) {
			return;
		}

		// Load Square Web Payments SDK.
		$sdk_url = 'sandbox' === $mode
			? 'https://sandbox.web.squarecdn.com/v1/square.js'
			: 'https://web.squarecdn.com/v1/square.js';

		wp_enqueue_script(
			'square-web-payments-sdk',
			$sdk_url,
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			true
		);

		wp_enqueue_style(
			'bkx-square-checkout',
			$this->plugin_url . 'assets/css/square-checkout.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'bkx-square-checkout',
			$this->plugin_url . 'assets/js/square-checkout.js',
			array( 'jquery', 'square-web-payments-sdk' ),
			$this->version,
			true
		);

		wp_localize_script(
			'bkx-square-checkout',
			'bkxSquare',
			array(
				'applicationId'     => $app_id,
				'locationId'        => $this->get_setting( "square_{$mode}_location_id", '' ),
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'bkx_square_checkout' ),
				'enableApplePay'    => $this->get_setting( 'enable_apple_pay', false ),
				'enableGooglePay'   => $this->get_setting( 'enable_google_pay', false ),
				'enableCashAppPay'  => $this->get_setting( 'enable_cash_app_pay', false ),
				'currency'          => $this->get_setting( 'currency', 'USD' ),
				'i18n'              => array(
					'processing'    => __( 'Processing payment...', 'bkx-square-payments' ),
					'success'       => __( 'Payment successful!', 'bkx-square-payments' ),
					'error'         => __( 'Payment failed. Please try again.', 'bkx-square-payments' ),
					'cardNumber'    => __( 'Card Number', 'bkx-square-payments' ),
					'expiryDate'    => __( 'Expiry Date', 'bkx-square-payments' ),
					'cvv'           => __( 'CVV', 'bkx-square-payments' ),
					'postalCode'    => __( 'Postal Code', 'bkx-square-payments' ),
				),
			)
		);
	}

	/**
	 * Add settings link to plugins page.
	 *
	 * @since 1.0.0
	 * @param array $links Existing plugin action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'edit.php?post_type=bkx_booking&page=bkx_settings&tab=square_payments' ),
			__( 'Settings', 'bkx-square-payments' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'square_mode'                       => 'sandbox',
			'square_sandbox_application_id'     => '',
			'square_sandbox_access_token'       => '',
			'square_sandbox_location_id'        => '',
			'square_production_application_id'  => '',
			'square_production_access_token'    => '',
			'square_production_location_id'     => '',
			'square_webhook_signature_key'      => '',
			'enable_apple_pay'                  => false,
			'enable_google_pay'                 => false,
			'enable_cash_app_pay'               => false,
			'enable_customer_sync'              => false,
			'auto_refund_on_cancel'             => false,
			'currency'                          => 'USD',
			'debug_log'                         => false,
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
			'square_mode' => array(
				'type'    => 'select',
				'options' => array(
					'sandbox'    => __( 'Sandbox (Testing)', 'bkx-square-payments' ),
					'production' => __( 'Production (Live)', 'bkx-square-payments' ),
				),
				'default' => 'sandbox',
			),
			'square_sandbox_application_id' => array(
				'type'      => 'text',
				'sensitive' => false,
			),
			'square_sandbox_access_token' => array(
				'type'      => 'password',
				'sensitive' => true,
			),
			'square_sandbox_location_id' => array(
				'type'      => 'text',
				'sensitive' => false,
			),
			'square_production_application_id' => array(
				'type'      => 'text',
				'sensitive' => false,
			),
			'square_production_access_token' => array(
				'type'      => 'password',
				'sensitive' => true,
			),
			'square_production_location_id' => array(
				'type'      => 'text',
				'sensitive' => false,
			),
			'square_webhook_signature_key' => array(
				'type'      => 'password',
				'sensitive' => true,
			),
			'enable_apple_pay' => array(
				'type'    => 'checkbox',
				'default' => false,
			),
			'enable_google_pay' => array(
				'type'    => 'checkbox',
				'default' => false,
			),
			'enable_cash_app_pay' => array(
				'type'    => 'checkbox',
				'default' => false,
			),
			'enable_customer_sync' => array(
				'type'    => 'checkbox',
				'default' => false,
			),
			'auto_refund_on_cancel' => array(
				'type'    => 'checkbox',
				'default' => false,
			),
			'currency' => array(
				'type'    => 'text',
				'default' => 'USD',
			),
			'debug_log' => array(
				'type'    => 'checkbox',
				'default' => false,
			),
		);
	}

	/**
	 * Get the gateway instance.
	 *
	 * @since 1.0.0
	 * @return SquareGateway|null
	 */
	public function get_gateway() {
		return $this->gateway;
	}
}

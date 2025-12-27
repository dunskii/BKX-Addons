<?php
/**
 * Main PayPal Pro Add-on Class
 *
 * @package BookingX\PayPalPro
 * @since   1.0.0
 */

namespace BookingX\PayPalPro;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasRestApi;
use BookingX\AddonSDK\Traits\HasWebhooks;
use BookingX\PayPalPro\Gateway\PayPalGateway;
use BookingX\PayPalPro\Admin\SettingsPage;
use BookingX\PayPalPro\Api\WebhookController;

/**
 * Main PayPal Pro addon class.
 *
 * @since 1.0.0
 */
class PayPalPro extends AbstractAddon {
	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasRestApi;
	use HasWebhooks;

	/**
	 * Payment gateway instance.
	 *
	 * @var PayPalGateway|null
	 */
	protected ?PayPalGateway $gateway = null;

	/**
	 * Settings page instance.
	 *
	 * @var SettingsPage|null
	 */
	protected ?SettingsPage $settings_page = null;

	/**
	 * Webhook controller instance.
	 *
	 * @var WebhookController|null
	 */
	protected ?WebhookController $webhook_controller = null;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Path to the main plugin file.
	 */
	public function __construct( string $plugin_file ) {
		// Set addon properties.
		$this->addon_id     = 'bkx_paypal_pro';
		$this->addon_name   = __( 'PayPal Pro', 'bkx-paypal-pro' );
		$this->version      = BKX_PAYPAL_PRO_VERSION;
		$this->text_domain  = 'bkx-paypal-pro';

		// Set minimum versions.
		$this->min_bkx_version = '2.0.0';
		$this->min_php_version = '7.4';
		$this->min_wp_version  = '5.8';

		parent::__construct( $plugin_file );
	}

	/**
	 * Register with BookingX Framework registries.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function register_with_framework(): void {
		// Register payment gateway.
		add_filter( 'bkx_payment_gateways', array( $this, 'register_payment_gateway' ) );

		// Register settings tab.
		add_filter( 'bkx_settings_tabs', array( $this, 'register_settings_tab' ) );

		// Register addon as active.
		add_filter( 'bookingx_addon_bkx_paypal_pro_active', '__return_true' );
	}

	/**
	 * Register the payment gateway.
	 *
	 * @since 1.0.0
	 * @param array $gateways Existing gateways.
	 * @return array Modified gateways.
	 */
	public function register_payment_gateway( array $gateways ): array {
		$gateways['paypal_pro'] = PayPalGateway::class;
		return $gateways;
	}

	/**
	 * Register the settings tab.
	 *
	 * @since 1.0.0
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function register_settings_tab( array $tabs ): array {
		$tabs['paypal_pro'] = __( 'PayPal Pro', 'bkx-paypal-pro' );
		return $tabs;
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

		// Schedule token refresh.
		add_action( 'bkx_paypal_pro_token_refresh', array( $this, 'refresh_access_token' ) );

		// Enqueue scripts on booking pages.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
	}

	/**
	 * Initialize admin functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_admin(): void {
		$this->settings_page = new SettingsPage( $this );

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Initialize frontend functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_frontend(): void {
		$this->gateway = new PayPalGateway();
	}

	/**
	 * Initialize REST API endpoints.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_rest_api(): void {
		$this->webhook_controller = new WebhookController( $this );
		$this->register_webhook_endpoint();
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
				\BookingX\PayPalPro\Migrations\CreatePayPalTables::class,
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
			'bkx-paypal-pro',
			false,
			dirname( plugin_basename( $this->plugin_file ) ) . '/languages'
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
		// Only enqueue on BookingX settings pages.
		if ( 'bkx_booking_page_bkx_settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-paypal-pro-admin',
			$this->plugin_url . 'assets/css/admin.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'bkx-paypal-pro-admin',
			$this->plugin_url . 'assets/js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			'bkx-paypal-pro-admin',
			'bkxPayPalProAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_paypal_pro_admin' ),
				'i18n'    => array(
					'testConnection' => __( 'Testing connection...', 'bkx-paypal-pro' ),
					'testSuccess'    => __( 'Connection successful!', 'bkx-paypal-pro' ),
					'testFailed'     => __( 'Connection failed. Please check your credentials.', 'bkx-paypal-pro' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_scripts(): void {
		// Only enqueue on booking pages.
		if ( ! is_singular( 'bkx_booking' ) && ! has_shortcode( get_the_content(), 'bookingx' ) ) {
			return;
		}

		// Enqueue PayPal SDK.
		$settings  = $this->get_all_settings();
		$mode      = $settings['paypal_mode'] ?? 'sandbox';
		$client_id = $mode === 'live' ? ( $settings['paypal_live_client_id'] ?? '' ) : ( $settings['paypal_sandbox_client_id'] ?? '' );
		$currency  = $settings['currency'] ?? 'USD';
		$intent    = $settings['intent'] ?? 'capture';

		if ( empty( $client_id ) ) {
			return;
		}

		$paypal_sdk_params = array(
			'client-id'  => $client_id,
			'currency'   => $currency,
			'intent'     => $intent,
			'components' => 'buttons,hosted-fields',
		);

		// Add Pay Later support.
		if ( ! empty( $settings['enable_pay_later'] ) ) {
			$paypal_sdk_params['enable-funding'] = 'paylater';
		}

		$paypal_sdk_url = add_query_arg( $paypal_sdk_params, 'https://www.paypal.com/sdk/js' );

		wp_enqueue_script(
			'paypal-sdk',
			$paypal_sdk_url,
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			true
		);

		wp_enqueue_style(
			'bkx-paypal-pro-checkout',
			$this->plugin_url . 'assets/css/paypal-checkout.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'bkx-paypal-pro-checkout',
			$this->plugin_url . 'assets/js/paypal-checkout.js',
			array( 'jquery', 'paypal-sdk' ),
			$this->version,
			true
		);

		wp_localize_script(
			'bkx-paypal-pro-checkout',
			'bkxPayPalPro',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'restUrl'       => rest_url( 'bookingx/v1' ),
				'nonce'         => wp_create_nonce( 'bkx_paypal_pro_checkout' ),
				'mode'          => $mode,
				'buttonColor'   => $settings['button_color'] ?? 'gold',
				'buttonShape'   => $settings['button_shape'] ?? 'rect',
				'enableCards'   => ! empty( $settings['enable_card_fields'] ),
				'enablePayLater' => ! empty( $settings['enable_pay_later'] ),
				'i18n'          => array(
					'processing'   => __( 'Processing payment...', 'bkx-paypal-pro' ),
					'success'      => __( 'Payment successful!', 'bkx-paypal-pro' ),
					'error'        => __( 'Payment failed. Please try again.', 'bkx-paypal-pro' ),
					'cardRequired' => __( 'Please enter valid card details.', 'bkx-paypal-pro' ),
				),
			)
		);
	}

	/**
	 * Refresh PayPal access token.
	 *
	 * This is scheduled to run daily to ensure we always have a valid token.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function refresh_access_token(): void {
		if ( ! $this->gateway ) {
			$this->gateway = new PayPalGateway();
		}

		// Token refresh is handled automatically by PayPalClient when needed.
		delete_transient( 'bkx_paypal_pro_access_token' );
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
	 * Get settings fields.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_settings_fields(): array {
		return array(
			'enabled'                      => array( 'type' => 'checkbox' ),
			'paypal_mode'                  => array( 'type' => 'select' ),
			'paypal_sandbox_client_id'     => array( 'type' => 'text', 'sensitive' => true ),
			'paypal_sandbox_client_secret' => array( 'type' => 'password', 'sensitive' => true ),
			'paypal_live_client_id'        => array( 'type' => 'text', 'sensitive' => true ),
			'paypal_live_client_secret'    => array( 'type' => 'password', 'sensitive' => true ),
			'paypal_webhook_id'            => array( 'type' => 'text', 'sensitive' => true ),
			'enable_card_fields'           => array( 'type' => 'checkbox' ),
			'enable_pay_later'             => array( 'type' => 'checkbox' ),
			'button_color'                 => array( 'type' => 'select' ),
			'button_shape'                 => array( 'type' => 'select' ),
			'intent'                       => array( 'type' => 'select' ),
			'currency'                     => array( 'type' => 'select' ),
			'debug_log'                    => array( 'type' => 'checkbox' ),
		);
	}

	/**
	 * Handle webhook request from REST API.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_webhook_request( \WP_REST_Request $request ) {
		if ( $this->webhook_controller ) {
			return $this->webhook_controller->handle_webhook( $request );
		}

		return new \WP_Error(
			'webhook_controller_missing',
			__( 'Webhook controller not initialized.', 'bkx-paypal-pro' ),
			array( 'status' => 500 )
		);
	}
}

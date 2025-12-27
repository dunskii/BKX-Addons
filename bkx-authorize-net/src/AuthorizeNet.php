<?php
/**
 * Authorize.net Addon Main Class
 *
 * Main entry point for the Authorize.net payment gateway addon.
 *
 * @package BookingX\AuthorizeNet
 * @since   1.0.0
 */

namespace BookingX\AuthorizeNet;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasRestApi;
use BookingX\AddonSDK\Traits\HasWebhooks;
use BookingX\AuthorizeNet\Gateway\AuthorizeNetGateway;
use BookingX\AuthorizeNet\Admin\SettingsPage;
use BookingX\AuthorizeNet\Controllers\WebhookController;
use BookingX\AuthorizeNet\Controllers\AjaxController;
use BookingX\AuthorizeNet\Migrations\CreateAuthorizeNetTables;

/**
 * Main Authorize.net addon class.
 *
 * @since 1.0.0
 */
class AuthorizeNet extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasRestApi;
	use HasWebhooks;

	/**
	 * Singleton instance.
	 *
	 * @var AuthorizeNet|null
	 */
	private static ?AuthorizeNet $instance = null;

	/**
	 * The payment gateway instance.
	 *
	 * @var AuthorizeNetGateway|null
	 */
	protected ?AuthorizeNetGateway $gateway = null;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 * @return AuthorizeNet
	 */
	public static function get_instance(): AuthorizeNet {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get addon ID.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_id(): string {
		return 'authorize-net';
	}

	/**
	 * Get addon name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Authorize.net', 'bkx-authorize-net' );
	}

	/**
	 * Get addon version.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_version(): string {
		return BKX_AUTHORIZE_NET_VERSION;
	}

	/**
	 * Get addon file path.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_file(): string {
		return BKX_AUTHORIZE_NET_FILE;
	}

	/**
	 * Get addon path.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_path(): string {
		return BKX_AUTHORIZE_NET_PATH;
	}

	/**
	 * Get addon URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_url(): string {
		return BKX_AUTHORIZE_NET_URL;
	}

	/**
	 * Get minimum BookingX version required.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_min_core_version(): string {
		return '2.0.0';
	}

	/**
	 * Initialize the addon.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init(): void {
		$this->load_textdomain();
		$this->init_settings();
		$this->init_database();
		$this->init_gateway();
		$this->init_admin();
		$this->init_rest_api();
		$this->init_ajax();
		$this->init_hooks();
	}

	/**
	 * Initialize AJAX handlers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_ajax(): void {
		new AjaxController( $this );
	}

	/**
	 * Load text domain for translations.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function load_textdomain(): void {
		load_plugin_textdomain(
			'bkx-authorize-net',
			false,
			dirname( BKX_AUTHORIZE_NET_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_settings(): void {
		$this->settings_key = 'bkx_authorize_net_settings';

		$this->default_settings = array(
			'enabled'                => false,
			'authnet_mode'           => 'sandbox',
			'api_login_id'           => '',
			'transaction_key'        => '',
			'public_client_key'      => '',
			'signature_key'          => '',
			'integration_method'     => 'accept_js',
			'enable_cim'             => true,
			'enable_arb'             => false,
			'require_cvv'            => true,
			'transaction_type'       => 'auth_capture',
			'auto_refund_on_cancel'  => true,
			'accepted_card_types'    => array( 'visa', 'mastercard', 'amex', 'discover' ),
			'debug_log'              => false,
			'webhook_url'            => '',
		);

		$this->load_settings();
	}

	/**
	 * Initialize database tables.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_database(): void {
		$this->migrations = array(
			CreateAuthorizeNetTables::class,
		);

		// Run migrations on activation.
		if ( get_option( 'bkx_authorize_net_activated' ) ) {
			$this->run_migrations();
			delete_option( 'bkx_authorize_net_activated' );
		}
	}

	/**
	 * Initialize payment gateway.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_gateway(): void {
		$this->gateway = new AuthorizeNetGateway( $this );

		// Register with BookingX.
		add_filter( 'bkx_payment_gateways', array( $this, 'register_gateway' ) );
	}

	/**
	 * Register gateway with BookingX.
	 *
	 * @since 1.0.0
	 * @param array $gateways Existing gateways.
	 * @return array
	 */
	public function register_gateway( array $gateways ): array {
		$gateways['authorize_net'] = $this->gateway;
		return $gateways;
	}

	/**
	 * Get the gateway instance.
	 *
	 * @since 1.0.0
	 * @return AuthorizeNetGateway|null
	 */
	public function get_gateway(): ?AuthorizeNetGateway {
		return $this->gateway;
	}

	/**
	 * Initialize admin features.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_admin(): void {
		if ( is_admin() ) {
			new SettingsPage( $this );
		}
	}

	/**
	 * Initialize REST API.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_rest_api(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_rest_routes(): void {
		$webhook_controller = new WebhookController( $this );
		$webhook_controller->register_routes();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_hooks(): void {
		// Frontend scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

		// Admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Booking cancellation hook for auto-refund.
		add_action( 'bkx_booking_cancelled', array( $this, 'handle_booking_cancelled' ), 10, 2 );

		// Add payment meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_payment_meta_box' ) );
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_scripts(): void {
		if ( ! $this->get_setting( 'enabled', false ) ) {
			return;
		}

		$is_sandbox = 'sandbox' === $this->get_setting( 'authnet_mode', 'sandbox' );

		// Accept.js library - different URLs for sandbox/production.
		$accept_js_url = $is_sandbox
			? 'https://jstest.authorize.net/v1/Accept.js'
			: 'https://js.authorize.net/v1/Accept.js';

		wp_enqueue_script(
			'authorize-net-accept-js',
			$accept_js_url,
			array(),
			null,
			true
		);

		// Our payment form handler.
		wp_enqueue_script(
			'bkx-authorize-net-payment',
			BKX_AUTHORIZE_NET_URL . 'assets/js/payment-form.js',
			array( 'jquery', 'authorize-net-accept-js' ),
			BKX_AUTHORIZE_NET_VERSION,
			true
		);

		wp_localize_script(
			'bkx-authorize-net-payment',
			'bkxAuthorizeNet',
			array(
				'apiLoginId'     => $this->get_setting( 'api_login_id', '' ),
				'clientKey'      => $this->get_setting( 'public_client_key', '' ),
				'isSandbox'      => $is_sandbox,
				'requireCvv'     => $this->get_setting( 'require_cvv', true ),
				'acceptedCards'  => $this->get_setting( 'accepted_card_types', array() ),
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'bkx_authorize_net_payment' ),
				'i18n'           => array(
					'cardNumber'     => __( 'Card Number', 'bkx-authorize-net' ),
					'expirationDate' => __( 'Expiration Date', 'bkx-authorize-net' ),
					'cvv'            => __( 'CVV', 'bkx-authorize-net' ),
					'processingPayment' => __( 'Processing payment...', 'bkx-authorize-net' ),
					'paymentFailed'  => __( 'Payment failed. Please try again.', 'bkx-authorize-net' ),
					'invalidCard'    => __( 'Please enter a valid card number.', 'bkx-authorize-net' ),
				),
			)
		);

		wp_enqueue_style(
			'bkx-authorize-net-payment',
			BKX_AUTHORIZE_NET_URL . 'assets/css/payment-form.css',
			array(),
			BKX_AUTHORIZE_NET_VERSION
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
		if ( 'toplevel_page_bookingx-settings' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'bkx-authorize-net-admin',
			BKX_AUTHORIZE_NET_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_AUTHORIZE_NET_VERSION,
			true
		);
	}

	/**
	 * Handle booking cancellation for auto-refund.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function handle_booking_cancelled( int $booking_id, array $booking_data ): void {
		if ( ! $this->get_setting( 'auto_refund_on_cancel', true ) ) {
			return;
		}

		// Check if this booking was paid via Authorize.net.
		$payment_method = get_post_meta( $booking_id, '_payment_method', true );
		if ( 'authorize_net' !== $payment_method ) {
			return;
		}

		// Process refund.
		$transaction_id = get_post_meta( $booking_id, '_authnet_transaction_id', true );
		if ( ! empty( $transaction_id ) ) {
			$this->gateway->process_refund( $booking_id );
		}
	}

	/**
	 * Add payment meta box to booking edit screen.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_payment_meta_box(): void {
		add_meta_box(
			'bkx_authorize_net_payment',
			__( 'Authorize.net Payment', 'bkx-authorize-net' ),
			array( $this, 'render_payment_meta_box' ),
			'bkx_booking',
			'side',
			'default'
		);
	}

	/**
	 * Render payment meta box.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_payment_meta_box( \WP_Post $post ): void {
		$payment_method = get_post_meta( $post->ID, '_payment_method', true );

		if ( 'authorize_net' !== $payment_method ) {
			echo '<p>' . esc_html__( 'This booking was not paid via Authorize.net.', 'bkx-authorize-net' ) . '</p>';
			return;
		}

		$transaction_id = get_post_meta( $post->ID, '_authnet_transaction_id', true );
		$amount = get_post_meta( $post->ID, '_payment_amount', true );
		$status = get_post_meta( $post->ID, '_authnet_transaction_status', true );
		$card_type = get_post_meta( $post->ID, '_authnet_card_type', true );
		$last_four = get_post_meta( $post->ID, '_authnet_last_four', true );
		$refund_status = get_post_meta( $post->ID, '_authnet_refund_status', true );

		$is_sandbox = 'sandbox' === $this->get_setting( 'authnet_mode', 'sandbox' );
		$dashboard_url = $is_sandbox
			? 'https://sandbox.authorize.net/UI/themes/sandbox/transaction/transactiondetail.aspx?transID='
			: 'https://account.authorize.net/UI/themes/anet/transaction/transactiondetail.aspx?transID=';
		?>
		<div class="bkx-authnet-payment-info">
			<p>
				<strong><?php esc_html_e( 'Transaction ID:', 'bkx-authorize-net' ); ?></strong><br>
				<?php if ( ! empty( $transaction_id ) ) : ?>
					<a href="<?php echo esc_url( $dashboard_url . rawurlencode( $transaction_id ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $transaction_id ); ?>
					</a>
				<?php else : ?>
					<?php esc_html_e( 'N/A', 'bkx-authorize-net' ); ?>
				<?php endif; ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Amount:', 'bkx-authorize-net' ); ?></strong><br>
				<?php echo esc_html( ! empty( $amount ) ? number_format( (float) $amount, 2 ) : 'N/A' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Status:', 'bkx-authorize-net' ); ?></strong><br>
				<span class="bkx-status-<?php echo esc_attr( strtolower( $status ?? 'unknown' ) ); ?>">
					<?php echo esc_html( $status ?? __( 'Unknown', 'bkx-authorize-net' ) ); ?>
				</span>
			</p>
			<?php if ( ! empty( $card_type ) && ! empty( $last_four ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Card:', 'bkx-authorize-net' ); ?></strong><br>
					<?php echo esc_html( ucfirst( $card_type ) . ' **** ' . $last_four ); ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $refund_status ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Refund Status:', 'bkx-authorize-net' ); ?></strong><br>
					<span class="bkx-status-refunded"><?php echo esc_html( $refund_status ); ?></span>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get settings fields for the settings page.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings_fields(): array {
		return array(
			array(
				'id'      => 'enabled',
				'title'   => __( 'Enable/Disable', 'bkx-authorize-net' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Authorize.net', 'bkx-authorize-net' ),
				'default' => false,
			),
			array(
				'id'      => 'authnet_mode',
				'title'   => __( 'Mode', 'bkx-authorize-net' ),
				'type'    => 'select',
				'options' => array(
					'sandbox' => __( 'Sandbox (Testing)', 'bkx-authorize-net' ),
					'live'    => __( 'Live (Production)', 'bkx-authorize-net' ),
				),
				'default' => 'sandbox',
			),
			array(
				'id'          => 'api_login_id',
				'title'       => __( 'API Login ID', 'bkx-authorize-net' ),
				'type'        => 'text',
				'description' => __( 'Enter your Authorize.net API Login ID.', 'bkx-authorize-net' ),
			),
			array(
				'id'          => 'transaction_key',
				'title'       => __( 'Transaction Key', 'bkx-authorize-net' ),
				'type'        => 'password',
				'description' => __( 'Enter your Authorize.net Transaction Key.', 'bkx-authorize-net' ),
			),
			array(
				'id'          => 'public_client_key',
				'title'       => __( 'Public Client Key', 'bkx-authorize-net' ),
				'type'        => 'text',
				'description' => __( 'Enter your public client key for Accept.js.', 'bkx-authorize-net' ),
			),
			array(
				'id'          => 'signature_key',
				'title'       => __( 'Signature Key', 'bkx-authorize-net' ),
				'type'        => 'password',
				'description' => __( 'Enter your Signature Key for webhook verification.', 'bkx-authorize-net' ),
			),
			array(
				'id'      => 'transaction_type',
				'title'   => __( 'Transaction Type', 'bkx-authorize-net' ),
				'type'    => 'select',
				'options' => array(
					'auth_capture' => __( 'Authorize and Capture', 'bkx-authorize-net' ),
					'auth_only'    => __( 'Authorize Only', 'bkx-authorize-net' ),
				),
				'default' => 'auth_capture',
			),
			array(
				'id'      => 'require_cvv',
				'title'   => __( 'Require CVV', 'bkx-authorize-net' ),
				'type'    => 'checkbox',
				'label'   => __( 'Require CVV for card payments', 'bkx-authorize-net' ),
				'default' => true,
			),
			array(
				'id'      => 'enable_cim',
				'title'   => __( 'Customer Profiles', 'bkx-authorize-net' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Customer Information Manager (CIM)', 'bkx-authorize-net' ),
				'default' => true,
			),
			array(
				'id'      => 'auto_refund_on_cancel',
				'title'   => __( 'Auto Refund', 'bkx-authorize-net' ),
				'type'    => 'checkbox',
				'label'   => __( 'Automatically refund when booking is cancelled', 'bkx-authorize-net' ),
				'default' => true,
			),
			array(
				'id'      => 'debug_log',
				'title'   => __( 'Debug Log', 'bkx-authorize-net' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable debug logging', 'bkx-authorize-net' ),
				'default' => false,
			),
		);
	}
}

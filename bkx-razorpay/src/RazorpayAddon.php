<?php
/**
 * Razorpay Addon Main Class
 *
 * Main entry point for the Razorpay payment gateway addon.
 *
 * @package BookingX\Razorpay
 * @since   1.0.0
 */

namespace BookingX\Razorpay;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasRestApi;
use BookingX\AddonSDK\Traits\HasWebhooks;
use BookingX\Razorpay\Gateway\RazorpayGateway;
use BookingX\Razorpay\Admin\SettingsPage;
use BookingX\Razorpay\Controllers\WebhookController;
use BookingX\Razorpay\Controllers\AjaxController;
use BookingX\Razorpay\Migrations\CreateRazorpayTables;

/**
 * Main Razorpay addon class.
 *
 * @since 1.0.0
 */
class RazorpayAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasRestApi;
	use HasWebhooks;

	/**
	 * Singleton instance.
	 *
	 * @var RazorpayAddon|null
	 */
	private static ?RazorpayAddon $instance = null;

	/**
	 * The payment gateway instance.
	 *
	 * @var RazorpayGateway|null
	 */
	protected ?RazorpayGateway $gateway = null;

	/**
	 * Get the singleton instance.
	 *
	 * @since 1.0.0
	 * @return RazorpayAddon
	 */
	public static function get_instance(): RazorpayAddon {
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
		return 'razorpay';
	}

	/**
	 * Get addon name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Razorpay', 'bkx-razorpay' );
	}

	/**
	 * Get addon version.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_version(): string {
		return BKX_RAZORPAY_VERSION;
	}

	/**
	 * Get addon file path.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_file(): string {
		return BKX_RAZORPAY_FILE;
	}

	/**
	 * Get addon path.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_path(): string {
		return BKX_RAZORPAY_PATH;
	}

	/**
	 * Get addon URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_url(): string {
		return BKX_RAZORPAY_URL;
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
	 * Load text domain for translations.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function load_textdomain(): void {
		load_plugin_textdomain(
			'bkx-razorpay',
			false,
			dirname( BKX_RAZORPAY_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_settings(): void {
		$this->settings_key = 'bkx_razorpay_settings';

		$this->default_settings = array(
			'enabled'               => false,
			'razorpay_mode'         => 'test',
			'key_id'                => '',
			'key_secret'            => '',
			'webhook_secret'        => '',
			'payment_action'        => 'capture',
			'order_prefix'          => 'BKX-',
			'enable_upi'            => true,
			'enable_cards'          => true,
			'enable_netbanking'     => true,
			'enable_wallet'         => true,
			'auto_refund_on_cancel' => true,
			'currency'              => 'INR',
			'debug_log'             => false,
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
			CreateRazorpayTables::class,
		);

		// Run migrations on activation.
		if ( get_option( 'bkx_razorpay_activated' ) ) {
			$this->run_migrations();
			delete_option( 'bkx_razorpay_activated' );
		}
	}

	/**
	 * Initialize payment gateway.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_gateway(): void {
		$this->gateway = new RazorpayGateway( $this );

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
		$gateways['razorpay'] = $this->gateway;
		return $gateways;
	}

	/**
	 * Get the gateway instance.
	 *
	 * @since 1.0.0
	 * @return RazorpayGateway|null
	 */
	public function get_gateway(): ?RazorpayGateway {
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
	 * Initialize AJAX handlers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_ajax(): void {
		new AjaxController( $this );
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

		// Razorpay Checkout.js.
		wp_enqueue_script(
			'razorpay-checkout',
			'https://checkout.razorpay.com/v1/checkout.js',
			array(),
			null,
			true
		);

		// Our payment form handler.
		wp_enqueue_script(
			'bkx-razorpay-payment',
			BKX_RAZORPAY_URL . 'assets/js/payment-form.js',
			array( 'jquery', 'razorpay-checkout' ),
			BKX_RAZORPAY_VERSION,
			true
		);

		wp_localize_script(
			'bkx-razorpay-payment',
			'bkxRazorpay',
			array(
				'keyId'       => $this->get_setting( 'key_id', '' ),
				'currency'    => $this->get_setting( 'currency', 'INR' ),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'bkx_razorpay_payment' ),
				'siteName'    => get_bloginfo( 'name' ),
				'siteUrl'     => home_url(),
				'logoUrl'     => $this->get_checkout_logo_url(),
				'themeColor'  => '#528FF0',
				'i18n'        => array(
					'paymentFailed'     => __( 'Payment failed. Please try again.', 'bkx-razorpay' ),
					'verifyingPayment'  => __( 'Verifying payment...', 'bkx-razorpay' ),
					'paymentCancelled'  => __( 'Payment was cancelled.', 'bkx-razorpay' ),
					'processingPayment' => __( 'Processing payment...', 'bkx-razorpay' ),
				),
			)
		);

		wp_enqueue_style(
			'bkx-razorpay-payment',
			BKX_RAZORPAY_URL . 'assets/css/payment-form.css',
			array(),
			BKX_RAZORPAY_VERSION
		);
	}

	/**
	 * Get checkout logo URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_checkout_logo_url(): string {
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$logo_url = wp_get_attachment_image_url( $custom_logo_id, 'thumbnail' );
			if ( $logo_url ) {
				return $logo_url;
			}
		}
		return '';
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
			'bkx-razorpay-admin',
			BKX_RAZORPAY_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_RAZORPAY_VERSION,
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

		// Check if this booking was paid via Razorpay.
		$payment_method = get_post_meta( $booking_id, '_payment_method', true );
		if ( 'razorpay' !== $payment_method ) {
			return;
		}

		// Process full refund (0.0 means full amount).
		$payment_id = get_post_meta( $booking_id, '_razorpay_payment_id', true );
		if ( ! empty( $payment_id ) ) {
			$this->gateway->process_refund( $booking_id, 0.0, __( 'Booking cancelled', 'bkx-razorpay' ), $payment_id );
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
			'bkx_razorpay_payment',
			__( 'Razorpay Payment', 'bkx-razorpay' ),
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

		if ( 'razorpay' !== $payment_method ) {
			echo '<p>' . esc_html__( 'This booking was not paid via Razorpay.', 'bkx-razorpay' ) . '</p>';
			return;
		}

		$payment_id = get_post_meta( $post->ID, '_razorpay_payment_id', true );
		$order_id = get_post_meta( $post->ID, '_razorpay_order_id', true );
		$amount = get_post_meta( $post->ID, '_payment_amount', true );
		$status = get_post_meta( $post->ID, '_razorpay_payment_status', true );
		$method = get_post_meta( $post->ID, '_razorpay_payment_method', true );
		$refund_status = get_post_meta( $post->ID, '_razorpay_refund_status', true );

		$is_live = 'live' === $this->get_setting( 'razorpay_mode', 'test' );
		$dashboard_url = $is_live
			? 'https://dashboard.razorpay.com/app/payments/'
			: 'https://dashboard.razorpay.com/app/payments/';
		?>
		<div class="bkx-razorpay-payment-info">
			<p>
				<strong><?php esc_html_e( 'Payment ID:', 'bkx-razorpay' ); ?></strong><br>
				<?php if ( ! empty( $payment_id ) ) : ?>
					<a href="<?php echo esc_url( $dashboard_url . rawurlencode( $payment_id ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $payment_id ); ?>
					</a>
				<?php else : ?>
					<?php esc_html_e( 'N/A', 'bkx-razorpay' ); ?>
				<?php endif; ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Order ID:', 'bkx-razorpay' ); ?></strong><br>
				<?php echo esc_html( $order_id ?: __( 'N/A', 'bkx-razorpay' ) ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Amount:', 'bkx-razorpay' ); ?></strong><br>
				<?php echo esc_html( ! empty( $amount ) ? number_format( (float) $amount, 2 ) : 'N/A' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Status:', 'bkx-razorpay' ); ?></strong><br>
				<span class="bkx-status-<?php echo esc_attr( strtolower( $status ?? 'unknown' ) ); ?>">
					<?php echo esc_html( ucfirst( $status ?? __( 'Unknown', 'bkx-razorpay' ) ) ); ?>
				</span>
			</p>
			<?php if ( ! empty( $method ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Payment Method:', 'bkx-razorpay' ); ?></strong><br>
					<?php echo esc_html( ucfirst( $method ) ); ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $refund_status ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Refund Status:', 'bkx-razorpay' ); ?></strong><br>
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
				'title'   => __( 'Enable/Disable', 'bkx-razorpay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Razorpay', 'bkx-razorpay' ),
				'default' => false,
			),
			array(
				'id'      => 'razorpay_mode',
				'title'   => __( 'Mode', 'bkx-razorpay' ),
				'type'    => 'select',
				'options' => array(
					'test' => __( 'Test Mode', 'bkx-razorpay' ),
					'live' => __( 'Live Mode', 'bkx-razorpay' ),
				),
				'default' => 'test',
			),
			array(
				'id'          => 'key_id',
				'title'       => __( 'Key ID', 'bkx-razorpay' ),
				'type'        => 'text',
				'description' => __( 'Enter your Razorpay Key ID.', 'bkx-razorpay' ),
			),
			array(
				'id'          => 'key_secret',
				'title'       => __( 'Key Secret', 'bkx-razorpay' ),
				'type'        => 'password',
				'description' => __( 'Enter your Razorpay Key Secret.', 'bkx-razorpay' ),
			),
			array(
				'id'          => 'webhook_secret',
				'title'       => __( 'Webhook Secret', 'bkx-razorpay' ),
				'type'        => 'password',
				'description' => __( 'Enter your webhook secret for signature verification.', 'bkx-razorpay' ),
			),
			array(
				'id'      => 'payment_action',
				'title'   => __( 'Payment Action', 'bkx-razorpay' ),
				'type'    => 'select',
				'options' => array(
					'capture'   => __( 'Capture (Immediate)', 'bkx-razorpay' ),
					'authorize' => __( 'Authorize Only', 'bkx-razorpay' ),
				),
				'default' => 'capture',
			),
			array(
				'id'      => 'auto_refund_on_cancel',
				'title'   => __( 'Auto Refund', 'bkx-razorpay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Automatically refund when booking is cancelled', 'bkx-razorpay' ),
				'default' => true,
			),
			array(
				'id'      => 'debug_log',
				'title'   => __( 'Debug Log', 'bkx-razorpay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable debug logging', 'bkx-razorpay' ),
				'default' => false,
			),
		);
	}
}

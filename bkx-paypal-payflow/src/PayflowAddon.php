<?php
/**
 * PayPal Payflow Addon
 *
 * @package BookingX\PayPalPayflow
 * @since   1.0.0
 */

namespace BookingX\PayPalPayflow;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;

/**
 * Main addon class for PayPal Payflow Pro.
 *
 * @since 1.0.0
 */
class PayflowAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $slug = 'bkx-paypal-payflow';

	/**
	 * Addon version.
	 *
	 * @var string
	 */
	protected string $version = '1.0.0';

	/**
	 * EDD product ID.
	 *
	 * @var int
	 */
	protected int $product_id = 126;

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot the addon.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		// Register payment gateway.
		add_filter( 'bkx_payment_gateways', array( $this, 'register_gateway' ) );

		// Register admin settings.
		if ( is_admin() ) {
			new Admin\SettingsPage( $this );
		}

		// Enqueue assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'enabled'           => 0,
			'sandbox'           => 1,
			'title'             => __( 'Credit Card (PayPal Payflow)', 'bkx-paypal-payflow' ),
			'description'       => __( 'Pay securely with your credit card.', 'bkx-paypal-payflow' ),
			'partner'           => '',
			'vendor'            => '',
			'user'              => '',
			'password'          => '',
			'sandbox_partner'   => '',
			'sandbox_vendor'    => '',
			'sandbox_user'      => '',
			'sandbox_password'  => '',
			'transaction_type'  => 'S', // S = Sale, A = Authorization.
			'verbosity'         => 'MEDIUM',
			'card_types'        => array( 'visa', 'mastercard', 'amex', 'discover' ),
			'fraud_protection'  => 1,
		);
	}

	/**
	 * Register payment gateway.
	 *
	 * @since 1.0.0
	 * @param array $gateways Existing gateways.
	 * @return array
	 */
	public function register_gateway( array $gateways ): array {
		$settings = $this->get_settings();

		if ( ! empty( $settings['enabled'] ) ) {
			$gateways['paypal_payflow'] = array(
				'id'          => 'paypal_payflow',
				'title'       => $settings['title'],
				'description' => $settings['description'],
				'class'       => Services\PayflowGateway::class,
				'icon'        => BKX_PAYPAL_PAYFLOW_URL . 'assets/images/payflow.png',
				'supports'    => array( 'products', 'refunds', 'tokenization' ),
			);
		}

		return $gateways;
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! $this->should_load_assets() ) {
			return;
		}

		wp_enqueue_style(
			'bkx-paypal-payflow',
			BKX_PAYPAL_PAYFLOW_URL . 'assets/css/frontend.css',
			array(),
			BKX_PAYPAL_PAYFLOW_VERSION
		);

		wp_enqueue_script(
			'bkx-paypal-payflow',
			BKX_PAYPAL_PAYFLOW_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_PAYPAL_PAYFLOW_VERSION,
			true
		);

		wp_localize_script(
			'bkx-paypal-payflow',
			'bkxPayflow',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_payflow' ),
				'i18n'    => array(
					'processing'    => __( 'Processing...', 'bkx-paypal-payflow' ),
					'error'         => __( 'An error occurred. Please try again.', 'bkx-paypal-payflow' ),
					'invalid_card'  => __( 'Please enter valid card details.', 'bkx-paypal-payflow' ),
					'card_declined' => __( 'Your card was declined.', 'bkx-paypal-payflow' ),
				),
			)
		);
	}

	/**
	 * Check if assets should load.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function should_load_assets(): bool {
		$settings = $this->get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		// Load on booking pages.
		global $post;
		if ( $post && in_array( $post->post_type, array( 'bkx_base', 'bkx_seat' ), true ) ) {
			return true;
		}

		// Load if booking shortcode present.
		if ( $post && has_shortcode( $post->post_content, 'bookingx' ) ) {
			return true;
		}

		return false;
	}
}

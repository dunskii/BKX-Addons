<?php
/**
 * Main Google Pay Addon Class
 *
 * @package BookingX\GooglePay
 * @since   1.0.0
 */

namespace BookingX\GooglePay;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\GooglePay\Admin\SettingsPage;
use BookingX\GooglePay\Services\GooglePayGateway;

/**
 * Google Pay Addon main class.
 *
 * @since 1.0.0
 */
class GooglePayAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasAjax;

	/**
	 * Singleton instance.
	 *
	 * @var GooglePayAddon|null
	 */
	private static ?GooglePayAddon $instance = null;

	/**
	 * Gateway instance.
	 *
	 * @var GooglePayGateway|null
	 */
	private ?GooglePayGateway $gateway = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return GooglePayAddon
	 */
	public static function get_instance(): GooglePayAddon {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->id             = 'google_pay';
		$this->name           = __( 'Google Pay', 'bkx-google-pay' );
		$this->version        = BKX_GOOGLE_PAY_VERSION;
		$this->file           = BKX_GOOGLE_PAY_FILE;
		$this->settings_key   = 'bkx_google_pay_settings';
		$this->license_key    = 'bkx_google_pay_license_key';
		$this->license_status = 'bkx_google_pay_license_status';
		$this->store_url      = 'https://developer.com';
		$this->item_id        = 0;

		$this->init();
	}

	/**
	 * Initialize the addon.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		$this->load_settings();
		$this->init_gateway();

		// Admin.
		if ( is_admin() ) {
			new SettingsPage( $this );
		}

		// Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Register payment gateway.
		add_filter( 'bkx_payment_gateways', array( $this, 'register_gateway' ) );

		// AJAX handlers.
		$this->register_ajax_handlers();
	}

	/**
	 * Initialize the payment gateway.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_gateway(): void {
		$this->gateway = new GooglePayGateway();
	}

	/**
	 * Get gateway instance.
	 *
	 * @since 1.0.0
	 * @return GooglePayGateway
	 */
	public function get_gateway(): GooglePayGateway {
		return $this->gateway;
	}

	/**
	 * Register payment gateway.
	 *
	 * @since 1.0.0
	 * @param array $gateways Registered gateways.
	 * @return array
	 */
	public function register_gateway( array $gateways ): array {
		$settings = $this->get_settings();

		if ( ! empty( $settings['enabled'] ) ) {
			$gateways['google_pay'] = array(
				'id'          => 'google_pay',
				'title'       => __( 'Google Pay', 'bkx-google-pay' ),
				'description' => __( 'Pay quickly and securely with Google Pay.', 'bkx-google-pay' ),
				'icon'        => BKX_GOOGLE_PAY_URL . 'assets/images/google-pay-mark.svg',
				'gateway'     => $this->gateway,
			);
		}

		return $gateways;
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$settings = $this->get_settings();

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		// Only load on booking pages.
		if ( ! $this->is_booking_page() ) {
			return;
		}

		// Google Pay API.
		wp_enqueue_script(
			'google-pay-api',
			'https://pay.google.com/gp/p/js/pay.js',
			array(),
			null,
			true
		);

		// Frontend script.
		wp_enqueue_script(
			'bkx-google-pay',
			BKX_GOOGLE_PAY_URL . 'assets/js/frontend.js',
			array( 'jquery', 'google-pay-api' ),
			BKX_GOOGLE_PAY_VERSION,
			true
		);

		// Frontend styles.
		wp_enqueue_style(
			'bkx-google-pay',
			BKX_GOOGLE_PAY_URL . 'assets/css/frontend.css',
			array(),
			BKX_GOOGLE_PAY_VERSION
		);

		// Localize script.
		wp_localize_script( 'bkx-google-pay', 'bkxGooglePay', array(
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'bkx_google_pay_nonce' ),
			'environment'      => $settings['environment'] ?? 'TEST',
			'merchantId'       => $settings['merchant_id'] ?? '',
			'merchantName'     => $settings['merchant_name'] ?? get_bloginfo( 'name' ),
			'gateway'          => $settings['gateway'] ?? 'stripe',
			'gatewayMerchantId' => $settings['gateway_merchant_id'] ?? '',
			'allowedCards'     => $settings['allowed_cards'] ?? array( 'AMEX', 'MASTERCARD', 'VISA', 'DISCOVER' ),
			'buttonColor'      => $settings['button_color'] ?? 'black',
			'buttonType'       => $settings['button_type'] ?? 'pay',
			'buttonLocale'     => $settings['button_locale'] ?? 'en',
			'currencyCode'     => get_option( 'bkx_currency', 'USD' ),
			'countryCode'      => get_option( 'bkx_country', 'US' ),
			'i18n'             => array(
				'processingPayment' => __( 'Processing payment...', 'bkx-google-pay' ),
				'paymentFailed'     => __( 'Payment failed. Please try again.', 'bkx-google-pay' ),
				'notSupported'      => __( 'Google Pay is not available on this device.', 'bkx-google-pay' ),
			),
		) );
	}

	/**
	 * Check if current page is a booking page.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function is_booking_page(): bool {
		global $post;

		if ( ! $post ) {
			return false;
		}

		// Check for booking shortcode.
		if ( has_shortcode( $post->post_content, 'bookingx' ) ) {
			return true;
		}

		// Check for booking form block.
		if ( has_block( 'bookingx/booking-form', $post ) ) {
			return true;
		}

		// Check post type.
		$booking_post_types = array( 'bkx_seat', 'bkx_base' );
		if ( in_array( $post->post_type, $booking_post_types, true ) ) {
			return true;
		}

		return apply_filters( 'bkx_google_pay_is_booking_page', false, $post );
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		add_action( 'wp_ajax_bkx_google_pay_process', array( $this, 'ajax_process_payment' ) );
		add_action( 'wp_ajax_nopriv_bkx_google_pay_process', array( $this, 'ajax_process_payment' ) );

		add_action( 'wp_ajax_bkx_google_pay_validate', array( $this, 'ajax_validate_merchant' ) );
		add_action( 'wp_ajax_nopriv_bkx_google_pay_validate', array( $this, 'ajax_validate_merchant' ) );
	}

	/**
	 * AJAX: Process Google Pay payment.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_process_payment(): void {
		check_ajax_referer( 'bkx_google_pay_nonce', 'nonce' );

		$booking_id   = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$payment_data = isset( $_POST['payment_data'] ) ? json_decode( wp_unslash( $_POST['payment_data'] ), true ) : array();

		if ( ! $booking_id || empty( $payment_data ) ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid payment data.', 'bkx-google-pay' ),
			) );
		}

		$result = $this->gateway->process_payment( $booking_id, $payment_data );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Validate merchant.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_validate_merchant(): void {
		check_ajax_referer( 'bkx_google_pay_nonce', 'nonce' );

		$settings = $this->get_settings();

		// Basic validation.
		$is_valid = ! empty( $settings['merchant_id'] ) || 'TEST' === ( $settings['environment'] ?? 'TEST' );

		wp_send_json_success( array(
			'valid' => $is_valid,
		) );
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'enabled'             => 0,
			'environment'         => 'TEST',
			'merchant_id'         => '',
			'merchant_name'       => get_bloginfo( 'name' ),
			'gateway'             => 'stripe',
			'gateway_merchant_id' => '',
			'button_color'        => 'black',
			'button_type'         => 'pay',
			'button_locale'       => 'en',
			'allowed_cards'       => array( 'AMEX', 'MASTERCARD', 'VISA', 'DISCOVER' ),
		);
	}
}

<?php
/**
 * Main Regional Payments Addon Class
 *
 * @package BookingX\RegionalPayments
 * @since   1.0.0
 */

namespace BookingX\RegionalPayments;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\RegionalPayments\Admin\SettingsPage;
use BookingX\RegionalPayments\Services\GatewayRegistry;
use BookingX\RegionalPayments\Services\CountryDetector;

/**
 * Regional Payments Addon main class.
 *
 * @since 1.0.0
 */
class RegionalPaymentsAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasAjax;

	/**
	 * Singleton instance.
	 *
	 * @var RegionalPaymentsAddon|null
	 */
	private static ?RegionalPaymentsAddon $instance = null;

	/**
	 * Gateway registry.
	 *
	 * @var GatewayRegistry
	 */
	private GatewayRegistry $gateway_registry;

	/**
	 * Country detector.
	 *
	 * @var CountryDetector
	 */
	private CountryDetector $country_detector;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return RegionalPaymentsAddon
	 */
	public static function get_instance(): RegionalPaymentsAddon {
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
		$this->id             = 'regional_payments';
		$this->name           = __( 'Regional Payment Hub', 'bkx-regional-payments' );
		$this->version        = BKX_REGIONAL_PAYMENTS_VERSION;
		$this->file           = BKX_REGIONAL_PAYMENTS_FILE;
		$this->settings_key   = 'bkx_regional_payments_settings';
		$this->license_key    = 'bkx_regional_payments_license_key';
		$this->license_status = 'bkx_regional_payments_license_status';
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
		$this->init_services();

		// Admin.
		if ( is_admin() ) {
			new SettingsPage( $this );
		}

		// Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Register regional gateways.
		add_filter( 'bkx_payment_gateways', array( $this, 'register_gateways' ) );

		// Filter gateways based on customer location.
		add_filter( 'bkx_available_payment_gateways', array( $this, 'filter_gateways_by_location' ) );

		// AJAX handlers.
		$this->register_ajax_handlers();
	}

	/**
	 * Initialize services.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_services(): void {
		$this->gateway_registry = new GatewayRegistry();
		$this->country_detector = new CountryDetector();

		// Register built-in gateways.
		$this->register_builtin_gateways();
	}

	/**
	 * Register built-in regional gateways.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_builtin_gateways(): void {
		// PIX - Brazil.
		$this->gateway_registry->register(
			'pix',
			array(
				'id'          => 'pix',
				'title'       => __( 'PIX', 'bkx-regional-payments' ),
				'description' => __( 'Instant payment via PIX (Brazil).', 'bkx-regional-payments' ),
				'countries'   => array( 'BR' ),
				'currencies'  => array( 'BRL' ),
				'gateway'     => Gateways\PIXGateway::class,
				'icon'        => BKX_REGIONAL_PAYMENTS_URL . 'assets/images/pix.svg',
			)
		);

		// UPI - India.
		$this->gateway_registry->register(
			'upi',
			array(
				'id'          => 'upi',
				'title'       => __( 'UPI', 'bkx-regional-payments' ),
				'description' => __( 'Unified Payments Interface (India).', 'bkx-regional-payments' ),
				'countries'   => array( 'IN' ),
				'currencies'  => array( 'INR' ),
				'gateway'     => Gateways\UPIGateway::class,
				'icon'        => BKX_REGIONAL_PAYMENTS_URL . 'assets/images/upi.svg',
			)
		);

		// SEPA - Europe.
		$this->gateway_registry->register(
			'sepa',
			array(
				'id'          => 'sepa',
				'title'       => __( 'SEPA Direct Debit', 'bkx-regional-payments' ),
				'description' => __( 'Bank transfer via SEPA (Europe).', 'bkx-regional-payments' ),
				'countries'   => array( 'AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV', 'MC', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'SM', 'VA' ),
				'currencies'  => array( 'EUR' ),
				'gateway'     => Gateways\SEPAGateway::class,
				'icon'        => BKX_REGIONAL_PAYMENTS_URL . 'assets/images/sepa.svg',
			)
		);

		// iDEAL - Netherlands.
		$this->gateway_registry->register(
			'ideal',
			array(
				'id'          => 'ideal',
				'title'       => __( 'iDEAL', 'bkx-regional-payments' ),
				'description' => __( 'Bank transfer via iDEAL (Netherlands).', 'bkx-regional-payments' ),
				'countries'   => array( 'NL' ),
				'currencies'  => array( 'EUR' ),
				'gateway'     => Gateways\iDEALGateway::class,
				'icon'        => BKX_REGIONAL_PAYMENTS_URL . 'assets/images/ideal.svg',
			)
		);

		// Bancontact - Belgium.
		$this->gateway_registry->register(
			'bancontact',
			array(
				'id'          => 'bancontact',
				'title'       => __( 'Bancontact', 'bkx-regional-payments' ),
				'description' => __( 'Pay with Bancontact (Belgium).', 'bkx-regional-payments' ),
				'countries'   => array( 'BE' ),
				'currencies'  => array( 'EUR' ),
				'gateway'     => Gateways\BancontactGateway::class,
				'icon'        => BKX_REGIONAL_PAYMENTS_URL . 'assets/images/bancontact.svg',
			)
		);

		// GiroPay - Germany.
		$this->gateway_registry->register(
			'giropay',
			array(
				'id'          => 'giropay',
				'title'       => __( 'GiroPay', 'bkx-regional-payments' ),
				'description' => __( 'Bank transfer via GiroPay (Germany).', 'bkx-regional-payments' ),
				'countries'   => array( 'DE' ),
				'currencies'  => array( 'EUR' ),
				'gateway'     => Gateways\GiroPayGateway::class,
				'icon'        => BKX_REGIONAL_PAYMENTS_URL . 'assets/images/giropay.svg',
			)
		);

		// Przelewy24 - Poland.
		$this->gateway_registry->register(
			'przelewy24',
			array(
				'id'          => 'przelewy24',
				'title'       => __( 'Przelewy24', 'bkx-regional-payments' ),
				'description' => __( 'Bank transfer via Przelewy24 (Poland).', 'bkx-regional-payments' ),
				'countries'   => array( 'PL' ),
				'currencies'  => array( 'PLN', 'EUR' ),
				'gateway'     => Gateways\Przelewy24Gateway::class,
				'icon'        => BKX_REGIONAL_PAYMENTS_URL . 'assets/images/przelewy24.svg',
			)
		);

		// Boleto - Brazil.
		$this->gateway_registry->register(
			'boleto',
			array(
				'id'          => 'boleto',
				'title'       => __( 'Boleto Bancário', 'bkx-regional-payments' ),
				'description' => __( 'Pay with Boleto (Brazil).', 'bkx-regional-payments' ),
				'countries'   => array( 'BR' ),
				'currencies'  => array( 'BRL' ),
				'gateway'     => Gateways\BoletoGateway::class,
				'icon'        => BKX_REGIONAL_PAYMENTS_URL . 'assets/images/boleto.svg',
			)
		);

		// Allow third-party gateway registration.
		do_action( 'bkx_regional_payments_register_gateways', $this->gateway_registry );
	}

	/**
	 * Get gateway registry.
	 *
	 * @since 1.0.0
	 * @return GatewayRegistry
	 */
	public function get_gateway_registry(): GatewayRegistry {
		return $this->gateway_registry;
	}

	/**
	 * Get country detector.
	 *
	 * @since 1.0.0
	 * @return CountryDetector
	 */
	public function get_country_detector(): CountryDetector {
		return $this->country_detector;
	}

	/**
	 * Register payment gateways.
	 *
	 * @since 1.0.0
	 * @param array $gateways Registered gateways.
	 * @return array
	 */
	public function register_gateways( array $gateways ): array {
		$settings         = $this->get_settings();
		$enabled_gateways = $settings['enabled_gateways'] ?? array();

		foreach ( $this->gateway_registry->get_all() as $id => $gateway_config ) {
			if ( ! in_array( $id, $enabled_gateways, true ) ) {
				continue;
			}

			$gateway_class = $gateway_config['gateway'];

			if ( class_exists( $gateway_class ) ) {
				$gateways[ 'regional_' . $id ] = array(
					'id'          => 'regional_' . $id,
					'title'       => $gateway_config['title'],
					'description' => $gateway_config['description'],
					'icon'        => $gateway_config['icon'],
					'gateway'     => new $gateway_class(),
				);
			}
		}

		return $gateways;
	}

	/**
	 * Filter gateways based on customer location.
	 *
	 * @since 1.0.0
	 * @param array $gateways Available gateways.
	 * @return array
	 */
	public function filter_gateways_by_location( array $gateways ): array {
		$settings = $this->get_settings();

		// Skip filtering if auto-detect is disabled.
		if ( empty( $settings['auto_detect_country'] ) ) {
			return $gateways;
		}

		$customer_country = $this->country_detector->get_customer_country();

		if ( ! $customer_country ) {
			return $gateways;
		}

		$filtered = array();

		foreach ( $gateways as $id => $gateway ) {
			// Check if this is a regional gateway.
			if ( strpos( $id, 'regional_' ) !== 0 ) {
				$filtered[ $id ] = $gateway;
				continue;
			}

			// Get the regional gateway ID.
			$regional_id     = str_replace( 'regional_', '', $id );
			$gateway_config  = $this->gateway_registry->get( $regional_id );

			if ( ! $gateway_config ) {
				continue;
			}

			// Check if gateway is available in customer's country.
			$allowed_countries = $gateway_config['countries'] ?? array();

			if ( empty( $allowed_countries ) || in_array( $customer_country, $allowed_countries, true ) ) {
				$filtered[ $id ] = $gateway;
			}
		}

		return $filtered;
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$settings         = $this->get_settings();
		$enabled_gateways = $settings['enabled_gateways'] ?? array();

		if ( empty( $enabled_gateways ) ) {
			return;
		}

		// Frontend styles.
		wp_enqueue_style(
			'bkx-regional-payments',
			BKX_REGIONAL_PAYMENTS_URL . 'assets/css/frontend.css',
			array(),
			BKX_REGIONAL_PAYMENTS_VERSION
		);

		// Frontend script.
		wp_enqueue_script(
			'bkx-regional-payments',
			BKX_REGIONAL_PAYMENTS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_REGIONAL_PAYMENTS_VERSION,
			true
		);

		wp_localize_script( 'bkx-regional-payments', 'bkxRegionalPayments', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bkx_regional_payments_nonce' ),
			'i18n'    => array(
				'processing' => __( 'Processing payment...', 'bkx-regional-payments' ),
				'error'      => __( 'Payment failed. Please try again.', 'bkx-regional-payments' ),
				'success'    => __( 'Payment successful!', 'bkx-regional-payments' ),
			),
		) );
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		add_action( 'wp_ajax_bkx_regional_process_payment', array( $this, 'ajax_process_payment' ) );
		add_action( 'wp_ajax_nopriv_bkx_regional_process_payment', array( $this, 'ajax_process_payment' ) );

		add_action( 'wp_ajax_bkx_regional_check_payment_status', array( $this, 'ajax_check_payment_status' ) );
		add_action( 'wp_ajax_nopriv_bkx_regional_check_payment_status', array( $this, 'ajax_check_payment_status' ) );

		add_action( 'wp_ajax_bkx_regional_get_qr_code', array( $this, 'ajax_get_qr_code' ) );
		add_action( 'wp_ajax_nopriv_bkx_regional_get_qr_code', array( $this, 'ajax_get_qr_code' ) );
	}

	/**
	 * AJAX: Process payment.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_process_payment(): void {
		check_ajax_referer( 'bkx_regional_payments_nonce', 'nonce' );

		$booking_id   = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$gateway_id   = isset( $_POST['gateway'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway'] ) ) : '';
		$payment_data = isset( $_POST['payment_data'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['payment_data'] ) ) : array();

		if ( ! $booking_id || ! $gateway_id ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid request.', 'bkx-regional-payments' ),
			) );
		}

		// Get the gateway.
		$regional_id    = str_replace( 'regional_', '', $gateway_id );
		$gateway_config = $this->gateway_registry->get( $regional_id );

		if ( ! $gateway_config || ! class_exists( $gateway_config['gateway'] ) ) {
			wp_send_json_error( array(
				'message' => __( 'Gateway not found.', 'bkx-regional-payments' ),
			) );
		}

		$gateway = new $gateway_config['gateway']();
		$result  = $gateway->process_payment( $booking_id, $payment_data );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Check payment status.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_check_payment_status(): void {
		check_ajax_referer( 'bkx_regional_payments_nonce', 'nonce' );

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;

		if ( ! $booking_id ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid booking.', 'bkx-regional-payments' ),
			) );
		}

		$status = get_post_meta( $booking_id, '_bkx_payment_status', true );

		wp_send_json_success( array(
			'status'    => $status,
			'completed' => 'completed' === $status,
		) );
	}

	/**
	 * AJAX: Get QR code for payment.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_qr_code(): void {
		check_ajax_referer( 'bkx_regional_payments_nonce', 'nonce' );

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$gateway_id = isset( $_POST['gateway'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway'] ) ) : '';

		if ( ! $booking_id || ! $gateway_id ) {
			wp_send_json_error( array(
				'message' => __( 'Invalid request.', 'bkx-regional-payments' ),
			) );
		}

		$qr_code = get_post_meta( $booking_id, '_bkx_payment_qr_code', true );

		if ( $qr_code ) {
			wp_send_json_success( array(
				'qr_code' => $qr_code,
			) );
		} else {
			wp_send_json_error( array(
				'message' => __( 'QR code not available.', 'bkx-regional-payments' ),
			) );
		}
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'enabled_gateways'    => array(),
			'auto_detect_country' => 1,
			'fallback_gateway'    => '',
		);
	}
}

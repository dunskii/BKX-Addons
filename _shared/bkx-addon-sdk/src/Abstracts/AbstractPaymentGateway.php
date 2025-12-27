<?php
/**
 * Abstract Payment Gateway Base Class
 *
 * Provides the foundation for all BookingX payment gateway add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Abstracts
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Abstracts;

use BookingX\AddonSDK\Contracts\PaymentGatewayInterface;

/**
 * Abstract base class for payment gateways.
 *
 * @since 1.0.0
 */
abstract class AbstractPaymentGateway implements PaymentGatewayInterface {

    /**
     * Unique gateway identifier.
     *
     * @var string
     */
    protected string $id;

    /**
     * Gateway display name.
     *
     * @var string
     */
    protected string $title;

    /**
     * Gateway description.
     *
     * @var string
     */
    protected string $description;

    /**
     * Gateway icon URL.
     *
     * @var string
     */
    protected string $icon = '';

    /**
     * Whether the gateway is enabled.
     *
     * @var bool
     */
    protected bool $enabled = false;

    /**
     * Whether the gateway is in test/sandbox mode.
     *
     * @var bool
     */
    protected bool $test_mode = true;

    /**
     * Supported features.
     *
     * @var array
     */
    protected array $supports = [
        'payments',
        'refunds',
    ];

    /**
     * Supported currencies.
     *
     * @var array Empty array means all currencies are supported.
     */
    protected array $supported_currencies = [];

    /**
     * Gateway settings.
     *
     * @var array
     */
    protected array $settings = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->load_settings();
    }

    /**
     * Load gateway settings from database.
     *
     * @since 1.0.0
     * @return void
     */
    protected function load_settings(): void {
        $settings = get_option( "bkx_gateway_{$this->id}_settings", [] );

        $defaults = $this->get_default_settings();
        $this->settings = wp_parse_args( $settings, $defaults );

        $this->enabled   = $this->get_setting( 'enabled', false );
        $this->test_mode = $this->get_setting( 'test_mode', true );
    }

    /**
     * Get default settings.
     *
     * @since 1.0.0
     * @return array
     */
    protected function get_default_settings(): array {
        return [
            'enabled'   => false,
            'test_mode' => true,
        ];
    }

    /**
     * Get a specific setting value.
     *
     * @since 1.0.0
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_setting( string $key, $default = null ) {
        return $this->settings[ $key ] ?? $default;
    }

    /**
     * Update a setting value.
     *
     * @since 1.0.0
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     * @return void
     */
    public function update_setting( string $key, $value ): void {
        $this->settings[ $key ] = $value;
        update_option( "bkx_gateway_{$this->id}_settings", $this->settings );
    }

    /**
     * Save all settings.
     *
     * @since 1.0.0
     * @param array $settings Settings to save.
     * @return bool
     */
    public function save_settings( array $settings ): bool {
        // Validate settings before saving
        $validated = $this->validate_settings( $settings );
        if ( is_wp_error( $validated ) ) {
            return false;
        }

        $this->settings = wp_parse_args( $settings, $this->settings );
        return update_option( "bkx_gateway_{$this->id}_settings", $this->settings );
    }

    /**
     * Get the gateway ID.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Get the gateway title.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_title(): string {
        return $this->title;
    }

    /**
     * Get the gateway description.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_description(): string {
        return $this->description;
    }

    /**
     * Get the gateway icon URL.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_icon(): string {
        return $this->icon;
    }

    /**
     * Check if the gateway is enabled.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->enabled;
    }

    /**
     * Check if the gateway is in test mode.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_test_mode(): bool {
        return $this->test_mode;
    }

    /**
     * Check if the gateway is available for use.
     *
     * This checks if the gateway is enabled and properly configured.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_available(): bool {
        if ( ! $this->is_enabled() ) {
            return false;
        }

        // Check if current currency is supported
        if ( ! empty( $this->supported_currencies ) ) {
            $currency = $this->get_current_currency();
            if ( ! in_array( $currency, $this->supported_currencies, true ) ) {
                return false;
            }
        }

        // Let child classes add additional checks
        return $this->validate_availability();
    }

    /**
     * Additional availability validation.
     *
     * Override in child class to add gateway-specific checks.
     *
     * @since 1.0.0
     * @return bool
     */
    protected function validate_availability(): bool {
        return true;
    }

    /**
     * Get current currency.
     *
     * @since 1.0.0
     * @return string
     */
    protected function get_current_currency(): string {
        // Try to get from BookingX settings
        $currency = bkx_crud_option_multisite( 'bkx_currency' );
        return $currency ?: 'USD';
    }

    /**
     * Check if the gateway supports a feature.
     *
     * @since 1.0.0
     * @param string $feature Feature name.
     * @return bool
     */
    public function supports( string $feature ): bool {
        return in_array( $feature, $this->supports, true );
    }

    /**
     * Get supported features.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_supported_features(): array {
        return $this->supports;
    }

    /**
     * Get supported currencies.
     *
     * @since 1.0.0
     * @return array Empty array means all currencies are supported.
     */
    public function get_supported_currencies(): array {
        return $this->supported_currencies;
    }

    /**
     * Get API credentials based on mode.
     *
     * @since 1.0.0
     * @return array
     */
    protected function get_api_credentials(): array {
        if ( $this->is_test_mode() ) {
            return [
                'api_key'    => $this->get_setting( 'test_api_key', '' ),
                'api_secret' => $this->get_setting( 'test_api_secret', '' ),
            ];
        }

        return [
            'api_key'    => $this->get_setting( 'live_api_key', '' ),
            'api_secret' => $this->get_setting( 'live_api_secret', '' ),
        ];
    }

    /**
     * Log a gateway message.
     *
     * @since 1.0.0
     * @param string $message  Log message.
     * @param string $level    Log level (debug, info, warning, error).
     * @param array  $context  Additional context.
     * @return void
     */
    protected function log( string $message, string $level = 'info', array $context = [] ): void {
        if ( ! $this->get_setting( 'debug_log', false ) && 'debug' === $level ) {
            return;
        }

        $context['gateway'] = $this->id;
        $context['mode']    = $this->is_test_mode() ? 'test' : 'live';

        do_action( 'bkx_gateway_log', $this->id, $message, $level, $context );
    }

    /**
     * Format amount for the gateway.
     *
     * Most gateways expect amounts in cents.
     *
     * @since 1.0.0
     * @param float  $amount   Amount in dollars.
     * @param string $currency Currency code.
     * @return int Amount in smallest currency unit.
     */
    protected function format_amount( float $amount, string $currency = 'USD' ): int {
        // Zero-decimal currencies
        $zero_decimal = [ 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' ];

        if ( in_array( strtoupper( $currency ), $zero_decimal, true ) ) {
            return (int) round( $amount );
        }

        return (int) round( $amount * 100 );
    }

    /**
     * Format amount from gateway (cents to dollars).
     *
     * @since 1.0.0
     * @param int    $amount   Amount in smallest currency unit.
     * @param string $currency Currency code.
     * @return float Amount in dollars.
     */
    protected function unformat_amount( int $amount, string $currency = 'USD' ): float {
        $zero_decimal = [ 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' ];

        if ( in_array( strtoupper( $currency ), $zero_decimal, true ) ) {
            return (float) $amount;
        }

        return (float) $amount / 100;
    }

    /**
     * Generate a unique transaction ID.
     *
     * @since 1.0.0
     * @param int $booking_id Booking ID.
     * @return string
     */
    protected function generate_transaction_id( int $booking_id ): string {
        return sprintf(
            'bkx_%s_%d_%s',
            $this->id,
            $booking_id,
            wp_generate_password( 8, false )
        );
    }

    /**
     * Get the return URL after payment.
     *
     * @since 1.0.0
     * @param int    $booking_id Booking ID.
     * @param string $status     Payment status (success, cancel, error).
     * @return string
     */
    protected function get_return_url( int $booking_id, string $status = 'success' ): string {
        $base_url = home_url( '/booking-confirmation/' );

        return add_query_arg( [
            'booking_id' => $booking_id,
            'gateway'    => $this->id,
            'status'     => $status,
            'nonce'      => wp_create_nonce( "bkx_payment_{$booking_id}" ),
        ], $base_url );
    }

    /**
     * Get the webhook/IPN URL.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_webhook_url(): string {
        return rest_url( "bookingx/v1/webhooks/{$this->id}" );
    }

    /**
     * Validate settings.
     *
     * @since 1.0.0
     * @param array $settings Settings to validate.
     * @return true|\WP_Error True if valid, WP_Error otherwise.
     */
    public function validate_settings( array $settings ) {
        // Override in child class for gateway-specific validation
        return true;
    }

    /**
     * Get settings fields for the admin form.
     *
     * @since 1.0.0
     * @return array Array of field definitions.
     */
    abstract public function get_settings_fields(): array;

    /**
     * Process a payment.
     *
     * @since 1.0.0
     * @param int   $booking_id   Booking ID.
     * @param array $payment_data Payment data including amount, currency, etc.
     * @return array Result with 'success' bool and 'data' or 'error'.
     */
    abstract public function process_payment( int $booking_id, array $payment_data ): array;

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
    abstract public function process_refund( int $booking_id, float $amount, string $reason = '', string $transaction_id = '' ): array;

    /**
     * Handle webhook/IPN callback.
     *
     * @since 1.0.0
     * @param array $payload Webhook payload.
     * @return array Result with 'success' bool and 'message'.
     */
    abstract public function handle_webhook( array $payload ): array;

    /**
     * Render the payment form/fields on checkout.
     *
     * @since 1.0.0
     * @param int   $booking_id Booking ID.
     * @param float $amount     Payment amount.
     * @return void
     */
    public function render_payment_form( int $booking_id, float $amount ): void {
        // Override in child class to render gateway-specific form
    }

    /**
     * Enqueue gateway scripts and styles.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_scripts(): void {
        // Override in child class to enqueue gateway-specific assets
    }
}

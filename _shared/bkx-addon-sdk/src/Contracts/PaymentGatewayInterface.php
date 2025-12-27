<?php
/**
 * Payment Gateway Interface
 *
 * Contract that all payment gateways must implement.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Contracts
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Contracts;

/**
 * Interface for payment gateways.
 *
 * @since 1.0.0
 */
interface PaymentGatewayInterface {

    /**
     * Get the gateway ID.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_id(): string;

    /**
     * Get the gateway title.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_title(): string;

    /**
     * Get the gateway description.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_description(): string;

    /**
     * Check if the gateway is enabled.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_enabled(): bool;

    /**
     * Check if the gateway is available for use.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Check if the gateway supports a feature.
     *
     * @since 1.0.0
     * @param string $feature Feature name.
     * @return bool
     */
    public function supports( string $feature ): bool;

    /**
     * Get supported features.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_supported_features(): array;

    /**
     * Get settings fields for the admin form.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_settings_fields(): array;

    /**
     * Process a payment.
     *
     * @since 1.0.0
     * @param int   $booking_id   Booking ID.
     * @param array $payment_data Payment data.
     * @return array Result with 'success' bool and 'data' or 'error'.
     */
    public function process_payment( int $booking_id, array $payment_data ): array;

    /**
     * Process a refund.
     *
     * @since 1.0.0
     * @param int    $booking_id     Booking ID.
     * @param float  $amount         Refund amount.
     * @param string $reason         Refund reason.
     * @param string $transaction_id Original transaction ID.
     * @return array Result.
     */
    public function process_refund( int $booking_id, float $amount, string $reason = '', string $transaction_id = '' ): array;

    /**
     * Handle webhook/IPN callback.
     *
     * @since 1.0.0
     * @param array $payload Webhook payload.
     * @return array Result.
     */
    public function handle_webhook( array $payload ): array;

    /**
     * Validate settings.
     *
     * @since 1.0.0
     * @param array $settings Settings to validate.
     * @return true|\WP_Error
     */
    public function validate_settings( array $settings );
}

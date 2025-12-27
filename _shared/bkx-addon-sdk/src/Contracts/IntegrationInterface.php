<?php
/**
 * Integration Interface
 *
 * Contract that all third-party integrations must implement.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Contracts
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Contracts;

/**
 * Interface for third-party integrations.
 *
 * @since 1.0.0
 */
interface IntegrationInterface {

    /**
     * Get the integration ID.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_id(): string;

    /**
     * Get the integration name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_name(): string;

    /**
     * Check if the integration is enabled.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_enabled(): bool;

    /**
     * Check if the integration is connected.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_connected(): bool;

    /**
     * Check if the integration is available (enabled and connected).
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Connect the integration.
     *
     * @since 1.0.0
     * @param array $credentials Connection credentials.
     * @return array Result with 'success' bool and 'message'.
     */
    public function connect( array $credentials ): array;

    /**
     * Disconnect the integration.
     *
     * @since 1.0.0
     * @return bool
     */
    public function disconnect(): bool;

    /**
     * Get settings fields for the admin form.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_settings_fields(): array;

    /**
     * Sync a booking creation.
     *
     * @since 1.0.0
     * @param int   $booking_id   Booking ID.
     * @param array $booking_data Booking data.
     * @return bool
     */
    public function sync_booking_created( int $booking_id, array $booking_data ): bool;

    /**
     * Sync a booking update.
     *
     * @since 1.0.0
     * @param int   $booking_id   Booking ID.
     * @param array $booking_data Booking data.
     * @return bool
     */
    public function sync_booking_updated( int $booking_id, array $booking_data ): bool;

    /**
     * Sync a customer creation.
     *
     * @since 1.0.0
     * @param int   $customer_id   Customer/User ID.
     * @param array $customer_data Customer data.
     * @return bool
     */
    public function sync_customer_created( int $customer_id, array $customer_data ): bool;

    /**
     * Sync a customer update.
     *
     * @since 1.0.0
     * @param int   $customer_id   Customer/User ID.
     * @param array $customer_data Customer data.
     * @return bool
     */
    public function sync_customer_updated( int $customer_id, array $customer_data ): bool;
}

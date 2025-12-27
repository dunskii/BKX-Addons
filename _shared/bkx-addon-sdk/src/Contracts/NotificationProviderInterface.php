<?php
/**
 * Notification Provider Interface
 *
 * Contract that all notification providers must implement.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Contracts
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Contracts;

/**
 * Interface for notification providers.
 *
 * @since 1.0.0
 */
interface NotificationProviderInterface {

    /**
     * Get the provider ID.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_id(): string;

    /**
     * Get the provider name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_name(): string;

    /**
     * Get the channel type.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_channel(): string;

    /**
     * Check if the provider is enabled.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_enabled(): bool;

    /**
     * Check if the provider is available.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Check if a notification type is supported.
     *
     * @since 1.0.0
     * @param string $type Notification type.
     * @return bool
     */
    public function supports_type( string $type ): bool;

    /**
     * Send a notification.
     *
     * @since 1.0.0
     * @param array $notification Notification data.
     * @return array Result with 'success' bool and 'message_id' or 'error'.
     */
    public function send( array $notification ): array;

    /**
     * Get settings fields for the admin form.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_settings_fields(): array;

    /**
     * Validate credentials.
     *
     * @since 1.0.0
     * @return array Result with 'success' bool and 'message'.
     */
    public function validate_credentials(): array;
}

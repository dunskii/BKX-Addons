<?php
/**
 * Abstract Notification Provider Base Class
 *
 * Provides the foundation for notification providers (Email, SMS, Push, etc.)
 *
 * @package    BookingX\AddonSDK
 * @subpackage Abstracts
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Abstracts;

use BookingX\AddonSDK\Contracts\NotificationProviderInterface;

/**
 * Abstract base class for notification providers.
 *
 * @since 1.0.0
 */
abstract class AbstractNotificationProvider implements NotificationProviderInterface {

    /**
     * Provider identifier.
     *
     * @var string
     */
    protected string $id;

    /**
     * Provider display name.
     *
     * @var string
     */
    protected string $name;

    /**
     * Provider channel type (email, sms, push, chat).
     *
     * @var string
     */
    protected string $channel;

    /**
     * Provider settings.
     *
     * @var array
     */
    protected array $settings = [];

    /**
     * Whether the provider is enabled.
     *
     * @var bool
     */
    protected bool $enabled = false;

    /**
     * Supported notification types.
     *
     * @var array
     */
    protected array $supported_types = [
        'booking_confirmation',
        'booking_reminder',
        'booking_cancelled',
        'booking_rescheduled',
        'payment_received',
        'payment_failed',
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->load_settings();
    }

    /**
     * Load provider settings from database.
     *
     * @since 1.0.0
     * @return void
     */
    protected function load_settings(): void {
        $settings = get_option( "bkx_notification_{$this->id}_settings", [] );
        $defaults = $this->get_default_settings();
        $this->settings = wp_parse_args( $settings, $defaults );
        $this->enabled = (bool) $this->get_setting( 'enabled', false );
    }

    /**
     * Get default settings.
     *
     * @since 1.0.0
     * @return array
     */
    protected function get_default_settings(): array {
        return [
            'enabled' => false,
        ];
    }

    /**
     * Get a setting value.
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
        update_option( "bkx_notification_{$this->id}_settings", $this->settings );
    }

    /**
     * Get the provider ID.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Get the provider name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Get the channel type.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_channel(): string {
        return $this->channel;
    }

    /**
     * Check if the provider is enabled.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->enabled;
    }

    /**
     * Check if the provider is available.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_available(): bool {
        if ( ! $this->is_enabled() ) {
            return false;
        }

        return $this->validate_configuration();
    }

    /**
     * Validate provider configuration.
     *
     * @since 1.0.0
     * @return bool
     */
    abstract protected function validate_configuration(): bool;

    /**
     * Check if a notification type is supported.
     *
     * @since 1.0.0
     * @param string $type Notification type.
     * @return bool
     */
    public function supports_type( string $type ): bool {
        return in_array( $type, $this->supported_types, true );
    }

    /**
     * Get supported notification types.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_supported_types(): array {
        return $this->supported_types;
    }

    /**
     * Send a notification.
     *
     * @since 1.0.0
     * @param array $notification Notification data including:
     *                            - type: Notification type
     *                            - recipient: Recipient address/ID
     *                            - subject: Notification subject (for email)
     *                            - message: Notification message/body
     *                            - data: Additional data for templates.
     * @return array Result with 'success' bool and 'message_id' or 'error'.
     */
    abstract public function send( array $notification ): array;

    /**
     * Send a batch of notifications.
     *
     * @since 1.0.0
     * @param array $notifications Array of notification data.
     * @return array Results array with successes and failures.
     */
    public function send_batch( array $notifications ): array {
        $results = [
            'success' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];

        foreach ( $notifications as $index => $notification ) {
            $result = $this->send( $notification );

            if ( $result['success'] ) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][ $index ] = $result['error'] ?? 'Unknown error';
            }
        }

        return $results;
    }

    /**
     * Get delivery status of a sent notification.
     *
     * @since 1.0.0
     * @param string $message_id Message ID from send result.
     * @return array Status data.
     */
    public function get_delivery_status( string $message_id ): array {
        // Override in child class if provider supports status checking
        return [
            'status'  => 'unknown',
            'message' => __( 'Delivery status tracking not supported by this provider.', 'bkx-addon-sdk' ),
        ];
    }

    /**
     * Process merge tags in content.
     *
     * @since 1.0.0
     * @param string $content Content with merge tags.
     * @param array  $data    Data for merge tags.
     * @return string Processed content.
     */
    protected function process_merge_tags( string $content, array $data ): string {
        $merge_tags = $this->get_merge_tags();

        foreach ( $merge_tags as $tag => $callback ) {
            $pattern = '/\{\{\s*' . preg_quote( $tag, '/' ) . '\s*\}\}/';
            $value   = is_callable( $callback ) ? $callback( $data ) : ( $data[ $tag ] ?? '' );
            $content = preg_replace( $pattern, $value, $content );
        }

        return $content;
    }

    /**
     * Get available merge tags.
     *
     * @since 1.0.0
     * @return array Tag => callback/key pairs.
     */
    protected function get_merge_tags(): array {
        return apply_filters( "bkx_notification_{$this->id}_merge_tags", [
            'customer_name'    => function( $data ) { return $data['customer_name'] ?? ''; },
            'customer_email'   => function( $data ) { return $data['customer_email'] ?? ''; },
            'booking_date'     => function( $data ) { return $data['booking_date'] ?? ''; },
            'booking_time'     => function( $data ) { return $data['booking_time'] ?? ''; },
            'service_name'     => function( $data ) { return $data['service_name'] ?? ''; },
            'staff_name'       => function( $data ) { return $data['staff_name'] ?? ''; },
            'booking_total'    => function( $data ) { return $data['booking_total'] ?? ''; },
            'business_name'    => function( $data ) { return get_bloginfo( 'name' ); },
            'business_address' => function( $data ) { return $data['business_address'] ?? ''; },
            'booking_id'       => function( $data ) { return $data['booking_id'] ?? ''; },
            'cancel_url'       => function( $data ) { return $data['cancel_url'] ?? ''; },
            'reschedule_url'   => function( $data ) { return $data['reschedule_url'] ?? ''; },
        ] );
    }

    /**
     * Log a notification event.
     *
     * @since 1.0.0
     * @param string $message Log message.
     * @param string $level   Log level.
     * @param array  $context Additional context.
     * @return void
     */
    protected function log( string $message, string $level = 'info', array $context = [] ): void {
        $context['provider'] = $this->id;
        $context['channel']  = $this->channel;

        do_action( 'bkx_notification_log', $this->id, $message, $level, $context );
    }

    /**
     * Get settings fields for the admin form.
     *
     * @since 1.0.0
     * @return array
     */
    abstract public function get_settings_fields(): array;

    /**
     * Validate credentials.
     *
     * @since 1.0.0
     * @return array Result with 'success' bool and 'message'.
     */
    abstract public function validate_credentials(): array;
}

<?php
/**
 * Abstract Integration Base Class
 *
 * Provides the foundation for third-party integrations (CRM, Marketing, etc.)
 *
 * @package    BookingX\AddonSDK
 * @subpackage Abstracts
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Abstracts;

use BookingX\AddonSDK\Contracts\IntegrationInterface;
use BookingX\AddonSDK\Services\HttpClient;

/**
 * Abstract base class for third-party integrations.
 *
 * @since 1.0.0
 */
abstract class AbstractIntegration implements IntegrationInterface {

    /**
     * Unique integration identifier.
     *
     * @var string
     */
    protected string $id;

    /**
     * Integration display name.
     *
     * @var string
     */
    protected string $name;

    /**
     * Integration description.
     *
     * @var string
     */
    protected string $description;

    /**
     * Integration category.
     *
     * @var string
     */
    protected string $category = 'general';

    /**
     * Integration icon URL.
     *
     * @var string
     */
    protected string $icon = '';

    /**
     * Whether the integration is enabled.
     *
     * @var bool
     */
    protected bool $enabled = false;

    /**
     * Whether the integration is connected.
     *
     * @var bool
     */
    protected bool $connected = false;

    /**
     * Integration settings.
     *
     * @var array
     */
    protected array $settings = [];

    /**
     * HTTP client for API requests.
     *
     * @var HttpClient|null
     */
    protected ?HttpClient $http_client = null;

    /**
     * Supported sync entities.
     *
     * @var array
     */
    protected array $sync_entities = [
        'bookings',
        'customers',
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->load_settings();
        $this->http_client = new HttpClient( $this->get_api_base_url() );
    }

    /**
     * Load integration settings from database.
     *
     * @since 1.0.0
     * @return void
     */
    protected function load_settings(): void {
        $settings = get_option( "bkx_integration_{$this->id}_settings", [] );
        $defaults = $this->get_default_settings();
        $this->settings = wp_parse_args( $settings, $defaults );

        $this->enabled   = (bool) $this->get_setting( 'enabled', false );
        $this->connected = $this->check_connection();
    }

    /**
     * Get default settings.
     *
     * @since 1.0.0
     * @return array
     */
    protected function get_default_settings(): array {
        return [
            'enabled'          => false,
            'sync_bookings'    => true,
            'sync_customers'   => true,
            'sync_frequency'   => 'realtime', // realtime, hourly, daily
            'last_sync'        => null,
            'connection_error' => null,
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
        $this->save_settings( $this->settings );
    }

    /**
     * Save all settings.
     *
     * @since 1.0.0
     * @param array $settings Settings to save.
     * @return bool
     */
    public function save_settings( array $settings ): bool {
        $this->settings = wp_parse_args( $settings, $this->settings );
        return update_option( "bkx_integration_{$this->id}_settings", $this->settings );
    }

    /**
     * Get the integration ID.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Get the integration name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Get the integration description.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_description(): string {
        return $this->description;
    }

    /**
     * Get the integration category.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_category(): string {
        return $this->category;
    }

    /**
     * Get the integration icon.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_icon(): string {
        return $this->icon;
    }

    /**
     * Check if the integration is enabled.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->enabled;
    }

    /**
     * Check if the integration is connected.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_connected(): bool {
        return $this->connected;
    }

    /**
     * Check if the integration is available (enabled and connected).
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_available(): bool {
        return $this->is_enabled() && $this->is_connected();
    }

    /**
     * Get sync entities.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_sync_entities(): array {
        return $this->sync_entities;
    }

    /**
     * Check if an entity is syncable.
     *
     * @since 1.0.0
     * @param string $entity Entity name.
     * @return bool
     */
    public function can_sync( string $entity ): bool {
        return in_array( $entity, $this->sync_entities, true ) && $this->get_setting( "sync_{$entity}", true );
    }

    /**
     * Get the last sync time.
     *
     * @since 1.0.0
     * @param string $entity Entity name (optional).
     * @return int|null Unix timestamp or null.
     */
    public function get_last_sync( string $entity = '' ): ?int {
        $key = $entity ? "last_sync_{$entity}" : 'last_sync';
        return $this->get_setting( $key );
    }

    /**
     * Update the last sync time.
     *
     * @since 1.0.0
     * @param string $entity Entity name (optional).
     * @return void
     */
    public function update_last_sync( string $entity = '' ): void {
        $key = $entity ? "last_sync_{$entity}" : 'last_sync';
        $this->update_setting( $key, time() );
    }

    /**
     * Initialize hooks for real-time sync.
     *
     * @since 1.0.0
     * @return void
     */
    public function init_hooks(): void {
        if ( ! $this->is_available() ) {
            return;
        }

        if ( 'realtime' !== $this->get_setting( 'sync_frequency' ) ) {
            return;
        }

        // Booking hooks
        if ( $this->can_sync( 'bookings' ) ) {
            add_action( 'bkx_booking_created', [ $this, 'sync_booking_created' ], 10, 2 );
            add_action( 'bkx_booking_updated', [ $this, 'sync_booking_updated' ], 10, 2 );
            add_action( 'bkx_booking_cancelled', [ $this, 'sync_booking_cancelled' ], 10, 2 );
            add_action( 'bkx_booking_completed', [ $this, 'sync_booking_completed' ], 10, 2 );
        }

        // Customer hooks
        if ( $this->can_sync( 'customers' ) ) {
            add_action( 'bkx_customer_created', [ $this, 'sync_customer_created' ], 10, 2 );
            add_action( 'bkx_customer_updated', [ $this, 'sync_customer_updated' ], 10, 2 );
        }
    }

    /**
     * Log an integration message.
     *
     * @since 1.0.0
     * @param string $message Log message.
     * @param string $level   Log level.
     * @param array  $context Additional context.
     * @return void
     */
    protected function log( string $message, string $level = 'info', array $context = [] ): void {
        $context['integration'] = $this->id;

        do_action( 'bkx_integration_log', $this->id, $message, $level, $context );
    }

    /**
     * Handle connection error.
     *
     * @since 1.0.0
     * @param string $error Error message.
     * @return void
     */
    protected function handle_connection_error( string $error ): void {
        $this->connected = false;
        $this->update_setting( 'connection_error', $error );
        $this->log( "Connection error: {$error}", 'error' );
    }

    /**
     * Clear connection error.
     *
     * @since 1.0.0
     * @return void
     */
    protected function clear_connection_error(): void {
        $this->update_setting( 'connection_error', null );
    }

    /**
     * Get connection error message.
     *
     * @since 1.0.0
     * @return string|null
     */
    public function get_connection_error(): ?string {
        return $this->get_setting( 'connection_error' );
    }

    /**
     * Perform a batch sync.
     *
     * @since 1.0.0
     * @param string   $entity Entity to sync.
     * @param int|null $since  Unix timestamp to sync from (optional).
     * @return array Result with 'success', 'synced', 'failed', 'errors'.
     */
    public function batch_sync( string $entity, ?int $since = null ): array {
        if ( ! $this->can_sync( $entity ) ) {
            return [
                'success' => false,
                'error'   => sprintf( __( 'Sync not available for entity: %s', 'bkx-addon-sdk' ), $entity ),
            ];
        }

        $method = "batch_sync_{$entity}";
        if ( ! method_exists( $this, $method ) ) {
            return [
                'success' => false,
                'error'   => sprintf( __( 'Batch sync not implemented for entity: %s', 'bkx-addon-sdk' ), $entity ),
            ];
        }

        return $this->$method( $since );
    }

    /**
     * Get API base URL.
     *
     * @since 1.0.0
     * @return string
     */
    abstract protected function get_api_base_url(): string;

    /**
     * Check the API connection.
     *
     * @since 1.0.0
     * @return bool
     */
    abstract protected function check_connection(): bool;

    /**
     * Connect the integration.
     *
     * @since 1.0.0
     * @param array $credentials Connection credentials.
     * @return array Result with 'success' bool and 'message'.
     */
    abstract public function connect( array $credentials ): array;

    /**
     * Disconnect the integration.
     *
     * @since 1.0.0
     * @return bool
     */
    abstract public function disconnect(): bool;

    /**
     * Get settings fields for the admin form.
     *
     * @since 1.0.0
     * @return array
     */
    abstract public function get_settings_fields(): array;

    /**
     * Sync a booking creation.
     *
     * @since 1.0.0
     * @param int   $booking_id   Booking ID.
     * @param array $booking_data Booking data.
     * @return bool
     */
    abstract public function sync_booking_created( int $booking_id, array $booking_data ): bool;

    /**
     * Sync a booking update.
     *
     * @since 1.0.0
     * @param int   $booking_id   Booking ID.
     * @param array $booking_data Booking data.
     * @return bool
     */
    abstract public function sync_booking_updated( int $booking_id, array $booking_data ): bool;

    /**
     * Sync a booking cancellation.
     *
     * @since 1.0.0
     * @param int   $booking_id   Booking ID.
     * @param array $booking_data Booking data.
     * @return bool
     */
    abstract public function sync_booking_cancelled( int $booking_id, array $booking_data ): bool;

    /**
     * Sync a booking completion.
     *
     * @since 1.0.0
     * @param int   $booking_id   Booking ID.
     * @param array $booking_data Booking data.
     * @return bool
     */
    abstract public function sync_booking_completed( int $booking_id, array $booking_data ): bool;

    /**
     * Sync a customer creation.
     *
     * @since 1.0.0
     * @param int   $customer_id   Customer/User ID.
     * @param array $customer_data Customer data.
     * @return bool
     */
    abstract public function sync_customer_created( int $customer_id, array $customer_data ): bool;

    /**
     * Sync a customer update.
     *
     * @since 1.0.0
     * @param int   $customer_id   Customer/User ID.
     * @param array $customer_data Customer data.
     * @return bool
     */
    abstract public function sync_customer_updated( int $customer_id, array $customer_data ): bool;
}

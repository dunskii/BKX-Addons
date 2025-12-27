<?php
/**
 * Abstract Calendar Provider Base Class
 *
 * Provides the foundation for calendar integrations (Google, Outlook, iCal, etc.)
 *
 * @package    BookingX\AddonSDK
 * @subpackage Abstracts
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Abstracts;

use BookingX\AddonSDK\Contracts\CalendarProviderInterface;

/**
 * Abstract base class for calendar providers.
 *
 * @since 1.0.0
 */
abstract class AbstractCalendarProvider implements CalendarProviderInterface {

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
     * Whether bidirectional sync is supported.
     *
     * @var bool
     */
    protected bool $supports_bidirectional = true;

    /**
     * Supported sync directions.
     *
     * @var array
     */
    protected array $sync_directions = [ 'push', 'pull' ];

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
        $settings = get_option( "bkx_calendar_{$this->id}_settings", [] );
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
            'enabled'             => false,
            'sync_direction'      => 'push', // push, pull, both
            'sync_frequency'      => 'realtime', // realtime, 5min, 15min, hourly
            'include_details'     => true,
            'include_customer'    => false, // Privacy consideration
            'event_title_format'  => '{{service_name}} - {{customer_name}}',
            'block_external'      => true, // Block time for external calendar events
            'default_calendar_id' => '',
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
        update_option( "bkx_calendar_{$this->id}_settings", $this->settings );
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
     * Check if the provider is enabled.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->enabled;
    }

    /**
     * Check if the provider is available (enabled and connected).
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_available(): bool {
        return $this->is_enabled() && $this->is_connected();
    }

    /**
     * Check if bidirectional sync is supported.
     *
     * @since 1.0.0
     * @return bool
     */
    public function supports_bidirectional(): bool {
        return $this->supports_bidirectional;
    }

    /**
     * Get supported sync directions.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_sync_directions(): array {
        return $this->sync_directions;
    }

    /**
     * Format event title from template.
     *
     * @since 1.0.0
     * @param array $booking_data Booking data.
     * @return string
     */
    protected function format_event_title( array $booking_data ): string {
        $format = $this->get_setting( 'event_title_format', '{{service_name}} - {{customer_name}}' );

        $placeholders = [
            '{{service_name}}'    => $booking_data['service_name'] ?? '',
            '{{customer_name}}'   => $booking_data['customer_name'] ?? '',
            '{{staff_name}}'      => $booking_data['staff_name'] ?? '',
            '{{booking_id}}'      => $booking_data['booking_id'] ?? '',
            '{{booking_date}}'    => $booking_data['booking_date'] ?? '',
            '{{booking_time}}'    => $booking_data['booking_time'] ?? '',
        ];

        return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $format );
    }

    /**
     * Format event description from booking data.
     *
     * @since 1.0.0
     * @param array $booking_data Booking data.
     * @return string
     */
    protected function format_event_description( array $booking_data ): string {
        if ( ! $this->get_setting( 'include_details', true ) ) {
            return '';
        }

        $lines = [];

        $lines[] = sprintf( __( 'Service: %s', 'bkx-addon-sdk' ), $booking_data['service_name'] ?? '' );
        $lines[] = sprintf( __( 'Staff: %s', 'bkx-addon-sdk' ), $booking_data['staff_name'] ?? '' );
        $lines[] = sprintf( __( 'Duration: %s minutes', 'bkx-addon-sdk' ), $booking_data['duration'] ?? '' );

        if ( $this->get_setting( 'include_customer', false ) ) {
            $lines[] = '';
            $lines[] = sprintf( __( 'Customer: %s', 'bkx-addon-sdk' ), $booking_data['customer_name'] ?? '' );
            $lines[] = sprintf( __( 'Email: %s', 'bkx-addon-sdk' ), $booking_data['customer_email'] ?? '' );
            if ( ! empty( $booking_data['customer_phone'] ) ) {
                $lines[] = sprintf( __( 'Phone: %s', 'bkx-addon-sdk' ), $booking_data['customer_phone'] );
            }
        }

        if ( ! empty( $booking_data['notes'] ) ) {
            $lines[] = '';
            $lines[] = sprintf( __( 'Notes: %s', 'bkx-addon-sdk' ), $booking_data['notes'] );
        }

        $lines[] = '';
        $lines[] = sprintf( __( 'Booking ID: %s', 'bkx-addon-sdk' ), $booking_data['booking_id'] ?? '' );
        $lines[] = sprintf( __( 'Managed by BookingX', 'bkx-addon-sdk' ) );

        return implode( "\n", $lines );
    }

    /**
     * Convert booking data to calendar event format.
     *
     * @since 1.0.0
     * @param array $booking_data Booking data.
     * @return array Calendar event data.
     */
    protected function booking_to_event( array $booking_data ): array {
        return [
            'id'          => $booking_data['booking_id'] ?? null,
            'title'       => $this->format_event_title( $booking_data ),
            'description' => $this->format_event_description( $booking_data ),
            'start'       => $this->format_datetime( $booking_data['start_datetime'] ?? '' ),
            'end'         => $this->format_datetime( $booking_data['end_datetime'] ?? '' ),
            'location'    => $booking_data['location'] ?? '',
            'status'      => $this->map_booking_status( $booking_data['status'] ?? '' ),
            'attendees'   => $this->get_setting( 'include_customer', false ) ? [
                [
                    'email' => $booking_data['customer_email'] ?? '',
                    'name'  => $booking_data['customer_name'] ?? '',
                ],
            ] : [],
            'reminders'   => [],
            'metadata'    => [
                'bkx_booking_id' => $booking_data['booking_id'] ?? '',
                'bkx_service_id' => $booking_data['service_id'] ?? '',
                'bkx_seat_id'    => $booking_data['seat_id'] ?? '',
            ],
        ];
    }

    /**
     * Format datetime for the calendar API.
     *
     * @since 1.0.0
     * @param string $datetime DateTime string.
     * @return string ISO 8601 formatted datetime.
     */
    protected function format_datetime( string $datetime ): string {
        if ( empty( $datetime ) ) {
            return '';
        }

        try {
            $dt = new \DateTime( $datetime );
            return $dt->format( 'c' ); // ISO 8601
        } catch ( \Exception $e ) {
            return '';
        }
    }

    /**
     * Map booking status to calendar event status.
     *
     * @since 1.0.0
     * @param string $booking_status Booking status.
     * @return string Calendar event status.
     */
    protected function map_booking_status( string $booking_status ): string {
        $mapping = [
            'pending'   => 'tentative',
            'confirmed' => 'confirmed',
            'bkx-ack'   => 'confirmed',
            'completed' => 'confirmed',
            'cancelled' => 'cancelled',
            'missed'    => 'cancelled',
        ];

        return $mapping[ $booking_status ] ?? 'confirmed';
    }

    /**
     * Initialize real-time sync hooks.
     *
     * @since 1.0.0
     * @return void
     */
    public function init_hooks(): void {
        if ( ! $this->is_available() ) {
            return;
        }

        $sync_direction = $this->get_setting( 'sync_direction', 'push' );

        // Push: BookingX -> Calendar
        if ( in_array( $sync_direction, [ 'push', 'both' ], true ) ) {
            add_action( 'bkx_booking_created', [ $this, 'push_booking_created' ], 10, 2 );
            add_action( 'bkx_booking_updated', [ $this, 'push_booking_updated' ], 10, 2 );
            add_action( 'bkx_booking_cancelled', [ $this, 'push_booking_cancelled' ], 10, 2 );
        }

        // Pull: Calendar -> BookingX (for availability blocking)
        if ( in_array( $sync_direction, [ 'pull', 'both' ], true ) ) {
            add_filter( 'bkx_calendar_unavailable_days', [ $this, 'add_calendar_unavailable' ], 10, 3 );
            add_filter( 'bkx_availability_check', [ $this, 'check_calendar_availability' ], 10, 4 );
        }
    }

    /**
     * Log a calendar event.
     *
     * @since 1.0.0
     * @param string $message Log message.
     * @param string $level   Log level.
     * @param array  $context Additional context.
     * @return void
     */
    protected function log( string $message, string $level = 'info', array $context = [] ): void {
        $context['provider'] = $this->id;

        do_action( 'bkx_calendar_log', $this->id, $message, $level, $context );
    }

    /**
     * Check if the provider is connected.
     *
     * @since 1.0.0
     * @return bool
     */
    abstract public function is_connected(): bool;

    /**
     * Connect to the calendar service.
     *
     * @since 1.0.0
     * @param array $credentials OAuth credentials or API keys.
     * @return array Result with 'success' bool and 'message'.
     */
    abstract public function connect( array $credentials ): array;

    /**
     * Disconnect from the calendar service.
     *
     * @since 1.0.0
     * @return bool
     */
    abstract public function disconnect(): bool;

    /**
     * Get available calendars for the connected account.
     *
     * @since 1.0.0
     * @return array Array of calendars with 'id', 'name', 'primary'.
     */
    abstract public function get_calendars(): array;

    /**
     * Create an event in the calendar.
     *
     * @since 1.0.0
     * @param array  $event_data  Event data.
     * @param string $calendar_id Calendar ID (optional, uses default).
     * @return array Result with 'success' bool and 'event_id' or 'error'.
     */
    abstract public function create_event( array $event_data, string $calendar_id = '' ): array;

    /**
     * Update an event in the calendar.
     *
     * @since 1.0.0
     * @param string $event_id    Event ID.
     * @param array  $event_data  Event data.
     * @param string $calendar_id Calendar ID (optional).
     * @return array Result with 'success' bool.
     */
    abstract public function update_event( string $event_id, array $event_data, string $calendar_id = '' ): array;

    /**
     * Delete an event from the calendar.
     *
     * @since 1.0.0
     * @param string $event_id    Event ID.
     * @param string $calendar_id Calendar ID (optional).
     * @return array Result with 'success' bool.
     */
    abstract public function delete_event( string $event_id, string $calendar_id = '' ): array;

    /**
     * Get events within a date range.
     *
     * @since 1.0.0
     * @param string $start_date  Start date (Y-m-d).
     * @param string $end_date    End date (Y-m-d).
     * @param string $calendar_id Calendar ID (optional).
     * @return array Array of events.
     */
    abstract public function get_events( string $start_date, string $end_date, string $calendar_id = '' ): array;

    /**
     * Get busy times within a date range.
     *
     * @since 1.0.0
     * @param string $start_date  Start date (Y-m-d).
     * @param string $end_date    End date (Y-m-d).
     * @param string $calendar_id Calendar ID (optional).
     * @return array Array of busy time slots.
     */
    abstract public function get_busy_times( string $start_date, string $end_date, string $calendar_id = '' ): array;

    /**
     * Push booking creation to calendar.
     *
     * @since 1.0.0
     * @param int   $booking_id   Booking ID.
     * @param array $booking_data Booking data.
     * @return void
     */
    abstract public function push_booking_created( int $booking_id, array $booking_data ): void;

    /**
     * Push booking update to calendar.
     *
     * @since 1.0.0
     * @param int   $booking_id   Booking ID.
     * @param array $booking_data Booking data.
     * @return void
     */
    abstract public function push_booking_updated( int $booking_id, array $booking_data ): void;

    /**
     * Push booking cancellation to calendar.
     *
     * @since 1.0.0
     * @param int   $booking_id   Booking ID.
     * @param array $booking_data Booking data.
     * @return void
     */
    abstract public function push_booking_cancelled( int $booking_id, array $booking_data ): void;

    /**
     * Add calendar events to unavailable days filter.
     *
     * @since 1.0.0
     * @param array $unavailable_days Current unavailable days.
     * @param int   $seat_id          Seat/Staff ID.
     * @param array $date_range       Date range to check.
     * @return array Updated unavailable days.
     */
    abstract public function add_calendar_unavailable( array $unavailable_days, int $seat_id, array $date_range ): array;

    /**
     * Check calendar availability for a time slot.
     *
     * @since 1.0.0
     * @param bool   $is_available Current availability status.
     * @param int    $seat_id      Seat/Staff ID.
     * @param string $date         Date to check (Y-m-d).
     * @param string $time         Time to check (H:i).
     * @return bool Updated availability status.
     */
    abstract public function check_calendar_availability( bool $is_available, int $seat_id, string $date, string $time ): bool;

    /**
     * Get settings fields for the admin form.
     *
     * @since 1.0.0
     * @return array
     */
    abstract public function get_settings_fields(): array;
}

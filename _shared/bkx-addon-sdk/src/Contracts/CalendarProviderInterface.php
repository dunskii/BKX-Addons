<?php
/**
 * Calendar Provider Interface
 *
 * Contract that all calendar providers must implement.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Contracts
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Contracts;

/**
 * Interface for calendar providers.
 *
 * @since 1.0.0
 */
interface CalendarProviderInterface {

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
     * Check if the provider is enabled.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_enabled(): bool;

    /**
     * Check if the provider is connected.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_connected(): bool;

    /**
     * Check if the provider is available.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Connect to the calendar service.
     *
     * @since 1.0.0
     * @param array $credentials OAuth credentials or API keys.
     * @return array Result with 'success' bool and 'message'.
     */
    public function connect( array $credentials ): array;

    /**
     * Disconnect from the calendar service.
     *
     * @since 1.0.0
     * @return bool
     */
    public function disconnect(): bool;

    /**
     * Get available calendars.
     *
     * @since 1.0.0
     * @return array Array of calendars.
     */
    public function get_calendars(): array;

    /**
     * Create an event.
     *
     * @since 1.0.0
     * @param array  $event_data  Event data.
     * @param string $calendar_id Calendar ID.
     * @return array Result with 'success' bool and 'event_id' or 'error'.
     */
    public function create_event( array $event_data, string $calendar_id = '' ): array;

    /**
     * Update an event.
     *
     * @since 1.0.0
     * @param string $event_id    Event ID.
     * @param array  $event_data  Event data.
     * @param string $calendar_id Calendar ID.
     * @return array Result.
     */
    public function update_event( string $event_id, array $event_data, string $calendar_id = '' ): array;

    /**
     * Delete an event.
     *
     * @since 1.0.0
     * @param string $event_id    Event ID.
     * @param string $calendar_id Calendar ID.
     * @return array Result.
     */
    public function delete_event( string $event_id, string $calendar_id = '' ): array;

    /**
     * Get events within a date range.
     *
     * @since 1.0.0
     * @param string $start_date  Start date.
     * @param string $end_date    End date.
     * @param string $calendar_id Calendar ID.
     * @return array Array of events.
     */
    public function get_events( string $start_date, string $end_date, string $calendar_id = '' ): array;

    /**
     * Get busy times within a date range.
     *
     * @since 1.0.0
     * @param string $start_date  Start date.
     * @param string $end_date    End date.
     * @param string $calendar_id Calendar ID.
     * @return array Array of busy time slots.
     */
    public function get_busy_times( string $start_date, string $end_date, string $calendar_id = '' ): array;

    /**
     * Get settings fields.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_settings_fields(): array;
}

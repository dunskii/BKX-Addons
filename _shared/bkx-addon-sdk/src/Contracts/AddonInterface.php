<?php
/**
 * Addon Interface
 *
 * Contract that all add-ons must implement.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Contracts
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Contracts;

/**
 * Interface for add-ons.
 *
 * @since 1.0.0
 */
interface AddonInterface {

    /**
     * Initialize the add-on.
     *
     * @since 1.0.0
     * @return bool Whether initialization was successful.
     */
    public function init(): bool;

    /**
     * Get the add-on ID.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_id(): string;

    /**
     * Get the add-on name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_name(): string;

    /**
     * Get the add-on version.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_version(): string;

    /**
     * Get add-on info for the BookingX license system.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_addon_info(): array;

    /**
     * Check if the license is valid.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_license_valid(): bool;

    /**
     * Get database migrations.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_migrations(): array;
}

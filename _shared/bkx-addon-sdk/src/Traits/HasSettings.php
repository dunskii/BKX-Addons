<?php
/**
 * Settings Trait
 *
 * Provides common settings management functionality for add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Traits
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Traits;

/**
 * Trait for settings management.
 *
 * @since 1.0.0
 */
trait HasSettings {

    /**
     * Settings cache.
     *
     * @var array
     */
    protected array $settings_cache = [];

    /**
     * Get a setting value.
     *
     * @since 1.0.0
     * @param string $key     Setting key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_setting( string $key, $default = null ) {
        $settings = $this->get_all_settings();
        return $settings[ $key ] ?? $default;
    }

    /**
     * Update a setting value.
     *
     * @since 1.0.0
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     * @return bool
     */
    public function update_setting( string $key, $value ): bool {
        $settings = $this->get_all_settings();
        $settings[ $key ] = $value;
        return $this->save_all_settings( $settings );
    }

    /**
     * Delete a setting.
     *
     * @since 1.0.0
     * @param string $key Setting key.
     * @return bool
     */
    public function delete_setting( string $key ): bool {
        $settings = $this->get_all_settings();
        unset( $settings[ $key ] );
        return $this->save_all_settings( $settings );
    }

    /**
     * Get all settings.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_all_settings(): array {
        $option_name = $this->get_settings_option_name();

        if ( isset( $this->settings_cache[ $option_name ] ) ) {
            return $this->settings_cache[ $option_name ];
        }

        $settings = get_option( $option_name, [] );
        $defaults = $this->get_default_settings();

        $this->settings_cache[ $option_name ] = wp_parse_args( $settings, $defaults );

        return $this->settings_cache[ $option_name ];
    }

    /**
     * Save all settings.
     *
     * @since 1.0.0
     * @param array $settings Settings to save.
     * @return bool
     */
    public function save_all_settings( array $settings ): bool {
        $option_name = $this->get_settings_option_name();

        // Validate settings
        $settings = $this->validate_settings( $settings );

        // Sanitize settings
        $settings = $this->sanitize_settings( $settings );

        // Clear cache
        unset( $this->settings_cache[ $option_name ] );

        // Save
        $result = update_option( $option_name, $settings );

        if ( $result ) {
            do_action( "bkx_{$this->addon_id}_settings_saved", $settings );
        }

        return $result;
    }

    /**
     * Reset settings to defaults.
     *
     * @since 1.0.0
     * @return bool
     */
    public function reset_settings(): bool {
        $option_name = $this->get_settings_option_name();
        unset( $this->settings_cache[ $option_name ] );

        return delete_option( $option_name );
    }

    /**
     * Get the option name for settings storage.
     *
     * @since 1.0.0
     * @return string
     */
    protected function get_settings_option_name(): string {
        return "bkx_{$this->addon_id}_settings";
    }

    /**
     * Get default settings.
     *
     * Override in class to define defaults.
     *
     * @since 1.0.0
     * @return array
     */
    protected function get_default_settings(): array {
        return [];
    }

    /**
     * Validate settings.
     *
     * Override in class to add validation.
     *
     * @since 1.0.0
     * @param array $settings Settings to validate.
     * @return array Validated settings.
     */
    protected function validate_settings( array $settings ): array {
        return $settings;
    }

    /**
     * Sanitize settings.
     *
     * @since 1.0.0
     * @param array $settings Settings to sanitize.
     * @return array Sanitized settings.
     */
    protected function sanitize_settings( array $settings ): array {
        $fields    = $this->get_settings_fields();
        $sanitized = [];

        foreach ( $settings as $key => $value ) {
            $field = $fields[ $key ] ?? null;

            if ( ! $field ) {
                $sanitized[ $key ] = $value;
                continue;
            }

            $sanitized[ $key ] = $this->sanitize_field( $value, $field );
        }

        return $sanitized;
    }

    /**
     * Sanitize a single field value.
     *
     * @since 1.0.0
     * @param mixed $value Field value.
     * @param array $field Field definition.
     * @return mixed Sanitized value.
     */
    protected function sanitize_field( $value, array $field ) {
        $type = $field['type'] ?? 'text';

        switch ( $type ) {
            case 'text':
            case 'password':
                return sanitize_text_field( $value );

            case 'textarea':
                return sanitize_textarea_field( $value );

            case 'email':
                return sanitize_email( $value );

            case 'url':
                return esc_url_raw( $value );

            case 'number':
                return floatval( $value );

            case 'integer':
                return intval( $value );

            case 'checkbox':
            case 'toggle':
                return (bool) $value;

            case 'select':
            case 'radio':
                $options = $field['options'] ?? [];
                return in_array( $value, array_keys( $options ), true ) ? $value : ( $field['default'] ?? '' );

            case 'multiselect':
            case 'checkbox_group':
                if ( ! is_array( $value ) ) {
                    return [];
                }
                $options = $field['options'] ?? [];
                return array_intersect( $value, array_keys( $options ) );

            case 'color':
                return sanitize_hex_color( $value );

            case 'html':
                return wp_kses_post( $value );

            case 'json':
                if ( is_string( $value ) ) {
                    $decoded = json_decode( $value, true );
                    return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
                }
                return is_array( $value ) ? $value : [];

            case 'encrypted':
                // Will be handled by encryption service
                return $value;

            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Get settings fields.
     *
     * Override in class to define fields.
     *
     * @since 1.0.0
     * @return array
     */
    protected function get_settings_fields(): array {
        return [];
    }

    /**
     * Export settings.
     *
     * @since 1.0.0
     * @return array
     */
    public function export_settings(): array {
        $settings = $this->get_all_settings();

        // Remove sensitive fields
        $fields = $this->get_settings_fields();
        foreach ( $fields as $key => $field ) {
            if ( ! empty( $field['sensitive'] ) ) {
                unset( $settings[ $key ] );
            }
        }

        return $settings;
    }

    /**
     * Import settings.
     *
     * @since 1.0.0
     * @param array $settings Settings to import.
     * @param bool  $merge    Whether to merge with existing settings.
     * @return bool
     */
    public function import_settings( array $settings, bool $merge = true ): bool {
        if ( $merge ) {
            $current  = $this->get_all_settings();
            $settings = wp_parse_args( $settings, $current );
        }

        return $this->save_all_settings( $settings );
    }
}

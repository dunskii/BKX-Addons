<?php
/**
 * Sanitizer Utility
 *
 * Provides input sanitization methods.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Utilities
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Utilities;

/**
 * Sanitizer utility class.
 *
 * @since 1.0.0
 */
class Sanitizer {

    /**
     * Sanitize a text string.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return string
     */
    public static function text( $value ): string {
        return sanitize_text_field( $value );
    }

    /**
     * Sanitize a textarea.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return string
     */
    public static function textarea( $value ): string {
        return sanitize_textarea_field( $value );
    }

    /**
     * Sanitize an email address.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return string
     */
    public static function email( $value ): string {
        return sanitize_email( $value );
    }

    /**
     * Sanitize a URL.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return string
     */
    public static function url( $value ): string {
        return esc_url_raw( $value );
    }

    /**
     * Sanitize an integer.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return int
     */
    public static function int( $value ): int {
        return (int) $value;
    }

    /**
     * Sanitize a positive integer.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return int
     */
    public static function absint( $value ): int {
        return absint( $value );
    }

    /**
     * Sanitize a float.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return float
     */
    public static function float( $value ): float {
        return (float) $value;
    }

    /**
     * Sanitize a boolean.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return bool
     */
    public static function bool( $value ): bool {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * Sanitize HTML content.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return string
     */
    public static function html( $value ): string {
        return wp_kses_post( $value );
    }

    /**
     * Sanitize a file name.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return string
     */
    public static function filename( $value ): string {
        return sanitize_file_name( $value );
    }

    /**
     * Sanitize a key (lowercase alphanumeric with dashes/underscores).
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return string
     */
    public static function key( $value ): string {
        return sanitize_key( $value );
    }

    /**
     * Sanitize a title.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return string
     */
    public static function title( $value ): string {
        return sanitize_title( $value );
    }

    /**
     * Sanitize a hex color.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return string
     */
    public static function color( $value ): string {
        return sanitize_hex_color( $value ) ?: '';
    }

    /**
     * Sanitize a date string.
     *
     * @since 1.0.0
     * @param mixed  $value  Value to sanitize.
     * @param string $format Expected format.
     * @return string
     */
    public static function date( $value, string $format = 'Y-m-d' ): string {
        $date = \DateTime::createFromFormat( $format, $value );

        if ( ! $date ) {
            $date = \DateTime::createFromFormat( 'Y-m-d', $value );
        }

        if ( ! $date ) {
            return '';
        }

        return $date->format( $format );
    }

    /**
     * Sanitize a time string.
     *
     * @since 1.0.0
     * @param mixed  $value  Value to sanitize.
     * @param string $format Expected format.
     * @return string
     */
    public static function time( $value, string $format = 'H:i' ): string {
        $time = \DateTime::createFromFormat( $format, $value );

        if ( ! $time ) {
            $time = \DateTime::createFromFormat( 'H:i:s', $value );
        }

        if ( ! $time ) {
            $time = \DateTime::createFromFormat( 'H:i', $value );
        }

        if ( ! $time ) {
            return '';
        }

        return $time->format( $format );
    }

    /**
     * Sanitize a phone number.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return string
     */
    public static function phone( $value ): string {
        // Remove everything except digits and +
        return preg_replace( '/[^0-9+]/', '', $value );
    }

    /**
     * Sanitize an array of text values.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return array
     */
    public static function text_array( $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }

        return array_map( [ self::class, 'text' ], $value );
    }

    /**
     * Sanitize an array of integers.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return array
     */
    public static function int_array( $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }

        return array_map( 'absint', $value );
    }

    /**
     * Sanitize JSON data.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return array
     */
    public static function json( $value ): array {
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return [];
            }
            return $decoded;
        }

        return is_array( $value ) ? $value : [];
    }

    /**
     * Sanitize based on type.
     *
     * @since 1.0.0
     * @param mixed  $value Value to sanitize.
     * @param string $type  Sanitization type.
     * @return mixed
     */
    public static function by_type( $value, string $type ) {
        $method = $type;

        if ( method_exists( self::class, $method ) ) {
            return self::$method( $value );
        }

        // Fallback to text
        return self::text( $value );
    }

    /**
     * Sanitize an array of data with field types.
     *
     * @since 1.0.0
     * @param array $data   Data to sanitize.
     * @param array $fields Field types [ 'field_name' => 'type' ].
     * @return array
     */
    public static function data( array $data, array $fields ): array {
        $sanitized = [];

        foreach ( $fields as $field => $type ) {
            if ( isset( $data[ $field ] ) ) {
                $sanitized[ $field ] = self::by_type( $data[ $field ], $type );
            }
        }

        return $sanitized;
    }
}

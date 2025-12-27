<?php
/**
 * Encryption Service
 *
 * Provides encryption/decryption for sensitive data like API keys.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Services
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Services;

/**
 * Encryption service for sensitive data.
 *
 * @since 1.0.0
 */
class EncryptionService {

    /**
     * Encryption method.
     *
     * @var string
     */
    protected const METHOD = 'aes-256-cbc';

    /**
     * Get the encryption key.
     *
     * @since 1.0.0
     * @return string
     */
    protected function get_key(): string {
        // Use a dedicated constant if defined
        if ( defined( 'BKX_ENCRYPTION_KEY' ) && ! empty( BKX_ENCRYPTION_KEY ) ) {
            return BKX_ENCRYPTION_KEY;
        }

        // Fall back to a combination of WordPress keys
        if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
            return hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY );
        }

        // Last resort - use a stored key
        $stored_key = get_option( 'bkx_encryption_key' );
        if ( ! $stored_key ) {
            $stored_key = wp_generate_password( 64, true, true );
            update_option( 'bkx_encryption_key', $stored_key );
        }

        return hash( 'sha256', $stored_key );
    }

    /**
     * Encrypt a value.
     *
     * @since 1.0.0
     * @param string $value Value to encrypt.
     * @return string|false Encrypted value or false on failure.
     */
    public function encrypt( string $value ) {
        if ( empty( $value ) ) {
            return $value;
        }

        $key        = $this->get_key();
        $iv_length  = openssl_cipher_iv_length( self::METHOD );
        $iv         = openssl_random_pseudo_bytes( $iv_length );
        $encrypted  = openssl_encrypt( $value, self::METHOD, $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $encrypted ) {
            return false;
        }

        // Combine IV and encrypted data
        $result = base64_encode( $iv . $encrypted );

        return $result;
    }

    /**
     * Decrypt a value.
     *
     * @since 1.0.0
     * @param string $encrypted Encrypted value.
     * @return string|false Decrypted value or false on failure.
     */
    public function decrypt( string $encrypted ) {
        if ( empty( $encrypted ) ) {
            return $encrypted;
        }

        $key       = $this->get_key();
        $data      = base64_decode( $encrypted );
        $iv_length = openssl_cipher_iv_length( self::METHOD );

        if ( strlen( $data ) < $iv_length ) {
            return false;
        }

        $iv             = substr( $data, 0, $iv_length );
        $encrypted_data = substr( $data, $iv_length );

        $decrypted = openssl_decrypt( $encrypted_data, self::METHOD, $key, OPENSSL_RAW_DATA, $iv );

        return $decrypted;
    }

    /**
     * Check if a value is encrypted.
     *
     * @since 1.0.0
     * @param string $value Value to check.
     * @return bool
     */
    public function is_encrypted( string $value ): bool {
        if ( empty( $value ) ) {
            return false;
        }

        // Try to decode as base64
        $decoded = base64_decode( $value, true );
        if ( false === $decoded ) {
            return false;
        }

        // Check if decoded length is at least IV length
        $iv_length = openssl_cipher_iv_length( self::METHOD );

        return strlen( $decoded ) > $iv_length;
    }

    /**
     * Encrypt an array of values.
     *
     * @since 1.0.0
     * @param array $data       Array of data.
     * @param array $fields     Fields to encrypt.
     * @return array Encrypted data.
     */
    public function encrypt_fields( array $data, array $fields ): array {
        foreach ( $fields as $field ) {
            if ( isset( $data[ $field ] ) && ! empty( $data[ $field ] ) ) {
                $encrypted = $this->encrypt( $data[ $field ] );
                if ( false !== $encrypted ) {
                    $data[ $field ] = $encrypted;
                }
            }
        }

        return $data;
    }

    /**
     * Decrypt an array of values.
     *
     * @since 1.0.0
     * @param array $data   Array of data.
     * @param array $fields Fields to decrypt.
     * @return array Decrypted data.
     */
    public function decrypt_fields( array $data, array $fields ): array {
        foreach ( $fields as $field ) {
            if ( isset( $data[ $field ] ) && ! empty( $data[ $field ] ) ) {
                $decrypted = $this->decrypt( $data[ $field ] );
                if ( false !== $decrypted ) {
                    $data[ $field ] = $decrypted;
                }
            }
        }

        return $data;
    }

    /**
     * Hash a value (one-way).
     *
     * @since 1.0.0
     * @param string $value Value to hash.
     * @return string Hashed value.
     */
    public function hash( string $value ): string {
        return hash_hmac( 'sha256', $value, $this->get_key() );
    }

    /**
     * Verify a value against a hash.
     *
     * @since 1.0.0
     * @param string $value Value to verify.
     * @param string $hash  Hash to check against.
     * @return bool
     */
    public function verify_hash( string $value, string $hash ): bool {
        return hash_equals( $this->hash( $value ), $hash );
    }

    /**
     * Generate a random token.
     *
     * @since 1.0.0
     * @param int $length Token length.
     * @return string
     */
    public function generate_token( int $length = 32 ): string {
        $bytes = random_bytes( $length );
        return bin2hex( $bytes );
    }

    /**
     * Mask a sensitive value for display.
     *
     * @since 1.0.0
     * @param string $value       Value to mask.
     * @param int    $visible     Number of characters to show at start/end.
     * @param string $mask_char   Character to use for masking.
     * @return string Masked value.
     */
    public function mask( string $value, int $visible = 4, string $mask_char = '*' ): string {
        $length = strlen( $value );

        if ( $length <= $visible * 2 ) {
            return str_repeat( $mask_char, $length );
        }

        $start  = substr( $value, 0, $visible );
        $end    = substr( $value, -$visible );
        $middle = str_repeat( $mask_char, min( 10, $length - $visible * 2 ) );

        return $start . $middle . $end;
    }

    /**
     * Securely compare two strings.
     *
     * @since 1.0.0
     * @param string $a First string.
     * @param string $b Second string.
     * @return bool
     */
    public function secure_compare( string $a, string $b ): bool {
        return hash_equals( $a, $b );
    }
}

<?php
/**
 * Encryption Service for HIPAA Compliance.
 *
 * @package BookingX\HIPAA\Services
 */

namespace BookingX\HIPAA\Services;

defined( 'ABSPATH' ) || exit;

/**
 * EncryptionService class.
 *
 * Uses libsodium for HIPAA-compliant encryption.
 */
class EncryptionService {

	/**
	 * Encryption key.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$encoded_key = get_option( 'bkx_hipaa_encryption_key' );
		if ( $encoded_key ) {
			$this->key = base64_decode( $encoded_key );
		}
	}

	/**
	 * Encrypt data.
	 *
	 * @param string $plaintext Data to encrypt.
	 * @return string|false Encrypted data or false on failure.
	 */
	public function encrypt( $plaintext ) {
		if ( empty( $this->key ) || empty( $plaintext ) ) {
			return false;
		}

		try {
			// Generate a random nonce.
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

			// Encrypt the data.
			$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $this->key );

			// Combine nonce and ciphertext.
			$encrypted = $nonce . $ciphertext;

			// Clear sensitive data from memory.
			sodium_memzero( $plaintext );

			return base64_encode( $encrypted );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Decrypt data.
	 *
	 * @param string $encrypted Encrypted data.
	 * @return string|false Decrypted data or false on failure.
	 */
	public function decrypt( $encrypted ) {
		if ( empty( $this->key ) || empty( $encrypted ) ) {
			return false;
		}

		try {
			$decoded = base64_decode( $encrypted );

			if ( strlen( $decoded ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return false;
			}

			// Extract nonce and ciphertext.
			$nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

			// Decrypt.
			$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $this->key );

			if ( false === $plaintext ) {
				return false;
			}

			return $plaintext;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Generate a new encryption key.
	 *
	 * @return string Base64-encoded key.
	 */
	public function generate_key() {
		$key = sodium_crypto_secretbox_keygen();
		return base64_encode( $key );
	}

	/**
	 * Rotate encryption key.
	 *
	 * @param string $new_key New encryption key.
	 * @return bool
	 */
	public function rotate_key( $new_key ) {
		$old_key   = $this->key;
		$this->key = base64_decode( $new_key );

		// In a real implementation, you would:
		// 1. Re-encrypt all PHI with the new key.
		// 2. Update the stored key.
		// 3. Log the key rotation.

		update_option( 'bkx_hipaa_encryption_key', $new_key, false );

		// Clear old key from memory.
		if ( ! empty( $old_key ) ) {
			sodium_memzero( $old_key );
		}

		return true;
	}

	/**
	 * Hash sensitive data (one-way).
	 *
	 * @param string $data Data to hash.
	 * @return string
	 */
	public function hash( $data ) {
		return sodium_crypto_generichash( $data, $this->key );
	}

	/**
	 * Verify hash.
	 *
	 * @param string $data Data to verify.
	 * @param string $hash Expected hash.
	 * @return bool
	 */
	public function verify_hash( $data, $hash ) {
		$computed = $this->hash( $data );
		return sodium_compare( $computed, $hash ) === 0;
	}

	/**
	 * Check if encryption is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return extension_loaded( 'sodium' ) && ! empty( $this->key );
	}
}

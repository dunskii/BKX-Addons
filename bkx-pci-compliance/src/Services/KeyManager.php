<?php
/**
 * Key Manager Service.
 *
 * @package BookingX\PCICompliance
 */

namespace BookingX\PCICompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * KeyManager class.
 *
 * Manages cryptographic keys per PCI DSS Requirement 3.5 and 3.6.
 */
class KeyManager {

	/**
	 * Rotate encryption key.
	 *
	 * @param string $key_type Key type (encryption, tokenization, api).
	 * @param string $reason   Rotation reason.
	 * @return bool|\WP_Error
	 */
	public function rotate_key( $key_type, $reason = 'manual' ) {
		global $wpdb;

		$valid_types = array( 'encryption', 'tokenization', 'api' );
		if ( ! in_array( $key_type, $valid_types, true ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid key type.', 'bkx-pci-compliance' ) );
		}

		$old_key      = $this->get_key( $key_type );
		$old_key_hash = $old_key ? hash( 'sha256', $old_key ) : null;

		// Generate new key.
		$new_key = $this->generate_key( $key_type );
		if ( is_wp_error( $new_key ) ) {
			return $new_key;
		}

		// Store new key.
		$this->store_key( $key_type, $new_key );

		// Log rotation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_pci_key_rotation',
			array(
				'key_type'        => $key_type,
				'key_identifier'  => $this->get_key_identifier( $key_type ),
				'rotation_reason' => $reason,
				'old_key_hash'    => $old_key_hash,
				'new_key_hash'    => hash( 'sha256', $new_key ),
				'rotated_by'      => get_current_user_id(),
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		// Re-encrypt data if needed.
		if ( 'encryption' === $key_type && $old_key ) {
			$this->re_encrypt_data( $old_key, $new_key );
		}

		// Log audit event.
		$addon = \BookingX\PCICompliance\PCIComplianceAddon::get_instance();
		$audit = $addon->get_service( 'audit_logger' );
		if ( $audit ) {
			$audit->log(
				'key_rotation',
				'security',
				'info',
				array(
					'key_type'        => $key_type,
					'reason'          => $reason,
					'pci_requirement' => '3.6',
				)
			);
		}

		return true;
	}

	/**
	 * Generate new key.
	 *
	 * @param string $key_type Key type.
	 * @return string
	 */
	private function generate_key( $key_type ) {
		switch ( $key_type ) {
			case 'encryption':
				return sodium_crypto_secretbox_keygen();

			case 'tokenization':
				return bin2hex( random_bytes( 32 ) );

			case 'api':
				return 'bkx_' . bin2hex( random_bytes( 24 ) );

			default:
				return bin2hex( random_bytes( 32 ) );
		}
	}

	/**
	 * Get key.
	 *
	 * @param string $key_type Key type.
	 * @return string|false
	 */
	public function get_key( $key_type ) {
		$option_name = 'bkx_pci_' . $key_type . '_key';
		$key         = get_option( $option_name );

		if ( ! $key ) {
			return false;
		}

		// Keys are stored base64 encoded.
		return base64_decode( $key );
	}

	/**
	 * Store key.
	 *
	 * @param string $key_type Key type.
	 * @param string $key      Key value.
	 */
	private function store_key( $key_type, $key ) {
		$option_name = 'bkx_pci_' . $key_type . '_key';
		update_option( $option_name, base64_encode( $key ) );
	}

	/**
	 * Get key identifier.
	 *
	 * @param string $key_type Key type.
	 * @return string
	 */
	private function get_key_identifier( $key_type ) {
		return $key_type . '_' . gmdate( 'Y-m-d_His' );
	}

	/**
	 * Re-encrypt data with new key.
	 *
	 * @param string $old_key Old encryption key.
	 * @param string $new_key New encryption key.
	 * @return int Number of records re-encrypted.
	 */
	private function re_encrypt_data( $old_key, $new_key ) {
		// This would re-encrypt any stored encrypted data.
		// Implementation depends on what data is encrypted.
		// For now, return 0 as we discourage storing card data.
		return 0;
	}

	/**
	 * Get key rotation history.
	 *
	 * @param string $key_type Key type (optional).
	 * @param int    $limit    Limit.
	 * @return array
	 */
	public function get_rotation_history( $key_type = null, $limit = 50 ) {
		global $wpdb;

		if ( $key_type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}bkx_pci_key_rotation
					WHERE key_type = %s
					ORDER BY created_at DESC
					LIMIT %d",
					$key_type,
					$limit
				),
				ARRAY_A
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_pci_key_rotation
				ORDER BY created_at DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get last rotation date.
	 *
	 * @param string $key_type Key type.
	 * @return string|null
	 */
	public function get_last_rotation( $key_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT created_at FROM {$wpdb->prefix}bkx_pci_key_rotation
				WHERE key_type = %s
				ORDER BY created_at DESC
				LIMIT 1",
				$key_type
			)
		);
	}

	/**
	 * Check if key needs rotation.
	 *
	 * @param string $key_type      Key type.
	 * @param int    $max_age_days  Maximum age in days before rotation needed.
	 * @return bool
	 */
	public function needs_rotation( $key_type, $max_age_days = 365 ) {
		$last_rotation = $this->get_last_rotation( $key_type );

		if ( ! $last_rotation ) {
			return true;
		}

		$days_since = floor( ( time() - strtotime( $last_rotation ) ) / DAY_IN_SECONDS );

		return $days_since >= $max_age_days;
	}

	/**
	 * Initialize keys if not exist.
	 */
	public function initialize_keys() {
		$key_types = array( 'encryption', 'tokenization', 'api' );

		foreach ( $key_types as $type ) {
			$key = $this->get_key( $type );
			if ( ! $key ) {
				$new_key = $this->generate_key( $type );
				$this->store_key( $type, $new_key );
			}
		}
	}

	/**
	 * Get key status.
	 *
	 * @return array
	 */
	public function get_key_status() {
		$key_types = array( 'encryption', 'tokenization', 'api' );
		$status    = array();

		foreach ( $key_types as $type ) {
			$key           = $this->get_key( $type );
			$last_rotation = $this->get_last_rotation( $type );

			$status[ $type ] = array(
				'exists'        => ! empty( $key ),
				'last_rotation' => $last_rotation,
				'needs_rotation' => $this->needs_rotation( $type ),
				'days_since'    => $last_rotation
					? floor( ( time() - strtotime( $last_rotation ) ) / DAY_IN_SECONDS )
					: null,
			);
		}

		return $status;
	}
}

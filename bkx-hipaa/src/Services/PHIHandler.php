<?php
/**
 * PHI Handler Service for HIPAA Compliance.
 *
 * @package BookingX\HIPAA\Services
 */

namespace BookingX\HIPAA\Services;

defined( 'ABSPATH' ) || exit;

/**
 * PHIHandler class.
 */
class PHIHandler {

	/**
	 * Encryption service.
	 *
	 * @var EncryptionService
	 */
	private $encryption;

	/**
	 * PHI fields.
	 *
	 * @var array
	 */
	private $phi_fields;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$addon           = \BookingX\HIPAA\HIPAAAddon::get_instance();
		$this->encryption = $addon->get_service( 'encryption' );

		$settings         = get_option( 'bkx_hipaa_settings', array() );
		$this->phi_fields = isset( $settings['phi_fields'] ) ? $settings['phi_fields'] : array(
			'customer_email',
			'customer_phone',
			'customer_name',
			'booking_notes',
		);
	}

	/**
	 * Encrypt PHI fields in data array.
	 *
	 * @param array $data Data to encrypt.
	 * @return array
	 */
	public function encrypt_fields( $data ) {
		foreach ( $this->phi_fields as $field ) {
			if ( isset( $data[ $field ] ) && ! empty( $data[ $field ] ) ) {
				$encrypted = $this->encryption->encrypt( $data[ $field ] );
				if ( $encrypted ) {
					$data[ $field ]           = $encrypted;
					$data[ $field . '_hash' ] = $this->encryption->hash( $data[ $field ] );
				}
			}
		}

		return $data;
	}

	/**
	 * Decrypt PHI fields in data array.
	 *
	 * @param array $data Data to decrypt.
	 * @return array
	 */
	public function decrypt_fields( $data ) {
		foreach ( $this->phi_fields as $field ) {
			if ( isset( $data[ $field ] ) && ! empty( $data[ $field ] ) ) {
				$decrypted = $this->encryption->decrypt( $data[ $field ] );
				if ( $decrypted ) {
					$data[ $field ] = $decrypted;
				}
			}
		}

		return $data;
	}

	/**
	 * Redact PHI fields.
	 *
	 * @param array $data Data to redact.
	 * @return array
	 */
	public function redact_phi( $data ) {
		foreach ( $this->phi_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = $this->redact_value( $data[ $field ], $field );
			}
		}

		return $data;
	}

	/**
	 * Redact a value.
	 *
	 * @param string $value Value to redact.
	 * @param string $field Field name.
	 * @return string
	 */
	private function redact_value( $value, $field ) {
		if ( empty( $value ) ) {
			return $value;
		}

		switch ( $field ) {
			case 'customer_email':
				return $this->redact_email( $value );

			case 'customer_phone':
				return $this->redact_phone( $value );

			case 'customer_name':
				return $this->redact_name( $value );

			default:
				return '[REDACTED]';
		}
	}

	/**
	 * Redact email address.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private function redact_email( $email ) {
		$parts = explode( '@', $email );
		if ( count( $parts ) !== 2 ) {
			return '[REDACTED]';
		}

		$local  = $parts[0];
		$domain = $parts[1];

		if ( strlen( $local ) <= 2 ) {
			$redacted_local = str_repeat( '*', strlen( $local ) );
		} else {
			$redacted_local = substr( $local, 0, 1 ) . str_repeat( '*', strlen( $local ) - 2 ) . substr( $local, -1 );
		}

		return $redacted_local . '@' . $domain;
	}

	/**
	 * Redact phone number.
	 *
	 * @param string $phone Phone number.
	 * @return string
	 */
	private function redact_phone( $phone ) {
		$digits = preg_replace( '/[^0-9]/', '', $phone );

		if ( strlen( $digits ) < 4 ) {
			return '[REDACTED]';
		}

		return str_repeat( '*', strlen( $digits ) - 4 ) . substr( $digits, -4 );
	}

	/**
	 * Redact name.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private function redact_name( $name ) {
		$parts   = explode( ' ', $name );
		$redacted = array();

		foreach ( $parts as $part ) {
			if ( strlen( $part ) <= 1 ) {
				$redacted[] = '*';
			} else {
				$redacted[] = substr( $part, 0, 1 ) . str_repeat( '*', strlen( $part ) - 1 );
			}
		}

		return implode( ' ', $redacted );
	}

	/**
	 * Check if data contains PHI.
	 *
	 * @param array $data Data to check.
	 * @return bool
	 */
	public function contains_phi( $data ) {
		foreach ( $this->phi_fields as $field ) {
			if ( isset( $data[ $field ] ) && ! empty( $data[ $field ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get PHI fields.
	 *
	 * @return array
	 */
	public function get_phi_fields() {
		return $this->phi_fields;
	}

	/**
	 * Set PHI fields.
	 *
	 * @param array $fields PHI fields.
	 */
	public function set_phi_fields( $fields ) {
		$this->phi_fields = $fields;
	}

	/**
	 * Get default PHI fields.
	 *
	 * @return array
	 */
	public static function get_default_phi_fields() {
		return array(
			'customer_email'   => __( 'Customer Email', 'bkx-hipaa' ),
			'customer_phone'   => __( 'Customer Phone', 'bkx-hipaa' ),
			'customer_name'    => __( 'Customer Name', 'bkx-hipaa' ),
			'customer_address' => __( 'Customer Address', 'bkx-hipaa' ),
			'customer_dob'     => __( 'Date of Birth', 'bkx-hipaa' ),
			'customer_ssn'     => __( 'Social Security Number', 'bkx-hipaa' ),
			'booking_notes'    => __( 'Booking Notes', 'bkx-hipaa' ),
			'medical_notes'    => __( 'Medical Notes', 'bkx-hipaa' ),
			'insurance_id'     => __( 'Insurance ID', 'bkx-hipaa' ),
			'diagnosis_codes'  => __( 'Diagnosis Codes', 'bkx-hipaa' ),
		);
	}
}

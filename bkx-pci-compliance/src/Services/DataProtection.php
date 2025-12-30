<?php
/**
 * Data Protection Service.
 *
 * @package BookingX\PCICompliance
 */

namespace BookingX\PCICompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DataProtection class.
 *
 * Handles cardholder data protection per PCI DSS Requirements 3 and 4.
 */
class DataProtection {

	/**
	 * Mask patterns for different data types.
	 *
	 * @var array
	 */
	private $mask_patterns = array(
		'card_number' => '/\b(\d{4})\d{8,12}(\d{4})\b/',
		'cvv'         => '/\b\d{3,4}\b/',
		'expiry'      => '/\b(\d{2})\/(\d{2,4})\b/',
		'ssn'         => '/\b(\d{3})-?(\d{2})-?(\d{4})\b/',
		'email'       => '/([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/',
		'phone'       => '/\b(\d{3})[-.]?(\d{3})[-.]?(\d{4})\b/',
	);

	/**
	 * Log data access.
	 *
	 * @param string $data_type     Data type being accessed.
	 * @param string $access_type   Type of access (view, export, modify, delete).
	 * @param int    $booking_id    Related booking ID.
	 * @param string $justification Reason for access.
	 * @return int|false
	 */
	public function log_data_access( $data_type, $access_type, $booking_id = null, $justification = '' ) {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_pci_data_access',
			array(
				'user_id'       => $user_id,
				'data_type'     => sanitize_key( $data_type ),
				'access_type'   => sanitize_key( $access_type ),
				'booking_id'    => $booking_id ? absint( $booking_id ) : null,
				'justification' => sanitize_text_field( $justification ),
				'ip_address'    => $this->get_client_ip(),
				'authorized'    => 1,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Mask sensitive data.
	 *
	 * @param array $data Data to mask.
	 * @return array
	 */
	public function mask_data( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$sensitive_fields = array(
			'card_number',
			'credit_card',
			'cc_number',
			'pan',
			'cvv',
			'cvc',
			'cvv2',
			'expiry',
			'exp_date',
			'cardholder',
			'card_holder',
			'ssn',
			'social_security',
		);

		foreach ( $data as $key => $value ) {
			$lower_key = strtolower( $key );

			// Check if field is sensitive.
			foreach ( $sensitive_fields as $field ) {
				if ( strpos( $lower_key, $field ) !== false ) {
					$data[ $key ] = $this->mask_value( $value, $field );
					break;
				}
			}

			// Recurse into arrays.
			if ( is_array( $value ) ) {
				$data[ $key ] = $this->mask_data( $value );
			}
		}

		return $data;
	}

	/**
	 * Mask a specific value.
	 *
	 * @param string $value Value to mask.
	 * @param string $type  Data type.
	 * @return string
	 */
	private function mask_value( $value, $type ) {
		if ( empty( $value ) ) {
			return $value;
		}

		switch ( $type ) {
			case 'card_number':
			case 'credit_card':
			case 'cc_number':
			case 'pan':
				// Show first 6 and last 4 digits (PCI allows this).
				$value = preg_replace( '/[^0-9]/', '', $value );
				if ( strlen( $value ) >= 13 ) {
					return substr( $value, 0, 6 ) . str_repeat( '*', strlen( $value ) - 10 ) . substr( $value, -4 );
				}
				return str_repeat( '*', strlen( $value ) );

			case 'cvv':
			case 'cvc':
			case 'cvv2':
				return '***';

			case 'expiry':
			case 'exp_date':
				return '**/**';

			case 'cardholder':
			case 'card_holder':
				return '[REDACTED]';

			case 'ssn':
			case 'social_security':
				return '***-**-' . substr( preg_replace( '/[^0-9]/', '', $value ), -4 );

			default:
				return '[MASKED]';
		}
	}

	/**
	 * Mask card number for display.
	 *
	 * @param string $card_number Full card number.
	 * @return string
	 */
	public function mask_card_number( $card_number ) {
		$card_number = preg_replace( '/[^0-9]/', '', $card_number );
		$length      = strlen( $card_number );

		if ( $length < 13 ) {
			return str_repeat( '*', $length );
		}

		return substr( $card_number, 0, 6 ) . str_repeat( '*', $length - 10 ) . substr( $card_number, -4 );
	}

	/**
	 * Get card type from number.
	 *
	 * @param string $card_number Card number.
	 * @return string
	 */
	public function get_card_type( $card_number ) {
		$card_number = preg_replace( '/[^0-9]/', '', $card_number );

		$patterns = array(
			'visa'       => '/^4[0-9]{12}(?:[0-9]{3})?$/',
			'mastercard' => '/^5[1-5][0-9]{14}$/',
			'amex'       => '/^3[47][0-9]{13}$/',
			'discover'   => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
			'diners'     => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
			'jcb'        => '/^(?:2131|1800|35\d{3})\d{11}$/',
		);

		foreach ( $patterns as $type => $pattern ) {
			if ( preg_match( $pattern, $card_number ) ) {
				return $type;
			}
		}

		return 'unknown';
	}

	/**
	 * Validate card number using Luhn algorithm.
	 *
	 * @param string $card_number Card number.
	 * @return bool
	 */
	public function validate_card_number( $card_number ) {
		$card_number = preg_replace( '/[^0-9]/', '', $card_number );
		$length      = strlen( $card_number );

		if ( $length < 13 || $length > 19 ) {
			return false;
		}

		$sum = 0;
		$alt = false;

		for ( $i = $length - 1; $i >= 0; $i-- ) {
			$n = intval( $card_number[ $i ] );
			if ( $alt ) {
				$n *= 2;
				if ( $n > 9 ) {
					$n -= 9;
				}
			}
			$sum += $n;
			$alt = ! $alt;
		}

		return ( $sum % 10 === 0 );
	}

	/**
	 * Encrypt sensitive data.
	 *
	 * @param string $data Data to encrypt.
	 * @return string|false
	 */
	public function encrypt( $data ) {
		if ( empty( $data ) ) {
			return $data;
		}

		$key = $this->get_encryption_key();
		if ( ! $key ) {
			return false;
		}

		$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = sodium_crypto_secretbox( $data, $nonce, $key );

		return base64_encode( $nonce . $ciphertext );
	}

	/**
	 * Decrypt sensitive data.
	 *
	 * @param string $encrypted Encrypted data.
	 * @return string|false
	 */
	public function decrypt( $encrypted ) {
		if ( empty( $encrypted ) ) {
			return $encrypted;
		}

		$key = $this->get_encryption_key();
		if ( ! $key ) {
			return false;
		}

		$decoded = base64_decode( $encrypted );
		if ( strlen( $decoded ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return false;
		}

		$nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

		return $plaintext !== false ? $plaintext : false;
	}

	/**
	 * Get encryption key.
	 *
	 * @return string|false
	 */
	private function get_encryption_key() {
		$key = get_option( 'bkx_pci_encryption_key' );

		if ( ! $key ) {
			// Generate new key.
			$key = sodium_crypto_secretbox_keygen();
			update_option( 'bkx_pci_encryption_key', base64_encode( $key ) );
			return $key;
		}

		return base64_decode( $key );
	}

	/**
	 * Securely delete data.
	 *
	 * @param string $data Data to securely delete.
	 * @return void
	 */
	public function secure_delete( &$data ) {
		if ( is_string( $data ) ) {
			$length = strlen( $data );
			for ( $i = 0; $i < $length; $i++ ) {
				$data[ $i ] = chr( random_int( 0, 255 ) );
			}
		}
		$data = null;
	}

	/**
	 * Check if cardholder data storage is allowed.
	 *
	 * @return bool
	 */
	public function is_storage_allowed() {
		$settings = get_option( 'bkx_pci_compliance_settings', array() );
		return ! empty( $settings['card_data_storage'] ) && 'none' !== $settings['card_data_storage'];
	}

	/**
	 * Get data access log.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_access_log( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page'  => 50,
			'page'      => 1,
			'user_id'   => null,
			'data_type' => null,
			'date_from' => null,
			'date_to'   => null,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array( '1=1' );
		$values = array();

		if ( $args['user_id'] ) {
			$where[]  = 'user_id = %d';
			$values[] = absint( $args['user_id'] );
		}

		if ( $args['data_type'] ) {
			$where[]  = 'data_type = %s';
			$values[] = sanitize_key( $args['data_type'] );
		}

		if ( $args['date_from'] ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}

		if ( $args['date_to'] ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );
		$offset       = ( $args['page'] - 1 ) * $args['per_page'];

		$query    = "SELECT * FROM {$wpdb->prefix}bkx_pci_data_access WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$values[] = $args['per_page'];
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$logs = $wpdb->get_results( $wpdb->prepare( $query, $values ), ARRAY_A );

		// Get total.
		$count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}bkx_pci_data_access WHERE {$where_clause}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$total = $wpdb->get_var( $wpdb->prepare( $count_query, array_slice( $values, 0, -2 ) ) );

		return array(
			'logs'  => $logs,
			'total' => (int) $total,
			'pages' => ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * Get client IP.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( $_SERVER[ $header ] );
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}

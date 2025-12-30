<?php
/**
 * Apple Authentication Service.
 *
 * Handles JWT token verification for Apple/SiriKit requests.
 *
 * @package BookingX\AppleSiri
 */

namespace BookingX\AppleSiri\Services;

defined( 'ABSPATH' ) || exit;

/**
 * AppleAuthService class.
 */
class AppleAuthService {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\AppleSiri\AppleSiriAddon
	 */
	private $addon;

	/**
	 * Apple public keys cache.
	 *
	 * @var array
	 */
	private $apple_keys = array();

	/**
	 * Constructor.
	 *
	 * @param \BookingX\AppleSiri\AppleSiriAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Verify JWT token from Apple.
	 *
	 * @param string $auth_header Authorization header value.
	 * @return bool
	 */
	public function verify_token( $auth_header ) {
		// Extract token from Bearer header.
		if ( strpos( $auth_header, 'Bearer ' ) !== 0 ) {
			$this->log_error( 'Invalid authorization header format' );
			return false;
		}

		$token = substr( $auth_header, 7 );

		if ( empty( $token ) ) {
			$this->log_error( 'Empty token' );
			return false;
		}

		// Decode and verify JWT.
		$decoded = $this->decode_jwt( $token );

		if ( ! $decoded ) {
			return false;
		}

		// Verify claims.
		return $this->verify_claims( $decoded );
	}

	/**
	 * Decode JWT token.
	 *
	 * @param string $token JWT token.
	 * @return array|false Decoded payload or false on failure.
	 */
	private function decode_jwt( $token ) {
		$parts = explode( '.', $token );

		if ( count( $parts ) !== 3 ) {
			$this->log_error( 'Invalid JWT format' );
			return false;
		}

		list( $header_b64, $payload_b64, $signature_b64 ) = $parts;

		// Decode header.
		$header = $this->base64url_decode( $header_b64 );
		if ( ! $header ) {
			$this->log_error( 'Failed to decode JWT header' );
			return false;
		}

		$header = json_decode( $header, true );
		if ( ! $header ) {
			$this->log_error( 'Failed to parse JWT header' );
			return false;
		}

		// Decode payload.
		$payload = $this->base64url_decode( $payload_b64 );
		if ( ! $payload ) {
			$this->log_error( 'Failed to decode JWT payload' );
			return false;
		}

		$payload = json_decode( $payload, true );
		if ( ! $payload ) {
			$this->log_error( 'Failed to parse JWT payload' );
			return false;
		}

		// Verify signature.
		if ( ! $this->verify_signature( $header_b64 . '.' . $payload_b64, $signature_b64, $header ) ) {
			$this->log_error( 'JWT signature verification failed' );
			return false;
		}

		return array(
			'header'  => $header,
			'payload' => $payload,
		);
	}

	/**
	 * Verify JWT signature.
	 *
	 * @param string $data      Data to verify.
	 * @param string $signature Signature.
	 * @param array  $header    JWT header.
	 * @return bool
	 */
	private function verify_signature( $data, $signature, $header ) {
		$alg = $header['alg'] ?? '';
		$kid = $header['kid'] ?? '';

		if ( ! in_array( $alg, array( 'RS256', 'ES256' ), true ) ) {
			$this->log_error( 'Unsupported algorithm: ' . $alg );
			return false;
		}

		// Get Apple public key.
		$public_key = $this->get_apple_public_key( $kid );

		if ( ! $public_key ) {
			$this->log_error( 'Failed to get Apple public key' );
			return false;
		}

		$signature = $this->base64url_decode( $signature );

		if ( 'RS256' === $alg ) {
			return openssl_verify( $data, $signature, $public_key, OPENSSL_ALGO_SHA256 ) === 1;
		}

		if ( 'ES256' === $alg ) {
			// Convert DER signature to P1363 format for ES256.
			$signature = $this->signature_from_der( $signature );
			return openssl_verify( $data, $signature, $public_key, OPENSSL_ALGO_SHA256 ) === 1;
		}

		return false;
	}

	/**
	 * Get Apple public key by key ID.
	 *
	 * @param string $kid Key ID.
	 * @return resource|false OpenSSL key resource or false.
	 */
	private function get_apple_public_key( $kid ) {
		// Check cache.
		$cached = get_transient( 'bkx_apple_siri_keys' );
		if ( $cached ) {
			$this->apple_keys = $cached;
		}

		// Find key by kid.
		foreach ( $this->apple_keys as $key ) {
			if ( ( $key['kid'] ?? '' ) === $kid ) {
				return $this->jwk_to_pem( $key );
			}
		}

		// Fetch fresh keys.
		$keys = $this->fetch_apple_keys();

		if ( ! $keys ) {
			return false;
		}

		$this->apple_keys = $keys;
		set_transient( 'bkx_apple_siri_keys', $keys, HOUR_IN_SECONDS );

		// Find key by kid.
		foreach ( $keys as $key ) {
			if ( ( $key['kid'] ?? '' ) === $kid ) {
				return $this->jwk_to_pem( $key );
			}
		}

		return false;
	}

	/**
	 * Fetch Apple public keys.
	 *
	 * @return array|false
	 */
	private function fetch_apple_keys() {
		$response = wp_remote_get(
			'https://appleid.apple.com/auth/keys',
			array(
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Failed to fetch Apple keys: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['keys'] ) || ! is_array( $data['keys'] ) ) {
			$this->log_error( 'Invalid Apple keys response' );
			return false;
		}

		return $data['keys'];
	}

	/**
	 * Convert JWK to PEM format.
	 *
	 * @param array $jwk JWK key data.
	 * @return resource|false OpenSSL key resource or false.
	 */
	private function jwk_to_pem( $jwk ) {
		$kty = $jwk['kty'] ?? '';

		if ( 'RSA' === $kty ) {
			return $this->rsa_jwk_to_pem( $jwk );
		}

		if ( 'EC' === $kty ) {
			return $this->ec_jwk_to_pem( $jwk );
		}

		return false;
	}

	/**
	 * Convert RSA JWK to PEM.
	 *
	 * @param array $jwk JWK key data.
	 * @return resource|false
	 */
	private function rsa_jwk_to_pem( $jwk ) {
		if ( ! isset( $jwk['n'], $jwk['e'] ) ) {
			return false;
		}

		$modulus  = $this->base64url_decode( $jwk['n'] );
		$exponent = $this->base64url_decode( $jwk['e'] );

		// Build DER sequence for RSA public key.
		$modulus_int  = pack( 'Ca*a*', 0x02, $this->encode_length( strlen( $modulus ) + 1 ), "\x00" . $modulus );
		$exponent_int = pack( 'Ca*a*', 0x02, $this->encode_length( strlen( $exponent ) ), $exponent );

		$rsa_public_key = pack( 'Ca*a*', 0x30, $this->encode_length( strlen( $modulus_int . $exponent_int ) ), $modulus_int . $exponent_int );

		// RSA algorithm identifier.
		$algorithm_id = pack( 'H*', '300D06092A864886F70D0101010500' );

		// Bit string wrapper.
		$bit_string = pack( 'Ca*a*', 0x03, $this->encode_length( strlen( $rsa_public_key ) + 1 ), "\x00" . $rsa_public_key );

		// Complete SubjectPublicKeyInfo.
		$public_key_info = pack( 'Ca*a*', 0x30, $this->encode_length( strlen( $algorithm_id . $bit_string ) ), $algorithm_id . $bit_string );

		$pem = "-----BEGIN PUBLIC KEY-----\n" .
			chunk_split( base64_encode( $public_key_info ), 64, "\n" ) .
			"-----END PUBLIC KEY-----\n";

		return openssl_pkey_get_public( $pem );
	}

	/**
	 * Convert EC JWK to PEM.
	 *
	 * @param array $jwk JWK key data.
	 * @return resource|false
	 */
	private function ec_jwk_to_pem( $jwk ) {
		if ( ! isset( $jwk['x'], $jwk['y'] ) ) {
			return false;
		}

		$x = $this->base64url_decode( $jwk['x'] );
		$y = $this->base64url_decode( $jwk['y'] );

		// Uncompressed point format: 0x04 || x || y.
		$point = "\x04" . str_pad( $x, 32, "\x00", STR_PAD_LEFT ) . str_pad( $y, 32, "\x00", STR_PAD_LEFT );

		// EC P-256 algorithm identifier.
		$algorithm_id = pack( 'H*', '301306072A8648CE3D020106082A8648CE3D030107' );

		// Bit string wrapper.
		$bit_string = pack( 'Ca*a*', 0x03, $this->encode_length( strlen( $point ) + 1 ), "\x00" . $point );

		// Complete SubjectPublicKeyInfo.
		$public_key_info = pack( 'Ca*a*', 0x30, $this->encode_length( strlen( $algorithm_id . $bit_string ) ), $algorithm_id . $bit_string );

		$pem = "-----BEGIN PUBLIC KEY-----\n" .
			chunk_split( base64_encode( $public_key_info ), 64, "\n" ) .
			"-----END PUBLIC KEY-----\n";

		return openssl_pkey_get_public( $pem );
	}

	/**
	 * Encode ASN.1 length.
	 *
	 * @param int $length Length value.
	 * @return string
	 */
	private function encode_length( $length ) {
		if ( $length <= 0x7F ) {
			return chr( $length );
		}

		$temp = ltrim( pack( 'N', $length ), "\x00" );
		return pack( 'Ca*', 0x80 | strlen( $temp ), $temp );
	}

	/**
	 * Convert DER signature to P1363 format.
	 *
	 * @param string $der DER-encoded signature.
	 * @return string P1363 format signature.
	 */
	private function signature_from_der( $der ) {
		$pos = 0;

		if ( ord( $der[ $pos++ ] ) !== 0x30 ) {
			return $der;
		}

		// Read length.
		$this->read_length( $der, $pos );

		// Read R.
		if ( ord( $der[ $pos++ ] ) !== 0x02 ) {
			return $der;
		}

		$r_len = $this->read_length( $der, $pos );
		$r     = substr( $der, $pos, $r_len );
		$pos  += $r_len;

		// Read S.
		if ( ord( $der[ $pos++ ] ) !== 0x02 ) {
			return $der;
		}

		$s_len = $this->read_length( $der, $pos );
		$s     = substr( $der, $pos, $s_len );

		// Remove leading zeros and pad to 32 bytes.
		$r = ltrim( $r, "\x00" );
		$s = ltrim( $s, "\x00" );

		$r = str_pad( $r, 32, "\x00", STR_PAD_LEFT );
		$s = str_pad( $s, 32, "\x00", STR_PAD_LEFT );

		return $r . $s;
	}

	/**
	 * Read ASN.1 length.
	 *
	 * @param string $data Data.
	 * @param int    $pos  Position (modified by reference).
	 * @return int Length value.
	 */
	private function read_length( $data, &$pos ) {
		$byte = ord( $data[ $pos++ ] );

		if ( $byte < 0x80 ) {
			return $byte;
		}

		$num_bytes = $byte & 0x7F;
		$length    = 0;

		for ( $i = 0; $i < $num_bytes; $i++ ) {
			$length = ( $length << 8 ) | ord( $data[ $pos++ ] );
		}

		return $length;
	}

	/**
	 * Verify JWT claims.
	 *
	 * @param array $decoded Decoded JWT data.
	 * @return bool
	 */
	private function verify_claims( $decoded ) {
		$payload = $decoded['payload'];

		// Check expiration.
		if ( isset( $payload['exp'] ) && $payload['exp'] < time() ) {
			$this->log_error( 'Token expired' );
			return false;
		}

		// Check not before.
		if ( isset( $payload['nbf'] ) && $payload['nbf'] > time() ) {
			$this->log_error( 'Token not yet valid' );
			return false;
		}

		// Check issued at (allow 5 minute clock skew).
		if ( isset( $payload['iat'] ) && $payload['iat'] > time() + 300 ) {
			$this->log_error( 'Token issued in the future' );
			return false;
		}

		// Verify issuer.
		$expected_issuer = 'https://appleid.apple.com';
		if ( isset( $payload['iss'] ) && $payload['iss'] !== $expected_issuer ) {
			$this->log_error( 'Invalid issuer' );
			return false;
		}

		// Verify audience (bundle identifier).
		$bundle_id = $this->addon->get_setting( 'bundle_identifier' );
		if ( ! empty( $bundle_id ) && isset( $payload['aud'] ) && $payload['aud'] !== $bundle_id ) {
			$this->log_error( 'Invalid audience' );
			return false;
		}

		return true;
	}

	/**
	 * Base64 URL decode.
	 *
	 * @param string $input Base64 URL encoded string.
	 * @return string|false
	 */
	private function base64url_decode( $input ) {
		$remainder = strlen( $input ) % 4;

		if ( $remainder ) {
			$input .= str_repeat( '=', 4 - $remainder );
		}

		return base64_decode( strtr( $input, '-_', '+/' ) );
	}

	/**
	 * Generate client secret JWT.
	 *
	 * @return string|false JWT token or false on failure.
	 */
	public function generate_client_secret() {
		$team_id     = $this->addon->get_setting( 'team_id' );
		$key_id      = $this->addon->get_setting( 'key_id' );
		$private_key = $this->addon->get_setting( 'private_key' );
		$bundle_id   = $this->addon->get_setting( 'bundle_identifier' );

		if ( empty( $team_id ) || empty( $key_id ) || empty( $private_key ) || empty( $bundle_id ) ) {
			$this->log_error( 'Missing required settings for client secret generation' );
			return false;
		}

		$header = array(
			'alg' => 'ES256',
			'kid' => $key_id,
		);

		$now = time();

		$payload = array(
			'iss' => $team_id,
			'iat' => $now,
			'exp' => $now + ( 6 * MONTH_IN_SECONDS ),
			'aud' => 'https://appleid.apple.com',
			'sub' => $bundle_id,
		);

		$header_encoded  = $this->base64url_encode( wp_json_encode( $header ) );
		$payload_encoded = $this->base64url_encode( wp_json_encode( $payload ) );

		$data = $header_encoded . '.' . $payload_encoded;

		// Sign with private key.
		$key = openssl_pkey_get_private( $private_key );

		if ( ! $key ) {
			$this->log_error( 'Failed to load private key' );
			return false;
		}

		$signature = '';
		if ( ! openssl_sign( $data, $signature, $key, OPENSSL_ALGO_SHA256 ) ) {
			$this->log_error( 'Failed to sign JWT' );
			return false;
		}

		// Convert signature to P1363 format for ES256.
		$signature = $this->signature_to_p1363( $signature );

		return $data . '.' . $this->base64url_encode( $signature );
	}

	/**
	 * Convert OpenSSL signature to P1363 format.
	 *
	 * @param string $signature DER-encoded signature.
	 * @return string P1363 format signature.
	 */
	private function signature_to_p1363( $signature ) {
		return $this->signature_from_der( $signature );
	}

	/**
	 * Base64 URL encode.
	 *
	 * @param string $input Input string.
	 * @return string
	 */
	private function base64url_encode( $input ) {
		return rtrim( strtr( base64_encode( $input ), '+/', '-_' ), '=' );
	}

	/**
	 * Test connection to Apple services.
	 *
	 * @return array
	 */
	public function test_connection() {
		// Check if required settings are present.
		$required = array( 'team_id', 'key_id', 'private_key', 'bundle_identifier' );
		$missing  = array();

		foreach ( $required as $key ) {
			if ( empty( $this->addon->get_setting( $key ) ) ) {
				$missing[] = $key;
			}
		}

		if ( ! empty( $missing ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Missing settings */
					__( 'Missing required settings: %s', 'bkx-apple-siri' ),
					implode( ', ', $missing )
				),
			);
		}

		// Try to generate a client secret.
		$secret = $this->generate_client_secret();

		if ( ! $secret ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to generate client secret. Check your private key.', 'bkx-apple-siri' ),
			);
		}

		// Try to fetch Apple public keys.
		$keys = $this->fetch_apple_keys();

		if ( ! $keys ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to fetch Apple public keys. Check network connectivity.', 'bkx-apple-siri' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Connection successful. Apple integration is properly configured.', 'bkx-apple-siri' ),
			'details' => array(
				'keys_fetched' => count( $keys ),
				'secret_valid' => true,
			),
		);
	}

	/**
	 * Get user ID from token.
	 *
	 * @param string $auth_header Authorization header.
	 * @return int|false User ID or false.
	 */
	public function get_user_from_token( $auth_header ) {
		if ( strpos( $auth_header, 'Bearer ' ) !== 0 ) {
			return false;
		}

		$token   = substr( $auth_header, 7 );
		$decoded = $this->decode_jwt( $token );

		if ( ! $decoded ) {
			return false;
		}

		$payload = $decoded['payload'];
		$sub     = $payload['sub'] ?? '';

		if ( empty( $sub ) ) {
			return false;
		}

		// Find WordPress user by Apple ID.
		$users = get_users(
			array(
				'meta_key'   => 'apple_user_id',
				'meta_value' => $sub,
				'number'     => 1,
			)
		);

		if ( ! empty( $users ) ) {
			return $users[0]->ID;
		}

		// Try to find by email if present.
		if ( ! empty( $payload['email'] ) ) {
			$user = get_user_by( 'email', $payload['email'] );
			if ( $user ) {
				// Store Apple ID for future lookups.
				update_user_meta( $user->ID, 'apple_user_id', $sub );
				return $user->ID;
			}
		}

		return false;
	}

	/**
	 * Log error.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( $message ) {
		if ( $this->addon->get_setting( 'log_requests', false ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BKX Apple Siri Auth: ' . $message );
		}
	}
}

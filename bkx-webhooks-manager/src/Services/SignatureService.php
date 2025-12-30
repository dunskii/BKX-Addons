<?php
/**
 * Signature Service.
 *
 * @package BookingX\WebhooksManager
 */

namespace BookingX\WebhooksManager\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class SignatureService
 *
 * Handles webhook signature generation and verification.
 */
class SignatureService {

	/**
	 * Default signature algorithm.
	 *
	 * @var string
	 */
	private $algorithm;

	/**
	 * Signature tolerance in seconds.
	 *
	 * @var int
	 */
	private $tolerance;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings        = get_option( 'bkx_webhooks_manager_settings', array() );
		$this->algorithm = $settings['signature_algorithm'] ?? 'sha256';
		$this->tolerance = $settings['signature_tolerance'] ?? 300; // 5 minutes.
	}

	/**
	 * Sign a payload.
	 *
	 * @param string $payload Payload to sign.
	 * @param string $secret  Secret key.
	 * @return string Signature header value.
	 */
	public function sign( string $payload, string $secret ): string {
		$timestamp = time();

		// Create signed payload.
		$signed_payload = $timestamp . '.' . $payload;

		// Generate signature.
		$signature = hash_hmac( $this->algorithm, $signed_payload, $secret );

		// Return formatted signature.
		return sprintf( 't=%d,v1=%s', $timestamp, $signature );
	}

	/**
	 * Verify a signature.
	 *
	 * @param string $payload           The payload that was signed.
	 * @param string $signature_header  The X-BKX-Signature header value.
	 * @param string $secret            The webhook secret.
	 * @return bool True if signature is valid, false otherwise.
	 */
	public function verify( string $payload, string $signature_header, string $secret ): bool {
		$elements = $this->parse_signature_header( $signature_header );

		if ( empty( $elements['timestamp'] ) || empty( $elements['signatures'] ) ) {
			return false;
		}

		$timestamp = (int) $elements['timestamp'];

		// Check timestamp tolerance.
		if ( ! $this->is_timestamp_valid( $timestamp ) ) {
			return false;
		}

		// Generate expected signature.
		$signed_payload    = $timestamp . '.' . $payload;
		$expected_signature = hash_hmac( $this->algorithm, $signed_payload, $secret );

		// Check if any provided signature matches.
		foreach ( $elements['signatures'] as $signature ) {
			if ( hash_equals( $expected_signature, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse signature header into components.
	 *
	 * @param string $header The signature header value.
	 * @return array Parsed elements with 'timestamp' and 'signatures'.
	 */
	private function parse_signature_header( string $header ): array {
		$elements = array(
			'timestamp'  => null,
			'signatures' => array(),
		);

		$parts = explode( ',', $header );

		foreach ( $parts as $part ) {
			$item = explode( '=', $part, 2 );

			if ( count( $item ) !== 2 ) {
				continue;
			}

			$key   = trim( $item[0] );
			$value = trim( $item[1] );

			if ( 't' === $key ) {
				$elements['timestamp'] = $value;
			} elseif ( strpos( $key, 'v' ) === 0 ) {
				$elements['signatures'][] = $value;
			}
		}

		return $elements;
	}

	/**
	 * Check if timestamp is within tolerance.
	 *
	 * @param int $timestamp The timestamp to check.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_timestamp_valid( int $timestamp ): bool {
		$current_time = time();
		return abs( $current_time - $timestamp ) <= $this->tolerance;
	}

	/**
	 * Generate a new webhook secret.
	 *
	 * @param int $length Secret length in bytes.
	 * @return string Generated secret (hex encoded).
	 */
	public function generate_secret( int $length = 32 ): string {
		return bin2hex( random_bytes( $length ) );
	}

	/**
	 * Get available signature algorithms.
	 *
	 * @return array Available algorithms.
	 */
	public function get_available_algorithms(): array {
		return array(
			'sha256' => array(
				'label'       => 'SHA-256',
				'description' => __( 'Standard HMAC-SHA256 signature (recommended).', 'bkx-webhooks-manager' ),
			),
			'sha384' => array(
				'label'       => 'SHA-384',
				'description' => __( 'HMAC-SHA384 signature for enhanced security.', 'bkx-webhooks-manager' ),
			),
			'sha512' => array(
				'label'       => 'SHA-512',
				'description' => __( 'HMAC-SHA512 signature for maximum security.', 'bkx-webhooks-manager' ),
			),
		);
	}

	/**
	 * Create a signature for verification by recipients.
	 *
	 * This creates a detailed breakdown for documentation/debugging purposes.
	 *
	 * @param string $payload   Payload to sign.
	 * @param string $secret    Secret key.
	 * @param int    $timestamp Optional specific timestamp.
	 * @return array Signature details.
	 */
	public function create_signature_details( string $payload, string $secret, int $timestamp = 0 ): array {
		if ( ! $timestamp ) {
			$timestamp = time();
		}

		$signed_payload = $timestamp . '.' . $payload;
		$signature      = hash_hmac( $this->algorithm, $signed_payload, $secret );

		return array(
			'timestamp'      => $timestamp,
			'algorithm'      => $this->algorithm,
			'signed_payload' => $signed_payload,
			'signature'      => $signature,
			'header_value'   => sprintf( 't=%d,v1=%s', $timestamp, $signature ),
		);
	}

	/**
	 * Generate example verification code for documentation.
	 *
	 * @param string $language Programming language.
	 * @return string Example code.
	 */
	public function get_verification_example( string $language = 'php' ): string {
		switch ( $language ) {
			case 'php':
				return <<<'CODE'
<?php
function verify_webhook_signature( $payload, $signature_header, $secret ) {
    // Parse the signature header
    $elements = [];
    foreach ( explode( ',', $signature_header ) as $part ) {
        list( $key, $value ) = explode( '=', $part, 2 );
        if ( $key === 't' ) {
            $elements['timestamp'] = $value;
        } elseif ( strpos( $key, 'v' ) === 0 ) {
            $elements['signatures'][] = $value;
        }
    }

    // Check timestamp (5 minute tolerance)
    if ( abs( time() - $elements['timestamp'] ) > 300 ) {
        return false;
    }

    // Generate expected signature
    $signed_payload = $elements['timestamp'] . '.' . $payload;
    $expected = hash_hmac( 'sha256', $signed_payload, $secret );

    // Verify signature
    foreach ( $elements['signatures'] as $signature ) {
        if ( hash_equals( $expected, $signature ) ) {
            return true;
        }
    }

    return false;
}

// Usage
$payload = file_get_contents( 'php://input' );
$signature = $_SERVER['HTTP_X_BKX_SIGNATURE'] ?? '';
$secret = 'your_webhook_secret';

if ( verify_webhook_signature( $payload, $signature, $secret ) ) {
    $data = json_decode( $payload, true );
    // Process webhook...
} else {
    http_response_code( 401 );
    exit( 'Invalid signature' );
}
CODE;

			case 'node':
			case 'javascript':
				return <<<'CODE'
const crypto = require('crypto');

function verifyWebhookSignature(payload, signatureHeader, secret) {
    // Parse the signature header
    const elements = { signatures: [] };
    signatureHeader.split(',').forEach(part => {
        const [key, value] = part.split('=');
        if (key === 't') {
            elements.timestamp = parseInt(value, 10);
        } else if (key.startsWith('v')) {
            elements.signatures.push(value);
        }
    });

    // Check timestamp (5 minute tolerance)
    const tolerance = 300;
    const currentTime = Math.floor(Date.now() / 1000);
    if (Math.abs(currentTime - elements.timestamp) > tolerance) {
        return false;
    }

    // Generate expected signature
    const signedPayload = `${elements.timestamp}.${payload}`;
    const expected = crypto
        .createHmac('sha256', secret)
        .update(signedPayload)
        .digest('hex');

    // Verify signature
    return elements.signatures.some(sig =>
        crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(sig))
    );
}

// Express.js usage
app.post('/webhook', express.raw({ type: 'application/json' }), (req, res) => {
    const payload = req.body.toString();
    const signature = req.headers['x-bkx-signature'];
    const secret = process.env.WEBHOOK_SECRET;

    if (verifyWebhookSignature(payload, signature, secret)) {
        const data = JSON.parse(payload);
        // Process webhook...
        res.sendStatus(200);
    } else {
        res.sendStatus(401);
    }
});
CODE;

			case 'python':
				return <<<'CODE'
import hmac
import hashlib
import time

def verify_webhook_signature(payload, signature_header, secret):
    # Parse the signature header
    elements = {'signatures': []}
    for part in signature_header.split(','):
        key, value = part.split('=', 1)
        if key == 't':
            elements['timestamp'] = int(value)
        elif key.startswith('v'):
            elements['signatures'].append(value)

    # Check timestamp (5 minute tolerance)
    tolerance = 300
    current_time = int(time.time())
    if abs(current_time - elements['timestamp']) > tolerance:
        return False

    # Generate expected signature
    signed_payload = f"{elements['timestamp']}.{payload}"
    expected = hmac.new(
        secret.encode(),
        signed_payload.encode(),
        hashlib.sha256
    ).hexdigest()

    # Verify signature (timing-safe comparison)
    return any(hmac.compare_digest(expected, sig) for sig in elements['signatures'])

# Flask usage
from flask import Flask, request

app = Flask(__name__)

@app.route('/webhook', methods=['POST'])
def webhook():
    payload = request.data.decode()
    signature = request.headers.get('X-BKX-Signature', '')
    secret = 'your_webhook_secret'

    if verify_webhook_signature(payload, signature, secret):
        data = request.json
        # Process webhook...
        return '', 200
    else:
        return '', 401
CODE;

			case 'ruby':
				return <<<'CODE'
require 'openssl'
require 'json'

def verify_webhook_signature(payload, signature_header, secret)
  # Parse the signature header
  elements = { signatures: [] }
  signature_header.split(',').each do |part|
    key, value = part.split('=', 2)
    if key == 't'
      elements[:timestamp] = value.to_i
    elsif key.start_with?('v')
      elements[:signatures] << value
    end
  end

  # Check timestamp (5 minute tolerance)
  tolerance = 300
  current_time = Time.now.to_i
  return false if (current_time - elements[:timestamp]).abs > tolerance

  # Generate expected signature
  signed_payload = "#{elements[:timestamp]}.#{payload}"
  expected = OpenSSL::HMAC.hexdigest('SHA256', secret, signed_payload)

  # Verify signature (timing-safe comparison)
  elements[:signatures].any? { |sig| secure_compare(expected, sig) }
end

def secure_compare(a, b)
  return false if a.bytesize != b.bytesize
  OpenSSL.fixed_length_secure_compare(a, b)
end

# Sinatra usage
post '/webhook' do
  payload = request.body.read
  signature = request.env['HTTP_X_BKX_SIGNATURE']
  secret = ENV['WEBHOOK_SECRET']

  if verify_webhook_signature(payload, signature, secret)
    data = JSON.parse(payload)
    # Process webhook...
    status 200
  else
    status 401
  end
end
CODE;

			default:
				return __( 'Example code not available for this language.', 'bkx-webhooks-manager' );
		}
	}

	/**
	 * Get supported verification languages.
	 *
	 * @return array Languages with labels.
	 */
	public function get_supported_languages(): array {
		return array(
			'php'        => 'PHP',
			'node'       => 'Node.js',
			'python'     => 'Python',
			'ruby'       => 'Ruby',
		);
	}
}

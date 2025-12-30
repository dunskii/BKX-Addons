<?php
/**
 * Security Headers service.
 *
 * @package BookingX\SecurityAudit
 */

namespace BookingX\SecurityAudit\Services;

defined( 'ABSPATH' ) || exit;

/**
 * SecurityHeaders class.
 */
class SecurityHeaders {

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Send security headers.
	 */
	public function send_security_headers() {
		// Don't send headers if already sent.
		if ( headers_sent() ) {
			return;
		}

		// X-Content-Type-Options.
		header( 'X-Content-Type-Options: nosniff' );

		// X-Frame-Options.
		header( 'X-Frame-Options: SAMEORIGIN' );

		// X-XSS-Protection.
		header( 'X-XSS-Protection: 1; mode=block' );

		// Referrer-Policy.
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );

		// Permissions-Policy (formerly Feature-Policy).
		header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );

		// Strict-Transport-Security (HSTS) - only if SSL.
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		}

		// Content-Security-Policy - basic policy.
		if ( ! is_admin() ) {
			$csp = $this->get_csp_header();
			if ( $csp ) {
				header( 'Content-Security-Policy: ' . $csp );
			}
		}

		/**
		 * Fires after security headers are sent.
		 */
		do_action( 'bkx_security_headers_sent' );
	}

	/**
	 * Get Content-Security-Policy header value.
	 *
	 * @return string
	 */
	private function get_csp_header() {
		$directives = array(
			"default-src 'self'",
			"script-src 'self' 'unsafe-inline' 'unsafe-eval'", // WordPress often needs these.
			"style-src 'self' 'unsafe-inline'",
			"img-src 'self' data: https:",
			"font-src 'self' data:",
			"connect-src 'self'",
			"frame-ancestors 'self'",
			"base-uri 'self'",
			"form-action 'self'",
		);

		/**
		 * Filter the CSP directives.
		 *
		 * @param array $directives CSP directives.
		 */
		$directives = apply_filters( 'bkx_security_csp_directives', $directives );

		return implode( '; ', $directives );
	}

	/**
	 * Get current headers for testing.
	 *
	 * @return array
	 */
	public function get_current_headers() {
		$headers = array();

		$check_headers = array(
			'X-Content-Type-Options',
			'X-Frame-Options',
			'X-XSS-Protection',
			'Referrer-Policy',
			'Permissions-Policy',
			'Strict-Transport-Security',
			'Content-Security-Policy',
		);

		// We can't actually read outgoing headers, but we can check what we'd send.
		foreach ( $check_headers as $header ) {
			$headers[ $header ] = $this->would_send_header( $header );
		}

		return $headers;
	}

	/**
	 * Check if we would send a specific header.
	 *
	 * @param string $header Header name.
	 * @return string|bool
	 */
	private function would_send_header( $header ) {
		$header_values = array(
			'X-Content-Type-Options'      => 'nosniff',
			'X-Frame-Options'             => 'SAMEORIGIN',
			'X-XSS-Protection'            => '1; mode=block',
			'Referrer-Policy'             => 'strict-origin-when-cross-origin',
			'Permissions-Policy'          => 'geolocation=(), microphone=(), camera=()',
			'Strict-Transport-Security'   => is_ssl() ? 'max-age=31536000; includeSubDomains' : false,
			'Content-Security-Policy'     => ! is_admin() ? $this->get_csp_header() : false,
		);

		return $header_values[ $header ] ?? false;
	}
}

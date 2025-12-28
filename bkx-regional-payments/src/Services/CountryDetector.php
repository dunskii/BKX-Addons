<?php
/**
 * Country Detector
 *
 * @package BookingX\RegionalPayments\Services
 * @since   1.0.0
 */

namespace BookingX\RegionalPayments\Services;

/**
 * Detects customer's country for payment gateway filtering.
 *
 * @since 1.0.0
 */
class CountryDetector {

	/**
	 * Cached country code.
	 *
	 * @var string|null
	 */
	private ?string $cached_country = null;

	/**
	 * Get customer's country code.
	 *
	 * @since 1.0.0
	 * @return string|null Two-letter country code or null.
	 */
	public function get_customer_country(): ?string {
		if ( null !== $this->cached_country ) {
			return $this->cached_country;
		}

		// Priority 1: User-provided country.
		$country = $this->get_from_session();

		// Priority 2: GeoIP detection.
		if ( ! $country ) {
			$country = $this->get_from_geoip();
		}

		// Priority 3: CloudFlare header.
		if ( ! $country ) {
			$country = $this->get_from_cloudflare();
		}

		// Priority 4: WooCommerce GeoIP.
		if ( ! $country ) {
			$country = $this->get_from_woocommerce();
		}

		// Priority 5: Default from settings.
		if ( ! $country ) {
			$country = $this->get_default_country();
		}

		$this->cached_country = $country;

		return $country;
	}

	/**
	 * Get country from session.
	 *
	 * @since 1.0.0
	 * @return string|null
	 */
	private function get_from_session(): ?string {
		if ( ! session_id() && ! headers_sent() ) {
			// Don't start a session just for this.
			return null;
		}

		// Check for stored country in session.
		if ( isset( $_SESSION['bkx_customer_country'] ) ) {
			$country = sanitize_text_field( $_SESSION['bkx_customer_country'] );
			if ( $this->is_valid_country_code( $country ) ) {
				return $country;
			}
		}

		return null;
	}

	/**
	 * Get country from GeoIP database.
	 *
	 * @since 1.0.0
	 * @return string|null
	 */
	private function get_from_geoip(): ?string {
		// Check for MaxMind GeoIP database.
		$geoip_path = WP_CONTENT_DIR . '/uploads/geoip/GeoLite2-Country.mmdb';

		if ( ! file_exists( $geoip_path ) ) {
			return null;
		}

		// Check if MaxMind library is available.
		if ( ! class_exists( 'GeoIp2\\Database\\Reader' ) ) {
			return null;
		}

		try {
			$reader  = new \GeoIp2\Database\Reader( $geoip_path );
			$ip      = $this->get_client_ip();
			$record  = $reader->country( $ip );
			$country = $record->country->isoCode;

			if ( $this->is_valid_country_code( $country ) ) {
				return $country;
			}
		} catch ( \Exception $e ) {
			// GeoIP lookup failed, continue to next method.
		}

		return null;
	}

	/**
	 * Get country from CloudFlare header.
	 *
	 * @since 1.0.0
	 * @return string|null
	 */
	private function get_from_cloudflare(): ?string {
		if ( isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			$country = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) );
			if ( $this->is_valid_country_code( $country ) ) {
				return $country;
			}
		}

		return null;
	}

	/**
	 * Get country from WooCommerce GeoIP.
	 *
	 * @since 1.0.0
	 * @return string|null
	 */
	private function get_from_woocommerce(): ?string {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$geolocation = \WC_Geolocation::geolocate_ip();

		if ( ! empty( $geolocation['country'] ) && $this->is_valid_country_code( $geolocation['country'] ) ) {
			return $geolocation['country'];
		}

		return null;
	}

	/**
	 * Get default country from settings.
	 *
	 * @since 1.0.0
	 * @return string|null
	 */
	private function get_default_country(): ?string {
		// Check BookingX setting.
		$country = get_option( 'bkx_country', '' );

		if ( $country && $this->is_valid_country_code( $country ) ) {
			return $country;
		}

		// Check WooCommerce setting.
		if ( function_exists( 'WC' ) ) {
			$country = wc_get_base_location()['country'] ?? '';
			if ( $country && $this->is_valid_country_code( $country ) ) {
				return $country;
			}
		}

		// Default to US.
		return 'US';
	}

	/**
	 * Get client IP address.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

				// Handle comma-separated IPs (X-Forwarded-For).
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}

				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
	}

	/**
	 * Validate country code.
	 *
	 * @since 1.0.0
	 * @param string $code Country code to validate.
	 * @return bool
	 */
	private function is_valid_country_code( string $code ): bool {
		// Basic validation: 2 uppercase letters.
		return preg_match( '/^[A-Z]{2}$/', $code ) === 1;
	}

	/**
	 * Set customer country manually.
	 *
	 * @since 1.0.0
	 * @param string $country_code Two-letter country code.
	 * @return void
	 */
	public function set_customer_country( string $country_code ): void {
		if ( ! $this->is_valid_country_code( $country_code ) ) {
			return;
		}

		$this->cached_country = $country_code;

		if ( session_id() || ( ! headers_sent() && session_start() ) ) {
			$_SESSION['bkx_customer_country'] = $country_code;
		}
	}

	/**
	 * Clear cached country.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function clear_cache(): void {
		$this->cached_country = null;
	}
}

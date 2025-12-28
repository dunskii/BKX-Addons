<?php
/**
 * Abstract Regional Gateway
 *
 * @package BookingX\RegionalPayments\Gateways
 * @since   1.0.0
 */

namespace BookingX\RegionalPayments\Gateways;

use BookingX\AddonSDK\Abstracts\AbstractPaymentGateway;

/**
 * Base class for regional payment gateways.
 *
 * @since 1.0.0
 */
abstract class AbstractRegionalGateway extends AbstractPaymentGateway {

	/**
	 * Gateway region/country.
	 *
	 * @var array
	 */
	protected array $countries = array();

	/**
	 * Supported currencies.
	 *
	 * @var array
	 */
	protected array $currencies = array();

	/**
	 * Whether gateway supports QR codes.
	 *
	 * @var bool
	 */
	protected bool $supports_qr = false;

	/**
	 * Whether gateway uses redirect flow.
	 *
	 * @var bool
	 */
	protected bool $uses_redirect = false;

	/**
	 * Get settings key.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_settings_key(): string {
		return 'bkx_regional_' . $this->id . '_settings';
	}

	/**
	 * Get gateway settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_gateway_settings(): array {
		return get_option( $this->get_settings_key(), array() );
	}

	/**
	 * Check if gateway is available for a country.
	 *
	 * @since 1.0.0
	 * @param string $country_code Two-letter country code.
	 * @return bool
	 */
	public function is_available_for_country( string $country_code ): bool {
		if ( empty( $this->countries ) ) {
			return true;
		}

		return in_array( $country_code, $this->countries, true );
	}

	/**
	 * Check if gateway supports a currency.
	 *
	 * @since 1.0.0
	 * @param string $currency_code Three-letter currency code.
	 * @return bool
	 */
	public function supports_currency( string $currency_code ): bool {
		if ( empty( $this->currencies ) ) {
			return true;
		}

		return in_array( $currency_code, $this->currencies, true );
	}

	/**
	 * Check if gateway supports QR codes.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function supports_qr(): bool {
		return $this->supports_qr;
	}

	/**
	 * Check if gateway uses redirect flow.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function uses_redirect(): bool {
		return $this->uses_redirect;
	}

	/**
	 * Generate QR code for payment.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param array $data       Payment data.
	 * @return string|null QR code data or null.
	 */
	public function generate_qr_code( int $booking_id, array $data ): ?string {
		return null;
	}

	/**
	 * Get redirect URL for payment.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param array $data       Payment data.
	 * @return string|null Redirect URL or null.
	 */
	public function get_redirect_url( int $booking_id, array $data ): ?string {
		return null;
	}

	/**
	 * Log transaction.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $type       Transaction type.
	 * @param array  $data       Transaction data.
	 * @return void
	 */
	protected function log_transaction( int $booking_id, string $type, array $data ): void {
		$log = get_post_meta( $booking_id, '_bkx_payment_log', true );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'type'      => $type,
			'gateway'   => $this->id,
			'timestamp' => current_time( 'mysql' ),
			'data'      => $data,
		);

		update_post_meta( $booking_id, '_bkx_payment_log', $log );
	}

	/**
	 * Format amount for API.
	 *
	 * @since 1.0.0
	 * @param float  $amount   Amount to format.
	 * @param string $currency Currency code.
	 * @return int|float
	 */
	protected function format_amount( float $amount, string $currency ) {
		// Most APIs use cents/smallest unit.
		$zero_decimal_currencies = array( 'JPY', 'KRW', 'VND' );

		if ( in_array( $currency, $zero_decimal_currencies, true ) ) {
			return (int) round( $amount );
		}

		return (int) round( $amount * 100 );
	}

	/**
	 * Get return URL after payment.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	protected function get_return_url( int $booking_id ): string {
		$return_url = add_query_arg( array(
			'bkx_payment_return' => 1,
			'booking_id'         => $booking_id,
			'gateway'            => $this->id,
		), home_url( '/' ) );

		return apply_filters( 'bkx_regional_payment_return_url', $return_url, $booking_id, $this->id );
	}

	/**
	 * Get webhook URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_webhook_url(): string {
		return add_query_arg( array(
			'bkx_webhook' => 1,
			'gateway'     => $this->id,
		), home_url( '/' ) );
	}
}

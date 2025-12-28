<?php
/**
 * Gateway Registry
 *
 * @package BookingX\RegionalPayments\Services
 * @since   1.0.0
 */

namespace BookingX\RegionalPayments\Services;

/**
 * Registry for regional payment gateways.
 *
 * @since 1.0.0
 */
class GatewayRegistry {

	/**
	 * Registered gateways.
	 *
	 * @var array
	 */
	private array $gateways = array();

	/**
	 * Register a gateway.
	 *
	 * @since 1.0.0
	 * @param string $id     Gateway ID.
	 * @param array  $config Gateway configuration.
	 * @return void
	 */
	public function register( string $id, array $config ): void {
		$this->gateways[ $id ] = wp_parse_args( $config, array(
			'id'          => $id,
			'title'       => '',
			'description' => '',
			'countries'   => array(),
			'currencies'  => array(),
			'gateway'     => '',
			'icon'        => '',
			'settings'    => array(),
		) );
	}

	/**
	 * Unregister a gateway.
	 *
	 * @since 1.0.0
	 * @param string $id Gateway ID.
	 * @return void
	 */
	public function unregister( string $id ): void {
		unset( $this->gateways[ $id ] );
	}

	/**
	 * Get a gateway by ID.
	 *
	 * @since 1.0.0
	 * @param string $id Gateway ID.
	 * @return array|null
	 */
	public function get( string $id ): ?array {
		return $this->gateways[ $id ] ?? null;
	}

	/**
	 * Get all registered gateways.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_all(): array {
		return $this->gateways;
	}

	/**
	 * Get gateways by country.
	 *
	 * @since 1.0.0
	 * @param string $country_code Two-letter country code.
	 * @return array
	 */
	public function get_by_country( string $country_code ): array {
		$filtered = array();

		foreach ( $this->gateways as $id => $gateway ) {
			$countries = $gateway['countries'] ?? array();

			if ( empty( $countries ) || in_array( $country_code, $countries, true ) ) {
				$filtered[ $id ] = $gateway;
			}
		}

		return $filtered;
	}

	/**
	 * Get gateways by currency.
	 *
	 * @since 1.0.0
	 * @param string $currency_code Three-letter currency code.
	 * @return array
	 */
	public function get_by_currency( string $currency_code ): array {
		$filtered = array();

		foreach ( $this->gateways as $id => $gateway ) {
			$currencies = $gateway['currencies'] ?? array();

			if ( empty( $currencies ) || in_array( $currency_code, $currencies, true ) ) {
				$filtered[ $id ] = $gateway;
			}
		}

		return $filtered;
	}

	/**
	 * Check if a gateway is registered.
	 *
	 * @since 1.0.0
	 * @param string $id Gateway ID.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->gateways[ $id ] );
	}

	/**
	 * Get gateway count.
	 *
	 * @since 1.0.0
	 * @return int
	 */
	public function count(): int {
		return count( $this->gateways );
	}
}

<?php
/**
 * Group Pricing Service
 *
 * @package BookingX\GroupBookings\Services
 * @since   1.0.0
 */

namespace BookingX\GroupBookings\Services;

/**
 * Service for calculating group pricing.
 *
 * @since 1.0.0
 */
class GroupPricingService {

	/**
	 * Calculate price for a group booking.
	 *
	 * @since 1.0.0
	 * @param float  $base_price   Base price per booking.
	 * @param int    $quantity     Number of people.
	 * @param int    $base_id      Service post ID.
	 * @param string $pricing_mode Pricing mode.
	 * @param array  $settings     Plugin settings.
	 * @return float
	 */
	public function calculate_price( float $base_price, int $quantity, int $base_id, string $pricing_mode, array $settings ): float {
		$total = 0;

		switch ( $pricing_mode ) {
			case 'per_person':
				$total = $base_price * $quantity;
				break;

			case 'flat_rate':
				$total = $base_price;
				break;

			case 'tiered':
				$total = $this->calculate_tiered_price( $base_price, $quantity, $base_id );
				break;

			default:
				$total = $base_price * $quantity;
				break;
		}

		// Apply group discount if enabled.
		$total = $this->apply_group_discount( $total, $quantity, $base_id, $settings );

		return round( $total, 2 );
	}

	/**
	 * Calculate tiered pricing.
	 *
	 * @since 1.0.0
	 * @param float $base_price Base price.
	 * @param int   $quantity   Number of people.
	 * @param int   $base_id    Service post ID.
	 * @return float
	 */
	private function calculate_tiered_price( float $base_price, int $quantity, int $base_id ): float {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_group_pricing_tiers';

		// Get matching tier.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$tier = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE base_id = %d
				AND min_quantity <= %d
				AND max_quantity >= %d
				ORDER BY min_quantity ASC
				LIMIT 1",
				$base_id,
				$quantity,
				$quantity
			),
			ARRAY_A
		);

		if ( ! $tier ) {
			// No tier found, use per-person pricing.
			return $base_price * $quantity;
		}

		$tier_price = (float) $tier['price'];

		if ( 'per_person' === $tier['price_type'] ) {
			$total = $tier_price * $quantity;
		} else {
			$total = $tier_price;
		}

		// Apply tier discount if set.
		if ( ! empty( $tier['discount_type'] ) && ! empty( $tier['discount_value'] ) ) {
			$discount = (float) $tier['discount_value'];

			if ( 'percentage' === $tier['discount_type'] ) {
				$total -= $total * ( $discount / 100 );
			} else {
				$total -= $discount;
			}
		}

		return max( 0, $total );
	}

	/**
	 * Apply group discount.
	 *
	 * @since 1.0.0
	 * @param float $total    Current total.
	 * @param int   $quantity Number of people.
	 * @param int   $base_id  Service post ID.
	 * @param array $settings Plugin settings.
	 * @return float
	 */
	private function apply_group_discount( float $total, int $quantity, int $base_id, array $settings ): float {
		// Check service-specific discount first.
		$service_discount_enabled = get_post_meta( $base_id, '_bkx_group_discount_enabled', true );
		$service_discount_min     = (int) get_post_meta( $base_id, '_bkx_group_discount_min', true );
		$service_discount_type    = get_post_meta( $base_id, '_bkx_group_discount_type', true );
		$service_discount_value   = (float) get_post_meta( $base_id, '_bkx_group_discount_value', true );

		// Use service-specific if set.
		if ( $service_discount_enabled && $service_discount_min ) {
			$discount_enabled = true;
			$discount_min     = $service_discount_min;
			$discount_type    = $service_discount_type ?: 'percentage';
			$discount_value   = $service_discount_value;
		} else {
			// Fall back to global settings.
			$discount_enabled = ! empty( $settings['group_discount_enable'] );
			$discount_min     = (int) ( $settings['group_discount_min'] ?? 5 );
			$discount_type    = $settings['group_discount_type'] ?? 'percentage';
			$discount_value   = (float) ( $settings['group_discount_value'] ?? 0 );
		}

		if ( ! $discount_enabled || $quantity < $discount_min || $discount_value <= 0 ) {
			return $total;
		}

		if ( 'percentage' === $discount_type ) {
			$discount = $total * ( $discount_value / 100 );
		} else {
			$discount = $discount_value;
		}

		return max( 0, $total - $discount );
	}

	/**
	 * Get price breakdown.
	 *
	 * @since 1.0.0
	 * @param float  $base_price   Base price.
	 * @param int    $quantity     Number of people.
	 * @param int    $base_id      Service post ID.
	 * @param string $pricing_mode Pricing mode.
	 * @param array  $settings     Plugin settings.
	 * @return array
	 */
	public function get_price_breakdown( float $base_price, int $quantity, int $base_id, string $pricing_mode, array $settings ): array {
		$breakdown = array();

		// Base calculation.
		switch ( $pricing_mode ) {
			case 'per_person':
				$subtotal    = $base_price * $quantity;
				$breakdown[] = array(
					'label' => sprintf(
						/* translators: 1: base price, 2: quantity */
						__( '%1$s x %2$d people', 'bkx-group-bookings' ),
						$this->format_price( $base_price ),
						$quantity
					),
					'value' => $subtotal,
				);
				break;

			case 'flat_rate':
				$subtotal    = $base_price;
				$breakdown[] = array(
					'label' => __( 'Flat rate', 'bkx-group-bookings' ),
					'value' => $subtotal,
				);
				break;

			case 'tiered':
				$subtotal    = $this->calculate_tiered_price( $base_price, $quantity, $base_id );
				$breakdown[] = array(
					'label' => sprintf(
						/* translators: %d: quantity */
						__( 'Group rate (%d people)', 'bkx-group-bookings' ),
						$quantity
					),
					'value' => $subtotal,
				);
				break;

			default:
				$subtotal = $base_price * $quantity;
				break;
		}

		// Group discount.
		$final_total = $this->apply_group_discount( $subtotal, $quantity, $base_id, $settings );
		$discount    = $subtotal - $final_total;

		if ( $discount > 0 ) {
			$breakdown[] = array(
				'label'      => __( 'Group discount', 'bkx-group-bookings' ),
				'value'      => -$discount,
				'is_discount' => true,
			);
		}

		return $breakdown;
	}

	/**
	 * Format price.
	 *
	 * @since 1.0.0
	 * @param float $price Price value.
	 * @return string
	 */
	private function format_price( float $price ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $price );
		}

		return '$' . number_format( $price, 2 );
	}

	/**
	 * Add pricing tier.
	 *
	 * @since 1.0.0
	 * @param int   $base_id Service post ID.
	 * @param array $data    Tier data.
	 * @return int|false Tier ID or false on failure.
	 */
	public function add_tier( int $base_id, array $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_group_pricing_tiers';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$table,
			array(
				'base_id'        => $base_id,
				'min_quantity'   => absint( $data['min_quantity'] ),
				'max_quantity'   => absint( $data['max_quantity'] ),
				'price_type'     => sanitize_text_field( $data['price_type'] ),
				'price'          => floatval( $data['price'] ),
				'discount_type'  => isset( $data['discount_type'] ) ? sanitize_text_field( $data['discount_type'] ) : null,
				'discount_value' => isset( $data['discount_value'] ) ? floatval( $data['discount_value'] ) : null,
			),
			array( '%d', '%d', '%d', '%s', '%f', '%s', '%f' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get pricing tiers for a service.
	 *
	 * @since 1.0.0
	 * @param int $base_id Service post ID.
	 * @return array
	 */
	public function get_tiers( int $base_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_group_pricing_tiers';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE base_id = %d ORDER BY min_quantity ASC",
				$base_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Delete pricing tier.
	 *
	 * @since 1.0.0
	 * @param int $tier_id Tier ID.
	 * @return bool
	 */
	public function delete_tier( int $tier_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_group_pricing_tiers';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete(
			$table,
			array( 'id' => $tier_id ),
			array( '%d' )
		);

		return false !== $result;
	}
}

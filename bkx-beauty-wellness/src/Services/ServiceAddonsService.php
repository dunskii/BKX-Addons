<?php
/**
 * Service Add-ons Service
 *
 * Manages upsell add-ons for beauty treatments.
 *
 * @package BookingX\BeautyWellness\Services
 * @since   1.0.0
 */

namespace BookingX\BeautyWellness\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ServiceAddonsService
 *
 * @since 1.0.0
 */
class ServiceAddonsService {

	/**
	 * Get available add-ons for a treatment.
	 *
	 * @since 1.0.0
	 * @param int $treatment_id Treatment (base) post ID.
	 * @return array
	 */
	public function get_addons_for_treatment( int $treatment_id ): array {
		// Get explicitly linked add-ons.
		$linked_addon_ids = get_post_meta( $treatment_id, '_bkx_treatment_addons', true ) ?: array();

		// Get add-ons by category matching.
		$treatment_categories = wp_get_post_terms( $treatment_id, 'bkx_treatment_category', array( 'fields' => 'ids' ) );

		$args = array(
			'post_type'      => 'bkx_addition',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post__in'       => ! empty( $linked_addon_ids ) ? $linked_addon_ids : array( 0 ),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		// If no linked add-ons, get by category.
		if ( empty( $linked_addon_ids ) && ! empty( $treatment_categories ) ) {
			unset( $args['post__in'] );
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'bkx_treatment_category',
					'field'    => 'term_id',
					'terms'    => $treatment_categories,
				),
			);
		}

		$query  = new \WP_Query( $args );
		$addons = array();

		foreach ( $query->posts as $addon ) {
			$addons[] = $this->format_addon( $addon );
		}

		return $addons;
	}

	/**
	 * Get all available add-ons.
	 *
	 * @since 1.0.0
	 * @param array $filters Optional filters.
	 * @return array
	 */
	public function get_all_addons( array $filters = array() ): array {
		$args = array(
			'post_type'      => 'bkx_addition',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		// Category filter.
		if ( ! empty( $filters['category'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'bkx_treatment_category',
				'field'    => 'term_id',
				'terms'    => absint( $filters['category'] ),
			);
		}

		// Type filter (enhancement, upgrade, bundle).
		if ( ! empty( $filters['type'] ) ) {
			$args['meta_query'][] = array(
				'key'   => '_bkx_addon_type',
				'value' => sanitize_text_field( $filters['type'] ),
			);
		}

		$query  = new \WP_Query( $args );
		$addons = array();

		foreach ( $query->posts as $addon ) {
			$addons[] = $this->format_addon( $addon );
		}

		return $addons;
	}

	/**
	 * Get add-on by ID.
	 *
	 * @since 1.0.0
	 * @param int $addon_id Add-on post ID.
	 * @return array|null
	 */
	public function get_addon( int $addon_id ): ?array {
		$addon = get_post( $addon_id );

		if ( ! $addon || 'bkx_addition' !== $addon->post_type ) {
			return null;
		}

		return $this->format_addon( $addon );
	}

	/**
	 * Get popular add-ons based on booking frequency.
	 *
	 * @since 1.0.0
	 * @param int $limit Number of add-ons to return.
	 * @return array
	 */
	public function get_popular_addons( int $limit = 5 ): array {
		global $wpdb;

		// Get most booked add-ons from booking meta.
		$popular_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta}
				WHERE meta_key = 'addition_id'
				AND post_id IN (
					SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'bkx_booking'
					AND post_status IN ('bkx-completed', 'bkx-ack')
					AND post_date > DATE_SUB(NOW(), INTERVAL 90 DAY)
				)
				GROUP BY meta_value
				ORDER BY COUNT(*) DESC
				LIMIT %d",
				$limit
			)
		);

		if ( empty( $popular_ids ) ) {
			// Fallback to featured add-ons.
			return $this->get_featured_addons( $limit );
		}

		$addons = array();
		foreach ( $popular_ids as $addon_id ) {
			$addon = $this->get_addon( absint( $addon_id ) );
			if ( $addon ) {
				$addons[] = $addon;
			}
		}

		return $addons;
	}

	/**
	 * Get featured add-ons.
	 *
	 * @since 1.0.0
	 * @param int $limit Number of add-ons to return.
	 * @return array
	 */
	public function get_featured_addons( int $limit = 5 ): array {
		$args = array(
			'post_type'      => 'bkx_addition',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'meta_query'     => array(
				array(
					'key'   => '_bkx_featured_addon',
					'value' => '1',
				),
			),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		$query  = new \WP_Query( $args );
		$addons = array();

		foreach ( $query->posts as $addon ) {
			$addons[] = $this->format_addon( $addon );
		}

		return $addons;
	}

	/**
	 * Get add-on bundles.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_bundles(): array {
		$args = array(
			'post_type'      => 'bkx_addition',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_bkx_addon_type',
					'value' => 'bundle',
				),
			),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		$query   = new \WP_Query( $args );
		$bundles = array();

		foreach ( $query->posts as $addon ) {
			$bundle              = $this->format_addon( $addon );
			$bundle['items']     = $this->get_bundle_items( $addon->ID );
			$bundle['savings']   = $this->calculate_bundle_savings( $addon->ID );
			$bundles[]           = $bundle;
		}

		return $bundles;
	}

	/**
	 * Get items in a bundle.
	 *
	 * @since 1.0.0
	 * @param int $bundle_id Bundle add-on post ID.
	 * @return array
	 */
	public function get_bundle_items( int $bundle_id ): array {
		$item_ids = get_post_meta( $bundle_id, '_bkx_bundle_items', true ) ?: array();
		$items    = array();

		foreach ( $item_ids as $item_id ) {
			$item = $this->get_addon( absint( $item_id ) );
			if ( $item ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Calculate bundle savings.
	 *
	 * @since 1.0.0
	 * @param int $bundle_id Bundle add-on post ID.
	 * @return float
	 */
	public function calculate_bundle_savings( int $bundle_id ): float {
		$bundle_price = floatval( get_post_meta( $bundle_id, 'addition_price', true ) );
		$items        = $this->get_bundle_items( $bundle_id );
		$items_total  = 0;

		foreach ( $items as $item ) {
			$items_total += $item['price'];
		}

		return max( 0, $items_total - $bundle_price );
	}

	/**
	 * Get recommended add-ons based on client preferences.
	 *
	 * @since 1.0.0
	 * @param int $user_id      Client user ID.
	 * @param int $treatment_id Optional treatment ID for context.
	 * @return array
	 */
	public function get_recommended_addons( int $user_id, int $treatment_id = 0 ): array {
		$preferences_service = new ClientPreferencesService();
		$preferences         = $preferences_service->get_preferences( $user_id );
		$recommendations     = array();

		// Get add-ons matching skin type.
		if ( ! empty( $preferences['skin_type'] ) ) {
			$args = array(
				'post_type'      => 'bkx_addition',
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'tax_query'      => array(
					array(
						'taxonomy' => 'bkx_skin_type',
						'field'    => 'slug',
						'terms'    => $preferences['skin_type'],
					),
				),
			);

			$query = new \WP_Query( $args );

			foreach ( $query->posts as $addon ) {
				$formatted          = $this->format_addon( $addon );
				$formatted['reason'] = sprintf(
					/* translators: %s: skin type */
					__( 'Recommended for %s skin', 'bkx-beauty-wellness' ),
					$preferences['skin_type']
				);
				$recommendations[] = $formatted;
			}
		}

		// If treatment provided, get complementary add-ons.
		if ( $treatment_id > 0 ) {
			$complementary = get_post_meta( $treatment_id, '_bkx_complementary_addons', true ) ?: array();

			foreach ( $complementary as $addon_id ) {
				$addon = $this->get_addon( absint( $addon_id ) );
				if ( $addon ) {
					$addon['reason']   = __( 'Pairs well with your selected treatment', 'bkx-beauty-wellness' );
					$recommendations[] = $addon;
				}
			}
		}

		// Remove duplicates.
		$seen = array();
		$recommendations = array_filter( $recommendations, function( $addon ) use ( &$seen ) {
			if ( in_array( $addon['id'], $seen, true ) ) {
				return false;
			}
			$seen[] = $addon['id'];
			return true;
		} );

		return array_values( $recommendations );
	}

	/**
	 * Calculate add-on pricing with quantity.
	 *
	 * @since 1.0.0
	 * @param int $addon_id Add-on post ID.
	 * @param int $quantity Quantity.
	 * @return array
	 */
	public function calculate_addon_price( int $addon_id, int $quantity = 1 ): array {
		$addon = $this->get_addon( $addon_id );

		if ( ! $addon ) {
			return array(
				'subtotal'  => 0,
				'discount'  => 0,
				'total'     => 0,
				'duration'  => 0,
			);
		}

		$base_price = $addon['price'];
		$subtotal   = $base_price * $quantity;
		$discount   = 0;

		// Apply quantity discount if configured.
		$quantity_discounts = get_post_meta( $addon_id, '_bkx_quantity_discounts', true ) ?: array();

		foreach ( $quantity_discounts as $tier ) {
			if ( $quantity >= absint( $tier['min_qty'] ) ) {
				$discount = $subtotal * ( floatval( $tier['discount_percent'] ) / 100 );
			}
		}

		return array(
			'subtotal'  => $subtotal,
			'discount'  => $discount,
			'total'     => $subtotal - $discount,
			'duration'  => $addon['duration'] * $quantity,
		);
	}

	/**
	 * Validate add-on availability for a treatment and time slot.
	 *
	 * @since 1.0.0
	 * @param int    $addon_id     Add-on post ID.
	 * @param int    $treatment_id Treatment post ID.
	 * @param string $datetime     Booking datetime.
	 * @return array
	 */
	public function validate_addon_availability( int $addon_id, int $treatment_id, string $datetime ): array {
		$addon = $this->get_addon( $addon_id );

		if ( ! $addon ) {
			return array(
				'available' => false,
				'reason'    => __( 'Add-on not found.', 'bkx-beauty-wellness' ),
			);
		}

		// Check if add-on is compatible with treatment.
		$allowed_treatments = get_post_meta( $addon_id, '_bkx_allowed_treatments', true ) ?: array();

		if ( ! empty( $allowed_treatments ) && ! in_array( $treatment_id, $allowed_treatments, true ) ) {
			return array(
				'available' => false,
				'reason'    => __( 'This add-on is not available for the selected treatment.', 'bkx-beauty-wellness' ),
			);
		}

		// Check time-based availability.
		$availability_rules = get_post_meta( $addon_id, '_bkx_availability_rules', true ) ?: array();

		if ( ! empty( $availability_rules ) ) {
			$booking_time = strtotime( $datetime );
			$day_of_week  = strtolower( date( 'l', $booking_time ) );
			$hour         = (int) date( 'G', $booking_time );

			foreach ( $availability_rules as $rule ) {
				if ( isset( $rule['days'] ) && ! in_array( $day_of_week, $rule['days'], true ) ) {
					continue;
				}

				if ( isset( $rule['start_hour'], $rule['end_hour'] ) ) {
					if ( $hour < $rule['start_hour'] || $hour >= $rule['end_hour'] ) {
						return array(
							'available' => false,
							'reason'    => __( 'This add-on is not available at the selected time.', 'bkx-beauty-wellness' ),
						);
					}
				}
			}
		}

		return array(
			'available' => true,
			'addon'     => $addon,
		);
	}

	/**
	 * Format add-on post data.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $addon Add-on post object.
	 * @return array
	 */
	private function format_addon( \WP_Post $addon ): array {
		$image_id = get_post_thumbnail_id( $addon->ID );

		return array(
			'id'          => $addon->ID,
			'name'        => $addon->post_title,
			'slug'        => $addon->post_name,
			'description' => $addon->post_content,
			'short_description' => $addon->post_excerpt,
			'price'       => floatval( get_post_meta( $addon->ID, 'addition_price', true ) ),
			'duration'    => absint( get_post_meta( $addon->ID, 'addition_time', true ) ),
			'image'       => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '',
			'type'        => get_post_meta( $addon->ID, '_bkx_addon_type', true ) ?: 'enhancement',
			'categories'  => wp_get_post_terms( $addon->ID, 'bkx_treatment_category', array( 'fields' => 'names' ) ),
			'is_featured' => (bool) get_post_meta( $addon->ID, '_bkx_featured_addon', true ),
			'is_bundle'   => 'bundle' === get_post_meta( $addon->ID, '_bkx_addon_type', true ),
		);
	}
}

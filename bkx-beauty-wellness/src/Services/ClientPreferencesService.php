<?php
/**
 * Client Preferences Service
 *
 * Manages client preferences including skin type, allergies, and treatment history.
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
 * Class ClientPreferencesService
 *
 * @since 1.0.0
 */
class ClientPreferencesService {

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'bkx_client_preferences';
	}

	/**
	 * Get client preferences.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_preferences( int $user_id ): array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return $this->get_default_preferences();
		}

		return array(
			'user_id'              => $row['user_id'],
			'skin_type'            => $row['skin_type'],
			'skin_concerns'        => json_decode( $row['skin_concerns'] ?: '[]', true ),
			'allergies'            => json_decode( $row['allergies'] ?: '[]', true ),
			'sensitivities'        => json_decode( $row['sensitivities'] ?: '[]', true ),
			'product_preferences'  => json_decode( $row['product_preferences'] ?: '[]', true ),
			'pressure_preference'  => $row['pressure_preference'],
			'temperature_preference' => $row['temperature_preference'],
			'music_preference'     => $row['music_preference'],
			'aromatherapy_preference' => $row['aromatherapy_preference'],
			'preferred_stylist'    => absint( $row['preferred_stylist'] ),
			'hair_type'            => $row['hair_type'],
			'hair_concerns'        => json_decode( $row['hair_concerns'] ?: '[]', true ),
			'nail_shape_preference' => $row['nail_shape_preference'],
			'notes'                => $row['notes'],
			'consultation_completed' => (bool) $row['consultation_completed'],
			'last_updated'         => $row['updated_at'],
		);
	}

	/**
	 * Get default preferences.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_default_preferences(): array {
		return array(
			'user_id'              => 0,
			'skin_type'            => '',
			'skin_concerns'        => array(),
			'allergies'            => array(),
			'sensitivities'        => array(),
			'product_preferences'  => array(),
			'pressure_preference'  => 'medium',
			'temperature_preference' => 'warm',
			'music_preference'     => '',
			'aromatherapy_preference' => '',
			'preferred_stylist'    => 0,
			'hair_type'            => '',
			'hair_concerns'        => array(),
			'nail_shape_preference' => '',
			'notes'                => '',
			'consultation_completed' => false,
			'last_updated'         => null,
		);
	}

	/**
	 * Save client preferences.
	 *
	 * @since 1.0.0
	 * @param int   $user_id User ID.
	 * @param array $preferences Preferences data.
	 * @return bool
	 */
	public function save_preferences( int $user_id, array $preferences ): bool {
		global $wpdb;

		$data = array(
			'user_id'              => $user_id,
			'skin_type'            => sanitize_text_field( $preferences['skin_type'] ?? '' ),
			'skin_concerns'        => wp_json_encode( $preferences['skin_concerns'] ?? array() ),
			'allergies'            => wp_json_encode( $preferences['allergies'] ?? array() ),
			'sensitivities'        => wp_json_encode( $preferences['sensitivities'] ?? array() ),
			'product_preferences'  => wp_json_encode( $preferences['product_preferences'] ?? array() ),
			'pressure_preference'  => sanitize_text_field( $preferences['pressure_preference'] ?? 'medium' ),
			'temperature_preference' => sanitize_text_field( $preferences['temperature_preference'] ?? 'warm' ),
			'music_preference'     => sanitize_text_field( $preferences['music_preference'] ?? '' ),
			'aromatherapy_preference' => sanitize_text_field( $preferences['aromatherapy_preference'] ?? '' ),
			'preferred_stylist'    => absint( $preferences['preferred_stylist'] ?? 0 ),
			'hair_type'            => sanitize_text_field( $preferences['hair_type'] ?? '' ),
			'hair_concerns'        => wp_json_encode( $preferences['hair_concerns'] ?? array() ),
			'nail_shape_preference' => sanitize_text_field( $preferences['nail_shape_preference'] ?? '' ),
			'notes'                => sanitize_textarea_field( $preferences['notes'] ?? '' ),
			'consultation_completed' => ! empty( $preferences['consultation_completed'] ) ? 1 : 0,
			'updated_at'           => current_time( 'mysql' ),
		);

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE user_id = %d",
				$user_id
			)
		);

		if ( $existing ) {
			$result = $wpdb->update(
				$this->table_name,
				$data,
				array( 'user_id' => $user_id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$result = $wpdb->insert(
				$this->table_name,
				$data,
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}

		if ( false !== $result ) {
			/**
			 * Fires after client preferences are saved.
			 *
			 * @param int   $user_id     User ID.
			 * @param array $preferences Saved preferences.
			 */
			do_action( 'bkx_beauty_wellness_preferences_saved', $user_id, $preferences );
		}

		return false !== $result;
	}

	/**
	 * Get allergy alerts for a treatment.
	 *
	 * @since 1.0.0
	 * @param int $user_id      User ID.
	 * @param int $treatment_id Treatment post ID.
	 * @return array
	 */
	public function get_allergy_alerts( int $user_id, int $treatment_id ): array {
		$preferences = $this->get_preferences( $user_id );
		$alerts      = array();

		if ( empty( $preferences['allergies'] ) ) {
			return $alerts;
		}

		// Get treatment ingredients/products.
		$treatment_ingredients = get_post_meta( $treatment_id, '_bkx_treatment_ingredients', true ) ?: array();
		$contraindications     = get_post_meta( $treatment_id, '_bkx_contraindications', true ) ?: array();

		// Check for allergy matches.
		foreach ( $preferences['allergies'] as $allergy ) {
			$allergy_lower = strtolower( $allergy );

			// Check ingredients.
			foreach ( $treatment_ingredients as $ingredient ) {
				if ( stripos( $ingredient, $allergy ) !== false ) {
					$alerts[] = array(
						'type'       => 'allergy',
						'severity'   => 'high',
						'allergy'    => $allergy,
						'ingredient' => $ingredient,
						'message'    => sprintf(
							/* translators: 1: allergy name, 2: ingredient name */
							__( 'Warning: Client has allergy to %1$s. Treatment contains %2$s.', 'bkx-beauty-wellness' ),
							$allergy,
							$ingredient
						),
					);
				}
			}

			// Check contraindications.
			foreach ( $contraindications as $contraindication ) {
				if ( stripos( $contraindication, $allergy ) !== false ) {
					$alerts[] = array(
						'type'           => 'contraindication',
						'severity'       => 'critical',
						'allergy'        => $allergy,
						'contraindication' => $contraindication,
						'message'        => sprintf(
							/* translators: 1: contraindication, 2: allergy */
							__( 'Critical: Treatment has contraindication for %1$s. Client has %2$s.', 'bkx-beauty-wellness' ),
							$contraindication,
							$allergy
						),
					);
				}
			}
		}

		// Check sensitivities.
		foreach ( $preferences['sensitivities'] as $sensitivity ) {
			foreach ( $treatment_ingredients as $ingredient ) {
				if ( stripos( $ingredient, $sensitivity ) !== false ) {
					$alerts[] = array(
						'type'        => 'sensitivity',
						'severity'    => 'medium',
						'sensitivity' => $sensitivity,
						'ingredient'  => $ingredient,
						'message'     => sprintf(
							/* translators: 1: sensitivity, 2: ingredient */
							__( 'Note: Client has sensitivity to %1$s. Treatment contains %2$s.', 'bkx-beauty-wellness' ),
							$sensitivity,
							$ingredient
						),
					);
				}
			}
		}

		return $alerts;
	}

	/**
	 * Get skin type options.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_skin_type_options(): array {
		return array(
			'normal'      => __( 'Normal', 'bkx-beauty-wellness' ),
			'dry'         => __( 'Dry', 'bkx-beauty-wellness' ),
			'oily'        => __( 'Oily', 'bkx-beauty-wellness' ),
			'combination' => __( 'Combination', 'bkx-beauty-wellness' ),
			'sensitive'   => __( 'Sensitive', 'bkx-beauty-wellness' ),
			'mature'      => __( 'Mature', 'bkx-beauty-wellness' ),
		);
	}

	/**
	 * Get skin concern options.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_skin_concern_options(): array {
		return array(
			'acne'           => __( 'Acne', 'bkx-beauty-wellness' ),
			'aging'          => __( 'Aging/Fine Lines', 'bkx-beauty-wellness' ),
			'dark_circles'   => __( 'Dark Circles', 'bkx-beauty-wellness' ),
			'dark_spots'     => __( 'Dark Spots/Hyperpigmentation', 'bkx-beauty-wellness' ),
			'dehydration'    => __( 'Dehydration', 'bkx-beauty-wellness' ),
			'dullness'       => __( 'Dullness', 'bkx-beauty-wellness' ),
			'large_pores'    => __( 'Large Pores', 'bkx-beauty-wellness' ),
			'redness'        => __( 'Redness/Rosacea', 'bkx-beauty-wellness' ),
			'sun_damage'     => __( 'Sun Damage', 'bkx-beauty-wellness' ),
			'texture'        => __( 'Uneven Texture', 'bkx-beauty-wellness' ),
		);
	}

	/**
	 * Get common allergy options.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_common_allergies(): array {
		return array(
			'latex'        => __( 'Latex', 'bkx-beauty-wellness' ),
			'fragrance'    => __( 'Fragrance/Perfume', 'bkx-beauty-wellness' ),
			'parabens'     => __( 'Parabens', 'bkx-beauty-wellness' ),
			'sulfates'     => __( 'Sulfates', 'bkx-beauty-wellness' ),
			'formaldehyde' => __( 'Formaldehyde', 'bkx-beauty-wellness' ),
			'lanolin'      => __( 'Lanolin', 'bkx-beauty-wellness' ),
			'nut_oils'     => __( 'Nut Oils (Almond, etc.)', 'bkx-beauty-wellness' ),
			'essential_oils' => __( 'Essential Oils', 'bkx-beauty-wellness' ),
			'retinol'      => __( 'Retinol/Retinoids', 'bkx-beauty-wellness' ),
			'salicylic'    => __( 'Salicylic Acid', 'bkx-beauty-wellness' ),
			'glycolic'     => __( 'Glycolic Acid', 'bkx-beauty-wellness' ),
			'propylene_glycol' => __( 'Propylene Glycol', 'bkx-beauty-wellness' ),
		);
	}

	/**
	 * Get hair type options.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_hair_type_options(): array {
		return array(
			'straight_fine'    => __( 'Straight - Fine', 'bkx-beauty-wellness' ),
			'straight_medium'  => __( 'Straight - Medium', 'bkx-beauty-wellness' ),
			'straight_coarse'  => __( 'Straight - Coarse', 'bkx-beauty-wellness' ),
			'wavy_fine'        => __( 'Wavy - Fine', 'bkx-beauty-wellness' ),
			'wavy_medium'      => __( 'Wavy - Medium', 'bkx-beauty-wellness' ),
			'wavy_coarse'      => __( 'Wavy - Coarse', 'bkx-beauty-wellness' ),
			'curly_fine'       => __( 'Curly - Fine', 'bkx-beauty-wellness' ),
			'curly_medium'     => __( 'Curly - Medium', 'bkx-beauty-wellness' ),
			'curly_coarse'     => __( 'Curly - Coarse', 'bkx-beauty-wellness' ),
			'coily'            => __( 'Coily/Kinky', 'bkx-beauty-wellness' ),
		);
	}

	/**
	 * Get treatment history for a client.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $limit   Number of records to return.
	 * @return array
	 */
	public function get_treatment_history( int $user_id, int $limit = 20 ): array {
		$args = array(
			'post_type'      => 'bkx_booking',
			'post_status'    => array( 'bkx-completed', 'bkx-ack' ),
			'posts_per_page' => $limit,
			'meta_query'     => array(
				array(
					'key'   => 'customer_id',
					'value' => $user_id,
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query   = new \WP_Query( $args );
		$history = array();

		foreach ( $query->posts as $booking ) {
			$base_id = get_post_meta( $booking->ID, 'base_id', true );
			$seat_id = get_post_meta( $booking->ID, 'seat_id', true );

			$history[] = array(
				'booking_id'     => $booking->ID,
				'date'           => get_post_meta( $booking->ID, 'booking_date', true ),
				'treatment_id'   => $base_id,
				'treatment_name' => get_the_title( $base_id ),
				'stylist_id'     => $seat_id,
				'stylist_name'   => get_the_title( $seat_id ),
				'notes'          => get_post_meta( $booking->ID, '_bkx_treatment_notes', true ),
				'products_used'  => get_post_meta( $booking->ID, '_bkx_products_used', true ) ?: array(),
				'satisfaction'   => get_post_meta( $booking->ID, '_bkx_satisfaction_rating', true ),
			);
		}

		return $history;
	}

	/**
	 * Get product recommendations based on client preferences.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_product_recommendations( int $user_id ): array {
		$preferences    = $this->get_preferences( $user_id );
		$recommendations = array();

		if ( empty( $preferences['skin_type'] ) ) {
			return $recommendations;
		}

		// Get products tagged for this skin type.
		$args = array(
			'post_type'      => 'product', // WooCommerce product.
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'tax_query'      => array(
				array(
					'taxonomy' => 'bkx_skin_type',
					'field'    => 'slug',
					'terms'    => $preferences['skin_type'],
				),
			),
		);

		// Exclude products with client's allergies.
		if ( ! empty( $preferences['allergies'] ) ) {
			$args['meta_query'] = array(
				'relation' => 'AND',
			);

			foreach ( $preferences['allergies'] as $allergy ) {
				$args['meta_query'][] = array(
					'key'     => '_bkx_product_ingredients',
					'value'   => $allergy,
					'compare' => 'NOT LIKE',
				);
			}
		}

		$query = new \WP_Query( $args );

		foreach ( $query->posts as $product ) {
			$recommendations[] = array(
				'id'          => $product->ID,
				'name'        => $product->post_title,
				'description' => $product->post_excerpt,
				'price'       => get_post_meta( $product->ID, '_price', true ),
				'image'       => get_the_post_thumbnail_url( $product->ID, 'thumbnail' ),
				'permalink'   => get_permalink( $product->ID ),
				'reason'      => sprintf(
					/* translators: %s: skin type */
					__( 'Recommended for %s skin', 'bkx-beauty-wellness' ),
					$preferences['skin_type']
				),
			);
		}

		return $recommendations;
	}
}

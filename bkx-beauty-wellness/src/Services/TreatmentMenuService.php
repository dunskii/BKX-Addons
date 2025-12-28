<?php
/**
 * Treatment Menu Service
 *
 * Manages treatment menus with categories, pricing, and duration.
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
 * Class TreatmentMenuService
 *
 * @since 1.0.0
 */
class TreatmentMenuService {

	/**
	 * Get all treatment categories.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_categories(): array {
		$terms = get_terms( array(
			'taxonomy'   => 'bkx_treatment_category',
			'hide_empty' => false,
			'orderby'    => 'menu_order',
			'order'      => 'ASC',
		) );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}

	/**
	 * Get treatments by category.
	 *
	 * @since 1.0.0
	 * @param int $category_id Category term ID.
	 * @return array
	 */
	public function get_treatments_by_category( int $category_id ): array {
		$args = array(
			'post_type'      => 'bkx_base',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'bkx_treatment_category',
					'field'    => 'term_id',
					'terms'    => $category_id,
				),
			),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		$query = new \WP_Query( $args );

		return $this->format_treatments( $query->posts );
	}

	/**
	 * Get full treatment menu organized by category.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_full_menu(): array {
		$menu       = array();
		$categories = $this->get_categories();

		foreach ( $categories as $category ) {
			$treatments = $this->get_treatments_by_category( $category->term_id );

			if ( ! empty( $treatments ) ) {
				$menu[] = array(
					'category'   => array(
						'id'          => $category->term_id,
						'name'        => $category->name,
						'slug'        => $category->slug,
						'description' => $category->description,
						'icon'        => get_term_meta( $category->term_id, 'category_icon', true ),
						'image'       => get_term_meta( $category->term_id, 'category_image', true ),
					),
					'treatments' => $treatments,
				);
			}
		}

		return $menu;
	}

	/**
	 * Get treatment details.
	 *
	 * @since 1.0.0
	 * @param int $treatment_id Treatment (base) post ID.
	 * @return array|null
	 */
	public function get_treatment( int $treatment_id ): ?array {
		$post = get_post( $treatment_id );

		if ( ! $post || 'bkx_base' !== $post->post_type ) {
			return null;
		}

		return $this->format_treatment( $post );
	}

	/**
	 * Get treatment variations.
	 *
	 * @since 1.0.0
	 * @param int $treatment_id Treatment post ID.
	 * @return array
	 */
	public function get_variations( int $treatment_id ): array {
		$variations = get_post_meta( $treatment_id, '_bkx_treatment_variations', true );

		if ( empty( $variations ) || ! is_array( $variations ) ) {
			return array();
		}

		return array_map( function( $variation ) {
			return array(
				'name'     => sanitize_text_field( $variation['name'] ?? '' ),
				'duration' => absint( $variation['duration'] ?? 0 ),
				'price'    => floatval( $variation['price'] ?? 0 ),
				'enabled'  => ! empty( $variation['enabled'] ),
			);
		}, $variations );
	}

	/**
	 * Get available add-ons for a treatment.
	 *
	 * @since 1.0.0
	 * @param int $treatment_id Treatment post ID.
	 * @return array
	 */
	public function get_treatment_addons( int $treatment_id ): array {
		$addon_ids = get_post_meta( $treatment_id, '_bkx_treatment_addons', true );

		if ( empty( $addon_ids ) || ! is_array( $addon_ids ) ) {
			return array();
		}

		$addons = array();

		foreach ( $addon_ids as $addon_id ) {
			$addon_post = get_post( absint( $addon_id ) );

			if ( $addon_post && 'bkx_addition' === $addon_post->post_type ) {
				$addons[] = array(
					'id'          => $addon_post->ID,
					'name'        => $addon_post->post_title,
					'description' => $addon_post->post_excerpt,
					'price'       => floatval( get_post_meta( $addon_post->ID, 'addition_price', true ) ),
					'duration'    => absint( get_post_meta( $addon_post->ID, 'addition_time', true ) ),
				);
			}
		}

		return $addons;
	}

	/**
	 * Get recommended treatments based on skin type.
	 *
	 * @since 1.0.0
	 * @param string $skin_type Skin type slug.
	 * @return array
	 */
	public function get_recommended_for_skin_type( string $skin_type ): array {
		$args = array(
			'post_type'      => 'bkx_base',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
			'tax_query'      => array(
				array(
					'taxonomy' => 'bkx_skin_type',
					'field'    => 'slug',
					'terms'    => $skin_type,
				),
			),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		$query = new \WP_Query( $args );

		return $this->format_treatments( $query->posts );
	}

	/**
	 * Search treatments.
	 *
	 * @since 1.0.0
	 * @param string $search_term Search term.
	 * @param array  $filters     Optional filters.
	 * @return array
	 */
	public function search_treatments( string $search_term, array $filters = array() ): array {
		$args = array(
			'post_type'      => 'bkx_base',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			's'              => sanitize_text_field( $search_term ),
		);

		// Apply category filter.
		if ( ! empty( $filters['category'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'bkx_treatment_category',
				'field'    => 'term_id',
				'terms'    => absint( $filters['category'] ),
			);
		}

		// Apply price range filter.
		if ( ! empty( $filters['min_price'] ) || ! empty( $filters['max_price'] ) ) {
			$args['meta_query'][] = array(
				'key'     => 'base_price',
				'value'   => array(
					floatval( $filters['min_price'] ?? 0 ),
					floatval( $filters['max_price'] ?? 99999 ),
				),
				'compare' => 'BETWEEN',
				'type'    => 'DECIMAL(10,2)',
			);
		}

		// Apply duration filter.
		if ( ! empty( $filters['max_duration'] ) ) {
			$args['meta_query'][] = array(
				'key'     => 'base_time',
				'value'   => absint( $filters['max_duration'] ),
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}

		$query = new \WP_Query( $args );

		return $this->format_treatments( $query->posts );
	}

	/**
	 * Get featured treatments.
	 *
	 * @since 1.0.0
	 * @param int $limit Number of treatments to return.
	 * @return array
	 */
	public function get_featured_treatments( int $limit = 6 ): array {
		$args = array(
			'post_type'      => 'bkx_base',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'meta_query'     => array(
				array(
					'key'   => '_bkx_featured_treatment',
					'value' => '1',
				),
			),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		$query = new \WP_Query( $args );

		return $this->format_treatments( $query->posts );
	}

	/**
	 * Format treatment posts array.
	 *
	 * @since 1.0.0
	 * @param array $posts Array of WP_Post objects.
	 * @return array
	 */
	private function format_treatments( array $posts ): array {
		return array_map( array( $this, 'format_treatment' ), $posts );
	}

	/**
	 * Format single treatment post.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Treatment post object.
	 * @return array
	 */
	private function format_treatment( \WP_Post $post ): array {
		$image_id = get_post_thumbnail_id( $post->ID );

		return array(
			'id'                  => $post->ID,
			'name'                => $post->post_title,
			'slug'                => $post->post_name,
			'description'         => $post->post_content,
			'short_description'   => $post->post_excerpt,
			'price'               => floatval( get_post_meta( $post->ID, 'base_price', true ) ),
			'duration'            => absint( get_post_meta( $post->ID, 'base_time', true ) ),
			'image'               => $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '',
			'image_large'         => $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '',
			'categories'          => wp_get_post_terms( $post->ID, 'bkx_treatment_category', array( 'fields' => 'names' ) ),
			'skin_types'          => wp_get_post_terms( $post->ID, 'bkx_skin_type', array( 'fields' => 'names' ) ),
			'is_featured'         => (bool) get_post_meta( $post->ID, '_bkx_featured_treatment', true ),
			'consultation_required' => (bool) get_post_meta( $post->ID, '_bkx_consultation_required', true ),
			'contraindications'   => get_post_meta( $post->ID, '_bkx_contraindications', true ) ?: array(),
			'aftercare_notes'     => get_post_meta( $post->ID, '_bkx_aftercare_notes', true ) ?: '',
			'variations'          => $this->get_variations( $post->ID ),
			'permalink'           => get_permalink( $post->ID ),
		);
	}

	/**
	 * Render treatment menu HTML.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_menu( array $atts = array() ): string {
		$atts = shortcode_atts( array(
			'category'    => '',
			'columns'     => 3,
			'show_price'  => 'yes',
			'show_duration' => 'yes',
			'show_image'  => 'yes',
			'style'       => 'grid',
		), $atts );

		if ( ! empty( $atts['category'] ) ) {
			$term = get_term_by( 'slug', $atts['category'], 'bkx_treatment_category' );
			$menu = $term ? array( array(
				'category'   => array(
					'id'   => $term->term_id,
					'name' => $term->name,
				),
				'treatments' => $this->get_treatments_by_category( $term->term_id ),
			) ) : array();
		} else {
			$menu = $this->get_full_menu();
		}

		ob_start();

		$template = BKX_BEAUTY_WELLNESS_PATH . 'templates/treatment-menu.php';

		if ( file_exists( $template ) ) {
			include $template;
		} else {
			$this->render_default_menu( $menu, $atts );
		}

		return ob_get_clean();
	}

	/**
	 * Render default menu template.
	 *
	 * @since 1.0.0
	 * @param array $menu Menu data.
	 * @param array $atts Attributes.
	 * @return void
	 */
	private function render_default_menu( array $menu, array $atts ): void {
		$columns_class = 'bkx-treatment-columns-' . absint( $atts['columns'] );
		$style_class   = 'bkx-treatment-style-' . sanitize_html_class( $atts['style'] );
		?>
		<div class="bkx-treatment-menu <?php echo esc_attr( $columns_class . ' ' . $style_class ); ?>">
			<?php foreach ( $menu as $section ) : ?>
				<div class="bkx-treatment-category">
					<h3 class="bkx-category-title"><?php echo esc_html( $section['category']['name'] ); ?></h3>

					<div class="bkx-treatments-grid">
						<?php foreach ( $section['treatments'] as $treatment ) : ?>
							<div class="bkx-treatment-card">
								<?php if ( 'yes' === $atts['show_image'] && ! empty( $treatment['image'] ) ) : ?>
									<div class="bkx-treatment-image">
										<img src="<?php echo esc_url( $treatment['image'] ); ?>" alt="<?php echo esc_attr( $treatment['name'] ); ?>">
									</div>
								<?php endif; ?>

								<div class="bkx-treatment-content">
									<h4 class="bkx-treatment-name">
										<a href="<?php echo esc_url( $treatment['permalink'] ); ?>">
											<?php echo esc_html( $treatment['name'] ); ?>
										</a>
									</h4>

									<?php if ( ! empty( $treatment['short_description'] ) ) : ?>
										<p class="bkx-treatment-description">
											<?php echo esc_html( $treatment['short_description'] ); ?>
										</p>
									<?php endif; ?>

									<div class="bkx-treatment-meta">
										<?php if ( 'yes' === $atts['show_duration'] ) : ?>
											<span class="bkx-treatment-duration">
												<?php
												printf(
													/* translators: %d: duration in minutes */
													esc_html__( '%d min', 'bkx-beauty-wellness' ),
													$treatment['duration']
												);
												?>
											</span>
										<?php endif; ?>

										<?php if ( 'yes' === $atts['show_price'] ) : ?>
											<span class="bkx-treatment-price">
												<?php echo wp_kses_post( wc_price( $treatment['price'] ) ); ?>
											</span>
										<?php endif; ?>
									</div>

									<a href="<?php echo esc_url( $treatment['permalink'] ); ?>" class="bkx-book-treatment-btn">
										<?php esc_html_e( 'Book Now', 'bkx-beauty-wellness' ); ?>
									</a>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

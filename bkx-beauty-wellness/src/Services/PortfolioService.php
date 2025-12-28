<?php
/**
 * Portfolio Service
 *
 * Manages stylist portfolios with before/after photos and work samples.
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
 * Class PortfolioService
 *
 * @since 1.0.0
 */
class PortfolioService {

	/**
	 * Database table name for portfolio items.
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
		$this->table_name = $wpdb->prefix . 'bkx_stylist_portfolio';
	}

	/**
	 * Get portfolio for a stylist.
	 *
	 * @since 1.0.0
	 * @param int   $stylist_id Stylist (seat) post ID.
	 * @param array $filters    Optional filters.
	 * @return array
	 */
	public function get_portfolio( int $stylist_id, array $filters = array() ): array {
		global $wpdb;

		$where = array( 'stylist_id = %d' );
		$args  = array( $stylist_id );

		// Category filter.
		if ( ! empty( $filters['category'] ) ) {
			$where[] = 'category = %s';
			$args[]  = sanitize_text_field( $filters['category'] );
		}

		// Type filter (before_after, single, video).
		if ( ! empty( $filters['type'] ) ) {
			$where[] = 'type = %s';
			$args[]  = sanitize_text_field( $filters['type'] );
		}

		// Featured only.
		if ( ! empty( $filters['featured'] ) ) {
			$where[] = 'is_featured = 1';
		}

		$where_clause = implode( ' AND ', $where );
		$limit        = absint( $filters['limit'] ?? 20 );
		$offset       = absint( $filters['offset'] ?? 0 );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE {$where_clause}
				ORDER BY is_featured DESC, created_at DESC
				LIMIT %d OFFSET %d",
				array_merge( $args, array( $limit, $offset ) )
			),
			ARRAY_A
		);

		return array_map( array( $this, 'format_portfolio_item' ), $results ?: array() );
	}

	/**
	 * Get single portfolio item.
	 *
	 * @since 1.0.0
	 * @param int $item_id Portfolio item ID.
	 * @return array|null
	 */
	public function get_item( int $item_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$item_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		return $this->format_portfolio_item( $row );
	}

	/**
	 * Add portfolio item.
	 *
	 * @since 1.0.0
	 * @param int   $stylist_id Stylist post ID.
	 * @param array $data       Portfolio item data.
	 * @return int|false Item ID on success, false on failure.
	 */
	public function add_item( int $stylist_id, array $data ) {
		global $wpdb;

		$insert_data = array(
			'stylist_id'       => $stylist_id,
			'title'            => sanitize_text_field( $data['title'] ?? '' ),
			'description'      => sanitize_textarea_field( $data['description'] ?? '' ),
			'type'             => sanitize_text_field( $data['type'] ?? 'single' ),
			'category'         => sanitize_text_field( $data['category'] ?? '' ),
			'before_image_id'  => absint( $data['before_image_id'] ?? 0 ),
			'after_image_id'   => absint( $data['after_image_id'] ?? 0 ),
			'image_id'         => absint( $data['image_id'] ?? 0 ),
			'video_url'        => esc_url_raw( $data['video_url'] ?? '' ),
			'treatment_ids'    => wp_json_encode( $data['treatment_ids'] ?? array() ),
			'products_used'    => wp_json_encode( $data['products_used'] ?? array() ),
			'techniques'       => wp_json_encode( $data['techniques'] ?? array() ),
			'client_testimonial' => sanitize_textarea_field( $data['client_testimonial'] ?? '' ),
			'is_featured'      => ! empty( $data['is_featured'] ) ? 1 : 0,
			'is_public'        => isset( $data['is_public'] ) ? ( $data['is_public'] ? 1 : 0 ) : 1,
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$this->table_name,
			$insert_data,
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$item_id = $wpdb->insert_id;

		/**
		 * Fires after a portfolio item is added.
		 *
		 * @param int   $item_id    Portfolio item ID.
		 * @param int   $stylist_id Stylist ID.
		 * @param array $data       Portfolio data.
		 */
		do_action( 'bkx_beauty_wellness_portfolio_item_added', $item_id, $stylist_id, $data );

		return $item_id;
	}

	/**
	 * Update portfolio item.
	 *
	 * @since 1.0.0
	 * @param int   $item_id Portfolio item ID.
	 * @param array $data    Updated data.
	 * @return bool
	 */
	public function update_item( int $item_id, array $data ): bool {
		global $wpdb;

		$update_data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		$allowed_fields = array(
			'title', 'description', 'type', 'category', 'before_image_id',
			'after_image_id', 'image_id', 'video_url', 'treatment_ids',
			'products_used', 'techniques', 'client_testimonial', 'is_featured', 'is_public',
		);

		foreach ( $allowed_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				switch ( $field ) {
					case 'title':
					case 'type':
					case 'category':
						$update_data[ $field ] = sanitize_text_field( $data[ $field ] );
						break;

					case 'description':
					case 'client_testimonial':
						$update_data[ $field ] = sanitize_textarea_field( $data[ $field ] );
						break;

					case 'before_image_id':
					case 'after_image_id':
					case 'image_id':
						$update_data[ $field ] = absint( $data[ $field ] );
						break;

					case 'video_url':
						$update_data[ $field ] = esc_url_raw( $data[ $field ] );
						break;

					case 'treatment_ids':
					case 'products_used':
					case 'techniques':
						$update_data[ $field ] = wp_json_encode( $data[ $field ] );
						break;

					case 'is_featured':
					case 'is_public':
						$update_data[ $field ] = $data[ $field ] ? 1 : 0;
						break;
				}
			}
		}

		$result = $wpdb->update(
			$this->table_name,
			$update_data,
			array( 'id' => $item_id )
		);

		return false !== $result;
	}

	/**
	 * Delete portfolio item.
	 *
	 * @since 1.0.0
	 * @param int $item_id Portfolio item ID.
	 * @return bool
	 */
	public function delete_item( int $item_id ): bool {
		global $wpdb;

		// Get item before deleting for cleanup.
		$item = $this->get_item( $item_id );

		if ( ! $item ) {
			return false;
		}

		$result = $wpdb->delete(
			$this->table_name,
			array( 'id' => $item_id ),
			array( '%d' )
		);

		if ( $result ) {
			/**
			 * Fires after a portfolio item is deleted.
			 *
			 * @param int   $item_id Portfolio item ID.
			 * @param array $item    Deleted item data.
			 */
			do_action( 'bkx_beauty_wellness_portfolio_item_deleted', $item_id, $item );
		}

		return (bool) $result;
	}

	/**
	 * Get all stylists with portfolios.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_stylists_with_portfolios(): array {
		$args = array(
			'post_type'      => 'bkx_seat',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_bkx_has_portfolio',
					'value'   => '1',
					'compare' => '=',
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$query    = new \WP_Query( $args );
		$stylists = array();

		foreach ( $query->posts as $stylist ) {
			$portfolio_count = $this->get_portfolio_count( $stylist->ID );

			if ( $portfolio_count > 0 ) {
				$stylists[] = array(
					'id'              => $stylist->ID,
					'name'            => $stylist->post_title,
					'slug'            => $stylist->post_name,
					'image'           => get_the_post_thumbnail_url( $stylist->ID, 'thumbnail' ),
					'specializations' => wp_get_post_terms( $stylist->ID, 'bkx_specialization', array( 'fields' => 'names' ) ),
					'portfolio_count' => $portfolio_count,
					'featured_item'   => $this->get_featured_item( $stylist->ID ),
				);
			}
		}

		return $stylists;
	}

	/**
	 * Get portfolio count for a stylist.
	 *
	 * @since 1.0.0
	 * @param int $stylist_id Stylist post ID.
	 * @return int
	 */
	public function get_portfolio_count( int $stylist_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE stylist_id = %d AND is_public = 1",
				$stylist_id
			)
		);
	}

	/**
	 * Get featured portfolio item for a stylist.
	 *
	 * @since 1.0.0
	 * @param int $stylist_id Stylist post ID.
	 * @return array|null
	 */
	public function get_featured_item( int $stylist_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE stylist_id = %d AND is_public = 1
				ORDER BY is_featured DESC, created_at DESC
				LIMIT 1",
				$stylist_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		return $this->format_portfolio_item( $row );
	}

	/**
	 * Get portfolio categories.
	 *
	 * @since 1.0.0
	 * @param int $stylist_id Optional stylist ID to filter.
	 * @return array
	 */
	public function get_categories( int $stylist_id = 0 ): array {
		global $wpdb;

		$where = 'is_public = 1';
		$args  = array();

		if ( $stylist_id > 0 ) {
			$where .= ' AND stylist_id = %d';
			$args[] = $stylist_id;
		}

		$categories = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT category FROM {$this->table_name} WHERE {$where} AND category != '' ORDER BY category ASC",
				$args
			)
		);

		return $categories ?: array();
	}

	/**
	 * Render portfolio gallery HTML.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_gallery( array $atts = array() ): string {
		$atts = shortcode_atts( array(
			'stylist'  => 0,
			'category' => '',
			'columns'  => 3,
			'limit'    => 12,
			'type'     => '',
			'style'    => 'grid',
		), $atts );

		$filters = array(
			'limit' => absint( $atts['limit'] ),
		);

		if ( ! empty( $atts['category'] ) ) {
			$filters['category'] = $atts['category'];
		}

		if ( ! empty( $atts['type'] ) ) {
			$filters['type'] = $atts['type'];
		}

		if ( $atts['stylist'] > 0 ) {
			$items = $this->get_portfolio( absint( $atts['stylist'] ), $filters );
		} else {
			// Get from all stylists.
			$items = $this->get_all_portfolio_items( $filters );
		}

		ob_start();

		$template = BKX_BEAUTY_WELLNESS_PATH . 'templates/portfolio-gallery.php';

		if ( file_exists( $template ) ) {
			include $template;
		} else {
			$this->render_default_gallery( $items, $atts );
		}

		return ob_get_clean();
	}

	/**
	 * Get all portfolio items across stylists.
	 *
	 * @since 1.0.0
	 * @param array $filters Filters.
	 * @return array
	 */
	private function get_all_portfolio_items( array $filters = array() ): array {
		global $wpdb;

		$where = array( 'is_public = 1' );
		$args  = array();

		if ( ! empty( $filters['category'] ) ) {
			$where[] = 'category = %s';
			$args[]  = sanitize_text_field( $filters['category'] );
		}

		if ( ! empty( $filters['type'] ) ) {
			$where[] = 'type = %s';
			$args[]  = sanitize_text_field( $filters['type'] );
		}

		$where_clause = implode( ' AND ', $where );
		$limit        = absint( $filters['limit'] ?? 20 );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, s.post_title as stylist_name
				FROM {$this->table_name} p
				LEFT JOIN {$wpdb->posts} s ON p.stylist_id = s.ID
				WHERE {$where_clause}
				ORDER BY p.is_featured DESC, p.created_at DESC
				LIMIT %d",
				array_merge( $args, array( $limit ) )
			),
			ARRAY_A
		);

		return array_map( array( $this, 'format_portfolio_item' ), $results ?: array() );
	}

	/**
	 * Format portfolio item data.
	 *
	 * @since 1.0.0
	 * @param array $row Database row.
	 * @return array
	 */
	private function format_portfolio_item( array $row ): array {
		return array(
			'id'                => absint( $row['id'] ),
			'stylist_id'        => absint( $row['stylist_id'] ),
			'stylist_name'      => $row['stylist_name'] ?? get_the_title( $row['stylist_id'] ),
			'title'             => $row['title'],
			'description'       => $row['description'],
			'type'              => $row['type'],
			'category'          => $row['category'],
			'before_image'      => $row['before_image_id'] ? wp_get_attachment_image_url( $row['before_image_id'], 'medium' ) : '',
			'before_image_full' => $row['before_image_id'] ? wp_get_attachment_image_url( $row['before_image_id'], 'full' ) : '',
			'after_image'       => $row['after_image_id'] ? wp_get_attachment_image_url( $row['after_image_id'], 'medium' ) : '',
			'after_image_full'  => $row['after_image_id'] ? wp_get_attachment_image_url( $row['after_image_id'], 'full' ) : '',
			'image'             => $row['image_id'] ? wp_get_attachment_image_url( $row['image_id'], 'medium' ) : '',
			'image_full'        => $row['image_id'] ? wp_get_attachment_image_url( $row['image_id'], 'full' ) : '',
			'video_url'         => $row['video_url'],
			'treatment_ids'     => json_decode( $row['treatment_ids'] ?: '[]', true ),
			'products_used'     => json_decode( $row['products_used'] ?: '[]', true ),
			'techniques'        => json_decode( $row['techniques'] ?: '[]', true ),
			'client_testimonial' => $row['client_testimonial'],
			'is_featured'       => (bool) $row['is_featured'],
			'is_public'         => (bool) $row['is_public'],
			'created_at'        => $row['created_at'],
		);
	}

	/**
	 * Render default gallery template.
	 *
	 * @since 1.0.0
	 * @param array $items Portfolio items.
	 * @param array $atts  Attributes.
	 * @return void
	 */
	private function render_default_gallery( array $items, array $atts ): void {
		$columns_class = 'bkx-portfolio-columns-' . absint( $atts['columns'] );
		$style_class   = 'bkx-portfolio-style-' . sanitize_html_class( $atts['style'] );
		?>
		<div class="bkx-portfolio-gallery <?php echo esc_attr( $columns_class . ' ' . $style_class ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<div class="bkx-portfolio-item bkx-portfolio-type-<?php echo esc_attr( $item['type'] ); ?>" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">

					<?php if ( 'before_after' === $item['type'] && $item['before_image'] && $item['after_image'] ) : ?>
						<div class="bkx-before-after-container">
							<div class="bkx-before-image">
								<span class="bkx-label"><?php esc_html_e( 'Before', 'bkx-beauty-wellness' ); ?></span>
								<img src="<?php echo esc_url( $item['before_image'] ); ?>" alt="<?php esc_attr_e( 'Before', 'bkx-beauty-wellness' ); ?>">
							</div>
							<div class="bkx-after-image">
								<span class="bkx-label"><?php esc_html_e( 'After', 'bkx-beauty-wellness' ); ?></span>
								<img src="<?php echo esc_url( $item['after_image'] ); ?>" alt="<?php esc_attr_e( 'After', 'bkx-beauty-wellness' ); ?>">
							</div>
						</div>
					<?php elseif ( 'video' === $item['type'] && $item['video_url'] ) : ?>
						<div class="bkx-video-container">
							<?php echo wp_oembed_get( $item['video_url'] ); ?>
						</div>
					<?php elseif ( $item['image'] ) : ?>
						<div class="bkx-single-image">
							<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>">
						</div>
					<?php endif; ?>

					<div class="bkx-portfolio-content">
						<h4 class="bkx-portfolio-title"><?php echo esc_html( $item['title'] ); ?></h4>

						<?php if ( ! empty( $item['stylist_name'] ) ) : ?>
							<p class="bkx-portfolio-stylist">
								<?php
								printf(
									/* translators: %s: stylist name */
									esc_html__( 'By %s', 'bkx-beauty-wellness' ),
									esc_html( $item['stylist_name'] )
								);
								?>
							</p>
						<?php endif; ?>

						<?php if ( ! empty( $item['category'] ) ) : ?>
							<span class="bkx-portfolio-category"><?php echo esc_html( $item['category'] ); ?></span>
						<?php endif; ?>

						<?php if ( ! empty( $item['client_testimonial'] ) ) : ?>
							<blockquote class="bkx-testimonial">
								<?php echo esc_html( $item['client_testimonial'] ); ?>
							</blockquote>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

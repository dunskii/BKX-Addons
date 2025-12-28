<?php
/**
 * Class List Template
 *
 * @package BookingX\ClassBookings
 * @since   1.0.0
 *
 * @var array $classes Array of class posts.
 * @var array $atts    Shortcode attributes.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$columns = isset( $atts['columns'] ) ? absint( $atts['columns'] ) : 3;
?>

<div class="bkx-class-list bkx-columns-<?php echo esc_attr( $columns ); ?>">
	<?php if ( empty( $classes ) ) : ?>
		<p class="bkx-no-classes"><?php esc_html_e( 'No classes available.', 'bkx-class-bookings' ); ?></p>
	<?php else : ?>
		<div class="bkx-class-grid">
			<?php foreach ( $classes as $class ) : ?>
				<?php
				$meta       = get_post_meta( $class->ID );
				$duration   = $meta['_bkx_class_duration'][0] ?? 60;
				$price      = $meta['_bkx_class_price'][0] ?? 0;
				$capacity   = $meta['_bkx_class_capacity'][0] ?? 10;
				$color      = $meta['_bkx_class_color'][0] ?? '#3788d8';
				$location   = $meta['_bkx_class_location'][0] ?? '';
				$instructor = $meta['_bkx_class_instructor_id'][0] ?? 0;
				$thumbnail  = get_the_post_thumbnail_url( $class->ID, 'medium' );

				$categories = get_the_terms( $class->ID, 'bkx_class_category' );
				?>
				<div class="bkx-class-card" style="--bkx-class-color: <?php echo esc_attr( $color ); ?>;">
					<?php if ( $thumbnail ) : ?>
						<div class="bkx-class-image">
							<a href="<?php echo esc_url( get_permalink( $class->ID ) ); ?>">
								<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $class->post_title ); ?>">
							</a>
						</div>
					<?php endif; ?>

					<div class="bkx-class-content">
						<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
							<div class="bkx-class-categories">
								<?php foreach ( $categories as $category ) : ?>
									<span class="bkx-category-badge"><?php echo esc_html( $category->name ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<h3 class="bkx-class-title">
							<a href="<?php echo esc_url( get_permalink( $class->ID ) ); ?>">
								<?php echo esc_html( $class->post_title ); ?>
							</a>
						</h3>

						<?php if ( $class->post_excerpt ) : ?>
							<p class="bkx-class-excerpt"><?php echo esc_html( $class->post_excerpt ); ?></p>
						<?php endif; ?>

						<div class="bkx-class-meta">
							<span class="bkx-meta-item bkx-duration">
								<span class="dashicons dashicons-clock"></span>
								<?php
								printf(
									/* translators: %d: duration in minutes */
									esc_html__( '%d min', 'bkx-class-bookings' ),
									esc_html( $duration )
								);
								?>
							</span>
							<span class="bkx-meta-item bkx-capacity">
								<span class="dashicons dashicons-groups"></span>
								<?php
								printf(
									/* translators: %d: max capacity */
									esc_html__( 'Max %d', 'bkx-class-bookings' ),
									esc_html( $capacity )
								);
								?>
							</span>
							<?php if ( $location ) : ?>
								<span class="bkx-meta-item bkx-location">
									<span class="dashicons dashicons-location"></span>
									<?php echo esc_html( $location ); ?>
								</span>
							<?php endif; ?>
						</div>

						<?php if ( $instructor ) : ?>
							<?php $instructor_post = get_post( $instructor ); ?>
							<?php if ( $instructor_post ) : ?>
								<div class="bkx-class-instructor">
									<span class="dashicons dashicons-admin-users"></span>
									<?php echo esc_html( $instructor_post->post_title ); ?>
								</div>
							<?php endif; ?>
						<?php endif; ?>

						<div class="bkx-class-footer">
							<?php if ( $price > 0 ) : ?>
								<span class="bkx-class-price">
									<?php echo esc_html( get_woocommerce_currency_symbol() ?? '$' ); ?><?php echo esc_html( number_format( $price, 2 ) ); ?>
								</span>
							<?php else : ?>
								<span class="bkx-class-price bkx-price-free"><?php esc_html_e( 'Free', 'bkx-class-bookings' ); ?></span>
							<?php endif; ?>
							<a href="<?php echo esc_url( get_permalink( $class->ID ) ); ?>" class="button bkx-view-class">
								<?php esc_html_e( 'View Schedule', 'bkx-class-bookings' ); ?>
							</a>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

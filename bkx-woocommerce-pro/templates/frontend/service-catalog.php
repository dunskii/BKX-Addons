<?php
/**
 * Service Catalog Template.
 *
 * @package BookingX\WooCommercePro
 * @since   1.0.0
 *
 * @var array $atts Shortcode attributes.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BookingX\WooCommercePro\Services\ProductIntegration;

$columns     = absint( $atts['columns'] );
$show_price  = 'yes' === $atts['show_price'];
$show_desc   = 'yes' === $atts['show_desc'];
$add_to_cart = 'yes' === $atts['add_to_cart'];
$seat        = absint( $atts['seat'] );
$category    = sanitize_text_field( $atts['category'] );

// Get services.
$args = array(
	'post_type'      => 'bkx_base',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
	'orderby'        => 'title',
	'order'          => 'ASC',
);

if ( $category ) {
	$args['tax_query'] = array(
		array(
			'taxonomy' => 'bkx_base_category',
			'field'    => 'slug',
			'terms'    => $category,
		),
	);
}

$services = get_posts( $args );

if ( empty( $services ) ) {
	echo '<p class="bkx-no-services">' . esc_html__( 'No services found.', 'bkx-woocommerce-pro' ) . '</p>';
	return;
}

$product_integration = ProductIntegration::get_instance();
?>
<div class="bkx-service-catalog" style="--columns: <?php echo esc_attr( $columns ); ?>">
	<?php foreach ( $services as $service ) : ?>
		<?php
		$price      = get_post_meta( $service->ID, 'base_price', true );
		$duration   = get_post_meta( $service->ID, 'base_time', true );
		$product_id = $product_integration->get_product_for_service( $service->ID );
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		// Format duration.
		$duration_formatted = '';
		if ( $duration ) {
			$hours = floor( $duration / 60 );
			$mins  = $duration % 60;

			if ( $hours && $mins ) {
				$duration_formatted = sprintf( '%dh %dm', $hours, $mins );
			} elseif ( $hours ) {
				$duration_formatted = sprintf( '%d hour%s', $hours, $hours > 1 ? 's' : '' );
			} else {
				$duration_formatted = sprintf( '%d min', $mins );
			}
		}

		$book_url = $product ? $product->get_permalink() : get_permalink( $service->ID );
		?>
		<div class="bkx-service-card">
			<div class="service-image">
				<?php if ( has_post_thumbnail( $service->ID ) ) : ?>
					<?php echo get_the_post_thumbnail( $service->ID, 'medium' ); ?>
				<?php else : ?>
					<div class="placeholder-image"></div>
				<?php endif; ?>
			</div>

			<div class="service-content">
				<h3 class="service-title">
					<?php echo esc_html( $service->post_title ); ?>
				</h3>

				<?php if ( $show_desc && $service->post_excerpt ) : ?>
					<div class="service-description">
						<?php echo wp_kses_post( $service->post_excerpt ); ?>
					</div>
				<?php endif; ?>

				<div class="service-meta">
					<?php if ( $show_price && $price ) : ?>
						<span class="service-price">
							<?php echo wc_price( $price ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $duration_formatted ) : ?>
						<span class="service-duration">
							<?php echo esc_html( $duration_formatted ); ?>
						</span>
					<?php endif; ?>
				</div>

				<?php if ( $add_to_cart ) : ?>
					<?php if ( $product && ! $product->requires_date_selection() ) : ?>
						<a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
						   class="book-button add_to_cart_button ajax_add_to_cart"
						   data-product_id="<?php echo esc_attr( $product_id ); ?>"
						   data-product_sku="<?php echo esc_attr( $product->get_sku() ); ?>"
						   data-quantity="1"
						   data-requires-date="false">
							<?php esc_html_e( 'Add to Cart', 'bkx-woocommerce-pro' ); ?>
						</a>
					<?php else : ?>
						<a href="<?php echo esc_url( $book_url ); ?>"
						   class="book-button"
						   data-product-id="<?php echo esc_attr( $product_id ); ?>"
						   data-requires-date="true">
							<?php esc_html_e( 'Book Now', 'bkx-woocommerce-pro' ); ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>

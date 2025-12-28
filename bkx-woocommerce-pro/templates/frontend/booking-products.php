<?php
/**
 * Booking Products Grid Template.
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

$columns  = absint( $atts['columns'] );
$limit    = absint( $atts['limit'] );
$orderby  = sanitize_text_field( $atts['orderby'] );
$order    = sanitize_text_field( $atts['order'] );
$category = sanitize_text_field( $atts['category'] );
$seat     = absint( $atts['seat'] );

// Build query args.
$args = array(
	'status'  => 'publish',
	'limit'   => $limit,
	'orderby' => $orderby,
	'order'   => $order,
	'type'    => 'bkx_booking',
);

if ( $category ) {
	$args['category'] = array( $category );
}

$products = wc_get_products( $args );

if ( empty( $products ) ) {
	echo '<p class="bkx-no-products">' . esc_html__( 'No booking products found.', 'bkx-woocommerce-pro' ) . '</p>';
	return;
}
?>
<div class="bkx-booking-products woocommerce columns-<?php echo esc_attr( $columns ); ?>">
	<ul class="products columns-<?php echo esc_attr( $columns ); ?>">
		<?php foreach ( $products as $product ) : ?>
			<?php
			$post_object = get_post( $product->get_id() );
			setup_postdata( $GLOBALS['post'] =& $post_object ); // phpcs:ignore
			?>
			<li <?php wc_product_class( '', $product ); ?>>
				<?php
				/**
				 * Hook: woocommerce_before_shop_loop_item.
				 */
				do_action( 'woocommerce_before_shop_loop_item' );

				/**
				 * Hook: woocommerce_before_shop_loop_item_title.
				 */
				do_action( 'woocommerce_before_shop_loop_item_title' );
				?>

				<div class="bkx-product-content">
					<h2 class="woocommerce-loop-product__title">
						<?php echo esc_html( $product->get_name() ); ?>
					</h2>

					<?php if ( $product->get_short_description() ) : ?>
						<div class="bkx-product-description">
							<?php echo wp_kses_post( $product->get_short_description() ); ?>
						</div>
					<?php endif; ?>

					<div class="bkx-product-meta">
						<?php if ( method_exists( $product, 'get_formatted_duration' ) ) : ?>
							<span class="bkx-product-duration">
								<span class="dashicons dashicons-clock"></span>
								<?php echo esc_html( $product->get_formatted_duration() ); ?>
							</span>
						<?php endif; ?>
					</div>

					<?php
					/**
					 * Hook: woocommerce_after_shop_loop_item_title.
					 */
					do_action( 'woocommerce_after_shop_loop_item_title' );

					/**
					 * Hook: woocommerce_after_shop_loop_item.
					 */
					do_action( 'woocommerce_after_shop_loop_item' );
					?>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
<?php
wp_reset_postdata();

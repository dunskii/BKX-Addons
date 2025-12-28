<?php
/**
 * Quantity Field Template
 *
 * @package BookingX\GroupBookings
 * @since   1.0.0
 *
 * @var int    $min_qty   Minimum quantity.
 * @var int    $max_qty   Maximum quantity.
 * @var int    $base_id   Service post ID.
 * @var string $label     Field label.
 * @var float  $base_price Base price per unit.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="bkx-quantity-wrapper">
	<?php if ( ! empty( $label ) ) : ?>
		<label for="bkx_quantity" class="bkx-quantity-label"><?php echo esc_html( $label ); ?></label>
	<?php endif; ?>

	<div class="bkx-quantity-control">
		<button type="button" class="bkx-qty-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'bkx-group-bookings' ); ?>">-</button>
		<input type="number" id="bkx_quantity" name="bkx_quantity"
			   value="<?php echo esc_attr( $min_qty ); ?>"
			   min="<?php echo esc_attr( $min_qty ); ?>"
			   max="<?php echo esc_attr( $max_qty ); ?>"
			   class="bkx-quantity-input"
			   data-base-id="<?php echo esc_attr( $base_id ); ?>"
			   data-base-price="<?php echo esc_attr( $base_price ); ?>">
		<button type="button" class="bkx-qty-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'bkx-group-bookings' ); ?>">+</button>
	</div>

	<div class="bkx-quantity-info">
		<span class="bkx-qty-range">
			<?php
			printf(
				/* translators: 1: minimum, 2: maximum */
				esc_html__( '%1$d - %2$d people', 'bkx-group-bookings' ),
				esc_html( $min_qty ),
				esc_html( $max_qty )
			);
			?>
		</span>
	</div>

	<div class="bkx-price-preview">
		<span class="bkx-price-label"><?php esc_html_e( 'Estimated total:', 'bkx-group-bookings' ); ?></span>
		<span class="bkx-price-value" id="bkx-group-total">
			<?php echo esc_html( function_exists( 'wc_price' ) ? wc_price( $base_price * $min_qty ) : '$' . number_format( $base_price * $min_qty, 2 ) ); ?>
		</span>
	</div>

	<div class="bkx-price-breakdown" id="bkx-price-breakdown" style="display: none;">
		<!-- Populated via JS -->
	</div>
</div>

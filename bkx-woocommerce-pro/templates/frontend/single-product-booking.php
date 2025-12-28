<?php
/**
 * Single Booking Product Template.
 *
 * @package BookingX\WooCommercePro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( ! $product || 'bkx_booking' !== $product->get_type() ) {
	return;
}

$service      = $product->get_linked_service();
$seat         = $product->get_linked_seat();
$extras       = $product->get_allowed_extras();
$requires_date = $product->requires_date_selection();
$requires_seat = $product->requires_seat_selection();

// Get all available seats for this service.
$available_seats = array();
if ( $requires_seat && ! $seat ) {
	$available_seats = get_posts( array(
		'post_type'      => 'bkx_seat',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
}

// Get seat alias.
$seat_alias = get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-woocommerce-pro' ) );
?>
<div class="bkx-woo-booking-form"
	 data-base-price="<?php echo esc_attr( $product->get_price() ); ?>"
	 data-requires-date="<?php echo $requires_date ? 'true' : 'false'; ?>"
	 data-requires-seat="<?php echo $requires_seat ? 'true' : 'false'; ?>">

	<?php do_action( 'bkx_woo_before_booking_form', $product ); ?>

	<input type="hidden" name="bkx_service_id" value="<?php echo esc_attr( $product->get_linked_service_id() ); ?>">
	<input type="hidden" id="bkx_booking_date" name="bkx_booking_date" value="">
	<input type="hidden" id="bkx_booking_time" name="bkx_booking_time" value="">

	<?php if ( $seat ) : ?>
		<input type="hidden" name="bkx_seat_id" value="<?php echo esc_attr( $seat->ID ); ?>">
	<?php endif; ?>

	<!-- Duration Display -->
	<?php if ( $product->get_booking_duration() ) : ?>
		<div class="bkx-booking-duration">
			<span class="duration-label"><?php esc_html_e( 'Duration:', 'bkx-woocommerce-pro' ); ?></span>
			<span class="duration-value"><?php echo esc_html( $product->get_formatted_duration() ); ?></span>
		</div>
	<?php endif; ?>

	<!-- Resource/Staff Selection -->
	<?php if ( $requires_seat && ! empty( $available_seats ) ) : ?>
		<div class="form-row bkx-seat-selection">
			<label><?php echo esc_html( sprintf( __( 'Select %s', 'bkx-woocommerce-pro' ), $seat_alias ) ); ?></label>
			<div class="bkx-resource-grid">
				<?php foreach ( $available_seats as $available_seat ) : ?>
					<label class="bkx-resource-card">
						<input type="radio" name="bkx_seat_id" value="<?php echo esc_attr( $available_seat->ID ); ?>">
						<div class="resource-image">
							<?php if ( has_post_thumbnail( $available_seat->ID ) ) : ?>
								<?php echo get_the_post_thumbnail( $available_seat->ID, 'thumbnail' ); ?>
							<?php else : ?>
								<div class="placeholder"></div>
							<?php endif; ?>
						</div>
						<span class="resource-name"><?php echo esc_html( $available_seat->post_title ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
	<?php elseif ( $seat ) : ?>
		<div class="bkx-selected-seat">
			<span class="seat-label"><?php echo esc_html( $seat_alias . ':' ); ?></span>
			<span class="seat-name"><?php echo esc_html( $seat->post_title ); ?></span>
		</div>
	<?php endif; ?>

	<!-- Date Selection -->
	<?php if ( $requires_date ) : ?>
		<div class="form-row bkx-date-selection">
			<label for="bkx-booking-date-picker">
				<?php esc_html_e( 'Select Date', 'bkx-woocommerce-pro' ); ?>
			</label>
			<input type="date"
				   id="bkx-booking-date-picker"
				   class="bkx-booking-date"
				   min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"
				   required>
		</div>

		<!-- Time Slots -->
		<div class="form-row bkx-time-selection">
			<label><?php esc_html_e( 'Select Time', 'bkx-woocommerce-pro' ); ?></label>
			<div class="bkx-time-slots-container">
				<p class="select-date-first">
					<?php esc_html_e( 'Please select a date first.', 'bkx-woocommerce-pro' ); ?>
				</p>
			</div>
		</div>
	<?php endif; ?>

	<!-- Extras Selection -->
	<?php if ( ! empty( $extras ) ) : ?>
		<div class="form-row bkx-extras-selection">
			<label><?php esc_html_e( 'Add Extras', 'bkx-woocommerce-pro' ); ?></label>
			<div class="bkx-extras-list">
				<?php foreach ( $extras as $extra_id ) : ?>
					<?php
					$extra = get_post( $extra_id );
					if ( ! $extra || 'bkx_addition' !== $extra->post_type ) {
						continue;
					}

					$extra_price = get_post_meta( $extra_id, 'addition_price', true );
					$extra_time  = get_post_meta( $extra_id, 'addition_time', true );
					?>
					<label class="bkx-extra-item">
						<input type="checkbox"
							   name="bkx_extras[]"
							   value="<?php echo esc_attr( $extra_id ); ?>"
							   data-price="<?php echo esc_attr( $extra_price ); ?>">
						<span class="extra-info">
							<span class="extra-name"><?php echo esc_html( $extra->post_title ); ?></span>
							<?php if ( $extra->post_excerpt ) : ?>
								<span class="extra-desc"><?php echo esc_html( $extra->post_excerpt ); ?></span>
							<?php endif; ?>
						</span>
						<span class="extra-price">+<?php echo wc_price( $extra_price ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Price Summary -->
	<div class="bkx-price-summary">
		<div class="price-row base-price">
			<span class="label"><?php esc_html_e( 'Service', 'bkx-woocommerce-pro' ); ?></span>
			<span class="value"><?php echo $product->get_price_html(); ?></span>
		</div>
		<div class="price-row extras-price" style="display: none;">
			<span class="label"><?php esc_html_e( 'Extras', 'bkx-woocommerce-pro' ); ?></span>
			<span class="value bkx-extras-total"><?php echo wc_price( 0 ); ?></span>
		</div>
		<div class="price-row total-price">
			<span class="label"><?php esc_html_e( 'Total', 'bkx-woocommerce-pro' ); ?></span>
			<span class="value bkx-total-price"><?php echo $product->get_price_html(); ?></span>
		</div>
	</div>

	<?php do_action( 'bkx_woo_after_booking_form', $product ); ?>

	<!-- Cart Message -->
	<div class="bkx-cart-message"></div>
</div>

<style>
.bkx-woo-booking-form {
	margin-bottom: 30px;
}

.bkx-booking-duration {
	margin-bottom: 20px;
	padding: 10px 15px;
	background: #f5f5f5;
	border-radius: 4px;
}

.bkx-selected-seat {
	margin-bottom: 20px;
	padding: 10px 15px;
	background: #e8f4fd;
	border-radius: 4px;
}

.bkx-price-summary {
	margin-top: 20px;
	padding: 15px;
	background: #f9f9f9;
	border-radius: 4px;
}

.bkx-price-summary .price-row {
	display: flex;
	justify-content: space-between;
	padding: 8px 0;
	border-bottom: 1px solid #e5e5e5;
}

.bkx-price-summary .price-row:last-child {
	border-bottom: none;
	font-weight: 600;
	font-size: 18px;
}

.bkx-price-summary .price-row.total-price {
	margin-top: 10px;
	padding-top: 15px;
	border-top: 2px solid #ddd;
}

.bkx-time-slots-container .select-date-first {
	color: #666;
	font-style: italic;
}

.bkx-time-slots-container.loading {
	position: relative;
	min-height: 100px;
}

.bkx-time-slots-container.loading::after {
	content: "";
	position: absolute;
	top: 50%;
	left: 50%;
	width: 30px;
	height: 30px;
	margin: -15px 0 0 -15px;
	border: 3px solid #ddd;
	border-top-color: #2271b1;
	border-radius: 50%;
	animation: spin 1s linear infinite;
}

@keyframes spin {
	to { transform: rotate(360deg); }
}
</style>

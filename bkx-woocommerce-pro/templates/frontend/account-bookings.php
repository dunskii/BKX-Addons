<?php
/**
 * My Account - Bookings Template.
 *
 * @package BookingX\WooCommercePro
 * @since   1.0.0
 *
 * @var array $bookings Customer bookings.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seat_alias = get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-woocommerce-pro' ) );

/**
 * Get booking status label.
 *
 * @param string $status Status slug.
 * @return string
 */
function bkx_woo_get_status_label( $status ) {
	$labels = array(
		'bkx-pending'   => __( 'Pending', 'bkx-woocommerce-pro' ),
		'bkx-ack'       => __( 'Confirmed', 'bkx-woocommerce-pro' ),
		'bkx-completed' => __( 'Completed', 'bkx-woocommerce-pro' ),
		'bkx-cancelled' => __( 'Cancelled', 'bkx-woocommerce-pro' ),
		'bkx-missed'    => __( 'Missed', 'bkx-woocommerce-pro' ),
	);

	return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( str_replace( 'bkx-', '', $status ) );
}

/**
 * Get status CSS class.
 *
 * @param string $status Status slug.
 * @return string
 */
function bkx_woo_get_status_class( $status ) {
	$classes = array(
		'bkx-pending'   => 'pending',
		'bkx-ack'       => 'confirmed',
		'bkx-completed' => 'completed',
		'bkx-cancelled' => 'cancelled',
		'bkx-missed'    => 'cancelled',
	);

	return isset( $classes[ $status ] ) ? $classes[ $status ] : 'pending';
}
?>
<div class="bkx-account-bookings">
	<h2><?php esc_html_e( 'My Bookings', 'bkx-woocommerce-pro' ); ?></h2>

	<?php if ( empty( $bookings ) ) : ?>
		<div class="bkx-no-bookings">
			<p><?php esc_html_e( 'You have no bookings yet.', 'bkx-woocommerce-pro' ); ?></p>
			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="button">
				<?php esc_html_e( 'Browse Services', 'bkx-woocommerce-pro' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="bkx-bookings-list">
			<?php foreach ( $bookings as $booking ) : ?>
				<?php
				$status       = get_post_status( $booking->ID );
				$status_label = bkx_woo_get_status_label( $status );
				$status_class = bkx_woo_get_status_class( $status );

				$booking_date = get_post_meta( $booking->ID, 'booking_date', true );
				$booking_time = get_post_meta( $booking->ID, 'booking_time', true );
				$seat_id      = get_post_meta( $booking->ID, 'seat_id', true );
				$service_ids  = get_post_meta( $booking->ID, 'booking_multi_base', true );
				$order_id     = get_post_meta( $booking->ID, 'from_woo_order', true );

				// Get service names.
				$service_names = array();
				if ( ! empty( $service_ids ) ) {
					foreach ( (array) $service_ids as $service_id ) {
						$service = get_post( $service_id );
						if ( $service ) {
							$service_names[] = $service->post_title;
						}
					}
				}

				// Check if can cancel.
				$can_cancel = in_array( $status, array( 'bkx-pending', 'bkx-ack' ), true );

				// Check if booking is in the future.
				$is_future = false;
				if ( $booking_date && $booking_time ) {
					$booking_datetime = strtotime( $booking_date . ' ' . $booking_time );
					$is_future        = $booking_datetime > time();
				}

				$can_cancel = $can_cancel && $is_future;
				?>
				<div class="booking-row" data-booking-id="<?php echo esc_attr( $booking->ID ); ?>">
					<div class="booking-date">
						<?php if ( $booking_date && $booking_time ) : ?>
							<strong>
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking_date ) ) ); ?>
							</strong>
							<br>
							<span class="booking-time">
								<?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $booking_time ) ) ); ?>
							</span>
						<?php else : ?>
							<span class="no-date"><?php esc_html_e( 'Date TBD', 'bkx-woocommerce-pro' ); ?></span>
						<?php endif; ?>
					</div>

					<div class="booking-service">
						<strong>
							<?php echo esc_html( implode( ', ', $service_names ) ?: __( 'Service', 'bkx-woocommerce-pro' ) ); ?>
						</strong>

						<?php if ( $seat_id ) : ?>
							<br>
							<span class="booking-seat">
								<?php
								echo esc_html( $seat_alias . ': ' . get_the_title( $seat_id ) );
								?>
							</span>
						<?php endif; ?>

						<?php if ( $order_id ) : ?>
							<br>
							<span class="booking-order">
								<?php
								$order = wc_get_order( $order_id );
								if ( $order ) {
									printf(
										/* translators: %s: order number */
										esc_html__( 'Order #%s', 'bkx-woocommerce-pro' ),
										esc_html( $order->get_order_number() )
									);
								}
								?>
							</span>
						<?php endif; ?>
					</div>

					<div class="booking-status">
						<span class="bkx-status-badge bkx-status-<?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_label ); ?>
						</span>
					</div>

					<div class="booking-actions">
						<?php if ( $can_cancel ) : ?>
							<button type="button"
									class="button cancel-booking"
									data-booking-id="<?php echo esc_attr( $booking->ID ); ?>">
								<?php esc_html_e( 'Cancel', 'bkx-woocommerce-pro' ); ?>
							</button>
						<?php endif; ?>

						<?php if ( $order_id ) : ?>
							<a href="<?php echo esc_url( wc_get_endpoint_url( 'view-order', $order_id, wc_get_page_permalink( 'myaccount' ) ) ); ?>"
							   class="button button-secondary">
								<?php esc_html_e( 'View Order', 'bkx-woocommerce-pro' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<style>
.bkx-account-bookings {
	margin-top: 20px;
}

.bkx-account-bookings h2 {
	margin-bottom: 20px;
}

.bkx-bookings-list {
	border: 1px solid #e5e5e5;
	border-radius: 4px;
}

.booking-row {
	display: flex;
	align-items: center;
	padding: 20px;
	border-bottom: 1px solid #e5e5e5;
	flex-wrap: wrap;
	gap: 15px;
}

.booking-row:last-child {
	border-bottom: none;
}

.booking-date {
	flex: 0 0 120px;
}

.booking-date .booking-time {
	color: #666;
	font-size: 14px;
}

.booking-service {
	flex: 1;
	min-width: 200px;
}

.booking-service .booking-seat,
.booking-service .booking-order {
	color: #666;
	font-size: 14px;
}

.booking-status {
	flex: 0 0 100px;
	text-align: center;
}

.booking-actions {
	flex: 0 0 auto;
	display: flex;
	gap: 10px;
}

.booking-actions .button {
	padding: 5px 15px;
	font-size: 13px;
}

.bkx-status-badge {
	display: inline-block;
	padding: 5px 12px;
	border-radius: 20px;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
}

.bkx-status-pending {
	background: #fff3cd;
	color: #856404;
}

.bkx-status-confirmed {
	background: #cce5ff;
	color: #004085;
}

.bkx-status-completed {
	background: #d4edda;
	color: #155724;
}

.bkx-status-cancelled {
	background: #f8d7da;
	color: #721c24;
}

.bkx-no-bookings {
	text-align: center;
	padding: 40px;
	background: #f9f9f9;
	border-radius: 4px;
}

.bkx-no-bookings p {
	margin-bottom: 20px;
	color: #666;
}

@media screen and (max-width: 768px) {
	.booking-row {
		flex-direction: column;
		align-items: flex-start;
	}

	.booking-date,
	.booking-status {
		flex: none;
		width: 100%;
		text-align: left;
	}

	.booking-actions {
		width: 100%;
		justify-content: flex-start;
	}
}
</style>

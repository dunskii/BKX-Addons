<?php
/**
 * Booking History Template
 *
 * @package BookingX\UserProfilesAdvanced
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id         = get_current_user_id();
$profile_service = $this->addon->get_profile_service();
$paged           = max( 1, get_query_var( 'paged', 1 ) );

$history = $profile_service->get_booking_history(
	$user_id,
	array(
		'posts_per_page' => $this->addon->get_setting( 'bookings_per_page', 10 ),
		'paged'          => $paged,
	)
);
?>

<div class="bkx-booking-history">
	<h3><?php esc_html_e( 'Booking History', 'bkx-user-profiles-advanced' ); ?></h3>

	<?php if ( empty( $history['bookings'] ) ) : ?>
		<p class="bkx-no-bookings"><?php esc_html_e( 'You have no bookings yet.', 'bkx-user-profiles-advanced' ); ?></p>
	<?php else : ?>
		<div class="bkx-bookings-list">
			<?php foreach ( $history['bookings'] as $booking ) : ?>
				<?php
				$booking_date  = get_post_meta( $booking->ID, 'booking_date', true );
				$booking_time  = get_post_meta( $booking->ID, 'booking_time', true );
				$seat_id       = get_post_meta( $booking->ID, 'seat_id', true );
				$base_id       = get_post_meta( $booking->ID, 'base_id', true );
				$total         = get_post_meta( $booking->ID, 'total_price', true );
				$seat          = get_post( $seat_id );
				$base          = get_post( $base_id );
				$status        = $booking->post_status;
				$status_label  = str_replace( 'bkx-', '', $status );
				$is_upcoming   = strtotime( $booking_date . ' ' . $booking_time ) > time();
				?>
				<div class="bkx-booking-card" data-id="<?php echo esc_attr( $booking->ID ); ?>">
					<div class="bkx-booking-date">
						<span class="bkx-date-day"><?php echo esc_html( date_i18n( 'j', strtotime( $booking_date ) ) ); ?></span>
						<span class="bkx-date-month"><?php echo esc_html( date_i18n( 'M', strtotime( $booking_date ) ) ); ?></span>
					</div>
					<div class="bkx-booking-details">
						<h4><?php echo esc_html( $base ? $base->post_title : __( 'Service', 'bkx-user-profiles-advanced' ) ); ?></h4>
						<p class="bkx-booking-meta">
							<span class="bkx-time"><?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $booking_time ) ) ); ?></span>
							<?php if ( $seat ) : ?>
								<span class="bkx-staff"><?php echo esc_html( $seat->post_title ); ?></span>
							<?php endif; ?>
						</p>
						<p class="bkx-booking-total"><?php echo esc_html( number_format( (float) $total, 2 ) ); ?></p>
					</div>
					<div class="bkx-booking-status">
						<span class="bkx-status bkx-status-<?php echo esc_attr( $status_label ); ?>">
							<?php echo esc_html( ucfirst( $status_label ) ); ?>
						</span>
					</div>
					<div class="bkx-booking-actions">
						<?php if ( $this->addon->get_setting( 'allow_rebooking', true ) && ! $is_upcoming ) : ?>
							<button type="button" class="bkx-button bkx-rebook" data-id="<?php echo esc_attr( $booking->ID ); ?>">
								<?php esc_html_e( 'Book Again', 'bkx-user-profiles-advanced' ); ?>
							</button>
						<?php endif; ?>

						<?php if ( $this->addon->get_setting( 'allow_cancellation', true ) && $is_upcoming && 'bkx-cancelled' !== $status ) : ?>
							<button type="button" class="bkx-button bkx-button-danger bkx-cancel-booking" data-id="<?php echo esc_attr( $booking->ID ); ?>">
								<?php esc_html_e( 'Cancel', 'bkx-user-profiles-advanced' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $history['total_pages'] > 1 ) : ?>
			<div class="bkx-pagination">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo paginate_links(
					array(
						'total'   => $history['total_pages'],
						'current' => $paged,
					)
				);
				?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

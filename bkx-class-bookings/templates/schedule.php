<?php
/**
 * Class Schedule Template
 *
 * @package BookingX\ClassBookings
 * @since   1.0.0
 *
 * @var array  $sessions   Array of sessions.
 * @var string $start_date Start date.
 * @var string $end_date   End date.
 * @var array  $atts       Shortcode attributes.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="bkx-class-schedule">
	<?php if ( ! empty( $atts['title'] ) ) : ?>
		<h2 class="bkx-schedule-title"><?php echo esc_html( $atts['title'] ); ?></h2>
	<?php endif; ?>

	<?php if ( empty( $sessions ) ) : ?>
		<p class="bkx-no-sessions"><?php esc_html_e( 'No classes scheduled for this period.', 'bkx-class-bookings' ); ?></p>
	<?php else : ?>
		<div class="bkx-schedule-list">
			<?php
			$current_date = '';
			foreach ( $sessions as $session ) :
				$session_date = $session['session_date'];
				if ( $session_date !== $current_date ) :
					if ( $current_date ) :
						echo '</div>'; // Close previous day.
					endif;
					$current_date = $session_date;
					?>
					<div class="bkx-schedule-day">
						<h3 class="bkx-day-header">
							<?php echo esc_html( wp_date( 'l, F j', strtotime( $session_date ) ) ); ?>
						</h3>
				<?php endif; ?>

				<?php
				$available = $session['capacity'] - $session['booked_count'];
				$is_full   = $available <= 0;
				$is_past   = strtotime( $session['session_date'] . ' ' . $session['end_time'] ) < current_time( 'timestamp' );
				$class_meta = get_post_meta( $session['class_id'] );
				$color     = $class_meta['_bkx_class_color'][0] ?? '#3788d8';
				?>
				<div class="bkx-session-item <?php echo $is_full ? 'bkx-session-full' : ''; ?> <?php echo $is_past ? 'bkx-session-past' : ''; ?>"
					 style="border-left-color: <?php echo esc_attr( $color ); ?>;">
					<div class="bkx-session-time">
						<span class="bkx-start-time"><?php echo esc_html( wp_date( 'g:i A', strtotime( $session['start_time'] ) ) ); ?></span>
						<span class="bkx-time-sep">-</span>
						<span class="bkx-end-time"><?php echo esc_html( wp_date( 'g:i A', strtotime( $session['end_time'] ) ) ); ?></span>
					</div>
					<div class="bkx-session-info">
						<h4 class="bkx-class-name">
							<a href="<?php echo esc_url( get_permalink( $session['class_id'] ) ); ?>">
								<?php echo esc_html( $session['class_name'] ); ?>
							</a>
						</h4>
						<?php if ( ! empty( $class_meta['_bkx_class_location'][0] ) ) : ?>
							<span class="bkx-class-location">
								<span class="dashicons dashicons-location"></span>
								<?php echo esc_html( $class_meta['_bkx_class_location'][0] ); ?>
							</span>
						<?php endif; ?>
						<?php if ( ! empty( $class_meta['_bkx_class_instructor_id'][0] ) ) : ?>
							<?php $instructor = get_post( $class_meta['_bkx_class_instructor_id'][0] ); ?>
							<?php if ( $instructor ) : ?>
								<span class="bkx-class-instructor">
									<span class="dashicons dashicons-admin-users"></span>
									<?php echo esc_html( $instructor->post_title ); ?>
								</span>
							<?php endif; ?>
						<?php endif; ?>
					</div>
					<div class="bkx-session-availability">
						<?php if ( $is_past ) : ?>
							<span class="bkx-status-past"><?php esc_html_e( 'Completed', 'bkx-class-bookings' ); ?></span>
						<?php elseif ( $is_full ) : ?>
							<span class="bkx-status-full"><?php esc_html_e( 'Full', 'bkx-class-bookings' ); ?></span>
							<?php if ( $class_meta['_bkx_class_allow_waitlist'][0] ?? false ) : ?>
								<a href="#" class="bkx-join-waitlist button button-small" data-session="<?php echo esc_attr( $session['id'] ); ?>">
									<?php esc_html_e( 'Join Waitlist', 'bkx-class-bookings' ); ?>
								</a>
							<?php endif; ?>
						<?php else : ?>
							<span class="bkx-spots-available">
								<?php
								printf(
									/* translators: %d: number of spots */
									esc_html( _n( '%d spot left', '%d spots left', $available, 'bkx-class-bookings' ) ),
									esc_html( $available )
								);
								?>
							</span>
							<a href="#" class="bkx-book-class button button-primary" data-session="<?php echo esc_attr( $session['id'] ); ?>">
								<?php esc_html_e( 'Book Now', 'bkx-class-bookings' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
			</div><!-- Close last day -->
		</div>
	<?php endif; ?>
</div>

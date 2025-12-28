<?php
/**
 * Class Calendar Template
 *
 * @package BookingX\ClassBookings
 * @since   1.0.0
 *
 * @var array  $sessions Array of sessions.
 * @var string $month    Current month (Y-m).
 * @var array  $atts     Shortcode attributes.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$year        = (int) substr( $month, 0, 4 );
$month_num   = (int) substr( $month, 5, 2 );
$first_day   = mktime( 0, 0, 0, $month_num, 1, $year );
$days_in_month = (int) gmdate( 't', $first_day );
$start_day   = (int) gmdate( 'w', $first_day );
$prev_month  = gmdate( 'Y-m', strtotime( '-1 month', $first_day ) );
$next_month  = gmdate( 'Y-m', strtotime( '+1 month', $first_day ) );

// Organize sessions by date.
$sessions_by_date = array();
foreach ( $sessions as $session ) {
	$date = $session['session_date'];
	if ( ! isset( $sessions_by_date[ $date ] ) ) {
		$sessions_by_date[ $date ] = array();
	}
	$sessions_by_date[ $date ][] = $session;
}
?>

<div class="bkx-class-calendar">
	<div class="bkx-calendar-header">
		<a href="<?php echo esc_url( add_query_arg( 'month', $prev_month ) ); ?>" class="bkx-cal-nav bkx-cal-prev">
			&laquo; <?php esc_html_e( 'Previous', 'bkx-class-bookings' ); ?>
		</a>
		<h2 class="bkx-calendar-month">
			<?php echo esc_html( wp_date( 'F Y', $first_day ) ); ?>
		</h2>
		<a href="<?php echo esc_url( add_query_arg( 'month', $next_month ) ); ?>" class="bkx-cal-nav bkx-cal-next">
			<?php esc_html_e( 'Next', 'bkx-class-bookings' ); ?> &raquo;
		</a>
	</div>

	<table class="bkx-calendar-grid">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Sun', 'bkx-class-bookings' ); ?></th>
				<th><?php esc_html_e( 'Mon', 'bkx-class-bookings' ); ?></th>
				<th><?php esc_html_e( 'Tue', 'bkx-class-bookings' ); ?></th>
				<th><?php esc_html_e( 'Wed', 'bkx-class-bookings' ); ?></th>
				<th><?php esc_html_e( 'Thu', 'bkx-class-bookings' ); ?></th>
				<th><?php esc_html_e( 'Fri', 'bkx-class-bookings' ); ?></th>
				<th><?php esc_html_e( 'Sat', 'bkx-class-bookings' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$day = 1;
			$cell = 0;
			$today = current_time( 'Y-m-d' );

			while ( $day <= $days_in_month ) :
				?>
				<tr>
					<?php
					for ( $i = 0; $i < 7; $i++ ) :
						$date_str = '';
						$classes  = array( 'bkx-cal-day' );

						if ( $cell < $start_day || $day > $days_in_month ) {
							$classes[] = 'bkx-cal-empty';
						} else {
							$date_str = sprintf( '%04d-%02d-%02d', $year, $month_num, $day );
							if ( $date_str === $today ) {
								$classes[] = 'bkx-cal-today';
							}
							if ( $date_str < $today ) {
								$classes[] = 'bkx-cal-past';
							}
							if ( isset( $sessions_by_date[ $date_str ] ) ) {
								$classes[] = 'bkx-cal-has-sessions';
							}
						}
						?>
						<td class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
							<?php if ( $cell >= $start_day && $day <= $days_in_month ) : ?>
								<div class="bkx-day-number"><?php echo esc_html( $day ); ?></div>
								<?php if ( isset( $sessions_by_date[ $date_str ] ) ) : ?>
									<div class="bkx-day-sessions">
										<?php foreach ( $sessions_by_date[ $date_str ] as $session ) : ?>
											<?php
											$color     = get_post_meta( $session['class_id'], '_bkx_class_color', true ) ?: '#3788d8';
											$available = $session['capacity'] - $session['booked_count'];
											?>
											<a href="#" class="bkx-cal-session <?php echo $available <= 0 ? 'bkx-session-full' : ''; ?>"
											   style="background-color: <?php echo esc_attr( $color ); ?>;"
											   data-session="<?php echo esc_attr( $session['id'] ); ?>"
											   title="<?php echo esc_attr( $session['class_name'] . ' - ' . wp_date( 'g:i A', strtotime( $session['start_time'] ) ) ); ?>">
												<span class="bkx-session-time"><?php echo esc_html( wp_date( 'g:i', strtotime( $session['start_time'] ) ) ); ?></span>
												<span class="bkx-session-name"><?php echo esc_html( $session['class_name'] ); ?></span>
											</a>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
								<?php ++$day; ?>
							<?php endif; ?>
						</td>
						<?php
						++$cell;
					endfor;
					?>
				</tr>
			<?php endwhile; ?>
		</tbody>
	</table>
</div>

<!-- Session Detail Modal -->
<div id="bkx-session-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<button class="bkx-modal-close">&times;</button>
		<div class="bkx-modal-body">
			<!-- Populated via JS -->
		</div>
	</div>
</div>

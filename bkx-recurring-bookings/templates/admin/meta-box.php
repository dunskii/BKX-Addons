<?php
/**
 * Admin Meta Box Template
 *
 * @package BookingX\RecurringBookings
 * @since   1.0.0
 *
 * @var array   $series Series data.
 * @var bool    $is_master Whether this is the master booking.
 * @var WP_Post $post Current post.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pattern_label = $this->addon->get_pattern_label( $series['pattern'] );
$stats         = $this->instance_generator->get_series_stats( $series['id'] );
?>
<div class="bkx-recurring-meta-box">
	<div class="bkx-recurring-status">
		<?php if ( $is_master ) : ?>
			<span class="bkx-badge bkx-badge-primary"><?php esc_html_e( 'Master Booking', 'bkx-recurring-bookings' ); ?></span>
		<?php else : ?>
			<span class="bkx-badge bkx-badge-secondary"><?php esc_html_e( 'Instance', 'bkx-recurring-bookings' ); ?></span>
		<?php endif; ?>
	</div>

	<table class="bkx-recurring-info-table">
		<tr>
			<th><?php esc_html_e( 'Series ID', 'bkx-recurring-bookings' ); ?></th>
			<td>#<?php echo esc_html( $series['id'] ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Pattern', 'bkx-recurring-bookings' ); ?></th>
			<td><?php echo esc_html( $pattern_label ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Start Date', 'bkx-recurring-bookings' ); ?></th>
			<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $series['start_date'] ) ) ); ?></td>
		</tr>
		<?php if ( ! empty( $series['end_date'] ) ) : ?>
		<tr>
			<th><?php esc_html_e( 'End Date', 'bkx-recurring-bookings' ); ?></th>
			<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $series['end_date'] ) ) ); ?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<th><?php esc_html_e( 'Time', 'bkx-recurring-bookings' ); ?></th>
			<td>
				<?php
				echo esc_html(
					wp_date( get_option( 'time_format' ), strtotime( $series['start_time'] ) ) .
					' - ' .
					wp_date( get_option( 'time_format' ), strtotime( $series['end_time'] ) )
				);
				?>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Progress', 'bkx-recurring-bookings' ); ?></th>
			<td>
				<?php
				printf(
					/* translators: 1: completed count, 2: total count */
					esc_html__( '%1$d of %2$d completed', 'bkx-recurring-bookings' ),
					$stats['completed'],
					$series['max_occurrences']
				);
				?>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Status', 'bkx-recurring-bookings' ); ?></th>
			<td>
				<span class="bkx-status-badge bkx-status-<?php echo esc_attr( $series['status'] ); ?>">
					<?php echo esc_html( ucfirst( $series['status'] ) ); ?>
				</span>
			</td>
		</tr>
		<?php if ( $series['recurring_discount'] > 0 ) : ?>
		<tr>
			<th><?php esc_html_e( 'Discount', 'bkx-recurring-bookings' ); ?></th>
			<td><?php echo esc_html( $series['recurring_discount'] ); ?>%</td>
		</tr>
		<?php endif; ?>
	</table>

	<div class="bkx-recurring-actions">
		<button type="button" class="button bkx-view-instances" data-series-id="<?php echo esc_attr( $series['id'] ); ?>">
			<?php esc_html_e( 'View All Instances', 'bkx-recurring-bookings' ); ?>
		</button>

		<?php if ( 'active' === $series['status'] ) : ?>
			<button type="button" class="button bkx-cancel-series" data-series-id="<?php echo esc_attr( $series['id'] ); ?>">
				<?php esc_html_e( 'Cancel Series', 'bkx-recurring-bookings' ); ?>
			</button>
		<?php endif; ?>
	</div>
</div>

<style>
.bkx-recurring-meta-box .bkx-recurring-status {
	margin-bottom: 10px;
}

.bkx-recurring-meta-box .bkx-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 600;
	text-transform: uppercase;
}

.bkx-recurring-meta-box .bkx-badge-primary {
	background: #0073aa;
	color: #fff;
}

.bkx-recurring-meta-box .bkx-badge-secondary {
	background: #f0f0f1;
	color: #50575e;
}

.bkx-recurring-meta-box .bkx-recurring-info-table {
	width: 100%;
	margin: 10px 0;
}

.bkx-recurring-meta-box .bkx-recurring-info-table th {
	text-align: left;
	width: 40%;
	padding: 5px 0;
	font-weight: 600;
}

.bkx-recurring-meta-box .bkx-recurring-info-table td {
	padding: 5px 0;
}

.bkx-recurring-meta-box .bkx-status-badge {
	display: inline-block;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 11px;
}

.bkx-recurring-meta-box .bkx-status-active {
	background: #d4edda;
	color: #155724;
}

.bkx-recurring-meta-box .bkx-status-cancelled {
	background: #f8d7da;
	color: #721c24;
}

.bkx-recurring-meta-box .bkx-status-completed {
	background: #cce5ff;
	color: #004085;
}

.bkx-recurring-meta-box .bkx-recurring-actions {
	margin-top: 15px;
	padding-top: 10px;
	border-top: 1px solid #ddd;
}

.bkx-recurring-meta-box .bkx-recurring-actions .button {
	margin-right: 5px;
}
</style>

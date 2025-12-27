<?php
/**
 * My Waitlist Template
 *
 * @package BookingX\WaitingLists
 * @since   1.0.0
 * @var array $entries Waitlist entries.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="bkx-my-waitlist">
	<h3><?php esc_html_e( 'My Waiting List', 'bkx-waiting-lists' ); ?></h3>

	<?php if ( empty( $entries ) ) : ?>
		<p class="bkx-no-entries"><?php esc_html_e( 'You are not on any waiting lists.', 'bkx-waiting-lists' ); ?></p>
	<?php else : ?>
		<table class="bkx-waitlist-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'bkx-waiting-lists' ); ?></th>
					<th><?php esc_html_e( 'Time', 'bkx-waiting-lists' ); ?></th>
					<th><?php esc_html_e( 'Service', 'bkx-waiting-lists' ); ?></th>
					<th><?php esc_html_e( 'Position', 'bkx-waiting-lists' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bkx-waiting-lists' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'bkx-waiting-lists' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<?php
					$service = get_post( $entry['service_id'] );
					$seat    = get_post( $entry['seat_id'] );

					$status_labels = array(
						'waiting' => __( 'Waiting', 'bkx-waiting-lists' ),
						'offered' => __( 'Spot Available!', 'bkx-waiting-lists' ),
					);
					?>
					<tr class="bkx-waitlist-entry bkx-status-<?php echo esc_attr( $entry['status'] ); ?>">
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry['booking_date'] ) ) ); ?></td>
						<td><?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $entry['booking_time'] ) ) ); ?></td>
						<td>
							<?php echo $service ? esc_html( $service->post_title ) : '—'; ?>
							<?php if ( $seat ) : ?>
								<br /><small><?php echo esc_html( $seat->post_title ); ?></small>
							<?php endif; ?>
						</td>
						<td>#<?php echo esc_html( $entry['position'] ); ?></td>
						<td>
							<span class="bkx-status-badge bkx-status-<?php echo esc_attr( $entry['status'] ); ?>">
								<?php echo esc_html( $status_labels[ $entry['status'] ] ?? $entry['status'] ); ?>
							</span>
						</td>
						<td>
							<?php if ( 'offered' === $entry['status'] ) : ?>
								<a href="#" class="bkx-accept-offer button button-primary"
								   data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>"
								   data-token="<?php echo esc_attr( $entry['token'] ); ?>">
									<?php esc_html_e( 'Accept', 'bkx-waiting-lists' ); ?>
								</a>
								<a href="#" class="bkx-decline-offer button"
								   data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>"
								   data-token="<?php echo esc_attr( $entry['token'] ); ?>">
									<?php esc_html_e( 'Decline', 'bkx-waiting-lists' ); ?>
								</a>
								<?php if ( ! empty( $entry['offer_expires_at'] ) ) : ?>
									<br />
									<small class="bkx-expires">
										<?php
										/* translators: %s: expiry time */
										printf(
											esc_html__( 'Expires: %s', 'bkx-waiting-lists' ),
											esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['offer_expires_at'] ) ) )
										);
										?>
									</small>
								<?php endif; ?>
							<?php else : ?>
								<a href="#" class="bkx-leave-waitlist"
								   data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>"
								   data-token="<?php echo esc_attr( $entry['token'] ); ?>">
									<?php esc_html_e( 'Leave', 'bkx-waiting-lists' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

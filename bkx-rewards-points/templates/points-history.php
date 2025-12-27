<?php
/**
 * Points History Template
 *
 * @package BookingX\RewardsPoints
 * @since   1.0.0
 *
 * @var array $history Points history
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="bkx-points-history-full">
	<?php if ( empty( $history ) ) : ?>
		<p><?php esc_html_e( 'No points activity yet.', 'bkx-rewards-points' ); ?></p>
	<?php else : ?>
		<table class="bkx-history-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'bkx-rewards-points' ); ?></th>
					<th><?php esc_html_e( 'Type', 'bkx-rewards-points' ); ?></th>
					<th><?php esc_html_e( 'Description', 'bkx-rewards-points' ); ?></th>
					<th><?php esc_html_e( 'Points', 'bkx-rewards-points' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $history as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['created_at'] ) ) ); ?></td>
						<td>
							<span class="bkx-type-badge bkx-type-<?php echo esc_attr( $entry['type'] ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $entry['type'] ) ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $entry['description'] ?: '-' ); ?></td>
						<td class="<?php echo $entry['points'] > 0 ? 'bkx-points-positive' : 'bkx-points-negative'; ?>">
							<?php echo $entry['points'] > 0 ? '+' : ''; ?><?php echo esc_html( number_format( $entry['points'] ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

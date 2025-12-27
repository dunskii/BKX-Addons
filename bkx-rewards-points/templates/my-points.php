<?php
/**
 * My Points Template
 *
 * @package BookingX\RewardsPoints
 * @since   1.0.0
 *
 * @var int   $balance Current points balance
 * @var array $history Points history
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats           = bkx_rewards()->get_points_service()->get_user_stats( get_current_user_id() );
$redemption_value = floatval( bkx_rewards()->get_setting( 'redemption_value', 0.01 ) );
$potential_value  = $balance * $redemption_value;
?>
<div class="bkx-my-points">
	<div class="bkx-points-header">
		<div class="bkx-points-balance-card">
			<div class="bkx-points-balance-value"><?php echo esc_html( number_format( $balance ) ); ?></div>
			<div class="bkx-points-balance-label"><?php esc_html_e( 'Available Points', 'bkx-rewards-points' ); ?></div>
			<div class="bkx-points-value">
				<?php
				printf(
					/* translators: Dollar value */
					esc_html__( 'Worth $%s in discounts', 'bkx-rewards-points' ),
					esc_html( number_format( $potential_value, 2 ) )
				);
				?>
			</div>
		</div>

		<div class="bkx-points-stats">
			<div class="bkx-stat-item">
				<div class="bkx-stat-value"><?php echo esc_html( number_format( $stats['lifetime_earned'] ) ); ?></div>
				<div class="bkx-stat-label"><?php esc_html_e( 'Lifetime Earned', 'bkx-rewards-points' ); ?></div>
			</div>
			<div class="bkx-stat-item">
				<div class="bkx-stat-value"><?php echo esc_html( number_format( $stats['lifetime_redeemed'] ) ); ?></div>
				<div class="bkx-stat-label"><?php esc_html_e( 'Lifetime Redeemed', 'bkx-rewards-points' ); ?></div>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $history ) ) : ?>
		<div class="bkx-points-history">
			<h3><?php esc_html_e( 'Recent Activity', 'bkx-rewards-points' ); ?></h3>

			<table class="bkx-history-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'bkx-rewards-points' ); ?></th>
						<th><?php esc_html_e( 'Description', 'bkx-rewards-points' ); ?></th>
						<th><?php esc_html_e( 'Points', 'bkx-rewards-points' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry['created_at'] ) ) ); ?></td>
							<td><?php echo esc_html( $entry['description'] ?: ucfirst( $entry['type'] ) ); ?></td>
							<td class="<?php echo $entry['points'] > 0 ? 'bkx-points-positive' : 'bkx-points-negative'; ?>">
								<?php echo $entry['points'] > 0 ? '+' : ''; ?><?php echo esc_html( number_format( $entry['points'] ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<div class="bkx-points-info">
		<h3><?php esc_html_e( 'How It Works', 'bkx-rewards-points' ); ?></h3>
		<ul>
			<li>
				<?php
				printf(
					/* translators: Points per dollar */
					esc_html__( 'Earn %s point(s) for every $1 spent on bookings.', 'bkx-rewards-points' ),
					esc_html( bkx_rewards()->get_setting( 'points_per_dollar', 1 ) )
				);
				?>
			</li>
			<li>
				<?php
				printf(
					/* translators: Value per point */
					esc_html__( 'Each point is worth $%s in discounts.', 'bkx-rewards-points' ),
					esc_html( number_format( $redemption_value, 2 ) )
				);
				?>
			</li>
			<li>
				<?php
				printf(
					/* translators: Minimum points */
					esc_html__( 'Minimum %s points required for redemption.', 'bkx-rewards-points' ),
					esc_html( number_format( bkx_rewards()->get_setting( 'min_redemption', 100 ) ) )
				);
				?>
			</li>
		</ul>
	</div>
</div>

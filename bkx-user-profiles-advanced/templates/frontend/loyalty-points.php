<?php
/**
 * Loyalty Points Template
 *
 * @package BookingX\UserProfilesAdvanced
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id         = get_current_user_id();
$loyalty_service = $this->addon->get_loyalty_service();
$balance         = $loyalty_service->get_balance( $user_id );
$history         = $loyalty_service->get_history( $user_id, array( 'limit' => 10 ) );

$min_redeem        = $this->addon->get_setting( 'min_points_redeem', 100 );
$redemption_rate   = $this->addon->get_setting( 'points_redemption_rate', 100 );
$redemption_value  = $this->addon->get_setting( 'points_redemption_value', 1 );
$points_value      = $loyalty_service->calculate_value( $balance['available_points'] );
?>

<div class="bkx-loyalty-points">
	<h3><?php esc_html_e( 'My Loyalty Points', 'bkx-user-profiles-advanced' ); ?></h3>

	<div class="bkx-points-overview">
		<div class="bkx-points-balance">
			<span class="bkx-points-number"><?php echo esc_html( number_format( $balance['available_points'] ) ); ?></span>
			<span class="bkx-points-label"><?php esc_html_e( 'Available Points', 'bkx-user-profiles-advanced' ); ?></span>
			<span class="bkx-points-value">
				<?php
				printf(
					/* translators: %s: currency value */
					esc_html__( 'Worth $%s', 'bkx-user-profiles-advanced' ),
					esc_html( number_format( $points_value, 2 ) )
				);
				?>
			</span>
		</div>

		<div class="bkx-points-stats">
			<div class="bkx-stat">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $balance['lifetime_earned'] ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Lifetime Earned', 'bkx-user-profiles-advanced' ); ?></span>
			</div>
			<div class="bkx-stat">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $balance['lifetime_redeemed'] ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Lifetime Redeemed', 'bkx-user-profiles-advanced' ); ?></span>
			</div>
		</div>
	</div>

	<?php if ( $balance['available_points'] >= $min_redeem ) : ?>
		<div class="bkx-redeem-section">
			<h4><?php esc_html_e( 'Redeem Points', 'bkx-user-profiles-advanced' ); ?></h4>
			<form id="bkx-redeem-form" class="bkx-redeem-form">
				<div class="bkx-form-row">
					<label for="bkx-redeem-points"><?php esc_html_e( 'Points to Redeem', 'bkx-user-profiles-advanced' ); ?></label>
					<input type="number" id="bkx-redeem-points" name="points"
						   min="<?php echo esc_attr( $min_redeem ); ?>"
						   max="<?php echo esc_attr( $balance['available_points'] ); ?>"
						   step="<?php echo esc_attr( $redemption_rate ); ?>"
						   value="<?php echo esc_attr( min( $balance['available_points'], $min_redeem ) ); ?>" />
				</div>
				<div class="bkx-form-row">
					<span class="bkx-discount-preview">
						<?php esc_html_e( 'Discount:', 'bkx-user-profiles-advanced' ); ?>
						<strong id="bkx-discount-amount">$<?php echo esc_html( number_format( ( $min_redeem / $redemption_rate ) * $redemption_value, 2 ) ); ?></strong>
					</span>
				</div>
				<div class="bkx-form-row">
					<button type="submit" class="bkx-button bkx-button-primary"><?php esc_html_e( 'Redeem', 'bkx-user-profiles-advanced' ); ?></button>
					<span class="bkx-form-message"></span>
				</div>
			</form>
			<p class="bkx-redeem-note">
				<?php
				printf(
					/* translators: 1: points, 2: currency value */
					esc_html__( '%1$s points = $%2$s off your next booking', 'bkx-user-profiles-advanced' ),
					esc_html( $redemption_rate ),
					esc_html( number_format( $redemption_value, 2 ) )
				);
				?>
			</p>
		</div>
	<?php else : ?>
		<div class="bkx-redeem-section">
			<p class="bkx-earn-more">
				<?php
				printf(
					/* translators: %s: points needed */
					esc_html__( 'Earn %s more points to start redeeming!', 'bkx-user-profiles-advanced' ),
					esc_html( number_format( $min_redeem - $balance['available_points'] ) )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $balance['pending_discount'] > 0 ) : ?>
		<div class="bkx-pending-discount">
			<p>
				<?php
				printf(
					/* translators: %s: discount amount */
					esc_html__( 'You have a $%s discount waiting to be applied to your next booking!', 'bkx-user-profiles-advanced' ),
					esc_html( number_format( $balance['pending_discount'], 2 ) )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $history ) ) : ?>
		<div class="bkx-points-history">
			<h4><?php esc_html_e( 'Points History', 'bkx-user-profiles-advanced' ); ?></h4>
			<table class="bkx-history-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'bkx-user-profiles-advanced' ); ?></th>
						<th><?php esc_html_e( 'Description', 'bkx-user-profiles-advanced' ); ?></th>
						<th><?php esc_html_e( 'Points', 'bkx-user-profiles-advanced' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry->created_at ) ) ); ?></td>
							<td><?php echo esc_html( $entry->description ); ?></td>
							<td class="bkx-points-<?php echo $entry->points >= 0 ? 'positive' : 'negative'; ?>">
								<?php echo $entry->points >= 0 ? '+' : ''; ?><?php echo esc_html( number_format( $entry->points ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<div class="bkx-earn-points-info">
		<h4><?php esc_html_e( 'How to Earn Points', 'bkx-user-profiles-advanced' ); ?></h4>
		<ul>
			<li><?php esc_html_e( 'Complete a booking: Earn bonus points', 'bkx-user-profiles-advanced' ); ?></li>
			<li><?php esc_html_e( 'Spend money: Earn points per dollar spent', 'bkx-user-profiles-advanced' ); ?></li>
			<li><?php esc_html_e( 'Refer a friend: Earn referral bonus', 'bkx-user-profiles-advanced' ); ?></li>
		</ul>
	</div>
</div>

<?php
/**
 * Points Selector Template (Booking Form)
 *
 * @package BookingX\RewardsPoints
 * @since   1.0.0
 *
 * @var int $balance Current points balance
 * @var int $min_redemption Minimum points for redemption
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$redemption_value = floatval( bkx_rewards()->get_setting( 'redemption_value', 0.01 ) );
$max_discount     = $balance * $redemption_value;
?>
<div class="bkx-points-selector">
	<div class="bkx-points-selector-header">
		<span class="bkx-points-icon dashicons dashicons-awards"></span>
		<span class="bkx-points-title"><?php esc_html_e( 'Use Rewards Points', 'bkx-rewards-points' ); ?></span>
		<span class="bkx-points-available">
			<?php
			printf(
				/* translators: Number of points */
				esc_html__( '%s points available', 'bkx-rewards-points' ),
				esc_html( number_format( $balance ) )
			);
			?>
		</span>
	</div>

	<div class="bkx-points-selector-body">
		<div class="bkx-points-input-group">
			<label for="bkx-redeem-points"><?php esc_html_e( 'Points to redeem:', 'bkx-rewards-points' ); ?></label>
			<input type="number"
				   id="bkx-redeem-points"
				   name="bkx_redeem_points"
				   value="0"
				   min="0"
				   max="<?php echo esc_attr( $balance ); ?>"
				   step="1"
				   class="bkx-points-input">
			<button type="button" class="bkx-apply-points button"><?php esc_html_e( 'Apply', 'bkx-rewards-points' ); ?></button>
		</div>

		<div class="bkx-points-quick-select">
			<button type="button" class="bkx-quick-points" data-points="<?php echo esc_attr( $min_redemption ); ?>">
				<?php echo esc_html( number_format( $min_redemption ) ); ?>
			</button>
			<?php if ( $balance >= $min_redemption * 2 ) : ?>
				<button type="button" class="bkx-quick-points" data-points="<?php echo esc_attr( floor( $balance / 2 ) ); ?>">
					<?php echo esc_html( number_format( floor( $balance / 2 ) ) ); ?>
				</button>
			<?php endif; ?>
			<button type="button" class="bkx-quick-points bkx-use-all" data-points="<?php echo esc_attr( $balance ); ?>">
				<?php esc_html_e( 'Use All', 'bkx-rewards-points' ); ?>
			</button>
		</div>

		<div class="bkx-points-discount-preview">
			<span class="bkx-discount-label"><?php esc_html_e( 'Discount:', 'bkx-rewards-points' ); ?></span>
			<span class="bkx-discount-value">$0.00</span>
		</div>
	</div>

	<div class="bkx-points-applied" style="display: none;">
		<span class="dashicons dashicons-yes-alt"></span>
		<span class="bkx-applied-text"></span>
		<button type="button" class="bkx-remove-points"><?php esc_html_e( 'Remove', 'bkx-rewards-points' ); ?></button>
	</div>

	<input type="hidden" name="bkx_points_redemption_id" id="bkx-points-redemption-id" value="">
</div>

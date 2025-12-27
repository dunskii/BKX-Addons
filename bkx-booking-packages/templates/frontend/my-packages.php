<?php
/**
 * My Packages Template
 *
 * @package BookingX\BookingPackages
 * @since   1.0.0
 *
 * @var array $packages Customer packages.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="bkx-my-packages">
	<h3><?php esc_html_e( 'My Packages', 'bkx-booking-packages' ); ?></h3>

	<?php if ( empty( $packages ) ) : ?>
		<div class="bkx-no-packages">
			<p><?php esc_html_e( 'You don\'t have any packages yet.', 'bkx-booking-packages' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/packages/' ) ); ?>" class="bkx-btn bkx-btn-primary">
				<?php esc_html_e( 'Browse Packages', 'bkx-booking-packages' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="bkx-packages-list">
			<?php foreach ( $packages as $customer_package ) : ?>
				<?php
				$package   = $customer_package['package'];
				$is_valid  = 'active' === $customer_package['status'] && $customer_package['uses_remaining'] > 0;
				$is_expiring = false;

				if ( $customer_package['expiry_date'] ) {
					$days_until_expiry = ( strtotime( $customer_package['expiry_date'] ) - time() ) / DAY_IN_SECONDS;
					$is_expiring       = $days_until_expiry <= 7 && $days_until_expiry > 0;
				}
				?>
				<div class="bkx-package-item <?php echo ! $is_valid ? 'bkx-package-inactive' : ''; ?> <?php echo $is_expiring ? 'bkx-package-expiring' : ''; ?>">
					<div class="bkx-package-header">
						<h4><?php echo esc_html( $package['title'] ); ?></h4>
						<span class="bkx-package-status bkx-status-<?php echo esc_attr( $customer_package['status'] ); ?>">
							<?php echo esc_html( ucfirst( $customer_package['status'] ) ); ?>
						</span>
					</div>

					<div class="bkx-package-details">
						<div class="bkx-detail-row">
							<span class="bkx-detail-label"><?php esc_html_e( 'Uses Remaining', 'bkx-booking-packages' ); ?></span>
							<span class="bkx-detail-value">
								<strong><?php echo esc_html( $customer_package['uses_remaining'] ); ?></strong>
								/ <?php echo esc_html( $customer_package['total_uses'] ); ?>
							</span>
						</div>

						<div class="bkx-uses-bar">
							<?php
							$used_percent = 100 - ( ( $customer_package['uses_remaining'] / $customer_package['total_uses'] ) * 100 );
							?>
							<div class="bkx-uses-progress" style="width: <?php echo esc_attr( $used_percent ); ?>%;"></div>
						</div>

						<div class="bkx-detail-row">
							<span class="bkx-detail-label"><?php esc_html_e( 'Purchased', 'bkx-booking-packages' ); ?></span>
							<span class="bkx-detail-value">
								<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $customer_package['purchase_date'] ) ) ); ?>
							</span>
						</div>

						<?php if ( $customer_package['expiry_date'] ) : ?>
							<div class="bkx-detail-row <?php echo $is_expiring ? 'bkx-expiring-warning' : ''; ?>">
								<span class="bkx-detail-label"><?php esc_html_e( 'Expires', 'bkx-booking-packages' ); ?></span>
								<span class="bkx-detail-value">
									<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $customer_package['expiry_date'] ) ) ); ?>
									<?php if ( $is_expiring ) : ?>
										<span class="bkx-expiry-notice">
											<?php esc_html_e( '(Expiring soon!)', 'bkx-booking-packages' ); ?>
										</span>
									<?php endif; ?>
								</span>
							</div>
						<?php else : ?>
							<div class="bkx-detail-row">
								<span class="bkx-detail-label"><?php esc_html_e( 'Expires', 'bkx-booking-packages' ); ?></span>
								<span class="bkx-detail-value bkx-no-expiry">
									<?php esc_html_e( 'Never', 'bkx-booking-packages' ); ?>
								</span>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( $is_valid ) : ?>
						<div class="bkx-package-actions">
							<a href="<?php echo esc_url( home_url( '/book/' ) ); ?>" class="bkx-btn bkx-btn-primary">
								<?php esc_html_e( 'Book Now', 'bkx-booking-packages' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

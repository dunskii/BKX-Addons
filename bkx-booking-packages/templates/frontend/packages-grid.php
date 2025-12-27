<?php
/**
 * Packages Grid Template
 *
 * @package BookingX\BookingPackages
 * @since   1.0.0
 *
 * @var array $packages Available packages.
 * @var array $atts Shortcode attributes.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$columns = isset( $atts['columns'] ) ? absint( $atts['columns'] ) : 3;
?>
<div class="bkx-packages-grid bkx-columns-<?php echo esc_attr( $columns ); ?>">
	<?php if ( empty( $packages ) ) : ?>
		<p class="bkx-no-packages"><?php esc_html_e( 'No packages available at this time.', 'bkx-booking-packages' ); ?></p>
	<?php else : ?>
		<?php foreach ( $packages as $package ) : ?>
			<div class="bkx-package-card">
				<?php if ( $package['image'] ) : ?>
					<div class="bkx-package-image">
						<img src="<?php echo esc_url( $package['image'] ); ?>" alt="<?php echo esc_attr( $package['title'] ); ?>">
					</div>
				<?php endif; ?>

				<div class="bkx-package-content">
					<h3 class="bkx-package-title"><?php echo esc_html( $package['title'] ); ?></h3>

					<?php if ( $package['excerpt'] ) : ?>
						<p class="bkx-package-excerpt"><?php echo esc_html( $package['excerpt'] ); ?></p>
					<?php endif; ?>

					<div class="bkx-package-meta">
						<span class="bkx-package-uses">
							<strong><?php echo esc_html( $package['uses'] ); ?></strong>
							<?php esc_html_e( 'uses', 'bkx-booking-packages' ); ?>
						</span>

						<?php if ( $package['validity_days'] > 0 ) : ?>
							<span class="bkx-package-validity">
								<?php
								printf(
									/* translators: %d: number of days */
									esc_html__( 'Valid for %d days', 'bkx-booking-packages' ),
									$package['validity_days']
								);
								?>
							</span>
						<?php else : ?>
							<span class="bkx-package-validity bkx-no-expiry">
								<?php esc_html_e( 'Never expires', 'bkx-booking-packages' ); ?>
							</span>
						<?php endif; ?>
					</div>

					<div class="bkx-package-pricing">
						<?php if ( $package['regular_price'] && $package['regular_price'] > $package['price'] ) : ?>
							<span class="bkx-package-regular-price">
								<?php echo esc_html( strip_tags( wc_price( $package['regular_price'] ) ?? '$' . number_format( $package['regular_price'], 2 ) ) ); ?>
							</span>
							<span class="bkx-package-savings">
								<?php
								$savings = round( ( ( $package['regular_price'] - $package['price'] ) / $package['regular_price'] ) * 100 );
								printf(
									/* translators: %d: savings percentage */
									esc_html__( 'Save %d%%', 'bkx-booking-packages' ),
									$savings
								);
								?>
							</span>
						<?php endif; ?>

						<span class="bkx-package-price">
							<?php echo esc_html( strip_tags( wc_price( $package['price'] ) ?? '$' . number_format( $package['price'], 2 ) ) ); ?>
						</span>
					</div>

					<div class="bkx-package-actions">
						<?php if ( is_user_logged_in() ) : ?>
							<button type="button" class="bkx-btn bkx-btn-primary bkx-purchase-package"
									data-package-id="<?php echo esc_attr( $package['id'] ); ?>">
								<?php esc_html_e( 'Buy Now', 'bkx-booking-packages' ); ?>
							</button>
						<?php else : ?>
							<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bkx-btn bkx-btn-primary">
								<?php esc_html_e( 'Login to Purchase', 'bkx-booking-packages' ); ?>
							</a>
						<?php endif; ?>

						<button type="button" class="bkx-btn bkx-btn-secondary bkx-view-package"
								data-package-id="<?php echo esc_attr( $package['id'] ); ?>">
							<?php esc_html_e( 'Details', 'bkx-booking-packages' ); ?>
						</button>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

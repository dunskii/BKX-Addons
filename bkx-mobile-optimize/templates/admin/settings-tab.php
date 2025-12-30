<?php
/**
 * Mobile Optimization Settings Tab.
 *
 * @package BookingX\MobileOptimize
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_mobile_optimize_settings', array() );
?>

<div class="bkx-mobile-settings-tab">
	<p class="description">
		<?php esc_html_e( 'Configure mobile booking optimizations.', 'bkx-mobile-optimize' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-mobile-optimize' ) ); ?>">
			<?php esc_html_e( 'Open full settings', 'bkx-mobile-optimize' ); ?>
		</a>
	</p>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Mobile Optimization', 'bkx-mobile-optimize' ); ?></th>
			<td>
				<span class="bkx-status-badge <?php echo ! empty( $settings['enabled'] ) ? 'active' : 'inactive'; ?>">
					<?php echo ! empty( $settings['enabled'] ) ? esc_html__( 'Active', 'bkx-mobile-optimize' ) : esc_html__( 'Inactive', 'bkx-mobile-optimize' ); ?>
				</span>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Features Enabled', 'bkx-mobile-optimize' ); ?></th>
			<td>
				<ul class="bkx-feature-list">
					<?php
					$features = array(
						'responsive_form'     => __( 'Responsive Form', 'bkx-mobile-optimize' ),
						'touch_friendly'      => __( 'Touch-Friendly UI', 'bkx-mobile-optimize' ),
						'swipe_calendar'      => __( 'Swipe Calendar', 'bkx-mobile-optimize' ),
						'floating_cta'        => __( 'Floating CTA', 'bkx-mobile-optimize' ),
						'skeleton_loading'    => __( 'Skeleton Loading', 'bkx-mobile-optimize' ),
					);

					foreach ( $features as $key => $label ) :
						$enabled = ! empty( $settings[ $key ] );
						?>
						<li class="<?php echo $enabled ? 'enabled' : 'disabled'; ?>">
							<span class="dashicons <?php echo $enabled ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
							<?php echo esc_html( $label ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</td>
		</tr>
	</table>
</div>

<style>
.bkx-status-badge {
	display: inline-block;
	padding: 4px 12px;
	border-radius: 20px;
	font-size: 12px;
	font-weight: 500;
}
.bkx-status-badge.active {
	background: #d1fae5;
	color: #065f46;
}
.bkx-status-badge.inactive {
	background: #f3f4f6;
	color: #6b7280;
}
.bkx-feature-list {
	margin: 0;
	padding: 0;
	list-style: none;
}
.bkx-feature-list li {
	display: flex;
	align-items: center;
	gap: 5px;
	margin-bottom: 5px;
}
.bkx-feature-list li.enabled .dashicons {
	color: #10b981;
}
.bkx-feature-list li.disabled .dashicons {
	color: #9ca3af;
}
</style>

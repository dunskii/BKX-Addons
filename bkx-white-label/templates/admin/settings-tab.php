<?php
/**
 * BookingX settings tab integration template.
 *
 * @package BookingX\WhiteLabel
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WhiteLabel\WhiteLabelAddon::get_instance();
$settings = $addon->get_settings();
$enabled  = ! empty( $settings['enabled'] );
?>
<div class="bkx-settings-white-label-tab">
	<h2><?php esc_html_e( 'White Label Solution', 'bkx-white-label' ); ?></h2>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'bkx-white-label' ); ?></th>
			<td>
				<?php if ( $enabled ) : ?>
					<span class="bkx-badge bkx-badge-success"><?php esc_html_e( 'Active', 'bkx-white-label' ); ?></span>
				<?php else : ?>
					<span class="bkx-badge bkx-badge-warning"><?php esc_html_e( 'Inactive', 'bkx-white-label' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( $enabled ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Brand Name', 'bkx-white-label' ); ?></th>
				<td>
					<?php if ( ! empty( $settings['brand_name'] ) ) : ?>
						<strong><?php echo esc_html( $settings['brand_name'] ); ?></strong>
					<?php else : ?>
						<em><?php esc_html_e( 'Not configured', 'bkx-white-label' ); ?></em>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Features', 'bkx-white-label' ); ?></th>
				<td>
					<ul style="margin: 0;">
						<li>
							<?php if ( ! empty( $settings['brand_logo'] ) ) : ?>
								<span class="dashicons dashicons-yes" style="color: green;"></span>
							<?php else : ?>
								<span class="dashicons dashicons-no" style="color: red;"></span>
							<?php endif; ?>
							<?php esc_html_e( 'Custom Logo', 'bkx-white-label' ); ?>
						</li>
						<li>
							<?php if ( ! empty( $settings['primary_color'] ) && $settings['primary_color'] !== '#2271b1' ) : ?>
								<span class="dashicons dashicons-yes" style="color: green;"></span>
							<?php else : ?>
								<span class="dashicons dashicons-no" style="color: red;"></span>
							<?php endif; ?>
							<?php esc_html_e( 'Custom Colors', 'bkx-white-label' ); ?>
						</li>
						<li>
							<?php if ( ! empty( $settings['email_header_image'] ) || ! empty( $settings['email_footer_text'] ) ) : ?>
								<span class="dashicons dashicons-yes" style="color: green;"></span>
							<?php else : ?>
								<span class="dashicons dashicons-no" style="color: red;"></span>
							<?php endif; ?>
							<?php esc_html_e( 'Email Branding', 'bkx-white-label' ); ?>
						</li>
						<li>
							<?php if ( ! empty( $settings['login_logo'] ) || ! empty( $settings['login_background'] ) ) : ?>
								<span class="dashicons dashicons-yes" style="color: green;"></span>
							<?php else : ?>
								<span class="dashicons dashicons-no" style="color: red;"></span>
							<?php endif; ?>
							<?php esc_html_e( 'Login Customization', 'bkx-white-label' ); ?>
						</li>
						<li>
							<?php if ( ! empty( $settings['hide_bookingx_branding'] ) ) : ?>
								<span class="dashicons dashicons-yes" style="color: green;"></span>
							<?php else : ?>
								<span class="dashicons dashicons-no" style="color: red;"></span>
							<?php endif; ?>
							<?php esc_html_e( 'Branding Hidden', 'bkx-white-label' ); ?>
						</li>
					</ul>
				</td>
			</tr>
		<?php endif; ?>
	</table>

	<h3><?php esc_html_e( 'Quick Access', 'bkx-white-label' ); ?></h3>
	<p>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-white-label' ) ); ?>" class="button">
			<span class="dashicons dashicons-admin-customizer" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Branding Settings', 'bkx-white-label' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-white-label&tab=colors' ) ); ?>" class="button">
			<span class="dashicons dashicons-admin-appearance" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Color Scheme', 'bkx-white-label' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-white-label&tab=emails' ) ); ?>" class="button">
			<span class="dashicons dashicons-email" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Email Customization', 'bkx-white-label' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-white-label&tab=login' ) ); ?>" class="button">
			<span class="dashicons dashicons-admin-network" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Login Page', 'bkx-white-label' ); ?>
		</a>
	</p>
</div>

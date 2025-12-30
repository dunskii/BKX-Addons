<?php
/**
 * HIPAA Settings Tab for BookingX Settings.
 *
 * @package BookingX\HIPAA
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\HIPAA\HIPAAAddon::get_instance();
$settings = get_option( 'bkx_hipaa_settings', array() );
$enabled  = isset( $settings['enabled'] ) ? $settings['enabled'] : true;
?>

<div class="bkx-hipaa-settings-tab">
	<h2><?php esc_html_e( 'HIPAA Compliance', 'bkx-hipaa' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'HIPAA compliance features for healthcare providers handling Protected Health Information (PHI).', 'bkx-hipaa' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'bkx-hipaa' ); ?></th>
			<td>
				<?php if ( $enabled ) : ?>
					<span class="bkx-status-badge bkx-status-active">
						<span class="dashicons dashicons-shield-alt"></span>
						<?php esc_html_e( 'HIPAA Mode Active', 'bkx-hipaa' ); ?>
					</span>
				<?php else : ?>
					<span class="bkx-status-badge bkx-status-inactive">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'HIPAA Mode Inactive', 'bkx-hipaa' ); ?>
					</span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Features', 'bkx-hipaa' ); ?></th>
			<td>
				<ul>
					<li><strong><?php esc_html_e( 'PHI Encryption', 'bkx-hipaa' ); ?></strong> - <?php esc_html_e( 'AES-256 encryption for sensitive data', 'bkx-hipaa' ); ?></li>
					<li><strong><?php esc_html_e( 'Audit Logging', 'bkx-hipaa' ); ?></strong> - <?php esc_html_e( '6-year retention as required by HIPAA', 'bkx-hipaa' ); ?></li>
					<li><strong><?php esc_html_e( 'Access Control', 'bkx-hipaa' ); ?></strong> - <?php esc_html_e( 'Role-based PHI access permissions', 'bkx-hipaa' ); ?></li>
					<li><strong><?php esc_html_e( 'BAA Management', 'bkx-hipaa' ); ?></strong> - <?php esc_html_e( 'Track Business Associate Agreements', 'bkx-hipaa' ); ?></li>
					<li><strong><?php esc_html_e( 'Session Security', 'bkx-hipaa' ); ?></strong> - <?php esc_html_e( 'Auto-logout and strong passwords', 'bkx-hipaa' ); ?></li>
				</ul>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Configuration', 'bkx-hipaa' ); ?></th>
			<td>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hipaa' ) ); ?>" class="button">
					<span class="dashicons dashicons-shield-alt"></span>
					<?php esc_html_e( 'HIPAA Dashboard', 'bkx-hipaa' ); ?>
				</a>
			</td>
		</tr>
	</table>
</div>

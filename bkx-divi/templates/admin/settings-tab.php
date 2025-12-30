<?php
/**
 * Divi Settings Tab for BookingX Settings.
 *
 * @package BookingX\Divi
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\Divi\DiviAddon::get_instance();
$settings = get_option( 'bkx_divi_settings', array() );
$enabled  = isset( $settings['enable_modules'] ) ? $settings['enable_modules'] : true;
?>

<div class="bkx-divi-settings-tab">
	<h2><?php esc_html_e( 'Divi Integration', 'bkx-divi' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Use BookingX modules in the Divi Builder to create beautiful booking pages.', 'bkx-divi' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'bkx-divi' ); ?></th>
			<td>
				<?php if ( $enabled ) : ?>
					<span class="bkx-status-badge bkx-status-active">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Active', 'bkx-divi' ); ?>
					</span>
				<?php else : ?>
					<span class="bkx-status-badge bkx-status-inactive">
						<span class="dashicons dashicons-minus"></span>
						<?php esc_html_e( 'Inactive', 'bkx-divi' ); ?>
					</span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Available Modules', 'bkx-divi' ); ?></th>
			<td>
				<ul>
					<li><strong>BKX Booking Form</strong> - <?php esc_html_e( 'Complete booking form', 'bkx-divi' ); ?></li>
					<li><strong>BKX Service List</strong> - <?php esc_html_e( 'Display services', 'bkx-divi' ); ?></li>
					<li><strong>BKX Resource List</strong> - <?php esc_html_e( 'Display staff/resources', 'bkx-divi' ); ?></li>
					<li><strong>BKX Availability Calendar</strong> - <?php esc_html_e( 'Show availability', 'bkx-divi' ); ?></li>
					<li><strong>BKX Booking Button</strong> - <?php esc_html_e( 'Call-to-action button', 'bkx-divi' ); ?></li>
				</ul>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Configuration', 'bkx-divi' ); ?></th>
			<td>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-divi' ) ); ?>" class="button">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Configure Divi Integration', 'bkx-divi' ); ?>
				</a>
			</td>
		</tr>
	</table>
</div>

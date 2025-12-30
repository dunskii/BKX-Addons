<?php
/**
 * FreshBooks Settings Tab for BookingX Settings.
 *
 * @package BookingX\FreshBooks
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\FreshBooks\FreshBooksAddon::get_instance();
$settings  = get_option( 'bkx_freshbooks_settings', array() );
$connected = $addon->is_connected();
?>

<div class="bkx-freshbooks-settings-tab">
	<h2><?php esc_html_e( 'FreshBooks Integration', 'bkx-freshbooks' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Sync your BookingX bookings with FreshBooks for invoicing and accounting.', 'bkx-freshbooks' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'bkx-freshbooks' ); ?></th>
			<td>
				<?php if ( $connected ) : ?>
					<span class="bkx-status-badge bkx-status-active">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Connected', 'bkx-freshbooks' ); ?>
					</span>
				<?php else : ?>
					<span class="bkx-status-badge bkx-status-inactive">
						<span class="dashicons dashicons-minus"></span>
						<?php esc_html_e( 'Not Connected', 'bkx-freshbooks' ); ?>
					</span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Configuration', 'bkx-freshbooks' ); ?></th>
			<td>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-freshbooks' ) ); ?>" class="button">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Configure FreshBooks', 'bkx-freshbooks' ); ?>
				</a>
			</td>
		</tr>
	</table>
</div>

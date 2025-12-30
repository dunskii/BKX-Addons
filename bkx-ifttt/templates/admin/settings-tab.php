<?php
/**
 * IFTTT Settings Tab for BookingX Settings.
 *
 * @package BookingX\IFTTT
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\IFTTT\IFTTTAddon::get_instance();
$settings = get_option( 'bkx_ifttt_settings', array() );
$enabled  = ! empty( $settings['enabled'] );
?>

<div class="bkx-ifttt-settings-tab">
	<h2><?php esc_html_e( 'IFTTT Integration', 'bkx-ifttt' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Connect BookingX with IFTTT to automate your booking workflow with 700+ apps and services.', 'bkx-ifttt' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'bkx-ifttt' ); ?></th>
			<td>
				<?php if ( $enabled ) : ?>
					<span class="bkx-status-badge bkx-status-active">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Connected', 'bkx-ifttt' ); ?>
					</span>
				<?php else : ?>
					<span class="bkx-status-badge bkx-status-inactive">
						<span class="dashicons dashicons-minus"></span>
						<?php esc_html_e( 'Disabled', 'bkx-ifttt' ); ?>
					</span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Configuration', 'bkx-ifttt' ); ?></th>
			<td>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-ifttt' ) ); ?>" class="button">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Configure IFTTT', 'bkx-ifttt' ); ?>
				</a>
				<p class="description">
					<?php esc_html_e( 'Manage triggers, actions, webhooks, and view logs.', 'bkx-ifttt' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<?php if ( $enabled ) : ?>
		<h3><?php esc_html_e( 'Quick Stats', 'bkx-ifttt' ); ?></h3>
		<?php
		$webhook_manager = $addon->get_service( 'webhook_manager' );
		$stats           = $webhook_manager ? $webhook_manager->get_stats() : array();
		?>
		<table class="widefat striped" style="max-width: 400px;">
			<tr>
				<td><?php esc_html_e( 'Active Webhooks', 'bkx-ifttt' ); ?></td>
				<td><strong><?php echo esc_html( $stats['active_webhooks'] ?? 0 ); ?></strong></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Total Deliveries', 'bkx-ifttt' ); ?></td>
				<td><strong><?php echo esc_html( $stats['total_sent'] ?? 0 ); ?></strong></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Success Rate', 'bkx-ifttt' ); ?></td>
				<td><strong><?php echo esc_html( ( $stats['success_rate'] ?? 100 ) . '%' ); ?></strong></td>
			</tr>
		</table>
	<?php endif; ?>
</div>

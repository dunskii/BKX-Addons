<?php
/**
 * MYOB Settings Tab for BookingX Settings.
 *
 * @package BookingX\MYOB
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\MYOB\MYOBAddon::get_instance();
$settings  = get_option( 'bkx_myob_settings', array() );
$connected = $addon->is_connected();
?>

<div class="bkx-myob-settings-tab">
	<h2><?php esc_html_e( 'MYOB Integration', 'bkx-myob' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Sync your BookingX bookings, invoices, and customers with MYOB accounting software.', 'bkx-myob' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'bkx-myob' ); ?></th>
			<td>
				<?php if ( $connected ) : ?>
					<span class="bkx-status-badge bkx-status-active">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Connected', 'bkx-myob' ); ?>
					</span>
					<?php if ( ! empty( $settings['company_file_name'] ) ) : ?>
						<span class="description"><?php echo esc_html( $settings['company_file_name'] ); ?></span>
					<?php endif; ?>
				<?php elseif ( ! empty( $settings['enabled'] ) ) : ?>
					<span class="bkx-status-badge bkx-status-warning">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Not Connected', 'bkx-myob' ); ?>
					</span>
				<?php else : ?>
					<span class="bkx-status-badge bkx-status-inactive">
						<span class="dashicons dashicons-minus"></span>
						<?php esc_html_e( 'Disabled', 'bkx-myob' ); ?>
					</span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Configuration', 'bkx-myob' ); ?></th>
			<td>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-myob' ) ); ?>" class="button">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Configure MYOB', 'bkx-myob' ); ?>
				</a>
			</td>
		</tr>
	</table>

	<?php if ( $connected ) : ?>
		<h3><?php esc_html_e( 'Sync Summary', 'bkx-myob' ); ?></h3>
		<?php
		global $wpdb;
		$total_synced = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_myob_synced'"
		);
		$pending_sync = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_myob_synced'
			 WHERE p.post_type = 'bkx_booking'
			 AND p.post_status IN ('bkx-ack', 'bkx-completed')
			 AND pm.meta_value IS NULL"
		);
		?>
		<table class="widefat striped" style="max-width: 400px;">
			<tr>
				<td><?php esc_html_e( 'Bookings Synced', 'bkx-myob' ); ?></td>
				<td><strong><?php echo esc_html( $total_synced ); ?></strong></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Pending Sync', 'bkx-myob' ); ?></td>
				<td><strong><?php echo esc_html( $pending_sync ); ?></strong></td>
			</tr>
		</table>
	<?php endif; ?>
</div>

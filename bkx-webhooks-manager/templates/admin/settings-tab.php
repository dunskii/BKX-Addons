<?php
/**
 * BookingX settings tab integration template.
 *
 * @package BookingX\WebhooksManager
 */

defined( 'ABSPATH' ) || exit;

$addon            = \BookingX\WebhooksManager\WebhooksManagerAddon::get_instance();
$settings         = get_option( 'bkx_webhooks_manager_settings', array() );
$webhook_manager  = $addon->get_service( 'webhook_manager' );
$delivery_service = $addon->get_service( 'delivery_service' );

$webhook_count = $webhook_manager->get_count( 'active' );
$stats         = $delivery_service->get_stats(
	array(
		'date_from' => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
		'date_to'   => gmdate( 'Y-m-d' ),
	)
);
?>
<div class="bkx-settings-webhooks-tab">
	<h2><?php esc_html_e( 'Webhooks Manager', 'bkx-webhooks-manager' ); ?></h2>

	<div class="bkx-webhooks-status">
		<div class="bkx-status-item">
			<span class="value"><?php echo esc_html( $webhook_count ); ?></span>
			<span class="label"><?php esc_html_e( 'Active Webhooks', 'bkx-webhooks-manager' ); ?></span>
		</div>
		<div class="bkx-status-item">
			<span class="value"><?php echo esc_html( number_format( $stats['delivered'] ) ); ?></span>
			<span class="label"><?php esc_html_e( 'Delivered (7 days)', 'bkx-webhooks-manager' ); ?></span>
		</div>
		<div class="bkx-status-item">
			<span class="value"><?php echo esc_html( number_format( $stats['failed'] ) ); ?></span>
			<span class="label"><?php esc_html_e( 'Failed (7 days)', 'bkx-webhooks-manager' ); ?></span>
		</div>
		<div class="bkx-status-item">
			<span class="value"><?php echo esc_html( $stats['avg_response_time'] ); ?> ms</span>
			<span class="label"><?php esc_html_e( 'Avg Response Time', 'bkx-webhooks-manager' ); ?></span>
		</div>
	</div>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'bkx-webhooks-manager' ); ?></th>
			<td>
				<?php if ( ! empty( $settings['enabled'] ) ) : ?>
					<span class="bkx-badge bkx-badge-success"><?php esc_html_e( 'Enabled', 'bkx-webhooks-manager' ); ?></span>
				<?php else : ?>
					<span class="bkx-badge bkx-badge-warning"><?php esc_html_e( 'Disabled', 'bkx-webhooks-manager' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Delivery Mode', 'bkx-webhooks-manager' ); ?></th>
			<td>
				<?php echo ! empty( $settings['async_delivery'] ) ? esc_html__( 'Asynchronous', 'bkx-webhooks-manager' ) : esc_html__( 'Synchronous', 'bkx-webhooks-manager' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Signature Algorithm', 'bkx-webhooks-manager' ); ?></th>
			<td>
				<code>HMAC-<?php echo esc_html( strtoupper( $settings['signature_algorithm'] ?? 'SHA256' ) ); ?></code>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Log Retention', 'bkx-webhooks-manager' ); ?></th>
			<td>
				<?php
				printf(
					/* translators: %d: number of days */
					esc_html__( '%d days', 'bkx-webhooks-manager' ),
					absint( $settings['log_retention_days'] ?? 30 )
				);
				?>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Quick Actions', 'bkx-webhooks-manager' ); ?></h3>
	<p>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-webhooks-manager' ) ); ?>" class="button">
			<span class="dashicons dashicons-rest-api" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Manage Webhooks', 'bkx-webhooks-manager' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-webhooks-manager&tab=deliveries' ) ); ?>" class="button">
			<span class="dashicons dashicons-migrate" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Delivery Log', 'bkx-webhooks-manager' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-webhooks-manager&tab=settings' ) ); ?>" class="button">
			<span class="dashicons dashicons-admin-generic" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Settings', 'bkx-webhooks-manager' ); ?>
		</a>
	</p>
</div>

<?php
/**
 * Dashboard tab template.
 *
 * @package BookingX\HubSpot
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$addon        = \BookingX\HubSpot\HubSpotAddon::get_instance();
$is_connected = $addon->is_connected();
$settings     = $addon->get_settings();

// Get sync statistics.
$mapping_table = $wpdb->prefix . 'bkx_hs_mappings';
$queue_table   = $wpdb->prefix . 'bkx_hs_queue';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$contact_count = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$mapping_table} WHERE hs_object_type = 'contact'"
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$deal_count = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$mapping_table} WHERE hs_object_type = 'deal'"
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$queue_pending = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$queue_table} WHERE status = 'pending'"
);

// Get recent sync logs.
$log_table = $wpdb->prefix . 'bkx_hs_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$recent_logs = $wpdb->get_results(
	"SELECT * FROM {$log_table} ORDER BY created_at DESC LIMIT 10"
);
?>

<div class="bkx-hs-dashboard">
	<!-- Connection Status -->
	<div class="bkx-card bkx-connection-status">
		<h2><?php esc_html_e( 'Connection Status', 'bkx-hubspot' ); ?></h2>

		<?php if ( $is_connected ) : ?>
			<div class="bkx-status-connected">
				<span class="dashicons dashicons-yes-alt"></span>
				<span><?php esc_html_e( 'Connected to HubSpot', 'bkx-hubspot' ); ?></span>
			</div>

			<div class="bkx-connection-actions">
				<button type="button" class="button" id="bkx-hs-test-connection">
					<?php esc_html_e( 'Test Connection', 'bkx-hubspot' ); ?>
				</button>
				<button type="button" class="button button-link-delete" id="bkx-hs-disconnect">
					<?php esc_html_e( 'Disconnect', 'bkx-hubspot' ); ?>
				</button>
			</div>

			<div id="bkx-hs-connection-info" class="bkx-connection-info" style="display: none;"></div>
		<?php else : ?>
			<div class="bkx-status-disconnected">
				<span class="dashicons dashicons-warning"></span>
				<span><?php esc_html_e( 'Not connected to HubSpot', 'bkx-hubspot' ); ?></span>
			</div>

			<?php if ( ! empty( $settings['client_id'] ) && ! empty( $settings['client_secret'] ) ) : ?>
				<p><?php esc_html_e( 'Click the button below to authorize your HubSpot account.', 'bkx-hubspot' ); ?></p>
				<button type="button" class="button button-primary" id="bkx-hs-connect">
					<?php esc_html_e( 'Connect to HubSpot', 'bkx-hubspot' ); ?>
				</button>
			<?php else : ?>
				<p>
					<?php
					printf(
						/* translators: %s: settings link */
						esc_html__( 'Please configure your HubSpot App credentials in the %s tab.', 'bkx-hubspot' ),
						'<a href="' . esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-hubspot&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'bkx-hubspot' ) . '</a>'
					);
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if ( $is_connected ) : ?>
		<!-- Sync Statistics -->
		<div class="bkx-stats-row">
			<div class="bkx-stat-card">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $contact_count ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Contacts Synced', 'bkx-hubspot' ); ?></span>
			</div>
			<div class="bkx-stat-card">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $deal_count ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Deals Created', 'bkx-hubspot' ); ?></span>
			</div>
			<div class="bkx-stat-card">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $queue_pending ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Pending in Queue', 'bkx-hubspot' ); ?></span>
			</div>
		</div>

		<!-- Manual Sync -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Manual Sync', 'bkx-hubspot' ); ?></h2>
			<p><?php esc_html_e( 'Manually trigger a sync of your BookingX data to HubSpot.', 'bkx-hubspot' ); ?></p>

			<div class="bkx-sync-controls">
				<select id="bkx-hs-sync-type">
					<option value="all"><?php esc_html_e( 'All Data', 'bkx-hubspot' ); ?></option>
					<option value="contacts"><?php esc_html_e( 'Contacts Only', 'bkx-hubspot' ); ?></option>
					<option value="deals"><?php esc_html_e( 'Deals Only', 'bkx-hubspot' ); ?></option>
				</select>

				<input type="number" id="bkx-hs-sync-limit" value="100" min="1" max="1000" class="small-text">
				<span class="description"><?php esc_html_e( 'records', 'bkx-hubspot' ); ?></span>

				<button type="button" class="button button-primary" id="bkx-hs-sync-now">
					<?php esc_html_e( 'Sync Now', 'bkx-hubspot' ); ?>
				</button>
			</div>

			<div id="bkx-hs-sync-progress" class="bkx-sync-progress" style="display: none;">
				<span class="spinner is-active"></span>
				<span class="bkx-sync-message"><?php esc_html_e( 'Syncing...', 'bkx-hubspot' ); ?></span>
			</div>

			<div id="bkx-hs-sync-result" class="bkx-sync-result" style="display: none;"></div>
		</div>

		<!-- Recent Activity -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Recent Sync Activity', 'bkx-hubspot' ); ?></h2>

			<?php if ( empty( $recent_logs ) ) : ?>
				<p class="bkx-no-items"><?php esc_html_e( 'No sync activity yet.', 'bkx-hubspot' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'bkx-hubspot' ); ?></th>
							<th><?php esc_html_e( 'Direction', 'bkx-hubspot' ); ?></th>
							<th><?php esc_html_e( 'Action', 'bkx-hubspot' ); ?></th>
							<th><?php esc_html_e( 'Object', 'bkx-hubspot' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-hubspot' ); ?></th>
							<th><?php esc_html_e( 'Message', 'bkx-hubspot' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_logs as $log ) : ?>
							<tr>
								<td>
									<?php
									echo esc_html(
										wp_date(
											get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
											strtotime( $log->created_at )
										)
									);
									?>
								</td>
								<td>
									<?php if ( 'wp_to_hs' === $log->direction ) : ?>
										<span class="bkx-direction bkx-direction-out">WP &rarr; HS</span>
									<?php else : ?>
										<span class="bkx-direction bkx-direction-in">HS &rarr; WP</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $log->action ); ?></td>
								<td><?php echo esc_html( ucfirst( $log->hs_object_type ) ); ?></td>
								<td>
									<span class="bkx-status bkx-status-<?php echo esc_attr( $log->status ); ?>">
										<?php echo esc_html( ucfirst( $log->status ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $log->message ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-hubspot&tab=logs' ) ); ?>">
						<?php esc_html_e( 'View all logs', 'bkx-hubspot' ); ?> &rarr;
					</a>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<!-- Help & Documentation -->
	<div class="bkx-card">
		<h2><?php esc_html_e( 'Setup Guide', 'bkx-hubspot' ); ?></h2>

		<ol class="bkx-setup-steps">
			<li>
				<strong><?php esc_html_e( 'Create a HubSpot App', 'bkx-hubspot' ); ?></strong>
				<p>
					<?php esc_html_e( 'Go to HubSpot Developer Account > Apps > Create App. Add the redirect URL:', 'bkx-hubspot' ); ?>
					<code><?php echo esc_html( add_query_arg( 'bkx_hs_oauth', '1', admin_url( 'admin.php' ) ) ); ?></code>
				</p>
			</li>
			<li>
				<strong><?php esc_html_e( 'Configure API Settings', 'bkx-hubspot' ); ?></strong>
				<p><?php esc_html_e( 'Copy your App ID and Client Secret and paste them in the Settings tab.', 'bkx-hubspot' ); ?></p>
			</li>
			<li>
				<strong><?php esc_html_e( 'Authorize the Connection', 'bkx-hubspot' ); ?></strong>
				<p><?php esc_html_e( 'Click "Connect to HubSpot" to authorize the integration with your HubSpot portal.', 'bkx-hubspot' ); ?></p>
			</li>
			<li>
				<strong><?php esc_html_e( 'Configure Property Mappings', 'bkx-hubspot' ); ?></strong>
				<p><?php esc_html_e( 'Customize how BookingX fields map to HubSpot properties in the Property Mappings tab.', 'bkx-hubspot' ); ?></p>
			</li>
		</ol>
	</div>
</div>

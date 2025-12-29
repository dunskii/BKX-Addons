<?php
/**
 * Dashboard tab template.
 *
 * @package BookingX\Salesforce
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$addon        = \BookingX\Salesforce\SalesforceAddon::get_instance();
$is_connected = $addon->is_connected();
$settings     = $addon->get_settings();

// Get sync statistics.
$mapping_table = $wpdb->prefix . 'bkx_sf_mappings';
$queue_table   = $wpdb->prefix . 'bkx_sf_queue';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$contact_count = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$mapping_table} WHERE sf_object_type = 'Contact'"
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$lead_count = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$mapping_table} WHERE sf_object_type = 'Lead'"
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$opp_count = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$mapping_table} WHERE sf_object_type = 'Opportunity'"
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$queue_pending = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$queue_table} WHERE status = 'pending'"
);

// Get recent sync logs.
$log_table = $wpdb->prefix . 'bkx_sf_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$recent_logs = $wpdb->get_results(
	"SELECT * FROM {$log_table} ORDER BY created_at DESC LIMIT 10"
);
?>

<div class="bkx-sf-dashboard">
	<!-- Connection Status -->
	<div class="bkx-card bkx-connection-status">
		<h2><?php esc_html_e( 'Connection Status', 'bkx-salesforce' ); ?></h2>

		<?php if ( $is_connected ) : ?>
			<div class="bkx-status-connected">
				<span class="dashicons dashicons-yes-alt"></span>
				<span><?php esc_html_e( 'Connected to Salesforce', 'bkx-salesforce' ); ?></span>
			</div>

			<div class="bkx-connection-actions">
				<button type="button" class="button" id="bkx-sf-test-connection">
					<?php esc_html_e( 'Test Connection', 'bkx-salesforce' ); ?>
				</button>
				<button type="button" class="button button-link-delete" id="bkx-sf-disconnect">
					<?php esc_html_e( 'Disconnect', 'bkx-salesforce' ); ?>
				</button>
			</div>

			<div id="bkx-sf-connection-info" class="bkx-connection-info" style="display: none;">
				<!-- Populated via AJAX -->
			</div>
		<?php else : ?>
			<div class="bkx-status-disconnected">
				<span class="dashicons dashicons-warning"></span>
				<span><?php esc_html_e( 'Not connected to Salesforce', 'bkx-salesforce' ); ?></span>
			</div>

			<?php if ( ! empty( $settings['client_id'] ) && ! empty( $settings['client_secret'] ) ) : ?>
				<p><?php esc_html_e( 'Click the button below to authorize your Salesforce account.', 'bkx-salesforce' ); ?></p>
				<button type="button" class="button button-primary" id="bkx-sf-connect">
					<?php esc_html_e( 'Connect to Salesforce', 'bkx-salesforce' ); ?>
				</button>
			<?php else : ?>
				<p>
					<?php
					printf(
						/* translators: %s: settings link */
						esc_html__( 'Please configure your Salesforce Connected App credentials in the %s tab.', 'bkx-salesforce' ),
						'<a href="' . esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-salesforce&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'bkx-salesforce' ) . '</a>'
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
				<span class="bkx-stat-label"><?php esc_html_e( 'Contacts Synced', 'bkx-salesforce' ); ?></span>
			</div>
			<div class="bkx-stat-card">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $lead_count ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Leads Synced', 'bkx-salesforce' ); ?></span>
			</div>
			<div class="bkx-stat-card">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $opp_count ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Opportunities Created', 'bkx-salesforce' ); ?></span>
			</div>
			<div class="bkx-stat-card">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $queue_pending ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Pending in Queue', 'bkx-salesforce' ); ?></span>
			</div>
		</div>

		<!-- Manual Sync -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Manual Sync', 'bkx-salesforce' ); ?></h2>
			<p><?php esc_html_e( 'Manually trigger a sync of your BookingX data to Salesforce.', 'bkx-salesforce' ); ?></p>

			<div class="bkx-sync-controls">
				<select id="bkx-sf-sync-type">
					<option value="all"><?php esc_html_e( 'All Data', 'bkx-salesforce' ); ?></option>
					<option value="contacts"><?php esc_html_e( 'Contacts Only', 'bkx-salesforce' ); ?></option>
					<option value="leads"><?php esc_html_e( 'Leads Only', 'bkx-salesforce' ); ?></option>
					<option value="opportunities"><?php esc_html_e( 'Opportunities Only', 'bkx-salesforce' ); ?></option>
				</select>

				<input type="number" id="bkx-sf-sync-limit" value="100" min="1" max="1000" class="small-text">
				<span class="description"><?php esc_html_e( 'records', 'bkx-salesforce' ); ?></span>

				<button type="button" class="button button-primary" id="bkx-sf-sync-now">
					<?php esc_html_e( 'Sync Now', 'bkx-salesforce' ); ?>
				</button>
			</div>

			<div id="bkx-sf-sync-progress" class="bkx-sync-progress" style="display: none;">
				<span class="spinner is-active"></span>
				<span class="bkx-sync-message"><?php esc_html_e( 'Syncing...', 'bkx-salesforce' ); ?></span>
			</div>

			<div id="bkx-sf-sync-result" class="bkx-sync-result" style="display: none;">
				<!-- Populated via AJAX -->
			</div>
		</div>

		<!-- Recent Activity -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Recent Sync Activity', 'bkx-salesforce' ); ?></h2>

			<?php if ( empty( $recent_logs ) ) : ?>
				<p class="bkx-no-items"><?php esc_html_e( 'No sync activity yet.', 'bkx-salesforce' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'bkx-salesforce' ); ?></th>
							<th><?php esc_html_e( 'Direction', 'bkx-salesforce' ); ?></th>
							<th><?php esc_html_e( 'Action', 'bkx-salesforce' ); ?></th>
							<th><?php esc_html_e( 'Object', 'bkx-salesforce' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-salesforce' ); ?></th>
							<th><?php esc_html_e( 'Message', 'bkx-salesforce' ); ?></th>
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
									<?php if ( 'wp_to_sf' === $log->direction ) : ?>
										<span class="bkx-direction bkx-direction-out">WP &rarr; SF</span>
									<?php else : ?>
										<span class="bkx-direction bkx-direction-in">SF &rarr; WP</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $log->action ); ?></td>
								<td><?php echo esc_html( $log->sf_object_type ); ?></td>
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
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-salesforce&tab=logs' ) ); ?>">
						<?php esc_html_e( 'View all logs', 'bkx-salesforce' ); ?> &rarr;
					</a>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<!-- Help & Documentation -->
	<div class="bkx-card">
		<h2><?php esc_html_e( 'Setup Guide', 'bkx-salesforce' ); ?></h2>

		<ol class="bkx-setup-steps">
			<li>
				<strong><?php esc_html_e( 'Create a Connected App in Salesforce', 'bkx-salesforce' ); ?></strong>
				<p>
					<?php esc_html_e( 'Go to Setup > Apps > App Manager > New Connected App. Enable OAuth Settings and add the callback URL:', 'bkx-salesforce' ); ?>
					<code><?php echo esc_html( add_query_arg( 'bkx_sf_oauth', '1', admin_url( 'admin.php' ) ) ); ?></code>
				</p>
			</li>
			<li>
				<strong><?php esc_html_e( 'Configure API Settings', 'bkx-salesforce' ); ?></strong>
				<p><?php esc_html_e( 'Copy your Consumer Key and Secret from the Connected App and paste them in the Settings tab.', 'bkx-salesforce' ); ?></p>
			</li>
			<li>
				<strong><?php esc_html_e( 'Authorize the Connection', 'bkx-salesforce' ); ?></strong>
				<p><?php esc_html_e( 'Click "Connect to Salesforce" to authorize the integration with your Salesforce org.', 'bkx-salesforce' ); ?></p>
			</li>
			<li>
				<strong><?php esc_html_e( 'Configure Field Mappings', 'bkx-salesforce' ); ?></strong>
				<p><?php esc_html_e( 'Customize how BookingX fields map to Salesforce fields in the Field Mappings tab.', 'bkx-salesforce' ); ?></p>
			</li>
		</ol>
	</div>
</div>

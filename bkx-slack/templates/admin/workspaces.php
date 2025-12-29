<?php
/**
 * Workspaces tab template.
 *
 * @package BookingX\Slack
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_slack_settings', array() );
$api = new \BookingX\Slack\Services\SlackApi();
?>

<div class="bkx-slack-workspaces">
	<div class="bkx-card">
		<h2><?php esc_html_e( 'Connect Workspace', 'bkx-slack' ); ?></h2>

		<?php if ( empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'Please configure your Slack App credentials in the Settings tab first.', 'bkx-slack' ); ?></p>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'Connect your Slack workspace to receive booking notifications.', 'bkx-slack' ); ?></p>
			<a href="<?php echo esc_url( $api->get_oauth_url() ); ?>" class="button button-primary bkx-slack-connect-btn">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
					<path d="M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zM6.313 15.165a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zM8.834 6.313a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zM18.956 8.834a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zM17.688 8.834a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zM15.165 18.956a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zM15.165 17.688a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z"/>
				</svg>
				<?php esc_html_e( 'Add to Slack', 'bkx-slack' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $workspaces ) ) : ?>
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Connected Workspaces', 'bkx-slack' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Workspace', 'bkx-slack' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-slack' ); ?></th>
						<th><?php esc_html_e( 'Default Channel', 'bkx-slack' ); ?></th>
						<th><?php esc_html_e( 'Connected', 'bkx-slack' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-slack' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $workspaces as $workspace ) : ?>
						<tr data-workspace-id="<?php echo esc_attr( $workspace->id ); ?>">
							<td>
								<strong><?php echo esc_html( $workspace->team_name ); ?></strong>
								<br>
								<small><?php echo esc_html( $workspace->team_id ); ?></small>
							</td>
							<td>
								<span class="bkx-status bkx-status-<?php echo esc_attr( $workspace->status ); ?>">
									<?php echo esc_html( ucfirst( $workspace->status ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $workspace->incoming_webhook_channel ) : ?>
									#<?php echo esc_html( $workspace->incoming_webhook_channel ); ?>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( human_time_diff( strtotime( $workspace->connected_at ), current_time( 'timestamp' ) ) . ' ago' ); ?>
							</td>
							<td>
								<?php if ( 'active' === $workspace->status ) : ?>
									<button type="button" class="button button-small bkx-test-notification"
											data-workspace-id="<?php echo esc_attr( $workspace->id ); ?>">
										<?php esc_html_e( 'Test', 'bkx-slack' ); ?>
									</button>
									<button type="button" class="button button-small bkx-manage-channels"
											data-workspace-id="<?php echo esc_attr( $workspace->id ); ?>">
										<?php esc_html_e( 'Channels', 'bkx-slack' ); ?>
									</button>
									<button type="button" class="button button-small button-link-delete bkx-disconnect-workspace"
											data-workspace-id="<?php echo esc_attr( $workspace->id ); ?>">
										<?php esc_html_e( 'Disconnect', 'bkx-slack' ); ?>
									</button>
								<?php else : ?>
									<a href="<?php echo esc_url( $api->get_oauth_url() ); ?>" class="button button-small">
										<?php esc_html_e( 'Reconnect', 'bkx-slack' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<!-- Channels Modal -->
<div id="bkx-channels-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<span class="bkx-modal-close">&times;</span>
		<h2><?php esc_html_e( 'Manage Notification Channels', 'bkx-slack' ); ?></h2>
		<div id="bkx-channels-list">
			<?php esc_html_e( 'Loading...', 'bkx-slack' ); ?>
		</div>
	</div>
</div>

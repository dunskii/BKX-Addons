<?php
/**
 * Settings tab template for BookingX settings page.
 *
 * @package BookingX\BkxIntegration
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_bkx_integration_settings', array() );

$defaults = array(
	'enabled'              => true,
	'sync_interval'        => 'hourly',
	'batch_size'           => 50,
	'retry_attempts'       => 3,
	'conflict_resolution'  => 'local',
	'sync_on_create'       => true,
	'sync_on_update'       => true,
	'sync_on_delete'       => true,
	'log_retention_days'   => 30,
	'webhook_enabled'      => true,
	'real_time_sync'       => false,
);

$settings = wp_parse_args( $settings, $defaults );
?>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'BKX to BKX Integration', 'bkx-bkx-integration' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure synchronization settings between BookingX sites.', 'bkx-bkx-integration' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="bkx_bkx_enabled"><?php esc_html_e( 'Enable Integration', 'bkx-bkx-integration' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="bkx_bkx_integration_settings[enabled]" id="bkx_bkx_enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
					<?php esc_html_e( 'Enable BKX to BKX synchronization', 'bkx-bkx-integration' ); ?>
				</label>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_bkx_sync_interval"><?php esc_html_e( 'Sync Interval', 'bkx-bkx-integration' ); ?></label>
			</th>
			<td>
				<select name="bkx_bkx_integration_settings[sync_interval]" id="bkx_bkx_sync_interval">
					<option value="every_5_minutes" <?php selected( $settings['sync_interval'], 'every_5_minutes' ); ?>>
						<?php esc_html_e( 'Every 5 Minutes', 'bkx-bkx-integration' ); ?>
					</option>
					<option value="every_15_minutes" <?php selected( $settings['sync_interval'], 'every_15_minutes' ); ?>>
						<?php esc_html_e( 'Every 15 Minutes', 'bkx-bkx-integration' ); ?>
					</option>
					<option value="every_30_minutes" <?php selected( $settings['sync_interval'], 'every_30_minutes' ); ?>>
						<?php esc_html_e( 'Every 30 Minutes', 'bkx-bkx-integration' ); ?>
					</option>
					<option value="hourly" <?php selected( $settings['sync_interval'], 'hourly' ); ?>>
						<?php esc_html_e( 'Hourly', 'bkx-bkx-integration' ); ?>
					</option>
					<option value="twicedaily" <?php selected( $settings['sync_interval'], 'twicedaily' ); ?>>
						<?php esc_html_e( 'Twice Daily', 'bkx-bkx-integration' ); ?>
					</option>
					<option value="daily" <?php selected( $settings['sync_interval'], 'daily' ); ?>>
						<?php esc_html_e( 'Daily', 'bkx-bkx-integration' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How often to process the sync queue.', 'bkx-bkx-integration' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_bkx_real_time"><?php esc_html_e( 'Real-Time Sync', 'bkx-bkx-integration' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="bkx_bkx_integration_settings[real_time_sync]" id="bkx_bkx_real_time" value="1" <?php checked( $settings['real_time_sync'] ); ?>>
					<?php esc_html_e( 'Enable real-time synchronization (webhooks)', 'bkx-bkx-integration' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Sync changes immediately when they occur. Requires webhook support on both sites.', 'bkx-bkx-integration' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_bkx_batch_size"><?php esc_html_e( 'Batch Size', 'bkx-bkx-integration' ); ?></label>
			</th>
			<td>
				<input type="number" name="bkx_bkx_integration_settings[batch_size]" id="bkx_bkx_batch_size"
					   value="<?php echo esc_attr( $settings['batch_size'] ); ?>" min="10" max="200" step="10" class="small-text">
				<p class="description">
					<?php esc_html_e( 'Number of items to process per batch (10-200).', 'bkx-bkx-integration' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_bkx_retry_attempts"><?php esc_html_e( 'Retry Attempts', 'bkx-bkx-integration' ); ?></label>
			</th>
			<td>
				<input type="number" name="bkx_bkx_integration_settings[retry_attempts]" id="bkx_bkx_retry_attempts"
					   value="<?php echo esc_attr( $settings['retry_attempts'] ); ?>" min="1" max="10" class="small-text">
				<p class="description">
					<?php esc_html_e( 'Number of retry attempts for failed sync operations (1-10).', 'bkx-bkx-integration' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_bkx_conflict_resolution"><?php esc_html_e( 'Default Conflict Resolution', 'bkx-bkx-integration' ); ?></label>
			</th>
			<td>
				<select name="bkx_bkx_integration_settings[conflict_resolution]" id="bkx_bkx_conflict_resolution">
					<option value="local" <?php selected( $settings['conflict_resolution'], 'local' ); ?>>
						<?php esc_html_e( 'Keep Local Data', 'bkx-bkx-integration' ); ?>
					</option>
					<option value="remote" <?php selected( $settings['conflict_resolution'], 'remote' ); ?>>
						<?php esc_html_e( 'Use Remote Data', 'bkx-bkx-integration' ); ?>
					</option>
					<option value="newest" <?php selected( $settings['conflict_resolution'], 'newest' ); ?>>
						<?php esc_html_e( 'Keep Newest', 'bkx-bkx-integration' ); ?>
					</option>
					<option value="manual" <?php selected( $settings['conflict_resolution'], 'manual' ); ?>>
						<?php esc_html_e( 'Manual Review', 'bkx-bkx-integration' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How to handle conflicts when the same data is modified on both sites.', 'bkx-bkx-integration' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Sync Triggers', 'bkx-bkx-integration' ); ?></th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="bkx_bkx_integration_settings[sync_on_create]" value="1" <?php checked( $settings['sync_on_create'] ); ?>>
						<?php esc_html_e( 'Sync when bookings are created', 'bkx-bkx-integration' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="bkx_bkx_integration_settings[sync_on_update]" value="1" <?php checked( $settings['sync_on_update'] ); ?>>
						<?php esc_html_e( 'Sync when bookings are updated', 'bkx-bkx-integration' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="bkx_bkx_integration_settings[sync_on_delete]" value="1" <?php checked( $settings['sync_on_delete'] ); ?>>
						<?php esc_html_e( 'Sync when bookings are deleted', 'bkx-bkx-integration' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_bkx_log_retention"><?php esc_html_e( 'Log Retention', 'bkx-bkx-integration' ); ?></label>
			</th>
			<td>
				<input type="number" name="bkx_bkx_integration_settings[log_retention_days]" id="bkx_bkx_log_retention"
					   value="<?php echo esc_attr( $settings['log_retention_days'] ); ?>" min="7" max="365" class="small-text">
				<?php esc_html_e( 'days', 'bkx-bkx-integration' ); ?>
				<p class="description">
					<?php esc_html_e( 'Number of days to keep sync logs (7-365).', 'bkx-bkx-integration' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_bkx_webhook_enabled"><?php esc_html_e( 'Incoming Webhooks', 'bkx-bkx-integration' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="bkx_bkx_integration_settings[webhook_enabled]" id="bkx_bkx_webhook_enabled" value="1" <?php checked( $settings['webhook_enabled'] ); ?>>
					<?php esc_html_e( 'Accept incoming sync requests from remote sites', 'bkx-bkx-integration' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Disable to prevent this site from receiving sync data.', 'bkx-bkx-integration' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Quick Links', 'bkx-bkx-integration' ); ?></h3>
	<p>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-integration' ) ); ?>" class="button">
			<?php esc_html_e( 'Manage Remote Sites', 'bkx-bkx-integration' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-integration&tab=api' ) ); ?>" class="button">
			<?php esc_html_e( 'View API Credentials', 'bkx-bkx-integration' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-integration&tab=conflicts' ) ); ?>" class="button">
			<?php esc_html_e( 'Review Conflicts', 'bkx-bkx-integration' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-integration&tab=logs' ) ); ?>" class="button">
			<?php esc_html_e( 'View Sync Logs', 'bkx-bkx-integration' ); ?>
		</a>
	</p>
</div>

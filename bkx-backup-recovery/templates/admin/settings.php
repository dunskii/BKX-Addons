<?php
/**
 * Settings template.
 *
 * @package BookingX\BackupRecovery
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_backup_recovery_settings', array() );

// Defaults.
$defaults = array(
	'backup_frequency'     => 'daily',
	'backup_time'          => '03:00',
	'backup_retention'     => 30,
	'include_bookings'     => true,
	'include_customers'    => true,
	'include_settings'     => true,
	'include_services'     => true,
	'include_staff'        => true,
	'compression'          => 'zip',
	'email_notification'   => true,
	'notify_email'         => get_option( 'admin_email' ),
	'remote_storage'       => 'none',
	'max_backup_size'      => 100,
);

$settings = wp_parse_args( $settings, $defaults );
?>
<div class="bkx-settings-section">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'Backup Settings', 'bkx-backup-recovery' ); ?></h2>
	</div>

	<form id="bkx-settings-form" method="post" action="">
		<h3><?php esc_html_e( 'Scheduled Backups', 'bkx-backup-recovery' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="backup_frequency"><?php esc_html_e( 'Backup Frequency', 'bkx-backup-recovery' ); ?></label>
				</th>
				<td>
					<select name="backup_frequency" id="backup_frequency">
						<option value="hourly" <?php selected( $settings['backup_frequency'], 'hourly' ); ?>>
							<?php esc_html_e( 'Hourly', 'bkx-backup-recovery' ); ?>
						</option>
						<option value="daily" <?php selected( $settings['backup_frequency'], 'daily' ); ?>>
							<?php esc_html_e( 'Daily', 'bkx-backup-recovery' ); ?>
						</option>
						<option value="weekly" <?php selected( $settings['backup_frequency'], 'weekly' ); ?>>
							<?php esc_html_e( 'Weekly', 'bkx-backup-recovery' ); ?>
						</option>
						<option value="monthly" <?php selected( $settings['backup_frequency'], 'monthly' ); ?>>
							<?php esc_html_e( 'Monthly', 'bkx-backup-recovery' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="backup_time"><?php esc_html_e( 'Backup Time', 'bkx-backup-recovery' ); ?></label>
				</th>
				<td>
					<input type="time" name="backup_time" id="backup_time"
						   value="<?php echo esc_attr( $settings['backup_time'] ); ?>">
					<p class="description">
						<?php esc_html_e( 'Time of day to run scheduled backups (server timezone).', 'bkx-backup-recovery' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="backup_retention"><?php esc_html_e( 'Retention Period', 'bkx-backup-recovery' ); ?></label>
				</th>
				<td>
					<input type="number" name="backup_retention" id="backup_retention"
						   value="<?php echo esc_attr( $settings['backup_retention'] ); ?>"
						   min="1" max="365" class="small-text">
					<?php esc_html_e( 'days', 'bkx-backup-recovery' ); ?>
					<p class="description">
						<?php esc_html_e( 'Backups older than this will be automatically deleted.', 'bkx-backup-recovery' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Backup Contents', 'bkx-backup-recovery' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Include in Backups', 'bkx-backup-recovery' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="include_bookings" value="1"
								<?php checked( $settings['include_bookings'] ); ?>>
							<?php esc_html_e( 'Bookings', 'bkx-backup-recovery' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="include_services" value="1"
								<?php checked( $settings['include_services'] ); ?>>
							<?php esc_html_e( 'Services', 'bkx-backup-recovery' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="include_staff" value="1"
								<?php checked( $settings['include_staff'] ); ?>>
							<?php esc_html_e( 'Staff', 'bkx-backup-recovery' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="include_customers" value="1"
								<?php checked( $settings['include_customers'] ); ?>>
							<?php esc_html_e( 'Customer Data', 'bkx-backup-recovery' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="include_settings" value="1"
								<?php checked( $settings['include_settings'] ); ?>>
							<?php esc_html_e( 'Plugin Settings', 'bkx-backup-recovery' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="compression"><?php esc_html_e( 'Compression', 'bkx-backup-recovery' ); ?></label>
				</th>
				<td>
					<select name="compression" id="compression">
						<option value="none" <?php selected( $settings['compression'], 'none' ); ?>>
							<?php esc_html_e( 'None', 'bkx-backup-recovery' ); ?>
						</option>
						<option value="zip" <?php selected( $settings['compression'], 'zip' ); ?>>
							<?php esc_html_e( 'ZIP', 'bkx-backup-recovery' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Compress backup files to save storage space.', 'bkx-backup-recovery' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="max_backup_size"><?php esc_html_e( 'Max Backup Size', 'bkx-backup-recovery' ); ?></label>
				</th>
				<td>
					<input type="number" name="max_backup_size" id="max_backup_size"
						   value="<?php echo esc_attr( $settings['max_backup_size'] ); ?>"
						   min="10" max="1000" class="small-text">
					<?php esc_html_e( 'MB', 'bkx-backup-recovery' ); ?>
					<p class="description">
						<?php esc_html_e( 'Maximum size for a single backup file.', 'bkx-backup-recovery' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Notifications', 'bkx-backup-recovery' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Email Notifications', 'bkx-backup-recovery' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="email_notification" value="1"
							<?php checked( $settings['email_notification'] ); ?>>
						<?php esc_html_e( 'Send email notification after scheduled backups', 'bkx-backup-recovery' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="notify_email"><?php esc_html_e( 'Notification Email', 'bkx-backup-recovery' ); ?></label>
				</th>
				<td>
					<input type="email" name="notify_email" id="notify_email"
						   value="<?php echo esc_attr( $settings['notify_email'] ); ?>"
						   class="regular-text">
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Storage', 'bkx-backup-recovery' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="remote_storage"><?php esc_html_e( 'Remote Storage', 'bkx-backup-recovery' ); ?></label>
				</th>
				<td>
					<select name="remote_storage" id="remote_storage">
						<option value="none" <?php selected( $settings['remote_storage'], 'none' ); ?>>
							<?php esc_html_e( 'Local Only', 'bkx-backup-recovery' ); ?>
						</option>
						<option value="dropbox" <?php selected( $settings['remote_storage'], 'dropbox' ); ?>>
							<?php esc_html_e( 'Dropbox', 'bkx-backup-recovery' ); ?>
						</option>
						<option value="google_drive" <?php selected( $settings['remote_storage'], 'google_drive' ); ?>>
							<?php esc_html_e( 'Google Drive', 'bkx-backup-recovery' ); ?>
						</option>
						<option value="s3" <?php selected( $settings['remote_storage'], 's3' ); ?>>
							<?php esc_html_e( 'Amazon S3', 'bkx-backup-recovery' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Optionally store backups in cloud storage for added security.', 'bkx-backup-recovery' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<div id="bkx-remote-storage-settings" style="display: none;">
			<div id="bkx-dropbox-settings" class="bkx-storage-settings" style="display: none;">
				<h4><?php esc_html_e( 'Dropbox Settings', 'bkx-backup-recovery' ); ?></h4>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="dropbox_token"><?php esc_html_e( 'Access Token', 'bkx-backup-recovery' ); ?></label>
						</th>
						<td>
							<input type="password" name="dropbox_token" id="dropbox_token"
								   value="<?php echo esc_attr( $settings['dropbox_token'] ?? '' ); ?>"
								   class="regular-text">
						</td>
					</tr>
				</table>
			</div>

			<div id="bkx-s3-settings" class="bkx-storage-settings" style="display: none;">
				<h4><?php esc_html_e( 'Amazon S3 Settings', 'bkx-backup-recovery' ); ?></h4>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="s3_key"><?php esc_html_e( 'Access Key', 'bkx-backup-recovery' ); ?></label>
						</th>
						<td>
							<input type="text" name="s3_key" id="s3_key"
								   value="<?php echo esc_attr( $settings['s3_key'] ?? '' ); ?>"
								   class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="s3_secret"><?php esc_html_e( 'Secret Key', 'bkx-backup-recovery' ); ?></label>
						</th>
						<td>
							<input type="password" name="s3_secret" id="s3_secret"
								   value="<?php echo esc_attr( $settings['s3_secret'] ?? '' ); ?>"
								   class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="s3_bucket"><?php esc_html_e( 'Bucket Name', 'bkx-backup-recovery' ); ?></label>
						</th>
						<td>
							<input type="text" name="s3_bucket" id="s3_bucket"
								   value="<?php echo esc_attr( $settings['s3_bucket'] ?? '' ); ?>"
								   class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="s3_region"><?php esc_html_e( 'Region', 'bkx-backup-recovery' ); ?></label>
						</th>
						<td>
							<input type="text" name="s3_region" id="s3_region"
								   value="<?php echo esc_attr( $settings['s3_region'] ?? 'us-east-1' ); ?>"
								   class="regular-text" placeholder="us-east-1">
						</td>
					</tr>
				</table>
			</div>
		</div>

		<?php wp_nonce_field( 'bkx_backup_settings', 'bkx_settings_nonce' ); ?>

		<p class="submit">
			<button type="submit" class="button button-primary" id="bkx-save-settings">
				<?php esc_html_e( 'Save Settings', 'bkx-backup-recovery' ); ?>
			</button>
		</p>
	</form>

	<hr>

	<h3><?php esc_html_e( 'Storage Information', 'bkx-backup-recovery' ); ?></h3>
	<?php
	$upload_dir = wp_upload_dir();
	$backup_dir = $upload_dir['basedir'] . '/bkx-backups/';
	$total_size = 0;
	$file_count = 0;

	if ( is_dir( $backup_dir ) ) {
		$files = glob( $backup_dir . '*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) && ! in_array( basename( $file ), array( '.htaccess', 'index.php' ), true ) ) {
				$total_size += filesize( $file );
				$file_count++;
			}
		}
	}
	?>
	<table class="widefat fixed">
		<tr>
			<td><strong><?php esc_html_e( 'Backup Location:', 'bkx-backup-recovery' ); ?></strong></td>
			<td><code><?php echo esc_html( $backup_dir ); ?></code></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Total Backups:', 'bkx-backup-recovery' ); ?></strong></td>
			<td><?php echo esc_html( number_format_i18n( $file_count ) ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Total Size:', 'bkx-backup-recovery' ); ?></strong></td>
			<td><?php echo esc_html( size_format( $total_size ) ); ?></td>
		</tr>
	</table>
</div>

<script>
jQuery(document).ready(function($) {
	// Show/hide remote storage settings.
	$('#remote_storage').on('change', function() {
		var storage = $(this).val();
		$('.bkx-storage-settings').hide();

		if (storage !== 'none') {
			$('#bkx-remote-storage-settings').show();
			$('#bkx-' + storage.replace('_', '-') + '-settings').show();
		} else {
			$('#bkx-remote-storage-settings').hide();
		}
	}).trigger('change');
});
</script>

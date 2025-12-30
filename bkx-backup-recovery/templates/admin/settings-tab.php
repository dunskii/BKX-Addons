<?php
/**
 * BookingX settings tab integration template.
 *
 * @package BookingX\BackupRecovery
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_backup_recovery_settings', array() );

// Defaults.
$defaults = array(
	'backup_frequency'   => 'daily',
	'backup_time'        => '03:00',
	'backup_retention'   => 30,
	'email_notification' => true,
	'notify_email'       => get_option( 'admin_email' ),
);

$settings = wp_parse_args( $settings, $defaults );

// Get next scheduled backup.
$next_backup = wp_next_scheduled( 'bkx_backup_scheduled_backup' );
?>
<div class="bkx-settings-backup-tab">
	<h2><?php esc_html_e( 'Backup & Recovery Settings', 'bkx-backup-recovery' ); ?></h2>

	<?php if ( $next_backup ) : ?>
		<div class="notice notice-info inline">
			<p>
				<span class="dashicons dashicons-calendar-alt"></span>
				<?php
				printf(
					/* translators: %s: Next backup date/time */
					esc_html__( 'Next scheduled backup: %s', 'bkx-backup-recovery' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_backup ) )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="bkx_backup_frequency"><?php esc_html_e( 'Backup Frequency', 'bkx-backup-recovery' ); ?></label>
			</th>
			<td>
				<select name="bkx_backup_recovery_settings[backup_frequency]" id="bkx_backup_frequency">
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
				<label for="bkx_backup_time"><?php esc_html_e( 'Backup Time', 'bkx-backup-recovery' ); ?></label>
			</th>
			<td>
				<input type="time" name="bkx_backup_recovery_settings[backup_time]" id="bkx_backup_time"
					   value="<?php echo esc_attr( $settings['backup_time'] ); ?>">
				<p class="description">
					<?php esc_html_e( 'Time to run scheduled backups (server timezone).', 'bkx-backup-recovery' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="bkx_backup_retention"><?php esc_html_e( 'Retention Period', 'bkx-backup-recovery' ); ?></label>
			</th>
			<td>
				<input type="number" name="bkx_backup_recovery_settings[backup_retention]" id="bkx_backup_retention"
					   value="<?php echo esc_attr( $settings['backup_retention'] ); ?>"
					   min="1" max="365" class="small-text">
				<?php esc_html_e( 'days', 'bkx-backup-recovery' ); ?>
				<p class="description">
					<?php esc_html_e( 'Backups older than this will be automatically deleted.', 'bkx-backup-recovery' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Email Notifications', 'bkx-backup-recovery' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="bkx_backup_recovery_settings[email_notification]" value="1"
						<?php checked( $settings['email_notification'] ); ?>>
					<?php esc_html_e( 'Send email after scheduled backups', 'bkx-backup-recovery' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="bkx_notify_email"><?php esc_html_e( 'Notification Email', 'bkx-backup-recovery' ); ?></label>
			</th>
			<td>
				<input type="email" name="bkx_backup_recovery_settings[notify_email]" id="bkx_notify_email"
					   value="<?php echo esc_attr( $settings['notify_email'] ); ?>"
					   class="regular-text">
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Quick Actions', 'bkx-backup-recovery' ); ?></h3>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-backup-recovery' ) ); ?>" class="button">
			<span class="dashicons dashicons-backup" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Manage Backups', 'bkx-backup-recovery' ); ?>
		</a>
		<button type="button" class="button" id="bkx-quick-backup">
			<span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Create Backup Now', 'bkx-backup-recovery' ); ?>
		</button>
	</p>

	<?php
	// Show recent backups.
	$backup_manager = new \BookingX\BackupRecovery\Services\BackupManager( array() );
	$recent         = $backup_manager->get_backups( array( 'per_page' => 5 ) );
	?>

	<?php if ( ! empty( $recent['backups'] ) ) : ?>
		<h3><?php esc_html_e( 'Recent Backups', 'bkx-backup-recovery' ); ?></h3>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'File', 'bkx-backup-recovery' ); ?></th>
					<th><?php esc_html_e( 'Size', 'bkx-backup-recovery' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bkx-backup-recovery' ); ?></th>
					<th><?php esc_html_e( 'Date', 'bkx-backup-recovery' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent['backups'] as $backup ) : ?>
					<tr>
						<td><code><?php echo esc_html( $backup['file_name'] ); ?></code></td>
						<td><?php echo esc_html( size_format( $backup['file_size'] ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $backup['status'] ) ); ?></td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $backup['created_at'] ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
	$('#bkx-quick-backup').on('click', function() {
		var $btn = $(this);
		$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Creating...', 'bkx-backup-recovery' ) ); ?>');

		$.post(ajaxurl, {
			action: 'bkx_backup_create',
			backup_type: 'manual',
			nonce: '<?php echo esc_js( wp_create_nonce( 'bkx_backup_action' ) ); ?>'
		}, function(response) {
			if (response.success) {
				alert('<?php echo esc_js( __( 'Backup created successfully!', 'bkx-backup-recovery' ) ); ?>');
				location.reload();
			} else {
				alert(response.data.message || '<?php echo esc_js( __( 'Backup failed.', 'bkx-backup-recovery' ) ); ?>');
			}
			$btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span> <?php echo esc_js( __( 'Create Backup Now', 'bkx-backup-recovery' ) ); ?>');
		});
	});
});
</script>

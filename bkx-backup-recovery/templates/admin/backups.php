<?php
/**
 * Backups list template.
 *
 * @package BookingX\BackupRecovery
 */

defined( 'ABSPATH' ) || exit;

$backup_manager = new \BookingX\BackupRecovery\Services\BackupManager( array() );
$page           = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$result         = $backup_manager->get_backups( array( 'page' => $page ) );
$backups        = $result['backups'];
$total_pages    = $result['pages'];

// Get next scheduled backup.
$next_backup = wp_next_scheduled( 'bkx_backup_scheduled_backup' );
?>
<div class="bkx-backups-section">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'Backup History', 'bkx-backup-recovery' ); ?></h2>
		<div class="bkx-header-actions">
			<button type="button" class="button button-primary" id="bkx-create-backup">
				<span class="dashicons dashicons-backup"></span>
				<?php esc_html_e( 'Create Backup Now', 'bkx-backup-recovery' ); ?>
			</button>
		</div>
	</div>

	<?php if ( $next_backup ) : ?>
		<div class="bkx-info-box">
			<span class="dashicons dashicons-calendar-alt"></span>
			<?php
			printf(
				/* translators: %s: Next backup date/time */
				esc_html__( 'Next scheduled backup: %s', 'bkx-backup-recovery' ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_backup ) )
			);
			?>
		</div>
	<?php endif; ?>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th class="column-type"><?php esc_html_e( 'Type', 'bkx-backup-recovery' ); ?></th>
				<th class="column-filename"><?php esc_html_e( 'File', 'bkx-backup-recovery' ); ?></th>
				<th class="column-size"><?php esc_html_e( 'Size', 'bkx-backup-recovery' ); ?></th>
				<th class="column-items"><?php esc_html_e( 'Items', 'bkx-backup-recovery' ); ?></th>
				<th class="column-status"><?php esc_html_e( 'Status', 'bkx-backup-recovery' ); ?></th>
				<th class="column-date"><?php esc_html_e( 'Date', 'bkx-backup-recovery' ); ?></th>
				<th class="column-actions"><?php esc_html_e( 'Actions', 'bkx-backup-recovery' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $backups ) ) : ?>
				<tr>
					<td colspan="7" class="no-items">
						<?php esc_html_e( 'No backups found. Create your first backup now.', 'bkx-backup-recovery' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $backups as $backup ) : ?>
					<tr data-backup-id="<?php echo esc_attr( $backup['id'] ); ?>">
						<td class="column-type">
							<?php
							$type_labels = array(
								'full'        => __( 'Full', 'bkx-backup-recovery' ),
								'incremental' => __( 'Incremental', 'bkx-backup-recovery' ),
								'manual'      => __( 'Manual', 'bkx-backup-recovery' ),
							);
							$type_label  = $type_labels[ $backup['backup_type'] ] ?? $backup['backup_type'];
							?>
							<span class="bkx-type-badge bkx-type-<?php echo esc_attr( $backup['backup_type'] ); ?>">
								<?php echo esc_html( $type_label ); ?>
							</span>
						</td>
						<td class="column-filename">
							<code><?php echo esc_html( $backup['file_name'] ); ?></code>
						</td>
						<td class="column-size">
							<?php echo esc_html( size_format( $backup['file_size'] ) ); ?>
						</td>
						<td class="column-items">
							<?php echo esc_html( number_format_i18n( $backup['items_count'] ) ); ?>
						</td>
						<td class="column-status">
							<?php
							$status_classes = array(
								'completed' => 'bkx-status-success',
								'running'   => 'bkx-status-warning',
								'failed'    => 'bkx-status-error',
								'pending'   => 'bkx-status-pending',
							);
							$status_class   = $status_classes[ $backup['status'] ] ?? '';
							?>
							<span class="bkx-status-badge <?php echo esc_attr( $status_class ); ?>">
								<?php echo esc_html( ucfirst( $backup['status'] ) ); ?>
							</span>
							<?php if ( ! empty( $backup['error_message'] ) ) : ?>
								<span class="dashicons dashicons-warning" title="<?php echo esc_attr( $backup['error_message'] ); ?>"></span>
							<?php endif; ?>
						</td>
						<td class="column-date">
							<?php
							echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $backup['created_at'] ) ) );
							?>
						</td>
						<td class="column-actions">
							<?php if ( 'completed' === $backup['status'] ) : ?>
								<button type="button" class="button button-small bkx-download-backup"
										data-id="<?php echo esc_attr( $backup['id'] ); ?>"
										title="<?php esc_attr_e( 'Download', 'bkx-backup-recovery' ); ?>">
									<span class="dashicons dashicons-download"></span>
								</button>
								<button type="button" class="button button-small bkx-restore-backup"
										data-id="<?php echo esc_attr( $backup['id'] ); ?>"
										title="<?php esc_attr_e( 'Restore', 'bkx-backup-recovery' ); ?>">
									<span class="dashicons dashicons-backup"></span>
								</button>
							<?php endif; ?>
							<button type="button" class="button button-small bkx-delete-backup"
									data-id="<?php echo esc_attr( $backup['id'] ); ?>"
									title="<?php esc_attr_e( 'Delete', 'bkx-backup-recovery' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo paginate_links( array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'total'     => $total_pages,
					'current'   => $page,
				) );
				?>
			</div>
		</div>
	<?php endif; ?>
</div>

<!-- Create Backup Modal -->
<div id="bkx-backup-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Create New Backup', 'bkx-backup-recovery' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<form id="bkx-backup-form">
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Backup Type', 'bkx-backup-recovery' ); ?></th>
						<td>
							<select name="backup_type" id="backup_type">
								<option value="full"><?php esc_html_e( 'Full Backup', 'bkx-backup-recovery' ); ?></option>
								<option value="incremental"><?php esc_html_e( 'Incremental Backup', 'bkx-backup-recovery' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Full backup includes all data. Incremental only includes changes since last backup.', 'bkx-backup-recovery' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Include Data', 'bkx-backup-recovery' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="include[]" value="bookings" checked>
									<?php esc_html_e( 'Bookings', 'bkx-backup-recovery' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="include[]" value="services" checked>
									<?php esc_html_e( 'Services', 'bkx-backup-recovery' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="include[]" value="staff" checked>
									<?php esc_html_e( 'Staff', 'bkx-backup-recovery' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="include[]" value="customers" checked>
									<?php esc_html_e( 'Customers', 'bkx-backup-recovery' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="include[]" value="settings" checked>
									<?php esc_html_e( 'Settings', 'bkx-backup-recovery' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
				</table>
				<?php wp_nonce_field( 'bkx_backup_action', 'bkx_backup_nonce' ); ?>
			</form>
		</div>
		<div class="bkx-modal-footer">
			<button type="button" class="button" id="bkx-cancel-backup"><?php esc_html_e( 'Cancel', 'bkx-backup-recovery' ); ?></button>
			<button type="button" class="button button-primary" id="bkx-start-backup">
				<span class="spinner"></span>
				<?php esc_html_e( 'Start Backup', 'bkx-backup-recovery' ); ?>
			</button>
		</div>
	</div>
</div>

<!-- Restore Confirmation Modal -->
<div id="bkx-restore-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Restore Backup', 'bkx-backup-recovery' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<div class="bkx-warning-box">
				<span class="dashicons dashicons-warning"></span>
				<p>
					<strong><?php esc_html_e( 'Warning:', 'bkx-backup-recovery' ); ?></strong>
					<?php esc_html_e( 'Restoring a backup will overwrite existing data. This action cannot be undone. We recommend creating a backup of your current data before proceeding.', 'bkx-backup-recovery' ); ?>
				</p>
			</div>
			<p>
				<label>
					<input type="checkbox" id="bkx-restore-confirm">
					<?php esc_html_e( 'I understand and want to proceed with the restore.', 'bkx-backup-recovery' ); ?>
				</label>
			</p>
			<input type="hidden" id="bkx-restore-backup-id" value="">
		</div>
		<div class="bkx-modal-footer">
			<button type="button" class="button" id="bkx-cancel-restore"><?php esc_html_e( 'Cancel', 'bkx-backup-recovery' ); ?></button>
			<button type="button" class="button button-primary" id="bkx-confirm-restore" disabled>
				<span class="spinner"></span>
				<?php esc_html_e( 'Restore Backup', 'bkx-backup-recovery' ); ?>
			</button>
		</div>
	</div>
</div>

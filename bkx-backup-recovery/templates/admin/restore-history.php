<?php
/**
 * Restore history template.
 *
 * @package BookingX\BackupRecovery
 */

defined( 'ABSPATH' ) || exit;

$restore_manager = new \BookingX\BackupRecovery\Services\RestoreManager();
$page            = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$result          = $restore_manager->get_restore_history( array( 'page' => $page ) );
$history         = $result['history'];
$total_pages     = $result['pages'];
?>
<div class="bkx-restore-history-section">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'Restore History', 'bkx-backup-recovery' ); ?></h2>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th class="column-backup"><?php esc_html_e( 'Backup', 'bkx-backup-recovery' ); ?></th>
				<th class="column-type"><?php esc_html_e( 'Type', 'bkx-backup-recovery' ); ?></th>
				<th class="column-items"><?php esc_html_e( 'Items Restored', 'bkx-backup-recovery' ); ?></th>
				<th class="column-status"><?php esc_html_e( 'Status', 'bkx-backup-recovery' ); ?></th>
				<th class="column-started"><?php esc_html_e( 'Started', 'bkx-backup-recovery' ); ?></th>
				<th class="column-completed"><?php esc_html_e( 'Completed', 'bkx-backup-recovery' ); ?></th>
				<th class="column-user"><?php esc_html_e( 'Restored By', 'bkx-backup-recovery' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $history ) ) : ?>
				<tr>
					<td colspan="7" class="no-items">
						<?php esc_html_e( 'No restore history found.', 'bkx-backup-recovery' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $history as $restore ) : ?>
					<tr>
						<td class="column-backup">
							<?php if ( ! empty( $restore['file_name'] ) ) : ?>
								<code><?php echo esc_html( $restore['file_name'] ); ?></code>
							<?php else : ?>
								<em><?php esc_html_e( 'Deleted', 'bkx-backup-recovery' ); ?></em>
							<?php endif; ?>
						</td>
						<td class="column-type">
							<?php
							$type_labels = array(
								'full'        => __( 'Full', 'bkx-backup-recovery' ),
								'incremental' => __( 'Incremental', 'bkx-backup-recovery' ),
								'manual'      => __( 'Manual', 'bkx-backup-recovery' ),
							);
							$type_label  = $type_labels[ $restore['backup_type'] ?? '' ] ?? '-';
							echo esc_html( $type_label );
							?>
						</td>
						<td class="column-items">
							<?php echo esc_html( number_format_i18n( $restore['items_restored'] ) ); ?>
						</td>
						<td class="column-status">
							<?php
							$status_classes = array(
								'completed' => 'bkx-status-success',
								'running'   => 'bkx-status-warning',
								'failed'    => 'bkx-status-error',
								'pending'   => 'bkx-status-pending',
							);
							$status_class   = $status_classes[ $restore['status'] ] ?? '';
							?>
							<span class="bkx-status-badge <?php echo esc_attr( $status_class ); ?>">
								<?php echo esc_html( ucfirst( $restore['status'] ) ); ?>
							</span>
							<?php if ( ! empty( $restore['error_message'] ) ) : ?>
								<span class="bkx-error-message"><?php echo esc_html( $restore['error_message'] ); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-started">
							<?php
							if ( ! empty( $restore['started_at'] ) ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $restore['started_at'] ) ) );
							} else {
								echo '-';
							}
							?>
						</td>
						<td class="column-completed">
							<?php
							if ( ! empty( $restore['completed_at'] ) ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $restore['completed_at'] ) ) );
							} else {
								echo '-';
							}
							?>
						</td>
						<td class="column-user">
							<?php
							if ( ! empty( $restore['restored_by'] ) ) {
								$user = get_user_by( 'id', $restore['restored_by'] );
								echo esc_html( $user ? $user->display_name : '#' . $restore['restored_by'] );
							} else {
								echo '-';
							}
							?>
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

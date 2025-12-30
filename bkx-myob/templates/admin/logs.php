<?php
/**
 * MYOB Logs Tab.
 *
 * @package BookingX\MYOB
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$table_name = $wpdb->prefix . 'bkx_myob_sync_log';
$page       = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page   = 20;
$offset     = ( $page - 1 ) * $per_page;

// Get logs.
$logs = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d",
		$table_name,
		$per_page,
		$offset
	)
);

$total_logs = $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM %i", $table_name )
);

$total_pages = ceil( $total_logs / $per_page );
?>

<div class="bkx-myob-logs">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'Sync Logs', 'bkx-myob' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'View history of sync operations between BookingX and MYOB.', 'bkx-myob' ); ?>
		</p>
	</div>

	<?php if ( empty( $logs ) ) : ?>
		<div class="bkx-no-logs">
			<span class="dashicons dashicons-info-outline"></span>
			<p><?php esc_html_e( 'No sync logs recorded yet.', 'bkx-myob' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped bkx-logs-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'bkx-myob' ); ?></th>
					<th><?php esc_html_e( 'Booking', 'bkx-myob' ); ?></th>
					<th><?php esc_html_e( 'Type', 'bkx-myob' ); ?></th>
					<th><?php esc_html_e( 'MYOB ID', 'bkx-myob' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bkx-myob' ); ?></th>
					<th><?php esc_html_e( 'Details', 'bkx-myob' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td>
							<span title="<?php echo esc_attr( $log->created_at ); ?>">
								<?php echo esc_html( human_time_diff( strtotime( $log->created_at ), current_time( 'timestamp' ) ) ); ?>
								<?php esc_html_e( 'ago', 'bkx-myob' ); ?>
							</span>
						</td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $log->booking_id . '&action=edit' ) ); ?>">
								#<?php echo esc_html( $log->booking_id ); ?>
							</a>
						</td>
						<td>
							<span class="bkx-type-badge bkx-type-<?php echo esc_attr( $log->myob_type ); ?>">
								<?php echo esc_html( ucfirst( $log->myob_type ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $log->myob_id ) : ?>
								<code><?php echo esc_html( $log->myob_number ?: substr( $log->myob_id, 0, 8 ) . '...' ); ?></code>
							<?php else : ?>
								-
							<?php endif; ?>
						</td>
						<td>
							<?php if ( 'success' === $log->sync_status ) : ?>
								<span class="bkx-status-success">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Success', 'bkx-myob' ); ?>
								</span>
							<?php else : ?>
								<span class="bkx-status-error">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'Failed', 'bkx-myob' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $log->error_message ) : ?>
								<span class="bkx-error-text" title="<?php echo esc_attr( $log->error_message ); ?>">
									<?php echo esc_html( wp_trim_words( $log->error_message, 10 ) ); ?>
								</span>
							<?php elseif ( $log->synced_at ) : ?>
								<?php echo esc_html( $log->synced_at ); ?>
							<?php else : ?>
								-
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $total_pages,
							'current'   => $page,
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>

		<p class="submit">
			<button type="button" class="button" id="bkx-clear-logs">
				<?php esc_html_e( 'Clear All Logs', 'bkx-myob' ); ?>
			</button>
		</p>
	<?php endif; ?>
</div>

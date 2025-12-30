<?php
/**
 * FreshBooks Logs Tab.
 *
 * @package BookingX\FreshBooks
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$table_name = $wpdb->prefix . 'bkx_freshbooks_sync_log';
$page       = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page   = 20;
$offset     = ( $page - 1 ) * $per_page;

$logs = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d",
		$table_name,
		$per_page,
		$offset
	)
);

$total_logs  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table_name ) );
$total_pages = ceil( $total_logs / $per_page );
?>

<div class="bkx-freshbooks-logs">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'Sync Logs', 'bkx-freshbooks' ); ?></h2>
	</div>

	<?php if ( empty( $logs ) ) : ?>
		<div class="bkx-no-logs">
			<span class="dashicons dashicons-info-outline"></span>
			<p><?php esc_html_e( 'No sync logs recorded yet.', 'bkx-freshbooks' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'bkx-freshbooks' ); ?></th>
					<th><?php esc_html_e( 'Booking', 'bkx-freshbooks' ); ?></th>
					<th><?php esc_html_e( 'Type', 'bkx-freshbooks' ); ?></th>
					<th><?php esc_html_e( 'FreshBooks ID', 'bkx-freshbooks' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bkx-freshbooks' ); ?></th>
					<th><?php esc_html_e( 'Details', 'bkx-freshbooks' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td>
							<span title="<?php echo esc_attr( $log->created_at ); ?>">
								<?php echo esc_html( human_time_diff( strtotime( $log->created_at ), current_time( 'timestamp' ) ) ); ?>
								<?php esc_html_e( 'ago', 'bkx-freshbooks' ); ?>
							</span>
						</td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $log->booking_id . '&action=edit' ) ); ?>">
								#<?php echo esc_html( $log->booking_id ); ?>
							</a>
						</td>
						<td>
							<span class="bkx-type-badge bkx-type-<?php echo esc_attr( $log->fb_type ); ?>">
								<?php echo esc_html( ucfirst( $log->fb_type ) ); ?>
							</span>
						</td>
						<td>
							<?php echo $log->fb_id ? '<code>' . esc_html( $log->fb_number ?: $log->fb_id ) . '</code>' : '-'; ?>
						</td>
						<td>
							<?php if ( 'success' === $log->sync_status ) : ?>
								<span class="bkx-status-success">
									<span class="dashicons dashicons-yes-alt"></span>
								</span>
							<?php else : ?>
								<span class="bkx-status-error">
									<span class="dashicons dashicons-warning"></span>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo $log->error_message ? '<span class="bkx-error-text">' . esc_html( wp_trim_words( $log->error_message, 10 ) ) . '</span>' : '-'; ?>
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
	<?php endif; ?>
</div>

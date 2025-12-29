<?php
/**
 * Logs tab template.
 *
 * @package BookingX\Discord
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$page_num = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page = 50;
$offset = ( $page_num - 1 ) * $per_page;

$logs_table = $wpdb->prefix . 'bkx_discord_logs';
$webhooks_table = $wpdb->prefix . 'bkx_discord_webhooks';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table}" );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$logs = $wpdb->get_results( $wpdb->prepare(
	"SELECT l.*, w.name as webhook_name
	FROM {$logs_table} l
	LEFT JOIN {$webhooks_table} w ON l.webhook_id = w.id
	ORDER BY l.created_at DESC
	LIMIT %d OFFSET %d",
	$per_page,
	$offset
) );
?>

<div class="bkx-discord-logs">
	<div class="bkx-card">
		<div class="bkx-card-header">
			<h2><?php esc_html_e( 'Notification Logs', 'bkx-discord' ); ?></h2>
			<button type="button" class="button" id="bkx-clear-logs">
				<?php esc_html_e( 'Clear Old Logs', 'bkx-discord' ); ?>
			</button>
		</div>

		<?php if ( empty( $logs ) ) : ?>
			<p><?php esc_html_e( 'No notifications have been sent yet.', 'bkx-discord' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 150px;"><?php esc_html_e( 'Date/Time', 'bkx-discord' ); ?></th>
						<th style="width: 120px;"><?php esc_html_e( 'Event', 'bkx-discord' ); ?></th>
						<th style="width: 150px;"><?php esc_html_e( 'Webhook', 'bkx-discord' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Booking', 'bkx-discord' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Status', 'bkx-discord' ); ?></th>
						<th><?php esc_html_e( 'Details', 'bkx-discord' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td>
								<?php echo esc_html( wp_date( 'M j, Y', strtotime( $log->created_at ) ) ); ?>
								<br>
								<small><?php echo esc_html( wp_date( 'g:i:s A', strtotime( $log->created_at ) ) ); ?></small>
							</td>
							<td>
								<code><?php echo esc_html( $log->event_type ); ?></code>
							</td>
							<td>
								<?php echo esc_html( $log->webhook_name ?: '—' ); ?>
							</td>
							<td>
								<?php if ( $log->booking_id ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $log->booking_id ) ); ?>">
										#<?php echo esc_html( $log->booking_id ); ?>
									</a>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td>
								<span class="bkx-status bkx-status-<?php echo esc_attr( $log->status ); ?>">
									<?php echo esc_html( ucfirst( $log->status ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $log->message_id ) : ?>
									<small class="bkx-muted">
										<?php esc_html_e( 'Message ID:', 'bkx-discord' ); ?>
										<?php echo esc_html( $log->message_id ); ?>
									</small>
								<?php endif; ?>
								<?php if ( $log->error_message ) : ?>
									<span class="bkx-error-text"><?php echo esc_html( $log->error_message ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$total_pages = ceil( $total / $per_page );
			if ( $total_pages > 1 ) :
				?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $total_pages,
							'current'   => $page_num,
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<!-- Log Statistics -->
	<div class="bkx-card">
		<h2><?php esc_html_e( 'Log Statistics', 'bkx-discord' ); ?></h2>

		<?php
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$stats = $wpdb->get_results(
			"SELECT status, COUNT(*) as count
			FROM {$logs_table}
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			GROUP BY status"
		);

		$sent_count = 0;
		$failed_count = 0;

		foreach ( $stats as $stat ) {
			if ( 'sent' === $stat->status ) {
				$sent_count = $stat->count;
			} elseif ( 'failed' === $stat->status ) {
				$failed_count = $stat->count;
			}
		}

		$total_count = $sent_count + $failed_count;
		$success_rate = $total_count > 0 ? round( ( $sent_count / $total_count ) * 100, 1 ) : 0;
		?>

		<div class="bkx-stats-grid">
			<div class="bkx-stat-card">
				<div class="bkx-stat-value"><?php echo esc_html( $sent_count ); ?></div>
				<div class="bkx-stat-label"><?php esc_html_e( 'Sent (30 days)', 'bkx-discord' ); ?></div>
			</div>
			<div class="bkx-stat-card">
				<div class="bkx-stat-value"><?php echo esc_html( $failed_count ); ?></div>
				<div class="bkx-stat-label"><?php esc_html_e( 'Failed (30 days)', 'bkx-discord' ); ?></div>
			</div>
			<div class="bkx-stat-card">
				<div class="bkx-stat-value"><?php echo esc_html( $success_rate ); ?>%</div>
				<div class="bkx-stat-label"><?php esc_html_e( 'Success Rate', 'bkx-discord' ); ?></div>
			</div>
		</div>
	</div>
</div>

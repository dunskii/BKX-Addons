<?php
/**
 * Sync logs tab template.
 *
 * @package BookingX\Salesforce
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table = $wpdb->prefix . 'bkx_sf_logs';

$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 50;
$offset   = ( $page - 1 ) * $per_page;

// Filters.
$status_filter    = sanitize_text_field( $_GET['status'] ?? '' );
$direction_filter = sanitize_text_field( $_GET['direction'] ?? '' );
$object_filter    = sanitize_text_field( $_GET['object'] ?? '' );

$where = '1=1';
if ( $status_filter ) {
	$where .= $wpdb->prepare( ' AND status = %s', $status_filter );
}
if ( $direction_filter ) {
	$where .= $wpdb->prepare( ' AND direction = %s', $direction_filter );
}
if ( $object_filter ) {
	$where .= $wpdb->prepare( ' AND sf_object_type = %s', $object_filter );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$logs = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
		$per_page,
		$offset
	)
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
$pages = ceil( $total / $per_page );
?>

<div class="bkx-sf-logs">
	<div class="bkx-card">
		<h2><?php esc_html_e( 'Sync Logs', 'bkx-salesforce' ); ?></h2>

		<!-- Filters -->
		<div class="bkx-log-filters">
			<form method="get">
				<input type="hidden" name="post_type" value="bkx_booking">
				<input type="hidden" name="page" value="bkx-salesforce">
				<input type="hidden" name="tab" value="logs">

				<select name="status">
					<option value=""><?php esc_html_e( 'All Statuses', 'bkx-salesforce' ); ?></option>
					<option value="success" <?php selected( $status_filter, 'success' ); ?>><?php esc_html_e( 'Success', 'bkx-salesforce' ); ?></option>
					<option value="error" <?php selected( $status_filter, 'error' ); ?>><?php esc_html_e( 'Error', 'bkx-salesforce' ); ?></option>
				</select>

				<select name="direction">
					<option value=""><?php esc_html_e( 'All Directions', 'bkx-salesforce' ); ?></option>
					<option value="wp_to_sf" <?php selected( $direction_filter, 'wp_to_sf' ); ?>><?php esc_html_e( 'WordPress to Salesforce', 'bkx-salesforce' ); ?></option>
					<option value="sf_to_wp" <?php selected( $direction_filter, 'sf_to_wp' ); ?>><?php esc_html_e( 'Salesforce to WordPress', 'bkx-salesforce' ); ?></option>
				</select>

				<select name="object">
					<option value=""><?php esc_html_e( 'All Objects', 'bkx-salesforce' ); ?></option>
					<option value="Contact" <?php selected( $object_filter, 'Contact' ); ?>><?php esc_html_e( 'Contact', 'bkx-salesforce' ); ?></option>
					<option value="Lead" <?php selected( $object_filter, 'Lead' ); ?>><?php esc_html_e( 'Lead', 'bkx-salesforce' ); ?></option>
					<option value="Opportunity" <?php selected( $object_filter, 'Opportunity' ); ?>><?php esc_html_e( 'Opportunity', 'bkx-salesforce' ); ?></option>
				</select>

				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-salesforce' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-salesforce&tab=logs' ) ); ?>" class="button">
					<?php esc_html_e( 'Reset', 'bkx-salesforce' ); ?>
				</a>
			</form>
		</div>

		<?php if ( empty( $logs ) ) : ?>
			<p class="bkx-no-items"><?php esc_html_e( 'No sync logs found.', 'bkx-salesforce' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'bkx-salesforce' ); ?></th>
						<th><?php esc_html_e( 'Direction', 'bkx-salesforce' ); ?></th>
						<th><?php esc_html_e( 'Action', 'bkx-salesforce' ); ?></th>
						<th><?php esc_html_e( 'WP Object', 'bkx-salesforce' ); ?></th>
						<th><?php esc_html_e( 'SF Object', 'bkx-salesforce' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-salesforce' ); ?></th>
						<th><?php esc_html_e( 'Message', 'bkx-salesforce' ); ?></th>
						<th><?php esc_html_e( 'Details', 'bkx-salesforce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
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
							<td>
								<?php if ( $log->wp_object_type && $log->wp_object_id ) : ?>
									<?php echo esc_html( $log->wp_object_type ); ?>
									<?php if ( 'booking' === $log->wp_object_type ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $log->wp_object_id ) ); ?>" target="_blank">
											#<?php echo esc_html( $log->wp_object_id ); ?>
										</a>
									<?php else : ?>
										#<?php echo esc_html( $log->wp_object_id ); ?>
									<?php endif; ?>
								<?php else : ?>
									<span class="bkx-muted">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $log->sf_object_type ) : ?>
									<?php echo esc_html( $log->sf_object_type ); ?>
									<?php if ( $log->sf_object_id ) : ?>
										<code><?php echo esc_html( $log->sf_object_id ); ?></code>
									<?php endif; ?>
								<?php else : ?>
									<span class="bkx-muted">-</span>
								<?php endif; ?>
							</td>
							<td>
								<span class="bkx-status bkx-status-<?php echo esc_attr( $log->status ); ?>">
									<?php echo esc_html( ucfirst( $log->status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $log->message ); ?></td>
							<td>
								<?php if ( $log->request_data || $log->response_data ) : ?>
									<button type="button" class="button button-small bkx-view-log-details"
											data-request="<?php echo esc_attr( $log->request_data ); ?>"
											data-response="<?php echo esc_attr( $log->response_data ); ?>">
										<?php esc_html_e( 'View', 'bkx-salesforce' ); ?>
									</button>
								<?php else : ?>
									<span class="bkx-muted">-</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %s: number of items */
								esc_html( _n( '%s item', '%s items', $total, 'bkx-salesforce' ) ),
								number_format_i18n( $total )
							);
							?>
						</span>
						<span class="pagination-links">
							<?php
							$base_url = add_query_arg(
								array(
									'post_type' => 'bkx_booking',
									'page'      => 'bkx-salesforce',
									'tab'       => 'logs',
									'status'    => $status_filter,
									'direction' => $direction_filter,
									'object'    => $object_filter,
								),
								admin_url( 'edit.php' )
							);

							if ( $page > 1 ) :
								?>
								<a class="first-page button" href="<?php echo esc_url( add_query_arg( 'paged', 1, $base_url ) ); ?>">
									&laquo;
								</a>
								<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $page - 1, $base_url ) ); ?>">
									&lsaquo;
								</a>
							<?php else : ?>
								<span class="tablenav-pages-navspan button disabled">&laquo;</span>
								<span class="tablenav-pages-navspan button disabled">&lsaquo;</span>
							<?php endif; ?>

							<span class="paging-input">
								<?php echo esc_html( $page ); ?> / <?php echo esc_html( $pages ); ?>
							</span>

							<?php if ( $page < $pages ) : ?>
								<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $page + 1, $base_url ) ); ?>">
									&rsaquo;
								</a>
								<a class="last-page button" href="<?php echo esc_url( add_query_arg( 'paged', $pages, $base_url ) ); ?>">
									&raquo;
								</a>
							<?php else : ?>
								<span class="tablenav-pages-navspan button disabled">&rsaquo;</span>
								<span class="tablenav-pages-navspan button disabled">&raquo;</span>
							<?php endif; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<!-- Clear Logs -->
		<div class="bkx-log-actions">
			<form method="post" id="bkx-clear-logs-form">
				<?php wp_nonce_field( 'bkx_sf_clear_logs', 'bkx_sf_clear_nonce' ); ?>
				<button type="button" class="button" id="bkx-clear-old-logs">
					<?php esc_html_e( 'Clear Logs Older Than 30 Days', 'bkx-salesforce' ); ?>
				</button>
			</form>
		</div>
	</div>
</div>

<!-- Log Details Modal -->
<div id="bkx-log-details-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content bkx-modal-large">
		<span class="bkx-modal-close">&times;</span>
		<h2><?php esc_html_e( 'Log Details', 'bkx-salesforce' ); ?></h2>

		<div class="bkx-log-detail-section">
			<h3><?php esc_html_e( 'Request Data', 'bkx-salesforce' ); ?></h3>
			<pre id="bkx-log-request-data"></pre>
		</div>

		<div class="bkx-log-detail-section">
			<h3><?php esc_html_e( 'Response Data', 'bkx-salesforce' ); ?></h3>
			<pre id="bkx-log-response-data"></pre>
		</div>
	</div>
</div>

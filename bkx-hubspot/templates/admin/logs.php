<?php
/**
 * Sync logs tab template.
 *
 * @package BookingX\HubSpot
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table = $wpdb->prefix . 'bkx_hs_logs';

$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 50;
$offset   = ( $page - 1 ) * $per_page;

// Filters.
$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$object_filter = sanitize_text_field( $_GET['object'] ?? '' );

$where = '1=1';
if ( $status_filter ) {
	$where .= $wpdb->prepare( ' AND status = %s', $status_filter );
}
if ( $object_filter ) {
	$where .= $wpdb->prepare( ' AND hs_object_type = %s', $object_filter );
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

<div class="bkx-hs-logs">
	<div class="bkx-card">
		<h2><?php esc_html_e( 'Sync Logs', 'bkx-hubspot' ); ?></h2>

		<!-- Filters -->
		<div class="bkx-log-filters">
			<form method="get">
				<input type="hidden" name="post_type" value="bkx_booking">
				<input type="hidden" name="page" value="bkx-hubspot">
				<input type="hidden" name="tab" value="logs">

				<select name="status">
					<option value=""><?php esc_html_e( 'All Statuses', 'bkx-hubspot' ); ?></option>
					<option value="success" <?php selected( $status_filter, 'success' ); ?>><?php esc_html_e( 'Success', 'bkx-hubspot' ); ?></option>
					<option value="error" <?php selected( $status_filter, 'error' ); ?>><?php esc_html_e( 'Error', 'bkx-hubspot' ); ?></option>
				</select>

				<select name="object">
					<option value=""><?php esc_html_e( 'All Objects', 'bkx-hubspot' ); ?></option>
					<option value="contact" <?php selected( $object_filter, 'contact' ); ?>><?php esc_html_e( 'Contact', 'bkx-hubspot' ); ?></option>
					<option value="deal" <?php selected( $object_filter, 'deal' ); ?>><?php esc_html_e( 'Deal', 'bkx-hubspot' ); ?></option>
				</select>

				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-hubspot' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-hubspot&tab=logs' ) ); ?>" class="button">
					<?php esc_html_e( 'Reset', 'bkx-hubspot' ); ?>
				</a>
			</form>
		</div>

		<?php if ( empty( $logs ) ) : ?>
			<p class="bkx-no-items"><?php esc_html_e( 'No sync logs found.', 'bkx-hubspot' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'bkx-hubspot' ); ?></th>
						<th><?php esc_html_e( 'Direction', 'bkx-hubspot' ); ?></th>
						<th><?php esc_html_e( 'Action', 'bkx-hubspot' ); ?></th>
						<th><?php esc_html_e( 'WP Object', 'bkx-hubspot' ); ?></th>
						<th><?php esc_html_e( 'HS Object', 'bkx-hubspot' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-hubspot' ); ?></th>
						<th><?php esc_html_e( 'Message', 'bkx-hubspot' ); ?></th>
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
								<?php if ( 'wp_to_hs' === $log->direction ) : ?>
									<span class="bkx-direction bkx-direction-out">WP &rarr; HS</span>
								<?php else : ?>
									<span class="bkx-direction bkx-direction-in">HS &rarr; WP</span>
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
								<?php if ( $log->hs_object_type ) : ?>
									<?php echo esc_html( ucfirst( $log->hs_object_type ) ); ?>
									<?php if ( $log->hs_object_id ) : ?>
										<code><?php echo esc_html( $log->hs_object_id ); ?></code>
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
								esc_html( _n( '%s item', '%s items', $total, 'bkx-hubspot' ) ),
								number_format_i18n( $total )
							);
							?>
						</span>
						<span class="pagination-links">
							<?php
							$base_url = add_query_arg(
								array(
									'post_type' => 'bkx_booking',
									'page'      => 'bkx-hubspot',
									'tab'       => 'logs',
									'status'    => $status_filter,
									'object'    => $object_filter,
								),
								admin_url( 'edit.php' )
							);

							if ( $page > 1 ) :
								?>
								<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $page - 1, $base_url ) ); ?>">
									&lsaquo;
								</a>
							<?php else : ?>
								<span class="tablenav-pages-navspan button disabled">&lsaquo;</span>
							<?php endif; ?>

							<span class="paging-input">
								<?php echo esc_html( $page ); ?> / <?php echo esc_html( $pages ); ?>
							</span>

							<?php if ( $page < $pages ) : ?>
								<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $page + 1, $base_url ) ); ?>">
									&rsaquo;
								</a>
							<?php else : ?>
								<span class="tablenav-pages-navspan button disabled">&rsaquo;</span>
							<?php endif; ?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

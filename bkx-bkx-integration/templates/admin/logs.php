<?php
/**
 * Sync logs template.
 *
 * @package BookingX\BkxIntegration
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table = $wpdb->prefix . 'bkx_remote_logs';

$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 50;
$offset   = ( $page - 1 ) * $per_page;

// Filters.
$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$site_filter   = absint( $_GET['site_id'] ?? 0 );

$where  = '1=1';
$params = array();

if ( $status_filter ) {
	$where   .= ' AND l.status = %s';
	$params[] = $status_filter;
}

if ( $site_filter ) {
	$where   .= ' AND l.site_id = %d';
	$params[] = $site_filter;
}

// Get logs.
$sql = "SELECT l.*, s.name as site_name
	FROM {$table} l
	LEFT JOIN {$wpdb->prefix}bkx_remote_sites s ON l.site_id = s.id
	WHERE {$where}
	ORDER BY l.created_at DESC
	LIMIT %d OFFSET %d";

$params[] = $per_page;
$params[] = $offset;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
$logs = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

// Get total count.
$count_sql = "SELECT COUNT(*) FROM {$table} l WHERE {$where}";
$count_params = array_slice( $params, 0, -2 );

if ( $count_params ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
	$total = $wpdb->get_var( $wpdb->prepare( $count_sql, $count_params ) );
} else {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
	$total = $wpdb->get_var( $count_sql );
}

$pages = ceil( $total / $per_page );

// Get sites for filter.
$addon = \BookingX\BkxIntegration\BkxIntegrationAddon::get_instance();
$sites = $addon->get_service( 'sites' )->get_all();
?>

<div class="bkx-bkx-logs">
	<div class="bkx-bkx-card">
		<h2><?php esc_html_e( 'Sync Logs', 'bkx-bkx-integration' ); ?></h2>

		<!-- Filters -->
		<div class="bkx-bkx-log-filters">
			<form method="get">
				<input type="hidden" name="post_type" value="bkx_booking">
				<input type="hidden" name="page" value="bkx-integration">
				<input type="hidden" name="tab" value="logs">

				<select name="site_id">
					<option value=""><?php esc_html_e( 'All Sites', 'bkx-bkx-integration' ); ?></option>
					<?php foreach ( $sites as $site ) : ?>
						<option value="<?php echo esc_attr( $site->id ); ?>" <?php selected( $site_filter, $site->id ); ?>>
							<?php echo esc_html( $site->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<select name="status">
					<option value=""><?php esc_html_e( 'All Statuses', 'bkx-bkx-integration' ); ?></option>
					<option value="success" <?php selected( $status_filter, 'success' ); ?>><?php esc_html_e( 'Success', 'bkx-bkx-integration' ); ?></option>
					<option value="error" <?php selected( $status_filter, 'error' ); ?>><?php esc_html_e( 'Error', 'bkx-bkx-integration' ); ?></option>
					<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'bkx-bkx-integration' ); ?></option>
				</select>

				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-bkx-integration' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-integration&tab=logs' ) ); ?>" class="button">
					<?php esc_html_e( 'Reset', 'bkx-bkx-integration' ); ?>
				</a>
				<button type="button" class="button" id="bkx-bkx-clear-logs" style="margin-left: 20px;">
					<?php esc_html_e( 'Clear All Logs', 'bkx-bkx-integration' ); ?>
				</button>
			</form>
		</div>

		<?php if ( empty( $logs ) ) : ?>
			<p class="bkx-bkx-no-items"><?php esc_html_e( 'No sync logs found.', 'bkx-bkx-integration' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'bkx-bkx-integration' ); ?></th>
						<th><?php esc_html_e( 'Site', 'bkx-bkx-integration' ); ?></th>
						<th><?php esc_html_e( 'Direction', 'bkx-bkx-integration' ); ?></th>
						<th><?php esc_html_e( 'Action', 'bkx-bkx-integration' ); ?></th>
						<th><?php esc_html_e( 'Object', 'bkx-bkx-integration' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-bkx-integration' ); ?></th>
						<th><?php esc_html_e( 'Message', 'bkx-bkx-integration' ); ?></th>
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
							<td><?php echo esc_html( $log->site_name ?? __( 'Unknown', 'bkx-bkx-integration' ) ); ?></td>
							<td>
								<?php if ( 'out' === $log->direction ) : ?>
									<span class="bkx-bkx-direction bkx-bkx-direction-out">&rarr; OUT</span>
								<?php else : ?>
									<span class="bkx-bkx-direction bkx-bkx-direction-in">&larr; IN</span>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( $log->action ); ?></code></td>
							<td><?php echo esc_html( ucfirst( $log->object_type ) ); ?></td>
							<td>
								<span class="bkx-bkx-status bkx-bkx-status-<?php echo esc_attr( $log->status ); ?>">
									<?php echo esc_html( ucfirst( $log->status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $log->message ?: '—' ); ?></td>
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
								esc_html( _n( '%s item', '%s items', $total, 'bkx-bkx-integration' ) ),
								number_format_i18n( $total )
							);
							?>
						</span>
						<span class="pagination-links">
							<?php
							$base_url = add_query_arg(
								array(
									'post_type' => 'bkx_booking',
									'page'      => 'bkx-integration',
									'tab'       => 'logs',
									'status'    => $status_filter,
									'site_id'   => $site_filter,
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

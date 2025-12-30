<?php
/**
 * Security events template.
 *
 * @package BookingX\SecurityAudit
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$severity     = isset( $_GET['severity'] ) ? sanitize_text_field( wp_unslash( $_GET['severity'] ) ) : '';
$resolved     = isset( $_GET['resolved'] ) ? sanitize_text_field( wp_unslash( $_GET['resolved'] ) ) : '';
$per_page     = 20;
$offset       = ( $current_page - 1 ) * $per_page;

$table = $wpdb->prefix . 'bkx_security_events';

// Build query.
$where = array( '1=1' );
$values = array();

if ( $severity ) {
	$where[]  = 'severity = %s';
	$values[] = $severity;
}

if ( $resolved !== '' ) {
	$where[]  = 'resolved = %d';
	$values[] = (int) $resolved;
}

$where_clause = implode( ' AND ', $where );

// Get total.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$total = $wpdb->get_var(
	empty( $values )
		? "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}"
		: $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", ...$values ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
);

// Get events.
$query_values   = $values;
$query_values[] = $per_page;
$query_values[] = $offset;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$events = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		...$query_values
	),
	ARRAY_A
);

$pages = ceil( $total / $per_page );
?>

<div class="bkx-security-events">
	<div class="bkx-security-card">
		<div class="bkx-security-card-header">
			<h2><?php esc_html_e( 'Security Events', 'bkx-security-audit' ); ?></h2>
		</div>

		<!-- Filters -->
		<form method="get" class="bkx-security-filters">
			<input type="hidden" name="page" value="bkx-security-audit">
			<input type="hidden" name="tab" value="events">

			<select name="severity">
				<option value=""><?php esc_html_e( 'All Severities', 'bkx-security-audit' ); ?></option>
				<option value="critical" <?php selected( $severity, 'critical' ); ?>><?php esc_html_e( 'Critical', 'bkx-security-audit' ); ?></option>
				<option value="high" <?php selected( $severity, 'high' ); ?>><?php esc_html_e( 'High', 'bkx-security-audit' ); ?></option>
				<option value="medium" <?php selected( $severity, 'medium' ); ?>><?php esc_html_e( 'Medium', 'bkx-security-audit' ); ?></option>
				<option value="low" <?php selected( $severity, 'low' ); ?>><?php esc_html_e( 'Low', 'bkx-security-audit' ); ?></option>
			</select>

			<select name="resolved">
				<option value=""><?php esc_html_e( 'All Status', 'bkx-security-audit' ); ?></option>
				<option value="0" <?php selected( $resolved, '0' ); ?>><?php esc_html_e( 'Unresolved', 'bkx-security-audit' ); ?></option>
				<option value="1" <?php selected( $resolved, '1' ); ?>><?php esc_html_e( 'Resolved', 'bkx-security-audit' ); ?></option>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-security-audit' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-security-audit&tab=events' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'bkx-security-audit' ); ?></a>
		</form>

		<?php if ( ! empty( $events ) ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Severity', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'Event', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'IP Address', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'Time', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-security-audit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $events as $event ) : ?>
						<tr class="bkx-event-severity-<?php echo esc_attr( $event['severity'] ); ?>">
							<td>
								<span class="bkx-severity-badge bkx-severity-<?php echo esc_attr( $event['severity'] ); ?>">
									<?php echo esc_html( ucfirst( $event['severity'] ) ); ?>
								</span>
							</td>
							<td>
								<strong><?php echo esc_html( $event['title'] ); ?></strong>
								<?php if ( $event['description'] ) : ?>
									<p class="description"><?php echo esc_html( $event['description'] ); ?></p>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $event['ip_address'] ) : ?>
									<code><?php echo esc_html( $event['ip_address'] ); ?></code>
								<?php else : ?>
									<span class="bkx-muted">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( human_time_diff( strtotime( $event['created_at'] ) ) . ' ago' ); ?>
							</td>
							<td>
								<?php if ( $event['resolved'] ) : ?>
									<span class="bkx-status bkx-status-resolved"><?php esc_html_e( 'Resolved', 'bkx-security-audit' ); ?></span>
								<?php else : ?>
									<span class="bkx-status bkx-status-pending"><?php esc_html_e( 'Pending', 'bkx-security-audit' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! $event['resolved'] ) : ?>
									<button type="button" class="button button-small bkx-resolve-event" data-id="<?php echo esc_attr( $event['id'] ); ?>">
										<?php esc_html_e( 'Resolve', 'bkx-security-audit' ); ?>
									</button>
								<?php endif; ?>
								<?php if ( $event['data'] ) : ?>
									<button type="button" class="button button-small bkx-view-event-data" data-data="<?php echo esc_attr( $event['data'] ); ?>">
										<?php esc_html_e( 'Details', 'bkx-security-audit' ); ?>
									</button>
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
						<?php
						echo wp_kses_post( paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $pages,
							'current'   => $current_page,
						) ) );
						?>
					</div>
				</div>
			<?php endif; ?>

		<?php else : ?>
			<p class="bkx-security-no-data"><?php esc_html_e( 'No security events found.', 'bkx-security-audit' ); ?></p>
		<?php endif; ?>
	</div>
</div>

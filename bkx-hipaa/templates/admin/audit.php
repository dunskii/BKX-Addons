<?php
/**
 * HIPAA Audit Log.
 *
 * @package BookingX\HIPAA
 */

defined( 'ABSPATH' ) || exit;

$addon        = \BookingX\HIPAA\HIPAAAddon::get_instance();
$audit_logger = $addon->get_service( 'audit_logger' );

$page      = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page  = 50;
$offset    = ( $page - 1 ) * $per_page;

$event_type = isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : '';
$date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$date_to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

$args = array(
	'limit'      => $per_page,
	'offset'     => $offset,
	'event_type' => $event_type,
	'date_from'  => $date_from,
	'date_to'    => $date_to,
);

$logs       = $audit_logger->get_logs( $args );
$total_logs = $audit_logger->count_logs( $args );
$total_pages = ceil( $total_logs / $per_page );
?>

<div class="bkx-audit-section">
	<h2><?php esc_html_e( 'Audit Log', 'bkx-hipaa' ); ?></h2>

	<!-- Filters -->
	<div class="bkx-audit-filters">
		<form method="get" action="">
			<input type="hidden" name="post_type" value="bkx_booking">
			<input type="hidden" name="page" value="bkx-hipaa">
			<input type="hidden" name="tab" value="audit">

			<select name="event_type">
				<option value=""><?php esc_html_e( 'All Event Types', 'bkx-hipaa' ); ?></option>
				<option value="authentication" <?php selected( $event_type, 'authentication' ); ?>><?php esc_html_e( 'Authentication', 'bkx-hipaa' ); ?></option>
				<option value="phi" <?php selected( $event_type, 'phi' ); ?>><?php esc_html_e( 'PHI Access', 'bkx-hipaa' ); ?></option>
				<option value="access_control" <?php selected( $event_type, 'access_control' ); ?>><?php esc_html_e( 'Access Control', 'bkx-hipaa' ); ?></option>
				<option value="settings" <?php selected( $event_type, 'settings' ); ?>><?php esc_html_e( 'Settings', 'bkx-hipaa' ); ?></option>
				<option value="baa" <?php selected( $event_type, 'baa' ); ?>><?php esc_html_e( 'BAA', 'bkx-hipaa' ); ?></option>
				<option value="system" <?php selected( $event_type, 'system' ); ?>><?php esc_html_e( 'System', 'bkx-hipaa' ); ?></option>
			</select>

			<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'From Date', 'bkx-hipaa' ); ?>">
			<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'To Date', 'bkx-hipaa' ); ?>">

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-hipaa' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hipaa&tab=audit' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'bkx-hipaa' ); ?></a>
		</form>

		<button type="button" id="bkx-export-audit" class="button button-secondary">
			<span class="dashicons dashicons-download"></span>
			<?php esc_html_e( 'Export CSV', 'bkx-hipaa' ); ?>
		</button>
	</div>

	<!-- Logs Table -->
	<?php if ( ! empty( $logs ) ) : ?>
		<table class="widefat striped bkx-audit-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date/Time', 'bkx-hipaa' ); ?></th>
					<th><?php esc_html_e( 'Event', 'bkx-hipaa' ); ?></th>
					<th><?php esc_html_e( 'User', 'bkx-hipaa' ); ?></th>
					<th><?php esc_html_e( 'IP Address', 'bkx-hipaa' ); ?></th>
					<th><?php esc_html_e( 'Resource', 'bkx-hipaa' ); ?></th>
					<th><?php esc_html_e( 'PHI', 'bkx-hipaa' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<?php $user = $log->user_id ? get_user_by( 'id', $log->user_id ) : null; ?>
					<tr>
						<td>
							<strong><?php echo esc_html( gmdate( 'M j, Y', strtotime( $log->created_at ) ) ); ?></strong><br>
							<span class="bkx-time"><?php echo esc_html( gmdate( 'g:i:s A', strtotime( $log->created_at ) ) ); ?></span>
						</td>
						<td>
							<span class="bkx-event-type bkx-type-<?php echo esc_attr( $log->event_type ); ?>">
								<?php echo esc_html( $log->event_type ); ?>
							</span>
							<span class="bkx-event-action"><?php echo esc_html( $log->event_action ); ?></span>
						</td>
						<td>
							<?php if ( $user ) : ?>
								<?php echo esc_html( $user->display_name ); ?><br>
								<span class="bkx-user-email"><?php echo esc_html( $user->user_email ); ?></span>
							<?php else : ?>
								<span class="bkx-unknown"><?php esc_html_e( 'System', 'bkx-hipaa' ); ?></span>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $log->user_ip ); ?></code></td>
						<td>
							<?php if ( $log->resource_type ) : ?>
								<?php echo esc_html( $log->resource_type ); ?>
								<?php if ( $log->resource_id ) : ?>
									<code>#<?php echo esc_html( $log->resource_id ); ?></code>
								<?php endif; ?>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $log->phi_accessed ) : ?>
								<span class="bkx-phi-badge"><?php esc_html_e( 'PHI', 'bkx-hipaa' ); ?></span>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="bkx-pagination">
				<?php
				$base_url = admin_url( 'admin.php?page=bkx-hipaa&tab=audit' );
				if ( $event_type ) {
					$base_url = add_query_arg( 'event_type', $event_type, $base_url );
				}
				if ( $date_from ) {
					$base_url = add_query_arg( 'date_from', $date_from, $base_url );
				}
				if ( $date_to ) {
					$base_url = add_query_arg( 'date_to', $date_to, $base_url );
				}

				echo paginate_links(
					array(
						'base'      => $base_url . '%_%',
						'format'    => '&paged=%#%',
						'current'   => $page,
						'total'     => $total_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					)
				);
				?>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="bkx-no-logs">
			<span class="dashicons dashicons-clipboard"></span>
			<p><?php esc_html_e( 'No audit logs found.', 'bkx-hipaa' ); ?></p>
		</div>
	<?php endif; ?>
</div>

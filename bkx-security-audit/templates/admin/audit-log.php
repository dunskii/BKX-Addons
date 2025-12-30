<?php
/**
 * Audit log template.
 *
 * @package BookingX\SecurityAudit
 */

defined( 'ABSPATH' ) || exit;

$addon = \BookingX\SecurityAudit\SecurityAuditAddon::get_instance();
$audit = $addon->get_service( 'audit' );

// Get filter parameters.
$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$action_filter = isset( $_GET['action_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['action_filter'] ) ) : '';
$user_filter = isset( $_GET['user_filter'] ) ? absint( $_GET['user_filter'] ) : 0;
$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

$logs = $audit->get_logs( array(
	'page'      => $current_page,
	'per_page'  => 20,
	'action'    => $action_filter ?: null,
	'user_id'   => $user_filter ?: null,
	'date_from' => $date_from ?: null,
	'date_to'   => $date_to ?: null,
	'search'    => $search ?: null,
) );

// Get unique actions for filter.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$actions = $wpdb->get_col( "SELECT DISTINCT action FROM {$wpdb->prefix}bkx_audit_log ORDER BY action" );
?>

<div class="bkx-security-audit-log">
	<div class="bkx-security-card">
		<div class="bkx-security-card-header">
			<h2><?php esc_html_e( 'Audit Log', 'bkx-security-audit' ); ?></h2>
			<div class="bkx-security-header-actions">
				<button type="button" class="button" id="bkx-export-audit-log">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export', 'bkx-security-audit' ); ?>
				</button>
			</div>
		</div>

		<!-- Filters -->
		<form method="get" class="bkx-security-filters">
			<input type="hidden" name="page" value="bkx-security-audit">
			<input type="hidden" name="tab" value="audit-log">

			<select name="action_filter">
				<option value=""><?php esc_html_e( 'All Actions', 'bkx-security-audit' ); ?></option>
				<?php foreach ( $actions as $action ) : ?>
					<option value="<?php echo esc_attr( $action ); ?>" <?php selected( $action_filter, $action ); ?>>
						<?php echo esc_html( str_replace( '_', ' ', $action ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php
			wp_dropdown_users( array(
				'name'             => 'user_filter',
				'show_option_none' => __( 'All Users', 'bkx-security-audit' ),
				'selected'         => $user_filter,
				'option_none_value' => 0,
			) );
			?>

			<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'From', 'bkx-security-audit' ); ?>">
			<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'To', 'bkx-security-audit' ); ?>">

			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search...', 'bkx-security-audit' ); ?>">

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-security-audit' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-security-audit&tab=audit-log' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'bkx-security-audit' ); ?></a>
		</form>

		<!-- Log Table -->
		<?php if ( ! empty( $logs['logs'] ) ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'Action', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'User', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'Object', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'IP Address', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'Details', 'bkx-security-audit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs['logs'] as $log ) : ?>
						<tr>
							<td>
								<span title="<?php echo esc_attr( $log['created_at'] ); ?>">
									<?php echo esc_html( human_time_diff( strtotime( $log['created_at'] ) ) . ' ago' ); ?>
								</span>
							</td>
							<td>
								<span class="bkx-action-badge bkx-action-<?php echo esc_attr( str_replace( '_', '-', $log['action'] ) ); ?>">
									<?php echo esc_html( str_replace( '_', ' ', $log['action'] ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $log['user_id'] ) : ?>
									<?php $user = get_userdata( $log['user_id'] ); ?>
									<?php if ( $user ) : ?>
										<a href="<?php echo esc_url( get_edit_user_link( $log['user_id'] ) ); ?>">
											<?php echo esc_html( $user->display_name ); ?>
										</a>
									<?php else : ?>
										<?php echo esc_html( $log['user_email'] ?: '#' . $log['user_id'] ); ?>
									<?php endif; ?>
								<?php else : ?>
									<span class="bkx-muted"><?php esc_html_e( 'Guest', 'bkx-security-audit' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $log['object_type'] && $log['object_id'] ) : ?>
									<span class="bkx-object-type"><?php echo esc_html( $log['object_type'] ); ?></span>
									#<?php echo esc_html( $log['object_id'] ); ?>
								<?php elseif ( $log['object_name'] ) : ?>
									<?php echo esc_html( $log['object_name'] ); ?>
								<?php else : ?>
									<span class="bkx-muted">—</span>
								<?php endif; ?>
							</td>
							<td>
								<code><?php echo esc_html( $log['ip_address'] ); ?></code>
								<button type="button" class="button-link bkx-ip-actions" data-ip="<?php echo esc_attr( $log['ip_address'] ); ?>">
									<span class="dashicons dashicons-ellipsis"></span>
								</button>
							</td>
							<td>
								<?php if ( $log['details'] ) : ?>
									<button type="button" class="button button-small bkx-view-details" data-details="<?php echo esc_attr( $log['details'] ); ?>">
										<?php esc_html_e( 'View', 'bkx-security-audit' ); ?>
									</button>
								<?php else : ?>
									<span class="bkx-muted">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $logs['pages'] > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post( paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $logs['pages'],
							'current'   => $current_page,
						) ) );
						?>
					</div>
				</div>
			<?php endif; ?>

		<?php else : ?>
			<p class="bkx-security-no-data"><?php esc_html_e( 'No audit log entries found.', 'bkx-security-audit' ); ?></p>
		<?php endif; ?>
	</div>
</div>

<!-- Details Modal -->
<div id="bkx-details-modal" class="bkx-security-modal" style="display: none;">
	<div class="bkx-security-modal-content">
		<span class="bkx-security-modal-close">&times;</span>
		<h2><?php esc_html_e( 'Log Details', 'bkx-security-audit' ); ?></h2>
		<pre id="bkx-details-content"></pre>
	</div>
</div>

<!-- IP Actions Menu -->
<div id="bkx-ip-menu" class="bkx-security-dropdown" style="display: none;">
	<ul>
		<li><a href="#" class="bkx-ip-whitelist"><?php esc_html_e( 'Add to Whitelist', 'bkx-security-audit' ); ?></a></li>
		<li><a href="#" class="bkx-ip-block"><?php esc_html_e( 'Block IP', 'bkx-security-audit' ); ?></a></li>
		<li><a href="#" class="bkx-ip-lookup"><?php esc_html_e( 'IP Lookup', 'bkx-security-audit' ); ?></a></li>
	</ul>
</div>

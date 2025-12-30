<?php
/**
 * Data requests template.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

$addon = \BookingX\GdprCompliance\GdprComplianceAddon::get_instance();

// Filters.
$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$type_filter   = sanitize_text_field( $_GET['type'] ?? '' );
$page          = max( 1, absint( $_GET['paged'] ?? 1 ) );

$requests = $addon->get_service( 'requests' )->get_requests(
	array(
		'status'   => $status_filter,
		'type'     => $type_filter,
		'per_page' => 20,
		'page'     => $page,
	)
);

$counts = $addon->get_service( 'requests' )->count_by_status();
?>

<div class="bkx-gdpr-requests">
	<!-- Status Filters -->
	<ul class="subsubsub">
		<li>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-gdpr&tab=requests' ) ); ?>"
			   class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">
				<?php esc_html_e( 'All', 'bkx-gdpr-compliance' ); ?>
				<span class="count">(<?php echo esc_html( array_sum( $counts ) ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( add_query_arg( 'status', 'verified' ) ); ?>"
			   class="<?php echo 'verified' === $status_filter ? 'current' : ''; ?>">
				<?php esc_html_e( 'Pending', 'bkx-gdpr-compliance' ); ?>
				<span class="count">(<?php echo esc_html( $counts['verified'] ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( add_query_arg( 'status', 'pending_verification' ) ); ?>"
			   class="<?php echo 'pending_verification' === $status_filter ? 'current' : ''; ?>">
				<?php esc_html_e( 'Awaiting Verification', 'bkx-gdpr-compliance' ); ?>
				<span class="count">(<?php echo esc_html( $counts['pending_verification'] ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( add_query_arg( 'status', 'completed' ) ); ?>"
			   class="<?php echo 'completed' === $status_filter ? 'current' : ''; ?>">
				<?php esc_html_e( 'Completed', 'bkx-gdpr-compliance' ); ?>
				<span class="count">(<?php echo esc_html( $counts['completed'] ); ?>)</span>
			</a>
		</li>
	</ul>

	<!-- Type Filter -->
	<div class="bkx-gdpr-filters">
		<form method="get">
			<input type="hidden" name="post_type" value="bkx_booking">
			<input type="hidden" name="page" value="bkx-gdpr">
			<input type="hidden" name="tab" value="requests">
			<?php if ( $status_filter ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>">
			<?php endif; ?>

			<select name="type">
				<option value=""><?php esc_html_e( 'All Types', 'bkx-gdpr-compliance' ); ?></option>
				<option value="export" <?php selected( $type_filter, 'export' ); ?>><?php esc_html_e( 'Export', 'bkx-gdpr-compliance' ); ?></option>
				<option value="erasure" <?php selected( $type_filter, 'erasure' ); ?>><?php esc_html_e( 'Erasure', 'bkx-gdpr-compliance' ); ?></option>
				<option value="access" <?php selected( $type_filter, 'access' ); ?>><?php esc_html_e( 'Access', 'bkx-gdpr-compliance' ); ?></option>
				<option value="rectification" <?php selected( $type_filter, 'rectification' ); ?>><?php esc_html_e( 'Rectification', 'bkx-gdpr-compliance' ); ?></option>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-gdpr-compliance' ); ?></button>
		</form>
	</div>

	<?php if ( empty( $requests ) ) : ?>
		<div class="bkx-gdpr-card">
			<p class="bkx-gdpr-no-items"><?php esc_html_e( 'No data requests found.', 'bkx-gdpr-compliance' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Request', 'bkx-gdpr-compliance' ); ?></th>
					<th><?php esc_html_e( 'Type', 'bkx-gdpr-compliance' ); ?></th>
					<th><?php esc_html_e( 'Email', 'bkx-gdpr-compliance' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bkx-gdpr-compliance' ); ?></th>
					<th><?php esc_html_e( 'Submitted', 'bkx-gdpr-compliance' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'bkx-gdpr-compliance' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $requests as $request ) : ?>
					<tr data-request-id="<?php echo esc_attr( $request->id ); ?>">
						<td>
							<strong>#<?php echo esc_html( $request->id ); ?></strong>
						</td>
						<td>
							<span class="bkx-gdpr-badge bkx-gdpr-badge-<?php echo esc_attr( $request->request_type ); ?>">
								<?php echo esc_html( ucfirst( $request->request_type ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $request->email ); ?></td>
						<td>
							<span class="bkx-gdpr-status bkx-gdpr-status-<?php echo esc_attr( $request->status ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $request->status ) ) ); ?>
							</span>
						</td>
						<td>
							<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request->created_at ) ) ); ?>
							<?php if ( $request->expires_at && strtotime( $request->expires_at ) > time() && 'pending_verification' === $request->status ) : ?>
								<br><small>
									<?php
									printf(
										/* translators: %s: time difference */
										esc_html__( 'Expires in %s', 'bkx-gdpr-compliance' ),
										human_time_diff( time(), strtotime( $request->expires_at ) )
									);
									?>
								</small>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( 'verified' === $request->status ) : ?>
								<button type="button" class="button button-primary button-small bkx-gdpr-process"
										data-id="<?php echo esc_attr( $request->id ); ?>"
										data-action="approve">
									<?php esc_html_e( 'Process', 'bkx-gdpr-compliance' ); ?>
								</button>
								<button type="button" class="button button-small bkx-gdpr-process"
										data-id="<?php echo esc_attr( $request->id ); ?>"
										data-action="reject">
									<?php esc_html_e( 'Reject', 'bkx-gdpr-compliance' ); ?>
								</button>
							<?php elseif ( 'completed' === $request->status && $request->export_file ) : ?>
								<a href="#" class="button button-small bkx-gdpr-download" data-id="<?php echo esc_attr( $request->id ); ?>">
									<?php esc_html_e( 'Download', 'bkx-gdpr-compliance' ); ?>
								</a>
							<?php else : ?>
								<span class="bkx-gdpr-muted">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

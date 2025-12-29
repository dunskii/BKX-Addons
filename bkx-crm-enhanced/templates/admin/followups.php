<?php
/**
 * Followups tab template.
 *
 * @package BookingX\CRM
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'pending';
$page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page = 20;
$offset = ( $page - 1 ) * $per_page;

$followups_table = $wpdb->prefix . 'bkx_crm_followups';
$customers_table = $wpdb->prefix . 'bkx_crm_customers';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$total = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$followups_table} WHERE status = %s",
	$status
) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$followups = $wpdb->get_results( $wpdb->prepare(
	"SELECT f.*, c.email, c.first_name, c.last_name
	FROM {$followups_table} f
	INNER JOIN {$customers_table} c ON f.customer_id = c.id
	WHERE f.status = %s
	ORDER BY f.scheduled_at ASC
	LIMIT %d OFFSET %d",
	$status,
	$per_page,
	$offset
) );

// Get status counts.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$status_counts = $wpdb->get_results(
	"SELECT status, COUNT(*) as count FROM {$followups_table} GROUP BY status"
);

$counts = array(
	'pending'   => 0,
	'sent'      => 0,
	'cancelled' => 0,
	'failed'    => 0,
);

foreach ( $status_counts as $s ) {
	$counts[ $s->status ] = (int) $s->count;
}
?>

<div class="bkx-crm-followups-page">
	<!-- Status Filters -->
	<ul class="subsubsub">
		<li>
			<a href="<?php echo esc_url( add_query_arg( 'status', 'pending' ) ); ?>"
			   class="<?php echo 'pending' === $status ? 'current' : ''; ?>">
				<?php esc_html_e( 'Pending', 'bkx-crm' ); ?>
				<span class="count">(<?php echo esc_html( $counts['pending'] ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( add_query_arg( 'status', 'sent' ) ); ?>"
			   class="<?php echo 'sent' === $status ? 'current' : ''; ?>">
				<?php esc_html_e( 'Sent', 'bkx-crm' ); ?>
				<span class="count">(<?php echo esc_html( $counts['sent'] ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( add_query_arg( 'status', 'cancelled' ) ); ?>"
			   class="<?php echo 'cancelled' === $status ? 'current' : ''; ?>">
				<?php esc_html_e( 'Cancelled', 'bkx-crm' ); ?>
				<span class="count">(<?php echo esc_html( $counts['cancelled'] ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( add_query_arg( 'status', 'failed' ) ); ?>"
			   class="<?php echo 'failed' === $status ? 'current' : ''; ?>">
				<?php esc_html_e( 'Failed', 'bkx-crm' ); ?>
				<span class="count">(<?php echo esc_html( $counts['failed'] ); ?>)</span>
			</a>
		</li>
	</ul>

	<div class="bkx-card" style="margin-top: 20px;">
		<?php if ( empty( $followups ) ) : ?>
			<p><?php esc_html_e( 'No follow-ups found.', 'bkx-crm' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Customer', 'bkx-crm' ); ?></th>
						<th style="width: 120px;"><?php esc_html_e( 'Type', 'bkx-crm' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Channel', 'bkx-crm' ); ?></th>
						<th style="width: 150px;"><?php esc_html_e( 'Scheduled', 'bkx-crm' ); ?></th>
						<?php if ( 'sent' === $status ) : ?>
							<th style="width: 150px;"><?php esc_html_e( 'Sent', 'bkx-crm' ); ?></th>
						<?php endif; ?>
						<?php if ( 'pending' === $status ) : ?>
							<th style="width: 100px;"><?php esc_html_e( 'Actions', 'bkx-crm' ); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $followups as $followup ) : ?>
						<tr data-followup-id="<?php echo esc_attr( $followup->id ); ?>">
							<td>
								<strong><?php echo esc_html( trim( $followup->first_name . ' ' . $followup->last_name ) ?: $followup->email ); ?></strong>
								<br>
								<small><?php echo esc_html( $followup->email ); ?></small>
							</td>
							<td>
								<span class="bkx-followup-type bkx-followup-<?php echo esc_attr( $followup->followup_type ); ?>">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $followup->followup_type ) ) ); ?>
								</span>
							</td>
							<td>
								<span class="dashicons dashicons-<?php echo 'email' === $followup->channel ? 'email' : 'smartphone'; ?>"></span>
								<?php echo esc_html( ucfirst( $followup->channel ) ); ?>
							</td>
							<td>
								<?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $followup->scheduled_at ) ) ); ?>
							</td>
							<?php if ( 'sent' === $status ) : ?>
								<td>
									<?php echo esc_html( wp_date( 'M j, Y g:i A', strtotime( $followup->sent_at ) ) ); ?>
								</td>
							<?php endif; ?>
							<?php if ( 'pending' === $status ) : ?>
								<td>
									<button type="button" class="button button-small button-link-delete bkx-cancel-followup"
											data-followup-id="<?php echo esc_attr( $followup->id ); ?>">
										<?php esc_html_e( 'Cancel', 'bkx-crm' ); ?>
									</button>
								</td>
							<?php endif; ?>
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
							'current'   => $page,
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

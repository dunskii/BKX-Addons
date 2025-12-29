<?php
/**
 * Customers tab template.
 *
 * @package BookingX\CRM
 */

defined( 'ABSPATH' ) || exit;

$customer_service = new \BookingX\CRM\Services\CustomerService();
$tag_service = new \BookingX\CRM\Services\TagService();
$segment_service = new \BookingX\CRM\Services\SegmentService();

$search   = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
$tag      = isset( $_GET['tag'] ) ? absint( $_GET['tag'] ) : 0;
$segment  = isset( $_GET['segment'] ) ? absint( $_GET['segment'] ) : 0;
$status   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$page     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

$result = $customer_service->query( array(
	'search'  => $search,
	'tag'     => $tag,
	'segment' => $segment,
	'status'  => $status,
	'page'    => $page,
) );

$all_tags = $tag_service->get_all();
$all_segments = $segment_service->get_all();
?>

<div class="bkx-crm-customers">
	<!-- Toolbar -->
	<div class="bkx-toolbar">
		<div class="bkx-toolbar-left">
			<form method="get" class="bkx-search-form">
				<input type="hidden" name="post_type" value="bkx_booking">
				<input type="hidden" name="page" value="bkx-crm">
				<input type="hidden" name="tab" value="customers">

				<input type="search" name="search" class="bkx-search-input"
					   placeholder="<?php esc_attr_e( 'Search customers...', 'bkx-crm' ); ?>"
					   value="<?php echo esc_attr( $search ); ?>">

				<select name="tag">
					<option value=""><?php esc_html_e( 'All Tags', 'bkx-crm' ); ?></option>
					<?php foreach ( $all_tags as $t ) : ?>
						<option value="<?php echo esc_attr( $t->id ); ?>" <?php selected( $tag, $t->id ); ?>>
							<?php echo esc_html( $t->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<select name="segment">
					<option value=""><?php esc_html_e( 'All Segments', 'bkx-crm' ); ?></option>
					<?php foreach ( $all_segments as $s ) : ?>
						<option value="<?php echo esc_attr( $s->id ); ?>" <?php selected( $segment, $s->id ); ?>>
							<?php echo esc_html( $s->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<select name="status">
					<option value=""><?php esc_html_e( 'All Statuses', 'bkx-crm' ); ?></option>
					<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'bkx-crm' ); ?></option>
					<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'bkx-crm' ); ?></option>
					<option value="blocked" <?php selected( $status, 'blocked' ); ?>><?php esc_html_e( 'Blocked', 'bkx-crm' ); ?></option>
				</select>

				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-crm' ); ?></button>
			</form>
		</div>

		<div class="bkx-toolbar-right">
			<button type="button" class="button" id="bkx-export-customers">
				<?php esc_html_e( 'Export', 'bkx-crm' ); ?>
			</button>
			<button type="button" class="button button-primary" id="bkx-add-customer">
				<?php esc_html_e( 'Add Customer', 'bkx-crm' ); ?>
			</button>
		</div>
	</div>

	<!-- Stats Cards -->
	<div class="bkx-stats-row">
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $result['total'] ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Total Customers', 'bkx-crm' ); ?></span>
		</div>
		<?php
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_crm_customers';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$active_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$new_this_month = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE customer_since >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total_ltv = $wpdb->get_var( "SELECT SUM(lifetime_value) FROM {$table}" );
		?>
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $active_count ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Active Customers', 'bkx-crm' ); ?></span>
		</div>
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $new_this_month ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'New This Month', 'bkx-crm' ); ?></span>
		</div>
		<div class="bkx-stat-card">
			<span class="bkx-stat-value">$<?php echo esc_html( number_format( (float) $total_ltv, 2 ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Total Lifetime Value', 'bkx-crm' ); ?></span>
		</div>
	</div>

	<!-- Customers Table -->
	<div class="bkx-card">
		<?php if ( empty( $result['items'] ) ) : ?>
			<p class="bkx-no-items"><?php esc_html_e( 'No customers found.', 'bkx-crm' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 40px;"><input type="checkbox" id="bkx-select-all"></th>
						<th><?php esc_html_e( 'Customer', 'bkx-crm' ); ?></th>
						<th style="width: 200px;"><?php esc_html_e( 'Tags', 'bkx-crm' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Bookings', 'bkx-crm' ); ?></th>
						<th style="width: 120px;"><?php esc_html_e( 'Lifetime Value', 'bkx-crm' ); ?></th>
						<th style="width: 120px;"><?php esc_html_e( 'Last Booking', 'bkx-crm' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Status', 'bkx-crm' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Actions', 'bkx-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $result['items'] as $customer ) :
						$customer_tags = $tag_service->get_customer_tags( $customer->id );
						?>
						<tr data-customer-id="<?php echo esc_attr( $customer->id ); ?>">
							<td><input type="checkbox" class="bkx-customer-checkbox" value="<?php echo esc_attr( $customer->id ); ?>"></td>
							<td>
								<a href="#" class="bkx-view-customer" data-customer-id="<?php echo esc_attr( $customer->id ); ?>">
									<strong><?php echo esc_html( trim( $customer->first_name . ' ' . $customer->last_name ) ?: $customer->email ); ?></strong>
								</a>
								<br>
								<small><?php echo esc_html( $customer->email ); ?></small>
								<?php if ( $customer->phone ) : ?>
									<br><small><?php echo esc_html( $customer->phone ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<div class="bkx-crm-tags">
									<?php foreach ( $customer_tags as $t ) : ?>
										<span class="bkx-crm-tag" style="background-color: <?php echo esc_attr( $t->color ); ?>;">
											<?php echo esc_html( $t->name ); ?>
										</span>
									<?php endforeach; ?>
								</div>
							</td>
							<td><?php echo esc_html( $customer->total_bookings ); ?></td>
							<td>$<?php echo esc_html( number_format( (float) $customer->lifetime_value, 2 ) ); ?></td>
							<td>
								<?php if ( $customer->last_booking_date ) : ?>
									<?php echo esc_html( wp_date( 'M j, Y', strtotime( $customer->last_booking_date ) ) ); ?>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td>
								<span class="bkx-status bkx-status-<?php echo esc_attr( $customer->status ); ?>">
									<?php echo esc_html( ucfirst( $customer->status ) ); ?>
								</span>
							</td>
							<td>
								<button type="button" class="button button-small bkx-view-customer" data-customer-id="<?php echo esc_attr( $customer->id ); ?>">
									<?php esc_html_e( 'View', 'bkx-crm' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $result['total_pages'] > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $result['total_pages'],
							'current'   => $page,
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<!-- Customer Modal -->
<div id="bkx-customer-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content bkx-modal-large">
		<span class="bkx-modal-close">&times;</span>
		<div id="bkx-customer-modal-content">
			<?php esc_html_e( 'Loading...', 'bkx-crm' ); ?>
		</div>
	</div>
</div>

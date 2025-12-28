<?php
/**
 * HIPAA Audit Log Admin Page Template.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Get filters.
$event_type = isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : '';
$user_id    = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
$date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$date_to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
$paged      = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$per_page   = 50;

// Build query.
$table_name = $wpdb->prefix . 'bkx_hipaa_audit_log';
$where      = array( '1=1' );
$params     = array();

if ( $event_type ) {
	$where[]  = 'event_type = %s';
	$params[] = $event_type;
}

if ( $user_id ) {
	$where[]  = 'user_id = %d';
	$params[] = $user_id;
}

if ( $date_from ) {
	$where[]  = 'created_at >= %s';
	$params[] = $date_from . ' 00:00:00';
}

if ( $date_to ) {
	$where[]  = 'created_at <= %s';
	$params[] = $date_to . ' 23:59:59';
}

$where_sql = implode( ' AND ', $where );

// Get total count.
$count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
if ( ! empty( $params ) ) {
	$count_sql = $wpdb->prepare( $count_sql, $params );
}
$total_items = $wpdb->get_var( $count_sql );

// Get results.
$offset    = ( $paged - 1 ) * $per_page;
$query_sql = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
$params[]  = $per_page;
$params[]  = $offset;

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$logs = $wpdb->get_results( $wpdb->prepare( $query_sql, $params ) );

// Get unique event types for filter.
$event_types = $wpdb->get_col( "SELECT DISTINCT event_type FROM {$table_name} ORDER BY event_type" );

// Pagination.
$total_pages = ceil( $total_items / $per_page );
?>
<div class="wrap bkx-audit-log">
	<h1><?php esc_html_e( 'HIPAA Audit Log', 'bkx-healthcare-practice' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'This log tracks all access to patient health information for HIPAA compliance.', 'bkx-healthcare-practice' ); ?>
	</p>

	<form method="get" class="bkx-audit-filters">
		<input type="hidden" name="page" value="bkx-hipaa-audit">

		<select name="event_type">
			<option value=""><?php esc_html_e( 'All Event Types', 'bkx-healthcare-practice' ); ?></option>
			<?php foreach ( $event_types as $type ) : ?>
				<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $event_type, $type ); ?>>
					<?php echo esc_html( ucwords( str_replace( '_', ' ', $type ) ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<?php
		wp_dropdown_users( array(
			'name'              => 'user_id',
			'selected'          => $user_id,
			'show_option_all'   => __( 'All Users', 'bkx-healthcare-practice' ),
			'option_none_value' => '',
		) );
		?>

		<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>"
			   placeholder="<?php esc_attr_e( 'From Date', 'bkx-healthcare-practice' ); ?>">

		<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>"
			   placeholder="<?php esc_attr_e( 'To Date', 'bkx-healthcare-practice' ); ?>">

		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-healthcare-practice' ); ?></button>

		<?php if ( $event_type || $user_id || $date_from || $date_to ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hipaa-audit' ) ); ?>" class="button">
				<?php esc_html_e( 'Clear Filters', 'bkx-healthcare-practice' ); ?>
			</a>
		<?php endif; ?>

		<button type="button" class="button bkx-export-audit-log">
			<?php esc_html_e( 'Export Log', 'bkx-healthcare-practice' ); ?>
		</button>
	</form>

	<p>
		<?php
		printf(
			/* translators: %d: Number of records */
			esc_html__( 'Showing %d records', 'bkx-healthcare-practice' ),
			$total_items
		);
		?>
	</p>

	<table class="bkx-audit-table wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 160px;"><?php esc_html_e( 'Date/Time', 'bkx-healthcare-practice' ); ?></th>
				<th style="width: 150px;"><?php esc_html_e( 'Event Type', 'bkx-healthcare-practice' ); ?></th>
				<th style="width: 150px;"><?php esc_html_e( 'User', 'bkx-healthcare-practice' ); ?></th>
				<th style="width: 120px;"><?php esc_html_e( 'Object ID', 'bkx-healthcare-practice' ); ?></th>
				<th style="width: 130px;"><?php esc_html_e( 'IP Address', 'bkx-healthcare-practice' ); ?></th>
				<th><?php esc_html_e( 'Details', 'bkx-healthcare-practice' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No audit log entries found.', 'bkx-healthcare-practice' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : ?>
					<?php
					$user = $log->user_id ? get_userdata( $log->user_id ) : null;
					$event_class = '';

					if ( strpos( $log->event_type, 'access' ) !== false || strpos( $log->event_type, 'viewed' ) !== false ) {
						$event_class = 'access';
					} elseif ( strpos( $log->event_type, 'consent' ) !== false ) {
						$event_class = 'consent';
					}
					?>
					<tr>
						<td><?php echo esc_html( date_i18n( 'Y-m-d H:i:s', strtotime( $log->created_at ) ) ); ?></td>
						<td>
							<span class="bkx-event-type <?php echo esc_attr( $event_class ); ?>">
								<?php echo esc_html( ucwords( str_replace( '_', ' ', $log->event_type ) ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $user ) : ?>
								<?php echo esc_html( $user->display_name ); ?>
								<br>
								<small><?php echo esc_html( $user->user_email ); ?></small>
							<?php else : ?>
								<?php esc_html_e( 'System', 'bkx-healthcare-practice' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $log->object_id ) : ?>
								#<?php echo esc_html( $log->object_id ); ?>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $log->ip_address ); ?></td>
						<td>
							<?php
							$event_data = json_decode( $log->event_data, true );
							if ( $event_data && is_array( $event_data ) ) {
								$details = array();
								foreach ( $event_data as $key => $value ) {
									if ( is_array( $value ) ) {
										$value = wp_json_encode( $value );
									}
									$details[] = esc_html( $key ) . ': ' . esc_html( $value );
								}
								echo implode( ', ', $details );
							} else {
								echo '&mdash;';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				$page_links = paginate_links( array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'total'     => $total_pages,
					'current'   => $paged,
				) );

				echo wp_kses_post( $page_links );
				?>
			</div>
		</div>
	<?php endif; ?>
</div>

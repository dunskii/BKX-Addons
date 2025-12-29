<?php
/**
 * Live Chat History Template.
 *
 * @package BookingX\LiveChat
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table = $wpdb->prefix . 'bkx_livechat_chats';

// Pagination.
$per_page     = 20;
$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$offset       = ( $current_page - 1 ) * $per_page;

// Filters.
$status_filter   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$operator_filter = isset( $_GET['operator'] ) ? absint( $_GET['operator'] ) : 0;

$where = "WHERE status = 'closed'";
if ( ! empty( $status_filter ) ) {
	$where = $wpdb->prepare( 'WHERE status = %s', $status_filter );
}
if ( $operator_filter ) {
	$where .= $wpdb->prepare( ' AND operator_id = %d', $operator_filter );
}

// Get chats.
$chats = $wpdb->get_results( // phpcs:ignore
	"SELECT c.*,
		(SELECT COUNT(*) FROM {$wpdb->prefix}bkx_livechat_messages m WHERE m.chat_id = c.id) as message_count
	FROM {$table} c
	{$where}
	ORDER BY c.started_at DESC
	LIMIT {$per_page} OFFSET {$offset}"
);

$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" ); // phpcs:ignore
$pages = ceil( $total / $per_page );
?>

<div class="wrap bkx-livechat-history">
	<h1><?php esc_html_e( 'Chat History', 'bkx-live-chat' ); ?></h1>

	<div class="bkx-filters">
		<form method="get">
			<input type="hidden" name="page" value="bkx-livechat-history">
			<select name="status">
				<option value=""><?php esc_html_e( 'All Statuses', 'bkx-live-chat' ); ?></option>
				<option value="closed" <?php selected( $status_filter, 'closed' ); ?>><?php esc_html_e( 'Closed', 'bkx-live-chat' ); ?></option>
				<option value="active" <?php selected( $status_filter, 'active' ); ?>><?php esc_html_e( 'Active', 'bkx-live-chat' ); ?></option>
				<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'bkx-live-chat' ); ?></option>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-live-chat' ); ?></button>
		</form>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Visitor', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Operator', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Messages', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Duration', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Rating', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Date', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'bkx-live-chat' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $chats ) ) : ?>
				<tr>
					<td colspan="8"><?php esc_html_e( 'No chat history found.', 'bkx-live-chat' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $chats as $chat ) : ?>
					<?php
					$operator_name = '-';
					if ( $chat->operator_id ) {
						$operator = get_user_by( 'id', $chat->operator_id );
						$operator_name = $operator ? $operator->display_name : '-';
					}

					$duration = '-';
					if ( $chat->ended_at ) {
						$seconds = strtotime( $chat->ended_at ) - strtotime( $chat->started_at );
						$minutes = floor( $seconds / 60 );
						$secs    = $seconds % 60;
						$duration = sprintf( '%d:%02d', $minutes, $secs );
					}
					?>
					<tr>
						<td>#<?php echo esc_html( $chat->id ); ?></td>
						<td>
							<strong><?php echo esc_html( $chat->visitor_name ?: __( 'Anonymous', 'bkx-live-chat' ) ); ?></strong>
							<?php if ( $chat->visitor_email ) : ?>
								<br><small><?php echo esc_html( $chat->visitor_email ); ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $operator_name ); ?></td>
						<td><?php echo esc_html( $chat->message_count ); ?></td>
						<td><?php echo esc_html( $duration ); ?></td>
						<td>
							<?php if ( $chat->rating ) : ?>
								<span class="bkx-rating"><?php echo esc_html( str_repeat( '★', $chat->rating ) . str_repeat( '☆', 5 - $chat->rating ) ); ?></span>
							<?php else : ?>
								-
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $chat->started_at ) ) ); ?></td>
						<td>
							<a href="#" class="bkx-view-transcript" data-id="<?php echo esc_attr( $chat->id ); ?>">
								<?php esc_html_e( 'View', 'bkx-live-chat' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $current_page,
							'total'   => $pages,
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>

<!-- Transcript Modal -->
<div id="bkx-transcript-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<span class="bkx-modal-close">&times;</span>
		<h3><?php esc_html_e( 'Chat Transcript', 'bkx-live-chat' ); ?></h3>
		<div id="bkx-transcript-content"></div>
	</div>
</div>

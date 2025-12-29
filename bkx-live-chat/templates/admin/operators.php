<?php
/**
 * Operators Template.
 *
 * @package BookingX\LiveChat
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;
$table     = $wpdb->prefix . 'bkx_livechat_operators';
$operators = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY display_name ASC" ); // phpcs:ignore

// Get users who can be operators.
$users = get_users( array( 'role__in' => array( 'administrator', 'editor' ) ) );
?>

<div class="wrap bkx-livechat-operators">
	<h1><?php esc_html_e( 'Operators', 'bkx-live-chat' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Operators are users who can respond to live chat conversations.', 'bkx-live-chat' ); ?>
	</p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Operator', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Status', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Active Chats', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Total Chats', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Avg Rating', 'bkx-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Max Chats', 'bkx-live-chat' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $operators ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No operators configured. Administrators will automatically become operators when they go online.', 'bkx-live-chat' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $operators as $operator ) : ?>
					<?php
					$user = get_user_by( 'id', $operator->user_id );
					if ( ! $user ) {
						continue;
					}

					$status_class = 'bkx-status-' . $operator->status;
					?>
					<tr>
						<td>
							<?php echo get_avatar( $operator->user_id, 32 ); ?>
							<strong><?php echo esc_html( $operator->display_name ?: $user->display_name ); ?></strong>
							<br><small><?php echo esc_html( $user->user_email ); ?></small>
						</td>
						<td>
							<span class="bkx-status-badge <?php echo esc_attr( $status_class ); ?>">
								<?php echo esc_html( ucfirst( $operator->status ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $operator->active_chats ); ?></td>
						<td><?php echo esc_html( $operator->total_chats ); ?></td>
						<td>
							<?php if ( $operator->avg_rating ) : ?>
								<?php echo esc_html( number_format( $operator->avg_rating, 1 ) ); ?> / 5
							<?php else : ?>
								-
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $operator->max_chats ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Add Operator', 'bkx-live-chat' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Select a user to add as an operator.', 'bkx-live-chat' ); ?>
	</p>

	<form id="bkx-add-operator-form" method="post">
		<?php wp_nonce_field( 'bkx_add_operator', 'bkx_nonce' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'User', 'bkx-live-chat' ); ?></th>
				<td>
					<select name="user_id" required>
						<option value=""><?php esc_html_e( 'Select a user...', 'bkx-live-chat' ); ?></option>
						<?php foreach ( $users as $user ) : ?>
							<?php
							// Check if already an operator.
							$is_operator = false;
							foreach ( $operators as $op ) {
								if ( $op->user_id === $user->ID ) {
									$is_operator = true;
									break;
								}
							}
							if ( $is_operator ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( $user->ID ); ?>">
								<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Max Concurrent Chats', 'bkx-live-chat' ); ?></th>
				<td>
					<input type="number" name="max_chats" value="5" min="1" max="20" style="width: 80px;">
				</td>
			</tr>
		</table>
		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Operator', 'bkx-live-chat' ); ?></button>
		</p>
	</form>
</div>

<style>
.bkx-livechat-operators td img {
	vertical-align: middle;
	margin-right: 10px;
	border-radius: 50%;
}
.bkx-status-badge {
	display: inline-block;
	padding: 3px 10px;
	border-radius: 12px;
	font-size: 11px;
	font-weight: 600;
}
.bkx-status-online {
	background: #d4edda;
	color: #155724;
}
.bkx-status-away {
	background: #fff3cd;
	color: #856404;
}
.bkx-status-busy {
	background: #f8d7da;
	color: #721c24;
}
.bkx-status-offline {
	background: #e2e3e5;
	color: #383d41;
}
</style>

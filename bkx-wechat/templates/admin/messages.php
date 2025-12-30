<?php
/**
 * Messages template.
 *
 * @package BookingX\WeChat
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WeChat\WeChatAddon::get_instance();
$settings = $addon->get_settings();
?>
<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Message Templates', 'bkx-wechat' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure template message IDs from your WeChat Official Account.', 'bkx-wechat' ); ?>
	</p>

	<table class="form-table">
		<?php
		$template_messages = $settings['template_messages'] ?? array();
		$templates         = array(
			'booking_confirmed' => array(
				'label'       => __( 'Booking Confirmed', 'bkx-wechat' ),
				'description' => __( 'Sent when a booking is created.', 'bkx-wechat' ),
			),
			'booking_reminder'  => array(
				'label'       => __( 'Booking Reminder', 'bkx-wechat' ),
				'description' => __( 'Sent 1 hour before the appointment.', 'bkx-wechat' ),
			),
			'booking_cancelled' => array(
				'label'       => __( 'Booking Cancelled', 'bkx-wechat' ),
				'description' => __( 'Sent when a booking is cancelled.', 'bkx-wechat' ),
			),
		);
		foreach ( $templates as $key => $template ) :
			?>
			<tr>
				<th scope="row">
					<label for="template_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $template['label'] ); ?></label>
				</th>
				<td>
					<input type="text" name="template_messages[<?php echo esc_attr( $key ); ?>]"
						   id="template_<?php echo esc_attr( $key ); ?>" class="regular-text"
						   value="<?php echo esc_attr( $template_messages[ $key ] ?? '' ); ?>"
						   placeholder="<?php esc_attr_e( 'Template ID', 'bkx-wechat' ); ?>">
					<p class="description"><?php echo esc_html( $template['description'] ); ?></p>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Auto Reply Rules', 'bkx-wechat' ); ?></h2>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="auto_reply_enabled"><?php esc_html_e( 'Enable Auto Reply', 'bkx-wechat' ); ?></label>
			</th>
			<td>
				<label class="bkx-toggle">
					<input type="checkbox" name="auto_reply_enabled" id="auto_reply_enabled" value="1"
						   <?php checked( ! empty( $settings['auto_reply_enabled'] ) ); ?>>
					<span class="bkx-toggle-slider"></span>
				</label>
			</td>
		</tr>
	</table>

	<div class="bkx-auto-reply-builder">
		<table class="wp-list-table widefat striped" id="bkx-auto-reply-rules">
			<thead>
				<tr>
					<th style="width: 150px;"><?php esc_html_e( 'Keyword', 'bkx-wechat' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Type', 'bkx-wechat' ); ?></th>
					<th><?php esc_html_e( 'Content', 'bkx-wechat' ); ?></th>
					<th style="width: 120px;"><?php esc_html_e( 'Actions', 'bkx-wechat' ); ?></th>
				</tr>
			</thead>
			<tbody id="auto-reply-rules-body">
				<?php
				$auto_reply_rules = $settings['auto_reply_rules'] ?? array();
				if ( empty( $auto_reply_rules ) ) :
					?>
					<tr class="no-items">
						<td colspan="4"><?php esc_html_e( 'No rules configured. Click "Add Rule" to create one.', 'bkx-wechat' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $auto_reply_rules as $index => $rule ) : ?>
						<tr data-index="<?php echo esc_attr( $index ); ?>">
							<td><code><?php echo esc_html( $rule['keyword'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $rule['type'] ?? 'text' ); ?></td>
							<td><?php echo esc_html( wp_trim_words( $rule['content'] ?? '', 20 ) ); ?></td>
							<td>
								<button type="button" class="button button-small bkx-edit-rule" data-index="<?php echo esc_attr( $index ); ?>">
									<?php esc_html_e( 'Edit', 'bkx-wechat' ); ?>
								</button>
								<button type="button" class="button button-small bkx-delete-rule" data-index="<?php echo esc_attr( $index ); ?>">
									<?php esc_html_e( 'Delete', 'bkx-wechat' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<p>
			<button type="button" class="button" id="bkx-add-rule">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add Rule', 'bkx-wechat' ); ?>
			</button>
		</p>
	</div>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Default Responses', 'bkx-wechat' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'These responses are built-in and handle common booking-related keywords.', 'bkx-wechat' ); ?>
	</p>

	<table class="wp-list-table widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Keywords', 'bkx-wechat' ); ?></th>
				<th><?php esc_html_e( 'Action', 'bkx-wechat' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><code>预约</code>, <code>book</code>, <code>订</code>, <code>约</code></td>
				<td><?php esc_html_e( 'Shows booking link', 'bkx-wechat' ); ?></td>
			</tr>
			<tr>
				<td><code>取消</code>, <code>cancel</code></td>
				<td><?php esc_html_e( 'Shows cancellation instructions', 'bkx-wechat' ); ?></td>
			</tr>
			<tr>
				<td><code>查询</code>, <code>query</code>, <code>我的</code>, <code>订单</code></td>
				<td><?php esc_html_e( 'Shows user bookings', 'bkx-wechat' ); ?></td>
			</tr>
			<tr>
				<td><code>帮助</code>, <code>help</code>, <code>?</code></td>
				<td><?php esc_html_e( 'Shows help message', 'bkx-wechat' ); ?></td>
			</tr>
		</tbody>
	</table>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Message Log', 'bkx-wechat' ); ?></h2>

	<?php
	$messages = get_option( 'bkx_wechat_message_log', array() );
	$messages = array_slice( array_reverse( $messages ), 0, 20 );
	?>

	<?php if ( empty( $messages ) ) : ?>
		<p><?php esc_html_e( 'No messages received yet.', 'bkx-wechat' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'bkx-wechat' ); ?></th>
					<th><?php esc_html_e( 'Type', 'bkx-wechat' ); ?></th>
					<th><?php esc_html_e( 'Content', 'bkx-wechat' ); ?></th>
					<th><?php esc_html_e( 'User', 'bkx-wechat' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $messages as $msg ) : ?>
					<tr>
						<td><?php echo esc_html( $msg['time'] ?? '' ); ?></td>
						<td><?php echo esc_html( $msg['type'] ?? '' ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $msg['content'] ?? '', 15 ) ); ?></td>
						<td><code><?php echo esc_html( substr( $msg['openid'] ?? '', 0, 10 ) . '...' ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<!-- Rule Editor Modal -->
<div id="bkx-rule-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Edit Auto Reply Rule', 'bkx-wechat' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<form id="bkx-rule-form">
				<input type="hidden" name="rule_index" id="rule_index" value="-1">

				<p>
					<label for="rule_keyword"><?php esc_html_e( 'Keyword', 'bkx-wechat' ); ?></label>
					<input type="text" name="keyword" id="rule_keyword" class="large-text" required>
				</p>

				<p>
					<label for="rule_type"><?php esc_html_e( 'Reply Type', 'bkx-wechat' ); ?></label>
					<select name="type" id="rule_type">
						<option value="text"><?php esc_html_e( 'Text', 'bkx-wechat' ); ?></option>
						<option value="image"><?php esc_html_e( 'Image', 'bkx-wechat' ); ?></option>
						<option value="news"><?php esc_html_e( 'News Article', 'bkx-wechat' ); ?></option>
					</select>
				</p>

				<p>
					<label for="rule_content"><?php esc_html_e( 'Content', 'bkx-wechat' ); ?></label>
					<textarea name="content" id="rule_content" class="large-text" rows="4" required></textarea>
				</p>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Rule', 'bkx-wechat' ); ?>
					</button>
				</p>
			</form>
		</div>
	</div>
</div>

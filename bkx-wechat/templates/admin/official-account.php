<?php
/**
 * Official Account settings template.
 *
 * @package BookingX\WeChat
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WeChat\WeChatAddon::get_instance();
$settings = $addon->get_settings();
?>
<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Official Account Menu', 'bkx-wechat' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure the custom menu for your WeChat Official Account.', 'bkx-wechat' ); ?>
	</p>

	<div id="bkx-menu-builder" class="bkx-menu-builder">
		<div class="bkx-menu-preview">
			<div class="bkx-phone-frame">
				<div class="bkx-menu-bar">
					<?php
					$menu_config = $settings['menu_config'] ?? array();
					for ( $i = 0; $i < 3; $i++ ) :
						$menu_item = $menu_config[ $i ] ?? array();
						?>
						<div class="bkx-menu-item" data-index="<?php echo esc_attr( $i ); ?>">
							<span class="bkx-menu-name"><?php echo esc_html( $menu_item['name'] ?? __( 'Menu', 'bkx-wechat' ) . ' ' . ( $i + 1 ) ); ?></span>
							<?php if ( ! empty( $menu_item['sub_button'] ) ) : ?>
								<div class="bkx-submenu">
									<?php foreach ( $menu_item['sub_button'] as $sub ) : ?>
										<div class="bkx-submenu-item"><?php echo esc_html( $sub['name'] ?? '' ); ?></div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endfor; ?>
				</div>
			</div>
		</div>

		<div class="bkx-menu-editor">
			<h3><?php esc_html_e( 'Edit Menu', 'bkx-wechat' ); ?></h3>
			<div id="bkx-menu-form">
				<p><?php esc_html_e( 'Click a menu item to edit.', 'bkx-wechat' ); ?></p>
			</div>
		</div>
	</div>

	<p>
		<button type="button" class="button button-primary" id="bkx-sync-menu">
			<?php esc_html_e( 'Sync Menu to WeChat', 'bkx-wechat' ); ?>
		</button>
		<span id="bkx-menu-status"></span>
	</p>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Template Messages', 'bkx-wechat' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure template message IDs for booking notifications.', 'bkx-wechat' ); ?>
	</p>

	<table class="form-table">
		<?php
		$template_messages = $settings['template_messages'] ?? array();
		$templates         = array(
			'booking_confirmed' => __( 'Booking Confirmed', 'bkx-wechat' ),
			'booking_reminder'  => __( 'Booking Reminder', 'bkx-wechat' ),
			'booking_cancelled' => __( 'Booking Cancelled', 'bkx-wechat' ),
		);
		foreach ( $templates as $key => $label ) :
			?>
			<tr>
				<th scope="row">
					<label for="template_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
				</th>
				<td>
					<input type="text" name="template_messages[<?php echo esc_attr( $key ); ?>]"
						   id="template_<?php echo esc_attr( $key ); ?>" class="regular-text"
						   value="<?php echo esc_attr( $template_messages[ $key ] ?? '' ); ?>"
						   placeholder="<?php esc_attr_e( 'Template ID', 'bkx-wechat' ); ?>">
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Auto Reply', 'bkx-wechat' ); ?></h2>

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

	<h3><?php esc_html_e( 'Keyword Rules', 'bkx-wechat' ); ?></h3>

	<table class="wp-list-table widefat" id="bkx-auto-reply-rules">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Keyword', 'bkx-wechat' ); ?></th>
				<th><?php esc_html_e( 'Reply Type', 'bkx-wechat' ); ?></th>
				<th><?php esc_html_e( 'Content', 'bkx-wechat' ); ?></th>
				<th width="80"><?php esc_html_e( 'Actions', 'bkx-wechat' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$auto_reply_rules = $settings['auto_reply_rules'] ?? array();
			if ( empty( $auto_reply_rules ) ) :
				?>
				<tr class="no-items">
					<td colspan="4"><?php esc_html_e( 'No rules configured.', 'bkx-wechat' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $auto_reply_rules as $index => $rule ) : ?>
					<tr>
						<td><?php echo esc_html( $rule['keyword'] ?? '' ); ?></td>
						<td><?php echo esc_html( $rule['type'] ?? 'text' ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $rule['content'] ?? '', 10 ) ); ?></td>
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
			<?php esc_html_e( 'Add Rule', 'bkx-wechat' ); ?>
		</button>
	</p>
</div>

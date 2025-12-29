<?php
/**
 * Conversations Tab Template.
 *
 * @package BookingX\WhatsAppBusiness
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="bkx-conversations-container">
	<div class="bkx-conversations-sidebar">
		<div class="bkx-conversations-header">
			<input type="text" id="bkx-conversation-search" placeholder="<?php esc_attr_e( 'Search conversations...', 'bkx-whatsapp-business' ); ?>">
			<select id="bkx-conversation-status-filter">
				<option value="active"><?php esc_html_e( 'Active', 'bkx-whatsapp-business' ); ?></option>
				<option value="archived"><?php esc_html_e( 'Archived', 'bkx-whatsapp-business' ); ?></option>
				<option value="all"><?php esc_html_e( 'All', 'bkx-whatsapp-business' ); ?></option>
			</select>
		</div>
		<div class="bkx-conversations-list" id="bkx-conversations-list">
			<p class="bkx-loading"><?php esc_html_e( 'Loading conversations...', 'bkx-whatsapp-business' ); ?></p>
		</div>
	</div>

	<div class="bkx-chat-container" id="bkx-chat-container">
		<div class="bkx-chat-placeholder">
			<span class="dashicons dashicons-format-chat"></span>
			<p><?php esc_html_e( 'Select a conversation to view messages', 'bkx-whatsapp-business' ); ?></p>
		</div>

		<div class="bkx-chat-view" id="bkx-chat-view" style="display: none;">
			<div class="bkx-chat-header">
				<div class="bkx-chat-contact">
					<strong id="bkx-chat-contact-name"></strong>
					<span id="bkx-chat-contact-phone"></span>
				</div>
				<div class="bkx-chat-actions">
					<button type="button" class="button" id="bkx-archive-conversation">
						<?php esc_html_e( 'Archive', 'bkx-whatsapp-business' ); ?>
					</button>
				</div>
			</div>

			<div class="bkx-chat-messages" id="bkx-chat-messages"></div>

			<div class="bkx-chat-input">
				<div class="bkx-quick-replies-trigger">
					<button type="button" id="bkx-quick-replies-btn" title="<?php esc_attr_e( 'Quick Replies', 'bkx-whatsapp-business' ); ?>">
						<span class="dashicons dashicons-format-quote"></span>
					</button>
					<div class="bkx-quick-replies-dropdown" id="bkx-quick-replies-dropdown" style="display: none;"></div>
				</div>
				<textarea id="bkx-message-input" placeholder="<?php esc_attr_e( 'Type a message...', 'bkx-whatsapp-business' ); ?>" rows="1"></textarea>
				<button type="button" id="bkx-send-message" class="button button-primary">
					<span class="dashicons dashicons-arrow-right-alt"></span>
				</button>
			</div>
		</div>
	</div>
</div>

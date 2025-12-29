<?php
/**
 * Live Chat Dashboard Template.
 *
 * @package BookingX\LiveChat
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap bkx-livechat-dashboard">
	<h1><?php esc_html_e( 'Live Chat Dashboard', 'bkx-live-chat' ); ?></h1>

	<div class="bkx-operator-status-bar">
		<span><?php esc_html_e( 'Your Status:', 'bkx-live-chat' ); ?></span>
		<select id="bkx-operator-status">
			<option value="online"><?php esc_html_e( 'Online', 'bkx-live-chat' ); ?></option>
			<option value="away"><?php esc_html_e( 'Away', 'bkx-live-chat' ); ?></option>
			<option value="busy"><?php esc_html_e( 'Busy', 'bkx-live-chat' ); ?></option>
			<option value="offline"><?php esc_html_e( 'Offline', 'bkx-live-chat' ); ?></option>
		</select>
	</div>

	<div class="bkx-dashboard-layout">
		<!-- Chat list -->
		<div class="bkx-chat-list-panel">
			<div class="bkx-panel-header">
				<h2><?php esc_html_e( 'Active Chats', 'bkx-live-chat' ); ?></h2>
				<div class="bkx-chat-filters">
					<button type="button" class="bkx-filter-btn active" data-status="all"><?php esc_html_e( 'All', 'bkx-live-chat' ); ?></button>
					<button type="button" class="bkx-filter-btn" data-status="pending"><?php esc_html_e( 'Pending', 'bkx-live-chat' ); ?></button>
					<button type="button" class="bkx-filter-btn" data-status="active"><?php esc_html_e( 'Active', 'bkx-live-chat' ); ?></button>
				</div>
			</div>
			<div class="bkx-chat-list" id="bkx-chat-list">
				<p class="bkx-loading"><?php esc_html_e( 'Loading chats...', 'bkx-live-chat' ); ?></p>
			</div>
		</div>

		<!-- Chat view -->
		<div class="bkx-chat-view-panel">
			<div class="bkx-no-chat-selected" id="bkx-no-chat-selected">
				<span class="dashicons dashicons-format-chat"></span>
				<p><?php esc_html_e( 'Select a chat to view messages', 'bkx-live-chat' ); ?></p>
			</div>

			<div class="bkx-active-chat" id="bkx-active-chat" style="display: none;">
				<div class="bkx-chat-header">
					<div class="bkx-visitor-info">
						<strong id="bkx-visitor-name"></strong>
						<span id="bkx-visitor-email"></span>
					</div>
					<div class="bkx-chat-actions">
						<button type="button" class="button" id="bkx-accept-chat" style="display: none;">
							<?php esc_html_e( 'Accept', 'bkx-live-chat' ); ?>
						</button>
						<button type="button" class="button" id="bkx-transfer-chat">
							<?php esc_html_e( 'Transfer', 'bkx-live-chat' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="bkx-close-chat">
							<?php esc_html_e( 'Close', 'bkx-live-chat' ); ?>
						</button>
					</div>
				</div>

				<div class="bkx-chat-messages" id="bkx-operator-messages"></div>

				<div class="bkx-chat-input-area">
					<div class="bkx-canned-responses">
						<button type="button" id="bkx-canned-btn" title="<?php esc_attr_e( 'Canned Responses', 'bkx-live-chat' ); ?>">
							<span class="dashicons dashicons-format-quote"></span>
						</button>
						<div class="bkx-canned-dropdown" id="bkx-canned-dropdown" style="display: none;"></div>
					</div>
					<textarea id="bkx-operator-message" placeholder="<?php esc_attr_e( 'Type a message...', 'bkx-live-chat' ); ?>" rows="2"></textarea>
					<button type="button" id="bkx-send-operator-message" class="button button-primary">
						<?php esc_html_e( 'Send', 'bkx-live-chat' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Visitor info -->
		<div class="bkx-visitor-panel">
			<div class="bkx-panel-header">
				<h2><?php esc_html_e( 'Visitor Info', 'bkx-live-chat' ); ?></h2>
			</div>
			<div class="bkx-visitor-details" id="bkx-visitor-details">
				<p class="bkx-no-selection"><?php esc_html_e( 'Select a chat to view visitor info', 'bkx-live-chat' ); ?></p>
			</div>

			<div class="bkx-panel-header">
				<h2><?php esc_html_e( 'Active Visitors', 'bkx-live-chat' ); ?></h2>
			</div>
			<div class="bkx-active-visitors" id="bkx-active-visitors">
				<p class="bkx-loading"><?php esc_html_e( 'Loading...', 'bkx-live-chat' ); ?></p>
			</div>
		</div>
	</div>
</div>

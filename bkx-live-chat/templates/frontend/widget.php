<?php
/**
 * Live Chat Widget Template.
 *
 * @package BookingX\LiveChat
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_live_chat_settings', array() );
$position = $settings['widget_position'] ?? 'bottom-right';
$color    = $settings['widget_color'] ?? '#2196f3';
?>

<div id="bkx-livechat-widget" class="bkx-livechat-widget <?php echo esc_attr( $position ); ?>" style="--bkx-chat-color: <?php echo esc_attr( $color ); ?>;">
	<!-- Chat button -->
	<button type="button" class="bkx-chat-button" id="bkx-chat-button" aria-label="<?php esc_attr_e( 'Open chat', 'bkx-live-chat' ); ?>">
		<span class="bkx-chat-icon">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
				<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/>
				<path d="M7 9h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2z"/>
			</svg>
		</span>
		<span class="bkx-chat-close">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
				<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
			</svg>
		</span>
		<span class="bkx-unread-badge" id="bkx-unread-badge" style="display: none;">0</span>
	</button>

	<!-- Chat window -->
	<div class="bkx-chat-window" id="bkx-chat-window" style="display: none;">
		<!-- Header -->
		<div class="bkx-chat-header">
			<div class="bkx-chat-header-info">
				<h4><?php echo esc_html( $settings['widget_title'] ?? __( 'Chat with us', 'bkx-live-chat' ) ); ?></h4>
				<span class="bkx-online-status" id="bkx-online-status">
					<span class="bkx-status-dot"></span>
					<span class="bkx-status-text"><?php esc_html_e( 'Online', 'bkx-live-chat' ); ?></span>
				</span>
			</div>
			<button type="button" class="bkx-chat-minimize" id="bkx-chat-minimize" aria-label="<?php esc_attr_e( 'Minimize', 'bkx-live-chat' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
					<path d="M19 13H5v-2h14v2z"/>
				</svg>
			</button>
		</div>

		<!-- Pre-chat form -->
		<div class="bkx-prechat-form" id="bkx-prechat-form">
			<p class="bkx-prechat-message">
				<?php echo esc_html( $settings['welcome_message'] ?? __( 'Hello! How can we help you today?', 'bkx-live-chat' ) ); ?>
			</p>
			<form id="bkx-start-chat-form">
				<?php if ( ! empty( $settings['require_name'] ) ) : ?>
					<div class="bkx-form-field">
						<input type="text" id="bkx-visitor-name" name="name" placeholder="<?php esc_attr_e( 'Your name', 'bkx-live-chat' ); ?>" required>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $settings['require_email'] ) ) : ?>
					<div class="bkx-form-field">
						<input type="email" id="bkx-visitor-email" name="email" placeholder="<?php esc_attr_e( 'Your email', 'bkx-live-chat' ); ?>" required>
					</div>
				<?php endif; ?>
				<div class="bkx-form-field">
					<textarea id="bkx-initial-message" name="message" placeholder="<?php esc_attr_e( 'Type your message...', 'bkx-live-chat' ); ?>" rows="3" required></textarea>
				</div>
				<button type="submit" class="bkx-start-chat-btn">
					<?php esc_html_e( 'Start Chat', 'bkx-live-chat' ); ?>
				</button>
			</form>
		</div>

		<!-- Chat messages -->
		<div class="bkx-chat-messages" id="bkx-chat-messages" style="display: none;"></div>

		<!-- Typing indicator -->
		<div class="bkx-typing-indicator" id="bkx-typing-indicator" style="display: none;">
			<span></span>
			<span></span>
			<span></span>
		</div>

		<!-- Chat input -->
		<div class="bkx-chat-input" id="bkx-chat-input" style="display: none;">
			<form id="bkx-send-message-form">
				<textarea id="bkx-message-input" placeholder="<?php esc_attr_e( 'Type a message...', 'bkx-live-chat' ); ?>" rows="1"></textarea>
				<button type="submit" aria-label="<?php esc_attr_e( 'Send', 'bkx-live-chat' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
						<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
					</svg>
				</button>
			</form>
		</div>

		<!-- Rating form -->
		<div class="bkx-rating-form" id="bkx-rating-form" style="display: none;">
			<h4><?php esc_html_e( 'How was your experience?', 'bkx-live-chat' ); ?></h4>
			<div class="bkx-rating-stars">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<button type="button" class="bkx-rating-star" data-rating="<?php echo esc_attr( $i ); ?>">★</button>
				<?php endfor; ?>
			</div>
			<textarea id="bkx-rating-feedback" placeholder="<?php esc_attr_e( 'Any additional feedback? (optional)', 'bkx-live-chat' ); ?>" rows="2"></textarea>
			<button type="button" id="bkx-submit-rating" class="bkx-submit-rating-btn">
				<?php esc_html_e( 'Submit', 'bkx-live-chat' ); ?>
			</button>
			<button type="button" id="bkx-skip-rating" class="bkx-skip-rating-btn">
				<?php esc_html_e( 'Skip', 'bkx-live-chat' ); ?>
			</button>
		</div>

		<!-- Offline message -->
		<div class="bkx-offline-message" id="bkx-offline-message" style="display: none;">
			<p><?php echo esc_html( $settings['offline_message'] ?? __( 'We are currently offline. Please leave a message.', 'bkx-live-chat' ) ); ?></p>
		</div>

		<!-- Powered by -->
		<div class="bkx-powered-by">
			<a href="https://bookingx.com" target="_blank" rel="noopener">
				<?php esc_html_e( 'Powered by BookingX', 'bkx-live-chat' ); ?>
			</a>
		</div>
	</div>
</div>

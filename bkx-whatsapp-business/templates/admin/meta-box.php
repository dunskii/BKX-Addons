<?php
/**
 * WhatsApp Meta Box Template.
 *
 * @package BookingX\WhatsAppBusiness
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="bkx-whatsapp-metabox">
	<p>
		<strong><?php esc_html_e( 'Phone:', 'bkx-whatsapp-business' ); ?></strong>
		<?php echo esc_html( $phone ); ?>
	</p>

	<?php if ( ! empty( $messages ) ) : ?>
		<div class="bkx-metabox-messages">
			<?php foreach ( array_reverse( $messages ) as $message ) : ?>
				<div class="bkx-metabox-message <?php echo esc_attr( $message->direction ); ?>">
					<span class="message-content"><?php echo esc_html( wp_trim_words( $message->content, 10 ) ); ?></span>
					<span class="message-meta">
						<?php echo esc_html( wp_date( 'M j, g:i a', strtotime( $message->created_at ) ) ); ?>
						<?php if ( 'outbound' === $message->direction ) : ?>
							<span class="message-status <?php echo esc_attr( $message->status ); ?>">
								<?php echo esc_html( ucfirst( $message->status ) ); ?>
							</span>
						<?php endif; ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="bkx-no-messages"><?php esc_html_e( 'No messages yet.', 'bkx-whatsapp-business' ); ?></p>
	<?php endif; ?>

	<div class="bkx-metabox-send">
		<textarea id="bkx-metabox-message" rows="2" placeholder="<?php esc_attr_e( 'Type a message...', 'bkx-whatsapp-business' ); ?>"></textarea>
		<button type="button" class="button" id="bkx-metabox-send" data-phone="<?php echo esc_attr( $phone ); ?>" data-booking="<?php echo esc_attr( $post->ID ); ?>">
			<?php esc_html_e( 'Send', 'bkx-whatsapp-business' ); ?>
		</button>
	</div>

	<p>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-whatsapp' ) ); ?>" class="button button-secondary">
			<?php esc_html_e( 'View Full Conversation', 'bkx-whatsapp-business' ); ?>
		</a>
	</p>
</div>

<style>
.bkx-whatsapp-metabox .bkx-metabox-messages {
	max-height: 200px;
	overflow-y: auto;
	margin: 10px 0;
	padding: 10px;
	background: #f6f7f7;
	border-radius: 4px;
}
.bkx-whatsapp-metabox .bkx-metabox-message {
	margin-bottom: 8px;
	padding: 8px;
	background: white;
	border-radius: 4px;
	font-size: 12px;
}
.bkx-whatsapp-metabox .bkx-metabox-message.inbound {
	border-left: 3px solid #2196f3;
}
.bkx-whatsapp-metabox .bkx-metabox-message.outbound {
	border-left: 3px solid #4caf50;
}
.bkx-whatsapp-metabox .message-meta {
	display: block;
	margin-top: 4px;
	color: #666;
	font-size: 11px;
}
.bkx-whatsapp-metabox .bkx-metabox-send {
	display: flex;
	gap: 5px;
	margin: 10px 0;
}
.bkx-whatsapp-metabox .bkx-metabox-send textarea {
	flex: 1;
}
</style>

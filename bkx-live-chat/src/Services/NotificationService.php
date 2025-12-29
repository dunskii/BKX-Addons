<?php
/**
 * Notification Service.
 *
 * @package BookingX\LiveChat\Services
 * @since   1.0.0
 */

namespace BookingX\LiveChat\Services;

defined( 'ABSPATH' ) || exit;

/**
 * NotificationService class.
 *
 * Handles chat notifications.
 *
 * @since 1.0.0
 */
class NotificationService {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Send chat transcript.
	 *
	 * @since 1.0.0
	 *
	 * @param int $chat_id Chat ID.
	 */
	public function send_transcript( $chat_id ) {
		global $wpdb;

		$chats_table    = $wpdb->prefix . 'bkx_livechat_chats';
		$messages_table = $wpdb->prefix . 'bkx_livechat_messages';

		$chat = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$chats_table,
				$chat_id
			)
		);

		if ( ! $chat || empty( $chat->visitor_email ) ) {
			return;
		}

		$messages = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE chat_id = %d ORDER BY created_at ASC",
				$messages_table,
				$chat_id
			)
		);

		// Build transcript.
		$transcript = $this->build_transcript( $chat, $messages );

		// Send email.
		$subject = sprintf(
			/* translators: %s: Site name */
			__( 'Chat Transcript - %s', 'bkx-live-chat' ),
			get_bloginfo( 'name' )
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $chat->visitor_email, $subject, $transcript, $headers );
	}

	/**
	 * Build transcript HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param object $chat     Chat object.
	 * @param array  $messages Messages array.
	 * @return string HTML transcript.
	 */
	private function build_transcript( $chat, $messages ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #2196f3; color: white; padding: 20px; text-align: center; }
				.chat-info { background: #f5f5f5; padding: 15px; margin: 20px 0; }
				.message { padding: 10px; margin: 10px 0; border-radius: 8px; }
				.message.visitor { background: #e3f2fd; }
				.message.operator { background: #e8f5e9; }
				.message.system { background: #fff3e0; font-style: italic; }
				.message-header { font-weight: bold; margin-bottom: 5px; }
				.message-time { font-size: 12px; color: #666; }
				.footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php esc_html_e( 'Chat Transcript', 'bkx-live-chat' ); ?></h1>
				</div>

				<div class="chat-info">
					<p><strong><?php esc_html_e( 'Date:', 'bkx-live-chat' ); ?></strong> <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $chat->started_at ) ) ); ?></p>
					<?php if ( $chat->ended_at ) : ?>
						<p><strong><?php esc_html_e( 'Duration:', 'bkx-live-chat' ); ?></strong> <?php echo esc_html( $this->format_duration( strtotime( $chat->ended_at ) - strtotime( $chat->started_at ) ) ); ?></p>
					<?php endif; ?>
				</div>

				<div class="messages">
					<?php foreach ( $messages as $message ) : ?>
						<div class="message <?php echo esc_attr( $message->sender_type ); ?>">
							<div class="message-header">
								<?php echo esc_html( $message->sender_name ); ?>
								<span class="message-time">
									<?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( $message->created_at ) ) ); ?>
								</span>
							</div>
							<div class="message-content">
								<?php echo esc_html( $message->content ); ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="footer">
					<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Format duration.
	 *
	 * @since 1.0.0
	 *
	 * @param int $seconds Duration in seconds.
	 * @return string Formatted duration.
	 */
	private function format_duration( $seconds ) {
		$hours   = floor( $seconds / 3600 );
		$minutes = floor( ( $seconds % 3600 ) / 60 );
		$secs    = $seconds % 60;

		$parts = array();

		if ( $hours > 0 ) {
			/* translators: %d: Number of hours */
			$parts[] = sprintf( _n( '%d hour', '%d hours', $hours, 'bkx-live-chat' ), $hours );
		}

		if ( $minutes > 0 ) {
			/* translators: %d: Number of minutes */
			$parts[] = sprintf( _n( '%d minute', '%d minutes', $minutes, 'bkx-live-chat' ), $minutes );
		}

		if ( empty( $parts ) || $secs > 0 ) {
			/* translators: %d: Number of seconds */
			$parts[] = sprintf( _n( '%d second', '%d seconds', $secs, 'bkx-live-chat' ), $secs );
		}

		return implode( ', ', $parts );
	}

	/**
	 * Notify operators of new chat.
	 *
	 * @since 1.0.0
	 *
	 * @param int $chat_id Chat ID.
	 */
	public function notify_new_chat( $chat_id ) {
		// This could send browser notifications, emails, etc.
		// For now, operators poll for new chats via AJAX.
		do_action( 'bkx_livechat_new_chat_notification', $chat_id );
	}
}

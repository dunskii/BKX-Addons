<?php
/**
 * Chat Service.
 *
 * @package BookingX\LiveChat\Services
 * @since   1.0.0
 */

namespace BookingX\LiveChat\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ChatService class.
 *
 * Manages chat sessions and messages.
 *
 * @since 1.0.0
 */
class ChatService {

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
	 * Start a new chat.
	 *
	 * @since 1.0.0
	 *
	 * @param string $session_id Session ID.
	 * @param string $name       Visitor name.
	 * @param string $email      Visitor email.
	 * @param string $message    Initial message.
	 * @param string $page_url   Page URL.
	 * @return array|\WP_Error Chat data or error.
	 */
	public function start_chat( $session_id, $name, $email, $message, $page_url ) {
		global $wpdb;

		// Check if chat already exists.
		$existing = $this->get_chat_by_session( $session_id );
		if ( $existing && in_array( $existing->status, array( 'pending', 'active' ), true ) ) {
			return array(
				'chat_id' => $existing->id,
				'status'  => $existing->status,
			);
		}

		$chats_table = $wpdb->prefix . 'bkx_livechat_chats';

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$chats_table,
			array(
				'session_id'       => $session_id,
				'visitor_name'     => $name,
				'visitor_email'    => $email,
				'visitor_ip'       => $this->get_visitor_ip(),
				'visitor_user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'page_url'         => $page_url,
				'status'           => 'pending',
				'last_message_at'  => current_time( 'mysql' ),
			)
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create chat.', 'bkx-live-chat' ) );
		}

		$chat_id = $wpdb->insert_id;

		// Add initial message.
		if ( ! empty( $message ) ) {
			$this->add_message( $chat_id, 'visitor', null, $name, $message );
		}

		// Add welcome message.
		$welcome = $this->settings['welcome_message'] ?? '';
		if ( ! empty( $welcome ) ) {
			$this->add_message( $chat_id, 'system', null, 'System', $welcome );
		}

		// Auto-assign if enabled.
		if ( ! empty( $this->settings['auto_assign_enabled'] ) ) {
			$operator_service = new OperatorService( $this->settings );
			$operator = $operator_service->get_available_operator();
			if ( $operator ) {
				$this->accept_chat( $chat_id, $operator->user_id );
			}
		}

		/**
		 * Fires when a new chat is started.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $chat_id    Chat ID.
		 * @param string $session_id Session ID.
		 */
		do_action( 'bkx_livechat_chat_started', $chat_id, $session_id );

		return array(
			'chat_id' => $chat_id,
			'status'  => 'pending',
		);
	}

	/**
	 * Get chat by session ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $session_id Session ID.
	 * @return object|null Chat or null.
	 */
	public function get_chat_by_session( $session_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_chats';

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE session_id = %s ORDER BY started_at DESC LIMIT 1",
				$table,
				$session_id
			)
		);
	}

	/**
	 * Get chat by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $chat_id Chat ID.
	 * @return object|null Chat or null.
	 */
	public function get_chat( $chat_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_chats';

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$table,
				$chat_id
			)
		);
	}

	/**
	 * Get chats list.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Filter by status.
	 * @param int    $page   Page number.
	 * @return array Chats.
	 */
	public function get_chats( $status = 'all', $page = 1 ) {
		global $wpdb;

		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;
		$table    = $wpdb->prefix . 'bkx_livechat_chats';

		$where = '';
		if ( 'all' !== $status ) {
			$where = $wpdb->prepare( 'WHERE status = %s', $status );
		}

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT c.*,
					(SELECT COUNT(*) FROM {$wpdb->prefix}bkx_livechat_messages m WHERE m.chat_id = c.id AND m.sender_type = 'visitor' AND m.is_read = 0) as unread_count
				FROM %i c
				{$where}
				ORDER BY c.last_message_at DESC
				LIMIT %d OFFSET %d",
				$table,
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Get pending chats count.
	 *
	 * @since 1.0.0
	 *
	 * @return int Count.
	 */
	public function get_pending_count() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_chats';

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status = 'pending'", $table )
		);
	}

	/**
	 * Accept a chat.
	 *
	 * @since 1.0.0
	 *
	 * @param int $chat_id     Chat ID.
	 * @param int $operator_id Operator user ID.
	 * @return true|\WP_Error True or error.
	 */
	public function accept_chat( $chat_id, $operator_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_chats';

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'operator_id' => $operator_id,
				'status'      => 'active',
			),
			array( 'id' => $chat_id )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to accept chat.', 'bkx-live-chat' ) );
		}

		// Update operator active chats.
		$operator_service = new OperatorService( $this->settings );
		$operator_service->increment_active_chats( $operator_id );

		// Add system message.
		$user = get_user_by( 'id', $operator_id );
		$this->add_message(
			$chat_id,
			'system',
			null,
			'System',
			sprintf(
				/* translators: %s: Operator name */
				__( '%s has joined the chat.', 'bkx-live-chat' ),
				$user->display_name
			)
		);

		/**
		 * Fires when a chat is accepted.
		 *
		 * @since 1.0.0
		 *
		 * @param int $chat_id     Chat ID.
		 * @param int $operator_id Operator ID.
		 */
		do_action( 'bkx_livechat_chat_accepted', $chat_id, $operator_id );

		return true;
	}

	/**
	 * Close a chat.
	 *
	 * @since 1.0.0
	 *
	 * @param int $chat_id Chat ID.
	 * @return true|\WP_Error True or error.
	 */
	public function close_chat( $chat_id ) {
		global $wpdb;

		$chat  = $this->get_chat( $chat_id );
		$table = $wpdb->prefix . 'bkx_livechat_chats';

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'status'   => 'closed',
				'ended_at' => current_time( 'mysql' ),
			),
			array( 'id' => $chat_id )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to close chat.', 'bkx-live-chat' ) );
		}

		// Update operator active chats.
		if ( $chat && $chat->operator_id ) {
			$operator_service = new OperatorService( $this->settings );
			$operator_service->decrement_active_chats( $chat->operator_id );
		}

		// Add system message.
		$this->add_message( $chat_id, 'system', null, 'System', __( 'Chat has ended.', 'bkx-live-chat' ) );

		/**
		 * Fires when a chat is closed.
		 *
		 * @since 1.0.0
		 *
		 * @param int $chat_id Chat ID.
		 */
		do_action( 'bkx_livechat_chat_closed', $chat_id );

		return true;
	}

	/**
	 * Transfer a chat.
	 *
	 * @since 1.0.0
	 *
	 * @param int $chat_id         Chat ID.
	 * @param int $new_operator_id New operator ID.
	 * @return true|\WP_Error True or error.
	 */
	public function transfer_chat( $chat_id, $new_operator_id ) {
		global $wpdb;

		$chat  = $this->get_chat( $chat_id );
		$table = $wpdb->prefix . 'bkx_livechat_chats';

		$old_operator_id = $chat->operator_id;

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'operator_id' => $new_operator_id ),
			array( 'id' => $chat_id )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to transfer chat.', 'bkx-live-chat' ) );
		}

		// Update operator active chats.
		$operator_service = new OperatorService( $this->settings );
		if ( $old_operator_id ) {
			$operator_service->decrement_active_chats( $old_operator_id );
		}
		$operator_service->increment_active_chats( $new_operator_id );

		// Add system message.
		$new_user = get_user_by( 'id', $new_operator_id );
		$this->add_message(
			$chat_id,
			'system',
			null,
			'System',
			sprintf(
				/* translators: %s: Operator name */
				__( 'Chat transferred to %s.', 'bkx-live-chat' ),
				$new_user->display_name
			)
		);

		return true;
	}

	/**
	 * Add a message.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $chat_id     Chat ID.
	 * @param string $sender_type Sender type (visitor/operator/system).
	 * @param int    $sender_id   Sender ID.
	 * @param string $sender_name Sender name.
	 * @param string $content     Message content.
	 * @param string $type        Message type.
	 * @param string $attachment_url Attachment URL.
	 * @param string $attachment_name Attachment name.
	 * @return array|\WP_Error Message data or error.
	 */
	public function add_message( $chat_id, $sender_type, $sender_id, $sender_name, $content, $type = 'text', $attachment_url = '', $attachment_name = '' ) {
		global $wpdb;

		$messages_table = $wpdb->prefix . 'bkx_livechat_messages';
		$chats_table    = $wpdb->prefix . 'bkx_livechat_chats';

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$messages_table,
			array(
				'chat_id'         => $chat_id,
				'sender_type'     => $sender_type,
				'sender_id'       => $sender_id,
				'sender_name'     => $sender_name,
				'message_type'    => $type,
				'content'         => $content,
				'attachment_url'  => $attachment_url ?: null,
				'attachment_name' => $attachment_name ?: null,
			)
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to send message.', 'bkx-live-chat' ) );
		}

		$message_id = $wpdb->insert_id;

		// Update chat last message time.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$chats_table,
			array( 'last_message_at' => current_time( 'mysql' ) ),
			array( 'id' => $chat_id )
		);

		return array(
			'id'          => $message_id,
			'sender_type' => $sender_type,
			'sender_name' => $sender_name,
			'content'     => $content,
			'created_at'  => current_time( 'mysql' ),
		);
	}

	/**
	 * Get messages for a chat.
	 *
	 * @since 1.0.0
	 *
	 * @param int $chat_id Chat ID.
	 * @param int $after   Get messages after this ID.
	 * @return array Messages.
	 */
	public function get_messages( $chat_id, $after = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_messages';

		$after_clause = '';
		if ( $after > 0 ) {
			$after_clause = $wpdb->prepare( 'AND id > %d', $after );
		}

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE chat_id = %d {$after_clause} ORDER BY created_at ASC",
				$table,
				$chat_id
			)
		);
	}

	/**
	 * Mark messages as read.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $chat_id     Chat ID.
	 * @param string $sender_type Sender type to mark as read.
	 */
	public function mark_messages_read( $chat_id, $sender_type ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_messages';

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'is_read' => 1 ),
			array(
				'chat_id'     => $chat_id,
				'sender_type' => $sender_type,
			)
		);
	}

	/**
	 * Submit rating.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $chat_id  Chat ID.
	 * @param int    $rating   Rating (1-5).
	 * @param string $feedback Feedback text.
	 */
	public function submit_rating( $chat_id, $rating, $feedback ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_livechat_chats';

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'rating'   => min( 5, max( 1, $rating ) ),
				'feedback' => $feedback,
			),
			array( 'id' => $chat_id )
		);

		// Update operator rating.
		$chat = $this->get_chat( $chat_id );
		if ( $chat && $chat->operator_id ) {
			$operator_service = new OperatorService( $this->settings );
			$operator_service->update_rating( $chat->operator_id );
		}
	}

	/**
	 * Get visitor IP.
	 *
	 * @since 1.0.0
	 *
	 * @return string IP address.
	 */
	private function get_visitor_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}
}

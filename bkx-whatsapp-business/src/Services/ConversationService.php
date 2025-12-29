<?php
/**
 * Conversation Service.
 *
 * @package BookingX\WhatsAppBusiness\Services
 * @since   1.0.0
 */

namespace BookingX\WhatsAppBusiness\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ConversationService class.
 *
 * Manages WhatsApp conversations.
 *
 * @since 1.0.0
 */
class ConversationService {

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
	 * Get conversations.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Filter by status.
	 * @param int    $page   Page number.
	 * @return array Conversations.
	 */
	public function get_conversations( $status = 'active', $page = 1 ) {
		global $wpdb;

		$per_page = 20;
		$offset   = ( $page - 1 ) * $per_page;
		$table    = $wpdb->prefix . 'bkx_whatsapp_conversations';

		$where = '';
		if ( 'all' !== $status ) {
			$where = $wpdb->prepare( 'WHERE status = %s', $status );
		}

		$conversations = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT c.*,
					(SELECT content FROM {$wpdb->prefix}bkx_whatsapp_messages m
					 WHERE m.phone_number = c.phone_number
					 ORDER BY m.created_at DESC LIMIT 1) as last_message
				FROM %i c
				{$where}
				ORDER BY c.last_message_at DESC
				LIMIT %d OFFSET %d",
				$table,
				$per_page,
				$offset
			)
		);

		$total = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i {$where}",
				$table
			)
		);

		return array(
			'conversations' => $conversations,
			'total'         => (int) $total,
			'pages'         => ceil( $total / $per_page ),
		);
	}

	/**
	 * Get or create conversation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $phone         Phone number.
	 * @param string $customer_name Optional customer name.
	 * @return object Conversation.
	 */
	public function get_or_create( $phone, $customer_name = '' ) {
		global $wpdb;

		$table        = $wpdb->prefix . 'bkx_whatsapp_conversations';
		$conversation = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE phone_number = %s",
				$table,
				$phone
			)
		);

		if ( $conversation ) {
			return $conversation;
		}

		// Create new conversation.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'phone_number'  => $phone,
				'customer_name' => $customer_name,
				'status'        => 'active',
			)
		);

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$table,
				$wpdb->insert_id
			)
		);
	}

	/**
	 * Update conversation on new message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $phone       Phone number.
	 * @param int    $message_id  Message ID.
	 * @param bool   $is_inbound  Whether message is inbound.
	 */
	public function on_new_message( $phone, $message_id, $is_inbound = false ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_whatsapp_conversations';

		$data = array(
			'last_message_id' => $message_id,
			'last_message_at' => current_time( 'mysql' ),
		);

		if ( $is_inbound ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"UPDATE %i SET last_message_id = %d, last_message_at = %s, unread_count = unread_count + 1 WHERE phone_number = %s",
					$table,
					$message_id,
					current_time( 'mysql' ),
					$phone
				)
			);
		} else {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				$data,
				array( 'phone_number' => $phone )
			);
		}
	}

	/**
	 * Mark conversation as read.
	 *
	 * @since 1.0.0
	 *
	 * @param string $phone Phone number.
	 */
	public function mark_as_read( $phone ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_whatsapp_conversations';

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'unread_count' => 0 ),
			array( 'phone_number' => $phone )
		);
	}

	/**
	 * Assign conversation to staff.
	 *
	 * @since 1.0.0
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id         User ID.
	 */
	public function assign_to( $conversation_id, $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_whatsapp_conversations';

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'assigned_to' => $user_id ),
			array( 'id' => $conversation_id )
		);
	}

	/**
	 * Update conversation status.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $status          New status.
	 */
	public function update_status( $conversation_id, $status ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_whatsapp_conversations';

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'status' => $status ),
			array( 'id' => $conversation_id )
		);
	}

	/**
	 * Link conversation to customer.
	 *
	 * @since 1.0.0
	 *
	 * @param string $phone       Phone number.
	 * @param int    $customer_id Customer user ID.
	 * @param string $name        Customer name.
	 */
	public function link_to_customer( $phone, $customer_id, $name = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_whatsapp_conversations';

		$data = array( 'customer_id' => $customer_id );

		if ( ! empty( $name ) ) {
			$data['customer_name'] = $name;
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			$data,
			array( 'phone_number' => $phone )
		);
	}

	/**
	 * Get unread count.
	 *
	 * @since 1.0.0
	 *
	 * @return int Unread count.
	 */
	public function get_unread_count() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_whatsapp_conversations';

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT SUM(unread_count) FROM %i WHERE status = 'active'", $table )
		);
	}

	/**
	 * Search conversations.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query Search query.
	 * @return array Conversations.
	 */
	public function search( $query ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_whatsapp_conversations';
		$like  = '%' . $wpdb->esc_like( $query ) . '%';

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE phone_number LIKE %s OR customer_name LIKE %s ORDER BY last_message_at DESC LIMIT 20",
				$table,
				$like,
				$like
			)
		);
	}
}

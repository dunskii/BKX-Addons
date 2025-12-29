<?php
/**
 * Webhook Manager Service.
 *
 * @package BookingX\Discord
 */

namespace BookingX\Discord\Services;

defined( 'ABSPATH' ) || exit;

/**
 * WebhookManager class.
 */
class WebhookManager {

	/**
	 * Get all webhooks.
	 *
	 * @param bool $active_only Only return active webhooks.
	 * @return array
	 */
	public function get_webhooks( $active_only = false ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_discord_webhooks';
		$where = $active_only ? 'WHERE is_active = 1' : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$webhooks = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC" );

		foreach ( $webhooks as $webhook ) {
			$webhook->notify_events = maybe_unserialize( $webhook->notify_events );
		}

		return $webhooks;
	}

	/**
	 * Get webhook by ID.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return object|null
	 */
	public function get_webhook( $webhook_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_discord_webhooks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$webhook = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $webhook_id )
		);

		if ( $webhook ) {
			$webhook->notify_events = maybe_unserialize( $webhook->notify_events );
		}

		return $webhook;
	}

	/**
	 * Get webhooks for event.
	 *
	 * @param string $event Event type.
	 * @return array
	 */
	public function get_webhooks_for_event( $event ) {
		$webhooks = $this->get_webhooks( true );
		$filtered = array();

		foreach ( $webhooks as $webhook ) {
			$events = is_array( $webhook->notify_events ) ? $webhook->notify_events : array();

			if ( empty( $events ) || in_array( $event, $events, true ) ) {
				$filtered[] = $webhook;
			}
		}

		return $filtered;
	}

	/**
	 * Add new webhook.
	 *
	 * @param string $name        Webhook name.
	 * @param string $webhook_url Webhook URL.
	 * @param array  $events      Events to notify.
	 * @return object|WP_Error
	 */
	public function add_webhook( $name, $webhook_url, $events = array() ) {
		global $wpdb;

		// Validate webhook by fetching info.
		$api  = new DiscordApi();
		$info = $api->get_webhook_info( $webhook_url );

		if ( is_wp_error( $info ) ) {
			return $info;
		}

		$table = $wpdb->prefix . 'bkx_discord_webhooks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$table,
			array(
				'name'          => $name,
				'webhook_url'   => $webhook_url,
				'guild_id'      => $info['guild_id'] ?? '',
				'channel_id'    => $info['channel_id'] ?? '',
				'channel_name'  => $info['name'] ?? '',
				'avatar_url'    => $info['avatar'] ?? null,
				'notify_events' => maybe_serialize( $events ),
				'is_active'     => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to save webhook.', 'bkx-discord' ) );
		}

		return $this->get_webhook( $wpdb->insert_id );
	}

	/**
	 * Update webhook.
	 *
	 * @param int   $webhook_id Webhook ID.
	 * @param array $data       Data to update.
	 * @return bool
	 */
	public function update_webhook( $webhook_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_discord_webhooks';

		if ( isset( $data['notify_events'] ) && is_array( $data['notify_events'] ) ) {
			$data['notify_events'] = maybe_serialize( $data['notify_events'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$table,
			$data,
			array( 'id' => $webhook_id )
		);

		return false !== $result;
	}

	/**
	 * Delete webhook.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return bool
	 */
	public function delete_webhook( $webhook_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_discord_webhooks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete( $table, array( 'id' => $webhook_id ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Toggle webhook status.
	 *
	 * @param int  $webhook_id Webhook ID.
	 * @param bool $is_active  Active status.
	 * @return bool
	 */
	public function toggle_webhook( $webhook_id, $is_active ) {
		return $this->update_webhook( $webhook_id, array( 'is_active' => $is_active ? 1 : 0 ) );
	}
}

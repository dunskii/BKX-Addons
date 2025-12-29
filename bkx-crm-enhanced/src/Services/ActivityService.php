<?php
/**
 * Activity Service.
 *
 * @package BookingX\CRM
 */

namespace BookingX\CRM\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ActivityService class.
 */
class ActivityService {

	/**
	 * Log an activity.
	 *
	 * @param int    $customer_id   Customer ID.
	 * @param string $activity_type Activity type.
	 * @param string $description   Description.
	 * @param array  $metadata      Additional metadata.
	 * @return int|false
	 */
	public function log( $customer_id, $activity_type, $description, $metadata = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_activities';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, array(
			'customer_id'   => $customer_id,
			'activity_type' => $activity_type,
			'description'   => $description,
			'metadata'      => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
		) );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get recent activities for a customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $limit       Number of activities.
	 * @param int $offset      Offset for pagination.
	 * @return array
	 */
	public function get_recent( $customer_id, $limit = 20, $offset = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_activities';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$activities = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE customer_id = %d
			ORDER BY created_at DESC
			LIMIT %d OFFSET %d",
			$customer_id,
			$limit,
			$offset
		) );

		foreach ( $activities as $activity ) {
			$activity->metadata = $activity->metadata ? json_decode( $activity->metadata, true ) : array();
			$activity->icon     = $this->get_activity_icon( $activity->activity_type );
		}

		return $activities;
	}

	/**
	 * Get activity icon.
	 *
	 * @param string $type Activity type.
	 * @return string
	 */
	private function get_activity_icon( $type ) {
		$icons = array(
			'booking_created'        => 'calendar',
			'booking_status_changed' => 'update',
			'note_added'             => 'edit',
			'tag_added'              => 'tag',
			'tag_removed'            => 'dismiss',
			'followup_scheduled'     => 'clock',
			'followup_sent'          => 'email',
			'communication_logged'   => 'format-chat',
			'profile_updated'        => 'admin-users',
			'customer_created'       => 'admin-users',
		);

		return $icons[ $type ] ?? 'marker';
	}

	/**
	 * REST: Get activity.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_activity( $request ) {
		$customer_id = $request->get_param( 'id' );
		$limit       = $request->get_param( 'limit' ) ?: 20;
		$offset      = $request->get_param( 'offset' ) ?: 0;

		$activities = $this->get_recent( $customer_id, $limit, $offset );

		return new \WP_REST_Response( $activities );
	}
}

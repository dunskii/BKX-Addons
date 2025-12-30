<?php
/**
 * Device Manager Service for Mobile App Framework.
 *
 * @package BookingX\MobileApp\Services
 */

namespace BookingX\MobileApp\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DeviceManager class.
 */
class DeviceManager {

	/**
	 * Register a device.
	 *
	 * @param array $data Device data.
	 * @return int|WP_Error
	 */
	public function register_device( $data ) {
		global $wpdb;

		if ( empty( $data['device_token'] ) ) {
			return new \WP_Error( 'missing_token', __( 'Device token is required.', 'bkx-mobile-app' ) );
		}

		if ( empty( $data['device_type'] ) || ! in_array( $data['device_type'], array( 'ios', 'android' ), true ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid device type.', 'bkx-mobile-app' ) );
		}

		// Check if device already exists.
		$existing = $this->get_device_by_token( $data['device_token'] );

		if ( $existing ) {
			// Update existing device.
			$wpdb->update(
				$wpdb->prefix . 'bkx_mobile_devices',
				array(
					'user_id'      => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : $existing->user_id,
					'device_name'  => isset( $data['device_name'] ) ? sanitize_text_field( $data['device_name'] ) : $existing->device_name,
					'device_model' => isset( $data['device_model'] ) ? sanitize_text_field( $data['device_model'] ) : $existing->device_model,
					'os_version'   => isset( $data['os_version'] ) ? sanitize_text_field( $data['os_version'] ) : $existing->os_version,
					'app_version'  => isset( $data['app_version'] ) ? sanitize_text_field( $data['app_version'] ) : $existing->app_version,
					'last_active'  => current_time( 'mysql' ),
				),
				array( 'id' => $existing->id )
			);

			return $existing->id;
		}

		// Create new device.
		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_mobile_devices',
			array(
				'user_id'      => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : null,
				'device_token' => sanitize_text_field( $data['device_token'] ),
				'device_type'  => sanitize_text_field( $data['device_type'] ),
				'device_name'  => isset( $data['device_name'] ) ? sanitize_text_field( $data['device_name'] ) : null,
				'device_model' => isset( $data['device_model'] ) ? sanitize_text_field( $data['device_model'] ) : null,
				'os_version'   => isset( $data['os_version'] ) ? sanitize_text_field( $data['os_version'] ) : null,
				'app_version'  => isset( $data['app_version'] ) ? sanitize_text_field( $data['app_version'] ) : null,
				'push_enabled' => 1,
				'last_active'  => current_time( 'mysql' ),
				'created_at'   => current_time( 'mysql' ),
			)
		);

		if ( ! $result ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to register device.', 'bkx-mobile-app' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Unregister a device.
	 *
	 * @param string $device_token Device token.
	 * @return bool
	 */
	public function unregister_device( $device_token ) {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . 'bkx_mobile_devices',
			array( 'device_token' => $device_token )
		) !== false;
	}

	/**
	 * Get device by token.
	 *
	 * @param string $device_token Device token.
	 * @return object|null
	 */
	public function get_device_by_token( $device_token ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_mobile_devices WHERE device_token = %s",
				$device_token
			)
		);
	}

	/**
	 * Get user devices.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_user_devices( $user_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_mobile_devices WHERE user_id = %d",
				$user_id
			)
		);
	}

	/**
	 * Get all devices.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all_devices( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'device_type' => '',
			'limit'       => 50,
			'offset'      => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['device_type'] ) ) {
			$where[] = 'device_type = %s';
			$values[] = $args['device_type'];
		}

		$where_clause = implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare(
				"SELECT d.*, u.display_name, u.user_email
				FROM {$wpdb->prefix}bkx_mobile_devices d
				LEFT JOIN {$wpdb->users} u ON d.user_id = u.ID
				WHERE {$where_clause}
				ORDER BY d.last_active DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $values, array( $args['limit'], $args['offset'] ) )
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT d.*, u.display_name, u.user_email
				FROM {$wpdb->prefix}bkx_mobile_devices d
				LEFT JOIN {$wpdb->users} u ON d.user_id = u.ID
				WHERE {$where_clause}
				ORDER BY d.last_active DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$args['limit'],
				$args['offset']
			);
		}

		return $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Update device push preference.
	 *
	 * @param int  $device_id    Device ID.
	 * @param bool $push_enabled Push enabled.
	 * @return bool
	 */
	public function update_push_preference( $device_id, $push_enabled ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'bkx_mobile_devices',
			array( 'push_enabled' => $push_enabled ? 1 : 0 ),
			array( 'id' => $device_id )
		) !== false;
	}

	/**
	 * Get device statistics.
	 *
	 * @return array
	 */
	public function get_statistics() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_mobile_devices';

		$stats = array(
			'total'        => 0,
			'ios'          => 0,
			'android'      => 0,
			'push_enabled' => 0,
			'active_today' => 0,
		);

		// Total devices.
		$stats['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		// By device type.
		$by_type = $wpdb->get_results(
			"SELECT device_type, COUNT(*) as count FROM {$table} GROUP BY device_type"
		);

		foreach ( $by_type as $row ) {
			$stats[ $row->device_type ] = (int) $row->count;
		}

		// Push enabled.
		$stats['push_enabled'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE push_enabled = 1"
		);

		// Active today.
		$today = gmdate( 'Y-m-d' );
		$stats['active_today'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE DATE(last_active) = %s",
				$today
			)
		);

		return $stats;
	}

	/**
	 * Cleanup inactive devices.
	 *
	 * @param int $days Days of inactivity.
	 * @return int Number of deleted devices.
	 */
	public function cleanup_inactive( $days = 90 ) {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bkx_mobile_devices WHERE last_active < %s",
				$cutoff_date
			)
		);
	}
}

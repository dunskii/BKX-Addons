<?php
/**
 * Usage Tracker Service.
 *
 * @package BookingX\MultiTenant\Services
 */

namespace BookingX\MultiTenant\Services;

defined( 'ABSPATH' ) || exit;

/**
 * UsageTracker class.
 */
class UsageTracker {

	/**
	 * Track booking created.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public function track_booking_created( $booking_id ) {
		$addon = \BookingX\MultiTenant\MultiTenantAddon::get_instance();
		$tenant = $addon->get_current_tenant();

		if ( ! $tenant ) {
			return;
		}

		$this->increment_usage( $tenant->id, 'bookings', 1, 'monthly' );
		$this->increment_usage( $tenant->id, 'bookings_total', 1, 'all_time' );
	}

	/**
	 * Increment usage.
	 *
	 * @param int    $tenant_id Tenant ID.
	 * @param string $metric    Metric name.
	 * @param int    $amount    Amount to increment.
	 * @param string $period    Period type (monthly, daily, all_time).
	 */
	public function increment_usage( $tenant_id, $metric, $amount = 1, $period = 'monthly' ) {
		global $wpdb;

		$period_start = $this->get_period_start( $period );

		// Try to update existing record.
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->base_prefix}bkx_tenant_usage
				SET value = value + %d
				WHERE tenant_id = %d AND metric = %s AND period = %s AND period_start = %s",
				$amount,
				$tenant_id,
				$metric,
				$period,
				$period_start
			)
		);

		// If no rows updated, insert new record.
		if ( $result === 0 ) {
			$wpdb->insert(
				$wpdb->base_prefix . 'bkx_tenant_usage',
				array(
					'tenant_id'    => $tenant_id,
					'metric'       => $metric,
					'value'        => $amount,
					'period'       => $period,
					'period_start' => $period_start,
				)
			);
		}
	}

	/**
	 * Get current usage.
	 *
	 * @param int    $tenant_id Tenant ID.
	 * @param string $metric    Metric name.
	 * @param string $period    Period type.
	 * @return int
	 */
	public function get_current_usage( $tenant_id, $metric, $period = 'monthly' ) {
		global $wpdb;

		$period_start = $this->get_period_start( $period );

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$wpdb->base_prefix}bkx_tenant_usage
				WHERE tenant_id = %d AND metric = %s AND period = %s AND period_start = %s",
				$tenant_id,
				$metric,
				$period,
				$period_start
			)
		);

		return (int) ( $value ?? 0 );
	}

	/**
	 * Get usage history.
	 *
	 * @param int    $tenant_id Tenant ID.
	 * @param string $metric    Metric name.
	 * @param string $period    Period type.
	 * @param int    $limit     Number of periods.
	 * @return array
	 */
	public function get_usage_history( $tenant_id, $metric, $period = 'monthly', $limit = 12 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->base_prefix}bkx_tenant_usage
				WHERE tenant_id = %d AND metric = %s AND period = %s
				ORDER BY period_start DESC
				LIMIT %d",
				$tenant_id,
				$metric,
				$period,
				$limit
			)
		);
	}

	/**
	 * Get all metrics for tenant.
	 *
	 * @param int $tenant_id Tenant ID.
	 * @return array
	 */
	public function get_tenant_usage_summary( $tenant_id ) {
		$metrics = array(
			'bookings'        => $this->get_current_usage( $tenant_id, 'bookings', 'monthly' ),
			'bookings_total'  => $this->get_current_usage( $tenant_id, 'bookings_total', 'all_time' ),
			'api_calls'       => $this->get_current_usage( $tenant_id, 'api_calls', 'monthly' ),
			'storage_mb'      => $this->get_current_usage( $tenant_id, 'storage_mb', 'all_time' ),
		);

		// Get plan limits for comparison.
		$plan_manager = \BookingX\MultiTenant\MultiTenantAddon::get_instance()->get_service( 'plan_manager' );
		$plan = $plan_manager->get_tenant_plan( $tenant_id );

		if ( $plan ) {
			$limits = json_decode( $plan->limits, true ) ?: array();
			foreach ( $metrics as $key => $value ) {
				$limit = $limits[ $key ] ?? -1;
				$metrics[ $key ] = array(
					'current' => $value,
					'limit'   => $limit,
					'percent' => $limit > 0 ? round( ( $value / $limit ) * 100, 1 ) : 0,
				);
			}
		}

		return $metrics;
	}

	/**
	 * Get period start date.
	 *
	 * @param string $period Period type.
	 * @return string
	 */
	private function get_period_start( $period ) {
		switch ( $period ) {
			case 'daily':
				return gmdate( 'Y-m-d' );

			case 'monthly':
				return gmdate( 'Y-m-01' );

			case 'yearly':
				return gmdate( 'Y-01-01' );

			case 'all_time':
				return '1970-01-01';

			default:
				return gmdate( 'Y-m-01' );
		}
	}

	/**
	 * Reset usage for period.
	 *
	 * @param int    $tenant_id Tenant ID.
	 * @param string $metric    Metric name.
	 * @param string $period    Period type.
	 */
	public function reset_usage( $tenant_id, $metric, $period ) {
		global $wpdb;

		$wpdb->delete(
			$wpdb->base_prefix . 'bkx_tenant_usage',
			array(
				'tenant_id'    => $tenant_id,
				'metric'       => $metric,
				'period'       => $period,
				'period_start' => $this->get_period_start( $period ),
			)
		);
	}

	/**
	 * Get usage statistics for all tenants.
	 *
	 * @param string $metric Metric name.
	 * @param string $period Period type.
	 * @return array
	 */
	public function get_global_usage_stats( $metric, $period = 'monthly' ) {
		global $wpdb;

		$period_start = $this->get_period_start( $period );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.*, t.name as tenant_name
				FROM {$wpdb->base_prefix}bkx_tenant_usage u
				LEFT JOIN {$wpdb->base_prefix}bkx_tenants t ON u.tenant_id = t.id
				WHERE u.metric = %s AND u.period = %s AND u.period_start = %s
				ORDER BY u.value DESC",
				$metric,
				$period,
				$period_start
			)
		);
	}

	/**
	 * REST: Get tenant usage.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_tenant_usage( $request ) {
		$tenant_id = $request->get_param( 'id' );
		$usage = $this->get_tenant_usage_summary( $tenant_id );

		return rest_ensure_response( $usage );
	}
}

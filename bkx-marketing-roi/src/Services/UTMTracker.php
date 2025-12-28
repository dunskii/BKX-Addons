<?php
/**
 * UTM Tracker Service.
 *
 * @package BookingX\MarketingROI
 * @since   1.0.0
 */

namespace BookingX\MarketingROI\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * UTMTracker Class.
 *
 * Tracks UTM-tagged visits and conversions.
 */
class UTMTracker {

	/**
	 * Track a visit.
	 *
	 * @param array $data Visit data.
	 * @return bool
	 */
	public function track_visit( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_roi_visits';

		// Check if already tracked in this session.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE session_id = %s AND utm_source = %s AND utm_campaign = %s",
				$table,
				$data['session_id'],
				$data['utm_source'],
				$data['utm_campaign']
			)
		);

		if ( $existing ) {
			return true; // Already tracked.
		}

		// Find matching campaign.
		$campaign_id = $this->find_campaign( $data['utm_source'], $data['utm_campaign'] );

		$device_type = $this->detect_device_type();

		$result = $wpdb->insert(
			$table,
			array(
				'session_id'   => $data['session_id'],
				'campaign_id'  => $campaign_id,
				'utm_source'   => $data['utm_source'],
				'utm_medium'   => $data['utm_medium'],
				'utm_campaign' => $data['utm_campaign'],
				'utm_content'  => $data['utm_content'],
				'utm_term'     => $data['utm_term'],
				'landing_page' => $data['landing_page'],
				'referrer'     => $data['referrer'],
				'device_type'  => $device_type,
				'converted'    => 0,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return (bool) $result;
	}

	/**
	 * Convert a visit (booking made).
	 *
	 * @param string $session_id Session ID.
	 * @param int    $booking_id Booking ID.
	 * @param float  $revenue    Revenue amount.
	 * @return bool
	 */
	public function convert_visit( $session_id, $booking_id, $revenue ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_roi_visits';

		$result = $wpdb->update(
			$table,
			array(
				'converted'  => 1,
				'booking_id' => $booking_id,
				'revenue'    => $revenue,
			),
			array( 'session_id' => $session_id ),
			array( '%d', '%d', '%f' ),
			array( '%s' )
		);

		return $result !== false;
	}

	/**
	 * Get visit by session ID.
	 *
	 * @param string $session_id Session ID.
	 * @return array|null
	 */
	public function get_visit( $session_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_roi_visits';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM %i WHERE session_id = %s", $table, $session_id ),
			ARRAY_A
		);
	}

	/**
	 * Get visits by campaign.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return array
	 */
	public function get_campaign_visits( $campaign_id, $start_date = '', $end_date = '' ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_roi_visits';
		$date_clause = $this->get_date_clause( $start_date, $end_date );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE campaign_id = %d {$date_clause} ORDER BY created_at DESC",
				$table,
				$campaign_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get visit statistics.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_visit_stats( $start_date = '', $end_date = '' ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_roi_visits';
		$date_clause = $this->get_date_clause( $start_date, $end_date );

		$total_visits = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE 1=1 {$date_clause}",
				$table
			)
		);

		$conversions = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE converted = 1 {$date_clause}",
				$table
			)
		);

		$total_revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(revenue) FROM %i WHERE converted = 1 {$date_clause}",
				$table
			)
		);

		$conversion_rate = $total_visits > 0 ? ( $conversions / $total_visits ) * 100 : 0;

		return array(
			'total_visits'    => (int) $total_visits,
			'conversions'     => (int) $conversions,
			'conversion_rate' => round( $conversion_rate, 2 ),
			'total_revenue'   => (float) ( $total_revenue ?? 0 ),
		);
	}

	/**
	 * Get visits grouped by UTM parameter.
	 *
	 * @param string $group_by   UTM parameter to group by.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_visits_by_utm( $group_by = 'utm_source', $start_date = '', $end_date = '' ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_roi_visits';
		$date_clause = $this->get_date_clause( $start_date, $end_date );

		$allowed_fields = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' );
		if ( ! in_array( $group_by, $allowed_fields, true ) ) {
			$group_by = 'utm_source';
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					{$group_by} as utm_value,
					COUNT(*) as visits,
					SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
					SUM(CASE WHEN converted = 1 THEN revenue ELSE 0 END) as revenue
				FROM %i
				WHERE {$group_by} IS NOT NULL AND {$group_by} != '' {$date_clause}
				GROUP BY {$group_by}
				ORDER BY visits DESC",
				$table
			),
			ARRAY_A
		);
	}

	/**
	 * Get daily visit trends.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_daily_trends( $start_date = '', $end_date = '' ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_roi_visits';
		$date_clause = $this->get_date_clause( $start_date, $end_date );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(created_at) as date,
					COUNT(*) as visits,
					SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
					SUM(CASE WHEN converted = 1 THEN revenue ELSE 0 END) as revenue
				FROM %i
				WHERE 1=1 {$date_clause}
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$table
			),
			ARRAY_A
		);
	}

	/**
	 * Find matching campaign.
	 *
	 * @param string $utm_source   UTM source.
	 * @param string $utm_campaign UTM campaign.
	 * @return int|null
	 */
	private function find_campaign( $utm_source, $utm_campaign ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_roi_campaigns';

		// Try exact match first.
		$campaign_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE utm_source = %s AND utm_campaign = %s LIMIT 1",
				$table,
				$utm_source,
				$utm_campaign
			)
		);

		if ( $campaign_id ) {
			return (int) $campaign_id;
		}

		// Try source-only match.
		$campaign_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE utm_source = %s AND (utm_campaign = '' OR utm_campaign IS NULL) LIMIT 1",
				$table,
				$utm_source
			)
		);

		return $campaign_id ? (int) $campaign_id : null;
	}

	/**
	 * Detect device type.
	 *
	 * @return string
	 */
	private function detect_device_type() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return 'unknown';
		}

		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );

		if ( preg_match( '/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $user_agent ) ) {
			return 'mobile';
		}

		if ( preg_match( '/tablet|ipad|playbook|silk/i', $user_agent ) ) {
			return 'tablet';
		}

		return 'desktop';
	}

	/**
	 * Get date clause.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return string
	 */
	private function get_date_clause( $start_date, $end_date ) {
		$clause = '';

		if ( ! empty( $start_date ) ) {
			$clause .= " AND created_at >= '" . esc_sql( $start_date ) . " 00:00:00'";
		}

		if ( ! empty( $end_date ) ) {
			$clause .= " AND created_at <= '" . esc_sql( $end_date ) . " 23:59:59'";
		}

		return $clause;
	}
}

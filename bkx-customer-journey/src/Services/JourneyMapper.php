<?php
/**
 * Journey Mapper Service.
 *
 * @package BookingX\CustomerJourney
 * @since   1.0.0
 */

namespace BookingX\CustomerJourney\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JourneyMapper Class.
 *
 * Maps customer journeys from first touch to conversion.
 */
class JourneyMapper {

	/**
	 * Journey outcomes.
	 *
	 * @var array
	 */
	private $outcomes = array(
		'converted'  => 'Converted',
		'abandoned'  => 'Abandoned',
		'bounced'    => 'Bounced',
		'in_progress' => 'In Progress',
	);

	/**
	 * Get journey overview.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_overview( $start_date = '', $end_date = '' ) {
		return array(
			'summary'      => $this->get_journey_summary( $start_date, $end_date ),
			'funnel'       => $this->get_journey_funnel( $start_date, $end_date ),
			'by_outcome'   => $this->get_journeys_by_outcome( $start_date, $end_date ),
			'duration'     => $this->get_duration_stats( $start_date, $end_date ),
			'drop_off'     => $this->get_drop_off_points( $start_date, $end_date ),
			'daily_trends' => $this->get_daily_trends( $start_date, $end_date ),
		);
	}

	/**
	 * Get journey summary.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_journey_summary( $start_date, $end_date ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_cj_journeys';
		$date_clause = $this->get_date_clause( $start_date, $end_date, 'journey_start' );

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE 1=1 {$date_clause}",
				$table
			)
		);

		$converted = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE journey_outcome = 'converted' {$date_clause}",
				$table
			)
		);

		$avg_touchpoints = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(touchpoint_count) FROM %i WHERE 1=1 {$date_clause}",
				$table
			)
		);

		$avg_duration = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(duration_seconds) FROM %i WHERE duration_seconds IS NOT NULL {$date_clause}",
				$table
			)
		);

		$conversion_rate = $total > 0 ? round( ( $converted / $total ) * 100, 1 ) : 0;

		return array(
			'total_journeys'     => (int) $total,
			'converted'          => (int) $converted,
			'conversion_rate'    => $conversion_rate,
			'avg_touchpoints'    => round( (float) $avg_touchpoints, 1 ),
			'avg_duration_mins'  => round( (float) $avg_duration / 60, 1 ),
		);
	}

	/**
	 * Get journey funnel.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_journey_funnel( $start_date, $end_date ) {
		global $wpdb;

		$touchpoints_table = $wpdb->prefix . 'bkx_cj_touchpoints';
		$journeys_table    = $wpdb->prefix . 'bkx_cj_journeys';
		$date_clause       = $this->get_date_clause( $start_date, $end_date, 'created_at' );

		// Define funnel stages.
		$stages = array(
			'page_view'        => 'Page Views',
			'service_view'     => 'Service Views',
			'widget_open'      => 'Widget Opened',
			'form_start'       => 'Form Started',
			'booking_attempt'  => 'Booking Attempted',
			'booking_complete' => 'Booking Complete',
		);

		$funnel = array();

		foreach ( $stages as $type => $label ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT session_id) FROM %i
					WHERE touchpoint_type = %s {$date_clause}",
					$touchpoints_table,
					$type
				)
			);

			$funnel[] = array(
				'stage' => $type,
				'label' => $label,
				'count' => (int) $count,
			);
		}

		// Calculate conversion rates between stages.
		$prev_count = null;
		foreach ( $funnel as &$stage ) {
			if ( $prev_count !== null && $prev_count > 0 ) {
				$stage['conversion_rate'] = round( ( $stage['count'] / $prev_count ) * 100, 1 );
			} else {
				$stage['conversion_rate'] = 100;
			}
			$prev_count = $stage['count'];
		}

		return $funnel;
	}

	/**
	 * Get journeys by outcome.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_journeys_by_outcome( $start_date, $end_date ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_cj_journeys';
		$date_clause = $this->get_date_clause( $start_date, $end_date, 'journey_start' );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT journey_outcome, COUNT(*) as count,
					AVG(touchpoint_count) as avg_touchpoints,
					AVG(duration_seconds) as avg_duration
				FROM %i
				WHERE journey_outcome IS NOT NULL {$date_clause}
				GROUP BY journey_outcome",
				$table
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				return array(
					'outcome'         => $row['journey_outcome'],
					'label'           => $this->outcomes[ $row['journey_outcome'] ] ?? ucfirst( $row['journey_outcome'] ),
					'count'           => (int) $row['count'],
					'avg_touchpoints' => round( (float) $row['avg_touchpoints'], 1 ),
					'avg_duration'    => round( (float) $row['avg_duration'] / 60, 1 ),
				);
			},
			$results
		);
	}

	/**
	 * Get duration statistics.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_duration_stats( $start_date, $end_date ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_cj_journeys';
		$date_clause = $this->get_date_clause( $start_date, $end_date, 'journey_start' );

		// Get converted journeys duration.
		$converted_duration = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					MIN(duration_seconds) as min_duration,
					MAX(duration_seconds) as max_duration,
					AVG(duration_seconds) as avg_duration
				FROM %i
				WHERE journey_outcome = 'converted'
				AND duration_seconds IS NOT NULL {$date_clause}",
				$table
			),
			ARRAY_A
		);

		// Get duration distribution.
		$distribution = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					CASE
						WHEN duration_seconds < 60 THEN '< 1 min'
						WHEN duration_seconds < 300 THEN '1-5 min'
						WHEN duration_seconds < 900 THEN '5-15 min'
						WHEN duration_seconds < 1800 THEN '15-30 min'
						ELSE '30+ min'
					END as duration_bucket,
					COUNT(*) as count
				FROM %i
				WHERE journey_outcome = 'converted'
				AND duration_seconds IS NOT NULL {$date_clause}
				GROUP BY duration_bucket
				ORDER BY MIN(duration_seconds)",
				$table
			),
			ARRAY_A
		);

		return array(
			'converted' => array(
				'min_mins' => round( (float) ( $converted_duration['min_duration'] ?? 0 ) / 60, 1 ),
				'max_mins' => round( (float) ( $converted_duration['max_duration'] ?? 0 ) / 60, 1 ),
				'avg_mins' => round( (float) ( $converted_duration['avg_duration'] ?? 0 ) / 60, 1 ),
			),
			'distribution' => $distribution,
		);
	}

	/**
	 * Get drop-off points.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_drop_off_points( $start_date, $end_date ) {
		global $wpdb;

		$touchpoints_table = $wpdb->prefix . 'bkx_cj_touchpoints';
		$journeys_table    = $wpdb->prefix . 'bkx_cj_journeys';
		$date_clause       = $this->get_date_clause( $start_date, $end_date, 'j.journey_start' );

		// Get last touchpoint for abandoned journeys.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.touchpoint_type, t.page_url, COUNT(*) as count
				FROM %i j
				INNER JOIN (
					SELECT session_id, touchpoint_type, page_url,
						ROW_NUMBER() OVER (PARTITION BY session_id ORDER BY created_at DESC) as rn
					FROM %i
				) t ON j.session_id = t.session_id AND t.rn = 1
				WHERE j.journey_outcome = 'abandoned' {$date_clause}
				GROUP BY t.touchpoint_type, t.page_url
				ORDER BY count DESC
				LIMIT 10",
				$journeys_table,
				$touchpoints_table
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				return array(
					'last_touchpoint' => $row['touchpoint_type'],
					'page'            => $row['page_url'],
					'count'           => (int) $row['count'],
				);
			},
			$results
		);
	}

	/**
	 * Get daily trends.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_daily_trends( $start_date, $end_date ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_cj_journeys';
		$date_clause = $this->get_date_clause( $start_date, $end_date, 'journey_start' );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(journey_start) as date,
					COUNT(*) as total,
					SUM(CASE WHEN journey_outcome = 'converted' THEN 1 ELSE 0 END) as converted
				FROM %i
				WHERE 1=1 {$date_clause}
				GROUP BY DATE(journey_start)
				ORDER BY date",
				$table
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				$rate = $row['total'] > 0 ? round( ( $row['converted'] / $row['total'] ) * 100, 1 ) : 0;
				return array(
					'date'            => $row['date'],
					'total'           => (int) $row['total'],
					'converted'       => (int) $row['converted'],
					'conversion_rate' => $rate,
				);
			},
			$results
		);
	}

	/**
	 * Complete a journey.
	 *
	 * @param string $session_id Session ID.
	 * @param string $email      Customer email.
	 * @param int    $booking_id Booking ID.
	 */
	public function complete_journey( $session_id, $email, $booking_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_cj_journeys';

		// Get journey start.
		$journey = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE session_id = %s",
				$table,
				$session_id
			),
			ARRAY_A
		);

		$now = current_time( 'mysql' );

		if ( $journey ) {
			// Calculate duration.
			$start    = new \DateTime( $journey['journey_start'] );
			$end      = new \DateTime( $now );
			$duration = $end->getTimestamp() - $start->getTimestamp();

			// Determine attribution source.
			$attribution = $this->determine_attribution( $session_id );

			$wpdb->update(
				$table,
				array(
					'customer_email'     => $email,
					'journey_end'        => $now,
					'journey_outcome'    => 'converted',
					'duration_seconds'   => $duration,
					'booking_id'         => $booking_id,
					'attribution_source' => $attribution,
				),
				array( 'session_id' => $session_id ),
				array( '%s', '%s', '%s', '%d', '%d', '%s' ),
				array( '%s' )
			);
		} else {
			// Create journey record.
			$wpdb->insert(
				$table,
				array(
					'session_id'         => $session_id,
					'customer_email'     => $email,
					'journey_start'      => $now,
					'journey_end'        => $now,
					'journey_outcome'    => 'converted',
					'touchpoint_count'   => 1,
					'duration_seconds'   => 0,
					'booking_id'         => $booking_id,
					'attribution_source' => 'direct',
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
			);
		}

		// Update touchpoints with customer email.
		$touchpoints_table = $wpdb->prefix . 'bkx_cj_touchpoints';
		$wpdb->update(
			$touchpoints_table,
			array( 'customer_email' => $email ),
			array( 'session_id' => $session_id ),
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Get customer journey.
	 *
	 * @param string $email Customer email.
	 * @return array
	 */
	public function get_customer_journey( $email ) {
		global $wpdb;

		$journeys_table    = $wpdb->prefix . 'bkx_cj_journeys';
		$touchpoints_table = $wpdb->prefix . 'bkx_cj_touchpoints';

		// Get all journeys for this customer.
		$journeys = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE customer_email = %s ORDER BY journey_start DESC",
				$journeys_table,
				$email
			),
			ARRAY_A
		);

		$result = array();

		foreach ( $journeys as $journey ) {
			// Get touchpoints for this journey.
			$touchpoints = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE session_id = %s ORDER BY created_at ASC",
					$touchpoints_table,
					$journey['session_id']
				),
				ARRAY_A
			);

			$result[] = array(
				'journey_id'   => $journey['id'],
				'session_id'   => $journey['session_id'],
				'start'        => $journey['journey_start'],
				'end'          => $journey['journey_end'],
				'outcome'      => $journey['journey_outcome'],
				'duration'     => (int) $journey['duration_seconds'],
				'booking_id'   => $journey['booking_id'],
				'attribution'  => $journey['attribution_source'],
				'touchpoints'  => array_map(
					function ( $tp ) {
						return array(
							'type'      => $tp['touchpoint_type'],
							'page'      => $tp['page_url'],
							'timestamp' => $tp['created_at'],
							'data'      => json_decode( $tp['touchpoint_data'], true ),
						);
					},
					$touchpoints
				),
			);
		}

		return $result;
	}

	/**
	 * Determine attribution source.
	 *
	 * @param string $session_id Session ID.
	 * @return string
	 */
	private function determine_attribution( $session_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_cj_touchpoints';

		// Get first touchpoint referrer.
		$first_referrer = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT referrer FROM %i
				WHERE session_id = %s AND referrer IS NOT NULL AND referrer != ''
				ORDER BY created_at ASC
				LIMIT 1",
				$table,
				$session_id
			)
		);

		if ( ! $first_referrer ) {
			return 'direct';
		}

		return $this->categorize_source( $first_referrer );
	}

	/**
	 * Categorize traffic source.
	 *
	 * @param string $referrer Referrer URL.
	 * @return string
	 */
	private function categorize_source( $referrer ) {
		$host = wp_parse_url( $referrer, PHP_URL_HOST );

		if ( ! $host ) {
			return 'direct';
		}

		// Search engines.
		$search_engines = array( 'google', 'bing', 'yahoo', 'duckduckgo', 'baidu' );
		foreach ( $search_engines as $engine ) {
			if ( stripos( $host, $engine ) !== false ) {
				return 'organic_search';
			}
		}

		// Social networks.
		$social = array( 'facebook', 'twitter', 'instagram', 'linkedin', 'pinterest', 'youtube', 'tiktok' );
		foreach ( $social as $network ) {
			if ( stripos( $host, $network ) !== false ) {
				return 'social';
			}
		}

		// Email.
		if ( stripos( $host, 'mail' ) !== false || stripos( $referrer, 'utm_medium=email' ) !== false ) {
			return 'email';
		}

		// Paid search (check for common ad parameters).
		if ( stripos( $referrer, 'gclid' ) !== false || stripos( $referrer, 'msclkid' ) !== false ) {
			return 'paid_search';
		}

		return 'referral';
	}

	/**
	 * Mark abandoned journeys.
	 *
	 * Marks journeys as abandoned if no activity for 30 minutes.
	 */
	public function mark_abandoned_journeys() {
		global $wpdb;

		$table    = $wpdb->prefix . 'bkx_cj_journeys';
		$cutoff   = gmdate( 'Y-m-d H:i:s', strtotime( '-30 minutes' ) );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
				SET journey_outcome = 'abandoned',
					journey_end = NOW()
				WHERE journey_outcome IS NULL
				AND journey_start < %s",
				$table,
				$cutoff
			)
		);
	}

	/**
	 * Get date clause.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param string $field      Date field name.
	 * @return string
	 */
	private function get_date_clause( $start_date, $end_date, $field = 'journey_start' ) {
		$clause = '';

		if ( ! empty( $start_date ) ) {
			$clause .= " AND {$field} >= '" . esc_sql( $start_date ) . " 00:00:00'";
		}

		if ( ! empty( $end_date ) ) {
			$clause .= " AND {$field} <= '" . esc_sql( $end_date ) . " 23:59:59'";
		}

		return $clause;
	}
}

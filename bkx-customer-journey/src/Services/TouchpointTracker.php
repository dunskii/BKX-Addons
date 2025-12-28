<?php
/**
 * Touchpoint Tracker Service.
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
 * TouchpointTracker Class.
 *
 * Tracks customer touchpoints (page views, interactions, conversions).
 */
class TouchpointTracker {

	/**
	 * Touchpoint types.
	 *
	 * @var array
	 */
	private $touchpoint_types = array(
		'page_view',
		'service_view',
		'staff_view',
		'widget_open',
		'widget_interact',
		'form_start',
		'form_step',
		'form_abandon',
		'booking_attempt',
		'booking_complete',
		'return_visit',
		'email_click',
		'social_click',
	);

	/**
	 * Track a touchpoint.
	 *
	 * @param string $session_id Session ID.
	 * @param string $type       Touchpoint type.
	 * @param array  $data       Additional data.
	 * @param string $page_url   Page URL.
	 * @param string $referrer   Referrer URL.
	 * @return bool
	 */
	public function track( $session_id, $type, $data = array(), $page_url = '', $referrer = '' ) {
		global $wpdb;

		if ( ! in_array( $type, $this->touchpoint_types, true ) ) {
			$type = 'custom';
		}

		$table = $wpdb->prefix . 'bkx_cj_touchpoints';

		$device_type = $this->detect_device_type();

		$result = $wpdb->insert(
			$table,
			array(
				'session_id'      => $session_id,
				'customer_id'     => get_current_user_id() ?: null,
				'customer_email'  => $this->get_current_customer_email(),
				'touchpoint_type' => $type,
				'touchpoint_data' => wp_json_encode( $data ),
				'page_url'        => $page_url,
				'referrer'        => $referrer,
				'device_type'     => $device_type,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			$this->update_journey_touchpoint_count( $session_id );
		}

		return (bool) $result;
	}

	/**
	 * Get touchpoint analysis.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_analysis( $start_date = '', $end_date = '' ) {
		return array(
			'summary'          => $this->get_touchpoint_summary( $start_date, $end_date ),
			'by_type'          => $this->get_touchpoints_by_type( $start_date, $end_date ),
			'by_device'        => $this->get_touchpoints_by_device( $start_date, $end_date ),
			'popular_pages'    => $this->get_popular_pages( $start_date, $end_date ),
			'referrer_sources' => $this->get_referrer_sources( $start_date, $end_date ),
			'hourly_patterns'  => $this->get_hourly_patterns( $start_date, $end_date ),
			'conversion_paths' => $this->get_common_paths( $start_date, $end_date ),
		);
	}

	/**
	 * Get touchpoint summary.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_touchpoint_summary( $start_date, $end_date ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_cj_touchpoints';
		$date_clause = $this->get_date_clause( $start_date, $end_date );

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE 1=1 {$date_clause}",
				$table
			)
		);

		$unique_sessions = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_id) FROM %i WHERE 1=1 {$date_clause}",
				$table
			)
		);

		$avg_per_session = $unique_sessions > 0 ? round( $total / $unique_sessions, 1 ) : 0;

		// Conversion touchpoints.
		$conversions = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE touchpoint_type = 'booking_complete' {$date_clause}",
				$table
			)
		);

		$conversion_rate = $unique_sessions > 0 ? round( ( $conversions / $unique_sessions ) * 100, 1 ) : 0;

		return array(
			'total_touchpoints'       => (int) $total,
			'unique_sessions'         => (int) $unique_sessions,
			'avg_per_session'         => $avg_per_session,
			'conversions'             => (int) $conversions,
			'conversion_rate'         => $conversion_rate,
			'avg_touchpoints_to_conv' => $this->get_avg_touchpoints_to_conversion( $date_clause ),
		);
	}

	/**
	 * Get average touchpoints to conversion.
	 *
	 * @param string $date_clause Date clause.
	 * @return float
	 */
	private function get_avg_touchpoints_to_conversion( $date_clause ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_cj_touchpoints';

		// Get sessions that converted.
		$converting_sessions = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT session_id FROM %i
				WHERE touchpoint_type = 'booking_complete' {$date_clause}",
				$table
			)
		);

		if ( empty( $converting_sessions ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $converting_sessions ), '%s' ) );
		$params       = array_merge( array( $table ), $converting_sessions );

		$avg = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(cnt) FROM (
					SELECT session_id, COUNT(*) as cnt
					FROM %i
					WHERE session_id IN ({$placeholders})
					GROUP BY session_id
				) as subquery",
				$params
			)
		);

		return round( (float) $avg, 1 );
	}

	/**
	 * Get touchpoints by type.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_touchpoints_by_type( $start_date, $end_date ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_cj_touchpoints';
		$date_clause = $this->get_date_clause( $start_date, $end_date );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT touchpoint_type, COUNT(*) as count
				FROM %i
				WHERE 1=1 {$date_clause}
				GROUP BY touchpoint_type
				ORDER BY count DESC",
				$table
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				return array(
					'type'  => $row['touchpoint_type'],
					'label' => $this->get_touchpoint_label( $row['touchpoint_type'] ),
					'count' => (int) $row['count'],
				);
			},
			$results
		);
	}

	/**
	 * Get touchpoints by device.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_touchpoints_by_device( $start_date, $end_date ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_cj_touchpoints';
		$date_clause = $this->get_date_clause( $start_date, $end_date );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT device_type, COUNT(*) as count, COUNT(DISTINCT session_id) as sessions
				FROM %i
				WHERE device_type IS NOT NULL {$date_clause}
				GROUP BY device_type
				ORDER BY count DESC",
				$table
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				return array(
					'device'   => $row['device_type'] ?: 'unknown',
					'count'    => (int) $row['count'],
					'sessions' => (int) $row['sessions'],
				);
			},
			$results
		);
	}

	/**
	 * Get popular pages.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param int    $limit      Limit.
	 * @return array
	 */
	private function get_popular_pages( $start_date, $end_date, $limit = 10 ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_cj_touchpoints';
		$date_clause = $this->get_date_clause( $start_date, $end_date );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_url, COUNT(*) as views, COUNT(DISTINCT session_id) as unique_views
				FROM %i
				WHERE page_url IS NOT NULL AND page_url != '' {$date_clause}
				GROUP BY page_url
				ORDER BY views DESC
				LIMIT %d",
				$table,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				$path = wp_parse_url( $row['page_url'], PHP_URL_PATH ) ?: $row['page_url'];
				return array(
					'url'          => $row['page_url'],
					'path'         => $path,
					'views'        => (int) $row['views'],
					'unique_views' => (int) $row['unique_views'],
				);
			},
			$results
		);
	}

	/**
	 * Get referrer sources.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_referrer_sources( $start_date, $end_date ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_cj_touchpoints';
		$date_clause = $this->get_date_clause( $start_date, $end_date );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT referrer, COUNT(*) as count, COUNT(DISTINCT session_id) as sessions
				FROM %i
				WHERE referrer IS NOT NULL AND referrer != '' {$date_clause}
				GROUP BY referrer
				ORDER BY count DESC
				LIMIT 20",
				$table
			),
			ARRAY_A
		);

		$sources = array();

		foreach ( $results as $row ) {
			$source = $this->categorize_referrer( $row['referrer'] );

			if ( ! isset( $sources[ $source ] ) ) {
				$sources[ $source ] = array(
					'source'   => $source,
					'count'    => 0,
					'sessions' => 0,
				);
			}

			$sources[ $source ]['count']    += (int) $row['count'];
			$sources[ $source ]['sessions'] += (int) $row['sessions'];
		}

		usort(
			$sources,
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		return array_values( $sources );
	}

	/**
	 * Get hourly patterns.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_hourly_patterns( $start_date, $end_date ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_cj_touchpoints';
		$date_clause = $this->get_date_clause( $start_date, $end_date );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT HOUR(created_at) as hour, COUNT(*) as count
				FROM %i
				WHERE 1=1 {$date_clause}
				GROUP BY HOUR(created_at)
				ORDER BY hour",
				$table
			),
			ARRAY_A
		);

		// Fill in all hours.
		$hours = array_fill( 0, 24, 0 );

		foreach ( $results as $row ) {
			$hours[ (int) $row['hour'] ] = (int) $row['count'];
		}

		return array_map(
			function ( $hour, $count ) {
				return array(
					'hour'  => $hour,
					'label' => sprintf( '%02d:00', $hour ),
					'count' => $count,
				);
			},
			array_keys( $hours ),
			array_values( $hours )
		);
	}

	/**
	 * Get common conversion paths.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param int    $limit      Limit.
	 * @return array
	 */
	private function get_common_paths( $start_date, $end_date, $limit = 10 ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_cj_touchpoints';
		$date_clause = $this->get_date_clause( $start_date, $end_date );

		// Get converting sessions.
		$converting_sessions = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT session_id FROM %i
				WHERE touchpoint_type = 'booking_complete' {$date_clause}",
				$table
			)
		);

		if ( empty( $converting_sessions ) ) {
			return array();
		}

		$paths = array();

		foreach ( $converting_sessions as $session_id ) {
			$touchpoints = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT touchpoint_type FROM %i
					WHERE session_id = %s
					ORDER BY created_at ASC",
					$table,
					$session_id
				)
			);

			$path_key = implode( ' → ', $touchpoints );

			if ( ! isset( $paths[ $path_key ] ) ) {
				$paths[ $path_key ] = array(
					'path'  => $touchpoints,
					'count' => 0,
				);
			}

			++$paths[ $path_key ]['count'];
		}

		usort(
			$paths,
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		return array_slice( $paths, 0, $limit );
	}

	/**
	 * Get session touchpoints.
	 *
	 * @param string $session_id Session ID.
	 * @return array
	 */
	public function get_session_touchpoints( $session_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_cj_touchpoints';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE session_id = %s ORDER BY created_at ASC",
				$table,
				$session_id
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				$row['touchpoint_data'] = json_decode( $row['touchpoint_data'], true );
				return $row;
			},
			$results
		);
	}

	/**
	 * Update journey touchpoint count.
	 *
	 * @param string $session_id Session ID.
	 */
	private function update_journey_touchpoint_count( $session_id ) {
		global $wpdb;

		$journey_table     = $wpdb->prefix . 'bkx_cj_journeys';
		$touchpoints_table = $wpdb->prefix . 'bkx_cj_touchpoints';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE session_id = %s",
				$touchpoints_table,
				$session_id
			)
		);

		// Check if journey exists.
		$journey_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE session_id = %s",
				$journey_table,
				$session_id
			)
		);

		if ( $journey_exists ) {
			$wpdb->update(
				$journey_table,
				array( 'touchpoint_count' => $count ),
				array( 'session_id' => $session_id ),
				array( '%d' ),
				array( '%s' )
			);
		} else {
			$wpdb->insert(
				$journey_table,
				array(
					'session_id'       => $session_id,
					'journey_start'    => current_time( 'mysql' ),
					'touchpoint_count' => $count,
				),
				array( '%s', '%s', '%d' )
			);
		}
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
	 * Get current customer email.
	 *
	 * @return string|null
	 */
	private function get_current_customer_email() {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			return $user->user_email;
		}

		return null;
	}

	/**
	 * Get touchpoint label.
	 *
	 * @param string $type Touchpoint type.
	 * @return string
	 */
	private function get_touchpoint_label( $type ) {
		$labels = array(
			'page_view'        => __( 'Page View', 'bkx-customer-journey' ),
			'service_view'     => __( 'Service View', 'bkx-customer-journey' ),
			'staff_view'       => __( 'Staff View', 'bkx-customer-journey' ),
			'widget_open'      => __( 'Widget Opened', 'bkx-customer-journey' ),
			'widget_interact'  => __( 'Widget Interaction', 'bkx-customer-journey' ),
			'form_start'       => __( 'Form Started', 'bkx-customer-journey' ),
			'form_step'        => __( 'Form Step', 'bkx-customer-journey' ),
			'form_abandon'     => __( 'Form Abandoned', 'bkx-customer-journey' ),
			'booking_attempt'  => __( 'Booking Attempt', 'bkx-customer-journey' ),
			'booking_complete' => __( 'Booking Complete', 'bkx-customer-journey' ),
			'return_visit'     => __( 'Return Visit', 'bkx-customer-journey' ),
			'email_click'      => __( 'Email Click', 'bkx-customer-journey' ),
			'social_click'     => __( 'Social Click', 'bkx-customer-journey' ),
			'custom'           => __( 'Custom Event', 'bkx-customer-journey' ),
		);

		return $labels[ $type ] ?? ucfirst( str_replace( '_', ' ', $type ) );
	}

	/**
	 * Categorize referrer.
	 *
	 * @param string $referrer Referrer URL.
	 * @return string
	 */
	private function categorize_referrer( $referrer ) {
		$host = wp_parse_url( $referrer, PHP_URL_HOST );

		if ( ! $host ) {
			return 'Direct';
		}

		// Search engines.
		$search_engines = array( 'google', 'bing', 'yahoo', 'duckduckgo', 'baidu' );
		foreach ( $search_engines as $engine ) {
			if ( stripos( $host, $engine ) !== false ) {
				return 'Organic Search';
			}
		}

		// Social networks.
		$social_networks = array( 'facebook', 'twitter', 'instagram', 'linkedin', 'pinterest', 'youtube', 'tiktok' );
		foreach ( $social_networks as $network ) {
			if ( stripos( $host, $network ) !== false ) {
				return 'Social Media';
			}
		}

		// Email providers.
		$email_providers = array( 'mail', 'outlook', 'gmail' );
		foreach ( $email_providers as $provider ) {
			if ( stripos( $host, $provider ) !== false ) {
				return 'Email';
			}
		}

		// Check if same site.
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $host === $site_host ) {
			return 'Internal';
		}

		return 'Referral';
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

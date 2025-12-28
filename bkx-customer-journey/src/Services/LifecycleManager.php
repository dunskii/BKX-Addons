<?php
/**
 * Lifecycle Manager Service.
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
 * LifecycleManager Class.
 *
 * Manages customer lifecycle stages and transitions.
 */
class LifecycleManager {

	/**
	 * Lifecycle stages.
	 *
	 * @var array
	 */
	private $stages = array(
		'lead'     => array(
			'label'       => 'Lead',
			'description' => 'Visited site but no booking',
			'color'       => '#9CA3AF',
		),
		'prospect' => array(
			'label'       => 'Prospect',
			'description' => 'Started booking process',
			'color'       => '#60A5FA',
		),
		'customer' => array(
			'label'       => 'Customer',
			'description' => '1 completed booking',
			'color'       => '#34D399',
		),
		'loyal'    => array(
			'label'       => 'Loyal',
			'description' => '3+ bookings',
			'color'       => '#A78BFA',
		),
		'champion' => array(
			'label'       => 'Champion',
			'description' => '10+ bookings, high value',
			'color'       => '#F59E0B',
		),
		'at_risk'  => array(
			'label'       => 'At Risk',
			'description' => 'No activity in 60+ days',
			'color'       => '#F97316',
		),
		'churned'  => array(
			'label'       => 'Churned',
			'description' => 'No activity in 120+ days',
			'color'       => '#EF4444',
		),
	);

	/**
	 * Get lifecycle stages.
	 *
	 * @return array
	 */
	public function get_stages() {
		return $this->stages;
	}

	/**
	 * Get lifecycle summary.
	 *
	 * @return array
	 */
	public function get_lifecycle_summary() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_cj_lifecycle';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					lifecycle_stage,
					COUNT(*) as count,
					SUM(total_revenue) as revenue,
					AVG(total_bookings) as avg_bookings,
					AVG(ltv_score) as avg_ltv
				FROM %i
				GROUP BY lifecycle_stage",
				$table
			),
			ARRAY_A
		);

		$summary = array();

		foreach ( $this->stages as $stage_key => $stage_info ) {
			$summary[ $stage_key ] = array(
				'stage'        => $stage_key,
				'label'        => $stage_info['label'],
				'color'        => $stage_info['color'],
				'count'        => 0,
				'revenue'      => 0,
				'avg_bookings' => 0,
				'avg_ltv'      => 0,
			);
		}

		foreach ( $results as $row ) {
			$stage = $row['lifecycle_stage'];
			if ( isset( $summary[ $stage ] ) ) {
				$summary[ $stage ]['count']        = (int) $row['count'];
				$summary[ $stage ]['revenue']      = (float) $row['revenue'];
				$summary[ $stage ]['avg_bookings'] = round( (float) $row['avg_bookings'], 1 );
				$summary[ $stage ]['avg_ltv']      = round( (float) $row['avg_ltv'], 2 );
			}
		}

		// Add transitions data.
		$transitions = $this->get_stage_transitions();

		return array(
			'stages'      => array_values( $summary ),
			'transitions' => $transitions,
			'totals'      => array(
				'customers'     => array_sum( array_column( $summary, 'count' ) ),
				'total_revenue' => array_sum( array_column( $summary, 'revenue' ) ),
			),
		);
	}

	/**
	 * Update customer lifecycle.
	 *
	 * @param string $email      Customer email.
	 * @param int    $booking_id Booking ID.
	 * @return bool
	 */
	public function update_customer_lifecycle( $email, $booking_id = 0 ) {
		global $wpdb;

		$table    = $wpdb->prefix . 'bkx_cj_lifecycle';
		$email    = sanitize_email( $email );
		$existing = $this->get_customer_record( $email );

		// Get booking stats.
		$stats = $this->calculate_customer_stats( $email );

		// Determine lifecycle stage.
		$stage = $this->determine_stage( $stats );

		// Calculate LTV score.
		$ltv_score = $this->calculate_ltv_score( $stats );

		// Calculate churn risk.
		$churn_risk = $this->calculate_churn_risk( $stats );

		$data = array(
			'customer_email'      => $email,
			'lifecycle_stage'     => $stage,
			'total_bookings'      => $stats['total_bookings'],
			'total_revenue'       => $stats['total_revenue'],
			'avg_booking_value'   => $stats['avg_booking_value'],
			'days_since_last'     => $stats['days_since_last'],
			'predicted_churn_risk' => $churn_risk,
			'ltv_score'           => $ltv_score,
			'updated_at'          => current_time( 'mysql' ),
		);

		if ( $existing ) {
			// Update existing record.
			$wpdb->update(
				$table,
				$data,
				array( 'customer_email' => $email ),
				array( '%s', '%s', '%d', '%f', '%f', '%d', '%f', '%f', '%s' ),
				array( '%s' )
			);
		} else {
			// Insert new record.
			$data['first_touch']   = current_time( 'mysql' );
			$data['first_booking'] = $booking_id ? current_time( 'mysql' ) : null;

			$wpdb->insert(
				$table,
				$data,
				array( '%s', '%s', '%d', '%f', '%f', '%d', '%f', '%f', '%s', '%s', '%s' )
			);
		}

		return true;
	}

	/**
	 * Record cancellation.
	 *
	 * @param string $email      Customer email.
	 * @param int    $booking_id Booking ID.
	 */
	public function record_cancellation( $email, $booking_id ) {
		// Recalculate lifecycle after cancellation.
		$this->update_customer_lifecycle( $email, $booking_id );
	}

	/**
	 * Get customer profile.
	 *
	 * @param string $email Customer email.
	 * @return array|null
	 */
	public function get_customer_profile( $email ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_cj_lifecycle';

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE customer_email = %s",
				$table,
				$email
			),
			ARRAY_A
		);

		if ( ! $record ) {
			return null;
		}

		$stage_info = $this->stages[ $record['lifecycle_stage'] ] ?? array();

		return array(
			'email'          => $record['customer_email'],
			'stage'          => $record['lifecycle_stage'],
			'stage_label'    => $stage_info['label'] ?? ucfirst( $record['lifecycle_stage'] ),
			'stage_color'    => $stage_info['color'] ?? '#9CA3AF',
			'first_touch'    => $record['first_touch'],
			'first_booking'  => $record['first_booking'],
			'last_booking'   => $record['last_booking'],
			'total_bookings' => (int) $record['total_bookings'],
			'total_revenue'  => (float) $record['total_revenue'],
			'avg_value'      => (float) $record['avg_booking_value'],
			'days_inactive'  => (int) $record['days_since_last'],
			'churn_risk'     => (float) $record['predicted_churn_risk'],
			'ltv_score'      => (float) $record['ltv_score'],
			'bookings'       => $this->get_customer_bookings( $email ),
		);
	}

	/**
	 * Update all customer lifecycles.
	 */
	public function update_all_lifecycles() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_cj_lifecycle';

		$emails = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT customer_email FROM %i",
				$table
			)
		);

		foreach ( $emails as $email ) {
			$this->update_customer_lifecycle( $email );
		}
	}

	/**
	 * Get customers at risk.
	 *
	 * @param float $min_risk Minimum risk threshold.
	 * @param int   $limit    Limit.
	 * @return array
	 */
	public function get_at_risk_customers( $min_risk = 0.5, $limit = 50 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_cj_lifecycle';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i
				WHERE predicted_churn_risk >= %f
				AND lifecycle_stage NOT IN ('churned', 'lead')
				ORDER BY predicted_churn_risk DESC
				LIMIT %d",
				$table,
				$min_risk,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				return array(
					'email'          => $row['customer_email'],
					'stage'          => $row['lifecycle_stage'],
					'churn_risk'     => round( (float) $row['predicted_churn_risk'] * 100, 1 ),
					'days_inactive'  => (int) $row['days_since_last'],
					'total_revenue'  => (float) $row['total_revenue'],
					'total_bookings' => (int) $row['total_bookings'],
				);
			},
			$results
		);
	}

	/**
	 * Get customer record.
	 *
	 * @param string $email Customer email.
	 * @return array|null
	 */
	private function get_customer_record( $email ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_cj_lifecycle';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE customer_email = %s",
				$table,
				$email
			),
			ARRAY_A
		);
	}

	/**
	 * Calculate customer stats from bookings.
	 *
	 * @param string $email Customer email.
	 * @return array
	 */
	private function calculate_customer_stats( $email ) {
		global $wpdb;

		// Get bookings for this customer.
		$bookings = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'posts_per_page' => -1,
				'post_status'    => array( 'bkx-completed', 'bkx-ack' ),
				'meta_query'     => array(
					array(
						'key'   => 'customer_email',
						'value' => $email,
					),
				),
			)
		);

		$total_bookings = count( $bookings );
		$total_revenue  = 0;
		$last_booking   = null;

		foreach ( $bookings as $booking ) {
			$amount = (float) get_post_meta( $booking->ID, 'total_amount', true );
			$total_revenue += $amount;

			$booking_date = get_post_meta( $booking->ID, 'booking_date', true );
			if ( ! $last_booking || $booking_date > $last_booking ) {
				$last_booking = $booking_date;
			}
		}

		$avg_booking_value = $total_bookings > 0 ? $total_revenue / $total_bookings : 0;

		$days_since_last = 0;
		if ( $last_booking ) {
			$last_date       = new \DateTime( $last_booking );
			$now             = new \DateTime();
			$days_since_last = $last_date->diff( $now )->days;
		}

		return array(
			'total_bookings'    => $total_bookings,
			'total_revenue'     => $total_revenue,
			'avg_booking_value' => $avg_booking_value,
			'last_booking'      => $last_booking,
			'days_since_last'   => $days_since_last,
		);
	}

	/**
	 * Determine lifecycle stage.
	 *
	 * @param array $stats Customer stats.
	 * @return string
	 */
	private function determine_stage( $stats ) {
		// Churned: 120+ days inactive.
		if ( $stats['days_since_last'] >= 120 && $stats['total_bookings'] > 0 ) {
			return 'churned';
		}

		// At Risk: 60+ days inactive.
		if ( $stats['days_since_last'] >= 60 && $stats['total_bookings'] > 0 ) {
			return 'at_risk';
		}

		// Champion: 10+ bookings and high value.
		if ( $stats['total_bookings'] >= 10 ) {
			return 'champion';
		}

		// Loyal: 3+ bookings.
		if ( $stats['total_bookings'] >= 3 ) {
			return 'loyal';
		}

		// Customer: 1+ booking.
		if ( $stats['total_bookings'] >= 1 ) {
			return 'customer';
		}

		// Lead: No bookings.
		return 'lead';
	}

	/**
	 * Calculate LTV score.
	 *
	 * @param array $stats Customer stats.
	 * @return float
	 */
	private function calculate_ltv_score( $stats ) {
		if ( $stats['total_bookings'] === 0 ) {
			return 0;
		}

		// Simple LTV calculation:
		// (Average booking value) * (Expected bookings per year) * (Expected customer lifespan in years)
		$avg_value = $stats['avg_booking_value'];

		// Estimate bookings per year based on frequency.
		if ( $stats['days_since_last'] > 0 && $stats['total_bookings'] > 1 ) {
			$days_as_customer     = $stats['days_since_last'] + 30; // Rough estimate.
			$bookings_per_year    = ( $stats['total_bookings'] / $days_as_customer ) * 365;
			$expected_lifespan    = 2; // 2 years default.
			$ltv                  = $avg_value * $bookings_per_year * $expected_lifespan;
		} else {
			// First-time customer, estimate based on average.
			$ltv = $avg_value * 3; // Assume 3 lifetime bookings.
		}

		return round( $ltv, 2 );
	}

	/**
	 * Calculate churn risk.
	 *
	 * @param array $stats Customer stats.
	 * @return float 0-1 probability.
	 */
	private function calculate_churn_risk( $stats ) {
		if ( $stats['total_bookings'] === 0 ) {
			return 0;
		}

		// Factors that increase churn risk:
		// - Days since last booking.
		// - Low booking frequency.
		// - Low average value.

		$risk = 0;

		// Days inactive factor (0-0.5).
		if ( $stats['days_since_last'] > 30 ) {
			$days_factor = min( ( $stats['days_since_last'] - 30 ) / 180, 1 );
			$risk       += $days_factor * 0.5;
		}

		// Low frequency factor (0-0.3).
		if ( $stats['total_bookings'] < 3 ) {
			$risk += 0.3 * ( 1 - ( $stats['total_bookings'] / 3 ) );
		}

		// Recent engagement reduces risk.
		if ( $stats['days_since_last'] < 14 ) {
			$risk *= 0.5;
		}

		return round( min( $risk, 1 ), 2 );
	}

	/**
	 * Get customer bookings.
	 *
	 * @param string $email Customer email.
	 * @param int    $limit Limit.
	 * @return array
	 */
	private function get_customer_bookings( $email, $limit = 10 ) {
		$bookings = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'posts_per_page' => $limit,
				'post_status'    => 'any',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'   => 'customer_email',
						'value' => $email,
					),
				),
			)
		);

		return array_map(
			function ( $booking ) {
				return array(
					'id'     => $booking->ID,
					'date'   => get_post_meta( $booking->ID, 'booking_date', true ),
					'status' => $booking->post_status,
					'amount' => (float) get_post_meta( $booking->ID, 'total_amount', true ),
				);
			},
			$bookings
		);
	}

	/**
	 * Get stage transitions.
	 *
	 * @return array
	 */
	private function get_stage_transitions() {
		// Define expected transitions between stages.
		return array(
			array( 'from' => 'lead', 'to' => 'prospect', 'label' => 'Started Booking' ),
			array( 'from' => 'prospect', 'to' => 'customer', 'label' => 'First Booking' ),
			array( 'from' => 'customer', 'to' => 'loyal', 'label' => '3+ Bookings' ),
			array( 'from' => 'loyal', 'to' => 'champion', 'label' => '10+ Bookings' ),
			array( 'from' => 'customer', 'to' => 'at_risk', 'label' => '60+ Days Inactive' ),
			array( 'from' => 'loyal', 'to' => 'at_risk', 'label' => '60+ Days Inactive' ),
			array( 'from' => 'at_risk', 'to' => 'churned', 'label' => '120+ Days Inactive' ),
			array( 'from' => 'at_risk', 'to' => 'customer', 'label' => 'Re-engaged' ),
			array( 'from' => 'churned', 'to' => 'customer', 'label' => 'Won Back' ),
		);
	}
}

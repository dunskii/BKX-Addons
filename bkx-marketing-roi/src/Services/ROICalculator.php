<?php
/**
 * ROI Calculator Service.
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
 * ROICalculator Class.
 *
 * Calculates ROI and marketing metrics.
 */
class ROICalculator {

	/**
	 * Campaign manager.
	 *
	 * @var CampaignManager
	 */
	private $campaign_manager;

	/**
	 * UTM tracker.
	 *
	 * @var UTMTracker
	 */
	private $utm_tracker;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->campaign_manager = new CampaignManager();
		$this->utm_tracker      = new UTMTracker();
	}

	/**
	 * Get dashboard data.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_dashboard_data( $start_date = '', $end_date = '' ) {
		return array(
			'summary'         => $this->get_summary( $start_date, $end_date ),
			'campaigns'       => $this->get_campaigns_with_roi( $start_date, $end_date ),
			'by_source'       => $this->utm_tracker->get_visits_by_utm( 'utm_source', $start_date, $end_date ),
			'by_medium'       => $this->utm_tracker->get_visits_by_utm( 'utm_medium', $start_date, $end_date ),
			'daily_trends'    => $this->utm_tracker->get_daily_trends( $start_date, $end_date ),
			'top_performers'  => $this->get_top_performers( $start_date, $end_date ),
		);
	}

	/**
	 * Get summary metrics.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_summary( $start_date = '', $end_date = '' ) {
		$visit_stats = $this->utm_tracker->get_visit_stats( $start_date, $end_date );
		$total_cost  = $this->get_total_cost( $start_date, $end_date );

		$revenue = $visit_stats['total_revenue'];
		$roi     = $total_cost > 0 ? ( ( $revenue - $total_cost ) / $total_cost ) * 100 : 0;
		$roas    = $total_cost > 0 ? $revenue / $total_cost : 0;
		$cpa     = $visit_stats['conversions'] > 0 ? $total_cost / $visit_stats['conversions'] : 0;
		$cpc     = $visit_stats['total_visits'] > 0 ? $total_cost / $visit_stats['total_visits'] : 0;

		return array(
			'total_visits'    => $visit_stats['total_visits'],
			'conversions'     => $visit_stats['conversions'],
			'conversion_rate' => $visit_stats['conversion_rate'],
			'total_revenue'   => $revenue,
			'total_cost'      => $total_cost,
			'roi'             => round( $roi, 2 ),
			'roas'            => round( $roas, 2 ),
			'cpa'             => round( $cpa, 2 ),
			'cpc'             => round( $cpc, 2 ),
			'profit'          => $revenue - $total_cost,
		);
	}

	/**
	 * Get total marketing cost.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return float
	 */
	private function get_total_cost( $start_date = '', $end_date = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_roi_costs';
		$where = '1=1';

		if ( ! empty( $start_date ) ) {
			$where .= $wpdb->prepare( ' AND cost_date >= %s', $start_date );
		}

		if ( ! empty( $end_date ) ) {
			$where .= $wpdb->prepare( ' AND cost_date <= %s', $end_date );
		}

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(amount) FROM %i WHERE {$where}",
				$table
			)
		);

		return (float) ( $total ?? 0 );
	}

	/**
	 * Get campaigns with ROI calculations.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_campaigns_with_roi( $start_date = '', $end_date = '' ) {
		$campaigns = $this->campaign_manager->get_all_campaigns();
		$result    = array();

		foreach ( $campaigns as $campaign ) {
			$metrics = $this->calculate_campaign_metrics( $campaign['id'], $start_date, $end_date );

			$result[] = array_merge( $campaign, $metrics );
		}

		// Sort by ROI descending.
		usort(
			$result,
			function ( $a, $b ) {
				return $b['roi'] <=> $a['roi'];
			}
		);

		return $result;
	}

	/**
	 * Calculate campaign metrics.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return array
	 */
	public function calculate_campaign_metrics( $campaign_id, $start_date = '', $end_date = '' ) {
		global $wpdb;

		$visits_table = $wpdb->prefix . 'bkx_roi_visits';
		$date_clause  = '';

		if ( ! empty( $start_date ) ) {
			$date_clause .= " AND created_at >= '" . esc_sql( $start_date ) . " 00:00:00'";
		}

		if ( ! empty( $end_date ) ) {
			$date_clause .= " AND created_at <= '" . esc_sql( $end_date ) . " 23:59:59'";
		}

		// Get visit stats.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as visits,
					SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
					SUM(CASE WHEN converted = 1 THEN revenue ELSE 0 END) as revenue
				FROM %i
				WHERE campaign_id = %d {$date_clause}",
				$visits_table,
				$campaign_id
			),
			ARRAY_A
		);

		$visits      = (int) ( $stats['visits'] ?? 0 );
		$conversions = (int) ( $stats['conversions'] ?? 0 );
		$revenue     = (float) ( $stats['revenue'] ?? 0 );

		// Get cost.
		$cost = $this->campaign_manager->get_total_cost( $campaign_id, $start_date, $end_date );

		// Calculate metrics.
		$conversion_rate = $visits > 0 ? ( $conversions / $visits ) * 100 : 0;
		$roi             = $cost > 0 ? ( ( $revenue - $cost ) / $cost ) * 100 : 0;
		$roas            = $cost > 0 ? $revenue / $cost : 0;
		$cpa             = $conversions > 0 ? $cost / $conversions : 0;
		$cpc             = $visits > 0 ? $cost / $visits : 0;
		$profit          = $revenue - $cost;

		return array(
			'visits'          => $visits,
			'conversions'     => $conversions,
			'conversion_rate' => round( $conversion_rate, 2 ),
			'revenue'         => $revenue,
			'cost'            => $cost,
			'roi'             => round( $roi, 2 ),
			'roas'            => round( $roas, 2 ),
			'cpa'             => round( $cpa, 2 ),
			'cpc'             => round( $cpc, 2 ),
			'profit'          => $profit,
		);
	}

	/**
	 * Get campaign details.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return array
	 */
	public function get_campaign_details( $campaign_id, $start_date = '', $end_date = '' ) {
		$campaign = $this->campaign_manager->get_campaign( $campaign_id );

		if ( ! $campaign ) {
			return null;
		}

		$metrics = $this->calculate_campaign_metrics( $campaign_id, $start_date, $end_date );
		$costs   = $this->campaign_manager->get_campaign_costs( $campaign_id, $start_date, $end_date );
		$daily   = $this->get_campaign_daily_stats( $campaign_id, $start_date, $end_date );

		return array(
			'campaign' => array_merge( $campaign, $metrics ),
			'costs'    => $costs,
			'daily'    => $daily,
		);
	}

	/**
	 * Get campaign daily statistics.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return array
	 */
	private function get_campaign_daily_stats( $campaign_id, $start_date = '', $end_date = '' ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_roi_visits';
		$date_clause = '';

		if ( ! empty( $start_date ) ) {
			$date_clause .= " AND created_at >= '" . esc_sql( $start_date ) . " 00:00:00'";
		}

		if ( ! empty( $end_date ) ) {
			$date_clause .= " AND created_at <= '" . esc_sql( $end_date ) . " 23:59:59'";
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(created_at) as date,
					COUNT(*) as visits,
					SUM(CASE WHEN converted = 1 THEN 1 ELSE 0 END) as conversions,
					SUM(CASE WHEN converted = 1 THEN revenue ELSE 0 END) as revenue
				FROM %i
				WHERE campaign_id = %d {$date_clause}
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$table,
				$campaign_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get top performing campaigns/sources.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param int    $limit      Limit.
	 * @return array
	 */
	public function get_top_performers( $start_date = '', $end_date = '', $limit = 5 ) {
		$campaigns = $this->get_campaigns_with_roi( $start_date, $end_date );

		// Top by ROI.
		$by_roi = array_filter( $campaigns, fn( $c ) => $c['roi'] > 0 );
		usort( $by_roi, fn( $a, $b ) => $b['roi'] <=> $a['roi'] );

		// Top by revenue.
		$by_revenue = $campaigns;
		usort( $by_revenue, fn( $a, $b ) => $b['revenue'] <=> $a['revenue'] );

		// Top by conversions.
		$by_conversions = $campaigns;
		usort( $by_conversions, fn( $a, $b ) => $b['conversions'] <=> $a['conversions'] );

		return array(
			'by_roi'         => array_slice( $by_roi, 0, $limit ),
			'by_revenue'     => array_slice( $by_revenue, 0, $limit ),
			'by_conversions' => array_slice( $by_conversions, 0, $limit ),
		);
	}

	/**
	 * Get UTM report.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param string $group_by   Group by parameter.
	 * @return array
	 */
	public function get_utm_report( $start_date = '', $end_date = '', $group_by = 'source' ) {
		$utm_field = 'utm_' . $group_by;
		$data      = $this->utm_tracker->get_visits_by_utm( $utm_field, $start_date, $end_date );

		return array_map(
			function ( $row ) {
				$conversion_rate = $row['visits'] > 0 ? ( $row['conversions'] / $row['visits'] ) * 100 : 0;
				return array(
					'value'           => $row['utm_value'],
					'visits'          => (int) $row['visits'],
					'conversions'     => (int) $row['conversions'],
					'conversion_rate' => round( $conversion_rate, 2 ),
					'revenue'         => (float) $row['revenue'],
				);
			},
			$data
		);
	}
}

<?php
/**
 * Season Manager Service.
 *
 * @package BookingX\SlidingPricing\Services
 * @since   1.0.0
 */

namespace BookingX\SlidingPricing\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SeasonManager Class.
 */
class SeasonManager {

	/**
	 * Save a season.
	 *
	 * @param array $data Season data.
	 * @return int|\WP_Error Season ID or error.
	 */
	public function save_season( $data ) {
		global $wpdb;

		// Validate.
		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'Season name is required', 'bkx-sliding-pricing' ) );
		}

		if ( empty( $data['start_date'] ) || empty( $data['end_date'] ) ) {
			return new \WP_Error( 'missing_dates', __( 'Start and end dates are required', 'bkx-sliding-pricing' ) );
		}

		$table = $wpdb->prefix . 'bkx_pricing_seasons';

		$season_data = array(
			'name'             => sanitize_text_field( $data['name'] ),
			'start_date'       => sanitize_text_field( $data['start_date'] ),
			'end_date'         => sanitize_text_field( $data['end_date'] ),
			'adjustment_type'  => sanitize_text_field( $data['adjustment_type'] ?? 'percentage' ),
			'adjustment_value' => floatval( $data['adjustment_value'] ?? 0 ),
			'applies_to'       => sanitize_text_field( $data['applies_to'] ?? 'all' ),
			'service_ids'      => maybe_serialize( $data['service_ids'] ?? array() ),
			'recurs_yearly'    => isset( $data['recurs_yearly'] ) ? 1 : 0,
			'is_active'        => isset( $data['is_active'] ) ? 1 : 0,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%d' );

		if ( ! empty( $data['id'] ) ) {
			// Update existing season.
			$result = $wpdb->update(
				$table,
				$season_data,
				array( 'id' => absint( $data['id'] ) ),
				$formats,
				array( '%d' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Database error occurred', 'bkx-sliding-pricing' ) );
			}

			return absint( $data['id'] );
		} else {
			// Insert new season.
			$result = $wpdb->insert( $table, $season_data, $formats );

			if ( ! $result ) {
				return new \WP_Error( 'db_error', __( 'Database error occurred', 'bkx-sliding-pricing' ) );
			}

			return $wpdb->insert_id;
		}
	}

	/**
	 * Delete a season.
	 *
	 * @param int $season_id Season ID.
	 * @return bool
	 */
	public function delete_season( $season_id ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'bkx_pricing_seasons';
		$result = $wpdb->delete( $table, array( 'id' => absint( $season_id ) ), array( '%d' ) );

		return $result !== false;
	}

	/**
	 * Get a season by ID.
	 *
	 * @param int $season_id Season ID.
	 * @return array|null
	 */
	public function get_season( $season_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_pricing_seasons';

		$season = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $season_id ),
			ARRAY_A
		);

		if ( $season ) {
			$season['service_ids'] = maybe_unserialize( $season['service_ids'] );
		}

		return $season;
	}

	/**
	 * Get all seasons.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_seasons( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'active_only' => false,
			'orderby'     => 'start_date',
			'order'       => 'ASC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_pricing_seasons';

		$where = 'WHERE 1=1';

		if ( $args['active_only'] ) {
			$where .= ' AND is_active = 1';
		}

		$orderby = in_array( $args['orderby'], array( 'start_date', 'end_date', 'name' ), true ) ? $args['orderby'] : 'start_date';
		$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$seasons = $wpdb->get_results(
			"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}",
			ARRAY_A
		);

		foreach ( $seasons as &$season ) {
			$season['service_ids'] = maybe_unserialize( $season['service_ids'] );
			$season['is_current']  = $this->is_season_current( $season );
		}

		return $seasons;
	}

	/**
	 * Get current season.
	 *
	 * @param int $service_id Optional service ID to filter by.
	 * @return array|null
	 */
	public function get_current_season( $service_id = 0 ) {
		$seasons = $this->get_seasons( array( 'active_only' => true ) );

		foreach ( $seasons as $season ) {
			if ( ! $this->is_season_current( $season ) ) {
				continue;
			}

			// Check service filter.
			if ( $service_id > 0 && 'specific' === $season['applies_to'] ) {
				if ( ! in_array( $service_id, (array) $season['service_ids'], true ) ) {
					continue;
				}
			}

			return $season;
		}

		return null;
	}

	/**
	 * Check if season is current.
	 *
	 * @param array $season Season data.
	 * @return bool
	 */
	public function is_season_current( $season ) {
		$today = gmdate( 'Y-m-d' );

		if ( $season['recurs_yearly'] ) {
			// Check by month-day for yearly recurring.
			$today_md  = gmdate( 'm-d' );
			$start_md  = gmdate( 'm-d', strtotime( $season['start_date'] ) );
			$end_md    = gmdate( 'm-d', strtotime( $season['end_date'] ) );

			// Handle seasons that span year boundary (e.g., Dec 15 - Jan 15).
			if ( $start_md > $end_md ) {
				return $today_md >= $start_md || $today_md <= $end_md;
			}

			return $today_md >= $start_md && $today_md <= $end_md;
		}

		// Check exact dates for non-recurring.
		return $today >= $season['start_date'] && $today <= $season['end_date'];
	}

	/**
	 * Get upcoming seasons.
	 *
	 * @param int $limit Number of seasons.
	 * @return array
	 */
	public function get_upcoming_seasons( $limit = 5 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_pricing_seasons';
		$today = gmdate( 'Y-m-d' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE is_active = 1 AND start_date > %s
				ORDER BY start_date ASC
				LIMIT %d",
				$today,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Create default seasons.
	 */
	public function create_default_seasons() {
		$current_year = gmdate( 'Y' );

		// Peak summer season.
		$this->save_season(
			array(
				'name'             => __( 'Peak Summer Season', 'bkx-sliding-pricing' ),
				'start_date'       => "$current_year-06-15",
				'end_date'         => "$current_year-08-31",
				'adjustment_type'  => 'percentage',
				'adjustment_value' => 15,
				'applies_to'       => 'all',
				'recurs_yearly'    => 1,
				'is_active'        => 0,
			)
		);

		// Holiday season.
		$this->save_season(
			array(
				'name'             => __( 'Holiday Season', 'bkx-sliding-pricing' ),
				'start_date'       => "$current_year-12-15",
				'end_date'         => "$current_year-01-05",
				'adjustment_type'  => 'percentage',
				'adjustment_value' => 20,
				'applies_to'       => 'all',
				'recurs_yearly'    => 1,
				'is_active'        => 0,
			)
		);

		// Off-season discount.
		$this->save_season(
			array(
				'name'             => __( 'Winter Off-Season', 'bkx-sliding-pricing' ),
				'start_date'       => "$current_year-01-15",
				'end_date'         => "$current_year-03-15",
				'adjustment_type'  => 'percentage',
				'adjustment_value' => -15,
				'applies_to'       => 'all',
				'recurs_yearly'    => 1,
				'is_active'        => 0,
			)
		);
	}
}

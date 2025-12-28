<?php
/**
 * Campaign Manager Service.
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
 * CampaignManager Class.
 *
 * Manages marketing campaigns CRUD operations.
 */
class CampaignManager {

	/**
	 * Get all campaigns.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all_campaigns( $args = array() ) {
		global $wpdb;

		$table    = $wpdb->prefix . 'bkx_roi_campaigns';
		$defaults = array(
			'status'   => '',
			'order_by' => 'created_at',
			'order'    => 'DESC',
			'limit'    => 100,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';
		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		$order_by = esc_sql( $args['order_by'] );
		$order    = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit    = absint( $args['limit'] );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where} ORDER BY {$order_by} {$order} LIMIT %d",
				$table,
				$limit
			),
			ARRAY_A
		);

		return array_map( array( $this, 'format_campaign' ), $results );
	}

	/**
	 * Get campaign by ID.
	 *
	 * @param int $id Campaign ID.
	 * @return array|null
	 */
	public function get_campaign( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_roi_campaigns';

		$campaign = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table, $id ),
			ARRAY_A
		);

		return $campaign ? $this->format_campaign( $campaign ) : null;
	}

	/**
	 * Get campaign by UTM parameters.
	 *
	 * @param string $utm_source   UTM source.
	 * @param string $utm_campaign UTM campaign.
	 * @return array|null
	 */
	public function get_campaign_by_utm( $utm_source, $utm_campaign ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_roi_campaigns';

		$campaign = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE utm_source = %s AND utm_campaign = %s",
				$table,
				$utm_source,
				$utm_campaign
			),
			ARRAY_A
		);

		return $campaign ? $this->format_campaign( $campaign ) : null;
	}

	/**
	 * Save campaign.
	 *
	 * @param array $data Campaign data.
	 * @return int|false Campaign ID or false on failure.
	 */
	public function save_campaign( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_roi_campaigns';

		$fields = array(
			'campaign_name' => $data['campaign_name'] ?? '',
			'utm_source'    => $data['utm_source'] ?? '',
			'utm_medium'    => $data['utm_medium'] ?? '',
			'utm_campaign'  => $data['utm_campaign'] ?? '',
			'utm_content'   => $data['utm_content'] ?? '',
			'utm_term'      => $data['utm_term'] ?? '',
			'budget'        => $data['budget'] ?? 0,
			'start_date'    => $data['start_date'] ?: null,
			'end_date'      => $data['end_date'] ?: null,
			'status'        => $data['status'] ?? 'active',
			'notes'         => $data['notes'] ?? '',
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s' );

		if ( ! empty( $data['id'] ) ) {
			// Update existing.
			$wpdb->update(
				$table,
				$fields,
				array( 'id' => $data['id'] ),
				$formats,
				array( '%d' )
			);
			return $data['id'];
		} else {
			// Insert new.
			$wpdb->insert( $table, $fields, $formats );
			return $wpdb->insert_id;
		}
	}

	/**
	 * Delete campaign.
	 *
	 * @param int $id Campaign ID.
	 * @return bool
	 */
	public function delete_campaign( $id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_roi_campaigns';

		$result = $wpdb->delete(
			$table,
			array( 'id' => $id ),
			array( '%d' )
		);

		// Also delete associated costs.
		$costs_table = $wpdb->prefix . 'bkx_roi_costs';
		$wpdb->delete(
			$costs_table,
			array( 'campaign_id' => $id ),
			array( '%d' )
		);

		return (bool) $result;
	}

	/**
	 * Add campaign cost.
	 *
	 * @param array $data Cost data.
	 * @return int|false Cost ID or false on failure.
	 */
	public function add_cost( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_roi_costs';

		$result = $wpdb->insert(
			$table,
			array(
				'campaign_id' => $data['campaign_id'],
				'cost_date'   => $data['cost_date'],
				'amount'      => $data['amount'],
				'cost_type'   => $data['cost_type'] ?? 'ad_spend',
				'notes'       => $data['notes'] ?? '',
			),
			array( '%d', '%s', '%f', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get campaign costs.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return array
	 */
	public function get_campaign_costs( $campaign_id, $start_date = '', $end_date = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_roi_costs';

		$where = $wpdb->prepare( 'campaign_id = %d', $campaign_id );

		if ( ! empty( $start_date ) ) {
			$where .= $wpdb->prepare( ' AND cost_date >= %s', $start_date );
		}

		if ( ! empty( $end_date ) ) {
			$where .= $wpdb->prepare( ' AND cost_date <= %s', $end_date );
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where} ORDER BY cost_date DESC",
				$table
			),
			ARRAY_A
		);
	}

	/**
	 * Get total campaign cost.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return float
	 */
	public function get_total_cost( $campaign_id, $start_date = '', $end_date = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_roi_costs';

		$where = $wpdb->prepare( 'campaign_id = %d', $campaign_id );

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
	 * Format campaign data.
	 *
	 * @param array $campaign Raw campaign data.
	 * @return array
	 */
	private function format_campaign( $campaign ) {
		return array(
			'id'            => (int) $campaign['id'],
			'campaign_name' => $campaign['campaign_name'],
			'utm_source'    => $campaign['utm_source'],
			'utm_medium'    => $campaign['utm_medium'],
			'utm_campaign'  => $campaign['utm_campaign'],
			'utm_content'   => $campaign['utm_content'],
			'utm_term'      => $campaign['utm_term'],
			'budget'        => (float) $campaign['budget'],
			'start_date'    => $campaign['start_date'],
			'end_date'      => $campaign['end_date'],
			'status'        => $campaign['status'],
			'notes'         => $campaign['notes'],
			'created_at'    => $campaign['created_at'],
		);
	}
}

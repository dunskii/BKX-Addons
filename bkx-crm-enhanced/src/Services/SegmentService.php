<?php
/**
 * Segment Service.
 *
 * @package BookingX\CRM
 */

namespace BookingX\CRM\Services;

defined( 'ABSPATH' ) || exit;

/**
 * SegmentService class.
 */
class SegmentService {

	/**
	 * Get segment by ID.
	 *
	 * @param int $segment_id Segment ID.
	 * @return object|null
	 */
	public function get( $segment_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_segments';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$segment_id
		) );
	}

	/**
	 * Get all segments.
	 *
	 * @return array
	 */
	public function get_all() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_segments';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$segments = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" );

		// Update counts for dynamic segments.
		foreach ( $segments as $segment ) {
			if ( $segment->is_dynamic ) {
				$segment->customer_count = $this->count_matching_customers( json_decode( $segment->conditions, true ) );
			}
		}

		return $segments;
	}

	/**
	 * Create segment.
	 *
	 * @param array $data Segment data.
	 * @return int|WP_Error
	 */
	public function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_segments';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, array(
			'name'        => $data['name'],
			'description' => $data['description'] ?? '',
			'conditions'  => $data['conditions'],
			'is_dynamic'  => $data['is_dynamic'] ?? 1,
			'created_by'  => $data['created_by'] ?? get_current_user_id(),
		) );

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create segment.', 'bkx-crm' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update segment.
	 *
	 * @param int   $segment_id Segment ID.
	 * @param array $data       Update data.
	 * @return int|WP_Error
	 */
	public function update( $segment_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_segments';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update( $table, $data, array( 'id' => $segment_id ) );

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to update segment.', 'bkx-crm' ) );
		}

		return $segment_id;
	}

	/**
	 * Delete segment.
	 *
	 * @param int $segment_id Segment ID.
	 * @return bool
	 */
	public function delete( $segment_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_segments';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->delete( $table, array( 'id' => $segment_id ), array( '%d' ) );
	}

	/**
	 * Get matching customers for conditions.
	 *
	 * @param array $conditions Segment conditions.
	 * @param int   $limit      Result limit.
	 * @return array
	 */
	public function get_matching_customers( $conditions, $limit = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_customers';

		$where = $this->build_where_clause( $conditions );

		if ( empty( $where ) ) {
			$where = '1=1';
		}

		$limit_sql = $limit > 0 ? $wpdb->prepare( 'LIMIT %d', $limit ) : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC {$limit_sql}" );
	}

	/**
	 * Count matching customers.
	 *
	 * @param array $conditions Segment conditions.
	 * @return int
	 */
	public function count_matching_customers( $conditions ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_customers';

		$where = $this->build_where_clause( $conditions );

		if ( empty( $where ) ) {
			$where = '1=1';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
	}

	/**
	 * Build WHERE clause from conditions.
	 *
	 * @param array $conditions Segment conditions.
	 * @return string
	 */
	public function build_where_clause( $conditions ) {
		global $wpdb;

		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			return '';
		}

		$clauses = array();

		foreach ( $conditions as $condition ) {
			$field    = $condition['field'] ?? '';
			$operator = $condition['operator'] ?? 'equals';
			$value    = $condition['value'] ?? '';

			if ( empty( $field ) ) {
				continue;
			}

			$clause = $this->build_condition_clause( $field, $operator, $value );

			if ( $clause ) {
				$clauses[] = $clause;
			}
		}

		if ( empty( $clauses ) ) {
			return '';
		}

		// Use AND by default, could be extended to support OR.
		return implode( ' AND ', $clauses );
	}

	/**
	 * Build single condition clause.
	 *
	 * @param string $field    Field name.
	 * @param string $operator Operator.
	 * @param mixed  $value    Value.
	 * @return string
	 */
	private function build_condition_clause( $field, $operator, $value ) {
		global $wpdb;

		$allowed_fields = array(
			'email',
			'first_name',
			'last_name',
			'phone',
			'company',
			'city',
			'state',
			'country',
			'total_bookings',
			'lifetime_value',
			'status',
			'source',
			'customer_since',
			'last_booking_date',
		);

		if ( ! in_array( $field, $allowed_fields, true ) ) {
			return '';
		}

		switch ( $operator ) {
			case 'equals':
				return $wpdb->prepare( "{$field} = %s", $value );

			case 'not_equals':
				return $wpdb->prepare( "{$field} != %s", $value );

			case 'contains':
				return $wpdb->prepare( "{$field} LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );

			case 'not_contains':
				return $wpdb->prepare( "{$field} NOT LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );

			case 'starts_with':
				return $wpdb->prepare( "{$field} LIKE %s", $wpdb->esc_like( $value ) . '%' );

			case 'ends_with':
				return $wpdb->prepare( "{$field} LIKE %s", '%' . $wpdb->esc_like( $value ) );

			case 'greater_than':
				return $wpdb->prepare( "{$field} > %s", $value );

			case 'less_than':
				return $wpdb->prepare( "{$field} < %s", $value );

			case 'greater_or_equal':
				return $wpdb->prepare( "{$field} >= %s", $value );

			case 'less_or_equal':
				return $wpdb->prepare( "{$field} <= %s", $value );

			case 'is_empty':
				return "({$field} IS NULL OR {$field} = '')";

			case 'is_not_empty':
				return "({$field} IS NOT NULL AND {$field} != '')";

			case 'in_last_days':
				return $wpdb->prepare( "{$field} >= DATE_SUB(NOW(), INTERVAL %d DAY)", absint( $value ) );

			case 'not_in_last_days':
				return $wpdb->prepare( "{$field} < DATE_SUB(NOW(), INTERVAL %d DAY)", absint( $value ) );

			default:
				return '';
		}
	}

	/**
	 * REST: Get segments.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_segments( $request ) {
		$segments = $this->get_all();
		return new \WP_REST_Response( $segments );
	}
}

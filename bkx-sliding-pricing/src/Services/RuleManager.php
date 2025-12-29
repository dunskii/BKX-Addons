<?php
/**
 * Rule Manager Service.
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
 * RuleManager Class.
 */
class RuleManager {

	/**
	 * Rule types.
	 *
	 * @var array
	 */
	private $rule_types = array(
		'early_bird'    => 'Early Bird Discount',
		'last_minute'   => 'Last Minute Deal',
		'demand_based'  => 'Demand-Based Pricing',
		'quantity'      => 'Quantity Discount',
		'customer_type' => 'Customer Type',
		'custom'        => 'Custom Rule',
	);

	/**
	 * Save a rule.
	 *
	 * @param array $data Rule data.
	 * @return int|\WP_Error Rule ID or error.
	 */
	public function save_rule( $data ) {
		global $wpdb;

		// Validate.
		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'Rule name is required', 'bkx-sliding-pricing' ) );
		}

		if ( empty( $data['rule_type'] ) ) {
			return new \WP_Error( 'missing_type', __( 'Rule type is required', 'bkx-sliding-pricing' ) );
		}

		$table = $wpdb->prefix . 'bkx_pricing_rules';

		$rule_data = array(
			'name'             => sanitize_text_field( $data['name'] ),
			'rule_type'        => sanitize_text_field( $data['rule_type'] ),
			'applies_to'       => sanitize_text_field( $data['applies_to'] ?? 'all' ),
			'service_ids'      => maybe_serialize( $data['service_ids'] ?? array() ),
			'staff_ids'        => maybe_serialize( $data['staff_ids'] ?? array() ),
			'priority'         => absint( $data['priority'] ?? 10 ),
			'adjustment_type'  => sanitize_text_field( $data['adjustment_type'] ?? 'percentage' ),
			'adjustment_value' => floatval( $data['adjustment_value'] ?? 0 ),
			'conditions'       => maybe_serialize( $data['conditions'] ?? array() ),
			'start_date'       => ! empty( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : null,
			'end_date'         => ! empty( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
			'is_active'        => isset( $data['is_active'] ) ? 1 : 0,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%d' );

		if ( ! empty( $data['id'] ) ) {
			// Update existing rule.
			$result = $wpdb->update(
				$table,
				$rule_data,
				array( 'id' => absint( $data['id'] ) ),
				$formats,
				array( '%d' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Database error occurred', 'bkx-sliding-pricing' ) );
			}

			$this->clear_cache();

			return absint( $data['id'] );
		} else {
			// Insert new rule.
			$result = $wpdb->insert( $table, $rule_data, $formats );

			if ( ! $result ) {
				return new \WP_Error( 'db_error', __( 'Database error occurred', 'bkx-sliding-pricing' ) );
			}

			$this->clear_cache();

			return $wpdb->insert_id;
		}
	}

	/**
	 * Delete a rule.
	 *
	 * @param int $rule_id Rule ID.
	 * @return bool
	 */
	public function delete_rule( $rule_id ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'bkx_pricing_rules';
		$result = $wpdb->delete( $table, array( 'id' => absint( $rule_id ) ), array( '%d' ) );

		if ( $result ) {
			$this->clear_cache();
		}

		return $result !== false;
	}

	/**
	 * Get a rule by ID.
	 *
	 * @param int $rule_id Rule ID.
	 * @return array|null
	 */
	public function get_rule( $rule_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_pricing_rules';

		$rule = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $rule_id ),
			ARRAY_A
		);

		if ( $rule ) {
			$rule['service_ids'] = maybe_unserialize( $rule['service_ids'] );
			$rule['staff_ids']   = maybe_unserialize( $rule['staff_ids'] );
			$rule['conditions']  = maybe_unserialize( $rule['conditions'] );
		}

		return $rule;
	}

	/**
	 * Get all rules.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_rules( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'active_only' => false,
			'rule_type'   => '',
			'orderby'     => 'priority',
			'order'       => 'ASC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_pricing_rules';

		$where = 'WHERE 1=1';

		if ( $args['active_only'] ) {
			$where .= ' AND is_active = 1';
		}

		if ( ! empty( $args['rule_type'] ) ) {
			$where .= $wpdb->prepare( ' AND rule_type = %s', $args['rule_type'] );
		}

		$orderby = in_array( $args['orderby'], array( 'priority', 'name', 'created_at' ), true ) ? $args['orderby'] : 'priority';
		$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$rules = $wpdb->get_results(
			"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}",
			ARRAY_A
		);

		foreach ( $rules as &$rule ) {
			$rule['service_ids']   = maybe_unserialize( $rule['service_ids'] );
			$rule['staff_ids']     = maybe_unserialize( $rule['staff_ids'] );
			$rule['conditions']    = maybe_unserialize( $rule['conditions'] );
			$rule['type_label']    = $this->rule_types[ $rule['rule_type'] ] ?? $rule['rule_type'];
		}

		return $rules;
	}

	/**
	 * Get rule types.
	 *
	 * @return array
	 */
	public function get_rule_types() {
		return $this->rule_types;
	}

	/**
	 * Duplicate a rule.
	 *
	 * @param int $rule_id Rule ID.
	 * @return int|\WP_Error New rule ID or error.
	 */
	public function duplicate_rule( $rule_id ) {
		$rule = $this->get_rule( $rule_id );

		if ( ! $rule ) {
			return new \WP_Error( 'not_found', __( 'Rule not found', 'bkx-sliding-pricing' ) );
		}

		unset( $rule['id'] );
		$rule['name']      = $rule['name'] . ' (Copy)';
		$rule['is_active'] = 0;

		return $this->save_rule( $rule );
	}

	/**
	 * Toggle rule active status.
	 *
	 * @param int $rule_id Rule ID.
	 * @return bool
	 */
	public function toggle_active( $rule_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_pricing_rules';

		$current = $wpdb->get_var(
			$wpdb->prepare( "SELECT is_active FROM {$table} WHERE id = %d", $rule_id )
		);

		$new_status = $current ? 0 : 1;

		$result = $wpdb->update(
			$table,
			array( 'is_active' => $new_status ),
			array( 'id' => $rule_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( $result ) {
			$this->clear_cache();
		}

		return $result !== false;
	}

	/**
	 * Reorder rules.
	 *
	 * @param array $order Array of rule IDs in order.
	 * @return bool
	 */
	public function reorder( $order ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_pricing_rules';

		foreach ( $order as $priority => $rule_id ) {
			$wpdb->update(
				$table,
				array( 'priority' => $priority ),
				array( 'id' => absint( $rule_id ) ),
				array( '%d' ),
				array( '%d' )
			);
		}

		$this->clear_cache();

		return true;
	}

	/**
	 * Create default rules.
	 */
	public function create_default_rules() {
		// Early bird discount (14+ days ahead).
		$this->save_rule(
			array(
				'name'             => __( 'Early Bird - 10% Off', 'bkx-sliding-pricing' ),
				'rule_type'        => 'early_bird',
				'applies_to'       => 'all',
				'priority'         => 10,
				'adjustment_type'  => 'percentage',
				'adjustment_value' => -10,
				'conditions'       => array(
					array(
						'type'     => 'days_before',
						'operator' => '>=',
						'value'    => '14',
					),
				),
				'is_active'        => 0,
			)
		);

		// Last minute deal.
		$this->save_rule(
			array(
				'name'             => __( 'Last Minute - 15% Off', 'bkx-sliding-pricing' ),
				'rule_type'        => 'last_minute',
				'applies_to'       => 'all',
				'priority'         => 20,
				'adjustment_type'  => 'percentage',
				'adjustment_value' => -15,
				'conditions'       => array(
					array(
						'type'     => 'days_before',
						'operator' => '<=',
						'value'    => '1',
					),
					array(
						'type'     => 'availability',
						'operator' => '>',
						'value'    => '50',
					),
				),
				'is_active'        => 0,
			)
		);

		// High demand surcharge.
		$this->save_rule(
			array(
				'name'             => __( 'High Demand - 10% Premium', 'bkx-sliding-pricing' ),
				'rule_type'        => 'demand_based',
				'applies_to'       => 'all',
				'priority'         => 30,
				'adjustment_type'  => 'percentage',
				'adjustment_value' => 10,
				'conditions'       => array(
					array(
						'type'     => 'availability',
						'operator' => '<',
						'value'    => '20',
					),
				),
				'is_active'        => 0,
			)
		);
	}

	/**
	 * Clear pricing cache.
	 */
	private function clear_cache() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_bkx_pricing_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_bkx_pricing_%'" );
	}
}

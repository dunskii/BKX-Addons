<?php
/**
 * Plan Manager Service.
 *
 * @package BookingX\MultiTenant\Services
 */

namespace BookingX\MultiTenant\Services;

defined( 'ABSPATH' ) || exit;

/**
 * PlanManager class.
 */
class PlanManager {

	/**
	 * Get all plans.
	 *
	 * @param bool $active_only Only active plans.
	 * @return array
	 */
	public function get_plans( $active_only = false ) {
		global $wpdb;

		$where = $active_only ? 'WHERE is_active = 1' : '';

		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->base_prefix}bkx_tenant_plans {$where} ORDER BY sort_order ASC"
		);
	}

	/**
	 * Get plan by ID.
	 *
	 * @param int $plan_id Plan ID.
	 * @return object|null
	 */
	public function get_plan( $plan_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->base_prefix}bkx_tenant_plans WHERE id = %d",
				$plan_id
			)
		);
	}

	/**
	 * Get plan by slug.
	 *
	 * @param string $slug Plan slug.
	 * @return object|null
	 */
	public function get_plan_by_slug( $slug ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->base_prefix}bkx_tenant_plans WHERE slug = %s",
				$slug
			)
		);
	}

	/**
	 * Get tenant's plan.
	 *
	 * @param int $tenant_id Tenant ID.
	 * @return object|null
	 */
	public function get_tenant_plan( $tenant_id ) {
		global $wpdb;

		$plan_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT plan_id FROM {$wpdb->base_prefix}bkx_tenants WHERE id = %d",
				$tenant_id
			)
		);

		if ( ! $plan_id ) {
			return null;
		}

		return $this->get_plan( $plan_id );
	}

	/**
	 * Create plan.
	 *
	 * @param array $data Plan data.
	 * @return int|WP_Error
	 */
	public function create_plan( $data ) {
		global $wpdb;

		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'Plan name is required.', 'bkx-multi-tenant' ) );
		}

		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		$exists = $this->get_plan_by_slug( $data['slug'] );
		if ( $exists ) {
			return new \WP_Error( 'duplicate_slug', __( 'A plan with this slug already exists.', 'bkx-multi-tenant' ) );
		}

		$result = $wpdb->insert(
			$wpdb->base_prefix . 'bkx_tenant_plans',
			array(
				'name'          => sanitize_text_field( $data['name'] ),
				'slug'          => sanitize_title( $data['slug'] ),
				'description'   => sanitize_textarea_field( $data['description'] ?? '' ),
				'price'         => floatval( $data['price'] ?? 0 ),
				'billing_cycle' => sanitize_text_field( $data['billing_cycle'] ?? 'monthly' ),
				'limits'        => wp_json_encode( $data['limits'] ?? array() ),
				'features'      => wp_json_encode( $data['features'] ?? array() ),
				'is_active'     => isset( $data['is_active'] ) ? 1 : 0,
				'sort_order'    => absint( $data['sort_order'] ?? 0 ),
			)
		);

		if ( ! $result ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create plan.', 'bkx-multi-tenant' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update plan.
	 *
	 * @param int   $plan_id Plan ID.
	 * @param array $data    Plan data.
	 * @return bool|WP_Error
	 */
	public function update_plan( $plan_id, $data ) {
		global $wpdb;

		$plan = $this->get_plan( $plan_id );
		if ( ! $plan ) {
			return new \WP_Error( 'not_found', __( 'Plan not found.', 'bkx-multi-tenant' ) );
		}

		$update_data = array();

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
		}

		if ( isset( $data['price'] ) ) {
			$update_data['price'] = floatval( $data['price'] );
		}

		if ( isset( $data['billing_cycle'] ) ) {
			$update_data['billing_cycle'] = sanitize_text_field( $data['billing_cycle'] );
		}

		if ( isset( $data['limits'] ) ) {
			$update_data['limits'] = wp_json_encode( $data['limits'] );
		}

		if ( isset( $data['features'] ) ) {
			$update_data['features'] = wp_json_encode( $data['features'] );
		}

		if ( isset( $data['is_active'] ) ) {
			$update_data['is_active'] = $data['is_active'] ? 1 : 0;
		}

		if ( isset( $data['sort_order'] ) ) {
			$update_data['sort_order'] = absint( $data['sort_order'] );
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		$result = $wpdb->update(
			$wpdb->base_prefix . 'bkx_tenant_plans',
			$update_data,
			array( 'id' => $plan_id )
		);

		return $result !== false;
	}

	/**
	 * Delete plan.
	 *
	 * @param int $plan_id Plan ID.
	 * @return bool|WP_Error
	 */
	public function delete_plan( $plan_id ) {
		global $wpdb;

		// Check if plan is in use.
		$tenants_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->base_prefix}bkx_tenants WHERE plan_id = %d",
				$plan_id
			)
		);

		if ( $tenants_count > 0 ) {
			return new \WP_Error(
				'plan_in_use',
				/* translators: %d: number of tenants */
				sprintf( __( 'Cannot delete plan. %d tenant(s) are using it.', 'bkx-multi-tenant' ), $tenants_count )
			);
		}

		$result = $wpdb->delete(
			$wpdb->base_prefix . 'bkx_tenant_plans',
			array( 'id' => $plan_id )
		);

		return $result !== false;
	}

	/**
	 * Get plan limits.
	 *
	 * @param int $plan_id Plan ID.
	 * @return array
	 */
	public function get_plan_limits( $plan_id ) {
		$plan = $this->get_plan( $plan_id );

		if ( ! $plan ) {
			return array();
		}

		return json_decode( $plan->limits, true ) ?: array();
	}

	/**
	 * Get plan features.
	 *
	 * @param int $plan_id Plan ID.
	 * @return array
	 */
	public function get_plan_features( $plan_id ) {
		$plan = $this->get_plan( $plan_id );

		if ( ! $plan ) {
			return array();
		}

		return json_decode( $plan->features, true ) ?: array();
	}

	/**
	 * Check if tenant has feature.
	 *
	 * @param int    $tenant_id Tenant ID.
	 * @param string $feature   Feature key.
	 * @return bool
	 */
	public function tenant_has_feature( $tenant_id, $feature ) {
		$plan = $this->get_tenant_plan( $tenant_id );

		if ( ! $plan ) {
			return false;
		}

		$features = json_decode( $plan->features, true ) ?: array();

		return in_array( $feature, $features, true );
	}

	/**
	 * Assign plan to tenant.
	 *
	 * @param int $tenant_id Tenant ID.
	 * @param int $plan_id   Plan ID.
	 * @return bool
	 */
	public function assign_plan_to_tenant( $tenant_id, $plan_id ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->base_prefix . 'bkx_tenants',
			array( 'plan_id' => $plan_id ),
			array( 'id' => $tenant_id )
		);

		if ( $result !== false ) {
			do_action( 'bkx_tenant_plan_changed', $tenant_id, $plan_id );
		}

		return $result !== false;
	}
}

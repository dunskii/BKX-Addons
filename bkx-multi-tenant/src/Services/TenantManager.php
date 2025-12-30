<?php
/**
 * Tenant Manager Service.
 *
 * @package BookingX\MultiTenant\Services
 */

namespace BookingX\MultiTenant\Services;

defined( 'ABSPATH' ) || exit;

/**
 * TenantManager class.
 */
class TenantManager {

	/**
	 * Get all tenants.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_tenants( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => '',
			'plan_id' => 0,
			'search'  => '',
			'limit'   => 50,
			'offset'  => 0,
			'orderby' => 'created_at',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['plan_id'] ) ) {
			$where[] = 'plan_id = %d';
			$values[] = $args['plan_id'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[] = '(name LIKE %s OR slug LIKE %s OR domain LIKE %s)';
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		$where_clause = implode( ' AND ', $where );
		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare(
				"SELECT t.*, p.name as plan_name
				FROM {$wpdb->base_prefix}bkx_tenants t
				LEFT JOIN {$wpdb->base_prefix}bkx_tenant_plans p ON t.plan_id = p.id
				WHERE {$where_clause}
				ORDER BY {$orderby}
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $values, array( $args['limit'], $args['offset'] ) )
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT t.*, p.name as plan_name
				FROM {$wpdb->base_prefix}bkx_tenants t
				LEFT JOIN {$wpdb->base_prefix}bkx_tenant_plans p ON t.plan_id = p.id
				WHERE {$where_clause}
				ORDER BY {$orderby}
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$args['limit'],
				$args['offset']
			);
		}

		return $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get tenant by ID.
	 *
	 * @param int $tenant_id Tenant ID.
	 * @return object|null
	 */
	public function get_tenant( $tenant_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->base_prefix}bkx_tenants WHERE id = %d",
				$tenant_id
			)
		);
	}

	/**
	 * Get tenant by site.
	 *
	 * @param int $site_id Site ID.
	 * @return object|null
	 */
	public function get_tenant_by_site( $site_id ) {
		global $wpdb;

		$tenant_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tenant_id FROM {$wpdb->base_prefix}bkx_tenant_sites WHERE site_id = %d",
				$site_id
			)
		);

		if ( ! $tenant_id ) {
			return null;
		}

		return $this->get_tenant( $tenant_id );
	}

	/**
	 * Get tenant by domain.
	 *
	 * @param string $domain Domain URL.
	 * @return object|null
	 */
	public function get_tenant_by_domain( $domain ) {
		global $wpdb;

		$domain = wp_parse_url( $domain, PHP_URL_HOST );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->base_prefix}bkx_tenants WHERE domain LIKE %s",
				'%' . $wpdb->esc_like( $domain ) . '%'
			)
		);
	}

	/**
	 * Get tenant by slug.
	 *
	 * @param string $slug Tenant slug.
	 * @return object|null
	 */
	public function get_tenant_by_slug( $slug ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->base_prefix}bkx_tenants WHERE slug = %s",
				$slug
			)
		);
	}

	/**
	 * Create tenant.
	 *
	 * @param array $data Tenant data.
	 * @return int|WP_Error
	 */
	public function create_tenant( $data ) {
		global $wpdb;

		// Validate required fields.
		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'Tenant name is required.', 'bkx-multi-tenant' ) );
		}

		// Generate slug if not provided.
		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		// Check for duplicate slug.
		$exists = $this->get_tenant_by_slug( $data['slug'] );
		if ( $exists ) {
			return new \WP_Error( 'duplicate_slug', __( 'A tenant with this slug already exists.', 'bkx-multi-tenant' ) );
		}

		$result = $wpdb->insert(
			$wpdb->base_prefix . 'bkx_tenants',
			array(
				'name'     => sanitize_text_field( $data['name'] ),
				'slug'     => sanitize_title( $data['slug'] ),
				'status'   => sanitize_text_field( $data['status'] ?? 'active' ),
				'owner_id' => absint( $data['owner_id'] ?? 0 ) ?: null,
				'plan_id'  => absint( $data['plan_id'] ?? 0 ) ?: null,
				'domain'   => sanitize_text_field( $data['domain'] ?? '' ) ?: null,
				'settings' => wp_json_encode( $data['settings'] ?? array() ),
				'branding' => wp_json_encode( $data['branding'] ?? array() ),
				'limits'   => wp_json_encode( $data['limits'] ?? array() ),
			)
		);

		if ( ! $result ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create tenant.', 'bkx-multi-tenant' ) );
		}

		$tenant_id = $wpdb->insert_id;

		// Create site mapping if multisite.
		if ( is_multisite() && ! empty( $data['site_id'] ) ) {
			$this->add_site_to_tenant( $tenant_id, absint( $data['site_id'] ), true );
		}

		// Add owner as admin.
		if ( ! empty( $data['owner_id'] ) ) {
			$user_manager = \BookingX\MultiTenant\MultiTenantAddon::get_instance()->get_service( 'user_manager' );
			$user_manager->add_user_to_tenant( $tenant_id, $data['owner_id'], 'admin' );
		}

		do_action( 'bkx_tenant_created', $tenant_id, $data );

		return $tenant_id;
	}

	/**
	 * Update tenant.
	 *
	 * @param int   $tenant_id Tenant ID.
	 * @param array $data      Tenant data.
	 * @return bool|WP_Error
	 */
	public function update_tenant( $tenant_id, $data ) {
		global $wpdb;

		$tenant = $this->get_tenant( $tenant_id );
		if ( ! $tenant ) {
			return new \WP_Error( 'not_found', __( 'Tenant not found.', 'bkx-multi-tenant' ) );
		}

		$update_data = array();

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_text_field( $data['status'] );
		}

		if ( isset( $data['plan_id'] ) ) {
			$update_data['plan_id'] = absint( $data['plan_id'] ) ?: null;
		}

		if ( isset( $data['domain'] ) ) {
			$update_data['domain'] = sanitize_text_field( $data['domain'] ) ?: null;
		}

		if ( isset( $data['settings'] ) ) {
			$update_data['settings'] = wp_json_encode( $data['settings'] );
		}

		if ( isset( $data['branding'] ) ) {
			$update_data['branding'] = wp_json_encode( $data['branding'] );
		}

		if ( isset( $data['limits'] ) ) {
			$update_data['limits'] = wp_json_encode( $data['limits'] );
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		$result = $wpdb->update(
			$wpdb->base_prefix . 'bkx_tenants',
			$update_data,
			array( 'id' => $tenant_id )
		);

		if ( $result === false ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update tenant.', 'bkx-multi-tenant' ) );
		}

		do_action( 'bkx_tenant_updated', $tenant_id, $data );

		return true;
	}

	/**
	 * Delete tenant.
	 *
	 * @param int $tenant_id Tenant ID.
	 * @return bool|WP_Error
	 */
	public function delete_tenant( $tenant_id ) {
		global $wpdb;

		$tenant = $this->get_tenant( $tenant_id );
		if ( ! $tenant ) {
			return new \WP_Error( 'not_found', __( 'Tenant not found.', 'bkx-multi-tenant' ) );
		}

		do_action( 'bkx_before_tenant_delete', $tenant_id );

		// Delete site mappings.
		$wpdb->delete( $wpdb->base_prefix . 'bkx_tenant_sites', array( 'tenant_id' => $tenant_id ) );

		// Delete user mappings.
		$wpdb->delete( $wpdb->base_prefix . 'bkx_tenant_users', array( 'tenant_id' => $tenant_id ) );

		// Delete usage data.
		$wpdb->delete( $wpdb->base_prefix . 'bkx_tenant_usage', array( 'tenant_id' => $tenant_id ) );

		// Delete tenant.
		$result = $wpdb->delete( $wpdb->base_prefix . 'bkx_tenants', array( 'id' => $tenant_id ) );

		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete tenant.', 'bkx-multi-tenant' ) );
		}

		do_action( 'bkx_tenant_deleted', $tenant_id );

		return true;
	}

	/**
	 * Add site to tenant.
	 *
	 * @param int  $tenant_id  Tenant ID.
	 * @param int  $site_id    Site ID.
	 * @param bool $is_primary Is primary site.
	 * @return bool
	 */
	public function add_site_to_tenant( $tenant_id, $site_id, $is_primary = false ) {
		global $wpdb;

		return $wpdb->insert(
			$wpdb->base_prefix . 'bkx_tenant_sites',
			array(
				'tenant_id'  => $tenant_id,
				'site_id'    => $site_id,
				'is_primary' => $is_primary ? 1 : 0,
			)
		) !== false;
	}

	/**
	 * Get tenant sites.
	 *
	 * @param int $tenant_id Tenant ID.
	 * @return array
	 */
	public function get_tenant_sites( $tenant_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->base_prefix}bkx_tenant_sites WHERE tenant_id = %d",
				$tenant_id
			)
		);
	}

	/**
	 * Get tenant count.
	 *
	 * @param string $status Status filter.
	 * @return int
	 */
	public function get_tenant_count( $status = '' ) {
		global $wpdb;

		if ( $status ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->base_prefix}bkx_tenants WHERE status = %s",
					$status
				)
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}bkx_tenants" );
	}

	/**
	 * REST: Get tenants.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_tenants( $request ) {
		$tenants = $this->get_tenants(
			array(
				'status'  => $request->get_param( 'status' ),
				'search'  => $request->get_param( 'search' ),
				'limit'   => $request->get_param( 'per_page' ) ?: 50,
				'offset'  => ( ( $request->get_param( 'page' ) ?: 1 ) - 1 ) * ( $request->get_param( 'per_page' ) ?: 50 ),
			)
		);

		return rest_ensure_response( $tenants );
	}

	/**
	 * REST: Get tenant.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_get_tenant( $request ) {
		$tenant = $this->get_tenant( $request->get_param( 'id' ) );

		if ( ! $tenant ) {
			return new \WP_Error( 'not_found', __( 'Tenant not found.', 'bkx-multi-tenant' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $tenant );
	}

	/**
	 * REST: Create tenant.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_create_tenant( $request ) {
		$result = $this->create_tenant( $request->get_json_params() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'id'      => $result,
				'message' => __( 'Tenant created successfully.', 'bkx-multi-tenant' ),
			)
		);
	}

	/**
	 * REST: Update tenant.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_update_tenant( $request ) {
		$result = $this->update_tenant(
			$request->get_param( 'id' ),
			$request->get_json_params()
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message' => __( 'Tenant updated successfully.', 'bkx-multi-tenant' ),
			)
		);
	}

	/**
	 * REST: Delete tenant.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_delete_tenant( $request ) {
		$result = $this->delete_tenant( $request->get_param( 'id' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message' => __( 'Tenant deleted successfully.', 'bkx-multi-tenant' ),
			)
		);
	}
}

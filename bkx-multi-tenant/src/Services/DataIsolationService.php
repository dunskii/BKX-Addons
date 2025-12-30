<?php
/**
 * Data Isolation Service.
 *
 * @package BookingX\MultiTenant\Services
 */

namespace BookingX\MultiTenant\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DataIsolationService class.
 */
class DataIsolationService {

	/**
	 * Current tenant.
	 *
	 * @var object|null
	 */
	private $tenant = null;

	/**
	 * Set tenant.
	 *
	 * @param object $tenant Tenant.
	 */
	public function set_tenant( $tenant ) {
		$this->tenant = $tenant;
	}

	/**
	 * Get current tenant.
	 *
	 * @return object|null
	 */
	public function get_tenant() {
		return $this->tenant;
	}

	/**
	 * Filter booking query.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function filter_booking_query( $args ) {
		if ( ! $this->tenant || $this->is_network_admin() ) {
			return $args;
		}

		// Add tenant meta query.
		if ( ! isset( $args['meta_query'] ) ) {
			$args['meta_query'] = array();
		}

		$args['meta_query'][] = array(
			'key'   => '_bkx_tenant_id',
			'value' => $this->tenant->id,
		);

		return $args;
	}

	/**
	 * Filter service query.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function filter_service_query( $args ) {
		if ( ! $this->tenant || $this->is_network_admin() ) {
			return $args;
		}

		if ( ! isset( $args['meta_query'] ) ) {
			$args['meta_query'] = array();
		}

		$args['meta_query'][] = array(
			'key'   => '_bkx_tenant_id',
			'value' => $this->tenant->id,
		);

		return $args;
	}

	/**
	 * Tag entity with tenant.
	 *
	 * @param int    $entity_id   Entity ID (post ID).
	 * @param int    $tenant_id   Tenant ID.
	 * @param string $entity_type Entity type.
	 */
	public function tag_entity( $entity_id, $tenant_id = null, $entity_type = 'post' ) {
		if ( ! $tenant_id && $this->tenant ) {
			$tenant_id = $this->tenant->id;
		}

		if ( ! $tenant_id ) {
			return;
		}

		update_post_meta( $entity_id, '_bkx_tenant_id', $tenant_id );
	}

	/**
	 * Check if entity belongs to tenant.
	 *
	 * @param int $entity_id Entity ID.
	 * @param int $tenant_id Tenant ID.
	 * @return bool
	 */
	public function entity_belongs_to_tenant( $entity_id, $tenant_id = null ) {
		if ( ! $tenant_id && $this->tenant ) {
			$tenant_id = $this->tenant->id;
		}

		if ( ! $tenant_id ) {
			return true;
		}

		$entity_tenant = get_post_meta( $entity_id, '_bkx_tenant_id', true );

		return (int) $entity_tenant === (int) $tenant_id;
	}

	/**
	 * Get tenant's entities.
	 *
	 * @param int    $tenant_id  Tenant ID.
	 * @param string $post_type  Post type.
	 * @param array  $extra_args Extra query arguments.
	 * @return array
	 */
	public function get_tenant_entities( $tenant_id, $post_type, $extra_args = array() ) {
		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'meta_query'     => array(
				array(
					'key'   => '_bkx_tenant_id',
					'value' => $tenant_id,
				),
			),
		);

		$args = wp_parse_args( $extra_args, $args );

		return get_posts( $args );
	}

	/**
	 * Check if current user can access tenant data.
	 *
	 * @param int $tenant_id Tenant ID.
	 * @return bool
	 */
	public function can_access_tenant_data( $tenant_id ) {
		// Network admins can access all.
		if ( is_super_admin() ) {
			return true;
		}

		// Check if user belongs to tenant.
		$user_manager = \BookingX\MultiTenant\MultiTenantAddon::get_instance()->get_service( 'user_manager' );
		$user_id = get_current_user_id();

		return $user_manager->user_belongs_to_tenant( $tenant_id, $user_id );
	}

	/**
	 * Migrate entity to tenant.
	 *
	 * @param int $entity_id Entity ID.
	 * @param int $from_tenant_id Source tenant ID.
	 * @param int $to_tenant_id Target tenant ID.
	 * @return bool
	 */
	public function migrate_entity( $entity_id, $from_tenant_id, $to_tenant_id ) {
		$current_tenant = get_post_meta( $entity_id, '_bkx_tenant_id', true );

		if ( (int) $current_tenant !== (int) $from_tenant_id ) {
			return false;
		}

		update_post_meta( $entity_id, '_bkx_tenant_id', $to_tenant_id );
		update_post_meta( $entity_id, '_bkx_previous_tenant_id', $from_tenant_id );

		do_action( 'bkx_entity_migrated', $entity_id, $from_tenant_id, $to_tenant_id );

		return true;
	}

	/**
	 * Check if in network admin.
	 *
	 * @return bool
	 */
	private function is_network_admin() {
		return is_multisite() && is_network_admin();
	}

	/**
	 * Get isolated table name.
	 *
	 * @param string $table Base table name.
	 * @return string
	 */
	public function get_tenant_table( $table ) {
		global $wpdb;

		if ( is_multisite() && $this->tenant ) {
			// Use site-specific tables in multisite.
			return $wpdb->prefix . $table;
		}

		return $wpdb->prefix . $table;
	}

	/**
	 * Apply tenant context to SQL query.
	 *
	 * @param string $sql    SQL query.
	 * @param string $table  Table name.
	 * @param string $column Tenant column name.
	 * @return string
	 */
	public function apply_tenant_filter_to_sql( $sql, $table, $column = 'tenant_id' ) {
		if ( ! $this->tenant ) {
			return $sql;
		}

		// Check if WHERE clause exists.
		if ( stripos( $sql, 'WHERE' ) !== false ) {
			$sql = preg_replace(
				'/WHERE/i',
				"WHERE {$table}.{$column} = " . (int) $this->tenant->id . ' AND ',
				$sql,
				1
			);
		} else {
			// Add WHERE clause before ORDER BY, LIMIT, or end.
			$sql = preg_replace(
				'/(ORDER BY|LIMIT|$)/i',
				"WHERE {$table}.{$column} = " . (int) $this->tenant->id . ' $1',
				$sql,
				1
			);
		}

		return $sql;
	}
}

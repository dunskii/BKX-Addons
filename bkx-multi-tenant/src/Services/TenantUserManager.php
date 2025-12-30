<?php
/**
 * Tenant User Manager Service.
 *
 * @package BookingX\MultiTenant\Services
 */

namespace BookingX\MultiTenant\Services;

defined( 'ABSPATH' ) || exit;

/**
 * TenantUserManager class.
 */
class TenantUserManager {

	/**
	 * Available roles.
	 *
	 * @var array
	 */
	private $roles = array(
		'admin'   => 'Administrator',
		'manager' => 'Manager',
		'staff'   => 'Staff',
		'member'  => 'Member',
	);

	/**
	 * Get tenant users.
	 *
	 * @param int   $tenant_id Tenant ID.
	 * @param array $args      Query arguments.
	 * @return array
	 */
	public function get_tenant_users( $tenant_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'role'   => '',
			'limit'  => 50,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( 'tu.tenant_id = %d' );
		$values = array( $tenant_id );

		if ( ! empty( $args['role'] ) ) {
			$where[] = 'tu.role = %s';
			$values[] = $args['role'];
		}

		$where_clause = implode( ' AND ', $where );
		$values[] = $args['limit'];
		$values[] = $args['offset'];

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tu.*, u.display_name, u.user_email
				FROM {$wpdb->base_prefix}bkx_tenant_users tu
				LEFT JOIN {$wpdb->users} u ON tu.user_id = u.ID
				WHERE {$where_clause}
				ORDER BY tu.created_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$values
			)
		);
	}

	/**
	 * Get user's tenants.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_user_tenants( $user_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tu.*, t.name as tenant_name, t.slug as tenant_slug, t.status as tenant_status
				FROM {$wpdb->base_prefix}bkx_tenant_users tu
				LEFT JOIN {$wpdb->base_prefix}bkx_tenants t ON tu.tenant_id = t.id
				WHERE tu.user_id = %d",
				$user_id
			)
		);
	}

	/**
	 * Add user to tenant.
	 *
	 * @param int    $tenant_id   Tenant ID.
	 * @param int    $user_id     User ID.
	 * @param string $role        Role.
	 * @param array  $permissions Custom permissions.
	 * @return bool|WP_Error
	 */
	public function add_user_to_tenant( $tenant_id, $user_id, $role = 'member', $permissions = array() ) {
		global $wpdb;

		// Check if already exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->base_prefix}bkx_tenant_users
				WHERE tenant_id = %d AND user_id = %d",
				$tenant_id,
				$user_id
			)
		);

		if ( $exists ) {
			return new \WP_Error( 'already_member', __( 'User is already a member of this tenant.', 'bkx-multi-tenant' ) );
		}

		$result = $wpdb->insert(
			$wpdb->base_prefix . 'bkx_tenant_users',
			array(
				'tenant_id'   => $tenant_id,
				'user_id'     => $user_id,
				'role'        => sanitize_text_field( $role ),
				'permissions' => wp_json_encode( $permissions ),
			)
		);

		if ( ! $result ) {
			return new \WP_Error( 'add_failed', __( 'Failed to add user to tenant.', 'bkx-multi-tenant' ) );
		}

		do_action( 'bkx_user_added_to_tenant', $tenant_id, $user_id, $role );

		return true;
	}

	/**
	 * Remove user from tenant.
	 *
	 * @param int $tenant_id Tenant ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	public function remove_user_from_tenant( $tenant_id, $user_id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$wpdb->base_prefix . 'bkx_tenant_users',
			array(
				'tenant_id' => $tenant_id,
				'user_id'   => $user_id,
			)
		);

		if ( $result ) {
			do_action( 'bkx_user_removed_from_tenant', $tenant_id, $user_id );
		}

		return $result !== false;
	}

	/**
	 * Update user role.
	 *
	 * @param int    $tenant_id Tenant ID.
	 * @param int    $user_id   User ID.
	 * @param string $role      New role.
	 * @return bool
	 */
	public function update_user_role( $tenant_id, $user_id, $role ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->base_prefix . 'bkx_tenant_users',
			array( 'role' => sanitize_text_field( $role ) ),
			array(
				'tenant_id' => $tenant_id,
				'user_id'   => $user_id,
			)
		) !== false;
	}

	/**
	 * Get user role in tenant.
	 *
	 * @param int $tenant_id Tenant ID.
	 * @param int $user_id   User ID.
	 * @return string|null
	 */
	public function get_user_role( $tenant_id, $user_id ) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT role FROM {$wpdb->base_prefix}bkx_tenant_users
				WHERE tenant_id = %d AND user_id = %d",
				$tenant_id,
				$user_id
			)
		);
	}

	/**
	 * Check if user belongs to tenant.
	 *
	 * @param int $tenant_id Tenant ID.
	 * @param int $user_id   User ID.
	 * @return bool
	 */
	public function user_belongs_to_tenant( $tenant_id, $user_id ) {
		return $this->get_user_role( $tenant_id, $user_id ) !== null;
	}

	/**
	 * Get available roles.
	 *
	 * @return array
	 */
	public function get_roles() {
		return $this->roles;
	}

	/**
	 * Get role permissions.
	 *
	 * @param string $role Role.
	 * @return array
	 */
	public function get_role_permissions( $role ) {
		$permissions = array(
			'admin'   => array(
				'manage_settings',
				'manage_users',
				'manage_bookings',
				'manage_services',
				'view_reports',
				'manage_branding',
			),
			'manager' => array(
				'manage_bookings',
				'manage_services',
				'view_reports',
			),
			'staff'   => array(
				'manage_bookings',
				'view_reports',
			),
			'member'  => array(
				'view_bookings',
			),
		);

		return $permissions[ $role ] ?? array();
	}

	/**
	 * Check user permission.
	 *
	 * @param int    $tenant_id  Tenant ID.
	 * @param int    $user_id    User ID.
	 * @param string $permission Permission.
	 * @return bool
	 */
	public function user_can( $tenant_id, $user_id, $permission ) {
		$role = $this->get_user_role( $tenant_id, $user_id );

		if ( ! $role ) {
			return false;
		}

		$permissions = $this->get_role_permissions( $role );

		return in_array( $permission, $permissions, true );
	}
}

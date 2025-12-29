<?php
/**
 * Tag Service.
 *
 * @package BookingX\CRM
 */

namespace BookingX\CRM\Services;

defined( 'ABSPATH' ) || exit;

/**
 * TagService class.
 */
class TagService {

	/**
	 * Get tag by ID.
	 *
	 * @param int $tag_id Tag ID.
	 * @return object|null
	 */
	public function get( $tag_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_tags';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$tag_id
		) );
	}

	/**
	 * Get tag by slug.
	 *
	 * @param string $slug Tag slug.
	 * @return object|null
	 */
	public function get_by_slug( $slug ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_tags';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE slug = %s",
			$slug
		) );
	}

	/**
	 * Get all tags.
	 *
	 * @return array
	 */
	public function get_all() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_tags';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" );
	}

	/**
	 * Create tag.
	 *
	 * @param string $name        Tag name.
	 * @param string $color       Tag color.
	 * @param string $description Tag description.
	 * @return int|WP_Error
	 */
	public function create( $name, $color = '#3b82f6', $description = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_tags';
		$slug  = sanitize_title( $name );

		// Check for duplicate slug.
		$existing = $this->get_by_slug( $slug );
		if ( $existing ) {
			return new \WP_Error( 'duplicate_slug', __( 'A tag with this name already exists.', 'bkx-crm' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, array(
			'name'        => $name,
			'slug'        => $slug,
			'color'       => $color,
			'description' => $description,
		) );

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create tag.', 'bkx-crm' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update tag.
	 *
	 * @param int   $tag_id Tag ID.
	 * @param array $data   Update data.
	 * @return bool
	 */
	public function update( $tag_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_tags';

		if ( isset( $data['name'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->update( $table, $data, array( 'id' => $tag_id ) );
	}

	/**
	 * Delete tag.
	 *
	 * @param int $tag_id Tag ID.
	 * @return bool
	 */
	public function delete( $tag_id ) {
		global $wpdb;

		$tags_table = $wpdb->prefix . 'bkx_crm_tags';
		$customer_tags_table = $wpdb->prefix . 'bkx_crm_customer_tags';

		// Remove from all customers.
		$wpdb->delete( $customer_tags_table, array( 'tag_id' => $tag_id ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->delete( $tags_table, array( 'id' => $tag_id ), array( '%d' ) );
	}

	/**
	 * Get customer tags.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 */
	public function get_customer_tags( $customer_id ) {
		global $wpdb;

		$tags_table = $wpdb->prefix . 'bkx_crm_tags';
		$customer_tags_table = $wpdb->prefix . 'bkx_crm_customer_tags';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT t.*, ct.added_at
			FROM {$tags_table} t
			INNER JOIN {$customer_tags_table} ct ON t.id = ct.tag_id
			WHERE ct.customer_id = %d
			ORDER BY t.name ASC",
			$customer_id
		) );
	}

	/**
	 * Add tag to customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $tag_id      Tag ID.
	 * @return bool
	 */
	public function add_to_customer( $customer_id, $tag_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_customer_tags';

		// Check if already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE customer_id = %d AND tag_id = %d",
			$customer_id,
			$tag_id
		) );

		if ( $existing ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, array(
			'customer_id' => $customer_id,
			'tag_id'      => $tag_id,
		) );

		if ( $result ) {
			$this->update_tag_count( $tag_id );
		}

		return false !== $result;
	}

	/**
	 * Remove tag from customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $tag_id      Tag ID.
	 * @return bool
	 */
	public function remove_from_customer( $customer_id, $tag_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_customer_tags';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete( $table, array(
			'customer_id' => $customer_id,
			'tag_id'      => $tag_id,
		), array( '%d', '%d' ) );

		if ( $result ) {
			$this->update_tag_count( $tag_id );
		}

		return false !== $result;
	}

	/**
	 * Update tag count.
	 *
	 * @param int $tag_id Tag ID.
	 */
	private function update_tag_count( $tag_id ) {
		global $wpdb;

		$tags_table = $wpdb->prefix . 'bkx_crm_tags';
		$customer_tags_table = $wpdb->prefix . 'bkx_crm_customer_tags';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$customer_tags_table} WHERE tag_id = %d",
			$tag_id
		) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $tags_table, array( 'count' => $count ), array( 'id' => $tag_id ) );
	}

	/**
	 * REST: Get tags.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_tags( $request ) {
		$tags = $this->get_all();
		return new \WP_REST_Response( $tags );
	}
}

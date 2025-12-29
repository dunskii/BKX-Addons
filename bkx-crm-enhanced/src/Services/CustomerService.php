<?php
/**
 * Customer Service.
 *
 * @package BookingX\CRM
 */

namespace BookingX\CRM\Services;

defined( 'ABSPATH' ) || exit;

/**
 * CustomerService class.
 */
class CustomerService {

	/**
	 * Get customer by ID.
	 *
	 * @param int $customer_id Customer ID.
	 * @return object|null
	 */
	public function get( $customer_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_customers';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d",
			$customer_id
		) );
	}

	/**
	 * Get customer by email.
	 *
	 * @param string $email Customer email.
	 * @return object|null
	 */
	public function get_by_email( $email ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_customers';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE email = %s",
			$email
		) );
	}

	/**
	 * Query customers.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function query( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search'   => '',
			'tag'      => 0,
			'segment'  => 0,
			'status'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'page'     => 1,
			'per_page' => 20,
		);

		$args = wp_parse_args( $args, $defaults );

		$table = $wpdb->prefix . 'bkx_crm_customers';
		$tags_table = $wpdb->prefix . 'bkx_crm_customer_tags';

		$where = array( '1=1' );
		$join  = '';

		// Search.
		if ( ! empty( $args['search'] ) ) {
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = $wpdb->prepare(
				"(c.email LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR c.phone LIKE %s)",
				$search,
				$search,
				$search,
				$search
			);
		}

		// Tag filter.
		if ( ! empty( $args['tag'] ) ) {
			$join = "INNER JOIN {$tags_table} ct ON c.id = ct.customer_id";
			$where[] = $wpdb->prepare( 'ct.tag_id = %d', $args['tag'] );
		}

		// Status filter.
		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'c.status = %s', $args['status'] );
		}

		// Segment filter.
		if ( ! empty( $args['segment'] ) ) {
			$segment_service = new SegmentService();
			$segment = $segment_service->get( $args['segment'] );

			if ( $segment && $segment->conditions ) {
				$conditions = json_decode( $segment->conditions, true );
				$segment_where = $segment_service->build_where_clause( $conditions );
				if ( $segment_where ) {
					$where[] = $segment_where;
				}
			}
		}

		$where_sql = implode( ' AND ', $where );

		// Count total.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $wpdb->get_var( "SELECT COUNT(DISTINCT c.id) FROM {$table} c {$join} WHERE {$where_sql}" );

		// Get items.
		$allowed_orderby = array( 'id', 'email', 'first_name', 'last_name', 'total_bookings', 'lifetime_value', 'created_at', 'last_booking_date' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT c.* FROM {$table} c {$join} WHERE {$where_sql} ORDER BY c.{$orderby} {$order} LIMIT %d OFFSET %d",
			$args['per_page'],
			$offset
		) );

		return array(
			'items'      => $items,
			'total'      => (int) $total,
			'page'       => $args['page'],
			'per_page'   => $args['per_page'],
			'total_pages' => ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * Create customer.
	 *
	 * @param array $data Customer data.
	 * @return int|WP_Error
	 */
	public function create( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_customers';

		// Check for duplicate email.
		$existing = $this->get_by_email( $data['email'] );
		if ( $existing ) {
			return new \WP_Error( 'duplicate_email', __( 'A customer with this email already exists.', 'bkx-crm' ) );
		}

		$insert_data = array(
			'email'      => $data['email'],
			'first_name' => $data['first_name'] ?? '',
			'last_name'  => $data['last_name'] ?? '',
			'phone'      => $data['phone'] ?? '',
			'company'    => $data['company'] ?? '',
			'address_1'  => $data['address_1'] ?? '',
			'address_2'  => $data['address_2'] ?? '',
			'city'       => $data['city'] ?? '',
			'state'      => $data['state'] ?? '',
			'postcode'   => $data['postcode'] ?? '',
			'country'    => $data['country'] ?? '',
			'source'     => $data['source'] ?? 'manual',
			'status'     => $data['status'] ?? 'active',
		);

		if ( ! empty( $data['date_of_birth'] ) ) {
			$insert_data['date_of_birth'] = $data['date_of_birth'];
		}

		if ( ! empty( $data['gender'] ) ) {
			$insert_data['gender'] = $data['gender'];
		}

		if ( ! empty( $data['user_id'] ) ) {
			$insert_data['user_id'] = $data['user_id'];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, $insert_data );

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create customer.', 'bkx-crm' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update customer.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $data        Update data.
	 * @return int|WP_Error
	 */
	public function update( $customer_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_customers';

		// Check for duplicate email if changing.
		if ( isset( $data['email'] ) ) {
			$existing = $this->get_by_email( $data['email'] );
			if ( $existing && $existing->id !== $customer_id ) {
				return new \WP_Error( 'duplicate_email', __( 'A customer with this email already exists.', 'bkx-crm' ) );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update( $table, $data, array( 'id' => $customer_id ) );

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to update customer.', 'bkx-crm' ) );
		}

		return $customer_id;
	}

	/**
	 * Delete customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @return bool
	 */
	public function delete( $customer_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_customers';

		// Delete related records.
		$wpdb->delete( $wpdb->prefix . 'bkx_crm_customer_tags', array( 'customer_id' => $customer_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bkx_crm_notes', array( 'customer_id' => $customer_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bkx_crm_communications', array( 'customer_id' => $customer_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bkx_crm_activities', array( 'customer_id' => $customer_id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bkx_crm_followups', array( 'customer_id' => $customer_id ), array( '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->delete( $table, array( 'id' => $customer_id ), array( '%d' ) );
	}

	/**
	 * Sync customer from WordPress user.
	 *
	 * @param int $user_id User ID.
	 * @return int|null
	 */
	public function sync_from_user( $user_id ) {
		$settings = get_option( 'bkx_crm_settings', array() );

		if ( empty( $settings['auto_sync_users'] ) ) {
			return null;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return null;
		}

		$customer = $this->get_by_email( $user->user_email );

		$data = array(
			'email'      => $user->user_email,
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name,
			'user_id'    => $user_id,
			'source'     => 'user_sync',
		);

		if ( $customer ) {
			$this->update( $customer->id, $data );
			return $customer->id;
		}

		return $this->create( $data );
	}

	/**
	 * Get customer notes.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 */
	public function get_notes( $customer_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_notes';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$notes = $wpdb->get_results( $wpdb->prepare(
			"SELECT n.*, u.display_name as author_name
			FROM {$table} n
			LEFT JOIN {$wpdb->users} u ON n.created_by = u.ID
			WHERE n.customer_id = %d
			ORDER BY n.created_at DESC",
			$customer_id
		) );

		return $notes;
	}

	/**
	 * Add note.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $data        Note data.
	 * @return int|WP_Error
	 */
	public function add_note( $customer_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_notes';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, array(
			'customer_id' => $customer_id,
			'booking_id'  => $data['booking_id'] ?? null,
			'note_type'   => $data['note_type'] ?? 'general',
			'content'     => $data['content'],
			'is_private'  => $data['is_private'] ?? 0,
			'created_by'  => $data['created_by'] ?? get_current_user_id(),
		) );

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to add note.', 'bkx-crm' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Delete note.
	 *
	 * @param int $note_id Note ID.
	 * @return bool
	 */
	public function delete_note( $note_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_notes';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->delete( $table, array( 'id' => $note_id ), array( '%d' ) );
	}

	/**
	 * Log communication.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $data        Communication data.
	 * @return int|WP_Error
	 */
	public function log_communication( $customer_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_communications';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, array(
			'customer_id' => $customer_id,
			'booking_id'  => $data['booking_id'] ?? null,
			'channel'     => $data['channel'],
			'direction'   => $data['direction'],
			'subject'     => $data['subject'] ?? '',
			'content'     => $data['content'] ?? '',
			'status'      => $data['status'] ?? 'sent',
			'sent_by'     => $data['sent_by'] ?? null,
			'external_id' => $data['external_id'] ?? null,
		) );

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to log communication.', 'bkx-crm' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get customer bookings.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 */
	public function get_bookings( $customer_id ) {
		$customer = $this->get( $customer_id );

		if ( ! $customer ) {
			return array();
		}

		$args = array(
			'post_type'      => 'bkx_booking',
			'posts_per_page' => 20,
			'meta_query'     => array(
				array(
					'key'   => 'customer_email',
					'value' => $customer->email,
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		return get_posts( $args );
	}

	/**
	 * Increment booking count.
	 *
	 * @param int $customer_id Customer ID.
	 */
	public function increment_booking_count( $customer_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_customers';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET total_bookings = total_bookings + 1, last_booking_date = NOW() WHERE id = %d",
			$customer_id
		) );
	}

	/**
	 * Add to lifetime value.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param float $amount      Amount to add.
	 */
	public function add_to_lifetime_value( $customer_id, $amount ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_customers';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET lifetime_value = lifetime_value + %f WHERE id = %d",
			$amount,
			$customer_id
		) );
	}

	/**
	 * REST: Get customers.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_customers( $request ) {
		$args = array(
			'search'   => $request->get_param( 'search' ),
			'tag'      => $request->get_param( 'tag' ),
			'segment'  => $request->get_param( 'segment' ),
			'status'   => $request->get_param( 'status' ),
			'orderby'  => $request->get_param( 'orderby' ),
			'order'    => $request->get_param( 'order' ),
			'page'     => $request->get_param( 'page' ) ?: 1,
			'per_page' => $request->get_param( 'per_page' ) ?: 20,
		);

		$result = $this->query( array_filter( $args ) );

		return new \WP_REST_Response( $result );
	}

	/**
	 * REST: Get customer.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_customer( $request ) {
		$customer_id = $request->get_param( 'id' );
		$customer    = $this->get( $customer_id );

		if ( ! $customer ) {
			return new \WP_REST_Response( array( 'message' => 'Customer not found' ), 404 );
		}

		return new \WP_REST_Response( $customer );
	}
}

<?php
/**
 * Package Service
 *
 * @package BookingX\BookingPackages\Services
 * @since   1.0.0
 */

namespace BookingX\BookingPackages\Services;

use BookingX\BookingPackages\BookingPackagesAddon;
use WP_Error;

/**
 * Service for managing packages.
 *
 * @since 1.0.0
 */
class PackageService {

	/**
	 * Addon instance.
	 *
	 * @var BookingPackagesAddon
	 */
	protected BookingPackagesAddon $addon;

	/**
	 * Customer packages table.
	 *
	 * @var string
	 */
	protected string $customer_packages_table;

	/**
	 * Package services table.
	 *
	 * @var string
	 */
	protected string $package_services_table;

	/**
	 * Constructor.
	 *
	 * @param BookingPackagesAddon $addon Addon instance.
	 */
	public function __construct( BookingPackagesAddon $addon ) {
		global $wpdb;

		$this->addon                   = $addon;
		$this->customer_packages_table = $wpdb->prefix . 'bkx_customer_packages';
		$this->package_services_table  = $wpdb->prefix . 'bkx_package_services';
	}

	/**
	 * Get available packages.
	 *
	 * @param array $args Query arguments.
	 * @return array Packages.
	 */
	public function get_available_packages( array $args = array() ): array {
		$defaults = array(
			'service_id' => 0,
			'limit'      => -1,
		);

		$args = wp_parse_args( $args, $defaults );

		$query_args = array(
			'post_type'      => 'bkx_package',
			'post_status'    => 'publish',
			'posts_per_page' => $args['limit'],
			'meta_query'     => array(
				array(
					'key'     => '_bkx_package_active',
					'value'   => '1',
					'compare' => '=',
				),
			),
		);

		if ( $args['service_id'] ) {
			$query_args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => '_bkx_package_all_services',
					'value'   => '1',
					'compare' => '=',
				),
				array(
					'key'     => '_bkx_package_services',
					'value'   => $args['service_id'],
					'compare' => 'LIKE',
				),
			);
		}

		$posts    = get_posts( $query_args );
		$packages = array();

		foreach ( $posts as $post ) {
			$packages[] = $this->format_package( $post );
		}

		return $packages;
	}

	/**
	 * Get package by ID.
	 *
	 * @param int $package_id Package ID.
	 * @return array|null Package data or null.
	 */
	public function get_package( int $package_id ): ?array {
		$post = get_post( $package_id );

		if ( ! $post || 'bkx_package' !== $post->post_type ) {
			return null;
		}

		return $this->format_package( $post );
	}

	/**
	 * Format package data.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array Package data.
	 */
	protected function format_package( \WP_Post $post ): array {
		return array(
			'id'            => $post->ID,
			'title'         => $post->post_title,
			'description'   => $post->post_content,
			'excerpt'       => $post->post_excerpt,
			'price'         => (float) get_post_meta( $post->ID, '_bkx_package_price', true ),
			'regular_price' => (float) get_post_meta( $post->ID, '_bkx_package_regular_price', true ),
			'uses'          => (int) get_post_meta( $post->ID, '_bkx_package_uses', true ),
			'validity_days' => (int) get_post_meta( $post->ID, '_bkx_package_validity_days', true ),
			'all_services'  => (bool) get_post_meta( $post->ID, '_bkx_package_all_services', true ),
			'services'      => get_post_meta( $post->ID, '_bkx_package_services', true ) ?: array(),
			'image'         => get_the_post_thumbnail_url( $post->ID, 'medium' ),
			'active'        => (bool) get_post_meta( $post->ID, '_bkx_package_active', true ),
		);
	}

	/**
	 * Get customer packages.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $args Query arguments.
	 * @return array Packages.
	 */
	public function get_customer_packages( int $customer_id, array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status' => 'active',
			'limit'  => 50,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'cp.customer_id = %d' );
		$params = array( $this->customer_packages_table, $customer_id );

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'cp.status = %s';
			$params[] = $args['status'];
		}

		$params[] = $args['limit'];

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cp.* FROM %i cp WHERE {$where_clause} ORDER BY cp.expiry_date ASC LIMIT %d",
				...$params
			),
			ARRAY_A
		);

		$packages = array();

		foreach ( $results as $row ) {
			$package = $this->get_package( $row['package_id'] );

			if ( $package ) {
				$packages[] = array_merge(
					$row,
					array(
						'package' => $package,
					)
				);
			}
		}

		return $packages;
	}

	/**
	 * Get customer package by ID.
	 *
	 * @param int $customer_package_id Customer package ID.
	 * @return array|null Customer package or null.
	 */
	public function get_customer_package( int $customer_package_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$this->customer_packages_table,
				$customer_package_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$package = $this->get_package( $row['package_id'] );

		if ( ! $package ) {
			return null;
		}

		return array_merge( $row, array( 'package' => $package ) );
	}

	/**
	 * Check if package is valid.
	 *
	 * @param array $customer_package Customer package data.
	 * @return bool True if valid.
	 */
	public function is_package_valid( array $customer_package ): bool {
		// Check status.
		if ( 'active' !== $customer_package['status'] ) {
			return false;
		}

		// Check uses.
		if ( $customer_package['uses_remaining'] <= 0 ) {
			return false;
		}

		// Check expiry.
		if ( ! empty( $customer_package['expiry_date'] ) ) {
			$expiry = strtotime( $customer_package['expiry_date'] );
			if ( $expiry && $expiry < time() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if package applies to service.
	 *
	 * @param array $customer_package Customer package data.
	 * @param int   $service_id Service ID.
	 * @return bool True if applies.
	 */
	public function package_applies_to_service( array $customer_package, int $service_id ): bool {
		$package = $customer_package['package'] ?? null;

		if ( ! $package ) {
			return false;
		}

		// All services.
		if ( $package['all_services'] ) {
			return true;
		}

		// Check specific services.
		return in_array( $service_id, (array) $package['services'], true );
	}

	/**
	 * Assign package to customer.
	 *
	 * @param int      $package_id Package ID.
	 * @param int      $customer_id Customer ID.
	 * @param int|null $order_id Order ID.
	 * @return int|WP_Error Customer package ID or error.
	 */
	public function assign_package_to_customer( int $package_id, int $customer_id, ?int $order_id = null ) {
		global $wpdb;

		$package = $this->get_package( $package_id );

		if ( ! $package ) {
			return new WP_Error( 'invalid_package', __( 'Package not found.', 'bkx-booking-packages' ) );
		}

		// Calculate expiry date.
		$expiry_date = null;
		$validity    = $package['validity_days'] ?: $this->addon->get_setting( 'default_validity', 365 );

		if ( $validity > 0 ) {
			$expiry_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$validity} days" ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->customer_packages_table,
			array(
				'customer_id'    => $customer_id,
				'package_id'     => $package_id,
				'order_id'       => $order_id,
				'total_uses'     => $package['uses'],
				'uses_remaining' => $package['uses'],
				'expiry_date'    => $expiry_date,
				'status'         => 'active',
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to assign package.', 'bkx-booking-packages' ) );
		}

		$customer_package_id = $wpdb->insert_id;

		/**
		 * Fires when a package is assigned to a customer.
		 *
		 * @param int $customer_package_id Customer package ID.
		 * @param int $package_id Package ID.
		 * @param int $customer_id Customer ID.
		 */
		do_action( 'bkx_package_assigned', $customer_package_id, $package_id, $customer_id );

		return $customer_package_id;
	}

	/**
	 * Create purchase session.
	 *
	 * @param int $package_id Package ID.
	 * @param int $customer_id Customer ID.
	 * @return string|WP_Error Checkout URL or error.
	 */
	public function create_purchase_session( int $package_id, int $customer_id ) {
		$package = $this->get_package( $package_id );

		if ( ! $package ) {
			return new WP_Error( 'invalid_package', __( 'Package not found.', 'bkx-booking-packages' ) );
		}

		// Store session data.
		$session_key = 'bkx_package_' . wp_generate_password( 16, false );

		set_transient(
			$session_key,
			array(
				'package_id'  => $package_id,
				'customer_id' => $customer_id,
				'price'       => $package['price'],
				'created_at'  => time(),
			),
			HOUR_IN_SECONDS
		);

		// Return checkout URL (integration with payment gateway).
		$checkout_url = add_query_arg(
			array(
				'bkx_package_checkout' => $session_key,
			),
			wc_get_checkout_url() ?? home_url( '/checkout/' )
		);

		/**
		 * Filter the package checkout URL.
		 *
		 * @param string $checkout_url Checkout URL.
		 * @param int    $package_id Package ID.
		 * @param int    $customer_id Customer ID.
		 * @param string $session_key Session key.
		 */
		return apply_filters( 'bkx_package_checkout_url', $checkout_url, $package_id, $customer_id, $session_key );
	}

	/**
	 * Expire packages.
	 *
	 * @return int Number of expired packages.
	 */
	public function expire_packages(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'expired' WHERE status = 'active' AND expiry_date IS NOT NULL AND expiry_date < NOW()",
				$this->customer_packages_table
			)
		);

		if ( $result > 0 ) {
			/**
			 * Fires when packages are expired.
			 *
			 * @param int $count Number of expired packages.
			 */
			do_action( 'bkx_packages_expired', $result );
		}

		return $result ?: 0;
	}

	/**
	 * Get expiring packages.
	 *
	 * @param int $days Days until expiry.
	 * @return array Expiring packages.
	 */
	public function get_expiring_packages( int $days = 7 ): array {
		global $wpdb;

		$target_date = gmdate( 'Y-m-d', strtotime( "+{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE status = 'active' AND DATE(expiry_date) = %s",
				$this->customer_packages_table,
				$target_date
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Update package uses.
	 *
	 * @param int $customer_package_id Customer package ID.
	 * @param int $change Change in uses (negative to decrement).
	 * @return bool Success.
	 */
	public function update_uses( int $customer_package_id, int $change ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET uses_remaining = GREATEST(0, uses_remaining + %d) WHERE id = %d',
				$this->customer_packages_table,
				$change,
				$customer_package_id
			)
		);

		// Check if uses are now 0.
		if ( $change < 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$remaining = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT uses_remaining FROM %i WHERE id = %d',
					$this->customer_packages_table,
					$customer_package_id
				)
			);

			if ( 0 === (int) $remaining ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$this->customer_packages_table,
					array( 'status' => 'exhausted' ),
					array( 'id' => $customer_package_id ),
					array( '%s' ),
					array( '%d' )
				);

				/**
				 * Fires when a package is exhausted.
				 *
				 * @param int $customer_package_id Customer package ID.
				 */
				do_action( 'bkx_package_exhausted', $customer_package_id );
			}
		}

		return false !== $result;
	}

	/**
	 * Get package statistics.
	 *
	 * @param int $package_id Package ID.
	 * @return array Statistics.
	 */
	public function get_package_stats( int $package_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_sold,
					SUM(total_uses) as total_uses_sold,
					SUM(total_uses - uses_remaining) as total_uses_redeemed,
					SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
					SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_count,
					SUM(CASE WHEN status = 'exhausted' THEN 1 ELSE 0 END) as exhausted_count
				FROM %i WHERE package_id = %d",
				$this->customer_packages_table,
				$package_id
			),
			ARRAY_A
		);

		return $stats ?: array(
			'total_sold'          => 0,
			'total_uses_sold'     => 0,
			'total_uses_redeemed' => 0,
			'active_count'        => 0,
			'expired_count'       => 0,
			'exhausted_count'     => 0,
		);
	}
}

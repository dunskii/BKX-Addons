<?php
/**
 * Facebook Page Manager.
 *
 * @package BookingX\FacebookBooking\Services
 */

namespace BookingX\FacebookBooking\Services;

defined( 'ABSPATH' ) || exit;

/**
 * PageManager class.
 *
 * Manages Facebook page connections and settings.
 */
class PageManager {

	/**
	 * Facebook API instance.
	 *
	 * @var FacebookApi
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param FacebookApi $api Facebook API instance.
	 */
	public function __construct( FacebookApi $api ) {
		$this->api = $api;
	}

	/**
	 * Get all connected pages.
	 *
	 * @param string $status Page status filter (active, disconnected, all).
	 * @return array
	 */
	public function get_pages( $status = 'active' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_pages';

		if ( 'all' === $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY connected_at DESC" );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = %s ORDER BY connected_at DESC",
			$status
		) );
	}

	/**
	 * Get page by ID.
	 *
	 * @param string $page_id Facebook page ID.
	 * @return object|null
	 */
	public function get_page( $page_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_pages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE page_id = %s",
			$page_id
		) );
	}

	/**
	 * Connect a Facebook page.
	 *
	 * @param array $page_data Page data from Facebook.
	 * @return int|false
	 */
	public function connect_page( $page_data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_pages';

		// Check if page already exists.
		$existing = $this->get_page( $page_data['id'] );

		$data = array(
			'page_id'      => $page_data['id'],
			'page_name'    => $page_data['name'],
			'access_token' => $this->encrypt_token( $page_data['access_token'] ),
			'category'     => $page_data['category'] ?? null,
			'page_url'     => $page_data['link'] ?? null,
			'status'       => 'active',
			'connected_at' => current_time( 'mysql' ),
		);

		if ( $existing ) {
			// Update existing page.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->update(
				$table,
				$data,
				array( 'page_id' => $page_data['id'] )
			);

			if ( false !== $result ) {
				$this->setup_page_integration( $page_data['id'], $page_data['access_token'] );
				return $existing->id;
			}
		} else {
			// Insert new page.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->insert( $table, $data );

			if ( $result ) {
				$this->setup_page_integration( $page_data['id'], $page_data['access_token'] );
				return $wpdb->insert_id;
			}
		}

		return false;
	}

	/**
	 * Disconnect a Facebook page.
	 *
	 * @param string $page_id Facebook page ID.
	 * @return bool
	 */
	public function disconnect_page( $page_id ) {
		global $wpdb;

		$page = $this->get_page( $page_id );

		if ( ! $page ) {
			return false;
		}

		$table = $wpdb->prefix . 'bkx_fb_pages';

		// Unsubscribe from webhooks.
		$token = $this->decrypt_token( $page->access_token );
		$this->api->unsubscribe_page_webhooks( $page_id, $token );

		// Update status to disconnected.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$table,
			array( 'status' => 'disconnected' ),
			array( 'page_id' => $page_id )
		);

		return false !== $result;
	}

	/**
	 * Delete a page completely.
	 *
	 * @param string $page_id Facebook page ID.
	 * @return bool
	 */
	public function delete_page( $page_id ) {
		global $wpdb;

		// First disconnect.
		$this->disconnect_page( $page_id );

		$table = $wpdb->prefix . 'bkx_fb_pages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete( $table, array( 'page_id' => $page_id ) );

		// Also delete associated services mapping.
		$services_table = $wpdb->prefix . 'bkx_fb_services';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $services_table, array( 'page_id' => $page_id ) );

		return false !== $result;
	}

	/**
	 * Set up page integration (webhooks, CTA button).
	 *
	 * @param string $page_id      Page ID.
	 * @param string $access_token Page access token.
	 */
	private function setup_page_integration( $page_id, $access_token ) {
		// Subscribe to webhooks.
		$this->api->subscribe_page_webhooks( $page_id, $access_token );

		// Set up Book Now button.
		$booking_url = $this->get_booking_widget_url( $page_id );
		$this->api->set_page_cta( $page_id, $access_token, $booking_url );
	}

	/**
	 * Get booking widget URL for a page.
	 *
	 * @param string $page_id Page ID.
	 * @return string
	 */
	public function get_booking_widget_url( $page_id ) {
		return add_query_arg(
			array(
				'bkx_fb_widget' => 1,
				'page_id'       => $page_id,
			),
			home_url( '/' )
		);
	}

	/**
	 * Get page access token.
	 *
	 * @param string $page_id Page ID.
	 * @return string|null
	 */
	public function get_access_token( $page_id ) {
		$page = $this->get_page( $page_id );

		if ( ! $page || 'active' !== $page->status ) {
			return null;
		}

		return $this->decrypt_token( $page->access_token );
	}

	/**
	 * Refresh page token if needed.
	 *
	 * @param string $page_id Page ID.
	 * @return bool
	 */
	public function refresh_token_if_needed( $page_id ) {
		$page = $this->get_page( $page_id );

		if ( ! $page ) {
			return false;
		}

		// Page tokens don't expire if they're from a long-lived user token.
		// But we should validate it periodically.
		$token = $this->decrypt_token( $page->access_token );
		$debug = $this->api->debug_token( $token );

		if ( is_wp_error( $debug ) ) {
			$this->mark_page_invalid( $page_id );
			return false;
		}

		if ( isset( $debug['data']['is_valid'] ) && ! $debug['data']['is_valid'] ) {
			$this->mark_page_invalid( $page_id );
			return false;
		}

		return true;
	}

	/**
	 * Mark page token as invalid.
	 *
	 * @param string $page_id Page ID.
	 */
	private function mark_page_invalid( $page_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_pages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array( 'status' => 'token_expired' ),
			array( 'page_id' => $page_id )
		);
	}

	/**
	 * Update page last sync time.
	 *
	 * @param string $page_id Page ID.
	 */
	public function update_last_sync( $page_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_pages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array( 'last_sync' => current_time( 'mysql' ) ),
			array( 'page_id' => $page_id )
		);
	}

	/**
	 * Get services mapped to a page.
	 *
	 * @param string $page_id Page ID.
	 * @return array
	 */
	public function get_page_services( $page_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_services';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE page_id = %s AND enabled = 1 ORDER BY name ASC",
			$page_id
		) );
	}

	/**
	 * Map a BookingX service to a Facebook page.
	 *
	 * @param string $page_id        Page ID.
	 * @param int    $bkx_service_id BookingX service ID.
	 * @return int|false
	 */
	public function map_service( $page_id, $bkx_service_id ) {
		global $wpdb;

		$service = get_post( $bkx_service_id );

		if ( ! $service || 'bkx_base' !== $service->post_type ) {
			return false;
		}

		$table = $wpdb->prefix . 'bkx_fb_services';

		// Check if mapping exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE page_id = %s AND bkx_service_id = %d",
			$page_id,
			$bkx_service_id
		) );

		$price = get_post_meta( $bkx_service_id, 'base_price', true );
		$duration = get_post_meta( $bkx_service_id, 'base_time', true );

		$data = array(
			'page_id'          => $page_id,
			'bkx_service_id'   => $bkx_service_id,
			'name'             => $service->post_title,
			'description'      => $service->post_excerpt,
			'price'            => floatval( $price ),
			'duration_minutes' => absint( $duration ),
			'enabled'          => 1,
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->update(
				$table,
				$data,
				array( 'id' => $existing->id )
			);
			return false !== $result ? $existing->id : false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, $data );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Unmap a service from a page.
	 *
	 * @param string $page_id        Page ID.
	 * @param int    $bkx_service_id BookingX service ID.
	 * @return bool
	 */
	public function unmap_service( $page_id, $bkx_service_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_services';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete( $table, array(
			'page_id'        => $page_id,
			'bkx_service_id' => $bkx_service_id,
		) );

		return false !== $result;
	}

	/**
	 * Toggle service enabled status.
	 *
	 * @param int  $mapping_id Mapping ID.
	 * @param bool $enabled    Enabled status.
	 * @return bool
	 */
	public function toggle_service( $mapping_id, $enabled ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_services';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$table,
			array( 'enabled' => $enabled ? 1 : 0 ),
			array( 'id' => $mapping_id )
		);

		return false !== $result;
	}

	/**
	 * Get all BookingX services available for mapping.
	 *
	 * @return array
	 */
	public function get_available_services() {
		$services = get_posts( array(
			'post_type'      => 'bkx_base',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$result = array();

		foreach ( $services as $service ) {
			$result[] = array(
				'id'       => $service->ID,
				'name'     => $service->post_title,
				'price'    => get_post_meta( $service->ID, 'base_price', true ),
				'duration' => get_post_meta( $service->ID, 'base_time', true ),
			);
		}

		return $result;
	}

	/**
	 * Encrypt access token for storage.
	 *
	 * @param string $token Token to encrypt.
	 * @return string
	 */
	private function encrypt_token( $token ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $token );
		}

		$key    = $this->get_encryption_key();
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $token, 'AES-256-CBC', $key, 0, $iv );

		return base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt stored access token.
	 *
	 * @param string $encrypted Encrypted token.
	 * @return string
	 */
	private function decrypt_token( $encrypted ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $encrypted );
		}

		$key  = $this->get_encryption_key();
		$data = base64_decode( $encrypted );
		$iv   = substr( $data, 0, 16 );
		$cipher = substr( $data, 16 );

		return openssl_decrypt( $cipher, 'AES-256-CBC', $key, 0, $iv );
	}

	/**
	 * Get encryption key.
	 *
	 * @return string
	 */
	private function get_encryption_key() {
		$key = defined( 'BKX_FB_ENCRYPTION_KEY' ) ? BKX_FB_ENCRYPTION_KEY : AUTH_KEY;
		return hash( 'sha256', $key, true );
	}

	/**
	 * Get page statistics.
	 *
	 * @param string $page_id Page ID.
	 * @return array
	 */
	public function get_page_stats( $page_id ) {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bkx_fb_bookings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$bookings_table} WHERE page_id = %s",
			$page_id
		) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$pending = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$bookings_table} WHERE page_id = %s AND status = 'pending'",
			$page_id
		) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$confirmed = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$bookings_table} WHERE page_id = %s AND status = 'confirmed'",
			$page_id
		) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$today = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$bookings_table} WHERE page_id = %s AND booking_date = %s",
			$page_id,
			current_time( 'Y-m-d' )
		) );

		return array(
			'total_bookings' => absint( $total ),
			'pending'        => absint( $pending ),
			'confirmed'      => absint( $confirmed ),
			'today'          => absint( $today ),
		);
	}
}

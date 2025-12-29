<?php
/**
 * Booking Synchronization Service.
 *
 * @package BookingX\FacebookBooking\Services
 */

namespace BookingX\FacebookBooking\Services;

defined( 'ABSPATH' ) || exit;

/**
 * BookingSync class.
 *
 * Handles synchronization between Facebook and BookingX bookings.
 */
class BookingSync {

	/**
	 * Create a booking from Facebook.
	 *
	 * @param array $data Booking data.
	 * @return array|WP_Error
	 */
	public function create_facebook_booking( $data ) {
		global $wpdb;

		// Generate Facebook booking ID.
		$fb_booking_id = 'FB-' . strtoupper( wp_generate_password( 8, false ) );

		// Get service details.
		$service = get_post( $data['service_id'] );
		$duration = get_post_meta( $data['service_id'], 'base_time', true );

		// Calculate end time.
		$start_time = $data['start_time'];
		$end_time = gmdate( 'H:i:s', strtotime( $start_time ) + ( absint( $duration ) * 60 ) );

		// Insert into Facebook bookings table.
		$table = $wpdb->prefix . 'bkx_fb_bookings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, array(
			'fb_booking_id'  => $fb_booking_id,
			'page_id'        => $data['page_id'],
			'service_id'     => $data['service_id'],
			'fb_user_id'     => $data['fb_user_id'] ?? null,
			'customer_name'  => $data['customer_name'] ?? '',
			'customer_email' => $data['customer_email'] ?? '',
			'customer_phone' => $data['customer_phone'] ?? '',
			'booking_date'   => $data['booking_date'],
			'start_time'     => $start_time,
			'end_time'       => $end_time,
			'status'         => 'pending',
			'source'         => $data['source'] ?? 'facebook_page',
			'notes'          => $data['notes'] ?? '',
			'raw_data'       => wp_json_encode( $data ),
			'created_at'     => current_time( 'mysql' ),
		) );

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create booking.', 'bkx-facebook-booking' ) );
		}

		$fb_row_id = $wpdb->insert_id;

		// Create corresponding BookingX booking.
		$bkx_booking_id = $this->create_bkx_booking( array(
			'service_id'     => $data['service_id'],
			'booking_date'   => $data['booking_date'],
			'start_time'     => $start_time,
			'end_time'       => $end_time,
			'customer_name'  => $data['customer_name'] ?? '',
			'customer_email' => $data['customer_email'] ?? '',
			'customer_phone' => $data['customer_phone'] ?? '',
			'source'         => 'facebook',
			'fb_booking_id'  => $fb_booking_id,
		) );

		if ( ! is_wp_error( $bkx_booking_id ) && $bkx_booking_id ) {
			// Update Facebook booking with BKX booking ID.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array(
					'bkx_booking_id' => $bkx_booking_id,
					'status'         => 'confirmed',
				),
				array( 'id' => $fb_row_id )
			);
		}

		/**
		 * Fires after a Facebook booking is created.
		 *
		 * @param string $fb_booking_id  Facebook booking ID.
		 * @param int    $bkx_booking_id BookingX booking ID.
		 * @param array  $data           Booking data.
		 */
		do_action( 'bkx_fb_booking_created', $fb_booking_id, $bkx_booking_id, $data );

		return array(
			'fb_booking_id'  => $fb_booking_id,
			'bkx_booking_id' => $bkx_booking_id,
		);
	}

	/**
	 * Create a BookingX booking.
	 *
	 * @param array $data Booking data.
	 * @return int|WP_Error
	 */
	private function create_bkx_booking( $data ) {
		// Create booking post.
		$booking_id = wp_insert_post( array(
			'post_type'   => 'bkx_booking',
			'post_status' => 'bkx-pending',
			'post_title'  => sprintf(
				/* translators: 1: Customer name, 2: Booking date */
				__( 'Booking by %1$s on %2$s', 'bkx-facebook-booking' ),
				$data['customer_name'] ?: __( 'Facebook User', 'bkx-facebook-booking' ),
				$data['booking_date']
			),
		), true );

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		// Add booking meta.
		update_post_meta( $booking_id, 'booking_date', $data['booking_date'] );
		update_post_meta( $booking_id, 'booking_time', $data['start_time'] );
		update_post_meta( $booking_id, 'booking_time_end', $data['end_time'] );
		update_post_meta( $booking_id, 'base_id', $data['service_id'] );
		update_post_meta( $booking_id, 'customer_name', $data['customer_name'] );
		update_post_meta( $booking_id, 'customer_email', $data['customer_email'] );
		update_post_meta( $booking_id, 'customer_phone', $data['customer_phone'] );
		update_post_meta( $booking_id, 'booking_source', 'facebook' );
		update_post_meta( $booking_id, 'fb_booking_id', $data['fb_booking_id'] );

		// Calculate total.
		$price = get_post_meta( $data['service_id'], 'base_price', true );
		update_post_meta( $booking_id, 'booking_total', floatval( $price ) );

		/**
		 * Fires after a BookingX booking is created from Facebook.
		 *
		 * @param int   $booking_id Booking ID.
		 * @param array $data       Booking data.
		 */
		do_action( 'bkx_booking_created', $booking_id, $data );

		return $booking_id;
	}

	/**
	 * Update booking status.
	 *
	 * @param string $fb_booking_id Facebook booking ID.
	 * @param string $status        New status.
	 * @return bool
	 */
	public function update_status( $fb_booking_id, $status ) {
		global $wpdb;

		$booking = $this->get_booking_by_fb_id( $fb_booking_id );

		if ( ! $booking ) {
			return false;
		}

		$table = $wpdb->prefix . 'bkx_fb_bookings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'fb_booking_id' => $fb_booking_id )
		);

		// Also update BookingX booking.
		if ( $booking->bkx_booking_id ) {
			$bkx_status = $this->map_status_to_bkx( $status );
			wp_update_post( array(
				'ID'          => $booking->bkx_booking_id,
				'post_status' => $bkx_status,
			) );
		}

		/**
		 * Fires after a Facebook booking status is updated.
		 *
		 * @param string $fb_booking_id Facebook booking ID.
		 * @param string $status        New status.
		 */
		do_action( 'bkx_fb_booking_status_updated', $fb_booking_id, $status );

		return false !== $result;
	}

	/**
	 * Cancel a booking.
	 *
	 * @param string $fb_booking_id Facebook booking ID.
	 * @return bool
	 */
	public function cancel_booking( $fb_booking_id ) {
		return $this->update_status( $fb_booking_id, 'cancelled' );
	}

	/**
	 * Get booking by Facebook booking ID.
	 *
	 * @param string $fb_booking_id Facebook booking ID.
	 * @return object|null
	 */
	public function get_booking_by_fb_id( $fb_booking_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_bookings';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE fb_booking_id = %s",
			$fb_booking_id
		) );
	}

	/**
	 * Get bookings for a user.
	 *
	 * @param string $fb_user_id Facebook user PSID.
	 * @param string $type       Type of bookings (upcoming, past, all).
	 * @return array
	 */
	public function get_user_bookings( $fb_user_id, $type = 'all' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_bookings';
		$today = current_time( 'Y-m-d' );

		$where = $wpdb->prepare( 'fb_user_id = %s', $fb_user_id );

		switch ( $type ) {
			case 'upcoming':
				$where .= $wpdb->prepare(
					" AND booking_date >= %s AND status NOT IN ('cancelled', 'completed')",
					$today
				);
				$order = 'booking_date ASC, start_time ASC';
				break;

			case 'past':
				$where .= $wpdb->prepare(
					' AND (booking_date < %s OR status IN (%s, %s))',
					$today,
					'completed',
					'cancelled'
				);
				$order = 'booking_date DESC, start_time DESC';
				break;

			default:
				$order = 'booking_date DESC, start_time DESC';
				break;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where} ORDER BY {$order} LIMIT 20" );
	}

	/**
	 * Get bookings for a page.
	 *
	 * @param string $page_id Page ID.
	 * @param array  $args    Query arguments.
	 * @return array
	 */
	public function get_page_bookings( $page_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => 'all',
			'date'     => null,
			'limit'    => 50,
			'offset'   => 0,
			'order'    => 'DESC',
			'order_by' => 'created_at',
		);

		$args = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_fb_bookings';

		$where = $wpdb->prepare( 'page_id = %s', $page_id );

		if ( 'all' !== $args['status'] ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		if ( $args['date'] ) {
			$where .= $wpdb->prepare( ' AND booking_date = %s', $args['date'] );
		}

		$order_by = in_array( $args['order_by'], array( 'booking_date', 'created_at', 'status' ), true )
			? $args['order_by']
			: 'created_at';

		$order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
			$args['limit'],
			$args['offset']
		) );
	}

	/**
	 * Get booking count by status.
	 *
	 * @param string $page_id Page ID (optional).
	 * @return array
	 */
	public function get_booking_counts( $page_id = null ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_bookings';

		$where = '1=1';
		if ( $page_id ) {
			$where = $wpdb->prepare( 'page_id = %s', $page_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( "SELECT status, COUNT(*) as count FROM {$table} WHERE {$where} GROUP BY status" );

		$counts = array(
			'total'     => 0,
			'pending'   => 0,
			'confirmed' => 0,
			'cancelled' => 0,
			'completed' => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row->status ] = absint( $row->count );
			$counts['total'] += absint( $row->count );
		}

		return $counts;
	}

	/**
	 * Sync booking status from BookingX.
	 *
	 * @param int $bkx_booking_id BookingX booking ID.
	 */
	public function sync_from_bkx( $bkx_booking_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_bookings';

		// Get Facebook booking by BKX ID.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$fb_booking = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE bkx_booking_id = %d",
			$bkx_booking_id
		) );

		if ( ! $fb_booking ) {
			return;
		}

		// Get BKX booking status.
		$bkx_status = get_post_status( $bkx_booking_id );
		$fb_status = $this->map_bkx_status_to_fb( $bkx_status );

		if ( $fb_booking->status !== $fb_status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array(
					'status'     => $fb_status,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $fb_booking->id )
			);
		}
	}

	/**
	 * Sync all bookings for a page.
	 *
	 * @param string $page_id Page ID.
	 * @return array
	 */
	public function sync_page_bookings( $page_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_bookings';

		// Get all Facebook bookings with linked BKX bookings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$bookings = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE page_id = %s AND bkx_booking_id IS NOT NULL",
			$page_id
		) );

		$synced = 0;

		foreach ( $bookings as $booking ) {
			$this->sync_from_bkx( $booking->bkx_booking_id );
			++$synced;
		}

		return array(
			'synced' => $synced,
			'total'  => count( $bookings ),
		);
	}

	/**
	 * Map Facebook status to BookingX status.
	 *
	 * @param string $status Facebook status.
	 * @return string
	 */
	private function map_status_to_bkx( $status ) {
		$map = array(
			'pending'   => 'bkx-pending',
			'confirmed' => 'bkx-ack',
			'completed' => 'bkx-completed',
			'cancelled' => 'bkx-cancelled',
			'no_show'   => 'bkx-missed',
		);

		return $map[ $status ] ?? 'bkx-pending';
	}

	/**
	 * Map BookingX status to Facebook status.
	 *
	 * @param string $bkx_status BookingX status.
	 * @return string
	 */
	private function map_bkx_status_to_fb( $bkx_status ) {
		$map = array(
			'bkx-pending'   => 'pending',
			'bkx-ack'       => 'confirmed',
			'bkx-completed' => 'completed',
			'bkx-cancelled' => 'cancelled',
			'bkx-missed'    => 'no_show',
		);

		return $map[ $bkx_status ] ?? 'pending';
	}

	/**
	 * Delete old bookings.
	 *
	 * @param int $days Days to keep.
	 * @return int Number of deleted bookings.
	 */
	public function cleanup_old_bookings( $days = 365 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_bookings';
		$cutoff = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$table} WHERE booking_date < %s AND status IN ('completed', 'cancelled')",
			$cutoff
		) );

		return absint( $deleted );
	}

	/**
	 * Export bookings as CSV.
	 *
	 * @param string $page_id Page ID.
	 * @param array  $args    Query arguments.
	 * @return string CSV content.
	 */
	public function export_csv( $page_id, $args = array() ) {
		$bookings = $this->get_page_bookings( $page_id, array_merge( $args, array( 'limit' => 1000 ) ) );

		$output = fopen( 'php://temp', 'r+' );

		// Header row.
		fputcsv( $output, array(
			'Booking ID',
			'BKX Booking ID',
			'Customer Name',
			'Customer Email',
			'Customer Phone',
			'Service ID',
			'Date',
			'Start Time',
			'End Time',
			'Status',
			'Source',
			'Created At',
		) );

		foreach ( $bookings as $booking ) {
			fputcsv( $output, array(
				$booking->fb_booking_id,
				$booking->bkx_booking_id,
				$booking->customer_name,
				$booking->customer_email,
				$booking->customer_phone,
				$booking->service_id,
				$booking->booking_date,
				$booking->start_time,
				$booking->end_time,
				$booking->status,
				$booking->source,
				$booking->created_at,
			) );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Get booking statistics.
	 *
	 * @param string $page_id Page ID (optional).
	 * @param string $period  Period (today, week, month, year).
	 * @return array
	 */
	public function get_stats( $page_id = null, $period = 'month' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_fb_bookings';

		$where = '1=1';
		if ( $page_id ) {
			$where = $wpdb->prepare( 'page_id = %s', $page_id );
		}

		// Determine date range.
		$today = current_time( 'Y-m-d' );

		switch ( $period ) {
			case 'today':
				$start_date = $today;
				break;

			case 'week':
				$start_date = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
				break;

			case 'month':
				$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
				break;

			case 'year':
				$start_date = gmdate( 'Y-m-d', strtotime( '-365 days' ) );
				break;

			default:
				$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
				break;
		}

		$where .= $wpdb->prepare( ' AND created_at >= %s', $start_date );

		// Total bookings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );

		// Confirmed bookings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$confirmed = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where} AND status = 'confirmed'" );

		// Cancelled bookings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cancelled = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where} AND status = 'cancelled'" );

		// Today's bookings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$today_bookings = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE {$where} AND booking_date = %s",
			$today
		) );

		// Bookings by source.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by_source = $wpdb->get_results( "SELECT source, COUNT(*) as count FROM {$table} WHERE {$where} GROUP BY source" );

		return array(
			'total_bookings'  => absint( $total ),
			'confirmed'       => absint( $confirmed ),
			'cancelled'       => absint( $cancelled ),
			'today_bookings'  => absint( $today_bookings ),
			'conversion_rate' => $total > 0 ? round( ( $confirmed / $total ) * 100, 1 ) : 0,
			'by_source'       => $by_source,
		);
	}
}

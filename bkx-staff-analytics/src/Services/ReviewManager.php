<?php
/**
 * Review Manager Service.
 *
 * @package BookingX\StaffAnalytics\Services
 * @since   1.0.0
 */

namespace BookingX\StaffAnalytics\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ReviewManager Class.
 */
class ReviewManager {

	/**
	 * Submit a review.
	 *
	 * @param array $data Review data.
	 * @return int|\WP_Error Review ID or error.
	 */
	public function submit_review( $data ) {
		global $wpdb;

		// Validate.
		if ( empty( $data['staff_id'] ) ) {
			return new \WP_Error( 'missing_staff', __( 'Staff member is required', 'bkx-staff-analytics' ) );
		}

		if ( empty( $data['booking_id'] ) ) {
			return new \WP_Error( 'missing_booking', __( 'Booking is required', 'bkx-staff-analytics' ) );
		}

		if ( empty( $data['rating'] ) || $data['rating'] < 1 || $data['rating'] > 5 ) {
			return new \WP_Error( 'invalid_rating', __( 'Rating must be between 1 and 5', 'bkx-staff-analytics' ) );
		}

		// Check if booking belongs to this staff.
		$booking_staff = get_post_meta( $data['booking_id'], 'seat_id', true );
		if ( absint( $booking_staff ) !== absint( $data['staff_id'] ) ) {
			return new \WP_Error( 'invalid_booking', __( 'Booking does not belong to this staff member', 'bkx-staff-analytics' ) );
		}

		// Check for duplicate review.
		$table    = $wpdb->prefix . 'bkx_staff_reviews';
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE booking_id = %d",
				$data['booking_id']
			)
		);

		if ( $existing ) {
			return new \WP_Error( 'duplicate_review', __( 'A review already exists for this booking', 'bkx-staff-analytics' ) );
		}

		// Auto-approve if settings allow.
		$auto_approve = apply_filters( 'bkx_staff_auto_approve_reviews', false );

		$result = $wpdb->insert(
			$table,
			array(
				'staff_id'    => absint( $data['staff_id'] ),
				'booking_id'  => absint( $data['booking_id'] ),
				'customer_id' => absint( $data['customer_id'] ?? 0 ),
				'rating'      => absint( $data['rating'] ),
				'review_text' => sanitize_textarea_field( $data['review_text'] ?? '' ),
				'is_approved' => $auto_approve ? 1 : 0,
				'reviewed_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%d', '%s' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Database error occurred', 'bkx-staff-analytics' ) );
		}

		$review_id = $wpdb->insert_id;

		// Update staff average rating if auto-approved.
		if ( $auto_approve ) {
			$this->update_staff_rating( $data['staff_id'] );
		}

		// Trigger action for notifications.
		do_action( 'bkx_staff_review_submitted', $review_id, $data );

		return $review_id;
	}

	/**
	 * Set review approval status.
	 *
	 * @param int  $review_id Review ID.
	 * @param bool $approved  Approval status.
	 * @return bool
	 */
	public function set_approval( $review_id, $approved = true ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_reviews';

		// Get staff ID before updating.
		$review = $wpdb->get_row(
			$wpdb->prepare( "SELECT staff_id FROM {$table} WHERE id = %d", $review_id ),
			ARRAY_A
		);

		if ( ! $review ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			array( 'is_approved' => $approved ? 1 : 0 ),
			array( 'id' => $review_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			// Update staff average rating.
			$this->update_staff_rating( $review['staff_id'] );
		}

		return $result !== false;
	}

	/**
	 * Delete a review.
	 *
	 * @param int $review_id Review ID.
	 * @return bool
	 */
	public function delete_review( $review_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_reviews';

		// Get staff ID before deleting.
		$review = $wpdb->get_row(
			$wpdb->prepare( "SELECT staff_id FROM {$table} WHERE id = %d", $review_id ),
			ARRAY_A
		);

		$result = $wpdb->delete( $table, array( 'id' => $review_id ), array( '%d' ) );

		if ( $result && $review ) {
			// Update staff average rating.
			$this->update_staff_rating( $review['staff_id'] );
		}

		return $result !== false;
	}

	/**
	 * Get staff reviews.
	 *
	 * @param int   $staff_id Staff ID.
	 * @param array $args     Query arguments.
	 * @return array
	 */
	public function get_staff_reviews( $staff_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'approved_only' => true,
			'limit'         => 20,
			'offset'        => 0,
			'order'         => 'DESC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_staff_reviews';

		$where = $wpdb->prepare( "WHERE staff_id = %d", $staff_id );

		if ( $args['approved_only'] ) {
			$where .= " AND is_approved = 1";
		}

		$order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*,
					COALESCE(u.display_name, 'Guest') as customer_name
				FROM {$table} r
				LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
				{$where}
				ORDER BY r.reviewed_at {$order}
				LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			),
			ARRAY_A
		);

		return $reviews;
	}

	/**
	 * Get pending reviews.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_pending_reviews( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_staff_reviews';

		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*,
					p.post_title as staff_name,
					COALESCE(u.display_name, 'Guest') as customer_name
				FROM {$table} r
				LEFT JOIN {$wpdb->posts} p ON r.staff_id = p.ID
				LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
				WHERE r.is_approved = 0
				ORDER BY r.reviewed_at DESC
				LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			),
			ARRAY_A
		);

		return $reviews;
	}

	/**
	 * Get staff rating summary.
	 *
	 * @param int $staff_id Staff ID.
	 * @return array
	 */
	public function get_rating_summary( $staff_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_reviews';

		// Overall stats.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					AVG(rating) as avg_rating,
					COUNT(*) as total_reviews,
					SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
					SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
					SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
					SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
					SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
				FROM {$table}
				WHERE staff_id = %d AND is_approved = 1",
				$staff_id
			),
			ARRAY_A
		);

		// Recent trend (last 30 days vs previous 30 days).
		$recent = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(rating) FROM {$table}
				WHERE staff_id = %d AND is_approved = 1 AND reviewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
				$staff_id
			)
		);

		$previous = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(rating) FROM {$table}
				WHERE staff_id = %d AND is_approved = 1
				AND reviewed_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
				AND reviewed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
				$staff_id
			)
		);

		$trend = 0;
		if ( $previous > 0 && $recent > 0 ) {
			$trend = round( $recent - $previous, 2 );
		}

		return array(
			'avg_rating'    => round( floatval( $stats['avg_rating'] ), 2 ),
			'total_reviews' => (int) $stats['total_reviews'],
			'distribution'  => array(
				5 => (int) $stats['five_star'],
				4 => (int) $stats['four_star'],
				3 => (int) $stats['three_star'],
				2 => (int) $stats['two_star'],
				1 => (int) $stats['one_star'],
			),
			'trend'         => $trend,
		);
	}

	/**
	 * Update staff average rating in post meta.
	 *
	 * @param int $staff_id Staff ID.
	 */
	private function update_staff_rating( $staff_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_reviews';

		$avg = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(rating) FROM {$table} WHERE staff_id = %d AND is_approved = 1",
				$staff_id
			)
		);

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE staff_id = %d AND is_approved = 1",
				$staff_id
			)
		);

		update_post_meta( $staff_id, '_bkx_avg_rating', round( floatval( $avg ), 2 ) );
		update_post_meta( $staff_id, '_bkx_review_count', absint( $count ) );
	}

	/**
	 * Get recent reviews across all staff.
	 *
	 * @param int $limit Number of reviews.
	 * @return array
	 */
	public function get_recent_reviews( $limit = 10 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_reviews';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*,
					p.post_title as staff_name,
					COALESCE(u.display_name, 'Guest') as customer_name
				FROM {$table} r
				LEFT JOIN {$wpdb->posts} p ON r.staff_id = p.ID
				LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
				WHERE r.is_approved = 1
				ORDER BY r.reviewed_at DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}
}

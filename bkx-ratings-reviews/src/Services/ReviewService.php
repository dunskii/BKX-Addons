<?php
/**
 * Review Service
 *
 * Handles review submission, retrieval, and management.
 *
 * @package BookingX\RatingsReviews
 * @since   1.0.0
 */

namespace BookingX\RatingsReviews\Services;

use BookingX\RatingsReviews\RatingsReviewsAddon;

/**
 * Review Service class.
 *
 * @since 1.0.0
 */
class ReviewService {

	/**
	 * Addon instance.
	 *
	 * @var RatingsReviewsAddon
	 */
	protected RatingsReviewsAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param RatingsReviewsAddon $addon Addon instance.
	 */
	public function __construct( RatingsReviewsAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Submit a review.
	 *
	 * @since 1.0.0
	 * @param array $review_data Review data.
	 * @return array Result with success and message.
	 */
	public function submit_review( array $review_data ): array {
		// Validate required fields
		if ( empty( $review_data['booking_id'] ) || empty( $review_data['rating'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Missing required fields.', 'bkx-ratings-reviews' ),
			);
		}

		// Check if user has already reviewed this booking
		if ( $this->has_user_reviewed( $review_data['booking_id'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'You have already reviewed this booking.', 'bkx-ratings-reviews' ),
			);
		}

		// Get booking details
		$booking = get_post( $review_data['booking_id'] );
		if ( ! $booking ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid booking.', 'bkx-ratings-reviews' ),
			);
		}

		$service_id = get_post_meta( $booking->ID, 'base_id', true );
		$seat_id = get_post_meta( $booking->ID, 'seat_id', true );
		$customer_email = get_post_meta( $booking->ID, 'customer_email', true );

		// Determine review status
		$status = $this->addon->get_setting( 'auto_approve_reviews', false ) ? 'approved' : 'pending';

		// Insert review
		$review_id = $this->addon->insert( 'reviews', array(
			'booking_id'     => $review_data['booking_id'],
			'service_id'     => $service_id,
			'seat_id'        => $seat_id,
			'customer_email' => $customer_email,
			'rating'         => intval( $review_data['rating'] ),
			'review_text'    => sanitize_textarea_field( $review_data['review_text'] ?? '' ),
			'status'         => $status,
			'created_at'     => current_time( 'mysql' ),
		) );

		if ( ! $review_id ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to submit review.', 'bkx-ratings-reviews' ),
			);
		}

		// Update average rating
		$this->update_service_rating( $service_id );

		return array(
			'success'   => true,
			'message'   => __( 'Review submitted successfully!', 'bkx-ratings-reviews' ),
			'review_id' => $review_id,
		);
	}

	/**
	 * Check if user has already reviewed a booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	protected function has_user_reviewed( int $booking_id ): bool {
		global $wpdb;

		$table = $this->addon->get_table_name( 'reviews' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE booking_id = %d",
				$table,
				$booking_id
			)
		);

		return intval( $count ) > 0;
	}

	/**
	 * Get reviews for a service.
	 *
	 * @since 1.0.0
	 * @param int $service_id Service ID.
	 * @param int $page       Page number.
	 * @param int $per_page   Reviews per page.
	 * @return array Reviews.
	 */
	public function get_service_reviews( int $service_id, int $page = 1, int $per_page = 10 ): array {
		global $wpdb;

		$table = $this->addon->get_table_name( 'reviews' );
		$offset = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE service_id = %d AND status = 'approved' ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$table,
				$service_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Get average rating for a service.
	 *
	 * @since 1.0.0
	 * @param int $service_id Service ID.
	 * @return float Average rating.
	 */
	public function get_average_rating( int $service_id ): float {
		global $wpdb;

		$table = $this->addon->get_table_name( 'reviews' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$avg = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(rating) FROM %i WHERE service_id = %d AND status = 'approved'",
				$table,
				$service_id
			)
		);

		return round( floatval( $avg ), 1 );
	}

	/**
	 * Get review count for a service.
	 *
	 * @since 1.0.0
	 * @param int $service_id Service ID.
	 * @return int Review count.
	 */
	public function get_review_count( int $service_id ): int {
		global $wpdb;

		$table = $this->addon->get_table_name( 'reviews' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE service_id = %d AND status = 'approved'",
				$table,
				$service_id
			)
		);

		return intval( $count );
	}

	/**
	 * Update service average rating.
	 *
	 * @since 1.0.0
	 * @param int $service_id Service ID.
	 * @return void
	 */
	protected function update_service_rating( int $service_id ): void {
		$average = $this->get_average_rating( $service_id );
		$count = $this->get_review_count( $service_id );

		update_post_meta( $service_id, '_bkx_average_rating', $average );
		update_post_meta( $service_id, '_bkx_review_count', $count );
	}

	/**
	 * Record a vote on a review.
	 *
	 * @since 1.0.0
	 * @param int    $review_id Review ID.
	 * @param string $vote_type Vote type (helpful or not_helpful).
	 * @return array Result.
	 */
	public function record_vote( int $review_id, string $vote_type ): array {
		$field = 'helpful' === $vote_type ? 'helpful_count' : 'not_helpful_count';

		global $wpdb;
		$table = $this->addon->get_table_name( 'reviews' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET {$field} = {$field} + 1 WHERE id = %d",
				$table,
				$review_id
			)
		);

		return array(
			'success' => true,
			'message' => __( 'Vote recorded.', 'bkx-ratings-reviews' ),
		);
	}
}

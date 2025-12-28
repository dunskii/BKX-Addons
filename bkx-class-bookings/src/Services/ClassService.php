<?php
/**
 * Class Service
 *
 * @package BookingX\ClassBookings\Services
 * @since   1.0.0
 */

namespace BookingX\ClassBookings\Services;

/**
 * Service for managing classes.
 *
 * @since 1.0.0
 */
class ClassService {

	/**
	 * Get all classes.
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_classes( array $args = array() ): array {
		$defaults = array(
			'post_type'      => 'bkx_class',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$query = new \WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Get a single class.
	 *
	 * @since 1.0.0
	 * @param int $class_id Class post ID.
	 * @return \WP_Post|null
	 */
	public function get_class( int $class_id ): ?\WP_Post {
		$post = get_post( $class_id );

		if ( ! $post || 'bkx_class' !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Get class meta data.
	 *
	 * @since 1.0.0
	 * @param int $class_id Class post ID.
	 * @return array
	 */
	public function get_class_meta( int $class_id ): array {
		return array(
			'duration'           => (int) get_post_meta( $class_id, '_bkx_class_duration', true ) ?: 60,
			'capacity'           => (int) get_post_meta( $class_id, '_bkx_class_capacity', true ) ?: 10,
			'price'              => (float) get_post_meta( $class_id, '_bkx_class_price', true ) ?: 0,
			'allow_waitlist'     => (bool) get_post_meta( $class_id, '_bkx_class_allow_waitlist', true ),
			'min_participants'   => (int) get_post_meta( $class_id, '_bkx_class_min_participants', true ) ?: 1,
			'instructor_id'      => (int) get_post_meta( $class_id, '_bkx_class_instructor_id', true ),
			'location'           => get_post_meta( $class_id, '_bkx_class_location', true ) ?: '',
			'color'              => get_post_meta( $class_id, '_bkx_class_color', true ) ?: '#3788d8',
			'cancellation_hours' => (int) get_post_meta( $class_id, '_bkx_class_cancellation_hours', true ) ?: 24,
		);
	}

	/**
	 * Update class meta data.
	 *
	 * @since 1.0.0
	 * @param int   $class_id Class post ID.
	 * @param array $meta     Meta data to update.
	 * @return void
	 */
	public function update_class_meta( int $class_id, array $meta ): void {
		$allowed_keys = array(
			'duration',
			'capacity',
			'price',
			'allow_waitlist',
			'min_participants',
			'instructor_id',
			'location',
			'color',
			'cancellation_hours',
		);

		foreach ( $meta as $key => $value ) {
			if ( in_array( $key, $allowed_keys, true ) ) {
				update_post_meta( $class_id, '_bkx_class_' . $key, $value );
			}
		}
	}

	/**
	 * Get classes by category.
	 *
	 * @since 1.0.0
	 * @param int|string $category Category ID or slug.
	 * @return array
	 */
	public function get_classes_by_category( $category ): array {
		$args = array(
			'post_type'      => 'bkx_class',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'bkx_class_category',
					'field'    => is_numeric( $category ) ? 'term_id' : 'slug',
					'terms'    => $category,
				),
			),
		);

		$query = new \WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Get classes by instructor.
	 *
	 * @since 1.0.0
	 * @param int $instructor_id Seat (instructor) post ID.
	 * @return array
	 */
	public function get_classes_by_instructor( int $instructor_id ): array {
		$args = array(
			'post_type'      => 'bkx_class',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_bkx_class_instructor_id',
					'value'   => $instructor_id,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
			),
		);

		$query = new \WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Get available spots for a class session.
	 *
	 * @since 1.0.0
	 * @param int $session_id Session ID.
	 * @return int
	 */
	public function get_available_spots( int $session_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_class_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT capacity, booked_count FROM {$table} WHERE id = %d",
				$session_id
			)
		);

		if ( ! $session ) {
			return 0;
		}

		return max( 0, $session->capacity - $session->booked_count );
	}

	/**
	 * Check if a class session is bookable.
	 *
	 * @since 1.0.0
	 * @param int $session_id Session ID.
	 * @param int $quantity   Number of spots requested.
	 * @return bool
	 */
	public function is_session_bookable( int $session_id, int $quantity = 1 ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_class_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$session_id
			)
		);

		if ( ! $session ) {
			return false;
		}

		// Check status.
		if ( 'scheduled' !== $session->status ) {
			return false;
		}

		// Check if session is in the past.
		$session_datetime = $session->session_date . ' ' . $session->start_time;
		if ( strtotime( $session_datetime ) < current_time( 'timestamp' ) ) {
			return false;
		}

		// Check capacity.
		$available = $session->capacity - $session->booked_count;
		if ( $available < $quantity ) {
			return false;
		}

		return true;
	}

	/**
	 * Get upcoming classes with sessions.
	 *
	 * @since 1.0.0
	 * @param int    $limit Number of classes to return.
	 * @param string $date  Start date (Y-m-d format).
	 * @return array
	 */
	public function get_upcoming_classes( int $limit = 10, string $date = '' ): array {
		global $wpdb;

		if ( empty( $date ) ) {
			$date = current_time( 'Y-m-d' );
		}

		$sessions_table = $wpdb->prefix . 'bkx_class_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, p.post_title as class_name
				FROM {$sessions_table} s
				INNER JOIN {$wpdb->posts} p ON s.class_id = p.ID
				WHERE s.session_date >= %s
				AND s.status = 'scheduled'
				AND p.post_status = 'publish'
				ORDER BY s.session_date ASC, s.start_time ASC
				LIMIT %d",
				$date,
				$limit
			)
		);

		return $sessions ?: array();
	}
}

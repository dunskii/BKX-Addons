<?php
/**
 * Recurrence Service
 *
 * @package BookingX\RecurringBookings\Services
 * @since   1.0.0
 */

namespace BookingX\RecurringBookings\Services;

use BookingX\RecurringBookings\RecurringBookingsAddon;
use BookingX\RecurringBookings\Patterns\PatternInterface;
use BookingX\RecurringBookings\Patterns\DailyPattern;
use BookingX\RecurringBookings\Patterns\WeeklyPattern;
use BookingX\RecurringBookings\Patterns\BiweeklyPattern;
use BookingX\RecurringBookings\Patterns\MonthlyPattern;
use BookingX\RecurringBookings\Patterns\CustomPattern;
use DateTimeImmutable;
use WP_Error;

/**
 * Service for managing recurrence patterns and series.
 *
 * @since 1.0.0
 */
class RecurrenceService {

	/**
	 * Addon instance.
	 *
	 * @var RecurringBookingsAddon
	 */
	protected RecurringBookingsAddon $addon;

	/**
	 * Series table.
	 *
	 * @var string
	 */
	protected string $series_table;

	/**
	 * Exclusions table.
	 *
	 * @var string
	 */
	protected string $exclusions_table;

	/**
	 * Registered patterns.
	 *
	 * @var array<string, PatternInterface>
	 */
	protected array $patterns = array();

	/**
	 * Constructor.
	 *
	 * @param RecurringBookingsAddon $addon Addon instance.
	 */
	public function __construct( RecurringBookingsAddon $addon ) {
		global $wpdb;

		$this->addon            = $addon;
		$this->series_table     = $wpdb->prefix . 'bkx_recurring_series';
		$this->exclusions_table = $wpdb->prefix . 'bkx_recurring_exclusions';

		$this->register_default_patterns();
	}

	/**
	 * Register default patterns.
	 *
	 * @return void
	 */
	protected function register_default_patterns(): void {
		$this->patterns = array(
			'daily'    => new DailyPattern(),
			'weekly'   => new WeeklyPattern(),
			'biweekly' => new BiweeklyPattern(),
			'monthly'  => new MonthlyPattern(),
			'custom'   => new CustomPattern(),
		);

		/**
		 * Filter registered recurrence patterns.
		 *
		 * @param array<string, PatternInterface> $patterns Registered patterns.
		 */
		$this->patterns = apply_filters( 'bkx_recurring_patterns', $this->patterns );
	}

	/**
	 * Get pattern by key.
	 *
	 * @param string $key Pattern key.
	 * @return PatternInterface|null Pattern instance or null.
	 */
	public function get_pattern( string $key ): ?PatternInterface {
		return $this->patterns[ $key ] ?? null;
	}

	/**
	 * Get all patterns.
	 *
	 * @return array<string, PatternInterface>
	 */
	public function get_patterns(): array {
		return $this->patterns;
	}

	/**
	 * Create a recurring series.
	 *
	 * @param int    $master_booking_id Master booking ID.
	 * @param string $pattern Pattern key.
	 * @param array  $options Series options.
	 * @return int|WP_Error Series ID or error.
	 */
	public function create_series( int $master_booking_id, string $pattern, array $options = array() ) {
		global $wpdb;

		$pattern_obj = $this->get_pattern( $pattern );

		if ( ! $pattern_obj ) {
			return new WP_Error( 'invalid_pattern', __( 'Invalid recurrence pattern.', 'bkx-recurring-bookings' ) );
		}

		// Validate pattern options.
		$pattern_options = $options['pattern_options'] ?? array();
		if ( ! $pattern_obj->validate_options( $pattern_options ) ) {
			return new WP_Error( 'invalid_options', __( 'Invalid pattern options.', 'bkx-recurring-bookings' ) );
		}

		// Get booking data.
		$booking = get_post( $master_booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new WP_Error( 'invalid_booking', __( 'Invalid booking.', 'bkx-recurring-bookings' ) );
		}

		$booking_date = get_post_meta( $master_booking_id, 'booking_date', true );
		$booking_time = get_post_meta( $master_booking_id, 'booking_time', true );
		$end_time     = get_post_meta( $master_booking_id, 'booking_time_end', true );
		$seat_id      = get_post_meta( $master_booking_id, 'seat_id', true );
		$base_id      = get_post_meta( $master_booking_id, 'base_id', true );
		$customer_id  = get_post_meta( $master_booking_id, 'customer_id', true );

		// Calculate end date.
		$end_date       = null;
		$max_occurrences = $options['occurrences'] ?? $this->addon->get_setting( 'max_occurrences', 52 );

		if ( ! empty( $options['end_date'] ) ) {
			$end_date = $options['end_date'];
		} elseif ( ! empty( $options['end_after'] ) ) {
			$max_occurrences = min( (int) $options['end_after'], $max_occurrences );
		}

		// Calculate recurring discount.
		$discount = 0;
		if ( ! empty( $options['apply_discount'] ) ) {
			$discount = $this->addon->get_setting( 'recurring_discount', 0 );
		}

		// Insert series.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->series_table,
			array(
				'master_booking_id' => $master_booking_id,
				'customer_id'       => $customer_id ?: get_current_user_id(),
				'seat_id'           => $seat_id,
				'base_id'           => $base_id,
				'pattern'           => $pattern,
				'pattern_options'   => wp_json_encode( $pattern_options ),
				'start_date'        => $booking_date,
				'end_date'          => $end_date,
				'start_time'        => $booking_time,
				'end_time'          => $end_time,
				'timezone'          => wp_timezone_string(),
				'max_occurrences'   => $max_occurrences,
				'recurring_discount' => $discount,
				'status'            => 'active',
				'metadata'          => wp_json_encode( $options['metadata'] ?? array() ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create series.', 'bkx-recurring-bookings' ) );
		}

		$series_id = $wpdb->insert_id;

		/**
		 * Fires when a recurring series is created.
		 *
		 * @param int   $series_id Series ID.
		 * @param int   $master_booking_id Master booking ID.
		 * @param array $options Series options.
		 */
		do_action( 'bkx_recurring_series_created', $series_id, $master_booking_id, $options );

		return $series_id;
	}

	/**
	 * Get series by ID.
	 *
	 * @param int $series_id Series ID.
	 * @return array|null Series data or null.
	 */
	public function get_series( int $series_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$series = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$this->series_table,
				$series_id
			),
			ARRAY_A
		);

		if ( ! $series ) {
			return null;
		}

		$series['pattern_options'] = json_decode( $series['pattern_options'], true ) ?: array();
		$series['metadata']        = json_decode( $series['metadata'], true ) ?: array();

		return $series;
	}

	/**
	 * Get all series.
	 *
	 * @param array $args Query arguments.
	 * @return array Series list.
	 */
	public function get_all_series( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'      => 'active',
			'customer_id' => 0,
			'seat_id'     => 0,
			'limit'       => 20,
			'offset'      => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array( $this->series_table );

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['customer_id'] ) ) {
			$where[]  = 'customer_id = %d';
			$params[] = $args['customer_id'];
		}

		if ( ! empty( $args['seat_id'] ) ) {
			$where[]  = 'seat_id = %d';
			$params[] = $args['seat_id'];
		}

		$params[] = $args['limit'];
		$params[] = $args['offset'];

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		);

		foreach ( $results as &$series ) {
			$series['pattern_options'] = json_decode( $series['pattern_options'], true ) ?: array();
			$series['metadata']        = json_decode( $series['metadata'], true ) ?: array();
		}

		return $results;
	}

	/**
	 * Update series.
	 *
	 * @param int   $series_id Series ID.
	 * @param array $data Data to update.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function update_series( int $series_id, array $data ) {
		global $wpdb;

		$series = $this->get_series( $series_id );

		if ( ! $series ) {
			return new WP_Error( 'not_found', __( 'Series not found.', 'bkx-recurring-bookings' ) );
		}

		$allowed_fields = array(
			'end_date',
			'max_occurrences',
			'status',
			'pattern_options',
			'metadata',
		);

		$update_data   = array();
		$update_format = array();

		foreach ( $allowed_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( in_array( $field, array( 'pattern_options', 'metadata' ), true ) ) {
					$update_data[ $field ] = wp_json_encode( $data[ $field ] );
					$update_format[]       = '%s';
				} elseif ( 'max_occurrences' === $field ) {
					$update_data[ $field ] = (int) $data[ $field ];
					$update_format[]       = '%d';
				} else {
					$update_data[ $field ] = sanitize_text_field( $data[ $field ] );
					$update_format[]       = '%s';
				}
			}
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->series_table,
			$update_data,
			array( 'id' => $series_id ),
			$update_format,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to update series.', 'bkx-recurring-bookings' ) );
		}

		/**
		 * Fires when a series is updated.
		 *
		 * @param int   $series_id Series ID.
		 * @param array $data Updated data.
		 */
		do_action( 'bkx_recurring_series_updated', $series_id, $data );

		return true;
	}

	/**
	 * Cancel a series.
	 *
	 * @param int    $series_id Series ID.
	 * @param string $reason Cancellation reason.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function cancel_series( int $series_id, string $reason = '' ) {
		$result = $this->update_series(
			$series_id,
			array(
				'status'   => 'cancelled',
				'metadata' => array(
					'cancellation_reason' => $reason,
					'cancelled_at'        => current_time( 'mysql' ),
					'cancelled_by'        => get_current_user_id(),
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires when a series is cancelled.
		 *
		 * @param int    $series_id Series ID.
		 * @param string $reason Cancellation reason.
		 */
		do_action( 'bkx_recurring_series_cancelled', $series_id, $reason );

		return true;
	}

	/**
	 * Add exclusion to series.
	 *
	 * @param int    $series_id Series ID.
	 * @param string $type Exclusion type (date, day_of_week, range).
	 * @param array  $data Exclusion data.
	 * @return int|WP_Error Exclusion ID or error.
	 */
	public function add_exclusion( int $series_id, string $type, array $data ) {
		global $wpdb;

		$series = $this->get_series( $series_id );

		if ( ! $series ) {
			return new WP_Error( 'not_found', __( 'Series not found.', 'bkx-recurring-bookings' ) );
		}

		$insert_data = array(
			'series_id'      => $series_id,
			'exclusion_type' => $type,
			'reason'         => $data['reason'] ?? null,
		);

		switch ( $type ) {
			case 'date':
				$insert_data['exclusion_date'] = $data['date'];
				break;

			case 'day_of_week':
				$insert_data['day_of_week'] = (int) $data['day'];
				break;

			case 'range':
				$insert_data['start_date'] = $data['start_date'];
				$insert_data['end_date']   = $data['end_date'];
				break;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $this->exclusions_table, $insert_data );

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to add exclusion.', 'bkx-recurring-bookings' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get exclusions for series.
	 *
	 * @param int $series_id Series ID.
	 * @return array Exclusions.
	 */
	public function get_exclusions( int $series_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE series_id = %d ORDER BY created_at DESC',
				$this->exclusions_table,
				$series_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Check if date is excluded.
	 *
	 * @param int               $series_id Series ID.
	 * @param DateTimeImmutable $date Date to check.
	 * @return bool True if excluded.
	 */
	public function is_date_excluded( int $series_id, DateTimeImmutable $date ): bool {
		$exclusions = $this->get_exclusions( $series_id );

		$date_str    = $date->format( 'Y-m-d' );
		$day_of_week = (int) $date->format( 'w' );

		foreach ( $exclusions as $exclusion ) {
			switch ( $exclusion['exclusion_type'] ) {
				case 'date':
					if ( $exclusion['exclusion_date'] === $date_str ) {
						return true;
					}
					break;

				case 'day_of_week':
					if ( (int) $exclusion['day_of_week'] === $day_of_week ) {
						return true;
					}
					break;

				case 'range':
					if ( $date_str >= $exclusion['start_date'] && $date_str <= $exclusion['end_date'] ) {
						return true;
					}
					break;
			}
		}

		return false;
	}

	/**
	 * Get preview of occurrences.
	 *
	 * @param string $pattern Pattern key.
	 * @param string $start_date Start date (Y-m-d).
	 * @param array  $options Pattern options.
	 * @return array Preview data.
	 */
	public function get_preview( string $pattern, string $start_date, array $options = array() ): array {
		$pattern_obj = $this->get_pattern( $pattern );

		if ( ! $pattern_obj ) {
			return array(
				'error' => __( 'Invalid pattern.', 'bkx-recurring-bookings' ),
			);
		}

		try {
			$start = new DateTimeImmutable( $start_date );
		} catch ( \Exception $e ) {
			return array(
				'error' => __( 'Invalid start date.', 'bkx-recurring-bookings' ),
			);
		}

		$count       = min( (int) ( $options['preview_count'] ?? 5 ), 12 );
		$occurrences = $pattern_obj->generate( $start, $count, $options );

		$dates = array();
		foreach ( $occurrences as $occurrence ) {
			$dates[] = array(
				'date'      => $occurrence->format( 'Y-m-d' ),
				'formatted' => wp_date( get_option( 'date_format' ), $occurrence->getTimestamp() ),
				'day'       => wp_date( 'l', $occurrence->getTimestamp() ),
			);
		}

		return array(
			'pattern'     => $pattern,
			'description' => $pattern_obj->get_description( $options ),
			'dates'       => $dates,
			'total_count' => count( $dates ),
		);
	}

	/**
	 * Increment completed occurrences.
	 *
	 * @param int $series_id Series ID.
	 * @return void
	 */
	public function increment_completed( int $series_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET completed_occurrences = completed_occurrences + 1 WHERE id = %d',
				$this->series_table,
				$series_id
			)
		);
	}

	/**
	 * Increment total occurrences.
	 *
	 * @param int $series_id Series ID.
	 * @param int $count Count to add.
	 * @return void
	 */
	public function increment_total( int $series_id, int $count = 1 ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET total_occurrences = total_occurrences + %d WHERE id = %d',
				$this->series_table,
				$count,
				$series_id
			)
		);
	}
}

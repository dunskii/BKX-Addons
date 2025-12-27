<?php
/**
 * Instance Generator Service
 *
 * @package BookingX\RecurringBookings\Services
 * @since   1.0.0
 */

namespace BookingX\RecurringBookings\Services;

use BookingX\RecurringBookings\RecurringBookingsAddon;
use DateTimeImmutable;
use WP_Error;

/**
 * Service for generating and managing booking instances.
 *
 * @since 1.0.0
 */
class InstanceGenerator {

	/**
	 * Addon instance.
	 *
	 * @var RecurringBookingsAddon
	 */
	protected RecurringBookingsAddon $addon;

	/**
	 * Recurrence service.
	 *
	 * @var RecurrenceService
	 */
	protected RecurrenceService $recurrence_service;

	/**
	 * Instances table.
	 *
	 * @var string
	 */
	protected string $instances_table;

	/**
	 * Constructor.
	 *
	 * @param RecurringBookingsAddon $addon Addon instance.
	 * @param RecurrenceService      $recurrence_service Recurrence service.
	 */
	public function __construct( RecurringBookingsAddon $addon, RecurrenceService $recurrence_service ) {
		global $wpdb;

		$this->addon              = $addon;
		$this->recurrence_service = $recurrence_service;
		$this->instances_table    = $wpdb->prefix . 'bkx_recurring_instances';
	}

	/**
	 * Generate instances for a series.
	 *
	 * @param int $series_id Series ID.
	 * @return array Generated instance IDs.
	 */
	public function generate_instances_for_series( int $series_id ): array {
		$series = $this->recurrence_service->get_series( $series_id );

		if ( ! $series || 'active' !== $series['status'] ) {
			return array();
		}

		$pattern = $this->recurrence_service->get_pattern( $series['pattern'] );

		if ( ! $pattern ) {
			return array();
		}

		// Get last generated instance.
		$last_instance = $this->get_last_instance( $series_id );
		$start_date    = $last_instance
			? new DateTimeImmutable( $last_instance['scheduled_date'] )
			: new DateTimeImmutable( $series['start_date'] );

		// Calculate how many more instances we can generate.
		$remaining = $series['max_occurrences'] - $series['total_occurrences'];

		if ( $remaining <= 0 ) {
			return array();
		}

		// Generate ahead for configured days.
		$generate_ahead = $this->addon->get_setting( 'generate_ahead_days', 30 );
		$end_limit      = new DateTimeImmutable( "+{$generate_ahead} days" );

		if ( ! empty( $series['end_date'] ) ) {
			$series_end = new DateTimeImmutable( $series['end_date'] );
			if ( $series_end < $end_limit ) {
				$end_limit = $series_end;
			}
		}

		// Generate occurrences.
		$options     = $series['pattern_options'];
		$options['end_date'] = $end_limit->format( 'Y-m-d' );

		// If we have a last instance, start from next occurrence.
		if ( $last_instance ) {
			$next_date    = $pattern->get_next( $start_date, $options );
			$occurrences  = $next_date ? array( $next_date ) : array();
			$current      = $next_date;
			$count        = 1;

			while ( $current && $count < $remaining ) {
				$next = $pattern->get_next( $current, $options );

				if ( ! $next || $next > $end_limit ) {
					break;
				}

				$occurrences[] = $next;
				$current       = $next;
				++$count;
			}
		} else {
			$occurrences = $pattern->generate( $start_date, min( $remaining, 100 ), $options );
			// Remove first occurrence (master booking).
			array_shift( $occurrences );
		}

		// Filter out excluded dates.
		$occurrences = array_filter(
			$occurrences,
			function ( $date ) use ( $series_id, $end_limit ) {
				return $date <= $end_limit && ! $this->recurrence_service->is_date_excluded( $series_id, $date );
			}
		);

		// Create instances.
		$instance_ids   = array();
		$instance_number = $series['total_occurrences'];

		foreach ( $occurrences as $occurrence ) {
			++$instance_number;
			$instance_id = $this->create_instance(
				$series_id,
				$occurrence,
				$series['start_time'],
				$instance_number
			);

			if ( $instance_id && ! is_wp_error( $instance_id ) ) {
				$instance_ids[] = $instance_id;
			}
		}

		// Update total occurrences.
		if ( ! empty( $instance_ids ) ) {
			$this->recurrence_service->increment_total( $series_id, count( $instance_ids ) );
		}

		return $instance_ids;
	}

	/**
	 * Generate upcoming instances for all active series.
	 *
	 * This is called by the daily cron job.
	 *
	 * @return array Summary of generated instances.
	 */
	public function generate_upcoming_instances(): array {
		$series_list = $this->recurrence_service->get_all_series(
			array(
				'status' => 'active',
				'limit'  => 1000,
			)
		);

		$summary = array(
			'series_processed' => 0,
			'instances_created' => 0,
			'errors'           => array(),
		);

		foreach ( $series_list as $series ) {
			$instance_ids = $this->generate_instances_for_series( $series['id'] );

			++$summary['series_processed'];
			$summary['instances_created'] += count( $instance_ids );
		}

		/**
		 * Fires after generating upcoming instances.
		 *
		 * @param array $summary Generation summary.
		 */
		do_action( 'bkx_recurring_instances_generated', $summary );

		return $summary;
	}

	/**
	 * Create an instance.
	 *
	 * @param int               $series_id Series ID.
	 * @param DateTimeImmutable $date Scheduled date.
	 * @param string            $time Scheduled time.
	 * @param int               $instance_number Instance number in series.
	 * @return int|WP_Error Instance ID or error.
	 */
	public function create_instance( int $series_id, DateTimeImmutable $date, string $time, int $instance_number ) {
		global $wpdb;

		// Check if instance already exists.
		$date_str = $date->format( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE series_id = %d AND scheduled_date = %s',
				$this->instances_table,
				$series_id,
				$date_str
			)
		);

		if ( $exists ) {
			return new WP_Error( 'duplicate', __( 'Instance already exists for this date.', 'bkx-recurring-bookings' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->instances_table,
			array(
				'series_id'       => $series_id,
				'instance_number' => $instance_number,
				'scheduled_date'  => $date_str,
				'scheduled_time'  => $time,
				'status'          => 'scheduled',
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to create instance.', 'bkx-recurring-bookings' ) );
		}

		$instance_id = $wpdb->insert_id;

		/**
		 * Fires when an instance is created.
		 *
		 * @param int               $instance_id Instance ID.
		 * @param int               $series_id Series ID.
		 * @param DateTimeImmutable $date Scheduled date.
		 */
		do_action( 'bkx_recurring_instance_created', $instance_id, $series_id, $date );

		return $instance_id;
	}

	/**
	 * Get instances for a series.
	 *
	 * @param int   $series_id Series ID.
	 * @param array $args Query arguments.
	 * @return array Instances.
	 */
	public function get_instances( int $series_id, array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'     => '',
			'from_date'  => '',
			'to_date'    => '',
			'limit'      => 50,
			'offset'     => 0,
			'order'      => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'series_id = %d' );
		$params = array( $this->instances_table, $series_id );

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['from_date'] ) ) {
			$where[]  = 'scheduled_date >= %s';
			$params[] = $args['from_date'];
		}

		if ( ! empty( $args['to_date'] ) ) {
			$where[]  = 'scheduled_date <= %s';
			$params[] = $args['to_date'];
		}

		$params[] = $args['limit'];
		$params[] = $args['offset'];

		$where_clause = implode( ' AND ', $where );
		$order        = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_clause} ORDER BY scheduled_date {$order} LIMIT %d OFFSET %d",
				...$params
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Get instance by ID.
	 *
	 * @param int $instance_id Instance ID.
	 * @return array|null Instance data or null.
	 */
	public function get_instance( int $instance_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$this->instances_table,
				$instance_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get last instance for series.
	 *
	 * @param int $series_id Series ID.
	 * @return array|null Instance data or null.
	 */
	public function get_last_instance( int $series_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE series_id = %d ORDER BY scheduled_date DESC LIMIT 1',
				$this->instances_table,
				$series_id
			),
			ARRAY_A
		);
	}

	/**
	 * Skip an instance.
	 *
	 * @param int    $instance_id Instance ID.
	 * @param string $reason Skip reason.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function skip_instance( int $instance_id, string $reason = '' ) {
		global $wpdb;

		$instance = $this->get_instance( $instance_id );

		if ( ! $instance ) {
			return new WP_Error( 'not_found', __( 'Instance not found.', 'bkx-recurring-bookings' ) );
		}

		if ( 'scheduled' !== $instance['status'] ) {
			return new WP_Error( 'invalid_status', __( 'Only scheduled instances can be skipped.', 'bkx-recurring-bookings' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->instances_table,
			array(
				'status'      => 'skipped',
				'skip_reason' => $reason,
			),
			array( 'id' => $instance_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to skip instance.', 'bkx-recurring-bookings' ) );
		}

		/**
		 * Fires when an instance is skipped.
		 *
		 * @param int    $instance_id Instance ID.
		 * @param string $reason Skip reason.
		 */
		do_action( 'bkx_recurring_instance_skipped', $instance_id, $reason );

		return true;
	}

	/**
	 * Reschedule an instance.
	 *
	 * @param int    $instance_id Instance ID.
	 * @param string $new_date New date (Y-m-d).
	 * @param string $new_time New time (H:i:s).
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function reschedule_instance( int $instance_id, string $new_date, string $new_time = '' ) {
		global $wpdb;

		$instance = $this->get_instance( $instance_id );

		if ( ! $instance ) {
			return new WP_Error( 'not_found', __( 'Instance not found.', 'bkx-recurring-bookings' ) );
		}

		if ( 'scheduled' !== $instance['status'] ) {
			return new WP_Error( 'invalid_status', __( 'Only scheduled instances can be rescheduled.', 'bkx-recurring-bookings' ) );
		}

		$update_data = array(
			'scheduled_date'    => $new_date,
			'original_date'     => $instance['original_date'] ?? $instance['scheduled_date'],
			'original_time'     => $instance['original_time'] ?? $instance['scheduled_time'],
			'reschedule_reason' => sprintf(
				/* translators: %s: original date */
				__( 'Rescheduled from %s', 'bkx-recurring-bookings' ),
				$instance['scheduled_date']
			),
		);

		if ( ! empty( $new_time ) ) {
			$update_data['scheduled_time'] = $new_time;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->instances_table,
			$update_data,
			array( 'id' => $instance_id )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to reschedule instance.', 'bkx-recurring-bookings' ) );
		}

		/**
		 * Fires when an instance is rescheduled.
		 *
		 * @param int    $instance_id Instance ID.
		 * @param string $new_date New date.
		 * @param string $old_date Old date.
		 */
		do_action( 'bkx_recurring_instance_rescheduled', $instance_id, $new_date, $instance['scheduled_date'] );

		return true;
	}

	/**
	 * Link instance to booking.
	 *
	 * @param int $instance_id Instance ID.
	 * @param int $booking_id Booking ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function link_booking( int $instance_id, int $booking_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->instances_table,
			array(
				'booking_id' => $booking_id,
				'status'     => 'booked',
			),
			array( 'id' => $instance_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to link booking.', 'bkx-recurring-bookings' ) );
		}

		// Store instance reference on booking.
		update_post_meta( $booking_id, '_bkx_recurring_instance_id', $instance_id );

		return true;
	}

	/**
	 * Mark instance as completed.
	 *
	 * @param int $instance_id Instance ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function complete_instance( int $instance_id ) {
		global $wpdb;

		$instance = $this->get_instance( $instance_id );

		if ( ! $instance ) {
			return new WP_Error( 'not_found', __( 'Instance not found.', 'bkx-recurring-bookings' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->instances_table,
			array( 'status' => 'completed' ),
			array( 'id' => $instance_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to complete instance.', 'bkx-recurring-bookings' ) );
		}

		// Increment completed count on series.
		$this->recurrence_service->increment_completed( $instance['series_id'] );

		/**
		 * Fires when an instance is completed.
		 *
		 * @param int $instance_id Instance ID.
		 * @param int $series_id Series ID.
		 */
		do_action( 'bkx_recurring_instance_completed', $instance_id, $instance['series_id'] );

		return true;
	}

	/**
	 * Get upcoming instances for customer.
	 *
	 * @param int $customer_id Customer ID.
	 * @param int $limit Limit.
	 * @return array Instances with series data.
	 */
	public function get_upcoming_for_customer( int $customer_id, int $limit = 10 ): array {
		global $wpdb;

		$series_table = $wpdb->prefix . 'bkx_recurring_series';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.*, s.pattern, s.seat_id, s.base_id
				FROM %i i
				INNER JOIN %i s ON i.series_id = s.id
				WHERE s.customer_id = %d
				AND i.status = 'scheduled'
				AND i.scheduled_date >= CURDATE()
				ORDER BY i.scheduled_date ASC
				LIMIT %d",
				$this->instances_table,
				$series_table,
				$customer_id,
				$limit
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Cleanup expired instances.
	 *
	 * @param int $retention_days Days to retain data.
	 * @return int Number of deleted instances.
	 */
	public function cleanup_expired( int $retention_days ): int {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d', strtotime( "-{$retention_days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE status IN ('completed', 'skipped') AND scheduled_date < %s",
				$this->instances_table,
				$cutoff_date
			)
		);

		return $result ?: 0;
	}

	/**
	 * Get instance statistics for series.
	 *
	 * @param int $series_id Series ID.
	 * @return array Statistics.
	 */
	public function get_series_stats( int $series_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) as count FROM %i WHERE series_id = %d GROUP BY status',
				$this->instances_table,
				$series_id
			),
			ARRAY_A
		) ?: array();

		$result = array(
			'scheduled' => 0,
			'booked'    => 0,
			'completed' => 0,
			'skipped'   => 0,
			'cancelled' => 0,
			'total'     => 0,
		);

		foreach ( $stats as $stat ) {
			$result[ $stat['status'] ] = (int) $stat['count'];
			$result['total']          += (int) $stat['count'];
		}

		return $result;
	}
}

<?php
/**
 * Weekly Pattern
 *
 * @package BookingX\RecurringBookings\Patterns
 * @since   1.0.0
 */

namespace BookingX\RecurringBookings\Patterns;

use DateTimeImmutable;
use DateInterval;

/**
 * Weekly recurrence pattern.
 *
 * @since 1.0.0
 */
class WeeklyPattern extends AbstractPattern {

	/**
	 * Pattern key.
	 *
	 * @var string
	 */
	protected string $key = 'weekly';

	/**
	 * Pattern label.
	 *
	 * @var string
	 */
	protected string $label = 'Weekly';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->label = __( 'Weekly', 'bkx-recurring-bookings' );
	}

	/**
	 * Calculate next occurrence.
	 *
	 * @param DateTimeImmutable $current Current date.
	 * @param array             $options Pattern options.
	 * @return DateTimeImmutable|null Next occurrence or null if none.
	 */
	public function get_next( DateTimeImmutable $current, array $options = array() ): ?DateTimeImmutable {
		$interval = isset( $options['interval'] ) ? max( 1, (int) $options['interval'] ) : 1;
		$days     = isset( $options['days'] ) ? (array) $options['days'] : array();

		// If no specific days, just add weeks.
		if ( empty( $days ) ) {
			return $current->add( new DateInterval( "P{$interval}W" ) );
		}

		// Sort days.
		sort( $days );

		$current_day = (int) $current->format( 'w' );
		$next        = null;

		// Find next day in current week.
		foreach ( $days as $day ) {
			if ( $day > $current_day ) {
				$diff = $day - $current_day;
				$next = $current->add( new DateInterval( "P{$diff}D" ) );
				break;
			}
		}

		// If no day found in current week, go to first day of next interval.
		if ( null === $next ) {
			$first_day   = $days[0];
			$days_to_add = ( 7 * $interval ) - $current_day + $first_day;
			$next        = $current->add( new DateInterval( "P{$days_to_add}D" ) );
		}

		return $next;
	}

	/**
	 * Generate occurrences.
	 *
	 * @param DateTimeImmutable $start Start date.
	 * @param int               $count Number of occurrences.
	 * @param array             $options Pattern options.
	 * @return array Array of DateTimeImmutable objects.
	 */
	public function generate( DateTimeImmutable $start, int $count, array $options = array() ): array {
		$days = isset( $options['days'] ) ? (array) $options['days'] : array();

		// If no specific days or only one day, use parent method.
		if ( count( $days ) <= 1 ) {
			return parent::generate( $start, $count, $options );
		}

		$occurrences    = array();
		$interval       = isset( $options['interval'] ) ? max( 1, (int) $options['interval'] ) : 1;
		$current        = $start;
		$week_start     = $start;
		$max_iterations = $count * 10;
		$iteration      = 0;

		sort( $days );

		// Add start date if it matches a selected day.
		$start_day = (int) $start->format( 'w' );
		if ( in_array( $start_day, $days, true ) ) {
			$occurrences[] = $start;
		}

		while ( count( $occurrences ) < $count && $iteration < $max_iterations ) {
			$next = $this->get_next( $current, $options );

			if ( null === $next ) {
				break;
			}

			// Check end date if provided.
			if ( ! empty( $options['end_date'] ) ) {
				$end_date = new DateTimeImmutable( $options['end_date'] );
				if ( $next > $end_date ) {
					break;
				}
			}

			$occurrences[] = $next;
			$current       = $next;
			++$iteration;
		}

		return $occurrences;
	}

	/**
	 * Get interval between occurrences.
	 *
	 * @param array $options Pattern options.
	 * @return DateInterval
	 */
	protected function get_interval( array $options = array() ): DateInterval {
		$weeks = isset( $options['interval'] ) ? max( 1, (int) $options['interval'] ) : 1;
		return new DateInterval( "P{$weeks}W" );
	}

	/**
	 * Get human-readable description.
	 *
	 * @param array $options Pattern options.
	 * @return string Description.
	 */
	public function get_description( array $options = array() ): string {
		$interval = isset( $options['interval'] ) ? (int) $options['interval'] : 1;
		$days     = isset( $options['days'] ) ? (array) $options['days'] : array();

		if ( 1 === $interval ) {
			$text = __( 'Every week', 'bkx-recurring-bookings' );
		} else {
			$text = sprintf(
				/* translators: %d: number of weeks */
				__( 'Every %d weeks', 'bkx-recurring-bookings' ),
				$interval
			);
		}

		if ( ! empty( $days ) ) {
			$text .= ' ' . sprintf(
				/* translators: %s: day names */
				__( 'on %s', 'bkx-recurring-bookings' ),
				$this->format_days( $days )
			);
		}

		return $text;
	}
}

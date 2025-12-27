<?php
/**
 * Monthly Pattern
 *
 * @package BookingX\RecurringBookings\Patterns
 * @since   1.0.0
 */

namespace BookingX\RecurringBookings\Patterns;

use DateTimeImmutable;
use DateInterval;

/**
 * Monthly recurrence pattern.
 *
 * @since 1.0.0
 */
class MonthlyPattern extends AbstractPattern {

	/**
	 * Pattern key.
	 *
	 * @var string
	 */
	protected string $key = 'monthly';

	/**
	 * Pattern label.
	 *
	 * @var string
	 */
	protected string $label = 'Monthly';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->label = __( 'Monthly', 'bkx-recurring-bookings' );
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
		$type     = $options['type'] ?? 'day_of_month';

		if ( 'day_of_week' === $type ) {
			return $this->get_next_by_day_of_week( $current, $options );
		}

		return $this->get_next_by_day_of_month( $current, $options );
	}

	/**
	 * Get next occurrence by day of month.
	 *
	 * @param DateTimeImmutable $current Current date.
	 * @param array             $options Pattern options.
	 * @return DateTimeImmutable|null Next occurrence.
	 */
	protected function get_next_by_day_of_month( DateTimeImmutable $current, array $options ): ?DateTimeImmutable {
		$interval    = isset( $options['interval'] ) ? max( 1, (int) $options['interval'] ) : 1;
		$target_day  = isset( $options['day_of_month'] ) ? (int) $options['day_of_month'] : (int) $current->format( 'j' );
		$current_day = (int) $current->format( 'j' );

		// Start with next month.
		$next = $current->modify( 'first day of this month' )->add( new DateInterval( "P{$interval}M" ) );

		// Get last day of target month.
		$last_day = (int) $next->format( 't' );

		// Adjust day if it exceeds month length.
		$actual_day = min( $target_day, $last_day );

		// Set the correct day.
		$next = $next->setDate(
			(int) $next->format( 'Y' ),
			(int) $next->format( 'n' ),
			$actual_day
		);

		return $next;
	}

	/**
	 * Get next occurrence by day of week.
	 *
	 * e.g., "2nd Tuesday of month".
	 *
	 * @param DateTimeImmutable $current Current date.
	 * @param array             $options Pattern options.
	 * @return DateTimeImmutable|null Next occurrence.
	 */
	protected function get_next_by_day_of_week( DateTimeImmutable $current, array $options ): ?DateTimeImmutable {
		$interval   = isset( $options['interval'] ) ? max( 1, (int) $options['interval'] ) : 1;
		$week_num   = isset( $options['week_number'] ) ? (int) $options['week_number'] : 1;
		$day_of_week = isset( $options['day_of_week'] ) ? (int) $options['day_of_week'] : (int) $current->format( 'w' );

		$day_names = array(
			0 => 'sunday',
			1 => 'monday',
			2 => 'tuesday',
			3 => 'wednesday',
			4 => 'thursday',
			5 => 'friday',
			6 => 'saturday',
		);

		$day_name = $day_names[ $day_of_week ];

		// Go to next interval month.
		$next = $current->modify( 'first day of this month' )->add( new DateInterval( "P{$interval}M" ) );

		// Handle "last" week (-1).
		if ( -1 === $week_num ) {
			$modifier = "last {$day_name} of this month";
		} else {
			$ordinals = array( 1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 5 => 'fifth' );
			$ordinal  = $ordinals[ $week_num ] ?? 'first';
			$modifier = "{$ordinal} {$day_name} of this month";
		}

		$next = $next->modify( $modifier );

		// Preserve time from current.
		$next = $next->setTime(
			(int) $current->format( 'H' ),
			(int) $current->format( 'i' ),
			(int) $current->format( 's' )
		);

		return $next;
	}

	/**
	 * Validate pattern options.
	 *
	 * @param array $options Pattern options.
	 * @return bool True if valid.
	 */
	public function validate_options( array $options ): bool {
		$type = $options['type'] ?? 'day_of_month';

		if ( 'day_of_month' === $type ) {
			$day = $options['day_of_month'] ?? 1;
			return $day >= 1 && $day <= 31;
		}

		if ( 'day_of_week' === $type ) {
			$week = $options['week_number'] ?? 1;
			$day  = $options['day_of_week'] ?? 0;

			return ( $week >= -1 && $week <= 5 && $week !== 0 ) && ( $day >= 0 && $day <= 6 );
		}

		return false;
	}

	/**
	 * Get interval between occurrences.
	 *
	 * @param array $options Pattern options.
	 * @return DateInterval
	 */
	protected function get_interval( array $options = array() ): DateInterval {
		$months = isset( $options['interval'] ) ? max( 1, (int) $options['interval'] ) : 1;
		return new DateInterval( "P{$months}M" );
	}

	/**
	 * Get human-readable description.
	 *
	 * @param array $options Pattern options.
	 * @return string Description.
	 */
	public function get_description( array $options = array() ): string {
		$interval = isset( $options['interval'] ) ? (int) $options['interval'] : 1;
		$type     = $options['type'] ?? 'day_of_month';

		if ( 1 === $interval ) {
			$text = __( 'Every month', 'bkx-recurring-bookings' );
		} else {
			$text = sprintf(
				/* translators: %d: number of months */
				__( 'Every %d months', 'bkx-recurring-bookings' ),
				$interval
			);
		}

		if ( 'day_of_month' === $type && isset( $options['day_of_month'] ) ) {
			$text .= ' ' . sprintf(
				/* translators: %s: day of month (1st, 2nd, etc.) */
				__( 'on the %s', 'bkx-recurring-bookings' ),
				$this->get_ordinal( (int) $options['day_of_month'] )
			);
		} elseif ( 'day_of_week' === $type ) {
			$week_num    = $options['week_number'] ?? 1;
			$day_of_week = $options['day_of_week'] ?? 0;

			$week_labels = array(
				-1 => __( 'last', 'bkx-recurring-bookings' ),
				1  => __( 'first', 'bkx-recurring-bookings' ),
				2  => __( 'second', 'bkx-recurring-bookings' ),
				3  => __( 'third', 'bkx-recurring-bookings' ),
				4  => __( 'fourth', 'bkx-recurring-bookings' ),
			);

			$week_label = $week_labels[ $week_num ] ?? 'first';

			$text .= ' ' . sprintf(
				/* translators: 1: week position (first, second, etc.), 2: day name */
				__( 'on the %1$s %2$s', 'bkx-recurring-bookings' ),
				$week_label,
				$this->format_days( array( $day_of_week ) )
			);
		}

		return $text;
	}
}

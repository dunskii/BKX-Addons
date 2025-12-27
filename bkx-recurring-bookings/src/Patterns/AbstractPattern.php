<?php
/**
 * Abstract Pattern
 *
 * @package BookingX\RecurringBookings\Patterns
 * @since   1.0.0
 */

namespace BookingX\RecurringBookings\Patterns;

use DateTimeImmutable;
use DateInterval;

/**
 * Abstract base class for recurrence patterns.
 *
 * @since 1.0.0
 */
abstract class AbstractPattern implements PatternInterface {

	/**
	 * Pattern key.
	 *
	 * @var string
	 */
	protected string $key = '';

	/**
	 * Pattern label.
	 *
	 * @var string
	 */
	protected string $label = '';

	/**
	 * Get pattern key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return $this->key;
	}

	/**
	 * Get pattern label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
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
		$occurrences = array( $start );
		$current     = $start;
		$max_iterations = $count * 10; // Safety limit.
		$iteration   = 0;

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
	 * Validate pattern options.
	 *
	 * @param array $options Pattern options.
	 * @return bool True if valid.
	 */
	public function validate_options( array $options ): bool {
		return true;
	}

	/**
	 * Get interval between occurrences.
	 *
	 * @param array $options Pattern options.
	 * @return DateInterval
	 */
	abstract protected function get_interval( array $options = array() ): DateInterval;

	/**
	 * Format day names.
	 *
	 * @param array $days Array of day numbers (0-6).
	 * @return string Formatted day names.
	 */
	protected function format_days( array $days ): string {
		$day_names = array(
			0 => __( 'Sunday', 'bkx-recurring-bookings' ),
			1 => __( 'Monday', 'bkx-recurring-bookings' ),
			2 => __( 'Tuesday', 'bkx-recurring-bookings' ),
			3 => __( 'Wednesday', 'bkx-recurring-bookings' ),
			4 => __( 'Thursday', 'bkx-recurring-bookings' ),
			5 => __( 'Friday', 'bkx-recurring-bookings' ),
			6 => __( 'Saturday', 'bkx-recurring-bookings' ),
		);

		$names = array();
		foreach ( $days as $day ) {
			if ( isset( $day_names[ $day ] ) ) {
				$names[] = $day_names[ $day ];
			}
		}

		if ( count( $names ) === 1 ) {
			return $names[0];
		}

		$last = array_pop( $names );
		return implode( ', ', $names ) . ' ' . __( 'and', 'bkx-recurring-bookings' ) . ' ' . $last;
	}

	/**
	 * Get ordinal suffix.
	 *
	 * @param int $number Number.
	 * @return string Ordinal suffix.
	 */
	protected function get_ordinal( int $number ): string {
		$suffixes = array(
			1  => __( '1st', 'bkx-recurring-bookings' ),
			2  => __( '2nd', 'bkx-recurring-bookings' ),
			3  => __( '3rd', 'bkx-recurring-bookings' ),
			21 => __( '21st', 'bkx-recurring-bookings' ),
			22 => __( '22nd', 'bkx-recurring-bookings' ),
			23 => __( '23rd', 'bkx-recurring-bookings' ),
			31 => __( '31st', 'bkx-recurring-bookings' ),
		);

		if ( isset( $suffixes[ $number ] ) ) {
			return $suffixes[ $number ];
		}

		return $number . __( 'th', 'bkx-recurring-bookings' );
	}
}

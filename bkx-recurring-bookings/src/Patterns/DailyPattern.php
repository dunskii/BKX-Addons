<?php
/**
 * Daily Pattern
 *
 * @package BookingX\RecurringBookings\Patterns
 * @since   1.0.0
 */

namespace BookingX\RecurringBookings\Patterns;

use DateTimeImmutable;
use DateInterval;

/**
 * Daily recurrence pattern.
 *
 * @since 1.0.0
 */
class DailyPattern extends AbstractPattern {

	/**
	 * Pattern key.
	 *
	 * @var string
	 */
	protected string $key = 'daily';

	/**
	 * Pattern label.
	 *
	 * @var string
	 */
	protected string $label = 'Daily';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->label = __( 'Daily', 'bkx-recurring-bookings' );
	}

	/**
	 * Calculate next occurrence.
	 *
	 * @param DateTimeImmutable $current Current date.
	 * @param array             $options Pattern options.
	 * @return DateTimeImmutable|null Next occurrence or null if none.
	 */
	public function get_next( DateTimeImmutable $current, array $options = array() ): ?DateTimeImmutable {
		$interval = $this->get_interval( $options );
		$next     = $current->add( $interval );

		// Skip weekends if configured.
		if ( ! empty( $options['skip_weekends'] ) ) {
			$day_of_week = (int) $next->format( 'w' );

			// Saturday = 6, Sunday = 0.
			if ( 6 === $day_of_week ) {
				$next = $next->add( new DateInterval( 'P2D' ) );
			} elseif ( 0 === $day_of_week ) {
				$next = $next->add( new DateInterval( 'P1D' ) );
			}
		}

		return $next;
	}

	/**
	 * Get interval between occurrences.
	 *
	 * @param array $options Pattern options.
	 * @return DateInterval
	 */
	protected function get_interval( array $options = array() ): DateInterval {
		$days = isset( $options['interval'] ) ? max( 1, (int) $options['interval'] ) : 1;
		return new DateInterval( "P{$days}D" );
	}

	/**
	 * Get human-readable description.
	 *
	 * @param array $options Pattern options.
	 * @return string Description.
	 */
	public function get_description( array $options = array() ): string {
		$interval = isset( $options['interval'] ) ? (int) $options['interval'] : 1;

		if ( 1 === $interval ) {
			$text = __( 'Every day', 'bkx-recurring-bookings' );
		} else {
			$text = sprintf(
				/* translators: %d: number of days */
				__( 'Every %d days', 'bkx-recurring-bookings' ),
				$interval
			);
		}

		if ( ! empty( $options['skip_weekends'] ) ) {
			$text .= ' ' . __( '(weekdays only)', 'bkx-recurring-bookings' );
		}

		return $text;
	}
}

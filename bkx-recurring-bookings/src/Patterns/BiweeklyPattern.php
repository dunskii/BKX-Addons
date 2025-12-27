<?php
/**
 * Biweekly Pattern
 *
 * @package BookingX\RecurringBookings\Patterns
 * @since   1.0.0
 */

namespace BookingX\RecurringBookings\Patterns;

use DateTimeImmutable;
use DateInterval;

/**
 * Biweekly (every 2 weeks) recurrence pattern.
 *
 * @since 1.0.0
 */
class BiweeklyPattern extends WeeklyPattern {

	/**
	 * Pattern key.
	 *
	 * @var string
	 */
	protected string $key = 'biweekly';

	/**
	 * Pattern label.
	 *
	 * @var string
	 */
	protected string $label = 'Every 2 weeks';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->label = __( 'Every 2 weeks', 'bkx-recurring-bookings' );
	}

	/**
	 * Calculate next occurrence.
	 *
	 * @param DateTimeImmutable $current Current date.
	 * @param array             $options Pattern options.
	 * @return DateTimeImmutable|null Next occurrence or null if none.
	 */
	public function get_next( DateTimeImmutable $current, array $options = array() ): ?DateTimeImmutable {
		// Force interval to 2 weeks.
		$options['interval'] = 2;
		return parent::get_next( $current, $options );
	}

	/**
	 * Get interval between occurrences.
	 *
	 * @param array $options Pattern options.
	 * @return DateInterval
	 */
	protected function get_interval( array $options = array() ): DateInterval {
		return new DateInterval( 'P2W' );
	}

	/**
	 * Get human-readable description.
	 *
	 * @param array $options Pattern options.
	 * @return string Description.
	 */
	public function get_description( array $options = array() ): string {
		$days = isset( $options['days'] ) ? (array) $options['days'] : array();
		$text = __( 'Every 2 weeks', 'bkx-recurring-bookings' );

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

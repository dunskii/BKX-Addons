<?php
/**
 * Custom Pattern
 *
 * @package BookingX\RecurringBookings\Patterns
 * @since   1.0.0
 */

namespace BookingX\RecurringBookings\Patterns;

use DateTimeImmutable;
use DateInterval;

/**
 * Custom recurrence pattern with flexible options.
 *
 * @since 1.0.0
 */
class CustomPattern extends AbstractPattern {

	/**
	 * Pattern key.
	 *
	 * @var string
	 */
	protected string $key = 'custom';

	/**
	 * Pattern label.
	 *
	 * @var string
	 */
	protected string $label = 'Custom';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->label = __( 'Custom', 'bkx-recurring-bookings' );
	}

	/**
	 * Calculate next occurrence.
	 *
	 * @param DateTimeImmutable $current Current date.
	 * @param array             $options Pattern options.
	 * @return DateTimeImmutable|null Next occurrence or null if none.
	 */
	public function get_next( DateTimeImmutable $current, array $options = array() ): ?DateTimeImmutable {
		$unit     = $options['unit'] ?? 'days';
		$interval = isset( $options['interval'] ) ? max( 1, (int) $options['interval'] ) : 1;

		$interval_spec = $this->get_interval_spec( $unit, $interval );
		$next          = $current->add( new DateInterval( $interval_spec ) );

		// Apply day filter if specified.
		if ( ! empty( $options['days'] ) && in_array( $unit, array( 'days', 'weeks' ), true ) ) {
			$days        = (array) $options['days'];
			$max_attempts = 365;
			$attempt     = 0;

			while ( ! in_array( (int) $next->format( 'w' ), $days, true ) && $attempt < $max_attempts ) {
				$next = $next->add( new DateInterval( 'P1D' ) );
				++$attempt;
			}
		}

		return $next;
	}

	/**
	 * Get interval specification.
	 *
	 * @param string $unit Unit (days, weeks, months, years).
	 * @param int    $interval Interval number.
	 * @return string DateInterval specification.
	 */
	protected function get_interval_spec( string $unit, int $interval ): string {
		switch ( $unit ) {
			case 'days':
				return "P{$interval}D";

			case 'weeks':
				return "P{$interval}W";

			case 'months':
				return "P{$interval}M";

			case 'years':
				return "P{$interval}Y";

			default:
				return "P{$interval}D";
		}
	}

	/**
	 * Validate pattern options.
	 *
	 * @param array $options Pattern options.
	 * @return bool True if valid.
	 */
	public function validate_options( array $options ): bool {
		$valid_units = array( 'days', 'weeks', 'months', 'years' );
		$unit        = $options['unit'] ?? 'days';

		if ( ! in_array( $unit, $valid_units, true ) ) {
			return false;
		}

		$interval = $options['interval'] ?? 1;
		if ( $interval < 1 || $interval > 365 ) {
			return false;
		}

		if ( ! empty( $options['days'] ) ) {
			$days = (array) $options['days'];
			foreach ( $days as $day ) {
				if ( $day < 0 || $day > 6 ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Get interval between occurrences.
	 *
	 * @param array $options Pattern options.
	 * @return DateInterval
	 */
	protected function get_interval( array $options = array() ): DateInterval {
		$unit     = $options['unit'] ?? 'days';
		$interval = isset( $options['interval'] ) ? max( 1, (int) $options['interval'] ) : 1;

		return new DateInterval( $this->get_interval_spec( $unit, $interval ) );
	}

	/**
	 * Get human-readable description.
	 *
	 * @param array $options Pattern options.
	 * @return string Description.
	 */
	public function get_description( array $options = array() ): string {
		$unit     = $options['unit'] ?? 'days';
		$interval = isset( $options['interval'] ) ? (int) $options['interval'] : 1;
		$days     = isset( $options['days'] ) ? (array) $options['days'] : array();

		$unit_labels = array(
			'days'   => array(
				/* translators: %d: number of days */
				'singular' => __( 'Every day', 'bkx-recurring-bookings' ),
				'plural'   => __( 'Every %d days', 'bkx-recurring-bookings' ),
			),
			'weeks'  => array(
				'singular' => __( 'Every week', 'bkx-recurring-bookings' ),
				'plural'   => __( 'Every %d weeks', 'bkx-recurring-bookings' ),
			),
			'months' => array(
				'singular' => __( 'Every month', 'bkx-recurring-bookings' ),
				'plural'   => __( 'Every %d months', 'bkx-recurring-bookings' ),
			),
			'years'  => array(
				'singular' => __( 'Every year', 'bkx-recurring-bookings' ),
				'plural'   => __( 'Every %d years', 'bkx-recurring-bookings' ),
			),
		);

		$labels = $unit_labels[ $unit ] ?? $unit_labels['days'];

		if ( 1 === $interval ) {
			$text = $labels['singular'];
		} else {
			$text = sprintf( $labels['plural'], $interval );
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

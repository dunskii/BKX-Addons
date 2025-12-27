<?php
/**
 * Duration Calculator Service
 *
 * Calculates total duration for multiple services.
 *
 * @package BookingX\MultipleServices
 * @since   1.0.0
 */

namespace BookingX\MultipleServices\Services;

use BookingX\MultipleServices\MultipleServicesAddon;

/**
 * Duration Calculator class.
 *
 * @since 1.0.0
 */
class DurationCalculator {

	/**
	 * Addon instance.
	 *
	 * @var MultipleServicesAddon
	 */
	protected MultipleServicesAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param MultipleServicesAddon $addon Addon instance.
	 */
	public function __construct( MultipleServicesAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Calculate total duration for multiple services.
	 *
	 * @since 1.0.0
	 * @param array  $service_ids Service IDs.
	 * @param string $mode        Calculation mode (sequential, parallel, longest).
	 * @return int Total duration in minutes.
	 */
	public function calculate_total_duration( array $service_ids, string $mode = 'sequential' ): int {
		$durations = array();

		foreach ( $service_ids as $service_id ) {
			$duration = get_post_meta( $service_id, 'duration', true );
			$durations[] = intval( $duration );
		}

		switch ( $mode ) {
			case 'sequential':
				// Add all durations together
				return array_sum( $durations );

			case 'parallel':
				// Services run in parallel, use longest duration
				return ! empty( $durations ) ? max( $durations ) : 0;

			case 'longest':
				// Use longest service duration
				return ! empty( $durations ) ? max( $durations ) : 0;

			default:
				return array_sum( $durations );
		}
	}

	/**
	 * Get individual service durations.
	 *
	 * @since 1.0.0
	 * @param array $service_ids Service IDs.
	 * @return array Array of durations indexed by service ID.
	 */
	public function get_service_durations( array $service_ids ): array {
		$durations = array();

		foreach ( $service_ids as $service_id ) {
			$duration = get_post_meta( $service_id, 'duration', true );
			$durations[ $service_id ] = intval( $duration );
		}

		return $durations;
	}

	/**
	 * Format duration for display.
	 *
	 * @since 1.0.0
	 * @param int $minutes Duration in minutes.
	 * @return string Formatted duration.
	 */
	public function format_duration( int $minutes ): string {
		if ( $minutes < 60 ) {
			return sprintf(
				/* translators: %d: number of minutes */
				_n( '%d minute', '%d minutes', $minutes, 'bkx-multiple-services' ),
				$minutes
			);
		}

		$hours = floor( $minutes / 60 );
		$remaining_minutes = $minutes % 60;

		if ( 0 === $remaining_minutes ) {
			return sprintf(
				/* translators: %d: number of hours */
				_n( '%d hour', '%d hours', $hours, 'bkx-multiple-services' ),
				$hours
			);
		}

		return sprintf(
			/* translators: 1: number of hours, 2: number of minutes */
			__( '%1$d hours %2$d minutes', 'bkx-multiple-services' ),
			$hours,
			$remaining_minutes
		);
	}
}

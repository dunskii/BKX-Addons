<?php
/**
 * Pattern Interface
 *
 * @package BookingX\RecurringBookings\Patterns
 * @since   1.0.0
 */

namespace BookingX\RecurringBookings\Patterns;

use DateTimeImmutable;

/**
 * Interface for recurrence patterns.
 *
 * @since 1.0.0
 */
interface PatternInterface {

	/**
	 * Get pattern key.
	 *
	 * @return string
	 */
	public function get_key(): string;

	/**
	 * Get pattern label.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Calculate next occurrence.
	 *
	 * @param DateTimeImmutable $current Current date.
	 * @param array             $options Pattern options.
	 * @return DateTimeImmutable|null Next occurrence or null if none.
	 */
	public function get_next( DateTimeImmutable $current, array $options = array() ): ?DateTimeImmutable;

	/**
	 * Generate occurrences.
	 *
	 * @param DateTimeImmutable $start Start date.
	 * @param int               $count Number of occurrences.
	 * @param array             $options Pattern options.
	 * @return array Array of DateTimeImmutable objects.
	 */
	public function generate( DateTimeImmutable $start, int $count, array $options = array() ): array;

	/**
	 * Validate pattern options.
	 *
	 * @param array $options Pattern options.
	 * @return bool True if valid.
	 */
	public function validate_options( array $options ): bool;

	/**
	 * Get human-readable description.
	 *
	 * @param array $options Pattern options.
	 * @return string Description.
	 */
	public function get_description( array $options = array() ): string;
}

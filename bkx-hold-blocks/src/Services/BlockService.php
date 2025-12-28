<?php
/**
 * Block Service
 *
 * @package BookingX\HoldBlocks\Services
 * @since   1.0.0
 */

namespace BookingX\HoldBlocks\Services;

/**
 * Service for managing hold blocks.
 *
 * @since 1.0.0
 */
class BlockService {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'bkx_hold_blocks';
	}

	/**
	 * Add a block.
	 *
	 * @since 1.0.0
	 * @param array $data Block data.
	 * @return int|false Block ID or false on failure.
	 */
	public function add_block( array $data ) {
		global $wpdb;

		$insert_data = array(
			'seat_id'    => $data['seat_id'] ?: null,
			'start_date' => sanitize_text_field( $data['start_date'] ),
			'end_date'   => ! empty( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : null,
			'start_time' => ! empty( $data['start_time'] ) && ! $data['all_day'] ? sanitize_text_field( $data['start_time'] ) : null,
			'end_time'   => ! empty( $data['end_time'] ) && ! $data['all_day'] ? sanitize_text_field( $data['end_time'] ) : null,
			'all_day'    => $data['all_day'] ? 1 : 0,
			'block_type' => sanitize_text_field( $data['block_type'] ?? 'hold' ),
			'reason'     => ! empty( $data['reason'] ) ? sanitize_textarea_field( $data['reason'] ) : null,
			'recurring'  => ! empty( $data['recurring'] ) ? sanitize_text_field( $data['recurring'] ) : null,
			'created_by' => get_current_user_id() ?: null,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $this->table, $insert_data );

		if ( ! $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update a block.
	 *
	 * @since 1.0.0
	 * @param int   $block_id Block ID.
	 * @param array $data     Block data.
	 * @return bool
	 */
	public function update_block( int $block_id, array $data ): bool {
		global $wpdb;

		$update_data = array();
		$format      = array();

		if ( isset( $data['seat_id'] ) ) {
			$update_data['seat_id'] = $data['seat_id'] ?: null;
			$format[]               = '%d';
		}

		if ( isset( $data['start_date'] ) ) {
			$update_data['start_date'] = sanitize_text_field( $data['start_date'] );
			$format[]                  = '%s';
		}

		if ( isset( $data['end_date'] ) ) {
			$update_data['end_date'] = $data['end_date'] ?: null;
			$format[]                = '%s';
		}

		if ( isset( $data['all_day'] ) ) {
			$update_data['all_day'] = $data['all_day'] ? 1 : 0;
			$format[]               = '%d';
		}

		if ( isset( $data['start_time'] ) ) {
			$update_data['start_time'] = $data['start_time'] ?: null;
			$format[]                  = '%s';
		}

		if ( isset( $data['end_time'] ) ) {
			$update_data['end_time'] = $data['end_time'] ?: null;
			$format[]                = '%s';
		}

		if ( isset( $data['block_type'] ) ) {
			$update_data['block_type'] = sanitize_text_field( $data['block_type'] );
			$format[]                  = '%s';
		}

		if ( isset( $data['reason'] ) ) {
			$update_data['reason'] = sanitize_textarea_field( $data['reason'] );
			$format[]              = '%s';
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $block_id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a block.
	 *
	 * @since 1.0.0
	 * @param int $block_id Block ID.
	 * @return bool
	 */
	public function delete_block( int $block_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete(
			$this->table,
			array( 'id' => $block_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get a block.
	 *
	 * @since 1.0.0
	 * @param int $block_id Block ID.
	 * @return array|null
	 */
	public function get_block( int $block_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$block = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$block_id
			),
			ARRAY_A
		);

		return $block ?: null;
	}

	/**
	 * Get blocks for a date range.
	 *
	 * @since 1.0.0
	 * @param int|null $seat_id    Seat post ID (null for all).
	 * @param string   $start_date Start date.
	 * @param string   $end_date   End date.
	 * @return array
	 */
	public function get_blocks( ?int $seat_id, string $start_date, string $end_date ): array {
		global $wpdb;

		$where = '1=1';
		$args  = array();

		if ( $seat_id ) {
			$where .= ' AND (seat_id = %d OR seat_id IS NULL)';
			$args[] = $seat_id;
		}

		if ( $start_date ) {
			$where .= ' AND (end_date >= %s OR (end_date IS NULL AND start_date >= %s))';
			$args[] = $start_date;
			$args[] = $start_date;
		}

		if ( $end_date ) {
			$where .= ' AND start_date <= %s';
			$args[] = $end_date;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE {$where} ORDER BY start_date, start_time",
				...$args
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Check if a time slot is blocked.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat post ID.
	 * @param string $date    Date (Y-m-d).
	 * @param string $time    Time (H:i).
	 * @return bool
	 */
	public function is_blocked( int $seat_id, string $date, string $time ): bool {
		$block = $this->get_active_block( $seat_id, $date, $time );

		return null !== $block;
	}

	/**
	 * Check if a date is fully blocked.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat post ID.
	 * @param string $date    Date (Y-m-d).
	 * @return bool
	 */
	public function is_date_fully_blocked( int $seat_id, string $date ): bool {
		global $wpdb;

		// Check for all-day blocks.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$block = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table}
				WHERE (seat_id = %d OR seat_id IS NULL)
				AND start_date <= %s
				AND (end_date >= %s OR end_date IS NULL)
				AND all_day = 1
				LIMIT 1",
				$seat_id,
				$date,
				$date
			)
		);

		return null !== $block;
	}

	/**
	 * Get active block for a time slot.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat post ID.
	 * @param string $date    Date (Y-m-d).
	 * @param string $time    Time (H:i).
	 * @return array|null
	 */
	public function get_active_block( int $seat_id, string $date, string $time ): ?array {
		global $wpdb;

		// Check all-day blocks first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$block = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE (seat_id = %d OR seat_id IS NULL)
				AND start_date <= %s
				AND (end_date >= %s OR end_date IS NULL)
				AND all_day = 1
				LIMIT 1",
				$seat_id,
				$date,
				$date
			),
			ARRAY_A
		);

		if ( $block ) {
			return $block;
		}

		// Check time-specific blocks.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$block = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE (seat_id = %d OR seat_id IS NULL)
				AND start_date <= %s
				AND (end_date >= %s OR end_date IS NULL)
				AND all_day = 0
				AND start_time <= %s
				AND end_time > %s
				LIMIT 1",
				$seat_id,
				$date,
				$date,
				$time,
				$time
			),
			ARRAY_A
		);

		if ( $block ) {
			return $block;
		}

		// Check recurring blocks.
		return $this->check_recurring_block( $seat_id, $date, $time );
	}

	/**
	 * Check for recurring blocks.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat post ID.
	 * @param string $date    Date (Y-m-d).
	 * @param string $time    Time (H:i).
	 * @return array|null
	 */
	private function check_recurring_block( int $seat_id, string $date, string $time ): ?array {
		global $wpdb;

		$day_of_week = (int) gmdate( 'w', strtotime( $date ) );
		$day_of_month = (int) gmdate( 'j', strtotime( $date ) );
		$month = (int) gmdate( 'n', strtotime( $date ) );

		// Get all recurring blocks.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$blocks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE (seat_id = %d OR seat_id IS NULL)
				AND recurring IS NOT NULL
				AND recurring != ''
				AND recurring != 'none'
				AND start_date <= %s
				AND (recurring_end_date IS NULL OR recurring_end_date >= %s)",
				$seat_id,
				$date,
				$date
			),
			ARRAY_A
		);

		foreach ( $blocks as $block ) {
			$matches = false;
			$block_date = strtotime( $block['start_date'] );

			switch ( $block['recurring'] ) {
				case 'daily':
					$matches = true;
					break;

				case 'weekly':
					$block_dow = (int) gmdate( 'w', $block_date );
					$matches   = ( $day_of_week === $block_dow );
					break;

				case 'monthly':
					$block_dom = (int) gmdate( 'j', $block_date );
					$matches   = ( $day_of_month === $block_dom );
					break;

				case 'yearly':
					$block_month = (int) gmdate( 'n', $block_date );
					$block_dom   = (int) gmdate( 'j', $block_date );
					$matches     = ( $month === $block_month && $day_of_month === $block_dom );
					break;
			}

			if ( $matches ) {
				// Check time.
				if ( $block['all_day'] ) {
					return $block;
				}

				if ( $block['start_time'] <= $time && $block['end_time'] > $time ) {
					return $block;
				}
			}
		}

		return null;
	}

	/**
	 * Cleanup expired blocks.
	 *
	 * @since 1.0.0
	 * @param int $days Number of days to keep.
	 * @return int Number of deleted blocks.
	 */
	public function cleanup_expired( int $days ): int {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table}
				WHERE recurring IS NULL OR recurring = '' OR recurring = 'none'
				AND (end_date < %s OR (end_date IS NULL AND start_date < %s))",
				$cutoff,
				$cutoff
			)
		);

		return $result ?: 0;
	}

	/**
	 * Get blocks by type.
	 *
	 * @since 1.0.0
	 * @param string $type Block type.
	 * @return array
	 */
	public function get_blocks_by_type( string $type ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE block_type = %s
				ORDER BY start_date DESC",
				$type
			),
			ARRAY_A
		) ?: array();
	}
}

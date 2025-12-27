<?php
/**
 * Favorites Service
 *
 * Handles customer favorites management.
 *
 * @package BookingX\UserProfilesAdvanced\Services
 * @since   1.0.0
 */

namespace BookingX\UserProfilesAdvanced\Services;

use BookingX\UserProfilesAdvanced\UserProfilesAdvancedAddon;

/**
 * Favorites service class.
 *
 * @since 1.0.0
 */
class FavoritesService {

	/**
	 * Addon instance.
	 *
	 * @var UserProfilesAdvancedAddon
	 */
	protected UserProfilesAdvancedAddon $addon;

	/**
	 * Favorites table.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Constructor.
	 *
	 * @param UserProfilesAdvancedAddon $addon Addon instance.
	 */
	public function __construct( UserProfilesAdvancedAddon $addon ) {
		global $wpdb;

		$this->addon = $addon;
		$this->table = $wpdb->prefix . 'bkx_favorites';
	}

	/**
	 * Get user favorites.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type Item type (service, staff, or empty for all).
	 * @return array Favorites.
	 */
	public function get_favorites( int $user_id, string $type = '' ): array {
		global $wpdb;

		if ( ! empty( $type ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$favorites = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE user_id = %d AND item_type = %s ORDER BY created_at DESC',
					$this->table,
					$user_id,
					$type
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$favorites = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE user_id = %d ORDER BY created_at DESC',
					$this->table,
					$user_id
				)
			);
		}

		// Enrich with item details.
		return array_map( array( $this, 'enrich_favorite' ), $favorites );
	}

	/**
	 * Check if item is favorited.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $item_id Item ID.
	 * @param string $type Item type.
	 * @return bool True if favorited.
	 */
	public function is_favorite( int $user_id, int $item_id, string $type = 'service' ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE user_id = %d AND item_id = %d AND item_type = %s',
				$this->table,
				$user_id,
				$item_id,
				$type
			)
		);

		return ! empty( $exists );
	}

	/**
	 * Add favorite.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $item_id Item ID.
	 * @param string $type Item type.
	 * @return bool True on success.
	 */
	public function add_favorite( int $user_id, int $item_id, string $type = 'service' ): bool {
		global $wpdb;

		// Check max favorites.
		$max_favorites = $this->addon->get_setting( 'max_favorites', 50 );
		$current_count = $this->get_favorites_count( $user_id );

		if ( $current_count >= $max_favorites ) {
			return false;
		}

		// Check if already exists.
		if ( $this->is_favorite( $user_id, $item_id, $type ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			array(
				'user_id'   => $user_id,
				'item_id'   => $item_id,
				'item_type' => $type,
			)
		);

		return false !== $result;
	}

	/**
	 * Remove favorite.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $item_id Item ID.
	 * @param string $type Item type.
	 * @return bool True on success.
	 */
	public function remove_favorite( int $user_id, int $item_id, string $type = 'service' ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->table,
			array(
				'user_id'   => $user_id,
				'item_id'   => $item_id,
				'item_type' => $type,
			)
		);

		return false !== $result;
	}

	/**
	 * Toggle favorite.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $item_id Item ID.
	 * @param string $type Item type.
	 * @return bool New state (true = favorited, false = removed).
	 */
	public function toggle_favorite( int $user_id, int $item_id, string $type = 'service' ): bool {
		if ( $this->is_favorite( $user_id, $item_id, $type ) ) {
			$this->remove_favorite( $user_id, $item_id, $type );
			return false;
		}

		$this->add_favorite( $user_id, $item_id, $type );
		return true;
	}

	/**
	 * Get favorites count.
	 *
	 * @param int $user_id User ID.
	 * @return int Count.
	 */
	public function get_favorites_count( int $user_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE user_id = %d',
				$this->table,
				$user_id
			)
		);
	}

	/**
	 * Delete all favorites for user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function delete_user_favorites( int $user_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->table, array( 'user_id' => $user_id ) );
	}

	/**
	 * Enrich favorite with item details.
	 *
	 * @param object $favorite Favorite record.
	 * @return object Enriched favorite.
	 */
	protected function enrich_favorite( object $favorite ): object {
		$post_type = 'service' === $favorite->item_type ? 'bkx_base' : 'bkx_seat';
		$item      = get_post( $favorite->item_id );

		if ( $item && $item->post_type === $post_type ) {
			$favorite->title     = $item->post_title;
			$favorite->excerpt   = $item->post_excerpt;
			$favorite->permalink = get_permalink( $item->ID );
			$favorite->thumbnail = get_the_post_thumbnail_url( $item->ID, 'thumbnail' );

			// Get price for services.
			if ( 'service' === $favorite->item_type ) {
				$favorite->price = get_post_meta( $item->ID, 'base_price', true );
			}
		} else {
			$favorite->title     = __( 'Item not found', 'bkx-user-profiles-advanced' );
			$favorite->excerpt   = '';
			$favorite->permalink = '';
			$favorite->thumbnail = '';
		}

		return $favorite;
	}

	/**
	 * Get popular favorites across all users.
	 *
	 * @param string $type Item type.
	 * @param int    $limit Limit.
	 * @return array Popular items.
	 */
	public function get_popular( string $type = 'service', int $limit = 10 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT item_id, COUNT(*) as count FROM %i WHERE item_type = %s GROUP BY item_id ORDER BY count DESC LIMIT %d',
				$this->table,
				$type,
				$limit
			)
		);
	}
}

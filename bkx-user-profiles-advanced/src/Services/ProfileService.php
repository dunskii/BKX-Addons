<?php
/**
 * Profile Service
 *
 * Handles customer profile management.
 *
 * @package BookingX\UserProfilesAdvanced\Services
 * @since   1.0.0
 */

namespace BookingX\UserProfilesAdvanced\Services;

use BookingX\UserProfilesAdvanced\UserProfilesAdvancedAddon;
use WP_Error;
use WP_User;

/**
 * Profile service class.
 *
 * @since 1.0.0
 */
class ProfileService {

	/**
	 * Addon instance.
	 *
	 * @var UserProfilesAdvancedAddon
	 */
	protected UserProfilesAdvancedAddon $addon;

	/**
	 * Profiles table.
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
		$this->table = $wpdb->prefix . 'bkx_customer_profiles';
	}

	/**
	 * Get profile by user ID.
	 *
	 * @param int $user_id User ID.
	 * @return object|null Profile or null.
	 */
	public function get_profile( int $user_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d',
				$this->table,
				$user_id
			)
		);
	}

	/**
	 * Create profile for user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $data Initial data.
	 * @return int|WP_Error Profile ID or error.
	 */
	public function create_profile( int $user_id, array $data = array() ) {
		global $wpdb;

		// Check if profile already exists.
		$existing = $this->get_profile( $user_id );
		if ( $existing ) {
			return $existing->id;
		}

		$insert = array(
			'user_id'                  => $user_id,
			'phone'                    => sanitize_text_field( $data['phone'] ?? '' ),
			'preferred_time'           => sanitize_text_field( $data['preferred_time'] ?? '' ),
			'communication_preference' => sanitize_text_field( $data['communication_preference'] ?? 'email' ),
			'notes'                    => sanitize_textarea_field( $data['notes'] ?? '' ),
			'email_optin'              => 1,
			'sms_optin'                => 0,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $this->table, $insert );

		if ( false === $result ) {
			return new WP_Error( 'create_failed', __( 'Failed to create profile.', 'bkx-user-profiles-advanced' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update profile.
	 *
	 * @param int   $user_id User ID.
	 * @param array $data Profile data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_profile( int $user_id, array $data ) {
		global $wpdb;

		$profile = $this->get_profile( $user_id );

		if ( ! $profile ) {
			return $this->create_profile( $user_id, $data );
		}

		$allowed = array(
			'phone',
			'preferred_time',
			'communication_preference',
			'notes',
			'sms_optin',
			'email_optin',
		);

		$update = array();

		foreach ( $allowed as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( in_array( $field, array( 'sms_optin', 'email_optin' ), true ) ) {
					$update[ $field ] = absint( $data[ $field ] );
				} elseif ( 'notes' === $field ) {
					$update[ $field ] = sanitize_textarea_field( $data[ $field ] );
				} else {
					$update[ $field ] = sanitize_text_field( $data[ $field ] );
				}
			}
		}

		if ( empty( $update ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( $this->table, $update, array( 'user_id' => $user_id ) );

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update profile.', 'bkx-user-profiles-advanced' ) );
		}

		return true;
	}

	/**
	 * Delete profile.
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success.
	 */
	public function delete_profile( int $user_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $this->table, array( 'user_id' => $user_id ) );

		return true;
	}

	/**
	 * Update last booking info.
	 *
	 * @param int $user_id User ID.
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function update_last_booking( int $user_id, int $booking_id ): void {
		global $wpdb;

		$profile = $this->get_profile( $user_id );

		if ( ! $profile ) {
			$this->create_profile( $user_id );
		}

		$total = get_post_meta( $booking_id, 'total_price', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET
					last_booking_id = %d,
					last_booking_date = NOW(),
					total_bookings = total_bookings + 1,
					total_spent = total_spent + %f
				WHERE user_id = %d',
				$this->table,
				$booking_id,
				floatval( $total ),
				$user_id
			)
		);
	}

	/**
	 * Increment cancellation count.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function increment_cancellation_count( int $user_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET cancellation_count = cancellation_count + 1 WHERE user_id = %d',
				$this->table,
				$user_id
			)
		);
	}

	/**
	 * Maybe create user from booking data.
	 *
	 * @param array $data Booking data.
	 * @return int|false User ID or false.
	 */
	public function maybe_create_user( array $data ) {
		$email = sanitize_email( $data['customer_email'] ?? '' );

		if ( empty( $email ) ) {
			return false;
		}

		// Check if user exists.
		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user ) {
			return $existing_user->ID;
		}

		// Create new user.
		$username = $this->generate_username( $email, $data );
		$password = wp_generate_password( 12, true );

		$user_id = wp_insert_user(
			array(
				'user_login' => $username,
				'user_email' => $email,
				'user_pass'  => $password,
				'first_name' => sanitize_text_field( $data['customer_first_name'] ?? '' ),
				'last_name'  => sanitize_text_field( $data['customer_last_name'] ?? '' ),
				'role'       => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return false;
		}

		// Create profile.
		$this->create_profile(
			$user_id,
			array(
				'phone' => $data['customer_phone'] ?? '',
			)
		);

		// Send welcome email with password.
		wp_new_user_notification( $user_id, null, 'user' );

		return $user_id;
	}

	/**
	 * Generate unique username.
	 *
	 * @param string $email Email address.
	 * @param array  $data Additional data.
	 * @return string Username.
	 */
	protected function generate_username( string $email, array $data ): string {
		// Try email username part.
		$username = strtok( $email, '@' );
		$username = sanitize_user( $username, true );

		if ( ! username_exists( $username ) ) {
			return $username;
		}

		// Try first name + last name.
		if ( ! empty( $data['customer_first_name'] ) && ! empty( $data['customer_last_name'] ) ) {
			$username = sanitize_user(
				strtolower( $data['customer_first_name'] . $data['customer_last_name'] ),
				true
			);

			if ( ! username_exists( $username ) ) {
				return $username;
			}
		}

		// Append random number.
		$base = strtok( $email, '@' );
		$i    = 1;

		while ( username_exists( $base . $i ) ) {
			++$i;
		}

		return sanitize_user( $base . $i, true );
	}

	/**
	 * Get booking history for user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args Query arguments.
	 * @return array Bookings.
	 */
	public function get_booking_history( int $user_id, array $args = array() ): array {
		$defaults = array(
			'posts_per_page' => 10,
			'paged'          => 1,
			'status'         => 'any',
		);

		$args = wp_parse_args( $args, $defaults );

		$query_args = array(
			'post_type'      => 'bkx_booking',
			'posts_per_page' => $args['posts_per_page'],
			'paged'          => $args['paged'],
			'post_status'    => $args['status'],
			'meta_query'     => array(
				array(
					'key'   => 'user_id',
					'value' => $user_id,
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new \WP_Query( $query_args );

		return array(
			'bookings'    => $query->posts,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
		);
	}

	/**
	 * Get profile statistics.
	 *
	 * @param int $user_id User ID.
	 * @return array Stats.
	 */
	public function get_stats( int $user_id ): array {
		$profile = $this->get_profile( $user_id );

		if ( ! $profile ) {
			return array(
				'total_bookings'     => 0,
				'total_spent'        => 0,
				'cancellation_count' => 0,
				'member_since'       => '',
			);
		}

		$user = get_user_by( 'id', $user_id );

		return array(
			'total_bookings'     => (int) $profile->total_bookings,
			'total_spent'        => (float) $profile->total_spent,
			'cancellation_count' => (int) $profile->cancellation_count,
			'member_since'       => $user ? $user->user_registered : '',
		);
	}

	/**
	 * Generate referral code for user.
	 *
	 * @param int $user_id User ID.
	 * @return string Referral code.
	 */
	public function get_referral_code( int $user_id ): string {
		$code = get_user_meta( $user_id, 'bkx_referral_code', true );

		if ( empty( $code ) ) {
			$code = strtoupper( wp_generate_password( 8, false ) );
			update_user_meta( $user_id, 'bkx_referral_code', $code );
		}

		return $code;
	}
}

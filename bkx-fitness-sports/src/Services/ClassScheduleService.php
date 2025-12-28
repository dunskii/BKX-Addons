<?php
/**
 * Class Schedule Service
 *
 * Manages fitness class scheduling, bookings, and waitlists.
 *
 * @package BookingX\FitnessSports\Services
 * @since   1.0.0
 */

namespace BookingX\FitnessSports\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ClassScheduleService
 *
 * @since 1.0.0
 */
class ClassScheduleService {

	/**
	 * Table names.
	 *
	 * @var array
	 */
	private array $tables;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->tables = array(
			'schedules' => $wpdb->prefix . 'bkx_class_schedules',
			'bookings'  => $wpdb->prefix . 'bkx_class_bookings',
			'waitlist'  => $wpdb->prefix . 'bkx_class_waitlist',
		);
	}

	/**
	 * Get upcoming classes.
	 *
	 * @since 1.0.0
	 * @param array $filters Filters.
	 * @return array
	 */
	public function get_upcoming_classes( array $filters = array() ): array {
		global $wpdb;

		$where = array( 's.start_datetime > %s' );
		$args  = array( current_time( 'mysql' ) );

		if ( ! empty( $filters['category'] ) ) {
			$where[] = 'c.ID IN (SELECT object_id FROM ' . $wpdb->term_relationships . ' WHERE term_taxonomy_id = %d)';
			$args[]  = absint( $filters['category'] );
		}

		if ( ! empty( $filters['trainer_id'] ) ) {
			$where[] = 's.trainer_id = %d';
			$args[]  = absint( $filters['trainer_id'] );
		}

		if ( ! empty( $filters['difficulty'] ) ) {
			$where[] = 'c.ID IN (SELECT object_id FROM ' . $wpdb->term_relationships . ' tr
				INNER JOIN ' . $wpdb->term_taxonomy . ' tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN ' . $wpdb->terms . ' t ON tt.term_id = t.term_id
				WHERE tt.taxonomy = %s AND t.slug = %s)';
			$args[]  = 'bkx_difficulty_level';
			$args[]  = sanitize_text_field( $filters['difficulty'] );
		}

		$limit  = absint( $filters['limit'] ?? 20 );
		$offset = absint( $filters['offset'] ?? 0 );

		$where_clause = implode( ' AND ', $where );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, c.post_title as class_name, c.post_content as description,
					t.post_title as trainer_name,
					(SELECT COUNT(*) FROM {$this->tables['bookings']} WHERE schedule_id = s.id AND status = 'confirmed') as booked_count
				FROM {$this->tables['schedules']} s
				INNER JOIN {$wpdb->posts} c ON s.class_id = c.ID
				LEFT JOIN {$wpdb->posts} t ON s.trainer_id = t.ID
				WHERE {$where_clause}
				ORDER BY s.start_datetime ASC
				LIMIT %d OFFSET %d",
				array_merge( $args, array( $limit, $offset ) )
			),
			ARRAY_A
		);

		return array_map( array( $this, 'format_schedule' ), $results ?: array() );
	}

	/**
	 * Get class schedule for a specific date range.
	 *
	 * @since 1.0.0
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array
	 */
	public function get_schedule_for_range( string $start_date, string $end_date ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, c.post_title as class_name,
					t.post_title as trainer_name,
					(SELECT COUNT(*) FROM {$this->tables['bookings']} WHERE schedule_id = s.id AND status = 'confirmed') as booked_count
				FROM {$this->tables['schedules']} s
				INNER JOIN {$wpdb->posts} c ON s.class_id = c.ID
				LEFT JOIN {$wpdb->posts} t ON s.trainer_id = t.ID
				WHERE DATE(s.start_datetime) BETWEEN %s AND %s
				ORDER BY s.start_datetime ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Group by date.
		$grouped = array();
		foreach ( $results ?: array() as $row ) {
			$date = date( 'Y-m-d', strtotime( $row['start_datetime'] ) );
			if ( ! isset( $grouped[ $date ] ) ) {
				$grouped[ $date ] = array();
			}
			$grouped[ $date ][] = $this->format_schedule( $row );
		}

		return $grouped;
	}

	/**
	 * Book a class.
	 *
	 * @since 1.0.0
	 * @param int $user_id     User ID.
	 * @param int $class_id    Class ID.
	 * @param int $schedule_id Schedule ID.
	 * @return int|\WP_Error Booking ID or error.
	 */
	public function book_class( int $user_id, int $class_id, int $schedule_id ) {
		global $wpdb;

		// Check if already booked.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->tables['bookings']}
				WHERE user_id = %d AND schedule_id = %d AND status != 'cancelled'",
				$user_id,
				$schedule_id
			)
		);

		if ( $existing ) {
			return new \WP_Error( 'already_booked', __( 'You have already booked this class.', 'bkx-fitness-sports' ) );
		}

		// Get schedule info.
		$schedule = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['schedules']} WHERE id = %d",
				$schedule_id
			),
			ARRAY_A
		);

		if ( ! $schedule ) {
			return new \WP_Error( 'invalid_schedule', __( 'Invalid class schedule.', 'bkx-fitness-sports' ) );
		}

		// Check capacity.
		$booked_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->tables['bookings']}
				WHERE schedule_id = %d AND status = 'confirmed'",
				$schedule_id
			)
		);

		if ( $booked_count >= $schedule['max_capacity'] ) {
			return new \WP_Error( 'class_full', __( 'This class is full. Would you like to join the waitlist?', 'bkx-fitness-sports' ) );
		}

		// Check membership if required.
		$membership_required = get_post_meta( $class_id, '_bkx_membership_required', true );
		if ( $membership_required ) {
			$membership_service = new MembershipService();
			if ( ! $membership_service->has_active_membership( $user_id ) ) {
				return new \WP_Error( 'membership_required', __( 'An active membership is required to book this class.', 'bkx-fitness-sports' ) );
			}
		}

		// Insert booking.
		$result = $wpdb->insert(
			$this->tables['bookings'],
			array(
				'user_id'      => $user_id,
				'class_id'     => $class_id,
				'schedule_id'  => $schedule_id,
				'status'       => 'confirmed',
				'booked_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'booking_failed', __( 'Failed to book class. Please try again.', 'bkx-fitness-sports' ) );
		}

		$booking_id = $wpdb->insert_id;

		// Send confirmation email.
		$this->send_booking_confirmation( $booking_id );

		/**
		 * Fires after a class is booked.
		 *
		 * @param int $booking_id  Booking ID.
		 * @param int $user_id     User ID.
		 * @param int $schedule_id Schedule ID.
		 */
		do_action( 'bkx_fitness_class_booked', $booking_id, $user_id, $schedule_id );

		return $booking_id;
	}

	/**
	 * Cancel a class booking.
	 *
	 * @since 1.0.0
	 * @param int $user_id    User ID.
	 * @param int $booking_id Booking ID.
	 * @return bool|\WP_Error
	 */
	public function cancel_booking( int $user_id, int $booking_id ) {
		global $wpdb;

		// Verify ownership.
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, s.start_datetime
				FROM {$this->tables['bookings']} b
				INNER JOIN {$this->tables['schedules']} s ON b.schedule_id = s.id
				WHERE b.id = %d AND b.user_id = %d",
				$booking_id,
				$user_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return new \WP_Error( 'invalid_booking', __( 'Booking not found.', 'bkx-fitness-sports' ) );
		}

		// Check cancellation deadline.
		$settings = get_option( 'bkx_fitness_sports_settings', array() );
		$cancel_hours = absint( $settings['cancellation_hours'] ?? 4 );
		$cancel_deadline = strtotime( $booking['start_datetime'] ) - ( $cancel_hours * HOUR_IN_SECONDS );

		if ( time() > $cancel_deadline ) {
			return new \WP_Error(
				'cancellation_deadline_passed',
				sprintf(
					/* translators: %d: hours */
					__( 'Cancellations must be made at least %d hours before class.', 'bkx-fitness-sports' ),
					$cancel_hours
				)
			);
		}

		// Update status.
		$result = $wpdb->update(
			$this->tables['bookings'],
			array(
				'status'       => 'cancelled',
				'cancelled_at' => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'cancel_failed', __( 'Failed to cancel booking.', 'bkx-fitness-sports' ) );
		}

		// Notify first person on waitlist.
		$this->promote_from_waitlist( $booking['schedule_id'] );

		/**
		 * Fires after a class booking is cancelled.
		 *
		 * @param int   $booking_id Booking ID.
		 * @param array $booking    Booking data.
		 */
		do_action( 'bkx_fitness_class_cancelled', $booking_id, $booking );

		return true;
	}

	/**
	 * Add user to waitlist.
	 *
	 * @since 1.0.0
	 * @param int $user_id     User ID.
	 * @param int $class_id    Class ID.
	 * @param int $schedule_id Schedule ID.
	 * @return int|\WP_Error Position in waitlist or error.
	 */
	public function add_to_waitlist( int $user_id, int $class_id, int $schedule_id ) {
		global $wpdb;

		// Check if already on waitlist.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->tables['waitlist']}
				WHERE user_id = %d AND schedule_id = %d AND status = 'waiting'",
				$user_id,
				$schedule_id
			)
		);

		if ( $existing ) {
			return new \WP_Error( 'already_waitlisted', __( 'You are already on the waitlist.', 'bkx-fitness-sports' ) );
		}

		// Get current position.
		$position = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) + 1 FROM {$this->tables['waitlist']}
				WHERE schedule_id = %d AND status = 'waiting'",
				$schedule_id
			)
		);

		$result = $wpdb->insert(
			$this->tables['waitlist'],
			array(
				'user_id'     => $user_id,
				'class_id'    => $class_id,
				'schedule_id' => $schedule_id,
				'position'    => $position,
				'status'      => 'waiting',
				'added_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'waitlist_failed', __( 'Failed to add to waitlist.', 'bkx-fitness-sports' ) );
		}

		return $position;
	}

	/**
	 * Promote first user from waitlist.
	 *
	 * @since 1.0.0
	 * @param int $schedule_id Schedule ID.
	 * @return bool
	 */
	public function promote_from_waitlist( int $schedule_id ): bool {
		global $wpdb;

		// Get first waiting user.
		$waitlist_entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->tables['waitlist']}
				WHERE schedule_id = %d AND status = 'waiting'
				ORDER BY position ASC
				LIMIT 1",
				$schedule_id
			),
			ARRAY_A
		);

		if ( ! $waitlist_entry ) {
			return false;
		}

		// Book the class.
		$booking_result = $this->book_class(
			$waitlist_entry['user_id'],
			$waitlist_entry['class_id'],
			$schedule_id
		);

		if ( is_wp_error( $booking_result ) ) {
			return false;
		}

		// Update waitlist status.
		$wpdb->update(
			$this->tables['waitlist'],
			array( 'status' => 'promoted' ),
			array( 'id' => $waitlist_entry['id'] ),
			array( '%s' ),
			array( '%d' )
		);

		// Send notification.
		$this->send_waitlist_promotion_notification( $waitlist_entry['user_id'], $schedule_id );

		return true;
	}

	/**
	 * Confirm attendance for a booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	public function confirm_attendance( int $booking_id ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->tables['bookings'],
			array(
				'status'      => 'attended',
				'attended_at' => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get user's class bookings.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID.
	 * @param string $status  Optional status filter.
	 * @return array
	 */
	public function get_user_bookings( int $user_id, string $status = '' ): array {
		global $wpdb;

		$where = array( 'b.user_id = %d' );
		$args  = array( $user_id );

		if ( $status ) {
			$where[] = 'b.status = %s';
			$args[]  = $status;
		}

		$where_clause = implode( ' AND ', $where );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, s.start_datetime, s.end_datetime, c.post_title as class_name, t.post_title as trainer_name
				FROM {$this->tables['bookings']} b
				INNER JOIN {$this->tables['schedules']} s ON b.schedule_id = s.id
				INNER JOIN {$wpdb->posts} c ON b.class_id = c.ID
				LEFT JOIN {$wpdb->posts} t ON s.trainer_id = t.ID
				WHERE {$where_clause}
				ORDER BY s.start_datetime DESC",
				$args
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Send booking confirmation email.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	private function send_booking_confirmation( int $booking_id ): void {
		global $wpdb;

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, s.start_datetime, s.end_datetime, s.location, c.post_title as class_name, u.user_email
				FROM {$this->tables['bookings']} b
				INNER JOIN {$this->tables['schedules']} s ON b.schedule_id = s.id
				INNER JOIN {$wpdb->posts} c ON b.class_id = c.ID
				INNER JOIN {$wpdb->users} u ON b.user_id = u.ID
				WHERE b.id = %d",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: class name */
			__( 'Class Booking Confirmation: %s', 'bkx-fitness-sports' ),
			$booking['class_name']
		);

		$message = sprintf(
			/* translators: 1: class name, 2: date, 3: time, 4: location */
			__( "You have successfully booked:\n\nClass: %1\$s\nDate: %2\$s\nTime: %3\$s\nLocation: %4\$s\n\nSee you there!", 'bkx-fitness-sports' ),
			$booking['class_name'],
			date_i18n( get_option( 'date_format' ), strtotime( $booking['start_datetime'] ) ),
			date_i18n( get_option( 'time_format' ), strtotime( $booking['start_datetime'] ) ),
			$booking['location']
		);

		wp_mail( $booking['user_email'], $subject, $message );
	}

	/**
	 * Send waitlist promotion notification.
	 *
	 * @since 1.0.0
	 * @param int $user_id     User ID.
	 * @param int $schedule_id Schedule ID.
	 * @return void
	 */
	private function send_waitlist_promotion_notification( int $user_id, int $schedule_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		global $wpdb;

		$schedule = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.*, c.post_title as class_name
				FROM {$this->tables['schedules']} s
				INNER JOIN {$wpdb->posts} c ON s.class_id = c.ID
				WHERE s.id = %d",
				$schedule_id
			),
			ARRAY_A
		);

		if ( ! $schedule ) {
			return;
		}

		$subject = __( 'You\'ve been moved off the waitlist!', 'bkx-fitness-sports' );

		$message = sprintf(
			/* translators: 1: class name, 2: date, 3: time */
			__( "Great news! A spot opened up and you've been automatically booked for:\n\nClass: %1\$s\nDate: %2\$s\nTime: %3\$s\n\nSee you there!", 'bkx-fitness-sports' ),
			$schedule['class_name'],
			date_i18n( get_option( 'date_format' ), strtotime( $schedule['start_datetime'] ) ),
			date_i18n( get_option( 'time_format' ), strtotime( $schedule['start_datetime'] ) )
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Send upcoming class reminders.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function send_upcoming_reminders(): void {
		global $wpdb;

		// Get classes starting in the next 2 hours.
		$upcoming = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, s.start_datetime, s.location, c.post_title as class_name, u.user_email, u.display_name
				FROM {$this->tables['bookings']} b
				INNER JOIN {$this->tables['schedules']} s ON b.schedule_id = s.id
				INNER JOIN {$wpdb->posts} c ON b.class_id = c.ID
				INNER JOIN {$wpdb->users} u ON b.user_id = u.ID
				WHERE b.status = 'confirmed'
				AND s.start_datetime BETWEEN %s AND %s
				AND b.reminder_sent = 0",
				current_time( 'mysql' ),
				date( 'Y-m-d H:i:s', strtotime( '+2 hours' ) )
			),
			ARRAY_A
		);

		foreach ( $upcoming ?: array() as $booking ) {
			$subject = sprintf(
				/* translators: %s: class name */
				__( 'Reminder: %s starts soon!', 'bkx-fitness-sports' ),
				$booking['class_name']
			);

			$message = sprintf(
				/* translators: 1: user name, 2: class name, 3: time, 4: location */
				__( "Hi %1\$s,\n\nThis is a reminder that %2\$s starts at %3\$s.\n\nLocation: %4\$s\n\nSee you soon!", 'bkx-fitness-sports' ),
				$booking['display_name'],
				$booking['class_name'],
				date_i18n( get_option( 'time_format' ), strtotime( $booking['start_datetime'] ) ),
				$booking['location']
			);

			wp_mail( $booking['user_email'], $subject, $message );

			// Mark reminder as sent.
			$wpdb->update(
				$this->tables['bookings'],
				array( 'reminder_sent' => 1 ),
				array( 'id' => $booking['id'] ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Render class schedule shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_schedule( array $atts = array() ): string {
		$atts = shortcode_atts( array(
			'view'     => 'week',
			'category' => '',
			'trainer'  => '',
		), $atts );

		$start_date = date( 'Y-m-d' );
		$end_date   = date( 'Y-m-d', strtotime( '+7 days' ) );

		if ( 'month' === $atts['view'] ) {
			$end_date = date( 'Y-m-d', strtotime( '+30 days' ) );
		}

		$schedule = $this->get_schedule_for_range( $start_date, $end_date );

		ob_start();
		?>
		<div class="bkx-class-schedule" data-view="<?php echo esc_attr( $atts['view'] ); ?>">
			<div class="bkx-schedule-header">
				<button class="bkx-schedule-nav bkx-prev">&larr; <?php esc_html_e( 'Previous', 'bkx-fitness-sports' ); ?></button>
				<span class="bkx-schedule-range"></span>
				<button class="bkx-schedule-nav bkx-next"><?php esc_html_e( 'Next', 'bkx-fitness-sports' ); ?> &rarr;</button>
			</div>

			<div class="bkx-schedule-body">
				<?php foreach ( $schedule as $date => $classes ) : ?>
					<div class="bkx-schedule-day" data-date="<?php echo esc_attr( $date ); ?>">
						<div class="bkx-day-header">
							<span class="bkx-day-name"><?php echo esc_html( date_i18n( 'l', strtotime( $date ) ) ); ?></span>
							<span class="bkx-day-date"><?php echo esc_html( date_i18n( 'M j', strtotime( $date ) ) ); ?></span>
						</div>

						<div class="bkx-day-classes">
							<?php foreach ( $classes as $class ) : ?>
								<div class="bkx-class-card <?php echo $class['is_full'] ? 'bkx-class-full' : ''; ?>" data-class-id="<?php echo esc_attr( $class['class_id'] ); ?>" data-schedule-id="<?php echo esc_attr( $class['id'] ); ?>">
									<div class="bkx-class-time">
										<?php echo esc_html( $class['start_time'] ); ?> - <?php echo esc_html( $class['end_time'] ); ?>
									</div>
									<div class="bkx-class-name"><?php echo esc_html( $class['class_name'] ); ?></div>
									<div class="bkx-class-trainer"><?php echo esc_html( $class['trainer_name'] ); ?></div>
									<div class="bkx-class-spots">
										<?php
										printf(
											/* translators: 1: booked count, 2: max capacity */
											esc_html__( '%1$d / %2$d spots', 'bkx-fitness-sports' ),
											$class['booked_count'],
											$class['max_capacity']
										);
										?>
									</div>

									<?php if ( is_user_logged_in() ) : ?>
										<?php if ( $class['is_full'] ) : ?>
											<button class="bkx-join-waitlist-btn" data-schedule-id="<?php echo esc_attr( $class['id'] ); ?>">
												<?php esc_html_e( 'Join Waitlist', 'bkx-fitness-sports' ); ?>
											</button>
										<?php else : ?>
											<button class="bkx-book-class-btn" data-schedule-id="<?php echo esc_attr( $class['id'] ); ?>">
												<?php esc_html_e( 'Book Class', 'bkx-fitness-sports' ); ?>
											</button>
										<?php endif; ?>
									<?php else : ?>
										<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bkx-login-to-book">
											<?php esc_html_e( 'Log in to Book', 'bkx-fitness-sports' ); ?>
										</a>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Format schedule row.
	 *
	 * @since 1.0.0
	 * @param array $row Database row.
	 * @return array
	 */
	private function format_schedule( array $row ): array {
		return array(
			'id'            => absint( $row['id'] ),
			'class_id'      => absint( $row['class_id'] ),
			'class_name'    => $row['class_name'],
			'description'   => $row['description'] ?? '',
			'trainer_id'    => absint( $row['trainer_id'] ),
			'trainer_name'  => $row['trainer_name'] ?? '',
			'start_datetime' => $row['start_datetime'],
			'end_datetime'  => $row['end_datetime'],
			'start_time'    => date_i18n( get_option( 'time_format' ), strtotime( $row['start_datetime'] ) ),
			'end_time'      => date_i18n( get_option( 'time_format' ), strtotime( $row['end_datetime'] ) ),
			'location'      => $row['location'] ?? '',
			'max_capacity'  => absint( $row['max_capacity'] ),
			'booked_count'  => absint( $row['booked_count'] ?? 0 ),
			'is_full'       => absint( $row['booked_count'] ?? 0 ) >= absint( $row['max_capacity'] ),
		);
	}
}

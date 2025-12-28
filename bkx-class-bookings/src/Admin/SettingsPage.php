<?php
/**
 * Settings Page
 *
 * @package BookingX\ClassBookings\Admin
 * @since   1.0.0
 */

namespace BookingX\ClassBookings\Admin;

/**
 * Admin settings page for Class Bookings.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\ClassBookings\ClassBookingsAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param \BookingX\ClassBookings\ClassBookingsAddon $addon Parent addon.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add menu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'bookingx',
			__( 'Class Bookings', 'bkx-class-bookings' ),
			__( 'Class Bookings', 'bkx-class-bookings' ),
			'manage_options',
			'bkx-class-bookings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'bkx_class_bookings_settings',
			'bkx_class_bookings_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @since 1.0.0
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		$sanitized['enable_waitlist'] = isset( $input['enable_waitlist'] ) ? 1 : 0;
		$sanitized['default_capacity'] = isset( $input['default_capacity'] ) ? absint( $input['default_capacity'] ) : 10;
		$sanitized['min_participants'] = isset( $input['min_participants'] ) ? absint( $input['min_participants'] ) : 1;
		$sanitized['cancellation_hours'] = isset( $input['cancellation_hours'] ) ? absint( $input['cancellation_hours'] ) : 24;
		$sanitized['allow_guest_booking'] = isset( $input['allow_guest_booking'] ) ? 1 : 0;
		$sanitized['require_phone'] = isset( $input['require_phone'] ) ? 1 : 0;
		$sanitized['send_confirmation'] = isset( $input['send_confirmation'] ) ? 1 : 0;
		$sanitized['send_reminder'] = isset( $input['send_reminder'] ) ? 1 : 0;
		$sanitized['reminder_hours'] = isset( $input['reminder_hours'] ) ? absint( $input['reminder_hours'] ) : 24;
		$sanitized['calendar_view'] = isset( $input['calendar_view'] ) ? sanitize_text_field( $input['calendar_view'] ) : 'week';
		$sanitized['schedule_weeks_ahead'] = isset( $input['schedule_weeks_ahead'] ) ? absint( $input['schedule_weeks_ahead'] ) : 4;

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$settings   = $this->addon->get_settings();
		?>
		<div class="wrap bkx-class-bookings-settings">
			<h1><?php esc_html_e( 'Class Bookings Settings', 'bkx-class-bookings' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-class-bookings&tab=settings' ) ); ?>"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bkx-class-bookings' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-class-bookings&tab=sessions' ) ); ?>"
				   class="nav-tab <?php echo 'sessions' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Sessions', 'bkx-class-bookings' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-class-bookings&tab=attendance' ) ); ?>"
				   class="nav-tab <?php echo 'attendance' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Attendance', 'bkx-class-bookings' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-class-bookings&tab=license' ) ); ?>"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'bkx-class-bookings' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'sessions':
						$this->render_sessions_tab();
						break;
					case 'attendance':
						$this->render_attendance_tab();
						break;
					case 'license':
						$this->addon->render_license_page();
						break;
					default:
						$this->render_settings_tab( $settings );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings tab.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_settings_tab( array $settings ): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bkx_class_bookings_settings' ); ?>

			<h2><?php esc_html_e( 'General Settings', 'bkx-class-bookings' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Default Capacity', 'bkx-class-bookings' ); ?></th>
					<td>
						<input type="number" name="bkx_class_bookings_settings[default_capacity]"
							   value="<?php echo esc_attr( $settings['default_capacity'] ?? 10 ); ?>"
							   min="1" max="500" class="small-text">
						<p class="description"><?php esc_html_e( 'Default number of spots per class session.', 'bkx-class-bookings' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Minimum Participants', 'bkx-class-bookings' ); ?></th>
					<td>
						<input type="number" name="bkx_class_bookings_settings[min_participants]"
							   value="<?php echo esc_attr( $settings['min_participants'] ?? 1 ); ?>"
							   min="1" max="100" class="small-text">
						<p class="description"><?php esc_html_e( 'Minimum participants required for a class to run.', 'bkx-class-bookings' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cancellation Window', 'bkx-class-bookings' ); ?></th>
					<td>
						<input type="number" name="bkx_class_bookings_settings[cancellation_hours]"
							   value="<?php echo esc_attr( $settings['cancellation_hours'] ?? 24 ); ?>"
							   min="0" max="168" class="small-text">
						<?php esc_html_e( 'hours before class', 'bkx-class-bookings' ); ?>
						<p class="description"><?php esc_html_e( 'Participants can cancel up to this many hours before the class.', 'bkx-class-bookings' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Schedule Ahead', 'bkx-class-bookings' ); ?></th>
					<td>
						<input type="number" name="bkx_class_bookings_settings[schedule_weeks_ahead]"
							   value="<?php echo esc_attr( $settings['schedule_weeks_ahead'] ?? 4 ); ?>"
							   min="1" max="52" class="small-text">
						<?php esc_html_e( 'weeks', 'bkx-class-bookings' ); ?>
						<p class="description"><?php esc_html_e( 'Auto-generate sessions this many weeks in advance.', 'bkx-class-bookings' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Booking Options', 'bkx-class-bookings' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Waitlist', 'bkx-class-bookings' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_class_bookings_settings[enable_waitlist]" value="1"
								   <?php checked( $settings['enable_waitlist'] ?? 0, 1 ); ?>>
							<?php esc_html_e( 'Allow customers to join a waitlist when classes are full', 'bkx-class-bookings' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Guest Booking', 'bkx-class-bookings' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_class_bookings_settings[allow_guest_booking]" value="1"
								   <?php checked( $settings['allow_guest_booking'] ?? 1, 1 ); ?>>
							<?php esc_html_e( 'Allow non-logged-in users to book classes', 'bkx-class-bookings' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Require Phone', 'bkx-class-bookings' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_class_bookings_settings[require_phone]" value="1"
								   <?php checked( $settings['require_phone'] ?? 0, 1 ); ?>>
							<?php esc_html_e( 'Require phone number for class bookings', 'bkx-class-bookings' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Notifications', 'bkx-class-bookings' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Confirmation Email', 'bkx-class-bookings' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_class_bookings_settings[send_confirmation]" value="1"
								   <?php checked( $settings['send_confirmation'] ?? 1, 1 ); ?>>
							<?php esc_html_e( 'Send confirmation email when class is booked', 'bkx-class-bookings' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Reminder Email', 'bkx-class-bookings' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_class_bookings_settings[send_reminder]" value="1"
								   <?php checked( $settings['send_reminder'] ?? 1, 1 ); ?>>
							<?php esc_html_e( 'Send reminder email before class', 'bkx-class-bookings' ); ?>
						</label>
						<br><br>
						<input type="number" name="bkx_class_bookings_settings[reminder_hours]"
							   value="<?php echo esc_attr( $settings['reminder_hours'] ?? 24 ); ?>"
							   min="1" max="168" class="small-text">
						<?php esc_html_e( 'hours before class', 'bkx-class-bookings' ); ?>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Display Options', 'bkx-class-bookings' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Default Calendar View', 'bkx-class-bookings' ); ?></th>
					<td>
						<select name="bkx_class_bookings_settings[calendar_view]">
							<option value="day" <?php selected( $settings['calendar_view'] ?? 'week', 'day' ); ?>>
								<?php esc_html_e( 'Day', 'bkx-class-bookings' ); ?>
							</option>
							<option value="week" <?php selected( $settings['calendar_view'] ?? 'week', 'week' ); ?>>
								<?php esc_html_e( 'Week', 'bkx-class-bookings' ); ?>
							</option>
							<option value="month" <?php selected( $settings['calendar_view'] ?? 'week', 'month' ); ?>>
								<?php esc_html_e( 'Month', 'bkx-class-bookings' ); ?>
							</option>
						</select>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render sessions tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_sessions_tab(): void {
		$schedule_service = new \BookingX\ClassBookings\Services\ScheduleService();
		$start_date       = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : current_time( 'Y-m-d' );
		$end_date         = gmdate( 'Y-m-d', strtotime( $start_date . ' +7 days' ) );
		$sessions         = $schedule_service->get_sessions( $start_date, $end_date );
		?>
		<div class="bkx-sessions-list">
			<h2><?php esc_html_e( 'Upcoming Sessions', 'bkx-class-bookings' ); ?></h2>

			<div class="bkx-date-nav">
				<?php
				$prev_date = gmdate( 'Y-m-d', strtotime( $start_date . ' -7 days' ) );
				$next_date = gmdate( 'Y-m-d', strtotime( $start_date . ' +7 days' ) );
				?>
				<a href="<?php echo esc_url( add_query_arg( 'start', $prev_date ) ); ?>" class="button">
					&laquo; <?php esc_html_e( 'Previous Week', 'bkx-class-bookings' ); ?>
				</a>
				<span class="bkx-date-range">
					<?php echo esc_html( wp_date( 'M j', strtotime( $start_date ) ) ); ?> -
					<?php echo esc_html( wp_date( 'M j, Y', strtotime( $end_date ) ) ); ?>
				</span>
				<a href="<?php echo esc_url( add_query_arg( 'start', $next_date ) ); ?>" class="button">
					<?php esc_html_e( 'Next Week', 'bkx-class-bookings' ); ?> &raquo;
				</a>
			</div>

			<?php if ( empty( $sessions ) ) : ?>
				<p><?php esc_html_e( 'No sessions scheduled for this period.', 'bkx-class-bookings' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Class', 'bkx-class-bookings' ); ?></th>
							<th><?php esc_html_e( 'Date', 'bkx-class-bookings' ); ?></th>
							<th><?php esc_html_e( 'Time', 'bkx-class-bookings' ); ?></th>
							<th><?php esc_html_e( 'Capacity', 'bkx-class-bookings' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-class-bookings' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-class-bookings' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sessions as $session ) : ?>
							<tr>
								<td><?php echo esc_html( $session['class_name'] ); ?></td>
								<td><?php echo esc_html( wp_date( 'l, M j', strtotime( $session['session_date'] ) ) ); ?></td>
								<td>
									<?php echo esc_html( wp_date( 'g:i A', strtotime( $session['start_time'] ) ) ); ?> -
									<?php echo esc_html( wp_date( 'g:i A', strtotime( $session['end_time'] ) ) ); ?>
								</td>
								<td>
									<?php echo esc_html( $session['booked_count'] ); ?> /
									<?php echo esc_html( $session['capacity'] ); ?>
								</td>
								<td>
									<span class="bkx-status bkx-status-<?php echo esc_attr( $session['status'] ); ?>">
										<?php echo esc_html( ucfirst( $session['status'] ) ); ?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-class-bookings&tab=attendance&session=' . $session['id'] ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Attendance', 'bkx-class-bookings' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render attendance tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_attendance_tab(): void {
		$session_id = isset( $_GET['session'] ) ? absint( $_GET['session'] ) : 0;

		if ( ! $session_id ) {
			echo '<p>' . esc_html__( 'Please select a session from the Sessions tab.', 'bkx-class-bookings' ) . '</p>';
			return;
		}

		$schedule_service   = new \BookingX\ClassBookings\Services\ScheduleService();
		$attendance_service = new \BookingX\ClassBookings\Services\AttendanceService();

		$session  = $schedule_service->get_session( $session_id );
		$bookings = $attendance_service->get_session_bookings( $session_id );

		if ( ! $session ) {
			echo '<p>' . esc_html__( 'Session not found.', 'bkx-class-bookings' ) . '</p>';
			return;
		}

		$class = get_post( $session['class_id'] );
		?>
		<div class="bkx-attendance-page">
			<h2>
				<?php
				printf(
					/* translators: 1: class name, 2: session date */
					esc_html__( 'Attendance: %1$s - %2$s', 'bkx-class-bookings' ),
					esc_html( $class->post_title ),
					esc_html( wp_date( 'l, M j, Y', strtotime( $session['session_date'] ) ) )
				);
				?>
			</h2>
			<p>
				<?php
				printf(
					/* translators: 1: start time, 2: end time */
					esc_html__( 'Time: %1$s - %2$s', 'bkx-class-bookings' ),
					esc_html( wp_date( 'g:i A', strtotime( $session['start_time'] ) ) ),
					esc_html( wp_date( 'g:i A', strtotime( $session['end_time'] ) ) )
				);
				?>
			</p>

			<?php if ( empty( $bookings ) ) : ?>
				<p><?php esc_html_e( 'No bookings for this session.', 'bkx-class-bookings' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped" id="bkx-attendance-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'bkx-class-bookings' ); ?></th>
							<th><?php esc_html_e( 'Email', 'bkx-class-bookings' ); ?></th>
							<th><?php esc_html_e( 'Qty', 'bkx-class-bookings' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-class-bookings' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-class-bookings' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $bookings as $booking ) : ?>
							<tr data-booking-id="<?php echo esc_attr( $booking['id'] ); ?>">
								<td><?php echo esc_html( $booking['customer_name'] ); ?></td>
								<td><?php echo esc_html( $booking['customer_email'] ); ?></td>
								<td><?php echo esc_html( $booking['quantity'] ); ?></td>
								<td>
									<span class="bkx-status bkx-status-<?php echo esc_attr( $booking['status'] ); ?>">
										<?php echo esc_html( ucfirst( $booking['status'] ) ); ?>
									</span>
									<?php if ( $booking['checked_in_at'] ) : ?>
										<br><small><?php echo esc_html( wp_date( 'g:i A', strtotime( $booking['checked_in_at'] ) ) ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( 'registered' === $booking['status'] ) : ?>
										<button type="button" class="button button-small bkx-check-in" data-id="<?php echo esc_attr( $booking['id'] ); ?>">
											<?php esc_html_e( 'Check In', 'bkx-class-bookings' ); ?>
										</button>
										<button type="button" class="button button-small bkx-mark-absent" data-id="<?php echo esc_attr( $booking['id'] ); ?>">
											<?php esc_html_e( 'Absent', 'bkx-class-bookings' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}

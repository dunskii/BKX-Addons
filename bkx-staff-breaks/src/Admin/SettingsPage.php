<?php
/**
 * Settings Page
 *
 * @package BookingX\StaffBreaks
 * @since   1.0.0
 */

namespace BookingX\StaffBreaks\Admin;

use BookingX\StaffBreaks\StaffBreaksAddon;

/**
 * Class SettingsPage
 *
 * Handles the admin settings page.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var StaffBreaksAddon
	 */
	private StaffBreaksAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param StaffBreaksAddon $addon Addon instance.
	 */
	public function __construct( StaffBreaksAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Add menu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'Staff Breaks', 'bkx-staff-breaks' ),
			__( 'Staff Breaks', 'bkx-staff-breaks' ),
			'manage_options',
			'bkx-staff-breaks',
			array( $this, 'render_page' ),
			'dashicons-coffee',
			57
		);
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		$this->handle_actions();

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'timeoff';
		?>
		<div class="wrap bkx-staff-breaks-settings">
			<h1><?php esc_html_e( 'Staff Breaks & Time Off', 'bkx-staff-breaks' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=bkx-staff-breaks&tab=timeoff"
				   class="nav-tab <?php echo 'timeoff' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Time Off', 'bkx-staff-breaks' ); ?>
				</a>
				<a href="?page=bkx-staff-breaks&tab=pending"
				   class="nav-tab <?php echo 'pending' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Pending Approval', 'bkx-staff-breaks' ); ?>
					<?php $this->render_pending_count(); ?>
				</a>
				<a href="?page=bkx-staff-breaks&tab=holidays"
				   class="nav-tab <?php echo 'holidays' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Holidays', 'bkx-staff-breaks' ); ?>
				</a>
				<a href="?page=bkx-staff-breaks&tab=settings"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bkx-staff-breaks' ); ?>
				</a>
				<a href="?page=bkx-staff-breaks&tab=license"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'bkx-staff-breaks' ); ?>
				</a>
			</nav>

			<div class="bkx-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'pending':
						$this->render_pending_tab();
						break;
					case 'holidays':
						$this->render_holidays_tab();
						break;
					case 'settings':
						$this->render_settings_tab();
						break;
					case 'license':
						$this->render_license_tab();
						break;
					default:
						$this->render_timeoff_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle form actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function handle_actions(): void {
		if ( ! isset( $_POST['bkx_breaks_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_breaks_nonce'] ) ), 'bkx_staff_breaks' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Save settings.
		if ( isset( $_POST['bkx_save_settings'] ) ) {
			$settings = array(
				'require_approval'     => ! empty( $_POST['require_approval'] ),
				'retention_days'       => absint( $_POST['retention_days'] ?? 90 ),
				'notify_on_request'    => ! empty( $_POST['notify_on_request'] ),
				'notify_on_approval'   => ! empty( $_POST['notify_on_approval'] ),
				'max_advance_days'     => absint( $_POST['max_advance_days'] ?? 365 ),
			);

			foreach ( $settings as $key => $value ) {
				$this->addon->update_setting( $key, $value );
			}

			add_settings_error( 'bkx_breaks', 'settings_updated', __( 'Settings saved.', 'bkx-staff-breaks' ), 'success' );
		}

		// Save holiday.
		if ( isset( $_POST['bkx_save_holiday'] ) ) {
			$timeoff_service = $this->addon->get_timeoff_service();

			$data = array(
				'seat_id'    => 0, // 0 means all staff.
				'start_date' => sanitize_text_field( wp_unslash( $_POST['holiday_date'] ?? '' ) ),
				'end_date'   => sanitize_text_field( wp_unslash( $_POST['holiday_date'] ?? '' ) ),
				'all_day'    => true,
				'type'       => 'holiday',
				'reason'     => sanitize_text_field( wp_unslash( $_POST['holiday_name'] ?? '' ) ),
				'recurring'  => ! empty( $_POST['holiday_recurring'] ) ? 'yearly' : '',
				'status'     => 'approved',
			);

			$result = $timeoff_service->add_timeoff( $data );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'bkx_breaks', 'holiday_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'bkx_breaks', 'holiday_added', __( 'Holiday added.', 'bkx-staff-breaks' ), 'success' );
			}
		}
	}

	/**
	 * Render pending count badge.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_pending_count(): void {
		$timeoff_service = $this->addon->get_timeoff_service();
		$pending         = $timeoff_service->get_timeoff( 0, 'pending' );
		$count           = count( $pending );

		if ( $count > 0 ) {
			echo '<span class="bkx-pending-badge">' . esc_html( $count ) . '</span>';
		}
	}

	/**
	 * Render time off tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_timeoff_tab(): void {
		settings_errors( 'bkx_breaks' );

		$timeoff_service = $this->addon->get_timeoff_service();
		$seat_id         = isset( $_GET['seat_id'] ) ? absint( $_GET['seat_id'] ) : 0;
		$entries         = $timeoff_service->get_timeoff( $seat_id, 'approved' );
		$types           = $timeoff_service->get_types();

		// Get seats for filter.
		$seats = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="bkx-timeoff-header">
			<form method="get" class="bkx-filter-form">
				<input type="hidden" name="page" value="bkx-staff-breaks" />
				<input type="hidden" name="tab" value="timeoff" />
				<select name="seat_id">
					<option value="0"><?php esc_html_e( 'All Resources', 'bkx-staff-breaks' ); ?></option>
					<?php foreach ( $seats as $seat ) : ?>
						<option value="<?php echo esc_attr( $seat->ID ); ?>" <?php selected( $seat_id, $seat->ID ); ?>>
							<?php echo esc_html( $seat->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-staff-breaks' ); ?></button>
			</form>
			<button type="button" class="button button-primary" id="bkx-add-timeoff">
				<?php esc_html_e( 'Add Time Off', 'bkx-staff-breaks' ); ?>
			</button>
		</div>

		<?php if ( empty( $entries ) ) : ?>
			<p><?php esc_html_e( 'No time off entries found.', 'bkx-staff-breaks' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Resource', 'bkx-staff-breaks' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-staff-breaks' ); ?></th>
						<th><?php esc_html_e( 'Date(s)', 'bkx-staff-breaks' ); ?></th>
						<th><?php esc_html_e( 'Time', 'bkx-staff-breaks' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'bkx-staff-breaks' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-staff-breaks' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $entries as $entry ) : ?>
						<?php
						$seat      = get_post( $entry['seat_id'] );
						$seat_name = $seat ? $seat->post_title : __( 'All Staff', 'bkx-staff-breaks' );
						?>
						<tr>
							<td><?php echo esc_html( $seat_name ); ?></td>
							<td><?php echo esc_html( $types[ $entry['type'] ] ?? $entry['type'] ); ?></td>
							<td>
								<?php
								if ( $entry['start_date'] === $entry['end_date'] ) {
									echo esc_html( $entry['start_date'] );
								} else {
									echo esc_html( $entry['start_date'] . ' - ' . $entry['end_date'] );
								}
								if ( 'yearly' === $entry['recurring'] ) {
									echo ' <span class="bkx-recurring-badge">' . esc_html__( 'Yearly', 'bkx-staff-breaks' ) . '</span>';
								}
								?>
							</td>
							<td>
								<?php
								if ( $entry['all_day'] ) {
									esc_html_e( 'All Day', 'bkx-staff-breaks' );
								} else {
									echo esc_html( substr( $entry['start_time'], 0, 5 ) . ' - ' . substr( $entry['end_time'], 0, 5 ) );
								}
								?>
							</td>
							<td><?php echo esc_html( $entry['reason'] ); ?></td>
							<td>
								<button type="button" class="button bkx-edit-timeoff" data-id="<?php echo esc_attr( $entry['id'] ); ?>">
									<?php esc_html_e( 'Edit', 'bkx-staff-breaks' ); ?>
								</button>
								<button type="button" class="button bkx-delete-timeoff" data-id="<?php echo esc_attr( $entry['id'] ); ?>">
									<?php esc_html_e( 'Delete', 'bkx-staff-breaks' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php $this->render_timeoff_modal( $seats, $types ); ?>
		<?php
	}

	/**
	 * Render time off modal.
	 *
	 * @since 1.0.0
	 * @param array $seats Available seats.
	 * @param array $types Time off types.
	 * @return void
	 */
	private function render_timeoff_modal( array $seats, array $types ): void {
		?>
		<div id="bkx-timeoff-modal" class="bkx-modal" style="display:none;">
			<div class="bkx-modal-content">
				<h2><?php esc_html_e( 'Add Time Off', 'bkx-staff-breaks' ); ?></h2>
				<form id="bkx-timeoff-form">
					<input type="hidden" name="entry_id" id="timeoff_entry_id" value="0" />
					<table class="form-table">
						<tr>
							<th><label for="timeoff_seat"><?php esc_html_e( 'Resource', 'bkx-staff-breaks' ); ?></label></th>
							<td>
								<select name="seat_id" id="timeoff_seat" required>
									<?php foreach ( $seats as $seat ) : ?>
										<option value="<?php echo esc_attr( $seat->ID ); ?>"><?php echo esc_html( $seat->post_title ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="timeoff_type"><?php esc_html_e( 'Type', 'bkx-staff-breaks' ); ?></label></th>
							<td>
								<select name="type" id="timeoff_type">
									<?php foreach ( $types as $value => $label ) : ?>
										<?php if ( 'holiday' !== $value ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
										<?php endif; ?>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="timeoff_start_date"><?php esc_html_e( 'Start Date', 'bkx-staff-breaks' ); ?></label></th>
							<td><input type="date" name="start_date" id="timeoff_start_date" required /></td>
						</tr>
						<tr>
							<th><label for="timeoff_end_date"><?php esc_html_e( 'End Date', 'bkx-staff-breaks' ); ?></label></th>
							<td><input type="date" name="end_date" id="timeoff_end_date" required /></td>
						</tr>
						<tr>
							<th><label for="timeoff_all_day"><?php esc_html_e( 'All Day', 'bkx-staff-breaks' ); ?></label></th>
							<td><input type="checkbox" name="all_day" id="timeoff_all_day" checked /></td>
						</tr>
						<tr class="bkx-time-row" style="display:none;">
							<th><label for="timeoff_start_time"><?php esc_html_e( 'Start Time', 'bkx-staff-breaks' ); ?></label></th>
							<td><input type="time" name="start_time" id="timeoff_start_time" /></td>
						</tr>
						<tr class="bkx-time-row" style="display:none;">
							<th><label for="timeoff_end_time"><?php esc_html_e( 'End Time', 'bkx-staff-breaks' ); ?></label></th>
							<td><input type="time" name="end_time" id="timeoff_end_time" /></td>
						</tr>
						<tr>
							<th><label for="timeoff_reason"><?php esc_html_e( 'Reason', 'bkx-staff-breaks' ); ?></label></th>
							<td><textarea name="reason" id="timeoff_reason" rows="3" class="large-text"></textarea></td>
						</tr>
					</table>
					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'bkx-staff-breaks' ); ?></button>
						<button type="button" class="button bkx-modal-close"><?php esc_html_e( 'Cancel', 'bkx-staff-breaks' ); ?></button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render pending approval tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_pending_tab(): void {
		$timeoff_service = $this->addon->get_timeoff_service();
		$entries         = $timeoff_service->get_timeoff( 0, 'pending' );
		$types           = $timeoff_service->get_types();

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'No pending time off requests.', 'bkx-staff-breaks' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Resource', 'bkx-staff-breaks' ); ?></th>
					<th><?php esc_html_e( 'Type', 'bkx-staff-breaks' ); ?></th>
					<th><?php esc_html_e( 'Date(s)', 'bkx-staff-breaks' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'bkx-staff-breaks' ); ?></th>
					<th><?php esc_html_e( 'Requested By', 'bkx-staff-breaks' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'bkx-staff-breaks' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<?php
					$seat         = get_post( $entry['seat_id'] );
					$seat_name    = $seat ? $seat->post_title : __( 'Unknown', 'bkx-staff-breaks' );
					$requested_by = get_user_by( 'ID', $entry['created_by'] );
					?>
					<tr>
						<td><?php echo esc_html( $seat_name ); ?></td>
						<td><?php echo esc_html( $types[ $entry['type'] ] ?? $entry['type'] ); ?></td>
						<td>
							<?php
							if ( $entry['start_date'] === $entry['end_date'] ) {
								echo esc_html( $entry['start_date'] );
							} else {
								echo esc_html( $entry['start_date'] . ' - ' . $entry['end_date'] );
							}
							?>
						</td>
						<td><?php echo esc_html( $entry['reason'] ); ?></td>
						<td><?php echo $requested_by ? esc_html( $requested_by->display_name ) : '—'; ?></td>
						<td>
							<button type="button" class="button button-primary bkx-approve-timeoff" data-id="<?php echo esc_attr( $entry['id'] ); ?>">
								<?php esc_html_e( 'Approve', 'bkx-staff-breaks' ); ?>
							</button>
							<button type="button" class="button bkx-reject-timeoff" data-id="<?php echo esc_attr( $entry['id'] ); ?>">
								<?php esc_html_e( 'Reject', 'bkx-staff-breaks' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render holidays tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_holidays_tab(): void {
		settings_errors( 'bkx_breaks' );

		$timeoff_service = $this->addon->get_timeoff_service();

		// Get holidays (seat_id = 0 and type = holiday).
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_staff_timeoff';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$holidays = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE type = %s ORDER BY start_date",
				'holiday'
			),
			ARRAY_A
		);
		?>
		<h2><?php esc_html_e( 'Company Holidays', 'bkx-staff-breaks' ); ?></h2>
		<p><?php esc_html_e( 'Holidays block bookings for all resources on these dates.', 'bkx-staff-breaks' ); ?></p>

		<form method="post" class="bkx-add-holiday-form">
			<?php wp_nonce_field( 'bkx_staff_breaks', 'bkx_breaks_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="holiday_name"><?php esc_html_e( 'Holiday Name', 'bkx-staff-breaks' ); ?></label></th>
					<td><input type="text" name="holiday_name" id="holiday_name" class="regular-text" required /></td>
				</tr>
				<tr>
					<th><label for="holiday_date"><?php esc_html_e( 'Date', 'bkx-staff-breaks' ); ?></label></th>
					<td><input type="date" name="holiday_date" id="holiday_date" required /></td>
				</tr>
				<tr>
					<th><label for="holiday_recurring"><?php esc_html_e( 'Recurring', 'bkx-staff-breaks' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="holiday_recurring" id="holiday_recurring" value="1" />
							<?php esc_html_e( 'Repeat this holiday every year', 'bkx-staff-breaks' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<p><button type="submit" name="bkx_save_holiday" class="button button-primary"><?php esc_html_e( 'Add Holiday', 'bkx-staff-breaks' ); ?></button></p>
		</form>

		<hr />

		<?php if ( empty( $holidays ) ) : ?>
			<p><?php esc_html_e( 'No holidays configured.', 'bkx-staff-breaks' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Holiday', 'bkx-staff-breaks' ); ?></th>
						<th><?php esc_html_e( 'Date', 'bkx-staff-breaks' ); ?></th>
						<th><?php esc_html_e( 'Recurring', 'bkx-staff-breaks' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-staff-breaks' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $holidays as $holiday ) : ?>
						<tr>
							<td><?php echo esc_html( $holiday['reason'] ); ?></td>
							<td><?php echo esc_html( $holiday['start_date'] ); ?></td>
							<td>
								<?php if ( 'yearly' === $holiday['recurring'] ) : ?>
									<span class="bkx-recurring-badge"><?php esc_html_e( 'Yes', 'bkx-staff-breaks' ); ?></span>
								<?php else : ?>
									<?php esc_html_e( 'No', 'bkx-staff-breaks' ); ?>
								<?php endif; ?>
							</td>
							<td>
								<button type="button" class="button bkx-delete-timeoff" data-id="<?php echo esc_attr( $holiday['id'] ); ?>">
									<?php esc_html_e( 'Delete', 'bkx-staff-breaks' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render settings tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_settings_tab(): void {
		settings_errors( 'bkx_breaks' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'bkx_staff_breaks', 'bkx_breaks_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th><label for="require_approval"><?php esc_html_e( 'Require Approval', 'bkx-staff-breaks' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="require_approval" id="require_approval" value="1"
								<?php checked( $this->addon->get_setting( 'require_approval', false ) ); ?> />
							<?php esc_html_e( 'Require admin approval for time off requests', 'bkx-staff-breaks' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="retention_days"><?php esc_html_e( 'Data Retention', 'bkx-staff-breaks' ); ?></label></th>
					<td>
						<input type="number" name="retention_days" id="retention_days" class="small-text" min="30" max="365"
							value="<?php echo esc_attr( $this->addon->get_setting( 'retention_days', 90 ) ); ?>" />
						<?php esc_html_e( 'days', 'bkx-staff-breaks' ); ?>
						<p class="description"><?php esc_html_e( 'Delete old time off entries after this many days.', 'bkx-staff-breaks' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="notify_on_request"><?php esc_html_e( 'Email Notifications', 'bkx-staff-breaks' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="notify_on_request" id="notify_on_request" value="1"
								<?php checked( $this->addon->get_setting( 'notify_on_request', true ) ); ?> />
							<?php esc_html_e( 'Notify admin when new time off request is submitted', 'bkx-staff-breaks' ); ?>
						</label>
						<br />
						<label>
							<input type="checkbox" name="notify_on_approval" id="notify_on_approval" value="1"
								<?php checked( $this->addon->get_setting( 'notify_on_approval', true ) ); ?> />
							<?php esc_html_e( 'Notify staff when time off request is approved/rejected', 'bkx-staff-breaks' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="max_advance_days"><?php esc_html_e( 'Maximum Advance', 'bkx-staff-breaks' ); ?></label></th>
					<td>
						<input type="number" name="max_advance_days" id="max_advance_days" class="small-text" min="30" max="730"
							value="<?php echo esc_attr( $this->addon->get_setting( 'max_advance_days', 365 ) ); ?>" />
						<?php esc_html_e( 'days', 'bkx-staff-breaks' ); ?>
						<p class="description"><?php esc_html_e( 'How far in advance staff can request time off.', 'bkx-staff-breaks' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="bkx_save_settings" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'bkx-staff-breaks' ); ?>
				</button>
			</p>
		</form>
		<?php
	}

	/**
	 * Render license tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_license_tab(): void {
		$license_key    = $this->addon->get_license_key();
		$license_status = $this->addon->get_license_status();
		$is_active      = 'valid' === $license_status;
		?>
		<form method="post">
			<?php wp_nonce_field( 'bkx_staff_breaks', 'bkx_breaks_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th><label for="license_key"><?php esc_html_e( 'License Key', 'bkx-staff-breaks' ); ?></label></th>
					<td>
						<input type="text" name="license_key" id="license_key" class="regular-text"
							value="<?php echo esc_attr( $license_key ); ?>"
							<?php echo $is_active ? 'readonly' : ''; ?> />
						<?php if ( $is_active ) : ?>
							<span class="bkx-license-active">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Active', 'bkx-staff-breaks' ); ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<p class="submit">
				<?php if ( $is_active ) : ?>
					<button type="submit" name="bkx_deactivate_license" class="button">
						<?php esc_html_e( 'Deactivate License', 'bkx-staff-breaks' ); ?>
					</button>
				<?php else : ?>
					<button type="submit" name="bkx_activate_license" class="button button-primary">
						<?php esc_html_e( 'Activate License', 'bkx-staff-breaks' ); ?>
					</button>
				<?php endif; ?>
			</p>
		</form>
		<?php
	}
}

<?php
/**
 * Class Metabox
 *
 * @package BookingX\ClassBookings\Admin
 * @since   1.0.0
 */

namespace BookingX\ClassBookings\Admin;

/**
 * Metabox for class post type.
 *
 * @since 1.0.0
 */
class ClassMetabox {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_bkx_class', array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Add meta boxes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'bkx-class-settings',
			__( 'Class Settings', 'bkx-class-bookings' ),
			array( $this, 'render_settings_metabox' ),
			'bkx_class',
			'normal',
			'high'
		);

		add_meta_box(
			'bkx-class-schedule',
			__( 'Recurring Schedule', 'bkx-class-bookings' ),
			array( $this, 'render_schedule_metabox' ),
			'bkx_class',
			'normal',
			'default'
		);
	}

	/**
	 * Render settings metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_settings_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'bkx_class_settings', 'bkx_class_nonce' );

		$duration           = get_post_meta( $post->ID, '_bkx_class_duration', true ) ?: 60;
		$capacity           = get_post_meta( $post->ID, '_bkx_class_capacity', true ) ?: 10;
		$price              = get_post_meta( $post->ID, '_bkx_class_price', true ) ?: 0;
		$allow_waitlist     = get_post_meta( $post->ID, '_bkx_class_allow_waitlist', true );
		$min_participants   = get_post_meta( $post->ID, '_bkx_class_min_participants', true ) ?: 1;
		$instructor_id      = get_post_meta( $post->ID, '_bkx_class_instructor_id', true );
		$location           = get_post_meta( $post->ID, '_bkx_class_location', true );
		$color              = get_post_meta( $post->ID, '_bkx_class_color', true ) ?: '#3788d8';
		$cancellation_hours = get_post_meta( $post->ID, '_bkx_class_cancellation_hours', true ) ?: 24;

		// Get instructors (seats).
		$instructors = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<table class="form-table bkx-class-settings">
			<tr>
				<th><label for="bkx_class_duration"><?php esc_html_e( 'Duration (minutes)', 'bkx-class-bookings' ); ?></label></th>
				<td>
					<input type="number" id="bkx_class_duration" name="bkx_class_duration"
						   value="<?php echo esc_attr( $duration ); ?>" min="5" max="480" class="small-text">
				</td>
			</tr>
			<tr>
				<th><label for="bkx_class_capacity"><?php esc_html_e( 'Capacity', 'bkx-class-bookings' ); ?></label></th>
				<td>
					<input type="number" id="bkx_class_capacity" name="bkx_class_capacity"
						   value="<?php echo esc_attr( $capacity ); ?>" min="1" max="500" class="small-text">
					<p class="description"><?php esc_html_e( 'Maximum number of participants per session.', 'bkx-class-bookings' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_class_price"><?php esc_html_e( 'Price', 'bkx-class-bookings' ); ?></label></th>
				<td>
					<input type="number" id="bkx_class_price" name="bkx_class_price"
						   value="<?php echo esc_attr( $price ); ?>" min="0" step="0.01" class="small-text">
				</td>
			</tr>
			<tr>
				<th><label for="bkx_class_min_participants"><?php esc_html_e( 'Minimum Participants', 'bkx-class-bookings' ); ?></label></th>
				<td>
					<input type="number" id="bkx_class_min_participants" name="bkx_class_min_participants"
						   value="<?php echo esc_attr( $min_participants ); ?>" min="1" max="100" class="small-text">
					<p class="description"><?php esc_html_e( 'Minimum required for the class to run.', 'bkx-class-bookings' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_class_instructor_id"><?php esc_html_e( 'Instructor', 'bkx-class-bookings' ); ?></label></th>
				<td>
					<select id="bkx_class_instructor_id" name="bkx_class_instructor_id">
						<option value=""><?php esc_html_e( '— Select Instructor —', 'bkx-class-bookings' ); ?></option>
						<?php foreach ( $instructors as $instructor ) : ?>
							<option value="<?php echo esc_attr( $instructor->ID ); ?>" <?php selected( $instructor_id, $instructor->ID ); ?>>
								<?php echo esc_html( $instructor->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_class_location"><?php esc_html_e( 'Location', 'bkx-class-bookings' ); ?></label></th>
				<td>
					<input type="text" id="bkx_class_location" name="bkx_class_location"
						   value="<?php echo esc_attr( $location ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Room or studio name.', 'bkx-class-bookings' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_class_color"><?php esc_html_e( 'Calendar Color', 'bkx-class-bookings' ); ?></label></th>
				<td>
					<input type="color" id="bkx_class_color" name="bkx_class_color"
						   value="<?php echo esc_attr( $color ); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="bkx_class_cancellation_hours"><?php esc_html_e( 'Cancellation Window', 'bkx-class-bookings' ); ?></label></th>
				<td>
					<input type="number" id="bkx_class_cancellation_hours" name="bkx_class_cancellation_hours"
						   value="<?php echo esc_attr( $cancellation_hours ); ?>" min="0" max="168" class="small-text">
					<?php esc_html_e( 'hours', 'bkx-class-bookings' ); ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Waitlist', 'bkx-class-bookings' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_class_allow_waitlist" value="1" <?php checked( $allow_waitlist, 1 ); ?>>
						<?php esc_html_e( 'Allow waitlist when class is full', 'bkx-class-bookings' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render schedule metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_schedule_metabox( \WP_Post $post ): void {
		$schedule_service = new \BookingX\ClassBookings\Services\ScheduleService();
		$schedules        = $schedule_service->get_schedules( $post->ID );

		$days = array(
			0 => __( 'Sunday', 'bkx-class-bookings' ),
			1 => __( 'Monday', 'bkx-class-bookings' ),
			2 => __( 'Tuesday', 'bkx-class-bookings' ),
			3 => __( 'Wednesday', 'bkx-class-bookings' ),
			4 => __( 'Thursday', 'bkx-class-bookings' ),
			5 => __( 'Friday', 'bkx-class-bookings' ),
			6 => __( 'Saturday', 'bkx-class-bookings' ),
		);
		?>
		<div class="bkx-schedule-wrapper">
			<p class="description"><?php esc_html_e( 'Define recurring time slots when this class is offered.', 'bkx-class-bookings' ); ?></p>

			<table class="widefat" id="bkx-schedule-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Day', 'bkx-class-bookings' ); ?></th>
						<th><?php esc_html_e( 'Start Time', 'bkx-class-bookings' ); ?></th>
						<th><?php esc_html_e( 'End Time', 'bkx-class-bookings' ); ?></th>
						<th><?php esc_html_e( 'Capacity', 'bkx-class-bookings' ); ?></th>
						<th><?php esc_html_e( 'Active', 'bkx-class-bookings' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $schedules ) ) : ?>
						<tr class="bkx-no-schedules">
							<td colspan="6"><?php esc_html_e( 'No schedules defined. Add one below.', 'bkx-class-bookings' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $schedules as $schedule ) : ?>
							<tr data-schedule-id="<?php echo esc_attr( $schedule['id'] ); ?>">
								<td><?php echo esc_html( $days[ (int) $schedule['day_of_week'] ] ); ?></td>
								<td><?php echo esc_html( wp_date( 'g:i A', strtotime( $schedule['start_time'] ) ) ); ?></td>
								<td><?php echo esc_html( wp_date( 'g:i A', strtotime( $schedule['end_time'] ) ) ); ?></td>
								<td><?php echo esc_html( $schedule['capacity'] ); ?></td>
								<td><?php echo $schedule['is_active'] ? '&#10003;' : '&#10007;'; ?></td>
								<td>
									<button type="button" class="button button-small bkx-delete-schedule" data-id="<?php echo esc_attr( $schedule['id'] ); ?>">
										<?php esc_html_e( 'Delete', 'bkx-class-bookings' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<div class="bkx-add-schedule">
				<h4><?php esc_html_e( 'Add Schedule', 'bkx-class-bookings' ); ?></h4>
				<div class="bkx-schedule-form">
					<label>
						<?php esc_html_e( 'Day:', 'bkx-class-bookings' ); ?>
						<select name="bkx_new_schedule_day">
							<?php foreach ( $days as $num => $name ) : ?>
								<option value="<?php echo esc_attr( $num ); ?>"><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<?php esc_html_e( 'Start:', 'bkx-class-bookings' ); ?>
						<input type="time" name="bkx_new_schedule_start" value="09:00">
					</label>
					<label>
						<?php esc_html_e( 'End:', 'bkx-class-bookings' ); ?>
						<input type="time" name="bkx_new_schedule_end" value="10:00">
					</label>
					<label>
						<?php esc_html_e( 'Capacity:', 'bkx-class-bookings' ); ?>
						<input type="number" name="bkx_new_schedule_capacity" value="10" min="1" max="500" class="small-text">
					</label>
					<button type="button" class="button button-primary" id="bkx-add-schedule-btn">
						<?php esc_html_e( 'Add', 'bkx-class-bookings' ); ?>
					</button>
				</div>
			</div>

			<div class="bkx-generate-sessions">
				<h4><?php esc_html_e( 'Generate Sessions', 'bkx-class-bookings' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Create individual session instances from the recurring schedule.', 'bkx-class-bookings' ); ?></p>
				<label>
					<?php esc_html_e( 'From:', 'bkx-class-bookings' ); ?>
					<input type="date" name="bkx_generate_from" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
				</label>
				<label>
					<?php esc_html_e( 'To:', 'bkx-class-bookings' ); ?>
					<input type="date" name="bkx_generate_to" value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+4 weeks' ) ) ); ?>">
				</label>
				<button type="button" class="button" id="bkx-generate-sessions-btn">
					<?php esc_html_e( 'Generate Sessions', 'bkx-class-bookings' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Save meta data.
	 *
	 * @since 1.0.0
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST['bkx_class_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_class_nonce'] ) ), 'bkx_class_settings' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save meta fields.
		$fields = array(
			'bkx_class_duration'           => 'absint',
			'bkx_class_capacity'           => 'absint',
			'bkx_class_price'              => 'floatval',
			'bkx_class_min_participants'   => 'absint',
			'bkx_class_instructor_id'      => 'absint',
			'bkx_class_location'           => 'sanitize_text_field',
			'bkx_class_color'              => 'sanitize_hex_color',
			'bkx_class_cancellation_hours' => 'absint',
		);

		foreach ( $fields as $field => $sanitizer ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = call_user_func( $sanitizer, wp_unslash( $_POST[ $field ] ) );
				update_post_meta( $post_id, '_' . $field, $value );
			}
		}

		// Checkbox field.
		$allow_waitlist = isset( $_POST['bkx_class_allow_waitlist'] ) ? 1 : 0;
		update_post_meta( $post_id, '_bkx_class_allow_waitlist', $allow_waitlist );
	}
}

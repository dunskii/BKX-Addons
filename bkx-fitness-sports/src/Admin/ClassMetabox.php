<?php
/**
 * Class Metabox
 *
 * Adds fitness-specific fields to class posts.
 *
 * @package BookingX\FitnessSports\Admin
 * @since   1.0.0
 */

namespace BookingX\FitnessSports\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ClassMetabox
 *
 * @since 1.0.0
 */
class ClassMetabox {

	/**
	 * Initialize metabox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post_bkx_fitness_class', array( $this, 'save_metabox' ), 10, 2 );
	}

	/**
	 * Add metaboxes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_metaboxes(): void {
		add_meta_box(
			'bkx_class_details',
			__( 'Class Details', 'bkx-fitness-sports' ),
			array( $this, 'render_details_metabox' ),
			'bkx_fitness_class',
			'normal',
			'high'
		);

		add_meta_box(
			'bkx_class_schedule',
			__( 'Class Schedule', 'bkx-fitness-sports' ),
			array( $this, 'render_schedule_metabox' ),
			'bkx_fitness_class',
			'normal',
			'default'
		);
	}

	/**
	 * Render class details metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_details_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'bkx_fitness_class', 'bkx_fitness_class_nonce' );

		$duration             = get_post_meta( $post->ID, '_bkx_class_duration', true ) ?: 60;
		$max_capacity         = get_post_meta( $post->ID, '_bkx_max_capacity', true ) ?: 20;
		$membership_required  = get_post_meta( $post->ID, '_bkx_membership_required', true );
		$equipment_needed     = get_post_meta( $post->ID, '_bkx_equipment_needed', true ) ?: array();
		$calories_estimate    = get_post_meta( $post->ID, '_bkx_calories_estimate', true );
		$virtual_option       = get_post_meta( $post->ID, '_bkx_virtual_option', true );
		$virtual_link         = get_post_meta( $post->ID, '_bkx_virtual_link', true );
		?>
		<table class="form-table">
			<tr>
				<th><label for="bkx_class_duration"><?php esc_html_e( 'Duration (minutes)', 'bkx-fitness-sports' ); ?></label></th>
				<td>
					<input type="number" name="bkx_class_duration" id="bkx_class_duration" value="<?php echo esc_attr( $duration ); ?>" min="15" step="5" class="small-text">
				</td>
			</tr>
			<tr>
				<th><label for="bkx_max_capacity"><?php esc_html_e( 'Max Capacity', 'bkx-fitness-sports' ); ?></label></th>
				<td>
					<input type="number" name="bkx_max_capacity" id="bkx_max_capacity" value="<?php echo esc_attr( $max_capacity ); ?>" min="1" class="small-text">
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Membership Required', 'bkx-fitness-sports' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_membership_required" value="1" <?php checked( $membership_required, '1' ); ?>>
						<?php esc_html_e( 'Require active membership to book', 'bkx-fitness-sports' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_equipment_needed"><?php esc_html_e( 'Equipment Needed', 'bkx-fitness-sports' ); ?></label></th>
				<td>
					<textarea name="bkx_equipment_needed" id="bkx_equipment_needed" rows="3" class="large-text"><?php echo esc_textarea( implode( "\n", $equipment_needed ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One item per line (e.g., yoga mat, resistance bands)', 'bkx-fitness-sports' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_calories_estimate"><?php esc_html_e( 'Estimated Calories Burned', 'bkx-fitness-sports' ); ?></label></th>
				<td>
					<input type="number" name="bkx_calories_estimate" id="bkx_calories_estimate" value="<?php echo esc_attr( $calories_estimate ); ?>" min="0" class="small-text">
					<span class="description"><?php esc_html_e( 'calories per session', 'bkx-fitness-sports' ); ?></span>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Virtual Option', 'bkx-fitness-sports' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_virtual_option" value="1" <?php checked( $virtual_option, '1' ); ?>>
						<?php esc_html_e( 'Offer virtual/online option', 'bkx-fitness-sports' ); ?>
					</label>
				</td>
			</tr>
			<tr class="bkx-virtual-link-row" <?php echo $virtual_option ? '' : 'style="display:none;"'; ?>>
				<th><label for="bkx_virtual_link"><?php esc_html_e( 'Virtual Class Link', 'bkx-fitness-sports' ); ?></label></th>
				<td>
					<input type="url" name="bkx_virtual_link" id="bkx_virtual_link" value="<?php echo esc_url( $virtual_link ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Zoom, Google Meet, or other virtual class link', 'bkx-fitness-sports' ); ?></p>
				</td>
			</tr>
		</table>

		<script>
		jQuery(document).ready(function($) {
			$('input[name="bkx_virtual_option"]').on('change', function() {
				$('.bkx-virtual-link-row').toggle($(this).is(':checked'));
			});
		});
		</script>
		<?php
	}

	/**
	 * Render schedule metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_schedule_metabox( \WP_Post $post ): void {
		global $wpdb;

		$schedules_table = $wpdb->prefix . 'bkx_class_schedules';

		$schedules = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, t.post_title as trainer_name
				FROM {$schedules_table} s
				LEFT JOIN {$wpdb->posts} t ON s.trainer_id = t.ID
				WHERE s.class_id = %d
				ORDER BY s.start_datetime ASC",
				$post->ID
			),
			ARRAY_A
		);

		$trainers = get_posts( array(
			'post_type'      => 'bkx_seat',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_bkx_is_trainer',
					'value' => '1',
				),
			),
		) );
		?>
		<div class="bkx-schedule-manager">
			<table class="widefat bkx-schedules-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Day', 'bkx-fitness-sports' ); ?></th>
						<th><?php esc_html_e( 'Start Time', 'bkx-fitness-sports' ); ?></th>
						<th><?php esc_html_e( 'End Time', 'bkx-fitness-sports' ); ?></th>
						<th><?php esc_html_e( 'Trainer', 'bkx-fitness-sports' ); ?></th>
						<th><?php esc_html_e( 'Location', 'bkx-fitness-sports' ); ?></th>
						<th><?php esc_html_e( 'Capacity', 'bkx-fitness-sports' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody id="bkx-schedules-body">
					<?php if ( ! empty( $schedules ) ) : ?>
						<?php foreach ( $schedules as $index => $schedule ) : ?>
							<tr class="bkx-schedule-row" data-schedule-id="<?php echo esc_attr( $schedule['id'] ); ?>">
								<td>
									<select name="bkx_schedules[<?php echo $index; ?>][day]">
										<?php
										$days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
										$schedule_day = strtolower( date( 'l', strtotime( $schedule['start_datetime'] ) ) );
										foreach ( $days as $day ) {
											echo '<option value="' . esc_attr( $day ) . '" ' . selected( $schedule_day, $day, false ) . '>' . esc_html( ucfirst( $day ) ) . '</option>';
										}
										?>
									</select>
								</td>
								<td>
									<input type="time" name="bkx_schedules[<?php echo $index; ?>][start_time]" value="<?php echo esc_attr( date( 'H:i', strtotime( $schedule['start_datetime'] ) ) ); ?>">
								</td>
								<td>
									<input type="time" name="bkx_schedules[<?php echo $index; ?>][end_time]" value="<?php echo esc_attr( date( 'H:i', strtotime( $schedule['end_datetime'] ) ) ); ?>">
								</td>
								<td>
									<select name="bkx_schedules[<?php echo $index; ?>][trainer_id]">
										<option value=""><?php esc_html_e( 'Select Trainer', 'bkx-fitness-sports' ); ?></option>
										<?php foreach ( $trainers as $trainer ) : ?>
											<option value="<?php echo esc_attr( $trainer->ID ); ?>" <?php selected( $schedule['trainer_id'], $trainer->ID ); ?>>
												<?php echo esc_html( $trainer->post_title ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<input type="text" name="bkx_schedules[<?php echo $index; ?>][location]" value="<?php echo esc_attr( $schedule['location'] ); ?>" placeholder="<?php esc_attr_e( 'Room/Studio', 'bkx-fitness-sports' ); ?>">
								</td>
								<td>
									<input type="number" name="bkx_schedules[<?php echo $index; ?>][max_capacity]" value="<?php echo esc_attr( $schedule['max_capacity'] ); ?>" min="1" class="small-text">
									<input type="hidden" name="bkx_schedules[<?php echo $index; ?>][id]" value="<?php echo esc_attr( $schedule['id'] ); ?>">
								</td>
								<td>
									<button type="button" class="button bkx-remove-schedule">&times;</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="7">
							<button type="button" class="button bkx-add-schedule" id="bkx-add-schedule">
								<?php esc_html_e( '+ Add Schedule', 'bkx-fitness-sports' ); ?>
							</button>
						</td>
					</tr>
				</tfoot>
			</table>
		</div>

		<script type="text/template" id="bkx-schedule-row-template">
			<tr class="bkx-schedule-row">
				<td>
					<select name="bkx_schedules[{{index}}][day]">
						<?php foreach ( $days as $day ) : ?>
							<option value="<?php echo esc_attr( $day ); ?>"><?php echo esc_html( ucfirst( $day ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<input type="time" name="bkx_schedules[{{index}}][start_time]" value="09:00">
				</td>
				<td>
					<input type="time" name="bkx_schedules[{{index}}][end_time]" value="10:00">
				</td>
				<td>
					<select name="bkx_schedules[{{index}}][trainer_id]">
						<option value=""><?php esc_html_e( 'Select Trainer', 'bkx-fitness-sports' ); ?></option>
						<?php foreach ( $trainers as $trainer ) : ?>
							<option value="<?php echo esc_attr( $trainer->ID ); ?>"><?php echo esc_html( $trainer->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td>
					<input type="text" name="bkx_schedules[{{index}}][location]" placeholder="<?php esc_attr_e( 'Room/Studio', 'bkx-fitness-sports' ); ?>">
				</td>
				<td>
					<input type="number" name="bkx_schedules[{{index}}][max_capacity]" value="20" min="1" class="small-text">
					<input type="hidden" name="bkx_schedules[{{index}}][id]" value="">
				</td>
				<td>
					<button type="button" class="button bkx-remove-schedule">&times;</button>
				</td>
			</tr>
		</script>

		<script>
		jQuery(document).ready(function($) {
			var scheduleIndex = <?php echo count( $schedules ); ?>;

			$('#bkx-add-schedule').on('click', function() {
				var template = $('#bkx-schedule-row-template').html();
				template = template.replace(/\{\{index\}\}/g, scheduleIndex);
				$('#bkx-schedules-body').append(template);
				scheduleIndex++;
			});

			$(document).on('click', '.bkx-remove-schedule', function() {
				$(this).closest('tr').remove();
			});
		});
		</script>
		<?php
	}

	/**
	 * Save metabox data.
	 *
	 * @since 1.0.0
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_metabox( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['bkx_fitness_class_nonce'] ) || ! wp_verify_nonce( $_POST['bkx_fitness_class_nonce'], 'bkx_fitness_class' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save class details.
		update_post_meta( $post_id, '_bkx_class_duration', absint( $_POST['bkx_class_duration'] ?? 60 ) );
		update_post_meta( $post_id, '_bkx_max_capacity', absint( $_POST['bkx_max_capacity'] ?? 20 ) );
		update_post_meta( $post_id, '_bkx_membership_required', isset( $_POST['bkx_membership_required'] ) ? '1' : '' );
		update_post_meta( $post_id, '_bkx_calories_estimate', absint( $_POST['bkx_calories_estimate'] ?? 0 ) );
		update_post_meta( $post_id, '_bkx_virtual_option', isset( $_POST['bkx_virtual_option'] ) ? '1' : '' );
		update_post_meta( $post_id, '_bkx_virtual_link', esc_url_raw( $_POST['bkx_virtual_link'] ?? '' ) );

		// Equipment needed.
		if ( isset( $_POST['bkx_equipment_needed'] ) ) {
			$equipment = array_filter( array_map( 'sanitize_text_field', explode( "\n", $_POST['bkx_equipment_needed'] ) ) );
			update_post_meta( $post_id, '_bkx_equipment_needed', $equipment );
		}

		// Save schedules.
		$this->save_schedules( $post_id );
	}

	/**
	 * Save class schedules.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_schedules( int $post_id ): void {
		global $wpdb;

		$schedules_table = $wpdb->prefix . 'bkx_class_schedules';
		$max_capacity    = absint( get_post_meta( $post_id, '_bkx_max_capacity', true ) ) ?: 20;

		if ( ! isset( $_POST['bkx_schedules'] ) || ! is_array( $_POST['bkx_schedules'] ) ) {
			return;
		}

		$existing_ids   = array();
		$submitted_ids  = array();

		// Get existing schedule IDs.
		$existing = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$schedules_table} WHERE class_id = %d",
				$post_id
			)
		);
		$existing_ids = array_map( 'absint', $existing );

		foreach ( $_POST['bkx_schedules'] as $schedule ) {
			$schedule_id = absint( $schedule['id'] ?? 0 );

			// Calculate datetime from day and time.
			$day        = sanitize_text_field( $schedule['day'] ?? 'monday' );
			$start_time = sanitize_text_field( $schedule['start_time'] ?? '09:00' );
			$end_time   = sanitize_text_field( $schedule['end_time'] ?? '10:00' );

			// Get next occurrence of this day.
			$next_date       = date( 'Y-m-d', strtotime( "next {$day}" ) );
			$start_datetime  = $next_date . ' ' . $start_time . ':00';
			$end_datetime    = $next_date . ' ' . $end_time . ':00';

			$data = array(
				'class_id'       => $post_id,
				'trainer_id'     => absint( $schedule['trainer_id'] ?? 0 ),
				'start_datetime' => $start_datetime,
				'end_datetime'   => $end_datetime,
				'location'       => sanitize_text_field( $schedule['location'] ?? '' ),
				'max_capacity'   => absint( $schedule['max_capacity'] ?? $max_capacity ),
				'is_recurring'   => 1,
				'recurrence_day' => $day,
			);

			if ( $schedule_id && in_array( $schedule_id, $existing_ids, true ) ) {
				// Update existing.
				$wpdb->update(
					$schedules_table,
					$data,
					array( 'id' => $schedule_id ),
					array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s' ),
					array( '%d' )
				);
				$submitted_ids[] = $schedule_id;
			} else {
				// Insert new.
				$wpdb->insert(
					$schedules_table,
					$data,
					array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s' )
				);
			}
		}

		// Delete removed schedules.
		$to_delete = array_diff( $existing_ids, $submitted_ids );
		if ( ! empty( $to_delete ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $to_delete ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$schedules_table} WHERE id IN ({$placeholders})",
					$to_delete
				)
			);
		}
	}
}

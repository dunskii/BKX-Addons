<?php
/**
 * Seat Metabox
 *
 * @package BookingX\StaffBreaks
 * @since   1.0.0
 */

namespace BookingX\StaffBreaks\Admin;

use BookingX\StaffBreaks\StaffBreaksAddon;

/**
 * Class SeatMetabox
 *
 * Adds breaks management metabox to seat edit screen.
 *
 * @since 1.0.0
 */
class SeatMetabox {

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
	 * Add metaboxes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_metaboxes(): void {
		add_meta_box(
			'bkx_staff_breaks',
			__( 'Daily Breaks', 'bkx-staff-breaks' ),
			array( $this, 'render_breaks_metabox' ),
			'bkx_seat',
			'normal',
			'default'
		);
	}

	/**
	 * Render breaks metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_breaks_metabox( \WP_Post $post ): void {
		$breaks_service = $this->addon->get_breaks_service();
		$breaks         = $breaks_service->get_breaks( $post->ID );

		$days = array(
			'monday'    => __( 'Monday', 'bkx-staff-breaks' ),
			'tuesday'   => __( 'Tuesday', 'bkx-staff-breaks' ),
			'wednesday' => __( 'Wednesday', 'bkx-staff-breaks' ),
			'thursday'  => __( 'Thursday', 'bkx-staff-breaks' ),
			'friday'    => __( 'Friday', 'bkx-staff-breaks' ),
			'saturday'  => __( 'Saturday', 'bkx-staff-breaks' ),
			'sunday'    => __( 'Sunday', 'bkx-staff-breaks' ),
			'all'       => __( 'Every Day', 'bkx-staff-breaks' ),
		);

		wp_nonce_field( 'bkx_save_breaks', 'bkx_breaks_metabox_nonce' );
		?>
		<p class="description">
			<?php esc_html_e( 'Configure recurring daily breaks (e.g., lunch breaks) for this resource.', 'bkx-staff-breaks' ); ?>
		</p>

		<div class="bkx-breaks-list" data-seat-id="<?php echo esc_attr( $post->ID ); ?>">
			<?php if ( empty( $breaks ) ) : ?>
				<p class="bkx-no-breaks"><?php esc_html_e( 'No breaks configured.', 'bkx-staff-breaks' ); ?></p>
			<?php else : ?>
				<table class="widefat striped bkx-breaks-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Day', 'bkx-staff-breaks' ); ?></th>
							<th><?php esc_html_e( 'Start', 'bkx-staff-breaks' ); ?></th>
							<th><?php esc_html_e( 'End', 'bkx-staff-breaks' ); ?></th>
							<th><?php esc_html_e( 'Label', 'bkx-staff-breaks' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-staff-breaks' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $breaks as $break ) : ?>
							<tr data-break-id="<?php echo esc_attr( $break['id'] ); ?>">
								<td><?php echo esc_html( $days[ $break['day'] ] ?? $break['day'] ); ?></td>
								<td><?php echo esc_html( substr( $break['start_time'], 0, 5 ) ); ?></td>
								<td><?php echo esc_html( substr( $break['end_time'], 0, 5 ) ); ?></td>
								<td><?php echo esc_html( $break['label'] ); ?></td>
								<td>
									<button type="button" class="button button-small bkx-edit-break" data-id="<?php echo esc_attr( $break['id'] ); ?>">
										<?php esc_html_e( 'Edit', 'bkx-staff-breaks' ); ?>
									</button>
									<button type="button" class="button button-small bkx-delete-break" data-id="<?php echo esc_attr( $break['id'] ); ?>">
										<?php esc_html_e( 'Delete', 'bkx-staff-breaks' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<p>
			<button type="button" class="button" id="bkx-add-break">
				<?php esc_html_e( 'Add Break', 'bkx-staff-breaks' ); ?>
			</button>
		</p>

		<!-- Break Form Modal -->
		<div id="bkx-break-modal" class="bkx-modal" style="display:none;">
			<div class="bkx-modal-content">
				<h3><?php esc_html_e( 'Add/Edit Break', 'bkx-staff-breaks' ); ?></h3>
				<form id="bkx-break-form">
					<input type="hidden" name="break_id" id="break_id" value="0" />
					<input type="hidden" name="seat_id" value="<?php echo esc_attr( $post->ID ); ?>" />

					<table class="form-table">
						<tr>
							<th><label for="break_day"><?php esc_html_e( 'Day', 'bkx-staff-breaks' ); ?></label></th>
							<td>
								<select name="day" id="break_day" required>
									<?php foreach ( $days as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="break_start_time"><?php esc_html_e( 'Start Time', 'bkx-staff-breaks' ); ?></label></th>
							<td><input type="time" name="start_time" id="break_start_time" required /></td>
						</tr>
						<tr>
							<th><label for="break_end_time"><?php esc_html_e( 'End Time', 'bkx-staff-breaks' ); ?></label></th>
							<td><input type="time" name="end_time" id="break_end_time" required /></td>
						</tr>
						<tr>
							<th><label for="break_label"><?php esc_html_e( 'Label', 'bkx-staff-breaks' ); ?></label></th>
							<td>
								<input type="text" name="label" id="break_label" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Lunch Break', 'bkx-staff-breaks' ); ?>" />
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Break', 'bkx-staff-breaks' ); ?></button>
						<button type="button" class="button bkx-modal-close"><?php esc_html_e( 'Cancel', 'bkx-staff-breaks' ); ?></button>
					</p>
				</form>
			</div>
		</div>
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
		// Breaks are saved via AJAX, so this is mainly for nonce verification.
		if ( ! isset( $_POST['bkx_breaks_metabox_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_breaks_metabox_nonce'] ) ), 'bkx_save_breaks' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}
}

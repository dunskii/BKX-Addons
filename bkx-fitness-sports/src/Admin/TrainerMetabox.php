<?php
/**
 * Trainer Metabox
 *
 * Adds fitness trainer fields to seat (resource) posts.
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
 * Class TrainerMetabox
 *
 * @since 1.0.0
 */
class TrainerMetabox {

	/**
	 * Initialize metabox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post_bkx_seat', array( $this, 'save_metabox' ), 10, 2 );
	}

	/**
	 * Add metabox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_metabox(): void {
		add_meta_box(
			'bkx_trainer_details',
			__( 'Trainer Profile', 'bkx-fitness-sports' ),
			array( $this, 'render_metabox' ),
			'bkx_seat',
			'normal',
			'default'
		);
	}

	/**
	 * Render trainer metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'bkx_trainer_details', 'bkx_trainer_details_nonce' );

		$is_trainer      = get_post_meta( $post->ID, '_bkx_is_trainer', true );
		$certifications  = get_post_meta( $post->ID, '_bkx_trainer_certifications', true ) ?: array();
		$experience      = get_post_meta( $post->ID, '_bkx_trainer_experience', true );
		$hourly_rate     = get_post_meta( $post->ID, '_bkx_trainer_hourly_rate', true );
		$availability    = get_post_meta( $post->ID, '_bkx_trainer_availability', true ) ?: array();
		$instagram       = get_post_meta( $post->ID, '_bkx_trainer_instagram', true );
		$booking_lead_time = get_post_meta( $post->ID, '_bkx_booking_lead_time', true ) ?: 24;
		?>
		<table class="form-table">
			<tr>
				<th><label><?php esc_html_e( 'Is Trainer', 'bkx-fitness-sports' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_is_trainer" value="1" <?php checked( $is_trainer, '1' ); ?>>
						<?php esc_html_e( 'This person is a fitness trainer', 'bkx-fitness-sports' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<div class="bkx-trainer-fields" <?php echo $is_trainer ? '' : 'style="display:none;"'; ?>>
			<table class="form-table">
				<tr>
					<th><label for="bkx_trainer_experience"><?php esc_html_e( 'Years of Experience', 'bkx-fitness-sports' ); ?></label></th>
					<td>
						<input type="number" name="bkx_trainer_experience" id="bkx_trainer_experience" value="<?php echo esc_attr( $experience ); ?>" min="0" class="small-text">
					</td>
				</tr>
				<tr>
					<th><label for="bkx_trainer_certifications"><?php esc_html_e( 'Certifications', 'bkx-fitness-sports' ); ?></label></th>
					<td>
						<textarea name="bkx_trainer_certifications" id="bkx_trainer_certifications" rows="4" class="large-text"><?php echo esc_textarea( implode( "\n", $certifications ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One certification per line (e.g., ACE Certified, NASM-CPT)', 'bkx-fitness-sports' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="bkx_trainer_hourly_rate"><?php esc_html_e( 'Hourly Rate', 'bkx-fitness-sports' ); ?></label></th>
					<td>
						<input type="number" name="bkx_trainer_hourly_rate" id="bkx_trainer_hourly_rate" value="<?php echo esc_attr( $hourly_rate ); ?>" min="0" step="0.01" class="small-text">
						<span class="description"><?php esc_html_e( 'for personal training sessions', 'bkx-fitness-sports' ); ?></span>
					</td>
				</tr>
				<tr>
					<th><label for="bkx_booking_lead_time"><?php esc_html_e( 'Booking Lead Time', 'bkx-fitness-sports' ); ?></label></th>
					<td>
						<input type="number" name="bkx_booking_lead_time" id="bkx_booking_lead_time" value="<?php echo esc_attr( $booking_lead_time ); ?>" min="0" class="small-text">
						<span class="description"><?php esc_html_e( 'hours in advance required to book', 'bkx-fitness-sports' ); ?></span>
					</td>
				</tr>
				<tr>
					<th><label for="bkx_trainer_instagram"><?php esc_html_e( 'Instagram Handle', 'bkx-fitness-sports' ); ?></label></th>
					<td>
						<input type="text" name="bkx_trainer_instagram" id="bkx_trainer_instagram" value="<?php echo esc_attr( $instagram ); ?>" class="regular-text" placeholder="@username">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Weekly Availability', 'bkx-fitness-sports' ); ?></th>
					<td>
						<table class="bkx-availability-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Day', 'bkx-fitness-sports' ); ?></th>
									<th><?php esc_html_e( 'Available', 'bkx-fitness-sports' ); ?></th>
									<th><?php esc_html_e( 'Start', 'bkx-fitness-sports' ); ?></th>
									<th><?php esc_html_e( 'End', 'bkx-fitness-sports' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$days = array(
									'monday'    => __( 'Monday', 'bkx-fitness-sports' ),
									'tuesday'   => __( 'Tuesday', 'bkx-fitness-sports' ),
									'wednesday' => __( 'Wednesday', 'bkx-fitness-sports' ),
									'thursday'  => __( 'Thursday', 'bkx-fitness-sports' ),
									'friday'    => __( 'Friday', 'bkx-fitness-sports' ),
									'saturday'  => __( 'Saturday', 'bkx-fitness-sports' ),
									'sunday'    => __( 'Sunday', 'bkx-fitness-sports' ),
								);

								foreach ( $days as $day_key => $day_label ) :
									$day_data = $availability[ $day_key ] ?? array();
									?>
									<tr>
										<td><strong><?php echo esc_html( $day_label ); ?></strong></td>
										<td>
											<input type="checkbox" name="bkx_availability[<?php echo esc_attr( $day_key ); ?>][enabled]" value="1" <?php checked( ! empty( $day_data['enabled'] ) ); ?>>
										</td>
										<td>
											<input type="time" name="bkx_availability[<?php echo esc_attr( $day_key ); ?>][start]" value="<?php echo esc_attr( $day_data['start'] ?? '06:00' ); ?>">
										</td>
										<td>
											<input type="time" name="bkx_availability[<?php echo esc_attr( $day_key ); ?>][end]" value="<?php echo esc_attr( $day_data['end'] ?? '21:00' ); ?>">
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</td>
				</tr>
			</table>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('input[name="bkx_is_trainer"]').on('change', function() {
				$('.bkx-trainer-fields').toggle($(this).is(':checked'));
			});
		});
		</script>

		<style>
			.bkx-availability-table { border-collapse: collapse; }
			.bkx-availability-table th, .bkx-availability-table td { padding: 8px; border: 1px solid #ddd; }
			.bkx-availability-table th { background: #f5f5f5; }
			.bkx-availability-table input[type="time"] { width: 110px; }
		</style>
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
		if ( ! isset( $_POST['bkx_trainer_details_nonce'] ) || ! wp_verify_nonce( $_POST['bkx_trainer_details_nonce'], 'bkx_trainer_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Is trainer.
		update_post_meta( $post_id, '_bkx_is_trainer', isset( $_POST['bkx_is_trainer'] ) ? '1' : '' );

		// Experience.
		update_post_meta( $post_id, '_bkx_trainer_experience', absint( $_POST['bkx_trainer_experience'] ?? 0 ) );

		// Certifications.
		if ( isset( $_POST['bkx_trainer_certifications'] ) ) {
			$certifications = array_filter( array_map( 'sanitize_text_field', explode( "\n", $_POST['bkx_trainer_certifications'] ) ) );
			update_post_meta( $post_id, '_bkx_trainer_certifications', $certifications );
		}

		// Hourly rate.
		update_post_meta( $post_id, '_bkx_trainer_hourly_rate', floatval( $_POST['bkx_trainer_hourly_rate'] ?? 0 ) );

		// Booking lead time.
		update_post_meta( $post_id, '_bkx_booking_lead_time', absint( $_POST['bkx_booking_lead_time'] ?? 24 ) );

		// Instagram.
		update_post_meta( $post_id, '_bkx_trainer_instagram', sanitize_text_field( $_POST['bkx_trainer_instagram'] ?? '' ) );

		// Availability.
		if ( isset( $_POST['bkx_availability'] ) && is_array( $_POST['bkx_availability'] ) ) {
			$availability = array();

			foreach ( $_POST['bkx_availability'] as $day => $data ) {
				$availability[ sanitize_key( $day ) ] = array(
					'enabled' => ! empty( $data['enabled'] ),
					'start'   => sanitize_text_field( $data['start'] ?? '06:00' ),
					'end'     => sanitize_text_field( $data['end'] ?? '21:00' ),
				);
			}

			update_post_meta( $post_id, '_bkx_trainer_availability', $availability );
		}
	}
}

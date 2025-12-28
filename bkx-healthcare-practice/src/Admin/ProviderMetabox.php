<?php
/**
 * Provider Metabox.
 *
 * Handles provider credentials and availability metaboxes.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

namespace BookingX\HealthcarePractice\Admin;

/**
 * Provider Metabox class.
 *
 * @since 1.0.0
 */
class ProviderMetabox {

	/**
	 * Render credentials metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public static function render_credentials( \WP_Post $post ): void {
		wp_nonce_field( 'bkx_healthcare_save_meta', 'bkx_healthcare_nonce' );

		$credentials = get_post_meta( $post->ID, '_bkx_provider_credentials', true ) ?: array();
		$npi         = get_post_meta( $post->ID, '_bkx_provider_npi', true );
		$license     = get_post_meta( $post->ID, '_bkx_provider_license', true );
		$specialties = wp_get_object_terms( $post->ID, 'bkx_medical_specialty', array( 'fields' => 'ids' ) );
		$linked_user = get_post_meta( $post->ID, '_bkx_linked_user', true );
		?>
		<div class="bkx-provider-credentials-metabox">
			<p>
				<label for="bkx_provider_npi">
					<strong><?php esc_html_e( 'NPI Number', 'bkx-healthcare-practice' ); ?></strong>
				</label>
				<br>
				<input type="text" id="bkx_provider_npi" name="bkx_provider_npi"
					   value="<?php echo esc_attr( $npi ); ?>" class="widefat"
					   placeholder="<?php esc_attr_e( '10-digit NPI', 'bkx-healthcare-practice' ); ?>">
			</p>

			<p>
				<label for="bkx_provider_license">
					<strong><?php esc_html_e( 'License Number', 'bkx-healthcare-practice' ); ?></strong>
				</label>
				<br>
				<input type="text" id="bkx_provider_license" name="bkx_provider_license"
					   value="<?php echo esc_attr( $license ); ?>" class="widefat">
			</p>

			<p>
				<label for="bkx_linked_user">
					<strong><?php esc_html_e( 'Linked WordPress User', 'bkx-healthcare-practice' ); ?></strong>
				</label>
				<br>
				<?php
				wp_dropdown_users( array(
					'name'              => 'bkx_linked_user',
					'id'                => 'bkx_linked_user',
					'selected'          => $linked_user,
					'show_option_none'  => __( 'Select a user', 'bkx-healthcare-practice' ),
					'option_none_value' => '',
					'class'             => 'widefat',
				) );
				?>
				<span class="description">
					<?php esc_html_e( 'Link this provider to a WordPress user account for login access.', 'bkx-healthcare-practice' ); ?>
				</span>
			</p>

			<h4><?php esc_html_e( 'Additional Credentials', 'bkx-healthcare-practice' ); ?></h4>

			<div class="bkx-credentials-list" id="bkx-credentials-list">
				<?php
				if ( ! empty( $credentials ) ) :
					foreach ( $credentials as $index => $cred ) :
						?>
						<div class="bkx-credential-item">
							<input type="text" name="bkx_credentials[<?php echo esc_attr( $index ); ?>][type]"
								   value="<?php echo esc_attr( $cred['type'] ?? '' ); ?>"
								   placeholder="<?php esc_attr_e( 'Credential Type (e.g., Board Certification)', 'bkx-healthcare-practice' ); ?>"
								   class="widefat">
							<input type="text" name="bkx_credentials[<?php echo esc_attr( $index ); ?>][value]"
								   value="<?php echo esc_attr( $cred['value'] ?? '' ); ?>"
								   placeholder="<?php esc_attr_e( 'Credential Value', 'bkx-healthcare-practice' ); ?>"
								   class="widefat">
							<input type="date" name="bkx_credentials[<?php echo esc_attr( $index ); ?>][expiry]"
								   value="<?php echo esc_attr( $cred['expiry'] ?? '' ); ?>">
							<button type="button" class="button bkx-remove-credential">&times;</button>
						</div>
						<?php
					endforeach;
				endif;
				?>
			</div>

			<button type="button" class="button bkx-add-credential">
				<?php esc_html_e( '+ Add Credential', 'bkx-healthcare-practice' ); ?>
			</button>

			<script type="text/template" id="bkx-credential-template">
				<div class="bkx-credential-item">
					<input type="text" name="bkx_credentials[{{index}}][type]"
						   placeholder="<?php esc_attr_e( 'Credential Type', 'bkx-healthcare-practice' ); ?>"
						   class="widefat">
					<input type="text" name="bkx_credentials[{{index}}][value]"
						   placeholder="<?php esc_attr_e( 'Credential Value', 'bkx-healthcare-practice' ); ?>"
						   class="widefat">
					<input type="date" name="bkx_credentials[{{index}}][expiry]">
					<button type="button" class="button bkx-remove-credential">&times;</button>
				</div>
			</script>
		</div>
		<?php
	}

	/**
	 * Render availability metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public static function render_availability( \WP_Post $post ): void {
		$availability        = get_post_meta( $post->ID, '_bkx_provider_availability', true ) ?: array();
		$appointment_duration = get_post_meta( $post->ID, '_bkx_appointment_duration', true ) ?: 30;
		$buffer_time         = get_post_meta( $post->ID, '_bkx_buffer_time', true ) ?: 0;
		$telemedicine        = get_post_meta( $post->ID, '_bkx_offers_telemedicine', true );

		$days = array(
			'monday'    => __( 'Monday', 'bkx-healthcare-practice' ),
			'tuesday'   => __( 'Tuesday', 'bkx-healthcare-practice' ),
			'wednesday' => __( 'Wednesday', 'bkx-healthcare-practice' ),
			'thursday'  => __( 'Thursday', 'bkx-healthcare-practice' ),
			'friday'    => __( 'Friday', 'bkx-healthcare-practice' ),
			'saturday'  => __( 'Saturday', 'bkx-healthcare-practice' ),
			'sunday'    => __( 'Sunday', 'bkx-healthcare-practice' ),
		);
		?>
		<div class="bkx-provider-availability-metabox">
			<p>
				<label>
					<strong><?php esc_html_e( 'Default Appointment Duration', 'bkx-healthcare-practice' ); ?></strong>
				</label>
				<br>
				<select name="bkx_appointment_duration">
					<?php
					$durations = array( 15, 20, 30, 45, 60, 90, 120 );
					foreach ( $durations as $dur ) :
						?>
						<option value="<?php echo esc_attr( $dur ); ?>" <?php selected( $appointment_duration, $dur ); ?>>
							<?php
							printf(
								/* translators: %d: Number of minutes */
								esc_html__( '%d minutes', 'bkx-healthcare-practice' ),
								$dur
							);
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label>
					<strong><?php esc_html_e( 'Buffer Time Between Appointments', 'bkx-healthcare-practice' ); ?></strong>
				</label>
				<br>
				<select name="bkx_buffer_time">
					<?php
					$buffers = array( 0, 5, 10, 15, 30 );
					foreach ( $buffers as $buf ) :
						?>
						<option value="<?php echo esc_attr( $buf ); ?>" <?php selected( $buffer_time, $buf ); ?>>
							<?php
							if ( 0 === $buf ) {
								esc_html_e( 'No buffer', 'bkx-healthcare-practice' );
							} else {
								printf(
									/* translators: %d: Number of minutes */
									esc_html__( '%d minutes', 'bkx-healthcare-practice' ),
									$buf
								);
							}
							?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label>
					<input type="checkbox" name="bkx_offers_telemedicine" value="1"
						   <?php checked( $telemedicine ); ?>>
					<?php esc_html_e( 'This provider offers telemedicine appointments', 'bkx-healthcare-practice' ); ?>
				</label>
			</p>

			<h4><?php esc_html_e( 'Weekly Availability', 'bkx-healthcare-practice' ); ?></h4>

			<table class="bkx-availability-table widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Day', 'bkx-healthcare-practice' ); ?></th>
						<th><?php esc_html_e( 'Available', 'bkx-healthcare-practice' ); ?></th>
						<th><?php esc_html_e( 'Start Time', 'bkx-healthcare-practice' ); ?></th>
						<th><?php esc_html_e( 'End Time', 'bkx-healthcare-practice' ); ?></th>
						<th><?php esc_html_e( 'Break Start', 'bkx-healthcare-practice' ); ?></th>
						<th><?php esc_html_e( 'Break End', 'bkx-healthcare-practice' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $days as $day_key => $day_label ) : ?>
						<?php
						$day_avail = $availability[ $day_key ] ?? array(
							'enabled'     => false,
							'start'       => '09:00',
							'end'         => '17:00',
							'break_start' => '',
							'break_end'   => '',
						);
						?>
						<tr>
							<td><?php echo esc_html( $day_label ); ?></td>
							<td>
								<input type="checkbox" name="bkx_availability[<?php echo esc_attr( $day_key ); ?>][enabled]"
									   value="1" <?php checked( ! empty( $day_avail['enabled'] ) ); ?>>
							</td>
							<td>
								<input type="time" name="bkx_availability[<?php echo esc_attr( $day_key ); ?>][start]"
									   value="<?php echo esc_attr( $day_avail['start'] ); ?>">
							</td>
							<td>
								<input type="time" name="bkx_availability[<?php echo esc_attr( $day_key ); ?>][end]"
									   value="<?php echo esc_attr( $day_avail['end'] ); ?>">
							</td>
							<td>
								<input type="time" name="bkx_availability[<?php echo esc_attr( $day_key ); ?>][break_start]"
									   value="<?php echo esc_attr( $day_avail['break_start'] ?? '' ); ?>">
							</td>
							<td>
								<input type="time" name="bkx_availability[<?php echo esc_attr( $day_key ); ?>][break_end]"
									   value="<?php echo esc_attr( $day_avail['break_end'] ?? '' ); ?>">
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Save metabox data.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save( int $post_id ): void {
		// Save NPI.
		if ( isset( $_POST['bkx_provider_npi'] ) ) {
			$npi = sanitize_text_field( wp_unslash( $_POST['bkx_provider_npi'] ) );
			// Validate NPI format (10 digits).
			if ( preg_match( '/^\d{10}$/', $npi ) || empty( $npi ) ) {
				update_post_meta( $post_id, '_bkx_provider_npi', $npi );
			}
		}

		// Save license.
		if ( isset( $_POST['bkx_provider_license'] ) ) {
			update_post_meta( $post_id, '_bkx_provider_license', sanitize_text_field( wp_unslash( $_POST['bkx_provider_license'] ) ) );
		}

		// Save linked user.
		if ( isset( $_POST['bkx_linked_user'] ) ) {
			update_post_meta( $post_id, '_bkx_linked_user', absint( $_POST['bkx_linked_user'] ) );
		}

		// Save credentials.
		if ( isset( $_POST['bkx_credentials'] ) ) {
			$credentials = array();
			foreach ( wp_unslash( $_POST['bkx_credentials'] ) as $cred ) {
				if ( ! empty( $cred['type'] ) || ! empty( $cred['value'] ) ) {
					$credentials[] = array(
						'type'   => sanitize_text_field( $cred['type'] ?? '' ),
						'value'  => sanitize_text_field( $cred['value'] ?? '' ),
						'expiry' => sanitize_text_field( $cred['expiry'] ?? '' ),
					);
				}
			}
			update_post_meta( $post_id, '_bkx_provider_credentials', $credentials );
		}

		// Save availability.
		if ( isset( $_POST['bkx_availability'] ) ) {
			$availability = array();
			foreach ( wp_unslash( $_POST['bkx_availability'] ) as $day => $avail ) {
				$availability[ sanitize_key( $day ) ] = array(
					'enabled'     => isset( $avail['enabled'] ) ? 1 : 0,
					'start'       => sanitize_text_field( $avail['start'] ?? '09:00' ),
					'end'         => sanitize_text_field( $avail['end'] ?? '17:00' ),
					'break_start' => sanitize_text_field( $avail['break_start'] ?? '' ),
					'break_end'   => sanitize_text_field( $avail['break_end'] ?? '' ),
				);
			}
			update_post_meta( $post_id, '_bkx_provider_availability', $availability );
		}

		// Save appointment settings.
		if ( isset( $_POST['bkx_appointment_duration'] ) ) {
			update_post_meta( $post_id, '_bkx_appointment_duration', absint( $_POST['bkx_appointment_duration'] ) );
		}

		if ( isset( $_POST['bkx_buffer_time'] ) ) {
			update_post_meta( $post_id, '_bkx_buffer_time', absint( $_POST['bkx_buffer_time'] ) );
		}

		// Save telemedicine option.
		update_post_meta( $post_id, '_bkx_offers_telemedicine', isset( $_POST['bkx_offers_telemedicine'] ) ? 1 : 0 );
	}
}

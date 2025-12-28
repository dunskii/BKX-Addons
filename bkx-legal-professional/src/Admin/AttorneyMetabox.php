<?php
/**
 * Attorney Metabox for Legal & Professional Services.
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

namespace BookingX\LegalProfessional\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attorney Metabox class.
 *
 * @since 1.0.0
 */
class AttorneyMetabox {

	/**
	 * Initialize the metabox.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post_bkx_attorney', array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Add metaboxes.
	 *
	 * @return void
	 */
	public function add_metaboxes(): void {
		add_meta_box(
			'bkx_attorney_profile',
			__( 'Attorney Profile', 'bkx-legal-professional' ),
			array( $this, 'render_profile_metabox' ),
			'bkx_attorney',
			'normal',
			'high'
		);

		add_meta_box(
			'bkx_attorney_credentials',
			__( 'Credentials & Admissions', 'bkx-legal-professional' ),
			array( $this, 'render_credentials_metabox' ),
			'bkx_attorney',
			'normal',
			'default'
		);

		add_meta_box(
			'bkx_attorney_billing',
			__( 'Billing Information', 'bkx-legal-professional' ),
			array( $this, 'render_billing_metabox' ),
			'bkx_attorney',
			'side',
			'default'
		);
	}

	/**
	 * Render profile metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_profile_metabox( $post ): void {
		wp_nonce_field( 'bkx_attorney_meta', 'bkx_attorney_nonce' );

		$user_id       = get_post_meta( $post->ID, '_bkx_user_id', true );
		$title         = get_post_meta( $post->ID, '_bkx_title', true );
		$email         = get_post_meta( $post->ID, '_bkx_email', true );
		$phone         = get_post_meta( $post->ID, '_bkx_phone', true );
		$bio           = get_post_meta( $post->ID, '_bkx_bio', true );
		$practice_areas = get_post_meta( $post->ID, '_bkx_practice_areas', true ) ?: array();

		$users = get_users( array(
			'role__in' => array( 'administrator', 'editor', 'author' ),
			'orderby'  => 'display_name',
		) );

		$areas = get_terms( array(
			'taxonomy'   => 'bkx_practice_area',
			'hide_empty' => false,
		) );
		?>
		<table class="form-table">
			<tr>
				<th><label for="bkx_user_id"><?php esc_html_e( 'Linked User Account', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<select id="bkx_user_id" name="bkx_user_id" class="regular-text">
						<option value=""><?php esc_html_e( '— Select User —', 'bkx-legal-professional' ); ?></option>
						<?php foreach ( $users as $user ) : ?>
							<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $user_id, $user->ID ); ?>>
								<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Link this profile to a WordPress user for login access.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_title"><?php esc_html_e( 'Title', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<select id="bkx_title" name="bkx_title" class="regular-text">
						<option value=""><?php esc_html_e( '— Select —', 'bkx-legal-professional' ); ?></option>
						<option value="Partner" <?php selected( $title, 'Partner' ); ?>><?php esc_html_e( 'Partner', 'bkx-legal-professional' ); ?></option>
						<option value="Senior Partner" <?php selected( $title, 'Senior Partner' ); ?>><?php esc_html_e( 'Senior Partner', 'bkx-legal-professional' ); ?></option>
						<option value="Managing Partner" <?php selected( $title, 'Managing Partner' ); ?>><?php esc_html_e( 'Managing Partner', 'bkx-legal-professional' ); ?></option>
						<option value="Associate" <?php selected( $title, 'Associate' ); ?>><?php esc_html_e( 'Associate', 'bkx-legal-professional' ); ?></option>
						<option value="Senior Associate" <?php selected( $title, 'Senior Associate' ); ?>><?php esc_html_e( 'Senior Associate', 'bkx-legal-professional' ); ?></option>
						<option value="Of Counsel" <?php selected( $title, 'Of Counsel' ); ?>><?php esc_html_e( 'Of Counsel', 'bkx-legal-professional' ); ?></option>
						<option value="Counsel" <?php selected( $title, 'Counsel' ); ?>><?php esc_html_e( 'Counsel', 'bkx-legal-professional' ); ?></option>
						<option value="Paralegal" <?php selected( $title, 'Paralegal' ); ?>><?php esc_html_e( 'Paralegal', 'bkx-legal-professional' ); ?></option>
						<option value="Legal Assistant" <?php selected( $title, 'Legal Assistant' ); ?>><?php esc_html_e( 'Legal Assistant', 'bkx-legal-professional' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_email"><?php esc_html_e( 'Email', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<input type="email" id="bkx_email" name="bkx_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th><label for="bkx_phone"><?php esc_html_e( 'Phone', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<input type="tel" id="bkx_phone" name="bkx_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th><label for="bkx_practice_areas"><?php esc_html_e( 'Practice Areas', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<?php if ( ! empty( $areas ) && ! is_wp_error( $areas ) ) : ?>
						<fieldset>
							<?php foreach ( $areas as $area ) : ?>
								<label>
									<input type="checkbox" name="bkx_practice_areas[]" value="<?php echo esc_attr( $area->term_id ); ?>" <?php checked( in_array( $area->term_id, $practice_areas, true ) ); ?>>
									<?php echo esc_html( $area->name ); ?>
								</label><br>
							<?php endforeach; ?>
						</fieldset>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No practice areas defined. Add practice areas in the taxonomy menu.', 'bkx-legal-professional' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_bio"><?php esc_html_e( 'Biography', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<?php
					wp_editor(
						$bio,
						'bkx_bio',
						array(
							'textarea_name' => 'bkx_bio',
							'textarea_rows' => 8,
							'media_buttons' => true,
						)
					);
					?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render credentials metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_credentials_metabox( $post ): void {
		$bar_number     = get_post_meta( $post->ID, '_bkx_bar_number', true );
		$bar_state      = get_post_meta( $post->ID, '_bkx_bar_state', true );
		$admissions     = get_post_meta( $post->ID, '_bkx_admissions', true ) ?: array();
		$certifications = get_post_meta( $post->ID, '_bkx_certifications', true );
		$education      = get_post_meta( $post->ID, '_bkx_education', true );
		?>
		<table class="form-table">
			<tr>
				<th><label for="bkx_bar_number"><?php esc_html_e( 'Primary Bar Number', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<input type="text" id="bkx_bar_number" name="bkx_bar_number" value="<?php echo esc_attr( $bar_number ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th><label for="bkx_bar_state"><?php esc_html_e( 'Primary Bar State', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<input type="text" id="bkx_bar_state" name="bkx_bar_state" value="<?php echo esc_attr( $bar_state ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., California, New York', 'bkx-legal-professional' ); ?>">
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Additional Bar Admissions', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<div id="bkx-admissions-list">
						<?php
						if ( ! empty( $admissions ) ) {
							foreach ( $admissions as $index => $admission ) {
								$this->render_admission_row( $index, $admission );
							}
						} else {
							$this->render_admission_row( 0 );
						}
						?>
					</div>
					<button type="button" class="button" id="bkx-add-admission"><?php esc_html_e( 'Add Admission', 'bkx-legal-professional' ); ?></button>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_certifications"><?php esc_html_e( 'Certifications', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<textarea id="bkx_certifications" name="bkx_certifications" rows="4" class="large-text"><?php echo esc_textarea( $certifications ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Board certifications, specializations, etc. One per line.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_education"><?php esc_html_e( 'Education', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<textarea id="bkx_education" name="bkx_education" rows="4" class="large-text"><?php echo esc_textarea( $education ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Law school, undergraduate, etc. One per line.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render admission row.
	 *
	 * @param int   $index     Row index.
	 * @param array $admission Admission data.
	 * @return void
	 */
	private function render_admission_row( int $index, array $admission = array() ): void {
		$state  = $admission['state'] ?? '';
		$number = $admission['number'] ?? '';
		$year   = $admission['year'] ?? '';
		?>
		<div class="bkx-admission-row" style="margin-bottom: 10px;">
			<input type="text" name="bkx_admissions[<?php echo esc_attr( $index ); ?>][state]" value="<?php echo esc_attr( $state ); ?>" placeholder="<?php esc_attr_e( 'State', 'bkx-legal-professional' ); ?>" style="width: 120px;">
			<input type="text" name="bkx_admissions[<?php echo esc_attr( $index ); ?>][number]" value="<?php echo esc_attr( $number ); ?>" placeholder="<?php esc_attr_e( 'Bar Number', 'bkx-legal-professional' ); ?>" style="width: 120px;">
			<input type="text" name="bkx_admissions[<?php echo esc_attr( $index ); ?>][year]" value="<?php echo esc_attr( $year ); ?>" placeholder="<?php esc_attr_e( 'Year', 'bkx-legal-professional' ); ?>" style="width: 80px;">
			<button type="button" class="button bkx-remove-admission">&times;</button>
		</div>
		<?php
	}

	/**
	 * Render billing metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_billing_metabox( $post ): void {
		$hourly_rate    = get_post_meta( $post->ID, '_bkx_hourly_rate', true );
		$billable       = get_post_meta( $post->ID, '_bkx_billable', true );
		$target_hours   = get_post_meta( $post->ID, '_bkx_target_hours', true );
		?>
		<p>
			<label for="bkx_hourly_rate"><strong><?php esc_html_e( 'Hourly Rate', 'bkx-legal-professional' ); ?></strong></label><br>
			<input type="number" id="bkx_hourly_rate" name="bkx_hourly_rate" value="<?php echo esc_attr( $hourly_rate ); ?>" min="0" step="0.01" class="widefat">
		</p>
		<p>
			<label>
				<input type="checkbox" name="bkx_billable" value="1" <?php checked( $billable, '1' ); ?>>
				<?php esc_html_e( 'Time is billable', 'bkx-legal-professional' ); ?>
			</label>
		</p>
		<p>
			<label for="bkx_target_hours"><strong><?php esc_html_e( 'Monthly Target Hours', 'bkx-legal-professional' ); ?></strong></label><br>
			<input type="number" id="bkx_target_hours" name="bkx_target_hours" value="<?php echo esc_attr( $target_hours ); ?>" min="0" class="widefat">
		</p>
		<?php
	}

	/**
	 * Save meta data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta( int $post_id, $post ): void {
		if ( ! isset( $_POST['bkx_attorney_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_attorney_nonce'] ) ), 'bkx_attorney_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Text fields.
		$text_fields = array( 'user_id', 'title', 'email', 'phone', 'bar_number', 'bar_state', 'certifications', 'education' );
		foreach ( $text_fields as $field ) {
			$key = 'bkx_' . $field;
			if ( isset( $_POST[ $key ] ) ) {
				$value = 'email' === $field
					? sanitize_email( wp_unslash( $_POST[ $key ] ) )
					: sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				update_post_meta( $post_id, '_bkx_' . $field, $value );
			}
		}

		// Bio (allows HTML).
		if ( isset( $_POST['bkx_bio'] ) ) {
			update_post_meta( $post_id, '_bkx_bio', wp_kses_post( wp_unslash( $_POST['bkx_bio'] ) ) );
		}

		// Practice areas (array of term IDs).
		if ( isset( $_POST['bkx_practice_areas'] ) && is_array( $_POST['bkx_practice_areas'] ) ) {
			$areas = array_map( 'absint', $_POST['bkx_practice_areas'] );
			update_post_meta( $post_id, '_bkx_practice_areas', $areas );
		} else {
			update_post_meta( $post_id, '_bkx_practice_areas', array() );
		}

		// Admissions (array of arrays).
		if ( isset( $_POST['bkx_admissions'] ) && is_array( $_POST['bkx_admissions'] ) ) {
			$admissions = array();
			foreach ( $_POST['bkx_admissions'] as $admission ) {
				if ( ! empty( $admission['state'] ) || ! empty( $admission['number'] ) ) {
					$admissions[] = array(
						'state'  => sanitize_text_field( $admission['state'] ?? '' ),
						'number' => sanitize_text_field( $admission['number'] ?? '' ),
						'year'   => sanitize_text_field( $admission['year'] ?? '' ),
					);
				}
			}
			update_post_meta( $post_id, '_bkx_admissions', $admissions );
		}

		// Billing.
		if ( isset( $_POST['bkx_hourly_rate'] ) ) {
			update_post_meta( $post_id, '_bkx_hourly_rate', floatval( $_POST['bkx_hourly_rate'] ) );
		}

		$billable = isset( $_POST['bkx_billable'] ) ? '1' : '0';
		update_post_meta( $post_id, '_bkx_billable', $billable );

		if ( isset( $_POST['bkx_target_hours'] ) ) {
			update_post_meta( $post_id, '_bkx_target_hours', absint( $_POST['bkx_target_hours'] ) );
		}
	}
}

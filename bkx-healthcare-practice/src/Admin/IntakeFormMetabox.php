<?php
/**
 * Intake Form Builder Metabox.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

namespace BookingX\HealthcarePractice\Admin;

/**
 * Intake Form Metabox class.
 *
 * @since 1.0.0
 */
class IntakeFormMetabox {

	/**
	 * Render the intake form builder metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public static function render( \WP_Post $post ): void {
		wp_nonce_field( 'bkx_healthcare_save_meta', 'bkx_healthcare_nonce' );

		$fields = get_post_meta( $post->ID, '_bkx_intake_fields', true ) ?: array();

		$field_types = array(
			'text'        => __( 'Text', 'bkx-healthcare-practice' ),
			'email'       => __( 'Email', 'bkx-healthcare-practice' ),
			'tel'         => __( 'Phone', 'bkx-healthcare-practice' ),
			'date'        => __( 'Date', 'bkx-healthcare-practice' ),
			'textarea'    => __( 'Text Area', 'bkx-healthcare-practice' ),
			'select'      => __( 'Dropdown', 'bkx-healthcare-practice' ),
			'radio'       => __( 'Radio Buttons', 'bkx-healthcare-practice' ),
			'checkbox'    => __( 'Checkbox', 'bkx-healthcare-practice' ),
			'checkboxes'  => __( 'Checkbox Group', 'bkx-healthcare-practice' ),
			'medications' => __( 'Medications List', 'bkx-healthcare-practice' ),
			'allergies'   => __( 'Allergies List', 'bkx-healthcare-practice' ),
		);
		?>
		<div class="bkx-intake-form-builder">
			<div class="bkx-form-fields-list" id="bkx-form-fields-list">
				<?php
				if ( ! empty( $fields ) ) {
					foreach ( $fields as $index => $field ) {
						self::render_field_row( $index, $field, $field_types );
					}
				}
				?>
			</div>

			<div class="bkx-add-field-section">
				<button type="button" class="button button-primary" id="bkx-add-field">
					<?php esc_html_e( '+ Add Field', 'bkx-healthcare-practice' ); ?>
				</button>
			</div>

			<script type="text/template" id="bkx-field-row-template">
				<?php self::render_field_row( '{{index}}', array(), $field_types ); ?>
			</script>

			<hr>

			<h4><?php esc_html_e( 'Shortcode', 'bkx-healthcare-practice' ); ?></h4>
			<p>
				<code>[bkx_patient_intake form_id="<?php echo esc_attr( $post->ID ); ?>"]</code>
			</p>

			<h4><?php esc_html_e( 'Common Templates', 'bkx-healthcare-practice' ); ?></h4>
			<p>
				<button type="button" class="button bkx-load-template" data-template="general">
					<?php esc_html_e( 'General Medical', 'bkx-healthcare-practice' ); ?>
				</button>
				<button type="button" class="button bkx-load-template" data-template="dental">
					<?php esc_html_e( 'Dental', 'bkx-healthcare-practice' ); ?>
				</button>
				<button type="button" class="button bkx-load-template" data-template="mental_health">
					<?php esc_html_e( 'Mental Health', 'bkx-healthcare-practice' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * Render a single field row.
	 *
	 * @since 1.0.0
	 * @param int|string $index       Field index.
	 * @param array      $field       Field data.
	 * @param array      $field_types Available field types.
	 * @return void
	 */
	private static function render_field_row( $index, array $field, array $field_types ): void {
		$name     = $field['name'] ?? '';
		$label    = $field['label'] ?? '';
		$type     = $field['type'] ?? 'text';
		$required = ! empty( $field['required'] );
		$section  = $field['section'] ?? '';
		$options  = $field['options'] ?? '';
		$help     = $field['help'] ?? '';
		?>
		<div class="bkx-field-row" data-index="<?php echo esc_attr( $index ); ?>">
			<div class="bkx-field-row-header">
				<span class="bkx-field-drag-handle dashicons dashicons-move"></span>
				<span class="bkx-field-label-preview"><?php echo esc_html( $label ?: __( 'New Field', 'bkx-healthcare-practice' ) ); ?></span>
				<span class="bkx-field-type-preview"><?php echo esc_html( $field_types[ $type ] ?? $type ); ?></span>
				<button type="button" class="bkx-toggle-field-settings">
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</button>
				<button type="button" class="bkx-remove-field">&times;</button>
			</div>

			<div class="bkx-field-row-settings" style="display: none;">
				<div class="bkx-field-setting">
					<label><?php esc_html_e( 'Field Name (unique ID)', 'bkx-healthcare-practice' ); ?></label>
					<input type="text" name="bkx_intake_fields[<?php echo esc_attr( $index ); ?>][name]"
						   value="<?php echo esc_attr( $name ); ?>" class="widefat bkx-field-name">
				</div>

				<div class="bkx-field-setting">
					<label><?php esc_html_e( 'Label', 'bkx-healthcare-practice' ); ?></label>
					<input type="text" name="bkx_intake_fields[<?php echo esc_attr( $index ); ?>][label]"
						   value="<?php echo esc_attr( $label ); ?>" class="widefat bkx-field-label-input">
				</div>

				<div class="bkx-field-setting">
					<label><?php esc_html_e( 'Field Type', 'bkx-healthcare-practice' ); ?></label>
					<select name="bkx_intake_fields[<?php echo esc_attr( $index ); ?>][type]" class="widefat bkx-field-type-select">
						<?php foreach ( $field_types as $type_val => $type_label ) : ?>
							<option value="<?php echo esc_attr( $type_val ); ?>" <?php selected( $type, $type_val ); ?>>
								<?php echo esc_html( $type_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="bkx-field-setting">
					<label><?php esc_html_e( 'Section Header', 'bkx-healthcare-practice' ); ?></label>
					<input type="text" name="bkx_intake_fields[<?php echo esc_attr( $index ); ?>][section]"
						   value="<?php echo esc_attr( $section ); ?>" class="widefat"
						   placeholder="<?php esc_attr_e( 'e.g., Personal Information, Medical History', 'bkx-healthcare-practice' ); ?>">
				</div>

				<div class="bkx-field-setting bkx-options-setting" style="<?php echo in_array( $type, array( 'select', 'radio', 'checkboxes' ), true ) ? '' : 'display:none;'; ?>">
					<label><?php esc_html_e( 'Options (one per line)', 'bkx-healthcare-practice' ); ?></label>
					<textarea name="bkx_intake_fields[<?php echo esc_attr( $index ); ?>][options_text]"
							  class="widefat" rows="4"><?php
						if ( is_array( $options ) ) {
							foreach ( $options as $opt_val => $opt_label ) {
								echo esc_textarea( $opt_val . '|' . $opt_label ) . "\n";
							}
						} else {
							echo esc_textarea( $options );
						}
					?></textarea>
					<span class="description">
						<?php esc_html_e( 'Format: value|label (e.g., yes|Yes, I agree)', 'bkx-healthcare-practice' ); ?>
					</span>
				</div>

				<div class="bkx-field-setting">
					<label><?php esc_html_e( 'Help Text', 'bkx-healthcare-practice' ); ?></label>
					<input type="text" name="bkx_intake_fields[<?php echo esc_attr( $index ); ?>][help]"
						   value="<?php echo esc_attr( $help ); ?>" class="widefat">
				</div>

				<div class="bkx-field-setting">
					<label>
						<input type="checkbox" name="bkx_intake_fields[<?php echo esc_attr( $index ); ?>][required]"
							   value="1" <?php checked( $required ); ?>>
						<?php esc_html_e( 'Required field', 'bkx-healthcare-practice' ); ?>
					</label>
				</div>
			</div>
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
		if ( ! isset( $_POST['bkx_intake_fields'] ) ) {
			update_post_meta( $post_id, '_bkx_intake_fields', array() );
			return;
		}

		$raw_fields = wp_unslash( $_POST['bkx_intake_fields'] );
		$fields = array();

		foreach ( $raw_fields as $field ) {
			if ( empty( $field['name'] ) && empty( $field['label'] ) ) {
				continue;
			}

			// Parse options from textarea.
			$options = array();
			if ( ! empty( $field['options_text'] ) ) {
				$lines = explode( "\n", $field['options_text'] );
				foreach ( $lines as $line ) {
					$line = trim( $line );
					if ( empty( $line ) ) {
						continue;
					}
					if ( strpos( $line, '|' ) !== false ) {
						list( $val, $label ) = explode( '|', $line, 2 );
						$options[ sanitize_key( trim( $val ) ) ] = sanitize_text_field( trim( $label ) );
					} else {
						$key = sanitize_key( $line );
						$options[ $key ] = sanitize_text_field( $line );
					}
				}
			}

			$fields[] = array(
				'name'     => sanitize_key( $field['name'] ?? '' ),
				'label'    => sanitize_text_field( $field['label'] ?? '' ),
				'type'     => sanitize_key( $field['type'] ?? 'text' ),
				'section'  => sanitize_text_field( $field['section'] ?? '' ),
				'required' => isset( $field['required'] ) ? 1 : 0,
				'options'  => $options,
				'help'     => sanitize_text_field( $field['help'] ?? '' ),
			);
		}

		update_post_meta( $post_id, '_bkx_intake_fields', $fields );
	}

	/**
	 * Get template fields.
	 *
	 * @since 1.0.0
	 * @param string $template Template name.
	 * @return array
	 */
	public static function get_template_fields( string $template ): array {
		$templates = array(
			'general' => array(
				array(
					'name'     => 'full_name',
					'label'    => __( 'Full Name', 'bkx-healthcare-practice' ),
					'type'     => 'text',
					'section'  => __( 'Personal Information', 'bkx-healthcare-practice' ),
					'required' => 1,
				),
				array(
					'name'     => 'date_of_birth',
					'label'    => __( 'Date of Birth', 'bkx-healthcare-practice' ),
					'type'     => 'date',
					'section'  => '',
					'required' => 1,
				),
				array(
					'name'     => 'phone',
					'label'    => __( 'Phone Number', 'bkx-healthcare-practice' ),
					'type'     => 'tel',
					'required' => 1,
				),
				array(
					'name'     => 'email',
					'label'    => __( 'Email Address', 'bkx-healthcare-practice' ),
					'type'     => 'email',
					'required' => 1,
				),
				array(
					'name'     => 'emergency_contact',
					'label'    => __( 'Emergency Contact Name', 'bkx-healthcare-practice' ),
					'type'     => 'text',
					'section'  => __( 'Emergency Contact', 'bkx-healthcare-practice' ),
					'required' => 1,
				),
				array(
					'name'     => 'emergency_phone',
					'label'    => __( 'Emergency Contact Phone', 'bkx-healthcare-practice' ),
					'type'     => 'tel',
					'required' => 1,
				),
				array(
					'name'     => 'current_medications',
					'label'    => __( 'Current Medications', 'bkx-healthcare-practice' ),
					'type'     => 'medications',
					'section'  => __( 'Medical History', 'bkx-healthcare-practice' ),
				),
				array(
					'name'     => 'allergies',
					'label'    => __( 'Allergies', 'bkx-healthcare-practice' ),
					'type'     => 'allergies',
				),
				array(
					'name'     => 'medical_conditions',
					'label'    => __( 'Current Medical Conditions', 'bkx-healthcare-practice' ),
					'type'     => 'textarea',
				),
				array(
					'name'     => 'reason_for_visit',
					'label'    => __( 'Reason for Visit', 'bkx-healthcare-practice' ),
					'type'     => 'textarea',
					'section'  => __( 'Today\'s Visit', 'bkx-healthcare-practice' ),
					'required' => 1,
				),
			),
			'dental' => array(
				array(
					'name'     => 'full_name',
					'label'    => __( 'Full Name', 'bkx-healthcare-practice' ),
					'type'     => 'text',
					'section'  => __( 'Personal Information', 'bkx-healthcare-practice' ),
					'required' => 1,
				),
				array(
					'name'     => 'date_of_birth',
					'label'    => __( 'Date of Birth', 'bkx-healthcare-practice' ),
					'type'     => 'date',
					'required' => 1,
				),
				array(
					'name'     => 'last_dental_visit',
					'label'    => __( 'Date of Last Dental Visit', 'bkx-healthcare-practice' ),
					'type'     => 'date',
					'section'  => __( 'Dental History', 'bkx-healthcare-practice' ),
				),
				array(
					'name'     => 'dental_concerns',
					'label'    => __( 'Current Dental Concerns', 'bkx-healthcare-practice' ),
					'type'     => 'checkboxes',
					'options'  => array(
						'pain'        => __( 'Tooth Pain', 'bkx-healthcare-practice' ),
						'sensitivity' => __( 'Sensitivity', 'bkx-healthcare-practice' ),
						'bleeding'    => __( 'Bleeding Gums', 'bkx-healthcare-practice' ),
						'cosmetic'    => __( 'Cosmetic Concerns', 'bkx-healthcare-practice' ),
						'checkup'     => __( 'Routine Checkup', 'bkx-healthcare-practice' ),
					),
				),
				array(
					'name'     => 'dental_anxiety',
					'label'    => __( 'Do you experience dental anxiety?', 'bkx-healthcare-practice' ),
					'type'     => 'radio',
					'options'  => array(
						'none'     => __( 'None', 'bkx-healthcare-practice' ),
						'mild'     => __( 'Mild', 'bkx-healthcare-practice' ),
						'moderate' => __( 'Moderate', 'bkx-healthcare-practice' ),
						'severe'   => __( 'Severe', 'bkx-healthcare-practice' ),
					),
				),
				array(
					'name'     => 'medications',
					'label'    => __( 'Current Medications', 'bkx-healthcare-practice' ),
					'type'     => 'medications',
					'section'  => __( 'Medical Information', 'bkx-healthcare-practice' ),
				),
				array(
					'name'     => 'medical_conditions',
					'label'    => __( 'Medical Conditions', 'bkx-healthcare-practice' ),
					'type'     => 'checkboxes',
					'options'  => array(
						'heart'    => __( 'Heart Disease', 'bkx-healthcare-practice' ),
						'diabetes' => __( 'Diabetes', 'bkx-healthcare-practice' ),
						'bp'       => __( 'High Blood Pressure', 'bkx-healthcare-practice' ),
						'bleeding' => __( 'Bleeding Disorder', 'bkx-healthcare-practice' ),
						'pregnant' => __( 'Pregnant/Nursing', 'bkx-healthcare-practice' ),
					),
				),
			),
			'mental_health' => array(
				array(
					'name'     => 'full_name',
					'label'    => __( 'Full Name', 'bkx-healthcare-practice' ),
					'type'     => 'text',
					'section'  => __( 'Personal Information', 'bkx-healthcare-practice' ),
					'required' => 1,
				),
				array(
					'name'     => 'date_of_birth',
					'label'    => __( 'Date of Birth', 'bkx-healthcare-practice' ),
					'type'     => 'date',
					'required' => 1,
				),
				array(
					'name'     => 'referral_source',
					'label'    => __( 'How did you hear about us?', 'bkx-healthcare-practice' ),
					'type'     => 'select',
					'options'  => array(
						'doctor'   => __( 'Doctor Referral', 'bkx-healthcare-practice' ),
						'friend'   => __( 'Friend/Family', 'bkx-healthcare-practice' ),
						'insurance' => __( 'Insurance Provider', 'bkx-healthcare-practice' ),
						'online'   => __( 'Online Search', 'bkx-healthcare-practice' ),
						'other'    => __( 'Other', 'bkx-healthcare-practice' ),
					),
				),
				array(
					'name'     => 'reason_for_seeking',
					'label'    => __( 'Primary reason for seeking therapy', 'bkx-healthcare-practice' ),
					'type'     => 'textarea',
					'section'  => __( 'Current Concerns', 'bkx-healthcare-practice' ),
					'required' => 1,
				),
				array(
					'name'     => 'symptoms',
					'label'    => __( 'Which symptoms are you experiencing?', 'bkx-healthcare-practice' ),
					'type'     => 'checkboxes',
					'options'  => array(
						'anxiety'     => __( 'Anxiety', 'bkx-healthcare-practice' ),
						'depression'  => __( 'Depression', 'bkx-healthcare-practice' ),
						'stress'      => __( 'Stress', 'bkx-healthcare-practice' ),
						'sleep'       => __( 'Sleep Problems', 'bkx-healthcare-practice' ),
						'relationship' => __( 'Relationship Issues', 'bkx-healthcare-practice' ),
						'grief'       => __( 'Grief/Loss', 'bkx-healthcare-practice' ),
						'trauma'      => __( 'Trauma', 'bkx-healthcare-practice' ),
					),
				),
				array(
					'name'     => 'previous_therapy',
					'label'    => __( 'Have you been in therapy before?', 'bkx-healthcare-practice' ),
					'type'     => 'radio',
					'section'  => __( 'Treatment History', 'bkx-healthcare-practice' ),
					'options'  => array(
						'yes' => __( 'Yes', 'bkx-healthcare-practice' ),
						'no'  => __( 'No', 'bkx-healthcare-practice' ),
					),
				),
				array(
					'name'     => 'current_medications',
					'label'    => __( 'Current Medications (including psychiatric)', 'bkx-healthcare-practice' ),
					'type'     => 'medications',
				),
				array(
					'name'     => 'emergency_contact',
					'label'    => __( 'Emergency Contact Name', 'bkx-healthcare-practice' ),
					'type'     => 'text',
					'section'  => __( 'Emergency Contact', 'bkx-healthcare-practice' ),
					'required' => 1,
				),
				array(
					'name'     => 'emergency_phone',
					'label'    => __( 'Emergency Contact Phone', 'bkx-healthcare-practice' ),
					'type'     => 'tel',
					'required' => 1,
				),
			),
		);

		return $templates[ $template ] ?? array();
	}
}

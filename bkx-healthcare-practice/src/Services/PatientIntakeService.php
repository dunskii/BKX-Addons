<?php
/**
 * Patient Intake Service.
 *
 * Handles patient intake forms, medical history, and pre-appointment questionnaires.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

namespace BookingX\HealthcarePractice\Services;

/**
 * Patient Intake Service class.
 *
 * @since 1.0.0
 */
class PatientIntakeService {

	/**
	 * Singleton instance.
	 *
	 * @var PatientIntakeService|null
	 */
	private static ?PatientIntakeService $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return PatientIntakeService
	 */
	public static function get_instance(): PatientIntakeService {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Private constructor for singleton.
	}

	/**
	 * Save patient intake form data.
	 *
	 * @since 1.0.0
	 * @param int   $form_id Form template ID.
	 * @param array $data    Form data.
	 * @return int|\WP_Error Intake record ID or error.
	 */
	public function save_intake( int $form_id, array $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_patient_intakes';

		// Encrypt sensitive data.
		$encrypted_data = $this->encrypt_sensitive_data( $data );

		$user_id = get_current_user_id();

		// Check for existing intake for this user and form.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE user_id = %d AND form_id = %d AND status = 'draft'",
				$user_id,
				$form_id
			)
		);

		if ( $existing ) {
			// Update existing draft.
			$wpdb->update(
				$table_name,
				array(
					'form_data'   => wp_json_encode( $encrypted_data ),
					'status'      => 'submitted',
					'updated_at'  => current_time( 'mysql' ),
				),
				array( 'id' => $existing ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			return absint( $existing );
		}

		// Insert new intake.
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'user_id'     => $user_id,
				'form_id'     => $form_id,
				'form_data'   => wp_json_encode( $encrypted_data ),
				'status'      => 'submitted',
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'db_error', __( 'Failed to save intake form', 'bkx-healthcare-practice' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get intake form data.
	 *
	 * @since 1.0.0
	 * @param int $intake_id Intake record ID.
	 * @return array|null
	 */
	public function get_intake_data( int $intake_id ): ?array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_patient_intakes';

		$intake = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$intake_id
			),
			ARRAY_A
		);

		if ( ! $intake ) {
			return null;
		}

		// Decrypt sensitive data.
		$intake['form_data'] = $this->decrypt_sensitive_data(
			json_decode( $intake['form_data'], true )
		);

		return $intake;
	}

	/**
	 * Encrypt sensitive patient data.
	 *
	 * @since 1.0.0
	 * @param array $data Raw data.
	 * @return array Encrypted data.
	 */
	private function encrypt_sensitive_data( array $data ): array {
		$sensitive_fields = array(
			'ssn',
			'date_of_birth',
			'medical_record_number',
			'insurance_id',
			'diagnosis',
			'medications',
			'allergies',
			'medical_history',
		);

		if ( class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();

			foreach ( $sensitive_fields as $field ) {
				if ( isset( $data[ $field ] ) && ! empty( $data[ $field ] ) ) {
					if ( is_array( $data[ $field ] ) ) {
						$data[ $field ] = $encryption->encrypt( wp_json_encode( $data[ $field ] ) );
					} else {
						$data[ $field ] = $encryption->encrypt( $data[ $field ] );
					}
					$data[ $field . '_encrypted' ] = true;
				}
			}
		}

		return $data;
	}

	/**
	 * Decrypt sensitive patient data.
	 *
	 * @since 1.0.0
	 * @param array $data Encrypted data.
	 * @return array Decrypted data.
	 */
	private function decrypt_sensitive_data( array $data ): array {
		if ( ! class_exists( 'BKX_Data_Encryption' ) ) {
			return $data;
		}

		$encryption = new \BKX_Data_Encryption();

		foreach ( $data as $field => $value ) {
			if ( isset( $data[ $field . '_encrypted' ] ) && $data[ $field . '_encrypted' ] ) {
				$decrypted = $encryption->decrypt( $value );

				// Check if it's JSON.
				$json_decoded = json_decode( $decrypted, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$data[ $field ] = $json_decoded;
				} else {
					$data[ $field ] = $decrypted;
				}

				unset( $data[ $field . '_encrypted' ] );
			}
		}

		return $data;
	}

	/**
	 * Link intake to booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function link_to_booking( int $booking_id, array $booking_data ): void {
		global $wpdb;

		$user_id = isset( $booking_data['user_id'] ) ? absint( $booking_data['user_id'] ) : get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$table_name = $wpdb->prefix . 'bkx_patient_intakes';

		// Find pending intake for this user.
		$intake_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE user_id = %d AND status = 'submitted' AND booking_id IS NULL ORDER BY created_at DESC LIMIT 1",
				$user_id
			)
		);

		if ( $intake_id ) {
			$wpdb->update(
				$table_name,
				array( 'booking_id' => $booking_id ),
				array( 'id' => $intake_id ),
				array( '%d' ),
				array( '%d' )
			);

			update_post_meta( $booking_id, 'patient_intake_id', $intake_id );
		}
	}

	/**
	 * Render intake form.
	 *
	 * @since 1.0.0
	 * @param int    $form_id    Form template ID.
	 * @param string $booking_id Optional booking reference.
	 * @return string HTML output.
	 */
	public function render_form( int $form_id, string $booking_id = '' ): string {
		if ( ! $form_id ) {
			return '<p class="bkx-error">' . esc_html__( 'Invalid form ID', 'bkx-healthcare-practice' ) . '</p>';
		}

		$form = get_post( $form_id );

		if ( ! $form || 'bkx_intake_form' !== $form->post_type ) {
			return '<p class="bkx-error">' . esc_html__( 'Form not found', 'bkx-healthcare-practice' ) . '</p>';
		}

		$fields = get_post_meta( $form_id, '_bkx_intake_fields', true );

		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return '<p class="bkx-error">' . esc_html__( 'No form fields configured', 'bkx-healthcare-practice' ) . '</p>';
		}

		// Get existing data for logged-in users.
		$existing_data = array();
		if ( is_user_logged_in() ) {
			$existing_data = $this->get_user_intake_data( get_current_user_id(), $form_id );
		}

		ob_start();
		?>
		<div class="bkx-patient-intake-form" data-form-id="<?php echo esc_attr( $form_id ); ?>">
			<h3><?php echo esc_html( $form->post_title ); ?></h3>

			<?php if ( $form->post_content ) : ?>
				<div class="bkx-form-description">
					<?php echo wp_kses_post( $form->post_content ); ?>
				</div>
			<?php endif; ?>

			<form id="bkx-intake-form-<?php echo esc_attr( $form_id ); ?>" class="bkx-intake-form">
				<?php wp_nonce_field( 'bkx_healthcare_frontend', 'bkx_healthcare_nonce' ); ?>
				<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
				<?php if ( $booking_id ) : ?>
					<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking_id ); ?>">
				<?php endif; ?>

				<?php $this->render_form_sections( $fields, $existing_data ); ?>

				<div class="bkx-hipaa-notice">
					<p>
						<strong><?php esc_html_e( 'Privacy Notice:', 'bkx-healthcare-practice' ); ?></strong>
						<?php esc_html_e( 'Your health information is protected under HIPAA regulations. We use industry-standard encryption to protect your data.', 'bkx-healthcare-practice' ); ?>
					</p>
				</div>

				<div class="bkx-form-actions">
					<button type="button" class="bkx-btn bkx-btn-secondary bkx-save-draft">
						<?php esc_html_e( 'Save as Draft', 'bkx-healthcare-practice' ); ?>
					</button>
					<button type="submit" class="bkx-btn bkx-btn-primary">
						<?php esc_html_e( 'Submit Form', 'bkx-healthcare-practice' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render form sections and fields.
	 *
	 * @since 1.0.0
	 * @param array $fields        Form fields configuration.
	 * @param array $existing_data Existing form data.
	 * @return void
	 */
	private function render_form_sections( array $fields, array $existing_data ): void {
		$current_section = '';

		foreach ( $fields as $field ) {
			// Handle section headers.
			if ( isset( $field['section'] ) && $field['section'] !== $current_section ) {
				if ( $current_section ) {
					echo '</fieldset>';
				}
				$current_section = $field['section'];
				echo '<fieldset class="bkx-intake-section">';
				echo '<legend>' . esc_html( $current_section ) . '</legend>';
			}

			$this->render_field( $field, $existing_data );
		}

		if ( $current_section ) {
			echo '</fieldset>';
		}
	}

	/**
	 * Render a single form field.
	 *
	 * @since 1.0.0
	 * @param array $field         Field configuration.
	 * @param array $existing_data Existing data.
	 * @return void
	 */
	private function render_field( array $field, array $existing_data ): void {
		$name     = sanitize_key( $field['name'] ?? '' );
		$label    = $field['label'] ?? '';
		$type     = $field['type'] ?? 'text';
		$required = ! empty( $field['required'] );
		$value    = $existing_data[ $name ] ?? ( $field['default'] ?? '' );
		$options  = $field['options'] ?? array();
		$help     = $field['help'] ?? '';

		$required_attr = $required ? 'required' : '';
		$required_mark = $required ? '<span class="required">*</span>' : '';

		echo '<div class="bkx-form-field bkx-field-' . esc_attr( $type ) . '">';
		echo '<label for="bkx_' . esc_attr( $name ) . '">' . esc_html( $label ) . wp_kses_post( $required_mark ) . '</label>';

		switch ( $type ) {
			case 'text':
			case 'email':
			case 'tel':
			case 'date':
				printf(
					'<input type="%s" id="bkx_%s" name="form_data[%s]" value="%s" %s>',
					esc_attr( $type ),
					esc_attr( $name ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( $required_attr )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="bkx_%s" name="form_data[%s]" rows="4" %s>%s</textarea>',
					esc_attr( $name ),
					esc_attr( $name ),
					esc_attr( $required_attr ),
					esc_textarea( $value )
				);
				break;

			case 'select':
				printf( '<select id="bkx_%s" name="form_data[%s]" %s>', esc_attr( $name ), esc_attr( $name ), esc_attr( $required_attr ) );
				echo '<option value="">' . esc_html__( 'Select...', 'bkx-healthcare-practice' ) . '</option>';
				foreach ( $options as $opt_value => $opt_label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $opt_value ),
						selected( $value, $opt_value, false ),
						esc_html( $opt_label )
					);
				}
				echo '</select>';
				break;

			case 'radio':
				echo '<div class="bkx-radio-group">';
				foreach ( $options as $opt_value => $opt_label ) {
					printf(
						'<label><input type="radio" name="form_data[%s]" value="%s" %s> %s</label>',
						esc_attr( $name ),
						esc_attr( $opt_value ),
						checked( $value, $opt_value, false ),
						esc_html( $opt_label )
					);
				}
				echo '</div>';
				break;

			case 'checkbox':
				printf(
					'<input type="checkbox" id="bkx_%s" name="form_data[%s]" value="1" %s>',
					esc_attr( $name ),
					esc_attr( $name ),
					checked( $value, '1', false )
				);
				break;

			case 'checkboxes':
				echo '<div class="bkx-checkbox-group">';
				$selected = is_array( $value ) ? $value : array();
				foreach ( $options as $opt_value => $opt_label ) {
					printf(
						'<label><input type="checkbox" name="form_data[%s][]" value="%s" %s> %s</label>',
						esc_attr( $name ),
						esc_attr( $opt_value ),
						in_array( $opt_value, $selected, true ) ? 'checked' : '',
						esc_html( $opt_label )
					);
				}
				echo '</div>';
				break;

			case 'medications':
				$this->render_medications_field( $name, $value );
				break;

			case 'allergies':
				$this->render_allergies_field( $name, $value );
				break;
		}

		if ( $help ) {
			echo '<p class="bkx-field-help">' . esc_html( $help ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Render medications repeater field.
	 *
	 * @since 1.0.0
	 * @param string $name  Field name.
	 * @param mixed  $value Current value.
	 * @return void
	 */
	private function render_medications_field( string $name, $value ): void {
		$medications = is_array( $value ) ? $value : array();
		?>
		<div class="bkx-medications-list" data-field="<?php echo esc_attr( $name ); ?>">
			<?php foreach ( $medications as $i => $med ) : ?>
				<div class="bkx-medication-item">
					<input type="text" name="form_data[<?php echo esc_attr( $name ); ?>][<?php echo esc_attr( $i ); ?>][name]"
						   placeholder="<?php esc_attr_e( 'Medication name', 'bkx-healthcare-practice' ); ?>"
						   value="<?php echo esc_attr( $med['name'] ?? '' ); ?>">
					<input type="text" name="form_data[<?php echo esc_attr( $name ); ?>][<?php echo esc_attr( $i ); ?>][dosage]"
						   placeholder="<?php esc_attr_e( 'Dosage', 'bkx-healthcare-practice' ); ?>"
						   value="<?php echo esc_attr( $med['dosage'] ?? '' ); ?>">
					<input type="text" name="form_data[<?php echo esc_attr( $name ); ?>][<?php echo esc_attr( $i ); ?>][frequency]"
						   placeholder="<?php esc_attr_e( 'Frequency', 'bkx-healthcare-practice' ); ?>"
						   value="<?php echo esc_attr( $med['frequency'] ?? '' ); ?>">
					<button type="button" class="bkx-remove-medication">&times;</button>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="bkx-btn bkx-btn-small bkx-add-medication">
			<?php esc_html_e( '+ Add Medication', 'bkx-healthcare-practice' ); ?>
		</button>
		<?php
	}

	/**
	 * Render allergies field.
	 *
	 * @since 1.0.0
	 * @param string $name  Field name.
	 * @param mixed  $value Current value.
	 * @return void
	 */
	private function render_allergies_field( string $name, $value ): void {
		$allergies = is_array( $value ) ? $value : array();
		?>
		<div class="bkx-allergies-list" data-field="<?php echo esc_attr( $name ); ?>">
			<?php foreach ( $allergies as $i => $allergy ) : ?>
				<div class="bkx-allergy-item">
					<input type="text" name="form_data[<?php echo esc_attr( $name ); ?>][<?php echo esc_attr( $i ); ?>][allergen]"
						   placeholder="<?php esc_attr_e( 'Allergen', 'bkx-healthcare-practice' ); ?>"
						   value="<?php echo esc_attr( $allergy['allergen'] ?? '' ); ?>">
					<select name="form_data[<?php echo esc_attr( $name ); ?>][<?php echo esc_attr( $i ); ?>][severity]">
						<option value="mild" <?php selected( $allergy['severity'] ?? '', 'mild' ); ?>><?php esc_html_e( 'Mild', 'bkx-healthcare-practice' ); ?></option>
						<option value="moderate" <?php selected( $allergy['severity'] ?? '', 'moderate' ); ?>><?php esc_html_e( 'Moderate', 'bkx-healthcare-practice' ); ?></option>
						<option value="severe" <?php selected( $allergy['severity'] ?? '', 'severe' ); ?>><?php esc_html_e( 'Severe', 'bkx-healthcare-practice' ); ?></option>
					</select>
					<input type="text" name="form_data[<?php echo esc_attr( $name ); ?>][<?php echo esc_attr( $i ); ?>][reaction]"
						   placeholder="<?php esc_attr_e( 'Reaction type', 'bkx-healthcare-practice' ); ?>"
						   value="<?php echo esc_attr( $allergy['reaction'] ?? '' ); ?>">
					<button type="button" class="bkx-remove-allergy">&times;</button>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="bkx-btn bkx-btn-small bkx-add-allergy">
			<?php esc_html_e( '+ Add Allergy', 'bkx-healthcare-practice' ); ?>
		</button>
		<?php
	}

	/**
	 * Get user's existing intake data.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @param int $form_id Form template ID.
	 * @return array
	 */
	private function get_user_intake_data( int $user_id, int $form_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_patient_intakes';

		$data = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT form_data FROM {$table_name} WHERE user_id = %d AND form_id = %d ORDER BY created_at DESC LIMIT 1",
				$user_id,
				$form_id
			)
		);

		if ( ! $data ) {
			return array();
		}

		$decoded = json_decode( $data, true );

		return $this->decrypt_sensitive_data( $decoded );
	}

	/**
	 * Get intake forms for a patient.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_patient_intakes( int $user_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_patient_intakes';

		$intakes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.*, p.post_title as form_name
				 FROM {$table_name} i
				 LEFT JOIN {$wpdb->posts} p ON i.form_id = p.ID
				 WHERE i.user_id = %d
				 ORDER BY i.created_at DESC",
				$user_id
			),
			ARRAY_A
		);

		return $intakes ?: array();
	}
}

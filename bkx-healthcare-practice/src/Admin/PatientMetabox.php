<?php
/**
 * Patient Information Metabox.
 *
 * Displays patient information on booking edit screens.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

namespace BookingX\HealthcarePractice\Admin;

use BookingX\HealthcarePractice\Services\PatientIntakeService;
use BookingX\HealthcarePractice\Services\ConsentService;

/**
 * Patient Metabox class.
 *
 * @since 1.0.0
 */
class PatientMetabox {

	/**
	 * Render the patient information metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Booking post object.
	 * @return void
	 */
	public static function render( \WP_Post $post ): void {
		$customer_id = get_post_meta( $post->ID, 'customer_id', true );

		if ( ! $customer_id ) {
			echo '<p>' . esc_html__( 'No patient information available.', 'bkx-healthcare-practice' ) . '</p>';
			return;
		}

		$user = get_userdata( $customer_id );

		if ( ! $user ) {
			echo '<p>' . esc_html__( 'Patient user not found.', 'bkx-healthcare-practice' ) . '</p>';
			return;
		}

		// Log access to patient record.
		do_action( 'bkx_healthcare_audit_log', 'patient_record_viewed', $customer_id, array(
			'booking_id'  => $post->ID,
			'accessed_by' => get_current_user_id(),
		) );

		// Get patient data.
		$intake_id = get_post_meta( $post->ID, 'patient_intake_id', true );
		$intake_data = $intake_id ? PatientIntakeService::get_instance()->get_intake_data( absint( $intake_id ) ) : null;
		$consents = ConsentService::get_instance()->get_user_consent_history( $customer_id );
		$allergies = self::get_patient_allergies( $customer_id );
		?>
		<div class="bkx-patient-metabox">
			<!-- Allergy Alert -->
			<?php if ( ! empty( $allergies ) ) : ?>
				<div class="bkx-allergy-alert">
					<strong><?php esc_html_e( 'ALLERGIES:', 'bkx-healthcare-practice' ); ?></strong>
					<?php
					$allergy_list = array();
					foreach ( $allergies as $allergy ) {
						$severity = isset( $allergy['severity'] ) && 'severe' === $allergy['severity'] ? ' (SEVERE)' : '';
						$allergy_list[] = esc_html( $allergy['allergen'] ) . $severity;
					}
					echo implode( ', ', $allergy_list );
					?>
				</div>
			<?php endif; ?>

			<!-- Patient Info -->
			<div class="bkx-patient-info">
				<p>
					<strong><?php esc_html_e( 'Name:', 'bkx-healthcare-practice' ); ?></strong>
					<?php echo esc_html( $user->display_name ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Email:', 'bkx-healthcare-practice' ); ?></strong>
					<a href="mailto:<?php echo esc_attr( $user->user_email ); ?>">
						<?php echo esc_html( $user->user_email ); ?>
					</a>
				</p>
				<?php
				$phone = get_user_meta( $customer_id, 'phone', true );
				if ( $phone ) :
					?>
					<p>
						<strong><?php esc_html_e( 'Phone:', 'bkx-healthcare-practice' ); ?></strong>
						<a href="tel:<?php echo esc_attr( $phone ); ?>">
							<?php echo esc_html( $phone ); ?>
						</a>
					</p>
				<?php endif; ?>
				<?php
				$dob = get_user_meta( $customer_id, '_bkx_date_of_birth', true );
				if ( $dob ) :
					?>
					<p>
						<strong><?php esc_html_e( 'DOB:', 'bkx-healthcare-practice' ); ?></strong>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $dob ) ) ); ?>
						(<?php echo esc_html( self::calculate_age( $dob ) ); ?>)
					</p>
				<?php endif; ?>
			</div>

			<!-- Intake Form Status -->
			<?php if ( $intake_data ) : ?>
				<div class="bkx-intake-status">
					<h4><?php esc_html_e( 'Intake Form', 'bkx-healthcare-practice' ); ?></h4>
					<p>
						<span class="bkx-status-badge bkx-status-<?php echo esc_attr( $intake_data['status'] ); ?>">
							<?php echo esc_html( ucfirst( $intake_data['status'] ) ); ?>
						</span>
						<a href="#" class="bkx-view-intake" data-intake-id="<?php echo esc_attr( $intake_id ); ?>">
							<?php esc_html_e( 'View Details', 'bkx-healthcare-practice' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<!-- Consent Status -->
			<?php if ( ! empty( $consents ) ) : ?>
				<div class="bkx-consent-status">
					<h4><?php esc_html_e( 'Consent Forms', 'bkx-healthcare-practice' ); ?></h4>
					<ul>
						<?php foreach ( array_slice( $consents, 0, 5 ) as $consent ) : ?>
							<li>
								<?php echo esc_html( $consent['form_name'] ); ?>
								<span class="bkx-status-badge bkx-status-<?php echo esc_attr( $consent['status'] ); ?>">
									<?php echo esc_html( ucfirst( $consent['status'] ) ); ?>
								</span>
								<br>
								<small>
									<?php
									printf(
										/* translators: %s: Date */
										esc_html__( 'Signed: %s', 'bkx-healthcare-practice' ),
										esc_html( date_i18n( get_option( 'date_format' ), strtotime( $consent['consented_at'] ) ) )
									);
									?>
								</small>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<!-- Actions -->
			<div class="bkx-patient-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-patient-records&patient_id=' . $customer_id ) ); ?>"
				   class="button button-small">
					<?php esc_html_e( 'Full Patient Record', 'bkx-healthcare-practice' ); ?>
				</a>
			</div>

			<!-- Provider Notes -->
			<div class="bkx-provider-notes">
				<h4><?php esc_html_e( 'Provider Notes', 'bkx-healthcare-practice' ); ?></h4>
				<?php
				$notes = get_post_meta( $post->ID, '_bkx_provider_notes', true );
				?>
				<textarea name="bkx_provider_notes" rows="4" class="widefat"><?php echo esc_textarea( $notes ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'Private notes visible only to staff.', 'bkx-healthcare-practice' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Get patient allergies.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	private static function get_patient_allergies( int $user_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_patient_intakes';

		// Get most recent intake with allergy data.
		$intake = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT form_data FROM {$table_name} WHERE user_id = %d AND status = 'submitted' ORDER BY created_at DESC LIMIT 1",
				$user_id
			)
		);

		if ( ! $intake ) {
			return array();
		}

		$data = json_decode( $intake, true );

		if ( ! isset( $data['allergies'] ) ) {
			return array();
		}

		// Decrypt if necessary.
		if ( isset( $data['allergies_encrypted'] ) && $data['allergies_encrypted'] && class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();
			$decrypted = $encryption->decrypt( $data['allergies'] );
			$allergies = json_decode( $decrypted, true );
			return is_array( $allergies ) ? $allergies : array();
		}

		return is_array( $data['allergies'] ) ? $data['allergies'] : array();
	}

	/**
	 * Calculate age from date of birth.
	 *
	 * @since 1.0.0
	 * @param string $dob Date of birth (Y-m-d format).
	 * @return string
	 */
	private static function calculate_age( string $dob ): string {
		$birth = new \DateTime( $dob );
		$today = new \DateTime( 'today' );
		$age = $birth->diff( $today )->y;

		return sprintf(
			/* translators: %d: Age in years */
			_n( '%d year old', '%d years old', $age, 'bkx-healthcare-practice' ),
			$age
		);
	}

	/**
	 * Save provider notes.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save_notes( int $post_id ): void {
		if ( isset( $_POST['bkx_provider_notes'] ) ) {
			$notes = sanitize_textarea_field( wp_unslash( $_POST['bkx_provider_notes'] ) );
			update_post_meta( $post_id, '_bkx_provider_notes', $notes );

			// Log note update.
			do_action( 'bkx_healthcare_audit_log', 'provider_notes_updated', $post_id, array(
				'updated_by' => get_current_user_id(),
			) );
		}
	}
}

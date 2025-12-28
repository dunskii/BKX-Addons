<?php
/**
 * Consent Service.
 *
 * Handles consent form management, signatures, and compliance tracking.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

namespace BookingX\HealthcarePractice\Services;

/**
 * Consent Service class.
 *
 * @since 1.0.0
 */
class ConsentService {

	/**
	 * Singleton instance.
	 *
	 * @var ConsentService|null
	 */
	private static ?ConsentService $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return ConsentService
	 */
	public static function get_instance(): ConsentService {
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
	 * Record consent submission.
	 *
	 * @since 1.0.0
	 * @param int    $form_id   Consent form template ID.
	 * @param string $signature Digital signature data.
	 * @return int|\WP_Error Consent record ID or error.
	 */
	public function record_consent( int $form_id, string $signature ) {
		global $wpdb;

		$form = get_post( $form_id );

		if ( ! $form || 'bkx_consent_form' !== $form->post_type ) {
			return new \WP_Error( 'invalid_form', __( 'Invalid consent form', 'bkx-healthcare-practice' ) );
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return new \WP_Error( 'not_logged_in', __( 'You must be logged in to submit consent', 'bkx-healthcare-practice' ) );
		}

		$table_name = $wpdb->prefix . 'bkx_patient_consents';

		// Get the form version.
		$form_version = get_post_meta( $form_id, '_bkx_consent_version', true ) ?: '1.0';

		// Store the form content snapshot for compliance.
		$form_content_hash = hash( 'sha256', $form->post_content );

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'user_id'           => $user_id,
				'form_id'           => $form_id,
				'form_version'      => $form_version,
				'form_content_hash' => $form_content_hash,
				'signature'         => $this->encrypt_signature( $signature ),
				'ip_address'        => $this->get_client_ip(),
				'user_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'consented_at'      => current_time( 'mysql' ),
				'expires_at'        => $this->calculate_expiry( $form_id ),
				'status'            => 'active',
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'db_error', __( 'Failed to record consent', 'bkx-healthcare-practice' ) );
		}

		$consent_id = $wpdb->insert_id;

		// Update user meta.
		$this->update_user_consent_status( $user_id, $form_id, $consent_id );

		return $consent_id;
	}

	/**
	 * Encrypt digital signature.
	 *
	 * @since 1.0.0
	 * @param string $signature Signature data.
	 * @return string Encrypted signature.
	 */
	private function encrypt_signature( string $signature ): string {
		if ( class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();
			return $encryption->encrypt( $signature );
		}
		return $signature;
	}

	/**
	 * Calculate consent expiry date.
	 *
	 * @since 1.0.0
	 * @param int $form_id Form ID.
	 * @return string|null Expiry date or null for no expiry.
	 */
	private function calculate_expiry( int $form_id ): ?string {
		$expiry_months = get_post_meta( $form_id, '_bkx_consent_expiry_months', true );

		if ( ! $expiry_months ) {
			return null;
		}

		$expiry_months = absint( $expiry_months );

		if ( $expiry_months < 1 ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_months} months" ) );
	}

	/**
	 * Get client IP address.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Update user's consent status.
	 *
	 * @since 1.0.0
	 * @param int $user_id    User ID.
	 * @param int $form_id    Form ID.
	 * @param int $consent_id Consent record ID.
	 * @return void
	 */
	private function update_user_consent_status( int $user_id, int $form_id, int $consent_id ): void {
		$consents = get_user_meta( $user_id, '_bkx_patient_consents', true ) ?: array();

		$consents[ $form_id ] = array(
			'consent_id'   => $consent_id,
			'consented_at' => current_time( 'mysql' ),
		);

		update_user_meta( $user_id, '_bkx_patient_consents', $consents );
	}

	/**
	 * Get pending consent forms for a user.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array Array of pending consent form posts.
	 */
	public function get_pending_consents( int $user_id ): array {
		// Get all required consent forms.
		$required_forms = get_posts( array(
			'post_type'      => 'bkx_consent_form',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'   => '_bkx_consent_required',
					'value' => '1',
				),
			),
		) );

		if ( empty( $required_forms ) ) {
			return array();
		}

		$user_consents = get_user_meta( $user_id, '_bkx_patient_consents', true ) ?: array();
		$pending       = array();

		foreach ( $required_forms as $form ) {
			// Check if user has valid consent for this form.
			if ( ! $this->has_valid_consent( $user_id, $form->ID, $user_consents ) ) {
				$pending[] = $form;
			}
		}

		return $pending;
	}

	/**
	 * Check if user has valid consent for a form.
	 *
	 * @since 1.0.0
	 * @param int   $user_id       User ID.
	 * @param int   $form_id       Form ID.
	 * @param array $user_consents User's consent records.
	 * @return bool
	 */
	private function has_valid_consent( int $user_id, int $form_id, array $user_consents ): bool {
		if ( ! isset( $user_consents[ $form_id ] ) ) {
			return false;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_patient_consents';
		$consent_id = $user_consents[ $form_id ]['consent_id'];

		// Check if consent is still active and not expired.
		$consent = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())",
				$consent_id
			)
		);

		if ( ! $consent ) {
			return false;
		}

		// Check if form version has changed.
		$current_version = get_post_meta( $form_id, '_bkx_consent_version', true ) ?: '1.0';
		$require_reconsent = get_post_meta( $form_id, '_bkx_require_reconsent_on_update', true );

		if ( $require_reconsent && $consent->form_version !== $current_version ) {
			return false;
		}

		return true;
	}

	/**
	 * Render consent form.
	 *
	 * @since 1.0.0
	 * @param int  $form_id    Form ID.
	 * @param bool $required   Whether consent is required.
	 * @param bool $show_print Whether to show print button.
	 * @return string HTML output.
	 */
	public function render_form( int $form_id, bool $required = true, bool $show_print = true ): string {
		if ( ! $form_id ) {
			return '<p class="bkx-error">' . esc_html__( 'Invalid consent form ID', 'bkx-healthcare-practice' ) . '</p>';
		}

		$form = get_post( $form_id );

		if ( ! $form || 'bkx_consent_form' !== $form->post_type ) {
			return '<p class="bkx-error">' . esc_html__( 'Consent form not found', 'bkx-healthcare-practice' ) . '</p>';
		}

		// Check if already consented.
		if ( is_user_logged_in() ) {
			$user_consents = get_user_meta( get_current_user_id(), '_bkx_patient_consents', true ) ?: array();
			if ( $this->has_valid_consent( get_current_user_id(), $form_id, $user_consents ) ) {
				return $this->render_already_consented( $form_id, $user_consents[ $form_id ] );
			}
		}

		$form_version = get_post_meta( $form_id, '_bkx_consent_version', true ) ?: '1.0';
		$require_signature = get_post_meta( $form_id, '_bkx_require_signature', true );

		ob_start();
		?>
		<div class="bkx-consent-form" data-form-id="<?php echo esc_attr( $form_id ); ?>">
			<div class="bkx-consent-header">
				<h3><?php echo esc_html( $form->post_title ); ?></h3>
				<span class="bkx-consent-version"><?php echo esc_html( sprintf( __( 'Version %s', 'bkx-healthcare-practice' ), $form_version ) ); ?></span>
			</div>

			<?php if ( $show_print ) : ?>
				<div class="bkx-consent-actions-top">
					<button type="button" class="bkx-btn bkx-btn-small bkx-print-consent">
						<?php esc_html_e( 'Print Form', 'bkx-healthcare-practice' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<div class="bkx-consent-content">
				<?php echo wp_kses_post( apply_filters( 'the_content', $form->post_content ) ); ?>
			</div>

			<?php if ( ! is_user_logged_in() ) : ?>
				<div class="bkx-consent-login-required">
					<p><?php esc_html_e( 'You must be logged in to submit consent.', 'bkx-healthcare-practice' ); ?></p>
					<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bkx-btn">
						<?php esc_html_e( 'Log In', 'bkx-healthcare-practice' ); ?>
					</a>
				</div>
			<?php else : ?>
				<form id="bkx-consent-form-<?php echo esc_attr( $form_id ); ?>" class="bkx-consent-submission">
					<?php wp_nonce_field( 'bkx_healthcare_frontend', 'bkx_healthcare_nonce' ); ?>
					<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">

					<?php if ( $require_signature ) : ?>
						<div class="bkx-signature-section">
							<label><?php esc_html_e( 'Digital Signature', 'bkx-healthcare-practice' ); ?> <span class="required">*</span></label>
							<div class="bkx-signature-pad-container">
								<canvas id="bkx-signature-pad-<?php echo esc_attr( $form_id ); ?>" class="bkx-signature-pad"></canvas>
								<input type="hidden" name="signature" id="bkx-signature-data-<?php echo esc_attr( $form_id ); ?>">
							</div>
							<button type="button" class="bkx-btn bkx-btn-small bkx-clear-signature">
								<?php esc_html_e( 'Clear Signature', 'bkx-healthcare-practice' ); ?>
							</button>
						</div>
					<?php endif; ?>

					<div class="bkx-consent-agreement">
						<label class="bkx-checkbox-label">
							<input type="checkbox" name="consent_agreed" value="1" <?php echo $required ? 'required' : ''; ?>>
							<span>
								<?php
								printf(
									/* translators: %s: Consent form title */
									esc_html__( 'I have read and agree to the terms of %s', 'bkx-healthcare-practice' ),
									'<strong>' . esc_html( $form->post_title ) . '</strong>'
								);
								?>
							</span>
						</label>
					</div>

					<div class="bkx-consent-submit">
						<button type="submit" class="bkx-btn bkx-btn-primary">
							<?php esc_html_e( 'Submit Consent', 'bkx-healthcare-practice' ); ?>
						</button>
					</div>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render already consented message.
	 *
	 * @since 1.0.0
	 * @param int   $form_id      Form ID.
	 * @param array $consent_data User's consent data.
	 * @return string HTML output.
	 */
	private function render_already_consented( int $form_id, array $consent_data ): string {
		$form = get_post( $form_id );

		ob_start();
		?>
		<div class="bkx-consent-already-signed">
			<div class="bkx-consent-check-icon">&#10003;</div>
			<h4><?php esc_html_e( 'Consent Already Recorded', 'bkx-healthcare-practice' ); ?></h4>
			<p>
				<?php
				printf(
					/* translators: 1: Form title, 2: Date */
					esc_html__( 'You have already consented to %1$s on %2$s.', 'bkx-healthcare-practice' ),
					'<strong>' . esc_html( $form->post_title ) . '</strong>',
					esc_html( date_i18n( get_option( 'date_format' ), strtotime( $consent_data['consented_at'] ) ) )
				);
				?>
			</p>
			<a href="#" class="bkx-view-consent-details" data-consent-id="<?php echo esc_attr( $consent_data['consent_id'] ); ?>">
				<?php esc_html_e( 'View Consent Details', 'bkx-healthcare-practice' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get consent records for a booking.
	 *
	 * @since 1.0.0
	 * @param array|string $consent_ids Consent IDs (array or comma-separated).
	 * @return array
	 */
	public function get_consent_records( $consent_ids ): array {
		global $wpdb;

		if ( is_string( $consent_ids ) ) {
			$consent_ids = array_map( 'absint', explode( ',', $consent_ids ) );
		}

		if ( empty( $consent_ids ) ) {
			return array();
		}

		$table_name   = $wpdb->prefix . 'bkx_patient_consents';
		$placeholders = implode( ',', array_fill( 0, count( $consent_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$consents = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, p.post_title as form_name
				 FROM {$table_name} c
				 LEFT JOIN {$wpdb->posts} p ON c.form_id = p.ID
				 WHERE c.id IN ({$placeholders})",
				$consent_ids
			),
			ARRAY_A
		);

		return $consents ?: array();
	}

	/**
	 * Revoke a consent.
	 *
	 * @since 1.0.0
	 * @param int    $consent_id Consent record ID.
	 * @param string $reason     Reason for revocation.
	 * @return bool|\WP_Error
	 */
	public function revoke_consent( int $consent_id, string $reason = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_patient_consents';

		$consent = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$consent_id
			)
		);

		if ( ! $consent ) {
			return new \WP_Error( 'not_found', __( 'Consent record not found', 'bkx-healthcare-practice' ) );
		}

		// Check authorization.
		if ( ! current_user_can( 'manage_options' ) && $consent->user_id !== get_current_user_id() ) {
			return new \WP_Error( 'unauthorized', __( 'You are not authorized to revoke this consent', 'bkx-healthcare-practice' ) );
		}

		$updated = $wpdb->update(
			$table_name,
			array(
				'status'      => 'revoked',
				'revoked_at'  => current_time( 'mysql' ),
				'revoked_by'  => get_current_user_id(),
				'revoke_reason' => sanitize_text_field( $reason ),
			),
			array( 'id' => $consent_id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( $updated ) {
			// Update user meta.
			$user_consents = get_user_meta( $consent->user_id, '_bkx_patient_consents', true ) ?: array();
			unset( $user_consents[ $consent->form_id ] );
			update_user_meta( $consent->user_id, '_bkx_patient_consents', $user_consents );

			// Log HIPAA event.
			do_action( 'bkx_healthcare_audit_log', 'consent_revoked', $consent_id, array(
				'reason' => $reason,
				'revoked_by' => get_current_user_id(),
			) );

			return true;
		}

		return new \WP_Error( 'db_error', __( 'Failed to revoke consent', 'bkx-healthcare-practice' ) );
	}

	/**
	 * Get consent history for a user.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_user_consent_history( int $user_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_patient_consents';

		$consents = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, p.post_title as form_name
				 FROM {$table_name} c
				 LEFT JOIN {$wpdb->posts} p ON c.form_id = p.ID
				 WHERE c.user_id = %d
				 ORDER BY c.consented_at DESC",
				$user_id
			),
			ARRAY_A
		);

		return $consents ?: array();
	}
}

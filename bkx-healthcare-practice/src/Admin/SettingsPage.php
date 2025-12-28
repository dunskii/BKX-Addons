<?php
/**
 * Healthcare Settings Page.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

namespace BookingX\HealthcarePractice\Admin;

/**
 * Settings Page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option( 'bkx_healthcare_settings', array() );
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';

		// Handle form submission.
		if ( isset( $_POST['bkx_healthcare_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_healthcare_settings_nonce'] ) ), 'bkx_healthcare_save_settings' ) ) {
			$settings = self::save_settings( $settings );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'bkx-healthcare-practice' ) . '</p></div>';
		}

		$tabs = array(
			'general'       => __( 'General', 'bkx-healthcare-practice' ),
			'intake'        => __( 'Patient Intake', 'bkx-healthcare-practice' ),
			'consent'       => __( 'Consent Forms', 'bkx-healthcare-practice' ),
			'telemedicine'  => __( 'Telemedicine', 'bkx-healthcare-practice' ),
			'insurance'     => __( 'Insurance', 'bkx-healthcare-practice' ),
			'hipaa'         => __( 'HIPAA Compliance', 'bkx-healthcare-practice' ),
			'reminders'     => __( 'Reminders', 'bkx-healthcare-practice' ),
			'license'       => __( 'License', 'bkx-healthcare-practice' ),
		);
		?>
		<div class="wrap bkx-healthcare-settings">
			<h1><?php esc_html_e( 'Healthcare Practice Settings', 'bkx-healthcare-practice' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id ) ); ?>"
					   class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_name ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="" class="bkx-settings-form">
				<?php wp_nonce_field( 'bkx_healthcare_save_settings', 'bkx_healthcare_settings_nonce' ); ?>
				<input type="hidden" name="active_tab" value="<?php echo esc_attr( $active_tab ); ?>">

				<div class="bkx-settings-content">
					<?php
					switch ( $active_tab ) {
						case 'intake':
							self::render_intake_settings( $settings );
							break;
						case 'consent':
							self::render_consent_settings( $settings );
							break;
						case 'telemedicine':
							self::render_telemedicine_settings( $settings );
							break;
						case 'insurance':
							self::render_insurance_settings( $settings );
							break;
						case 'hipaa':
							self::render_hipaa_settings( $settings );
							break;
						case 'reminders':
							self::render_reminder_settings( $settings );
							break;
						case 'license':
							self::render_license_settings();
							break;
						default:
							self::render_general_settings( $settings );
							break;
					}
					?>
				</div>

				<?php if ( 'license' !== $active_tab ) : ?>
					<p class="submit">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Save Settings', 'bkx-healthcare-practice' ); ?>
						</button>
					</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return array Updated settings.
	 */
	private static function save_settings( array $settings ): array {
		if ( ! isset( $_POST['bkx_healthcare_settings'] ) ) {
			return $settings;
		}

		$input = wp_unslash( $_POST['bkx_healthcare_settings'] );

		// Sanitize and merge settings.
		$sanitized = array();

		// General settings.
		$sanitized['enabled'] = isset( $input['enabled'] ) ? 1 : 0;
		$sanitized['practice_type'] = sanitize_text_field( $input['practice_type'] ?? 'general' );

		// Patient intake settings.
		$sanitized['enable_patient_intake'] = isset( $input['enable_patient_intake'] ) ? 1 : 0;
		$sanitized['default_intake_form'] = absint( $input['default_intake_form'] ?? 0 );
		$sanitized['intake_required_before_booking'] = isset( $input['intake_required_before_booking'] ) ? 1 : 0;

		// Consent settings.
		$sanitized['enable_consent_forms'] = isset( $input['enable_consent_forms'] ) ? 1 : 0;
		$sanitized['require_consent_before_booking'] = isset( $input['require_consent_before_booking'] ) ? 1 : 0;
		$sanitized['consent_expiry_reminder_days'] = absint( $input['consent_expiry_reminder_days'] ?? 30 );

		// Telemedicine settings.
		$sanitized['enable_telemedicine'] = isset( $input['enable_telemedicine'] ) ? 1 : 0;
		$sanitized['telemedicine_provider'] = sanitize_text_field( $input['telemedicine_provider'] ?? 'jitsi' );
		$sanitized['jitsi_domain'] = sanitize_text_field( $input['jitsi_domain'] ?? 'meet.jit.si' );
		$sanitized['telemedicine_early_join'] = absint( $input['telemedicine_early_join'] ?? 15 );
		$sanitized['doxy_room_url'] = esc_url_raw( $input['doxy_room_url'] ?? '' );

		// Encrypted API credentials.
		if ( class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();

			if ( ! empty( $input['zoom_api_key'] ) ) {
				$sanitized['zoom_api_key'] = $encryption->encrypt( sanitize_text_field( $input['zoom_api_key'] ) );
			}
			if ( ! empty( $input['zoom_api_secret'] ) ) {
				$sanitized['zoom_api_secret'] = $encryption->encrypt( sanitize_text_field( $input['zoom_api_secret'] ) );
			}
		} else {
			$sanitized['zoom_api_key'] = sanitize_text_field( $input['zoom_api_key'] ?? '' );
			$sanitized['zoom_api_secret'] = sanitize_text_field( $input['zoom_api_secret'] ?? '' );
		}
		$sanitized['zoom_user_id'] = sanitize_text_field( $input['zoom_user_id'] ?? '' );

		// Insurance settings.
		$sanitized['enable_insurance_verification'] = isset( $input['enable_insurance_verification'] ) ? 1 : 0;
		$sanitized['insurance_api_provider'] = sanitize_text_field( $input['insurance_api_provider'] ?? 'manual' );

		// HIPAA settings.
		$sanitized['enable_hipaa_compliance'] = isset( $input['enable_hipaa_compliance'] ) ? 1 : 0;
		$sanitized['patient_data_retention_days'] = absint( $input['patient_data_retention_days'] ?? 2555 );
		$sanitized['enable_audit_logging'] = isset( $input['enable_audit_logging'] ) ? 1 : 0;
		$sanitized['audit_log_retention_days'] = absint( $input['audit_log_retention_days'] ?? 2555 );

		// Reminder settings.
		$sanitized['enable_appointment_reminders'] = isset( $input['enable_appointment_reminders'] ) ? 1 : 0;
		$sanitized['reminder_hours'] = absint( $input['reminder_hours'] ?? 24 );
		$sanitized['reminder_methods'] = isset( $input['reminder_methods'] ) ? array_map( 'sanitize_text_field', (array) $input['reminder_methods'] ) : array( 'email' );

		// Patient portal settings.
		$sanitized['enable_patient_portal'] = isset( $input['enable_patient_portal'] ) ? 1 : 0;
		$sanitized['portal_page_id'] = absint( $input['portal_page_id'] ?? 0 );

		$merged = array_merge( $settings, $sanitized );
		update_option( 'bkx_healthcare_settings', $merged );

		return $merged;
	}

	/**
	 * Render general settings.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function render_general_settings( array $settings ): void {
		$practice_types = array(
			'general'       => __( 'General Practice', 'bkx-healthcare-practice' ),
			'dental'        => __( 'Dental Practice', 'bkx-healthcare-practice' ),
			'mental_health' => __( 'Mental Health / Therapy', 'bkx-healthcare-practice' ),
			'specialist'    => __( 'Medical Specialist', 'bkx-healthcare-practice' ),
			'chiropractic'  => __( 'Chiropractic', 'bkx-healthcare-practice' ),
			'physical_therapy' => __( 'Physical Therapy', 'bkx-healthcare-practice' ),
			'veterinary'    => __( 'Veterinary', 'bkx-healthcare-practice' ),
			'other'         => __( 'Other Healthcare', 'bkx-healthcare-practice' ),
		);
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Healthcare Features', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_healthcare_settings[enabled]" value="1"
							   <?php checked( ! empty( $settings['enabled'] ) ); ?>>
						<?php esc_html_e( 'Enable healthcare practice management features', 'bkx-healthcare-practice' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Practice Type', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<select name="bkx_healthcare_settings[practice_type]">
						<?php foreach ( $practice_types as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"
									<?php selected( $settings['practice_type'] ?? 'general', $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select your practice type to customize features and terminology.', 'bkx-healthcare-practice' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Patient Portal', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_healthcare_settings[enable_patient_portal]" value="1"
							   <?php checked( ! empty( $settings['enable_patient_portal'] ) ); ?>>
						<?php esc_html_e( 'Enable patient portal', 'bkx-healthcare-practice' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Allow patients to view appointments, documents, and manage their profile.', 'bkx-healthcare-practice' ); ?>
					</p>
				</td>
			</tr>
			<tr class="bkx-conditional-row" data-depends-on="enable_patient_portal">
				<th scope="row"><?php esc_html_e( 'Portal Page', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<?php
					wp_dropdown_pages( array(
						'name'              => 'bkx_healthcare_settings[portal_page_id]',
						'selected'          => $settings['portal_page_id'] ?? 0,
						'show_option_none'  => __( 'Select a page', 'bkx-healthcare-practice' ),
						'option_none_value' => 0,
					) );
					?>
					<p class="description">
						<?php esc_html_e( 'Select the page containing the [bkx_patient_portal] shortcode.', 'bkx-healthcare-practice' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render intake settings.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function render_intake_settings( array $settings ): void {
		$intake_forms = get_posts( array(
			'post_type'      => 'bkx_intake_form',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Patient Intake Forms', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_healthcare_settings[enable_patient_intake]" value="1"
							   <?php checked( ! empty( $settings['enable_patient_intake'] ) ); ?>>
						<?php esc_html_e( 'Enable patient intake forms', 'bkx-healthcare-practice' ); ?>
					</label>
				</td>
			</tr>
			<tr class="bkx-conditional-row" data-depends-on="enable_patient_intake">
				<th scope="row"><?php esc_html_e( 'Default Intake Form', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<select name="bkx_healthcare_settings[default_intake_form]">
						<option value="0"><?php esc_html_e( 'None', 'bkx-healthcare-practice' ); ?></option>
						<?php foreach ( $intake_forms as $form ) : ?>
							<option value="<?php echo esc_attr( $form->ID ); ?>"
									<?php selected( $settings['default_intake_form'] ?? 0, $form->ID ); ?>>
								<?php echo esc_html( $form->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select the default intake form for new patients.', 'bkx-healthcare-practice' ); ?>
					</p>
				</td>
			</tr>
			<tr class="bkx-conditional-row" data-depends-on="enable_patient_intake">
				<th scope="row"><?php esc_html_e( 'Required Before Booking', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_healthcare_settings[intake_required_before_booking]" value="1"
							   <?php checked( ! empty( $settings['intake_required_before_booking'] ) ); ?>>
						<?php esc_html_e( 'Require intake form completion before booking', 'bkx-healthcare-practice' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Create Intake Forms', 'bkx-healthcare-practice' ); ?></h3>
		<p>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=bkx_intake_form' ) ); ?>" class="button">
				<?php esc_html_e( 'Add New Intake Form', 'bkx-healthcare-practice' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_intake_form' ) ); ?>" class="button">
				<?php esc_html_e( 'Manage Intake Forms', 'bkx-healthcare-practice' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render consent settings.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function render_consent_settings( array $settings ): void {
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Consent Forms', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_healthcare_settings[enable_consent_forms]" value="1"
							   <?php checked( ! empty( $settings['enable_consent_forms'] ) ); ?>>
						<?php esc_html_e( 'Enable digital consent forms', 'bkx-healthcare-practice' ); ?>
					</label>
				</td>
			</tr>
			<tr class="bkx-conditional-row" data-depends-on="enable_consent_forms">
				<th scope="row"><?php esc_html_e( 'Require Before Booking', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_healthcare_settings[require_consent_before_booking]" value="1"
							   <?php checked( ! empty( $settings['require_consent_before_booking'] ) ); ?>>
						<?php esc_html_e( 'Require consent forms before patients can book', 'bkx-healthcare-practice' ); ?>
					</label>
				</td>
			</tr>
			<tr class="bkx-conditional-row" data-depends-on="enable_consent_forms">
				<th scope="row"><?php esc_html_e( 'Expiry Reminder', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<input type="number" name="bkx_healthcare_settings[consent_expiry_reminder_days]"
						   value="<?php echo esc_attr( $settings['consent_expiry_reminder_days'] ?? 30 ); ?>"
						   min="0" max="90" class="small-text">
					<?php esc_html_e( 'days before expiration', 'bkx-healthcare-practice' ); ?>
					<p class="description">
						<?php esc_html_e( 'Send reminder to patients when their consent is about to expire.', 'bkx-healthcare-practice' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Manage Consent Forms', 'bkx-healthcare-practice' ); ?></h3>
		<p>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=bkx_consent_form' ) ); ?>" class="button">
				<?php esc_html_e( 'Add New Consent Form', 'bkx-healthcare-practice' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_consent_form' ) ); ?>" class="button">
				<?php esc_html_e( 'Manage Consent Forms', 'bkx-healthcare-practice' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render telemedicine settings.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function render_telemedicine_settings( array $settings ): void {
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Telemedicine', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_healthcare_settings[enable_telemedicine]" value="1"
							   <?php checked( ! empty( $settings['enable_telemedicine'] ) ); ?>>
						<?php esc_html_e( 'Enable telemedicine appointments', 'bkx-healthcare-practice' ); ?>
					</label>
				</td>
			</tr>
			<tr class="bkx-conditional-row" data-depends-on="enable_telemedicine">
				<th scope="row"><?php esc_html_e( 'Video Provider', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<select name="bkx_healthcare_settings[telemedicine_provider]" class="bkx-provider-select">
						<option value="jitsi" <?php selected( $settings['telemedicine_provider'] ?? 'jitsi', 'jitsi' ); ?>>
							<?php esc_html_e( 'Jitsi Meet (Free)', 'bkx-healthcare-practice' ); ?>
						</option>
						<option value="zoom" <?php selected( $settings['telemedicine_provider'] ?? '', 'zoom' ); ?>>
							<?php esc_html_e( 'Zoom (Requires API Credentials)', 'bkx-healthcare-practice' ); ?>
						</option>
						<option value="doxy" <?php selected( $settings['telemedicine_provider'] ?? '', 'doxy' ); ?>>
							<?php esc_html_e( 'Doxy.me (External Link)', 'bkx-healthcare-practice' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr class="bkx-conditional-row" data-depends-on="enable_telemedicine">
				<th scope="row"><?php esc_html_e( 'Early Join Time', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<input type="number" name="bkx_healthcare_settings[telemedicine_early_join]"
						   value="<?php echo esc_attr( $settings['telemedicine_early_join'] ?? 15 ); ?>"
						   min="5" max="60" class="small-text">
					<?php esc_html_e( 'minutes before appointment', 'bkx-healthcare-practice' ); ?>
				</td>
			</tr>
		</table>

		<!-- Jitsi Settings -->
		<div class="bkx-provider-settings bkx-provider-jitsi">
			<h3><?php esc_html_e( 'Jitsi Settings', 'bkx-healthcare-practice' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Jitsi Domain', 'bkx-healthcare-practice' ); ?></th>
					<td>
						<input type="text" name="bkx_healthcare_settings[jitsi_domain]"
							   value="<?php echo esc_attr( $settings['jitsi_domain'] ?? 'meet.jit.si' ); ?>"
							   class="regular-text">
						<p class="description">
							<?php esc_html_e( 'Use meet.jit.si for free public servers or enter your own Jitsi server domain.', 'bkx-healthcare-practice' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Zoom Settings -->
		<div class="bkx-provider-settings bkx-provider-zoom" style="display:none;">
			<h3><?php esc_html_e( 'Zoom Settings', 'bkx-healthcare-practice' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'API Key', 'bkx-healthcare-practice' ); ?></th>
					<td>
						<input type="password" name="bkx_healthcare_settings[zoom_api_key]"
							   value="" class="regular-text" placeholder="<?php esc_attr_e( 'Enter new key to update', 'bkx-healthcare-practice' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'API Secret', 'bkx-healthcare-practice' ); ?></th>
					<td>
						<input type="password" name="bkx_healthcare_settings[zoom_api_secret]"
							   value="" class="regular-text" placeholder="<?php esc_attr_e( 'Enter new secret to update', 'bkx-healthcare-practice' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Host User ID', 'bkx-healthcare-practice' ); ?></th>
					<td>
						<input type="text" name="bkx_healthcare_settings[zoom_user_id]"
							   value="<?php echo esc_attr( $settings['zoom_user_id'] ?? '' ); ?>" class="regular-text">
					</td>
				</tr>
			</table>
		</div>

		<!-- Doxy Settings -->
		<div class="bkx-provider-settings bkx-provider-doxy" style="display:none;">
			<h3><?php esc_html_e( 'Doxy.me Settings', 'bkx-healthcare-practice' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Waiting Room URL', 'bkx-healthcare-practice' ); ?></th>
					<td>
						<input type="url" name="bkx_healthcare_settings[doxy_room_url]"
							   value="<?php echo esc_attr( $settings['doxy_room_url'] ?? '' ); ?>" class="regular-text">
						<p class="description">
							<?php esc_html_e( 'Your Doxy.me waiting room URL (e.g., https://doxy.me/yourpractice)', 'bkx-healthcare-practice' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render insurance settings.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function render_insurance_settings( array $settings ): void {
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Insurance Verification', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_healthcare_settings[enable_insurance_verification]" value="1"
							   <?php checked( ! empty( $settings['enable_insurance_verification'] ) ); ?>>
						<?php esc_html_e( 'Enable insurance eligibility verification', 'bkx-healthcare-practice' ); ?>
					</label>
				</td>
			</tr>
			<tr class="bkx-conditional-row" data-depends-on="enable_insurance_verification">
				<th scope="row"><?php esc_html_e( 'Verification Method', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<select name="bkx_healthcare_settings[insurance_api_provider]">
						<option value="manual" <?php selected( $settings['insurance_api_provider'] ?? 'manual', 'manual' ); ?>>
							<?php esc_html_e( 'Manual Verification', 'bkx-healthcare-practice' ); ?>
						</option>
						<option value="availity" <?php selected( $settings['insurance_api_provider'] ?? '', 'availity' ); ?>>
							<?php esc_html_e( 'Availity API', 'bkx-healthcare-practice' ); ?>
						</option>
						<option value="change_healthcare" <?php selected( $settings['insurance_api_provider'] ?? '', 'change_healthcare' ); ?>>
							<?php esc_html_e( 'Change Healthcare API', 'bkx-healthcare-practice' ); ?>
						</option>
					</select>
				</td>
			</tr>
		</table>

		<p class="description">
			<?php esc_html_e( 'Configure your insurance verification API credentials in the HIPAA Compliance tab.', 'bkx-healthcare-practice' ); ?>
		</p>
		<?php
	}

	/**
	 * Render HIPAA settings.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function render_hipaa_settings( array $settings ): void {
		?>
		<div class="bkx-hipaa-notice">
			<p>
				<strong><?php esc_html_e( 'HIPAA Compliance Notice:', 'bkx-healthcare-practice' ); ?></strong>
				<?php esc_html_e( 'This add-on provides tools to help with HIPAA compliance, but proper compliance requires appropriate policies, procedures, and staff training. Consult with a HIPAA compliance expert for your specific situation.', 'bkx-healthcare-practice' ); ?>
			</p>
		</div>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'HIPAA Features', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_healthcare_settings[enable_hipaa_compliance]" value="1"
							   <?php checked( ! empty( $settings['enable_hipaa_compliance'] ) ); ?>>
						<?php esc_html_e( 'Enable HIPAA compliance features', 'bkx-healthcare-practice' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enables data encryption, audit logging, and access controls.', 'bkx-healthcare-practice' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Audit Logging', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_healthcare_settings[enable_audit_logging]" value="1"
							   <?php checked( ! empty( $settings['enable_audit_logging'] ) ); ?>>
						<?php esc_html_e( 'Enable HIPAA audit logging', 'bkx-healthcare-practice' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Logs all access to patient data for compliance purposes.', 'bkx-healthcare-practice' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Data Retention', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<input type="number" name="bkx_healthcare_settings[patient_data_retention_days]"
						   value="<?php echo esc_attr( $settings['patient_data_retention_days'] ?? 2555 ); ?>"
						   min="365" class="small-text">
					<?php esc_html_e( 'days', 'bkx-healthcare-practice' ); ?>
					<p class="description">
						<?php esc_html_e( 'HIPAA requires medical records to be retained for at least 6 years (2190 days). State laws may require longer.', 'bkx-healthcare-practice' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Audit Log Retention', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<input type="number" name="bkx_healthcare_settings[audit_log_retention_days]"
						   value="<?php echo esc_attr( $settings['audit_log_retention_days'] ?? 2555 ); ?>"
						   min="365" class="small-text">
					<?php esc_html_e( 'days', 'bkx-healthcare-practice' ); ?>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'View Audit Logs', 'bkx-healthcare-practice' ); ?></h3>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hipaa-audit' ) ); ?>" class="button">
				<?php esc_html_e( 'View HIPAA Audit Log', 'bkx-healthcare-practice' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Render reminder settings.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function render_reminder_settings( array $settings ): void {
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Appointment Reminders', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_healthcare_settings[enable_appointment_reminders]" value="1"
							   <?php checked( ! empty( $settings['enable_appointment_reminders'] ) ); ?>>
						<?php esc_html_e( 'Enable appointment reminders', 'bkx-healthcare-practice' ); ?>
					</label>
				</td>
			</tr>
			<tr class="bkx-conditional-row" data-depends-on="enable_appointment_reminders">
				<th scope="row"><?php esc_html_e( 'Reminder Time', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<input type="number" name="bkx_healthcare_settings[reminder_hours]"
						   value="<?php echo esc_attr( $settings['reminder_hours'] ?? 24 ); ?>"
						   min="1" max="168" class="small-text">
					<?php esc_html_e( 'hours before appointment', 'bkx-healthcare-practice' ); ?>
				</td>
			</tr>
			<tr class="bkx-conditional-row" data-depends-on="enable_appointment_reminders">
				<th scope="row"><?php esc_html_e( 'Reminder Methods', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<?php
					$methods = $settings['reminder_methods'] ?? array( 'email' );
					$available_methods = array(
						'email' => __( 'Email', 'bkx-healthcare-practice' ),
						'sms'   => __( 'SMS (requires Twilio add-on)', 'bkx-healthcare-practice' ),
					);
					foreach ( $available_methods as $value => $label ) :
						?>
						<label style="display: block; margin-bottom: 5px;">
							<input type="checkbox" name="bkx_healthcare_settings[reminder_methods][]"
								   value="<?php echo esc_attr( $value ); ?>"
								   <?php checked( in_array( $value, $methods, true ) ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render license settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function render_license_settings(): void {
		$license_key    = get_option( 'bkx_healthcare_practice_license_key', '' );
		$license_status = get_option( 'bkx_healthcare_practice_license_status', '' );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'License Key', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<input type="password" id="bkx_healthcare_license_key" class="regular-text"
						   value="<?php echo esc_attr( $license_key ); ?>">
					<button type="button" class="button bkx-activate-license">
						<?php echo 'valid' === $license_status ? esc_html__( 'Deactivate', 'bkx-healthcare-practice' ) : esc_html__( 'Activate', 'bkx-healthcare-practice' ); ?>
					</button>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'bkx-healthcare-practice' ); ?></th>
				<td>
					<?php if ( 'valid' === $license_status ) : ?>
						<span class="bkx-license-active"><?php esc_html_e( 'Active', 'bkx-healthcare-practice' ); ?></span>
					<?php else : ?>
						<span class="bkx-license-inactive"><?php esc_html_e( 'Inactive', 'bkx-healthcare-practice' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}
}

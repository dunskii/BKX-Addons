<?php
/**
 * Settings Page for Legal & Professional Services.
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
 * Settings Page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Settings option key.
	 *
	 * @var string
	 */
	private string $option_key = 'bkx_legal_settings';

	/**
	 * Initialize the settings page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add menu item.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Legal & Professional Settings', 'bkx-legal-professional' ),
			__( 'Legal Settings', 'bkx-legal-professional' ),
			'manage_options',
			'bkx-legal-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'bkx_booking_page_bkx-legal-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-legal-admin',
			BKX_LEGAL_URL . 'assets/css/admin.css',
			array(),
			BKX_LEGAL_VERSION
		);

		wp_enqueue_script(
			'bkx-legal-admin',
			BKX_LEGAL_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_LEGAL_VERSION,
			true
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'bkx_legal_settings_group',
			$this->option_key,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input values.
	 * @return array
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// General settings.
		$sanitized['enabled']        = ! empty( $input['enabled'] ) ? 1 : 0;
		$sanitized['practice_type']  = sanitize_text_field( $input['practice_type'] ?? 'law_firm' );
		$sanitized['firm_name']      = sanitize_text_field( $input['firm_name'] ?? '' );
		$sanitized['bar_number']     = sanitize_text_field( $input['bar_number'] ?? '' );
		$sanitized['jurisdiction']   = sanitize_text_field( $input['jurisdiction'] ?? '' );

		// Feature toggles.
		$sanitized['enable_client_intake']        = ! empty( $input['enable_client_intake'] ) ? 1 : 0;
		$sanitized['enable_case_management']      = ! empty( $input['enable_case_management'] ) ? 1 : 0;
		$sanitized['enable_document_management']  = ! empty( $input['enable_document_management'] ) ? 1 : 0;
		$sanitized['enable_conflict_check']       = ! empty( $input['enable_conflict_check'] ) ? 1 : 0;
		$sanitized['enable_retainer_agreements']  = ! empty( $input['enable_retainer_agreements'] ) ? 1 : 0;
		$sanitized['enable_billing_tracking']     = ! empty( $input['enable_billing_tracking'] ) ? 1 : 0;
		$sanitized['enable_client_portal']        = ! empty( $input['enable_client_portal'] ) ? 1 : 0;
		$sanitized['enable_matter_types']         = ! empty( $input['enable_matter_types'] ) ? 1 : 0;

		// Scheduling settings.
		$sanitized['consultation_fee']              = floatval( $input['consultation_fee'] ?? 0 );
		$sanitized['default_consultation_duration'] = absint( $input['default_consultation_duration'] ?? 60 );
		$sanitized['require_intake_before_booking'] = ! empty( $input['require_intake_before_booking'] ) ? 1 : 0;
		$sanitized['enable_confidentiality_notice'] = ! empty( $input['enable_confidentiality_notice'] ) ? 1 : 0;

		// Billing settings.
		$sanitized['time_tracking_increment']  = absint( $input['time_tracking_increment'] ?? 6 );
		$sanitized['default_hourly_rate']      = floatval( $input['default_hourly_rate'] ?? 250 );
		$sanitized['auto_time_entry']          = ! empty( $input['auto_time_entry'] ) ? 1 : 0;
		$sanitized['invoice_prefix']           = sanitize_text_field( $input['invoice_prefix'] ?? 'INV' );
		$sanitized['matter_number_prefix']     = sanitize_text_field( $input['matter_number_prefix'] ?? 'M' );
		$sanitized['invoice_due_days']         = absint( $input['invoice_due_days'] ?? 30 );
		$sanitized['invoice_footer']           = wp_kses_post( $input['invoice_footer'] ?? '' );

		// Trust accounting.
		$sanitized['enable_trust_accounting']  = ! empty( $input['enable_trust_accounting'] ) ? 1 : 0;
		$sanitized['trust_account_name']       = sanitize_text_field( $input['trust_account_name'] ?? '' );
		$sanitized['trust_account_number']     = sanitize_text_field( $input['trust_account_number'] ?? '' );
		$sanitized['trust_bank_name']          = sanitize_text_field( $input['trust_bank_name'] ?? '' );

		// Conflict check settings.
		$sanitized['conflict_check_required']      = ! empty( $input['conflict_check_required'] ) ? 1 : 0;
		$sanitized['conflict_check_notify_email']  = sanitize_email( $input['conflict_check_notify_email'] ?? '' );
		$sanitized['conflict_sensitivity']         = floatval( $input['conflict_sensitivity'] ?? 0.8 );

		// Document settings.
		$sanitized['max_upload_size']           = absint( $input['max_upload_size'] ?? 10 );
		$sanitized['require_document_signing']  = ! empty( $input['require_document_signing'] ) ? 1 : 0;

		// Client portal.
		$sanitized['portal_page_id']        = absint( $input['portal_page_id'] ?? 0 );
		$sanitized['allow_client_documents'] = ! empty( $input['allow_client_documents'] ) ? 1 : 0;
		$sanitized['allow_client_messages']  = ! empty( $input['allow_client_messages'] ) ? 1 : 0;

		// Email templates.
		$sanitized['intake_confirmation_subject'] = sanitize_text_field( $input['intake_confirmation_subject'] ?? '' );
		$sanitized['intake_confirmation_body']    = wp_kses_post( $input['intake_confirmation_body'] ?? '' );
		$sanitized['retainer_email_subject']      = sanitize_text_field( $input['retainer_email_subject'] ?? '' );
		$sanitized['retainer_email_body']         = wp_kses_post( $input['retainer_email_body'] ?? '' );

		// Confidentiality notice.
		$sanitized['confidentiality_notice_text'] = wp_kses_post( $input['confidentiality_notice_text'] ?? '' );

		// License.
		$sanitized['license_key']    = sanitize_text_field( $input['license_key'] ?? '' );
		$sanitized['license_status'] = sanitize_text_field( $input['license_status'] ?? '' );

		return $sanitized;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$settings    = get_option( $this->option_key, array() );
		$active_tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tabs        = $this->get_tabs();
		?>
		<div class="wrap bkx-legal-settings">
			<h1><?php esc_html_e( 'Legal & Professional Services Settings', 'bkx-legal-professional' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id ) ); ?>"
					   class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'bkx_legal_settings_group' ); ?>

				<?php
				switch ( $active_tab ) {
					case 'general':
						$this->render_general_tab( $settings );
						break;
					case 'features':
						$this->render_features_tab( $settings );
						break;
					case 'scheduling':
						$this->render_scheduling_tab( $settings );
						break;
					case 'billing':
						$this->render_billing_tab( $settings );
						break;
					case 'trust':
						$this->render_trust_tab( $settings );
						break;
					case 'conflicts':
						$this->render_conflicts_tab( $settings );
						break;
					case 'documents':
						$this->render_documents_tab( $settings );
						break;
					case 'portal':
						$this->render_portal_tab( $settings );
						break;
					case 'emails':
						$this->render_emails_tab( $settings );
						break;
					case 'license':
						$this->render_license_tab( $settings );
						break;
				}
				?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get settings tabs.
	 *
	 * @return array
	 */
	private function get_tabs(): array {
		return array(
			'general'    => __( 'General', 'bkx-legal-professional' ),
			'features'   => __( 'Features', 'bkx-legal-professional' ),
			'scheduling' => __( 'Scheduling', 'bkx-legal-professional' ),
			'billing'    => __( 'Billing', 'bkx-legal-professional' ),
			'trust'      => __( 'Trust Accounting', 'bkx-legal-professional' ),
			'conflicts'  => __( 'Conflict Check', 'bkx-legal-professional' ),
			'documents'  => __( 'Documents', 'bkx-legal-professional' ),
			'portal'     => __( 'Client Portal', 'bkx-legal-professional' ),
			'emails'     => __( 'Email Templates', 'bkx-legal-professional' ),
			'license'    => __( 'License', 'bkx-legal-professional' ),
		);
	}

	/**
	 * Render general tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_general_tab( array $settings ): void {
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="enabled"><?php esc_html_e( 'Enable Add-on', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="checkbox" id="enabled" name="<?php echo esc_attr( $this->option_key ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ?? 1 ); ?>>
					<p class="description"><?php esc_html_e( 'Enable or disable the Legal & Professional Services add-on.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="practice_type"><?php esc_html_e( 'Practice Type', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<select id="practice_type" name="<?php echo esc_attr( $this->option_key ); ?>[practice_type]">
						<option value="law_firm" <?php selected( $settings['practice_type'] ?? '', 'law_firm' ); ?>><?php esc_html_e( 'Law Firm', 'bkx-legal-professional' ); ?></option>
						<option value="solo_attorney" <?php selected( $settings['practice_type'] ?? '', 'solo_attorney' ); ?>><?php esc_html_e( 'Solo Attorney', 'bkx-legal-professional' ); ?></option>
						<option value="consulting" <?php selected( $settings['practice_type'] ?? '', 'consulting' ); ?>><?php esc_html_e( 'Consulting Firm', 'bkx-legal-professional' ); ?></option>
						<option value="accounting" <?php selected( $settings['practice_type'] ?? '', 'accounting' ); ?>><?php esc_html_e( 'Accounting Firm', 'bkx-legal-professional' ); ?></option>
						<option value="financial" <?php selected( $settings['practice_type'] ?? '', 'financial' ); ?>><?php esc_html_e( 'Financial Services', 'bkx-legal-professional' ); ?></option>
						<option value="other" <?php selected( $settings['practice_type'] ?? '', 'other' ); ?>><?php esc_html_e( 'Other Professional Services', 'bkx-legal-professional' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="firm_name"><?php esc_html_e( 'Firm Name', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="text" id="firm_name" name="<?php echo esc_attr( $this->option_key ); ?>[firm_name]" value="<?php echo esc_attr( $settings['firm_name'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="bar_number"><?php esc_html_e( 'Bar Number / License', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="text" id="bar_number" name="<?php echo esc_attr( $this->option_key ); ?>[bar_number]" value="<?php echo esc_attr( $settings['bar_number'] ?? '' ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Primary bar number or professional license number.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="jurisdiction"><?php esc_html_e( 'Jurisdiction', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="text" id="jurisdiction" name="<?php echo esc_attr( $this->option_key ); ?>[jurisdiction]" value="<?php echo esc_attr( $settings['jurisdiction'] ?? '' ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Primary jurisdiction (e.g., California, New York).', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render features tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_features_tab( array $settings ): void {
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Core Features', 'bkx-legal-professional' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enable_client_intake]" value="1" <?php checked( $settings['enable_client_intake'] ?? 1 ); ?>>
							<?php esc_html_e( 'Client Intake Forms', 'bkx-legal-professional' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enable_case_management]" value="1" <?php checked( $settings['enable_case_management'] ?? 1 ); ?>>
							<?php esc_html_e( 'Case/Matter Management', 'bkx-legal-professional' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enable_document_management]" value="1" <?php checked( $settings['enable_document_management'] ?? 1 ); ?>>
							<?php esc_html_e( 'Document Management', 'bkx-legal-professional' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enable_conflict_check]" value="1" <?php checked( $settings['enable_conflict_check'] ?? 1 ); ?>>
							<?php esc_html_e( 'Conflict Checking', 'bkx-legal-professional' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enable_retainer_agreements]" value="1" <?php checked( $settings['enable_retainer_agreements'] ?? 1 ); ?>>
							<?php esc_html_e( 'Retainer Agreements', 'bkx-legal-professional' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enable_billing_tracking]" value="1" <?php checked( $settings['enable_billing_tracking'] ?? 1 ); ?>>
							<?php esc_html_e( 'Time Tracking & Billing', 'bkx-legal-professional' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enable_client_portal]" value="1" <?php checked( $settings['enable_client_portal'] ?? 1 ); ?>>
							<?php esc_html_e( 'Client Portal', 'bkx-legal-professional' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enable_matter_types]" value="1" <?php checked( $settings['enable_matter_types'] ?? 1 ); ?>>
							<?php esc_html_e( 'Matter Types & Practice Areas', 'bkx-legal-professional' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render scheduling tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_scheduling_tab( array $settings ): void {
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="consultation_fee"><?php esc_html_e( 'Initial Consultation Fee', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="number" id="consultation_fee" name="<?php echo esc_attr( $this->option_key ); ?>[consultation_fee]" value="<?php echo esc_attr( $settings['consultation_fee'] ?? 0 ); ?>" min="0" step="0.01" class="small-text">
					<p class="description"><?php esc_html_e( 'Set to 0 for free consultations.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="default_consultation_duration"><?php esc_html_e( 'Default Consultation Duration', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<select id="default_consultation_duration" name="<?php echo esc_attr( $this->option_key ); ?>[default_consultation_duration]">
						<option value="15" <?php selected( $settings['default_consultation_duration'] ?? 60, 15 ); ?>><?php esc_html_e( '15 minutes', 'bkx-legal-professional' ); ?></option>
						<option value="30" <?php selected( $settings['default_consultation_duration'] ?? 60, 30 ); ?>><?php esc_html_e( '30 minutes', 'bkx-legal-professional' ); ?></option>
						<option value="45" <?php selected( $settings['default_consultation_duration'] ?? 60, 45 ); ?>><?php esc_html_e( '45 minutes', 'bkx-legal-professional' ); ?></option>
						<option value="60" <?php selected( $settings['default_consultation_duration'] ?? 60, 60 ); ?>><?php esc_html_e( '1 hour', 'bkx-legal-professional' ); ?></option>
						<option value="90" <?php selected( $settings['default_consultation_duration'] ?? 60, 90 ); ?>><?php esc_html_e( '1.5 hours', 'bkx-legal-professional' ); ?></option>
						<option value="120" <?php selected( $settings['default_consultation_duration'] ?? 60, 120 ); ?>><?php esc_html_e( '2 hours', 'bkx-legal-professional' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Intake Requirements', 'bkx-legal-professional' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[require_intake_before_booking]" value="1" <?php checked( $settings['require_intake_before_booking'] ?? 1 ); ?>>
						<?php esc_html_e( 'Require intake form before booking', 'bkx-legal-professional' ); ?>
					</label><br>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enable_confidentiality_notice]" value="1" <?php checked( $settings['enable_confidentiality_notice'] ?? 1 ); ?>>
						<?php esc_html_e( 'Show confidentiality notice during booking', 'bkx-legal-professional' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="confidentiality_notice_text"><?php esc_html_e( 'Confidentiality Notice', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<?php
					wp_editor(
						$settings['confidentiality_notice_text'] ?? '',
						'confidentiality_notice_text',
						array(
							'textarea_name' => $this->option_key . '[confidentiality_notice_text]',
							'textarea_rows' => 6,
							'media_buttons' => false,
						)
					);
					?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render billing tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_billing_tab( array $settings ): void {
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="time_tracking_increment"><?php esc_html_e( 'Billing Increment', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<select id="time_tracking_increment" name="<?php echo esc_attr( $this->option_key ); ?>[time_tracking_increment]">
						<option value="1" <?php selected( $settings['time_tracking_increment'] ?? 6, 1 ); ?>><?php esc_html_e( '1 minute', 'bkx-legal-professional' ); ?></option>
						<option value="6" <?php selected( $settings['time_tracking_increment'] ?? 6, 6 ); ?>><?php esc_html_e( '6 minutes (0.1 hour)', 'bkx-legal-professional' ); ?></option>
						<option value="10" <?php selected( $settings['time_tracking_increment'] ?? 6, 10 ); ?>><?php esc_html_e( '10 minutes', 'bkx-legal-professional' ); ?></option>
						<option value="15" <?php selected( $settings['time_tracking_increment'] ?? 6, 15 ); ?>><?php esc_html_e( '15 minutes (0.25 hour)', 'bkx-legal-professional' ); ?></option>
						<option value="30" <?php selected( $settings['time_tracking_increment'] ?? 6, 30 ); ?>><?php esc_html_e( '30 minutes (0.5 hour)', 'bkx-legal-professional' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Time entries will be rounded up to the nearest increment.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="default_hourly_rate"><?php esc_html_e( 'Default Hourly Rate', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="number" id="default_hourly_rate" name="<?php echo esc_attr( $this->option_key ); ?>[default_hourly_rate]" value="<?php echo esc_attr( $settings['default_hourly_rate'] ?? 250 ); ?>" min="0" step="0.01" class="small-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="auto_time_entry"><?php esc_html_e( 'Auto Time Entry', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="auto_time_entry" name="<?php echo esc_attr( $this->option_key ); ?>[auto_time_entry]" value="1" <?php checked( $settings['auto_time_entry'] ?? 0 ); ?>>
						<?php esc_html_e( 'Automatically create time entries from completed appointments', 'bkx-legal-professional' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="matter_number_prefix"><?php esc_html_e( 'Matter Number Prefix', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="text" id="matter_number_prefix" name="<?php echo esc_attr( $this->option_key ); ?>[matter_number_prefix]" value="<?php echo esc_attr( $settings['matter_number_prefix'] ?? 'M' ); ?>" class="small-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="invoice_prefix"><?php esc_html_e( 'Invoice Prefix', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="text" id="invoice_prefix" name="<?php echo esc_attr( $this->option_key ); ?>[invoice_prefix]" value="<?php echo esc_attr( $settings['invoice_prefix'] ?? 'INV' ); ?>" class="small-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="invoice_due_days"><?php esc_html_e( 'Invoice Due Days', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="number" id="invoice_due_days" name="<?php echo esc_attr( $this->option_key ); ?>[invoice_due_days]" value="<?php echo esc_attr( $settings['invoice_due_days'] ?? 30 ); ?>" min="1" class="small-text">
					<p class="description"><?php esc_html_e( 'Number of days until invoice is due.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="invoice_footer"><?php esc_html_e( 'Invoice Footer', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<textarea id="invoice_footer" name="<?php echo esc_attr( $this->option_key ); ?>[invoice_footer]" rows="4" class="large-text"><?php echo esc_textarea( $settings['invoice_footer'] ?? '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Text to appear at the bottom of invoices (e.g., payment terms, bank details).', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render trust accounting tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_trust_tab( array $settings ): void {
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="enable_trust_accounting"><?php esc_html_e( 'Enable Trust Accounting', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="enable_trust_accounting" name="<?php echo esc_attr( $this->option_key ); ?>[enable_trust_accounting]" value="1" <?php checked( $settings['enable_trust_accounting'] ?? 0 ); ?>>
						<?php esc_html_e( 'Enable IOLTA/Trust account tracking', 'bkx-legal-professional' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="trust_account_name"><?php esc_html_e( 'Trust Account Name', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="text" id="trust_account_name" name="<?php echo esc_attr( $this->option_key ); ?>[trust_account_name]" value="<?php echo esc_attr( $settings['trust_account_name'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="trust_bank_name"><?php esc_html_e( 'Bank Name', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="text" id="trust_bank_name" name="<?php echo esc_attr( $this->option_key ); ?>[trust_bank_name]" value="<?php echo esc_attr( $settings['trust_bank_name'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="trust_account_number"><?php esc_html_e( 'Account Number', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="text" id="trust_account_number" name="<?php echo esc_attr( $this->option_key ); ?>[trust_account_number]" value="<?php echo esc_attr( $settings['trust_account_number'] ?? '' ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'This information is stored securely and used for reporting.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render conflict check tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_conflicts_tab( array $settings ): void {
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="conflict_check_required"><?php esc_html_e( 'Require Conflict Check', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="conflict_check_required" name="<?php echo esc_attr( $this->option_key ); ?>[conflict_check_required]" value="1" <?php checked( $settings['conflict_check_required'] ?? 1 ); ?>>
						<?php esc_html_e( 'Require conflict check before opening new matters', 'bkx-legal-professional' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="conflict_check_notify_email"><?php esc_html_e( 'Conflict Notification Email', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="email" id="conflict_check_notify_email" name="<?php echo esc_attr( $this->option_key ); ?>[conflict_check_notify_email]" value="<?php echo esc_attr( $settings['conflict_check_notify_email'] ?? '' ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Send conflict alerts to this email (in addition to admin).', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="conflict_sensitivity"><?php esc_html_e( 'Match Sensitivity', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<select id="conflict_sensitivity" name="<?php echo esc_attr( $this->option_key ); ?>[conflict_sensitivity]">
						<option value="0.9" <?php selected( $settings['conflict_sensitivity'] ?? 0.8, '0.9' ); ?>><?php esc_html_e( 'High (90% match required)', 'bkx-legal-professional' ); ?></option>
						<option value="0.8" <?php selected( $settings['conflict_sensitivity'] ?? 0.8, '0.8' ); ?>><?php esc_html_e( 'Medium (80% match required)', 'bkx-legal-professional' ); ?></option>
						<option value="0.7" <?php selected( $settings['conflict_sensitivity'] ?? 0.8, '0.7' ); ?>><?php esc_html_e( 'Low (70% match required)', 'bkx-legal-professional' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Lower sensitivity means more potential matches will be flagged.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render documents tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_documents_tab( array $settings ): void {
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="max_upload_size"><?php esc_html_e( 'Maximum Upload Size (MB)', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="number" id="max_upload_size" name="<?php echo esc_attr( $this->option_key ); ?>[max_upload_size]" value="<?php echo esc_attr( $settings['max_upload_size'] ?? 10 ); ?>" min="1" max="100" class="small-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="require_document_signing"><?php esc_html_e( 'Document Signing', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="require_document_signing" name="<?php echo esc_attr( $this->option_key ); ?>[require_document_signing]" value="1" <?php checked( $settings['require_document_signing'] ?? 0 ); ?>>
						<?php esc_html_e( 'Require digital signature on retainer agreements', 'bkx-legal-professional' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render portal tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_portal_tab( array $settings ): void {
		$pages = get_pages();
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="portal_page_id"><?php esc_html_e( 'Portal Page', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<select id="portal_page_id" name="<?php echo esc_attr( $this->option_key ); ?>[portal_page_id]">
						<option value=""><?php esc_html_e( '— Select Page —', 'bkx-legal-professional' ); ?></option>
						<?php foreach ( $pages as $page ) : ?>
							<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $settings['portal_page_id'] ?? 0, $page->ID ); ?>>
								<?php echo esc_html( $page->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Select the page containing the [bkx_legal_client_portal] shortcode.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Portal Features', 'bkx-legal-professional' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[allow_client_documents]" value="1" <?php checked( $settings['allow_client_documents'] ?? 1 ); ?>>
						<?php esc_html_e( 'Allow clients to upload documents', 'bkx-legal-professional' ); ?>
					</label><br>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[allow_client_messages]" value="1" <?php checked( $settings['allow_client_messages'] ?? 1 ); ?>>
						<?php esc_html_e( 'Allow clients to send messages', 'bkx-legal-professional' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render emails tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_emails_tab( array $settings ): void {
		?>
		<h3><?php esc_html_e( 'Intake Confirmation Email', 'bkx-legal-professional' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="intake_confirmation_subject"><?php esc_html_e( 'Subject', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="text" id="intake_confirmation_subject" name="<?php echo esc_attr( $this->option_key ); ?>[intake_confirmation_subject]" value="<?php echo esc_attr( $settings['intake_confirmation_subject'] ?? '' ); ?>" class="large-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="intake_confirmation_body"><?php esc_html_e( 'Body', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<?php
					wp_editor(
						$settings['intake_confirmation_body'] ?? '',
						'intake_confirmation_body',
						array(
							'textarea_name' => $this->option_key . '[intake_confirmation_body]',
							'textarea_rows' => 8,
							'media_buttons' => false,
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'Available placeholders: {client_name}, {firm_name}, {consultation_date}', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Retainer Email', 'bkx-legal-professional' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="retainer_email_subject"><?php esc_html_e( 'Subject', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="text" id="retainer_email_subject" name="<?php echo esc_attr( $this->option_key ); ?>[retainer_email_subject]" value="<?php echo esc_attr( $settings['retainer_email_subject'] ?? '' ); ?>" class="large-text">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="retainer_email_body"><?php esc_html_e( 'Body', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<?php
					wp_editor(
						$settings['retainer_email_body'] ?? '',
						'retainer_email_body',
						array(
							'textarea_name' => $this->option_key . '[retainer_email_body]',
							'textarea_rows' => 8,
							'media_buttons' => false,
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'Available placeholders: {client_name}, {matter_name}, {signature_url}, {firm_name}', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render license tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_license_tab( array $settings ): void {
		$status = $settings['license_status'] ?? '';
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="license_key"><?php esc_html_e( 'License Key', 'bkx-legal-professional' ); ?></label>
				</th>
				<td>
					<input type="text" id="license_key" name="<?php echo esc_attr( $this->option_key ); ?>[license_key]" value="<?php echo esc_attr( $settings['license_key'] ?? '' ); ?>" class="regular-text">
					<?php if ( 'valid' === $status ) : ?>
						<span class="bkx-license-status valid"><?php esc_html_e( 'Active', 'bkx-legal-professional' ); ?></span>
					<?php elseif ( ! empty( $status ) ) : ?>
						<span class="bkx-license-status invalid"><?php echo esc_html( ucfirst( $status ) ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"></th>
				<td>
					<button type="button" class="button" id="bkx-activate-license"><?php esc_html_e( 'Activate License', 'bkx-legal-professional' ); ?></button>
					<button type="button" class="button" id="bkx-deactivate-license"><?php esc_html_e( 'Deactivate License', 'bkx-legal-professional' ); ?></button>
				</td>
			</tr>
		</table>
		<?php
	}
}

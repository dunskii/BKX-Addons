<?php
/**
 * BookingX settings tab integration.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_gdpr_compliance_settings', array() );
?>

<div class="bkx-gdpr-settings-section">
	<h2><?php esc_html_e( 'GDPR/CCPA Compliance Settings', 'bkx-gdpr-compliance' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure privacy and compliance settings for your booking system.', 'bkx-gdpr-compliance' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-gdpr-compliance' ) ); ?>">
			<?php esc_html_e( 'View full GDPR dashboard →', 'bkx-gdpr-compliance' ); ?>
		</a>
	</p>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="bkx_gdpr_enable_cookie_banner"><?php esc_html_e( 'Cookie Consent Banner', 'bkx-gdpr-compliance' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="bkx_gdpr_enable_cookie_banner" name="bkx_gdpr_compliance_settings[enable_cookie_banner]" value="1"
						<?php checked( ! empty( $settings['enable_cookie_banner'] ) ); ?>>
					<?php esc_html_e( 'Show cookie consent banner to visitors', 'bkx-gdpr-compliance' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Display a GDPR-compliant cookie consent banner on your site.', 'bkx-gdpr-compliance' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_gdpr_require_booking_consent"><?php esc_html_e( 'Booking Form Consent', 'bkx-gdpr-compliance' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="bkx_gdpr_require_booking_consent" name="bkx_gdpr_compliance_settings[require_booking_consent]" value="1"
						<?php checked( ! empty( $settings['require_booking_consent'] ) ); ?>>
					<?php esc_html_e( 'Require consent checkbox on booking forms', 'bkx-gdpr-compliance' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Add mandatory privacy consent checkbox to all booking forms.', 'bkx-gdpr-compliance' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_gdpr_consent_types"><?php esc_html_e( 'Consent Types', 'bkx-gdpr-compliance' ); ?></label>
			</th>
			<td>
				<?php
				$consent_types = isset( $settings['consent_types'] ) ? (array) $settings['consent_types'] : array( 'privacy' );
				$available_types = array(
					'privacy'     => __( 'Privacy Policy Agreement', 'bkx-gdpr-compliance' ),
					'marketing'   => __( 'Marketing Communications', 'bkx-gdpr-compliance' ),
					'third_party' => __( 'Third Party Data Sharing', 'bkx-gdpr-compliance' ),
					'analytics'   => __( 'Analytics & Performance', 'bkx-gdpr-compliance' ),
				);
				foreach ( $available_types as $type => $label ) :
				?>
					<label style="display: block; margin-bottom: 5px;">
						<input type="checkbox" name="bkx_gdpr_compliance_settings[consent_types][]" value="<?php echo esc_attr( $type ); ?>"
							<?php checked( in_array( $type, $consent_types, true ) ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description">
					<?php esc_html_e( 'Select which consent options to display on booking forms.', 'bkx-gdpr-compliance' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_gdpr_data_retention"><?php esc_html_e( 'Data Retention Period', 'bkx-gdpr-compliance' ); ?></label>
			</th>
			<td>
				<select id="bkx_gdpr_data_retention" name="bkx_gdpr_compliance_settings[data_retention_period]">
					<?php
					$retention_period = isset( $settings['data_retention_period'] ) ? $settings['data_retention_period'] : '365';
					$periods = array(
						'90'    => __( '90 days', 'bkx-gdpr-compliance' ),
						'180'   => __( '6 months', 'bkx-gdpr-compliance' ),
						'365'   => __( '1 year', 'bkx-gdpr-compliance' ),
						'730'   => __( '2 years', 'bkx-gdpr-compliance' ),
						'1095'  => __( '3 years', 'bkx-gdpr-compliance' ),
						'1825'  => __( '5 years', 'bkx-gdpr-compliance' ),
						'0'     => __( 'Forever (not recommended)', 'bkx-gdpr-compliance' ),
					);
					foreach ( $periods as $value => $label ) :
					?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $retention_period, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'How long to retain customer booking data before automatic anonymization.', 'bkx-gdpr-compliance' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_gdpr_auto_anonymize"><?php esc_html_e( 'Auto-Anonymize', 'bkx-gdpr-compliance' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="bkx_gdpr_auto_anonymize" name="bkx_gdpr_compliance_settings[auto_anonymize]" value="1"
						<?php checked( ! empty( $settings['auto_anonymize'] ) ); ?>>
					<?php esc_html_e( 'Automatically anonymize old booking data', 'bkx-gdpr-compliance' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, personal data will be anonymized after the retention period instead of deleted.', 'bkx-gdpr-compliance' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_gdpr_ccpa_enabled"><?php esc_html_e( 'CCPA Compliance', 'bkx-gdpr-compliance' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="bkx_gdpr_ccpa_enabled" name="bkx_gdpr_compliance_settings[ccpa_enabled]" value="1"
						<?php checked( ! empty( $settings['ccpa_enabled'] ) ); ?>>
					<?php esc_html_e( 'Enable California Consumer Privacy Act (CCPA) features', 'bkx-gdpr-compliance' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Show "Do Not Sell My Personal Information" link and honor opt-out requests.', 'bkx-gdpr-compliance' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_gdpr_privacy_page"><?php esc_html_e( 'Privacy Policy Page', 'bkx-gdpr-compliance' ); ?></label>
			</th>
			<td>
				<?php
				$privacy_page_id = isset( $settings['privacy_page_id'] ) ? absint( $settings['privacy_page_id'] ) : 0;
				wp_dropdown_pages(
					array(
						'name'              => 'bkx_gdpr_compliance_settings[privacy_page_id]',
						'id'                => 'bkx_gdpr_privacy_page',
						'show_option_none'  => __( '— Select a page —', 'bkx-gdpr-compliance' ),
						'option_none_value' => '0',
						'selected'          => $privacy_page_id,
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Select the page containing your privacy policy.', 'bkx-gdpr-compliance' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_gdpr_dpo_email"><?php esc_html_e( 'DPO Email', 'bkx-gdpr-compliance' ); ?></label>
			</th>
			<td>
				<input type="email" id="bkx_gdpr_dpo_email" name="bkx_gdpr_compliance_settings[dpo_email]" class="regular-text"
					value="<?php echo esc_attr( isset( $settings['dpo_email'] ) ? $settings['dpo_email'] : '' ); ?>"
					placeholder="<?php esc_attr_e( 'dpo@example.com', 'bkx-gdpr-compliance' ); ?>">
				<p class="description">
					<?php esc_html_e( 'Data Protection Officer email address for data subject requests.', 'bkx-gdpr-compliance' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<div class="bkx-gdpr-quick-links" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
		<h4 style="margin-top: 0;"><?php esc_html_e( 'Quick Links', 'bkx-gdpr-compliance' ); ?></h4>
		<ul style="margin: 0; list-style: disc; padding-left: 20px;">
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-gdpr-compliance&tab=requests' ) ); ?>">
					<?php esc_html_e( 'Manage Data Requests', 'bkx-gdpr-compliance' ); ?>
				</a>
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-gdpr-compliance&tab=consents' ) ); ?>">
					<?php esc_html_e( 'View Consent Records', 'bkx-gdpr-compliance' ); ?>
				</a>
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-gdpr-compliance&tab=policies' ) ); ?>">
					<?php esc_html_e( 'Generate Privacy Policy', 'bkx-gdpr-compliance' ); ?>
				</a>
			</li>
		</ul>
	</div>
</div>

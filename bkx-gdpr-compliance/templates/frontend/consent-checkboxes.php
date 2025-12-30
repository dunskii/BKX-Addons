<?php
/**
 * Consent checkboxes template for booking form.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_gdpr_settings', array() );
$privacy_url = get_privacy_policy_url();
?>

<div class="bkx-gdpr-consents">
	<!-- Privacy Policy Consent (Required) -->
	<div class="bkx-gdpr-consent-field bkx-gdpr-consent-required">
		<label>
			<input type="checkbox" name="bkx_privacy_consent" value="1" required>
			<?php
			if ( $privacy_url ) {
				printf(
					/* translators: %s: privacy policy link */
					esc_html__( 'I have read and accept the %s *', 'bkx-gdpr-compliance' ),
					'<a href="' . esc_url( $privacy_url ) . '" target="_blank">' . esc_html__( 'Privacy Policy', 'bkx-gdpr-compliance' ) . '</a>'
				);
			} else {
				esc_html_e( 'I have read and accept the Privacy Policy *', 'bkx-gdpr-compliance' );
			}
			?>
		</label>
	</div>

	<?php if ( in_array( 'marketing', $settings['consent_types'] ?? array(), true ) ) : ?>
		<!-- Marketing Consent (Optional) -->
		<div class="bkx-gdpr-consent-field">
			<label>
				<input type="checkbox" name="bkx_marketing_consent" value="1">
				<?php esc_html_e( 'I agree to receive marketing communications and promotional offers', 'bkx-gdpr-compliance' ); ?>
			</label>
		</div>
	<?php endif; ?>

	<?php if ( in_array( 'third_party', $settings['consent_types'] ?? array(), true ) ) : ?>
		<!-- Third Party Consent (Optional) -->
		<div class="bkx-gdpr-consent-field">
			<label>
				<input type="checkbox" name="bkx_third_party_consent" value="1">
				<?php esc_html_e( 'I agree to share my data with trusted third-party service providers', 'bkx-gdpr-compliance' ); ?>
			</label>
		</div>
	<?php endif; ?>
</div>

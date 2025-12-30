<?php
/**
 * Policy generator template.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="bkx-gdpr-policies">
	<div class="bkx-gdpr-card">
		<h2><?php esc_html_e( 'Policy Generator', 'bkx-gdpr-compliance' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Generate GDPR-compliant policy documents based on your settings. Review and customize these before publishing.', 'bkx-gdpr-compliance' ); ?>
		</p>

		<div class="bkx-gdpr-policy-actions">
			<div class="bkx-gdpr-policy-card">
				<h3><?php esc_html_e( 'Privacy Policy', 'bkx-gdpr-compliance' ); ?></h3>
				<p><?php esc_html_e( 'Generate a comprehensive privacy policy covering data collection, use, and rights.', 'bkx-gdpr-compliance' ); ?></p>
				<button type="button" class="button button-primary bkx-gdpr-generate-policy" data-type="privacy">
					<?php esc_html_e( 'Generate Privacy Policy', 'bkx-gdpr-compliance' ); ?>
				</button>
			</div>

			<div class="bkx-gdpr-policy-card">
				<h3><?php esc_html_e( 'Cookie Policy', 'bkx-gdpr-compliance' ); ?></h3>
				<p><?php esc_html_e( 'Generate a cookie policy explaining what cookies are used and why.', 'bkx-gdpr-compliance' ); ?></p>
				<button type="button" class="button button-primary bkx-gdpr-generate-policy" data-type="cookie">
					<?php esc_html_e( 'Generate Cookie Policy', 'bkx-gdpr-compliance' ); ?>
				</button>
			</div>

			<div class="bkx-gdpr-policy-card">
				<h3><?php esc_html_e( 'Terms of Service', 'bkx-gdpr-compliance' ); ?></h3>
				<p><?php esc_html_e( 'Generate basic terms of service for your booking platform.', 'bkx-gdpr-compliance' ); ?></p>
				<button type="button" class="button button-primary bkx-gdpr-generate-policy" data-type="terms">
					<?php esc_html_e( 'Generate Terms of Service', 'bkx-gdpr-compliance' ); ?>
				</button>
			</div>
		</div>
	</div>

	<div class="bkx-gdpr-card" id="bkx-gdpr-policy-preview" style="display: none;">
		<div class="bkx-gdpr-card-header">
			<h2><?php esc_html_e( 'Generated Policy', 'bkx-gdpr-compliance' ); ?></h2>
			<div>
				<button type="button" class="button" id="bkx-gdpr-copy-policy">
					<?php esc_html_e( 'Copy to Clipboard', 'bkx-gdpr-compliance' ); ?>
				</button>
				<button type="button" class="button button-primary" id="bkx-gdpr-create-page">
					<?php esc_html_e( 'Create Page', 'bkx-gdpr-compliance' ); ?>
				</button>
			</div>
		</div>
		<div class="bkx-gdpr-policy-content" id="bkx-gdpr-policy-content"></div>
	</div>

	<div class="bkx-gdpr-card">
		<h2><?php esc_html_e( 'Important Notes', 'bkx-gdpr-compliance' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'These templates are provided as a starting point. You should review and customize them for your specific business.', 'bkx-gdpr-compliance' ); ?></li>
			<li><?php esc_html_e( 'Consider having a legal professional review your policies before publishing.', 'bkx-gdpr-compliance' ); ?></li>
			<li><?php esc_html_e( 'Update your policies whenever you make significant changes to your data processing activities.', 'bkx-gdpr-compliance' ); ?></li>
			<li><?php esc_html_e( 'Make sure your policies are easily accessible from your website.', 'bkx-gdpr-compliance' ); ?></li>
		</ul>
	</div>
</div>

<?php
/**
 * Policy Generator service.
 *
 * @package BookingX\GdprCompliance\Services
 */

namespace BookingX\GdprCompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * PolicyGenerator class.
 */
class PolicyGenerator {

	/**
	 * Generate a policy.
	 *
	 * @param string $type Policy type (privacy, cookie, terms).
	 * @return string
	 */
	public function generate( $type ) {
		$settings = get_option( 'bkx_gdpr_settings', array() );

		$company_name    = $settings['company_name'] ?? get_bloginfo( 'name' );
		$company_address = $settings['company_address'] ?? '';
		$dpo_email       = $settings['dpo_email'] ?? get_option( 'admin_email' );
		$dpo_name        = $settings['dpo_name'] ?? '';
		$site_url        = home_url();
		$date            = wp_date( get_option( 'date_format' ) );

		switch ( $type ) {
			case 'cookie':
				return $this->generate_cookie_policy( $company_name, $site_url, $date );

			case 'terms':
				return $this->generate_terms_of_service( $company_name, $company_address, $site_url, $date );

			case 'privacy':
			default:
				return $this->generate_privacy_policy(
					$company_name,
					$company_address,
					$dpo_email,
					$dpo_name,
					$site_url,
					$date
				);
		}
	}

	/**
	 * Generate privacy policy.
	 *
	 * @param string $company_name    Company name.
	 * @param string $company_address Company address.
	 * @param string $dpo_email       DPO email.
	 * @param string $dpo_name        DPO name.
	 * @param string $site_url        Website URL.
	 * @param string $date            Current date.
	 * @return string
	 */
	private function generate_privacy_policy( $company_name, $company_address, $dpo_email, $dpo_name, $site_url, $date ) {
		ob_start();
		?>
<h1><?php esc_html_e( 'Privacy Policy', 'bkx-gdpr-compliance' ); ?></h1>

<p><strong><?php esc_html_e( 'Last updated:', 'bkx-gdpr-compliance' ); ?></strong> <?php echo esc_html( $date ); ?></p>

<h2><?php esc_html_e( '1. Introduction', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php echo esc_html( sprintf( __( '%s ("we", "us", or "our") operates %s. This privacy policy explains how we collect, use, disclose, and safeguard your information when you use our booking services.', 'bkx-gdpr-compliance' ), $company_name, $site_url ) ); ?></p>

<h2><?php esc_html_e( '2. Data Controller', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php echo esc_html( $company_name ); ?></p>
<?php if ( $company_address ) : ?>
<p><?php echo esc_html( $company_address ); ?></p>
<?php endif; ?>
<?php if ( $dpo_name ) : ?>
<p><?php esc_html_e( 'Data Protection Officer:', 'bkx-gdpr-compliance' ); ?> <?php echo esc_html( $dpo_name ); ?></p>
<?php endif; ?>
<p><?php esc_html_e( 'Contact:', 'bkx-gdpr-compliance' ); ?> <?php echo esc_html( $dpo_email ); ?></p>

<h2><?php esc_html_e( '3. Information We Collect', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'We collect information you provide directly when making bookings:', 'bkx-gdpr-compliance' ); ?></p>
<ul>
	<li><?php esc_html_e( 'Name and contact information (email, phone number)', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Booking details (service, date, time, preferences)', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Payment information (processed securely by payment providers)', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Communication history and notes', 'bkx-gdpr-compliance' ); ?></li>
</ul>

<p><?php esc_html_e( 'We automatically collect:', 'bkx-gdpr-compliance' ); ?></p>
<ul>
	<li><?php esc_html_e( 'IP address and browser information', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Device and access information', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Cookies and similar technologies (see Cookie Policy)', 'bkx-gdpr-compliance' ); ?></li>
</ul>

<h2><?php esc_html_e( '4. How We Use Your Information', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'We use your information to:', 'bkx-gdpr-compliance' ); ?></p>
<ul>
	<li><?php esc_html_e( 'Process and manage your bookings', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Send booking confirmations and reminders', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Process payments', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Respond to your inquiries', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Improve our services', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Send marketing communications (with your consent)', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Comply with legal obligations', 'bkx-gdpr-compliance' ); ?></li>
</ul>

<h2><?php esc_html_e( '5. Legal Basis for Processing', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'We process your data based on:', 'bkx-gdpr-compliance' ); ?></p>
<ul>
	<li><strong><?php esc_html_e( 'Contract:', 'bkx-gdpr-compliance' ); ?></strong> <?php esc_html_e( 'To fulfill booking services you request', 'bkx-gdpr-compliance' ); ?></li>
	<li><strong><?php esc_html_e( 'Consent:', 'bkx-gdpr-compliance' ); ?></strong> <?php esc_html_e( 'For marketing and optional features', 'bkx-gdpr-compliance' ); ?></li>
	<li><strong><?php esc_html_e( 'Legitimate Interest:', 'bkx-gdpr-compliance' ); ?></strong> <?php esc_html_e( 'To improve services and prevent fraud', 'bkx-gdpr-compliance' ); ?></li>
	<li><strong><?php esc_html_e( 'Legal Obligation:', 'bkx-gdpr-compliance' ); ?></strong> <?php esc_html_e( 'To comply with tax and business laws', 'bkx-gdpr-compliance' ); ?></li>
</ul>

<h2><?php esc_html_e( '6. Data Sharing', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'We may share your data with:', 'bkx-gdpr-compliance' ); ?></p>
<ul>
	<li><?php esc_html_e( 'Service providers (payment processors, email services)', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Staff members who provide your booked service', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Legal authorities when required by law', 'bkx-gdpr-compliance' ); ?></li>
</ul>
<p><?php esc_html_e( 'We do not sell your personal data.', 'bkx-gdpr-compliance' ); ?></p>

<h2><?php esc_html_e( '7. Data Retention', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'We retain your data for as long as necessary to provide services and comply with legal obligations:', 'bkx-gdpr-compliance' ); ?></p>
<ul>
	<li><?php esc_html_e( 'Booking records: Up to 2 years after service completion', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Financial records: As required by tax law (typically 7 years)', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Marketing consents: Until you withdraw consent', 'bkx-gdpr-compliance' ); ?></li>
</ul>

<h2><?php esc_html_e( '8. Your Rights', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'Under GDPR, you have the right to:', 'bkx-gdpr-compliance' ); ?></p>
<ul>
	<li><strong><?php esc_html_e( 'Access:', 'bkx-gdpr-compliance' ); ?></strong> <?php esc_html_e( 'Request a copy of your data', 'bkx-gdpr-compliance' ); ?></li>
	<li><strong><?php esc_html_e( 'Rectification:', 'bkx-gdpr-compliance' ); ?></strong> <?php esc_html_e( 'Correct inaccurate data', 'bkx-gdpr-compliance' ); ?></li>
	<li><strong><?php esc_html_e( 'Erasure:', 'bkx-gdpr-compliance' ); ?></strong> <?php esc_html_e( 'Request deletion of your data', 'bkx-gdpr-compliance' ); ?></li>
	<li><strong><?php esc_html_e( 'Restriction:', 'bkx-gdpr-compliance' ); ?></strong> <?php esc_html_e( 'Limit processing of your data', 'bkx-gdpr-compliance' ); ?></li>
	<li><strong><?php esc_html_e( 'Portability:', 'bkx-gdpr-compliance' ); ?></strong> <?php esc_html_e( 'Receive your data in a portable format', 'bkx-gdpr-compliance' ); ?></li>
	<li><strong><?php esc_html_e( 'Objection:', 'bkx-gdpr-compliance' ); ?></strong> <?php esc_html_e( 'Object to processing based on legitimate interest', 'bkx-gdpr-compliance' ); ?></li>
	<li><strong><?php esc_html_e( 'Withdraw Consent:', 'bkx-gdpr-compliance' ); ?></strong> <?php esc_html_e( 'Withdraw consent at any time', 'bkx-gdpr-compliance' ); ?></li>
</ul>
<p><?php echo esc_html( sprintf( __( 'To exercise these rights, contact us at %s', 'bkx-gdpr-compliance' ), $dpo_email ) ); ?></p>

<h2><?php esc_html_e( '9. Security', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'We implement appropriate technical and organizational measures to protect your data, including encryption, access controls, and regular security assessments.', 'bkx-gdpr-compliance' ); ?></p>

<h2><?php esc_html_e( '10. International Transfers', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'Your data may be transferred to and processed in countries outside the EEA. We ensure appropriate safeguards are in place, such as Standard Contractual Clauses.', 'bkx-gdpr-compliance' ); ?></p>

<h2><?php esc_html_e( '11. Complaints', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'You have the right to lodge a complaint with your local data protection authority if you believe your data has been processed unlawfully.', 'bkx-gdpr-compliance' ); ?></p>

<h2><?php esc_html_e( '12. Changes to This Policy', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'We may update this policy from time to time. We will notify you of significant changes by email or through a notice on our website.', 'bkx-gdpr-compliance' ); ?></p>

<h2><?php esc_html_e( '13. Contact Us', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php echo esc_html( sprintf( __( 'For questions about this policy or your data, contact us at %s', 'bkx-gdpr-compliance' ), $dpo_email ) ); ?></p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate cookie policy.
	 *
	 * @param string $company_name Company name.
	 * @param string $site_url     Website URL.
	 * @param string $date         Current date.
	 * @return string
	 */
	private function generate_cookie_policy( $company_name, $site_url, $date ) {
		$addon      = \BookingX\GdprCompliance\GdprComplianceAddon::get_instance();
		$cookies    = $addon->get_service( 'cookies' );
		$categories = $cookies->get_categories();
		$cookie_list = $cookies->get_cookies_by_category();

		ob_start();
		?>
<h1><?php esc_html_e( 'Cookie Policy', 'bkx-gdpr-compliance' ); ?></h1>

<p><strong><?php esc_html_e( 'Last updated:', 'bkx-gdpr-compliance' ); ?></strong> <?php echo esc_html( $date ); ?></p>

<h2><?php esc_html_e( '1. What Are Cookies?', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'Cookies are small text files stored on your device when you visit a website. They help websites remember your preferences and improve your experience.', 'bkx-gdpr-compliance' ); ?></p>

<h2><?php esc_html_e( '2. How We Use Cookies', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php echo esc_html( sprintf( __( '%s uses cookies to:', 'bkx-gdpr-compliance' ), $company_name ) ); ?></p>
<ul>
	<li><?php esc_html_e( 'Remember your preferences and settings', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Keep you logged in during your session', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Analyze website traffic and usage', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Personalize content and advertisements', 'bkx-gdpr-compliance' ); ?></li>
</ul>

<h2><?php esc_html_e( '3. Cookie Categories', 'bkx-gdpr-compliance' ); ?></h2>

<?php foreach ( $categories as $key => $category ) : ?>
<h3><?php echo esc_html( $category['label'] ); ?></h3>
<p><?php echo esc_html( $category['description'] ); ?></p>
<?php if ( ! empty( $cookie_list[ $key ] ) ) : ?>
<table border="1" cellpadding="5">
	<tr>
		<th><?php esc_html_e( 'Cookie Name', 'bkx-gdpr-compliance' ); ?></th>
		<th><?php esc_html_e( 'Provider', 'bkx-gdpr-compliance' ); ?></th>
		<th><?php esc_html_e( 'Purpose', 'bkx-gdpr-compliance' ); ?></th>
		<th><?php esc_html_e( 'Expiry', 'bkx-gdpr-compliance' ); ?></th>
	</tr>
	<?php foreach ( $cookie_list[ $key ] as $cookie ) : ?>
	<tr>
		<td><?php echo esc_html( $cookie['name'] ); ?></td>
		<td><?php echo esc_html( $cookie['provider'] ); ?></td>
		<td><?php echo esc_html( $cookie['purpose'] ); ?></td>
		<td><?php echo esc_html( $cookie['expiry'] ); ?></td>
	</tr>
	<?php endforeach; ?>
</table>
<?php endif; ?>
<?php endforeach; ?>

<h2><?php esc_html_e( '4. Managing Cookies', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'You can manage your cookie preferences through our cookie consent banner or by adjusting your browser settings. Note that disabling certain cookies may affect website functionality.', 'bkx-gdpr-compliance' ); ?></p>

<h2><?php esc_html_e( '5. Browser Settings', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'Most browsers allow you to:', 'bkx-gdpr-compliance' ); ?></p>
<ul>
	<li><?php esc_html_e( 'View and delete cookies', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Block all or specific cookies', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Set preferences for specific websites', 'bkx-gdpr-compliance' ); ?></li>
</ul>

<h2><?php esc_html_e( '6. Changes to This Policy', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'We may update this cookie policy from time to time. Changes will be posted on this page with an updated revision date.', 'bkx-gdpr-compliance' ); ?></p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate terms of service.
	 *
	 * @param string $company_name    Company name.
	 * @param string $company_address Company address.
	 * @param string $site_url        Website URL.
	 * @param string $date            Current date.
	 * @return string
	 */
	private function generate_terms_of_service( $company_name, $company_address, $site_url, $date ) {
		ob_start();
		?>
<h1><?php esc_html_e( 'Terms of Service', 'bkx-gdpr-compliance' ); ?></h1>

<p><strong><?php esc_html_e( 'Last updated:', 'bkx-gdpr-compliance' ); ?></strong> <?php echo esc_html( $date ); ?></p>

<h2><?php esc_html_e( '1. Acceptance of Terms', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php echo esc_html( sprintf( __( 'By accessing or using %s booking services, you agree to be bound by these Terms of Service.', 'bkx-gdpr-compliance' ), $company_name ) ); ?></p>

<h2><?php esc_html_e( '2. Booking Services', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'Our booking platform allows you to schedule appointments and services. All bookings are subject to availability and confirmation.', 'bkx-gdpr-compliance' ); ?></p>

<h2><?php esc_html_e( '3. User Responsibilities', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'You agree to:', 'bkx-gdpr-compliance' ); ?></p>
<ul>
	<li><?php esc_html_e( 'Provide accurate and complete information', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Arrive on time for scheduled appointments', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Cancel or reschedule with adequate notice', 'bkx-gdpr-compliance' ); ?></li>
	<li><?php esc_html_e( 'Comply with all applicable laws', 'bkx-gdpr-compliance' ); ?></li>
</ul>

<h2><?php esc_html_e( '4. Cancellation Policy', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'Cancellation terms may vary by service. Please review the specific cancellation policy when making your booking.', 'bkx-gdpr-compliance' ); ?></p>

<h2><?php esc_html_e( '5. Payment', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'Payment terms are specified at the time of booking. Refunds are subject to our cancellation policy.', 'bkx-gdpr-compliance' ); ?></p>

<h2><?php esc_html_e( '6. Limitation of Liability', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php echo esc_html( sprintf( __( '%s shall not be liable for any indirect, incidental, or consequential damages arising from your use of our services.', 'bkx-gdpr-compliance' ), $company_name ) ); ?></p>

<h2><?php esc_html_e( '7. Changes to Terms', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php esc_html_e( 'We reserve the right to modify these terms at any time. Continued use of our services constitutes acceptance of updated terms.', 'bkx-gdpr-compliance' ); ?></p>

<h2><?php esc_html_e( '8. Contact', 'bkx-gdpr-compliance' ); ?></h2>
<p><?php echo esc_html( $company_name ); ?></p>
<?php if ( $company_address ) : ?>
<p><?php echo esc_html( $company_address ); ?></p>
<?php endif; ?>
		<?php
		return ob_get_clean();
	}
}

<?php
/**
 * Cookie settings page template.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

$settings       = get_option( 'bkx_gdpr_compliance_settings', array() );
$categories     = array(
	'necessary'  => array(
		'label'       => __( 'Necessary Cookies', 'bkx-gdpr-compliance' ),
		'description' => __( 'These cookies are essential for the website to function properly. They enable basic features like page navigation, access to secure areas, and booking functionality. The website cannot function properly without these cookies.', 'bkx-gdpr-compliance' ),
		'required'    => true,
	),
	'functional' => array(
		'label'       => __( 'Functional Cookies', 'bkx-gdpr-compliance' ),
		'description' => __( 'These cookies enable enhanced functionality and personalization, such as remembering your preferences, language selection, and login information. If you disable these cookies, some features may not work properly.', 'bkx-gdpr-compliance' ),
		'required'    => false,
	),
	'analytics'  => array(
		'label'       => __( 'Analytics Cookies', 'bkx-gdpr-compliance' ),
		'description' => __( 'These cookies help us understand how visitors interact with our website by collecting and reporting information anonymously. This helps us improve our website and services.', 'bkx-gdpr-compliance' ),
		'required'    => false,
	),
	'marketing'  => array(
		'label'       => __( 'Marketing Cookies', 'bkx-gdpr-compliance' ),
		'description' => __( 'These cookies are used to track visitors across websites to display relevant advertisements. They are set by advertising partners and may be used to build a profile of your interests.', 'bkx-gdpr-compliance' ),
		'required'    => false,
	),
);
?>

<div class="bkx-cookie-settings-page">
	<h2><?php esc_html_e( 'Cookie Preferences', 'bkx-gdpr-compliance' ); ?></h2>
	<p class="bkx-cookie-settings-intro">
		<?php esc_html_e( 'We use cookies and similar technologies to enhance your browsing experience, personalize content and ads, provide social media features, and analyze our traffic. Use the controls below to manage your cookie preferences.', 'bkx-gdpr-compliance' ); ?>
	</p>

	<form id="bkx-cookie-settings-form" class="bkx-cookie-settings-form">
		<?php foreach ( $categories as $key => $category ) : ?>
			<div class="bkx-cookie-category-card">
				<div class="bkx-cookie-category-header">
					<div class="bkx-cookie-category-info">
						<h3><?php echo esc_html( $category['label'] ); ?></h3>
						<?php if ( $category['required'] ) : ?>
							<span class="bkx-cookie-required-badge"><?php esc_html_e( 'Always Active', 'bkx-gdpr-compliance' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="bkx-cookie-toggle">
						<label class="bkx-switch">
							<input type="checkbox" name="cookie_<?php echo esc_attr( $key ); ?>" data-category="<?php echo esc_attr( $key ); ?>"
								<?php checked( $category['required'] ); ?>
								<?php disabled( $category['required'] ); ?>>
							<span class="bkx-slider"></span>
						</label>
					</div>
				</div>
				<p class="bkx-cookie-category-description">
					<?php echo esc_html( $category['description'] ); ?>
				</p>
				<button type="button" class="bkx-cookie-details-toggle" data-category="<?php echo esc_attr( $key ); ?>">
					<?php esc_html_e( 'View cookies', 'bkx-gdpr-compliance' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</button>
				<div class="bkx-cookie-details" id="bkx-cookie-details-<?php echo esc_attr( $key ); ?>" style="display: none;">
					<?php echo wp_kses_post( apply_filters( "bkx_gdpr_cookie_details_{$key}", $this->get_cookie_details( $key ) ) ); ?>
				</div>
			</div>
		<?php endforeach; ?>

		<div class="bkx-cookie-settings-actions">
			<button type="button" id="bkx-cookie-reject-all" class="bkx-cookie-btn bkx-cookie-btn-secondary">
				<?php esc_html_e( 'Reject All', 'bkx-gdpr-compliance' ); ?>
			</button>
			<button type="button" id="bkx-cookie-accept-selected" class="bkx-cookie-btn bkx-cookie-btn-primary">
				<?php esc_html_e( 'Save Preferences', 'bkx-gdpr-compliance' ); ?>
			</button>
			<button type="button" id="bkx-cookie-accept-all" class="bkx-cookie-btn bkx-cookie-btn-accept">
				<?php esc_html_e( 'Accept All', 'bkx-gdpr-compliance' ); ?>
			</button>
		</div>
	</form>

	<div class="bkx-cookie-settings-info">
		<h4><?php esc_html_e( 'About Our Cookie Policy', 'bkx-gdpr-compliance' ); ?></h4>
		<p>
			<?php
			$privacy_page_id = isset( $settings['privacy_page_id'] ) ? absint( $settings['privacy_page_id'] ) : 0;
			if ( $privacy_page_id ) {
				printf(
					/* translators: %s: privacy policy link */
					esc_html__( 'For more information about how we use cookies and your personal data, please read our %s.', 'bkx-gdpr-compliance' ),
					'<a href="' . esc_url( get_permalink( $privacy_page_id ) ) . '">' . esc_html__( 'Privacy Policy', 'bkx-gdpr-compliance' ) . '</a>'
				);
			} else {
				esc_html_e( 'For more information about how we use cookies and your personal data, please contact us.', 'bkx-gdpr-compliance' );
			}
			?>
		</p>
		<p>
			<?php esc_html_e( 'You can change your cookie preferences at any time by returning to this page.', 'bkx-gdpr-compliance' ); ?>
		</p>
	</div>
</div>

<style>
.bkx-cookie-settings-page {
	max-width: 800px;
	margin: 0 auto;
	padding: 30px;
}
.bkx-cookie-settings-intro {
	color: #666;
	margin-bottom: 30px;
	line-height: 1.6;
}
.bkx-cookie-category-card {
	background: #fff;
	border: 1px solid #e0e0e0;
	border-radius: 8px;
	padding: 20px;
	margin-bottom: 15px;
}
.bkx-cookie-category-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 10px;
}
.bkx-cookie-category-info {
	display: flex;
	align-items: center;
	gap: 10px;
}
.bkx-cookie-category-info h3 {
	margin: 0;
	font-size: 16px;
}
.bkx-cookie-required-badge {
	background: #0073aa;
	color: #fff;
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 500;
}
.bkx-cookie-category-description {
	color: #666;
	font-size: 14px;
	line-height: 1.5;
	margin-bottom: 10px;
}
.bkx-cookie-details-toggle {
	background: none;
	border: none;
	color: #0073aa;
	cursor: pointer;
	font-size: 13px;
	padding: 0;
	display: flex;
	align-items: center;
	gap: 5px;
}
.bkx-cookie-details-toggle:hover {
	color: #005a87;
}
.bkx-cookie-details {
	margin-top: 15px;
	padding: 15px;
	background: #f9f9f9;
	border-radius: 4px;
}
.bkx-cookie-details table {
	width: 100%;
	font-size: 13px;
}
.bkx-cookie-details th,
.bkx-cookie-details td {
	padding: 8px;
	text-align: left;
	border-bottom: 1px solid #e0e0e0;
}
.bkx-cookie-details th {
	font-weight: 600;
	background: #f0f0f0;
}
/* Toggle Switch */
.bkx-switch {
	position: relative;
	display: inline-block;
	width: 50px;
	height: 26px;
}
.bkx-switch input {
	opacity: 0;
	width: 0;
	height: 0;
}
.bkx-slider {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: #ccc;
	transition: 0.3s;
	border-radius: 26px;
}
.bkx-slider:before {
	position: absolute;
	content: "";
	height: 20px;
	width: 20px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	transition: 0.3s;
	border-radius: 50%;
}
input:checked + .bkx-slider {
	background-color: #0073aa;
}
input:disabled + .bkx-slider {
	opacity: 0.7;
	cursor: not-allowed;
}
input:checked + .bkx-slider:before {
	transform: translateX(24px);
}
/* Actions */
.bkx-cookie-settings-actions {
	display: flex;
	gap: 10px;
	justify-content: flex-end;
	margin-top: 20px;
	padding-top: 20px;
	border-top: 1px solid #e0e0e0;
}
.bkx-cookie-btn {
	padding: 12px 24px;
	border-radius: 4px;
	font-size: 14px;
	font-weight: 500;
	cursor: pointer;
	border: none;
	transition: background 0.2s;
}
.bkx-cookie-btn-secondary {
	background: #f0f0f0;
	color: #333;
}
.bkx-cookie-btn-secondary:hover {
	background: #e0e0e0;
}
.bkx-cookie-btn-primary {
	background: #0073aa;
	color: #fff;
}
.bkx-cookie-btn-primary:hover {
	background: #005a87;
}
.bkx-cookie-btn-accept {
	background: #46b450;
	color: #fff;
}
.bkx-cookie-btn-accept:hover {
	background: #3a9a42;
}
/* Info section */
.bkx-cookie-settings-info {
	margin-top: 30px;
	padding: 20px;
	background: #f9f9f9;
	border-radius: 8px;
}
.bkx-cookie-settings-info h4 {
	margin-top: 0;
}
.bkx-cookie-settings-info p {
	color: #666;
	font-size: 14px;
	line-height: 1.5;
}
.bkx-cookie-settings-info a {
	color: #0073aa;
}
@media screen and (max-width: 600px) {
	.bkx-cookie-settings-actions {
		flex-direction: column;
	}
	.bkx-cookie-btn {
		width: 100%;
	}
}
</style>

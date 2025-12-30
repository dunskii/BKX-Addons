<?php
/**
 * Cookie banner template.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_gdpr_settings', array() );
$position = $settings['cookie_banner_position'] ?? 'bottom';

$addon      = \BookingX\GdprCompliance\GdprComplianceAddon::get_instance();
$cookies    = $addon->get_service( 'cookies' );
$categories = $cookies->get_categories();
?>

<div id="bkx-cookie-banner" class="bkx-cookie-banner bkx-cookie-banner-<?php echo esc_attr( $position ); ?>">
	<div class="bkx-cookie-banner-content">
		<div class="bkx-cookie-banner-text">
			<p>
				<?php
				printf(
					/* translators: %s: privacy policy link */
					esc_html__( 'We use cookies to enhance your experience. By continuing to visit this site you agree to our use of cookies. %s', 'bkx-gdpr-compliance' ),
					'<a href="' . esc_url( get_privacy_policy_url() ) . '">' . esc_html__( 'Learn more', 'bkx-gdpr-compliance' ) . '</a>'
				);
				?>
			</p>
		</div>

		<div class="bkx-cookie-banner-actions">
			<button type="button" class="bkx-cookie-btn bkx-cookie-btn-accept" id="bkx-cookie-accept-all">
				<?php esc_html_e( 'Accept All', 'bkx-gdpr-compliance' ); ?>
			</button>
			<button type="button" class="bkx-cookie-btn bkx-cookie-btn-secondary" id="bkx-cookie-reject-all">
				<?php esc_html_e( 'Reject All', 'bkx-gdpr-compliance' ); ?>
			</button>
			<button type="button" class="bkx-cookie-btn bkx-cookie-btn-link" id="bkx-cookie-customize">
				<?php esc_html_e( 'Customize', 'bkx-gdpr-compliance' ); ?>
			</button>
		</div>
	</div>

	<!-- Preferences Panel -->
	<div class="bkx-cookie-preferences" id="bkx-cookie-preferences" style="display: none;">
		<h3><?php esc_html_e( 'Cookie Preferences', 'bkx-gdpr-compliance' ); ?></h3>

		<?php foreach ( $categories as $key => $category ) : ?>
			<div class="bkx-cookie-category">
				<label class="bkx-cookie-category-label">
					<input type="checkbox"
						   name="bkx_cookie_<?php echo esc_attr( $key ); ?>"
						   value="1"
						   <?php echo $category['required'] ? 'checked disabled' : ''; ?>
						   data-category="<?php echo esc_attr( $key ); ?>">
					<span class="bkx-cookie-category-name">
						<?php echo esc_html( $category['label'] ); ?>
						<?php if ( $category['required'] ) : ?>
							<span class="bkx-cookie-required">(<?php esc_html_e( 'Required', 'bkx-gdpr-compliance' ); ?>)</span>
						<?php endif; ?>
					</span>
				</label>
				<p class="bkx-cookie-category-desc"><?php echo esc_html( $category['description'] ); ?></p>
			</div>
		<?php endforeach; ?>

		<div class="bkx-cookie-preferences-actions">
			<button type="button" class="bkx-cookie-btn bkx-cookie-btn-accept" id="bkx-cookie-save-preferences">
				<?php esc_html_e( 'Save Preferences', 'bkx-gdpr-compliance' ); ?>
			</button>
		</div>
	</div>
</div>

<?php
/**
 * Login page customization settings template.
 *
 * @package BookingX\WhiteLabel
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WhiteLabel\WhiteLabelAddon::get_instance();
$settings = $addon->get_settings();
?>

<div class="bkx-login-settings">
	<div class="bkx-login-grid">
		<div class="bkx-login-form">
			<h2><?php esc_html_e( 'Login Page Customization', 'bkx-white-label' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Customize the WordPress login page with your branding.', 'bkx-white-label' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="login_logo"><?php esc_html_e( 'Login Logo', 'bkx-white-label' ); ?></label>
					</th>
					<td>
						<div class="bkx-image-upload">
							<input type="hidden" id="login_logo" name="login_logo" value="<?php echo esc_url( $settings['login_logo'] ?? '' ); ?>">
							<div class="bkx-image-preview">
								<?php if ( ! empty( $settings['login_logo'] ) ) : ?>
									<img src="<?php echo esc_url( $settings['login_logo'] ); ?>" alt="">
								<?php endif; ?>
							</div>
							<button type="button" class="button bkx-upload-image"><?php esc_html_e( 'Select Logo', 'bkx-white-label' ); ?></button>
							<button type="button" class="button bkx-remove-image" <?php echo empty( $settings['login_logo'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'bkx-white-label' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Replaces the WordPress logo on the login page. Recommended: 200x100 pixels.', 'bkx-white-label' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="login_background"><?php esc_html_e( 'Background Image', 'bkx-white-label' ); ?></label>
					</th>
					<td>
						<div class="bkx-image-upload">
							<input type="hidden" id="login_background" name="login_background" value="<?php echo esc_url( $settings['login_background'] ?? '' ); ?>">
							<div class="bkx-image-preview wide">
								<?php if ( ! empty( $settings['login_background'] ) ) : ?>
									<img src="<?php echo esc_url( $settings['login_background'] ); ?>" alt="">
								<?php endif; ?>
							</div>
							<button type="button" class="button bkx-upload-image"><?php esc_html_e( 'Select Image', 'bkx-white-label' ); ?></button>
							<button type="button" class="button bkx-remove-image" <?php echo empty( $settings['login_background'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'bkx-white-label' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Full-page background image. Recommended: 1920x1080 pixels or larger.', 'bkx-white-label' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="login_custom_css"><?php esc_html_e( 'Custom CSS', 'bkx-white-label' ); ?></label>
					</th>
					<td>
						<textarea id="login_custom_css" name="login_custom_css" rows="10" class="large-text code"><?php echo esc_textarea( $settings['login_custom_css'] ?? '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Add custom CSS styles for the login page.', 'bkx-white-label' ); ?></p>
					</td>
				</tr>
			</table>

			<div class="bkx-login-tips">
				<h3><?php esc_html_e( 'Useful CSS Selectors', 'bkx-white-label' ); ?></h3>
				<ul>
					<li><code>body.login</code> - <?php esc_html_e( 'Page background', 'bkx-white-label' ); ?></li>
					<li><code>#login</code> - <?php esc_html_e( 'Login form container', 'bkx-white-label' ); ?></li>
					<li><code>#login h1 a</code> - <?php esc_html_e( 'Logo/header link', 'bkx-white-label' ); ?></li>
					<li><code>.login form</code> - <?php esc_html_e( 'Form box', 'bkx-white-label' ); ?></li>
					<li><code>.login #nav, .login #backtoblog</code> - <?php esc_html_e( 'Footer links', 'bkx-white-label' ); ?></li>
				</ul>
			</div>
		</div>

		<div class="bkx-login-preview">
			<h3><?php esc_html_e( 'Preview', 'bkx-white-label' ); ?></h3>
			<div class="bkx-login-preview-container" id="bkx-login-preview">
				<?php
				$login_customizer = $addon->get_service( 'login_customizer' );
				echo $login_customizer->get_preview_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
			<p class="description"><?php esc_html_e( 'Preview updates when you save settings.', 'bkx-white-label' ); ?></p>
			<p>
				<a href="<?php echo esc_url( wp_login_url() ); ?>" target="_blank" class="button">
					<span class="dashicons dashicons-external"></span>
					<?php esc_html_e( 'View Live Login Page', 'bkx-white-label' ); ?>
				</a>
			</p>
		</div>
	</div>
</div>

<style>
.bkx-login-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 30px;
}
.bkx-login-preview {
	position: sticky;
	top: 32px;
}
.bkx-login-preview-container {
	border: 1px solid #ddd;
	border-radius: 4px;
	overflow: hidden;
	margin-bottom: 15px;
}
.bkx-login-tips {
	background: #f9f9f9;
	padding: 15px 20px;
	border-radius: 4px;
	margin-top: 20px;
}
.bkx-login-tips ul {
	margin: 10px 0 0 20px;
}
.bkx-login-tips code {
	background: #e0e0e0;
	padding: 2px 6px;
	border-radius: 3px;
}
@media screen and (max-width: 1200px) {
	.bkx-login-grid {
		grid-template-columns: 1fr;
	}
}
</style>

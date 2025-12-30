<?php
/**
 * Email customization settings template.
 *
 * @package BookingX\WhiteLabel
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WhiteLabel\WhiteLabelAddon::get_instance();
$settings = $addon->get_settings();
?>

<div class="bkx-emails-settings">
	<div class="bkx-emails-grid">
		<div class="bkx-emails-form">
			<h2><?php esc_html_e( 'Email Sender', 'bkx-white-label' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="email_from_name"><?php esc_html_e( 'From Name', 'bkx-white-label' ); ?></label>
					</th>
					<td>
						<input type="text" id="email_from_name" name="email_from_name" value="<?php echo esc_attr( $settings['email_from_name'] ?? '' ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Name shown in the "From" field of emails.', 'bkx-white-label' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="email_from_address"><?php esc_html_e( 'From Email', 'bkx-white-label' ); ?></label>
					</th>
					<td>
						<input type="email" id="email_from_address" name="email_from_address" value="<?php echo esc_attr( $settings['email_from_address'] ?? '' ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Email address shown in the "From" field.', 'bkx-white-label' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Email Branding', 'bkx-white-label' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="email_header_image"><?php esc_html_e( 'Header Image', 'bkx-white-label' ); ?></label>
					</th>
					<td>
						<div class="bkx-image-upload">
							<input type="hidden" id="email_header_image" name="email_header_image" value="<?php echo esc_url( $settings['email_header_image'] ?? '' ); ?>">
							<div class="bkx-image-preview wide">
								<?php if ( ! empty( $settings['email_header_image'] ) ) : ?>
									<img src="<?php echo esc_url( $settings['email_header_image'] ); ?>" alt="">
								<?php endif; ?>
							</div>
							<button type="button" class="button bkx-upload-image"><?php esc_html_e( 'Select Image', 'bkx-white-label' ); ?></button>
							<button type="button" class="button bkx-remove-image" <?php echo empty( $settings['email_header_image'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'bkx-white-label' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Logo displayed at the top of emails. Recommended width: 200px.', 'bkx-white-label' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="email_footer_text"><?php esc_html_e( 'Footer Text', 'bkx-white-label' ); ?></label>
					</th>
					<td>
						<textarea id="email_footer_text" name="email_footer_text" rows="3" class="large-text"><?php echo esc_textarea( $settings['email_footer_text'] ?? '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Custom text for email footer. HTML allowed.', 'bkx-white-label' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Email Colors', 'bkx-white-label' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="email_background_color"><?php esc_html_e( 'Background Color', 'bkx-white-label' ); ?></label>
					</th>
					<td>
						<input type="text" id="email_background_color" name="email_background_color" value="<?php echo esc_attr( $settings['email_background_color'] ?? '#f7f7f7' ); ?>" class="bkx-color-picker">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="email_body_color"><?php esc_html_e( 'Body Background', 'bkx-white-label' ); ?></label>
					</th>
					<td>
						<input type="text" id="email_body_color" name="email_body_color" value="<?php echo esc_attr( $settings['email_body_color'] ?? '#ffffff' ); ?>" class="bkx-color-picker">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="email_text_color"><?php esc_html_e( 'Text Color', 'bkx-white-label' ); ?></label>
					</th>
					<td>
						<input type="text" id="email_text_color" name="email_text_color" value="<?php echo esc_attr( $settings['email_text_color'] ?? '#636363' ); ?>" class="bkx-color-picker">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="email_link_color"><?php esc_html_e( 'Link/Button Color', 'bkx-white-label' ); ?></label>
					</th>
					<td>
						<input type="text" id="email_link_color" name="email_link_color" value="<?php echo esc_attr( $settings['email_link_color'] ?? '#2271b1' ); ?>" class="bkx-color-picker">
					</td>
				</tr>
			</table>

			<p>
				<button type="button" class="button bkx-preview-email">
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'Preview Email', 'bkx-white-label' ); ?>
				</button>
				<button type="button" class="button bkx-send-test-email">
					<span class="dashicons dashicons-email"></span>
					<?php esc_html_e( 'Send Test Email', 'bkx-white-label' ); ?>
				</button>
			</p>
		</div>

		<div class="bkx-emails-preview">
			<h3><?php esc_html_e( 'Email Preview', 'bkx-white-label' ); ?></h3>
			<div class="bkx-email-preview-frame" id="bkx-email-preview">
				<p class="bkx-no-preview"><?php esc_html_e( 'Click "Preview Email" to see a sample email.', 'bkx-white-label' ); ?></p>
			</div>
		</div>
	</div>
</div>

<style>
.bkx-emails-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 30px;
}
.bkx-emails-preview {
	position: sticky;
	top: 32px;
}
.bkx-email-preview-frame {
	background: #f0f0f0;
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 10px;
	min-height: 500px;
}
.bkx-email-preview-frame iframe {
	width: 100%;
	min-height: 600px;
	border: none;
	background: #fff;
}
.bkx-no-preview {
	text-align: center;
	padding: 50px;
	color: #666;
}
@media screen and (max-width: 1200px) {
	.bkx-emails-grid {
		grid-template-columns: 1fr;
	}
}
</style>

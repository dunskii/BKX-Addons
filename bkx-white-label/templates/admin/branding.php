<?php
/**
 * Branding settings template.
 *
 * @package BookingX\WhiteLabel
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WhiteLabel\WhiteLabelAddon::get_instance();
$settings = $addon->get_settings();
?>

<div class="bkx-branding-settings">
	<h2><?php esc_html_e( 'Brand Identity', 'bkx-white-label' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Replace BookingX branding with your own brand throughout the plugin.', 'bkx-white-label' ); ?></p>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="brand_name"><?php esc_html_e( 'Brand Name', 'bkx-white-label' ); ?></label>
			</th>
			<td>
				<input type="text" id="brand_name" name="brand_name" value="<?php echo esc_attr( $settings['brand_name'] ?? '' ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'This will replace "BookingX" throughout the admin and emails.', 'bkx-white-label' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="brand_logo"><?php esc_html_e( 'Brand Logo', 'bkx-white-label' ); ?></label>
			</th>
			<td>
				<div class="bkx-image-upload">
					<input type="hidden" id="brand_logo" name="brand_logo" value="<?php echo esc_url( $settings['brand_logo'] ?? '' ); ?>">
					<div class="bkx-image-preview">
						<?php if ( ! empty( $settings['brand_logo'] ) ) : ?>
							<img src="<?php echo esc_url( $settings['brand_logo'] ); ?>" alt="">
						<?php endif; ?>
					</div>
					<button type="button" class="button bkx-upload-image"><?php esc_html_e( 'Select Logo', 'bkx-white-label' ); ?></button>
					<button type="button" class="button bkx-remove-image" <?php echo empty( $settings['brand_logo'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'bkx-white-label' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'Recommended size: 200x50 pixels. Used in admin header.', 'bkx-white-label' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="brand_logo_dark"><?php esc_html_e( 'Dark Mode Logo', 'bkx-white-label' ); ?></label>
			</th>
			<td>
				<div class="bkx-image-upload">
					<input type="hidden" id="brand_logo_dark" name="brand_logo_dark" value="<?php echo esc_url( $settings['brand_logo_dark'] ?? '' ); ?>">
					<div class="bkx-image-preview dark">
						<?php if ( ! empty( $settings['brand_logo_dark'] ) ) : ?>
							<img src="<?php echo esc_url( $settings['brand_logo_dark'] ); ?>" alt="">
						<?php endif; ?>
					</div>
					<button type="button" class="button bkx-upload-image"><?php esc_html_e( 'Select Logo', 'bkx-white-label' ); ?></button>
					<button type="button" class="button bkx-remove-image" <?php echo empty( $settings['brand_logo_dark'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'bkx-white-label' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'Optional logo for dark backgrounds.', 'bkx-white-label' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="brand_icon"><?php esc_html_e( 'Menu Icon', 'bkx-white-label' ); ?></label>
			</th>
			<td>
				<div class="bkx-image-upload">
					<input type="hidden" id="brand_icon" name="brand_icon" value="<?php echo esc_url( $settings['brand_icon'] ?? '' ); ?>">
					<div class="bkx-image-preview small">
						<?php if ( ! empty( $settings['brand_icon'] ) ) : ?>
							<img src="<?php echo esc_url( $settings['brand_icon'] ); ?>" alt="">
						<?php endif; ?>
					</div>
					<button type="button" class="button bkx-upload-image"><?php esc_html_e( 'Select Icon', 'bkx-white-label' ); ?></button>
					<button type="button" class="button bkx-remove-image" <?php echo empty( $settings['brand_icon'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'bkx-white-label' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'Recommended size: 20x20 pixels. Used in admin menu.', 'bkx-white-label' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="brand_url"><?php esc_html_e( 'Brand URL', 'bkx-white-label' ); ?></label>
			</th>
			<td>
				<input type="url" id="brand_url" name="brand_url" value="<?php echo esc_url( $settings['brand_url'] ?? '' ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'Your brand website URL for logo links.', 'bkx-white-label' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Support Information', 'bkx-white-label' ); ?></h2>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="support_email"><?php esc_html_e( 'Support Email', 'bkx-white-label' ); ?></label>
			</th>
			<td>
				<input type="email" id="support_email" name="support_email" value="<?php echo esc_attr( $settings['support_email'] ?? '' ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'Email address for support inquiries.', 'bkx-white-label' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="support_url"><?php esc_html_e( 'Support URL', 'bkx-white-label' ); ?></label>
			</th>
			<td>
				<input type="url" id="support_url" name="support_url" value="<?php echo esc_url( $settings['support_url'] ?? '' ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'Link to your support portal or documentation.', 'bkx-white-label' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="custom_admin_footer"><?php esc_html_e( 'Admin Footer Text', 'bkx-white-label' ); ?></label>
			</th>
			<td>
				<input type="text" id="custom_admin_footer" name="custom_admin_footer" value="<?php echo esc_attr( $settings['custom_admin_footer'] ?? '' ); ?>" class="large-text">
				<p class="description"><?php esc_html_e( 'Custom text to display in the admin footer. HTML allowed.', 'bkx-white-label' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Visibility Options', 'bkx-white-label' ); ?></h2>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Hide Elements', 'bkx-white-label' ); ?></th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="hide_bookingx_branding" value="1" <?php checked( ! empty( $settings['hide_bookingx_branding'] ) ); ?>>
						<?php esc_html_e( 'Hide BookingX branding', 'bkx-white-label' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="hide_powered_by" value="1" <?php checked( ! empty( $settings['hide_powered_by'] ) ); ?>>
						<?php esc_html_e( 'Hide "Powered by BookingX" text', 'bkx-white-label' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="hide_plugin_notices" value="1" <?php checked( ! empty( $settings['hide_plugin_notices'] ) ); ?>>
						<?php esc_html_e( 'Hide promotional notices', 'bkx-white-label' ); ?>
					</label>
					<br>
					<label>
						<input type="checkbox" name="hide_changelog" value="1" <?php checked( ! empty( $settings['hide_changelog'] ) ); ?>>
						<?php esc_html_e( 'Hide changelog notifications', 'bkx-white-label' ); ?>
					</label>
				</fieldset>
			</td>
		</tr>
	</table>
</div>

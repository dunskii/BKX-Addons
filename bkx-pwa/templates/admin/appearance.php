<?php
/**
 * PWA Appearance Settings Tab.
 *
 * @package BookingX\PWA
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_pwa_settings', array() );
?>

<div class="bkx-card">
	<h3><?php esc_html_e( 'Colors', 'bkx-pwa' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="theme_color"><?php esc_html_e( 'Theme Color', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<input type="text" id="theme_color" name="theme_color" class="bkx-color-picker"
					   value="<?php echo esc_attr( $settings['theme_color'] ?? '#2563eb' ); ?>">
				<p class="description">
					<?php esc_html_e( 'The browser toolbar and status bar color.', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="background_color"><?php esc_html_e( 'Background Color', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<input type="text" id="background_color" name="background_color" class="bkx-color-picker"
					   value="<?php echo esc_attr( $settings['background_color'] ?? '#ffffff' ); ?>">
				<p class="description">
					<?php esc_html_e( 'The splash screen background color.', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-card">
	<h3><?php esc_html_e( 'App Icons', 'bkx-pwa' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Upload PNG icons for your app. If not set, the site icon will be used.', 'bkx-pwa' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="icon_192"><?php esc_html_e( 'Icon 192x192', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<div class="bkx-icon-upload">
					<?php if ( ! empty( $settings['icon_192'] ) ) : ?>
						<img src="<?php echo esc_url( $settings['icon_192'] ); ?>" class="bkx-icon-preview" width="96" height="96">
					<?php endif; ?>
					<input type="hidden" id="icon_192" name="icon_192"
						   value="<?php echo esc_attr( $settings['icon_192'] ?? '' ); ?>">
					<button type="button" class="button bkx-upload-icon" data-target="icon_192">
						<?php esc_html_e( 'Select Icon', 'bkx-pwa' ); ?>
					</button>
					<button type="button" class="button bkx-remove-icon" data-target="icon_192"
							<?php echo empty( $settings['icon_192'] ) ? 'style="display:none;"' : ''; ?>>
						<?php esc_html_e( 'Remove', 'bkx-pwa' ); ?>
					</button>
				</div>
				<p class="description">
					<?php esc_html_e( 'Used for Android home screen and Apple touch icon.', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="icon_512"><?php esc_html_e( 'Icon 512x512', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<div class="bkx-icon-upload">
					<?php if ( ! empty( $settings['icon_512'] ) ) : ?>
						<img src="<?php echo esc_url( $settings['icon_512'] ); ?>" class="bkx-icon-preview" width="96" height="96">
					<?php endif; ?>
					<input type="hidden" id="icon_512" name="icon_512"
						   value="<?php echo esc_attr( $settings['icon_512'] ?? '' ); ?>">
					<button type="button" class="button bkx-upload-icon" data-target="icon_512">
						<?php esc_html_e( 'Select Icon', 'bkx-pwa' ); ?>
					</button>
					<button type="button" class="button bkx-remove-icon" data-target="icon_512"
							<?php echo empty( $settings['icon_512'] ) ? 'style="display:none;"' : ''; ?>>
						<?php esc_html_e( 'Remove', 'bkx-pwa' ); ?>
					</button>
				</div>
				<p class="description">
					<?php esc_html_e( 'High resolution icon for splash screens.', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-card">
	<h3><?php esc_html_e( 'iOS Settings', 'bkx-pwa' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Splash Screens', 'bkx-pwa' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="ios_splash_screens" value="1"
						   <?php checked( $settings['ios_splash_screens'] ?? true ); ?>>
					<?php esc_html_e( 'Generate iOS splash screen meta tags', 'bkx-pwa' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Enables splash screens when launching from iOS home screen.', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-card">
	<h3><?php esc_html_e( 'Preview', 'bkx-pwa' ); ?></h3>

	<div class="bkx-preview-container">
		<div class="bkx-phone-preview">
			<div class="bkx-phone-screen" style="background-color: <?php echo esc_attr( $settings['background_color'] ?? '#ffffff' ); ?>;">
				<div class="bkx-phone-status-bar" style="background-color: <?php echo esc_attr( $settings['theme_color'] ?? '#2563eb' ); ?>;"></div>
				<div class="bkx-phone-content">
					<?php if ( ! empty( $settings['icon_192'] ) ) : ?>
						<img src="<?php echo esc_url( $settings['icon_192'] ); ?>" class="bkx-preview-icon">
					<?php else : ?>
						<div class="bkx-preview-icon-placeholder"></div>
					<?php endif; ?>
					<span class="bkx-preview-name"><?php echo esc_html( $settings['app_short_name'] ?? get_bloginfo( 'name' ) ); ?></span>
				</div>
			</div>
		</div>
	</div>
</div>

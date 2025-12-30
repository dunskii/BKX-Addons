<?php
/**
 * PWA General Settings Tab.
 *
 * @package BookingX\PWA
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_pwa_settings', array() );
?>

<div class="bkx-card">
	<h3><?php esc_html_e( 'PWA Status', 'bkx-pwa' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable PWA', 'bkx-pwa' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="enabled" value="1"
						   <?php checked( $settings['enabled'] ?? true ); ?>>
					<?php esc_html_e( 'Make this site an installable Progressive Web App', 'bkx-pwa' ); ?>
				</label>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-card">
	<h3><?php esc_html_e( 'App Information', 'bkx-pwa' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="app_name"><?php esc_html_e( 'App Name', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<input type="text" id="app_name" name="app_name" class="regular-text"
					   value="<?php echo esc_attr( $settings['app_name'] ?? get_bloginfo( 'name' ) ); ?>">
				<p class="description">
					<?php esc_html_e( 'The name displayed when installing the app.', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="app_short_name"><?php esc_html_e( 'Short Name', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<input type="text" id="app_short_name" name="app_short_name" class="regular-text"
					   value="<?php echo esc_attr( $settings['app_short_name'] ?? '' ); ?>"
					   maxlength="12">
				<p class="description">
					<?php esc_html_e( 'Short name for home screen (max 12 characters).', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="app_description"><?php esc_html_e( 'Description', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<textarea id="app_description" name="app_description" rows="3" class="large-text"><?php
					echo esc_textarea( $settings['app_description'] ?? get_bloginfo( 'description' ) );
				?></textarea>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="start_url"><?php esc_html_e( 'Start URL', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<input type="text" id="start_url" name="start_url" class="regular-text"
					   value="<?php echo esc_attr( $settings['start_url'] ?? '/' ); ?>">
				<p class="description">
					<?php esc_html_e( 'The page that opens when the app is launched.', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-card">
	<h3><?php esc_html_e( 'Display Settings', 'bkx-pwa' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="display"><?php esc_html_e( 'Display Mode', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<select id="display" name="display">
					<option value="standalone" <?php selected( $settings['display'] ?? '', 'standalone' ); ?>>
						<?php esc_html_e( 'Standalone (Recommended)', 'bkx-pwa' ); ?>
					</option>
					<option value="fullscreen" <?php selected( $settings['display'] ?? '', 'fullscreen' ); ?>>
						<?php esc_html_e( 'Fullscreen', 'bkx-pwa' ); ?>
					</option>
					<option value="minimal-ui" <?php selected( $settings['display'] ?? '', 'minimal-ui' ); ?>>
						<?php esc_html_e( 'Minimal UI', 'bkx-pwa' ); ?>
					</option>
					<option value="browser" <?php selected( $settings['display'] ?? '', 'browser' ); ?>>
						<?php esc_html_e( 'Browser', 'bkx-pwa' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How the app should be displayed when launched.', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="orientation"><?php esc_html_e( 'Orientation', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<select id="orientation" name="orientation">
					<option value="any" <?php selected( $settings['orientation'] ?? '', 'any' ); ?>>
						<?php esc_html_e( 'Any', 'bkx-pwa' ); ?>
					</option>
					<option value="portrait" <?php selected( $settings['orientation'] ?? '', 'portrait' ); ?>>
						<?php esc_html_e( 'Portrait', 'bkx-pwa' ); ?>
					</option>
					<option value="landscape" <?php selected( $settings['orientation'] ?? '', 'landscape' ); ?>>
						<?php esc_html_e( 'Landscape', 'bkx-pwa' ); ?>
					</option>
				</select>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-card">
	<h3><?php esc_html_e( 'Manifest Preview', 'bkx-pwa' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Preview your web app manifest:', 'bkx-pwa' ); ?>
		<a href="<?php echo esc_url( home_url( '/manifest.json' ) ); ?>" target="_blank">
			<?php echo esc_html( home_url( '/manifest.json' ) ); ?>
		</a>
	</p>

	<h4><?php esc_html_e( 'Service Worker', 'bkx-pwa' ); ?></h4>
	<p class="description">
		<?php esc_html_e( 'Your service worker is available at:', 'bkx-pwa' ); ?>
		<a href="<?php echo esc_url( home_url( '/bkx-sw.js' ) ); ?>" target="_blank">
			<?php echo esc_html( home_url( '/bkx-sw.js' ) ); ?>
		</a>
	</p>
</div>

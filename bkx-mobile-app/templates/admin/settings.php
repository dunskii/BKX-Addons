<?php
/**
 * Mobile App General Settings.
 *
 * @package BookingX\MobileApp
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\MobileApp\MobileAppAddon::get_instance();
$settings = get_option( 'bkx_mobile_app_settings', array() );

// Handle form submission.
if ( isset( $_POST['bkx_save_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bkx_save_settings' ) ) {
	$settings['enabled']           = isset( $_POST['enabled'] );
	$settings['debug_mode']        = isset( $_POST['debug_mode'] );
	$settings['jwt_expiry']        = absint( $_POST['jwt_expiry'] ?? 7 );
	$settings['rate_limit']        = absint( $_POST['rate_limit'] ?? 100 );
	$settings['deep_link_scheme']  = sanitize_text_field( wp_unslash( $_POST['deep_link_scheme'] ?? 'bookingx' ) );
	$settings['app_store_url']     = esc_url_raw( wp_unslash( $_POST['app_store_url'] ?? '' ) );
	$settings['play_store_url']    = esc_url_raw( wp_unslash( $_POST['play_store_url'] ?? '' ) );
	$settings['inactive_cleanup']  = absint( $_POST['inactive_cleanup'] ?? 90 );
	$settings['cors_origins']      = sanitize_textarea_field( wp_unslash( $_POST['cors_origins'] ?? '' ) );

	update_option( 'bkx_mobile_app_settings', $settings );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'bkx-mobile-app' ) . '</p></div>';
}
?>

<div class="bkx-settings">
	<form method="post">
		<?php wp_nonce_field( 'bkx_save_settings' ); ?>

		<!-- General Settings -->
		<div class="bkx-card">
			<h3>
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'General Settings', 'bkx-mobile-app' ); ?>
			</h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Mobile API', 'bkx-mobile-app' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1"
								   <?php checked( $settings['enabled'] ?? true ); ?>>
							<?php esc_html_e( 'Allow mobile apps to connect to this site', 'bkx-mobile-app' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Debug Mode', 'bkx-mobile-app' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="debug_mode" value="1"
								   <?php checked( $settings['debug_mode'] ?? false ); ?>>
							<?php esc_html_e( 'Enable detailed error logging', 'bkx-mobile-app' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Logs are stored in wp-content/uploads/bkx-logs/', 'bkx-mobile-app' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- API Configuration -->
		<div class="bkx-card">
			<h3>
				<span class="dashicons dashicons-admin-network"></span>
				<?php esc_html_e( 'API Configuration', 'bkx-mobile-app' ); ?>
			</h3>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="jwt_expiry"><?php esc_html_e( 'JWT Token Expiry', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<select id="jwt_expiry" name="jwt_expiry">
							<option value="1" <?php selected( $settings['jwt_expiry'] ?? 7, 1 ); ?>>
								<?php esc_html_e( '1 day', 'bkx-mobile-app' ); ?>
							</option>
							<option value="7" <?php selected( $settings['jwt_expiry'] ?? 7, 7 ); ?>>
								<?php esc_html_e( '7 days', 'bkx-mobile-app' ); ?>
							</option>
							<option value="14" <?php selected( $settings['jwt_expiry'] ?? 7, 14 ); ?>>
								<?php esc_html_e( '14 days', 'bkx-mobile-app' ); ?>
							</option>
							<option value="30" <?php selected( $settings['jwt_expiry'] ?? 7, 30 ); ?>>
								<?php esc_html_e( '30 days', 'bkx-mobile-app' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'How long user authentication tokens remain valid.', 'bkx-mobile-app' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rate_limit"><?php esc_html_e( 'Rate Limit', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<input type="number" id="rate_limit" name="rate_limit" class="small-text"
							   value="<?php echo esc_attr( $settings['rate_limit'] ?? 100 ); ?>" min="10" max="1000">
						<span><?php esc_html_e( 'requests per minute', 'bkx-mobile-app' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Maximum API requests per device per minute.', 'bkx-mobile-app' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cors_origins"><?php esc_html_e( 'CORS Origins', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<textarea id="cors_origins" name="cors_origins" rows="3" class="large-text code"
								  placeholder="https://example.com&#10;https://app.example.com"><?php echo esc_textarea( $settings['cors_origins'] ?? '' ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Allowed origins for cross-origin requests (one per line). Leave empty to allow all.', 'bkx-mobile-app' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Deep Linking -->
		<div class="bkx-card">
			<h3>
				<span class="dashicons dashicons-admin-links"></span>
				<?php esc_html_e( 'Deep Linking', 'bkx-mobile-app' ); ?>
			</h3>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="deep_link_scheme"><?php esc_html_e( 'URL Scheme', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<input type="text" id="deep_link_scheme" name="deep_link_scheme" class="regular-text"
							   value="<?php echo esc_attr( $settings['deep_link_scheme'] ?? 'bookingx' ); ?>"
							   pattern="[a-z][a-z0-9+.-]*">
						<p class="description">
							<?php esc_html_e( 'Custom URL scheme for deep links (e.g., bookingx://)', 'bkx-mobile-app' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- App Store Links -->
		<div class="bkx-card">
			<h3>
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'App Store Links', 'bkx-mobile-app' ); ?>
			</h3>
			<p class="description">
				<?php esc_html_e( 'Links to your mobile app in the app stores. These are used in smart banners and share links.', 'bkx-mobile-app' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="app_store_url"><?php esc_html_e( 'App Store URL', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<input type="url" id="app_store_url" name="app_store_url" class="regular-text"
							   value="<?php echo esc_attr( $settings['app_store_url'] ?? '' ); ?>"
							   placeholder="https://apps.apple.com/app/id123456789">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="play_store_url"><?php esc_html_e( 'Play Store URL', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<input type="url" id="play_store_url" name="play_store_url" class="regular-text"
							   value="<?php echo esc_attr( $settings['play_store_url'] ?? '' ); ?>"
							   placeholder="https://play.google.com/store/apps/details?id=com.example.app">
					</td>
				</tr>
			</table>
		</div>

		<!-- Maintenance -->
		<div class="bkx-card">
			<h3>
				<span class="dashicons dashicons-admin-tools"></span>
				<?php esc_html_e( 'Maintenance', 'bkx-mobile-app' ); ?>
			</h3>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="inactive_cleanup"><?php esc_html_e( 'Inactive Device Cleanup', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<select id="inactive_cleanup" name="inactive_cleanup">
							<option value="0" <?php selected( $settings['inactive_cleanup'] ?? 90, 0 ); ?>>
								<?php esc_html_e( 'Never', 'bkx-mobile-app' ); ?>
							</option>
							<option value="30" <?php selected( $settings['inactive_cleanup'] ?? 90, 30 ); ?>>
								<?php esc_html_e( 'After 30 days', 'bkx-mobile-app' ); ?>
							</option>
							<option value="60" <?php selected( $settings['inactive_cleanup'] ?? 90, 60 ); ?>>
								<?php esc_html_e( 'After 60 days', 'bkx-mobile-app' ); ?>
							</option>
							<option value="90" <?php selected( $settings['inactive_cleanup'] ?? 90, 90 ); ?>>
								<?php esc_html_e( 'After 90 days', 'bkx-mobile-app' ); ?>
							</option>
							<option value="180" <?php selected( $settings['inactive_cleanup'] ?? 90, 180 ); ?>>
								<?php esc_html_e( 'After 180 days', 'bkx-mobile-app' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Automatically remove devices that have not connected recently.', 'bkx-mobile-app' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Universal Links -->
		<div class="bkx-card">
			<h3>
				<span class="dashicons dashicons-share"></span>
				<?php esc_html_e( 'Universal Links / App Links', 'bkx-mobile-app' ); ?>
			</h3>
			<p class="description">
				<?php esc_html_e( 'For iOS Universal Links and Android App Links, you need to host association files on your server.', 'bkx-mobile-app' ); ?>
			</p>

			<h4><?php esc_html_e( 'iOS (apple-app-site-association)', 'bkx-mobile-app' ); ?></h4>
			<p>
				<?php
				printf(
					/* translators: %s: file path */
					esc_html__( 'Place this file at: %s', 'bkx-mobile-app' ),
					'<code>' . esc_html( home_url( '/.well-known/apple-app-site-association' ) ) . '</code>'
				);
				?>
			</p>
			<pre><code id="aasa-content"><?php
			$deep_link = $addon->get_service( 'deep_link' );
			echo esc_html( wp_json_encode( $deep_link->get_apple_app_site_association(), JSON_PRETTY_PRINT ) );
			?></code></pre>
			<button type="button" class="button bkx-copy-btn" data-target="#aasa-content">
				<?php esc_html_e( 'Copy', 'bkx-mobile-app' ); ?>
			</button>

			<h4><?php esc_html_e( 'Android (assetlinks.json)', 'bkx-mobile-app' ); ?></h4>
			<p>
				<?php
				printf(
					/* translators: %s: file path */
					esc_html__( 'Place this file at: %s', 'bkx-mobile-app' ),
					'<code>' . esc_html( home_url( '/.well-known/assetlinks.json' ) ) . '</code>'
				);
				?>
			</p>
			<p class="description">
				<?php esc_html_e( 'You need to generate this file with your Android app\'s package name and SHA256 certificate fingerprint.', 'bkx-mobile-app' ); ?>
			</p>
		</div>

		<p class="submit">
			<button type="submit" name="bkx_save_settings" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-mobile-app' ); ?>
			</button>
		</p>
	</form>
</div>

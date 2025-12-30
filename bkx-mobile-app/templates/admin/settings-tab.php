<?php
/**
 * Mobile App Settings Tab for BookingX Settings.
 *
 * @package BookingX\MobileApp
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\MobileApp\MobileAppAddon::get_instance();
$settings = get_option( 'bkx_mobile_app_settings', array() );

// Handle form submission.
if ( isset( $_POST['bkx_save_mobile_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bkx_save_mobile_settings' ) ) {
	$settings['enabled']          = isset( $_POST['enabled'] );
	$settings['deep_link_scheme'] = sanitize_text_field( wp_unslash( $_POST['deep_link_scheme'] ?? 'bookingx' ) );
	$settings['app_store_url']    = esc_url_raw( wp_unslash( $_POST['app_store_url'] ?? '' ) );
	$settings['play_store_url']   = esc_url_raw( wp_unslash( $_POST['play_store_url'] ?? '' ) );

	update_option( 'bkx_mobile_app_settings', $settings );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'bkx-mobile-app' ) . '</p></div>';
}
?>

<div class="bkx-mobile-settings-tab">
	<form method="post">
		<?php wp_nonce_field( 'bkx_save_mobile_settings' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Mobile API', 'bkx-mobile-app' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enabled" value="1"
							   <?php checked( $settings['enabled'] ?? true ); ?>>
						<?php esc_html_e( 'Allow mobile apps to connect', 'bkx-mobile-app' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="deep_link_scheme"><?php esc_html_e( 'Deep Link Scheme', 'bkx-mobile-app' ); ?></label>
				</th>
				<td>
					<input type="text" id="deep_link_scheme" name="deep_link_scheme" class="regular-text"
						   value="<?php echo esc_attr( $settings['deep_link_scheme'] ?? 'bookingx' ); ?>">
					<p class="description">
						<?php esc_html_e( 'Custom URL scheme for deep links (e.g., bookingx://)', 'bkx-mobile-app' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="app_store_url"><?php esc_html_e( 'iOS App Store URL', 'bkx-mobile-app' ); ?></label>
				</th>
				<td>
					<input type="url" id="app_store_url" name="app_store_url" class="regular-text"
						   value="<?php echo esc_attr( $settings['app_store_url'] ?? '' ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="play_store_url"><?php esc_html_e( 'Android Play Store URL', 'bkx-mobile-app' ); ?></label>
				</th>
				<td>
					<input type="url" id="play_store_url" name="play_store_url" class="regular-text"
						   value="<?php echo esc_attr( $settings['play_store_url'] ?? '' ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'API Endpoint', 'bkx-mobile-app' ); ?></th>
				<td>
					<code><?php echo esc_html( rest_url( 'bkx-mobile/v1' ) ); ?></code>
					<p class="description">
						<?php esc_html_e( 'Use this endpoint in your mobile app.', 'bkx-mobile-app' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" name="bkx_save_mobile_settings" class="button button-primary">
				<?php esc_html_e( 'Save Changes', 'bkx-mobile-app' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-mobile-app' ) ); ?>" class="button">
				<?php esc_html_e( 'Open Mobile App Dashboard', 'bkx-mobile-app' ); ?>
			</a>
		</p>
	</form>
</div>

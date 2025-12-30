<?php
/**
 * PWA Settings Tab for BookingX Settings.
 *
 * @package BookingX\PWA
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\PWA\PWAAddon::get_instance();
$settings = get_option( 'bkx_pwa_settings', array() );

// Handle form submission.
if ( isset( $_POST['bkx_save_pwa_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bkx_save_pwa_settings' ) ) {
	$settings['enabled']          = isset( $_POST['enabled'] );
	$settings['offline_bookings'] = isset( $_POST['offline_bookings'] );
	$settings['install_prompt']   = isset( $_POST['install_prompt'] );

	update_option( 'bkx_pwa_settings', $settings );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'bkx-pwa' ) . '</p></div>';
}
?>

<div class="bkx-pwa-settings-tab">
	<form method="post">
		<?php wp_nonce_field( 'bkx_save_pwa_settings' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable PWA', 'bkx-pwa' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enabled" value="1"
							   <?php checked( $settings['enabled'] ?? true ); ?>>
						<?php esc_html_e( 'Make site installable as PWA', 'bkx-pwa' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Offline Bookings', 'bkx-pwa' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="offline_bookings" value="1"
							   <?php checked( $settings['offline_bookings'] ?? true ); ?>>
						<?php esc_html_e( 'Allow offline booking creation', 'bkx-pwa' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Install Prompt', 'bkx-pwa' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="install_prompt" value="1"
							   <?php checked( $settings['install_prompt'] ?? true ); ?>>
						<?php esc_html_e( 'Show install prompt to visitors', 'bkx-pwa' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" name="bkx_save_pwa_settings" class="button button-primary">
				<?php esc_html_e( 'Save Changes', 'bkx-pwa' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pwa' ) ); ?>" class="button">
				<?php esc_html_e( 'Open PWA Settings', 'bkx-pwa' ); ?>
			</a>
		</p>
	</form>
</div>

<?php
/**
 * License Tab Template
 *
 * @package BookingX\BookingPackages
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$license_key    = get_option( 'bkx_packages_license_key', '' );
$license_status = get_option( 'bkx_packages_license_status', '' );
?>
<div class="bkx-license-settings">
	<h2><?php esc_html_e( 'License Activation', 'bkx-booking-packages' ); ?></h2>

	<p><?php esc_html_e( 'Enter your license key to receive automatic updates and support.', 'bkx-booking-packages' ); ?></p>

	<form method="post" action="">
		<?php wp_nonce_field( 'bkx_packages_license', 'bkx_packages_license_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="bkx_packages_license_key"><?php esc_html_e( 'License Key', 'bkx-booking-packages' ); ?></label>
				</th>
				<td>
					<input type="password"
						   id="bkx_packages_license_key"
						   name="bkx_packages_license_key"
						   value="<?php echo esc_attr( $license_key ); ?>"
						   class="regular-text"
						   autocomplete="off">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'bkx-booking-packages' ); ?></th>
				<td>
					<?php if ( 'valid' === $license_status ) : ?>
						<span class="bkx-license-status bkx-license-active">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Active', 'bkx-booking-packages' ); ?>
						</span>
					<?php elseif ( 'expired' === $license_status ) : ?>
						<span class="bkx-license-status bkx-license-expired">
							<span class="dashicons dashicons-warning"></span>
							<?php esc_html_e( 'Expired', 'bkx-booking-packages' ); ?>
						</span>
					<?php else : ?>
						<span class="bkx-license-status bkx-license-inactive">
							<span class="dashicons dashicons-marker"></span>
							<?php esc_html_e( 'Not Activated', 'bkx-booking-packages' ); ?>
						</span>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<p class="submit">
			<?php if ( 'valid' === $license_status ) : ?>
				<button type="submit" name="bkx_packages_license_deactivate" class="button">
					<?php esc_html_e( 'Deactivate License', 'bkx-booking-packages' ); ?>
				</button>
			<?php else : ?>
				<button type="submit" name="bkx_packages_license_activate" class="button button-primary">
					<?php esc_html_e( 'Activate License', 'bkx-booking-packages' ); ?>
				</button>
			<?php endif; ?>
		</p>
	</form>
</div>

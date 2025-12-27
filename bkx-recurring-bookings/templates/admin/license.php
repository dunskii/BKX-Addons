<?php
/**
 * License Tab Template
 *
 * @package BookingX\RecurringBookings
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$license_key    = get_option( 'bkx_recurring_license_key', '' );
$license_status = get_option( 'bkx_recurring_license_status', '' );
?>
<div class="bkx-license-settings">
	<h2><?php esc_html_e( 'License Activation', 'bkx-recurring-bookings' ); ?></h2>

	<p><?php esc_html_e( 'Enter your license key to receive automatic updates and support.', 'bkx-recurring-bookings' ); ?></p>

	<form method="post" action="">
		<?php wp_nonce_field( 'bkx_recurring_license', 'bkx_recurring_license_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="bkx_recurring_license_key"><?php esc_html_e( 'License Key', 'bkx-recurring-bookings' ); ?></label>
				</th>
				<td>
					<input type="password"
						   id="bkx_recurring_license_key"
						   name="bkx_recurring_license_key"
						   value="<?php echo esc_attr( $license_key ); ?>"
						   class="regular-text"
						   autocomplete="off">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'bkx-recurring-bookings' ); ?></th>
				<td>
					<?php if ( 'valid' === $license_status ) : ?>
						<span class="bkx-license-status bkx-license-active">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Active', 'bkx-recurring-bookings' ); ?>
						</span>
						<p class="description">
							<?php esc_html_e( 'Your license is active. You will receive automatic updates.', 'bkx-recurring-bookings' ); ?>
						</p>
					<?php elseif ( 'expired' === $license_status ) : ?>
						<span class="bkx-license-status bkx-license-expired">
							<span class="dashicons dashicons-warning"></span>
							<?php esc_html_e( 'Expired', 'bkx-recurring-bookings' ); ?>
						</span>
						<p class="description">
							<?php esc_html_e( 'Your license has expired. Please renew to continue receiving updates.', 'bkx-recurring-bookings' ); ?>
						</p>
					<?php elseif ( 'invalid' === $license_status ) : ?>
						<span class="bkx-license-status bkx-license-invalid">
							<span class="dashicons dashicons-dismiss"></span>
							<?php esc_html_e( 'Invalid', 'bkx-recurring-bookings' ); ?>
						</span>
						<p class="description">
							<?php esc_html_e( 'The license key is invalid. Please check and try again.', 'bkx-recurring-bookings' ); ?>
						</p>
					<?php else : ?>
						<span class="bkx-license-status bkx-license-inactive">
							<span class="dashicons dashicons-marker"></span>
							<?php esc_html_e( 'Not Activated', 'bkx-recurring-bookings' ); ?>
						</span>
						<p class="description">
							<?php esc_html_e( 'Enter your license key and click Activate to enable updates.', 'bkx-recurring-bookings' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<p class="submit">
			<?php if ( 'valid' === $license_status ) : ?>
				<button type="submit" name="bkx_recurring_license_deactivate" class="button">
					<?php esc_html_e( 'Deactivate License', 'bkx-recurring-bookings' ); ?>
				</button>
			<?php else : ?>
				<button type="submit" name="bkx_recurring_license_activate" class="button button-primary">
					<?php esc_html_e( 'Activate License', 'bkx-recurring-bookings' ); ?>
				</button>
			<?php endif; ?>
		</p>
	</form>

	<hr>

	<h3><?php esc_html_e( 'Need a License?', 'bkx-recurring-bookings' ); ?></h3>
	<p>
		<?php
		printf(
			/* translators: %s: link to purchase page */
			esc_html__( 'Purchase a license at %s to unlock automatic updates and priority support.', 'bkx-recurring-bookings' ),
			'<a href="https://bookingx.com/addons/recurring-bookings" target="_blank">bookingx.com</a>'
		);
		?>
	</p>
</div>

<style>
.bkx-license-settings {
	max-width: 800px;
}

.bkx-license-status {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 5px 10px;
	border-radius: 4px;
	font-weight: 500;
}

.bkx-license-active {
	background: #d4edda;
	color: #155724;
}

.bkx-license-expired {
	background: #fff3cd;
	color: #856404;
}

.bkx-license-invalid {
	background: #f8d7da;
	color: #721c24;
}

.bkx-license-inactive {
	background: #e2e3e5;
	color: #383d41;
}

.bkx-license-status .dashicons {
	font-size: 18px;
	width: 18px;
	height: 18px;
}
</style>

<?php
/**
 * Notifications tab template.
 *
 * @package BookingX\Slack
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_slack_settings', array() );
?>

<div class="bkx-slack-notifications">
	<form id="bkx-slack-notifications-form" method="post">
		<?php wp_nonce_field( 'bkx_slack_settings', 'bkx_slack_nonce' ); ?>

		<div class="bkx-card">
			<h2><?php esc_html_e( 'Notification Events', 'bkx-slack' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Choose which booking events trigger Slack notifications.', 'bkx-slack' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'New Booking', 'bkx-slack' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="notify_new_booking" value="1"
								   <?php checked( ! empty( $settings['notify_new_booking'] ) ); ?>>
							<?php esc_html_e( 'Send notification when a new booking is created', 'bkx-slack' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Booking Cancelled', 'bkx-slack' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="notify_cancelled" value="1"
								   <?php checked( ! empty( $settings['notify_cancelled'] ) ); ?>>
							<?php esc_html_e( 'Send notification when a booking is cancelled', 'bkx-slack' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Booking Completed', 'bkx-slack' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="notify_completed" value="1"
								   <?php checked( ! empty( $settings['notify_completed'] ) ); ?>>
							<?php esc_html_e( 'Send notification when a booking is marked complete', 'bkx-slack' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Booking Rescheduled', 'bkx-slack' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="notify_rescheduled" value="1"
								   <?php checked( ! empty( $settings['notify_rescheduled'] ) ); ?>>
							<?php esc_html_e( 'Send notification when a booking is rescheduled', 'bkx-slack' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-card">
			<h2><?php esc_html_e( 'Notification Preview', 'bkx-slack' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'This is how your booking notifications will appear in Slack.', 'bkx-slack' ); ?>
			</p>

			<div class="bkx-slack-preview">
				<div class="bkx-slack-message">
					<div class="bkx-slack-avatar">BX</div>
					<div class="bkx-slack-content">
						<div class="bkx-slack-header">
							<strong>BookingX</strong>
							<span class="bkx-slack-time">11:30 AM</span>
						</div>
						<div class="bkx-slack-text">:calendar: New Booking</div>
						<div class="bkx-slack-attachment" style="border-left-color: #36a64f;">
							<div class="bkx-slack-attachment-title">
								<strong>New Booking #123</strong>
							</div>
							<div class="bkx-slack-attachment-fields">
								<div class="bkx-slack-field">
									<span class="bkx-slack-field-title">Customer:</span>
									<span>John Smith</span>
								</div>
								<div class="bkx-slack-field">
									<span class="bkx-slack-field-title">Staff:</span>
									<span>Sarah Johnson</span>
								</div>
								<div class="bkx-slack-field">
									<span class="bkx-slack-field-title">Date:</span>
									<span>Monday, January 15, 2024</span>
								</div>
								<div class="bkx-slack-field">
									<span class="bkx-slack-field-title">Time:</span>
									<span>2:00 PM</span>
								</div>
							</div>
							<div class="bkx-slack-attachment-actions">
								<button type="button" class="bkx-slack-btn bkx-slack-btn-primary">Confirm</button>
								<button type="button" class="bkx-slack-btn">View Details</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Notification Settings', 'bkx-slack' ); ?>
			</button>
		</p>
	</form>
</div>

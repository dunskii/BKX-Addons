<?php
/**
 * Waitlist Join Form Template
 *
 * @package BookingX\WaitingLists
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="bkx-waitlist-modal" class="bkx-waitlist-modal" style="display:none;">
	<div class="bkx-waitlist-modal-content">
		<button type="button" class="bkx-waitlist-close">&times;</button>
		<h3><?php esc_html_e( 'Join Waiting List', 'bkx-waiting-lists' ); ?></h3>
		<p class="bkx-waitlist-description">
			<?php esc_html_e( 'This time slot is fully booked. Enter your details below to join the waiting list and we\'ll notify you if a spot becomes available.', 'bkx-waiting-lists' ); ?>
		</p>
		<form id="bkx-waitlist-form" class="bkx-waitlist-form">
			<input type="hidden" name="seat_id" id="waitlist_seat_id" value="" />
			<input type="hidden" name="service_id" id="waitlist_service_id" value="" />
			<input type="hidden" name="date" id="waitlist_date" value="" />
			<input type="hidden" name="time" id="waitlist_time" value="" />

			<div class="bkx-waitlist-slot-info">
				<span id="waitlist_slot_display"></span>
			</div>

			<div class="bkx-waitlist-field">
				<label for="waitlist_name"><?php esc_html_e( 'Your Name', 'bkx-waiting-lists' ); ?></label>
				<input type="text" name="name" id="waitlist_name" required
					value="<?php echo is_user_logged_in() ? esc_attr( wp_get_current_user()->display_name ) : ''; ?>" />
			</div>

			<div class="bkx-waitlist-field">
				<label for="waitlist_email"><?php esc_html_e( 'Email Address', 'bkx-waiting-lists' ); ?> <span class="required">*</span></label>
				<input type="email" name="email" id="waitlist_email" required
					value="<?php echo is_user_logged_in() ? esc_attr( wp_get_current_user()->user_email ) : ''; ?>" />
			</div>

			<div class="bkx-waitlist-field">
				<label for="waitlist_phone"><?php esc_html_e( 'Phone Number', 'bkx-waiting-lists' ); ?></label>
				<input type="tel" name="phone" id="waitlist_phone" />
			</div>

			<div class="bkx-waitlist-actions">
				<button type="submit" class="bkx-waitlist-submit">
					<?php esc_html_e( 'Join Waiting List', 'bkx-waiting-lists' ); ?>
				</button>
				<button type="button" class="bkx-waitlist-cancel">
					<?php esc_html_e( 'Cancel', 'bkx-waiting-lists' ); ?>
				</button>
			</div>

			<div class="bkx-waitlist-message" style="display:none;"></div>
		</form>
	</div>
</div>

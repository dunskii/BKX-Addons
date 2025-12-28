<?php
/**
 * Class Booking Form Template
 *
 * @package BookingX\ClassBookings
 * @since   1.0.0
 *
 * @var array $session  Session data.
 * @var array $class    Class post.
 * @var array $settings Plugin settings.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$available     = $session['capacity'] - $session['booked_count'];
$require_phone = $settings['require_phone'] ?? false;
$current_user  = wp_get_current_user();
?>

<div class="bkx-booking-form-wrapper">
	<div class="bkx-session-summary">
		<h3><?php echo esc_html( $class->post_title ); ?></h3>
		<p class="bkx-session-datetime">
			<strong><?php echo esc_html( wp_date( 'l, F j, Y', strtotime( $session['session_date'] ) ) ); ?></strong><br>
			<?php echo esc_html( wp_date( 'g:i A', strtotime( $session['start_time'] ) ) ); ?> -
			<?php echo esc_html( wp_date( 'g:i A', strtotime( $session['end_time'] ) ) ); ?>
		</p>
		<p class="bkx-spots-info">
			<?php
			printf(
				/* translators: %d: number of spots */
				esc_html( _n( '%d spot available', '%d spots available', $available, 'bkx-class-bookings' ) ),
				esc_html( $available )
			);
			?>
		</p>
	</div>

	<form id="bkx-class-booking-form" class="bkx-booking-form" method="post">
		<?php wp_nonce_field( 'bkx_book_class', 'bkx_booking_nonce' ); ?>
		<input type="hidden" name="session_id" value="<?php echo esc_attr( $session['id'] ); ?>">
		<input type="hidden" name="action" value="bkx_book_class">

		<div class="bkx-form-row">
			<label for="bkx_customer_name"><?php esc_html_e( 'Name', 'bkx-class-bookings' ); ?> <span class="required">*</span></label>
			<input type="text" id="bkx_customer_name" name="customer_name" required
				   value="<?php echo esc_attr( $current_user->ID ? $current_user->display_name : '' ); ?>">
		</div>

		<div class="bkx-form-row">
			<label for="bkx_customer_email"><?php esc_html_e( 'Email', 'bkx-class-bookings' ); ?> <span class="required">*</span></label>
			<input type="email" id="bkx_customer_email" name="customer_email" required
				   value="<?php echo esc_attr( $current_user->ID ? $current_user->user_email : '' ); ?>">
		</div>

		<div class="bkx-form-row">
			<label for="bkx_customer_phone">
				<?php esc_html_e( 'Phone', 'bkx-class-bookings' ); ?>
				<?php if ( $require_phone ) : ?>
					<span class="required">*</span>
				<?php endif; ?>
			</label>
			<input type="tel" id="bkx_customer_phone" name="customer_phone"
				   <?php echo $require_phone ? 'required' : ''; ?>>
		</div>

		<?php if ( $available > 1 ) : ?>
			<div class="bkx-form-row">
				<label for="bkx_quantity"><?php esc_html_e( 'Number of Spots', 'bkx-class-bookings' ); ?></label>
				<select id="bkx_quantity" name="quantity">
					<?php for ( $i = 1; $i <= min( $available, 5 ); $i++ ) : ?>
						<option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></option>
					<?php endfor; ?>
				</select>
			</div>
		<?php else : ?>
			<input type="hidden" name="quantity" value="1">
		<?php endif; ?>

		<div class="bkx-form-row">
			<label for="bkx_notes"><?php esc_html_e( 'Notes (optional)', 'bkx-class-bookings' ); ?></label>
			<textarea id="bkx_notes" name="notes" rows="3"></textarea>
		</div>

		<div class="bkx-form-actions">
			<button type="submit" class="button button-primary bkx-submit-booking">
				<?php esc_html_e( 'Confirm Booking', 'bkx-class-bookings' ); ?>
			</button>
			<button type="button" class="button bkx-cancel-booking">
				<?php esc_html_e( 'Cancel', 'bkx-class-bookings' ); ?>
			</button>
		</div>

		<div class="bkx-form-message" style="display: none;"></div>
	</form>
</div>

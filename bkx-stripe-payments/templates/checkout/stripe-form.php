<?php
/**
 * Stripe Checkout Form Template
 *
 * This template can be overridden by copying it to yourtheme/bookingx/stripe/checkout-form.php.
 *
 * @package BookingX\StripePayments
 * @since   1.0.0
 * @var int   $booking_id Booking ID
 * @var float $amount     Payment amount
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$addon = bkx_stripe();
if ( ! $addon ) {
	return;
}

$mode = $addon->get_setting( 'stripe_mode', 'test' );
$publishable_key = $addon->get_setting( "stripe_{$mode}_publishable_key", '' );

if ( empty( $publishable_key ) ) {
	?>
	<div class="bkx-stripe-error">
		<p><?php esc_html_e( 'Stripe is not properly configured. Please contact the administrator.', 'bkx-stripe-payments' ); ?></p>
	</div>
	<?php
	return;
}

?>
<div class="bkx-stripe-payment-form">
	<?php if ( 'test' === $mode ) : ?>
		<div class="bkx-stripe-test-notice">
			<p>
				<strong><?php esc_html_e( 'TEST MODE', 'bkx-stripe-payments' ); ?>:</strong>
				<?php esc_html_e( 'Use test card 4242 4242 4242 4242', 'bkx-stripe-payments' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div id="bkx-stripe-payment-element">
		<!-- Stripe Payment Element will be inserted here -->
	</div>

	<div id="bkx-stripe-error-message" class="bkx-stripe-error" style="display: none;"></div>

	<input type="hidden" id="bkx-stripe-booking-id" value="<?php echo esc_attr( $booking_id ); ?>" />
	<input type="hidden" id="bkx-stripe-amount" value="<?php echo esc_attr( $amount ); ?>" />

	<button type="button" id="bkx-stripe-submit-button" class="bkx-btn bkx-btn-primary">
		<span id="bkx-stripe-button-text">
			<?php
			/* translators: %s: Payment amount */
			printf( esc_html__( 'Pay %s', 'bkx-stripe-payments' ), esc_html( number_format( $amount, 2 ) ) );
			?>
		</span>
		<span id="bkx-stripe-spinner" class="bkx-spinner" style="display: none;"></span>
	</button>

	<?php if ( $addon->get_setting( 'enable_apple_pay', false ) || $addon->get_setting( 'enable_google_pay', false ) ) : ?>
		<div id="bkx-stripe-payment-request-button">
			<!-- Apple Pay / Google Pay button will be inserted here -->
		</div>
	<?php endif; ?>
</div>
<?php
// Note: Initialization is handled by stripe-checkout.js via wp_localize_script.
// No inline JavaScript for CSP compliance.

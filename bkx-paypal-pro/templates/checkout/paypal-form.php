<?php
/**
 * PayPal Checkout Form Template
 *
 * This template displays the PayPal payment buttons and card fields.
 *
 * @package BookingX\PayPalPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'bkx_paypal_pro_settings', array() );
$enable_cards = ! empty( $settings['enable_card_fields'] );
?>

<div class="bkx-paypal-checkout-container" data-booking-id="<?php echo esc_attr( $booking_id ); ?>" data-amount="<?php echo esc_attr( $amount ); ?>">

	<!-- PayPal Buttons Container -->
	<div id="paypal-button-container" class="bkx-paypal-buttons"></div>

	<?php if ( $enable_cards ) : ?>
		<!-- Card Fields Container -->
		<div class="bkx-paypal-card-container" style="display: none;">
			<h4><?php esc_html_e( 'Pay with Credit or Debit Card', 'bkx-paypal-pro' ); ?></h4>

			<div class="bkx-paypal-card-fields">
				<div class="card-field">
					<label for="card-number"><?php esc_html_e( 'Card Number', 'bkx-paypal-pro' ); ?></label>
					<div id="card-number" class="card-field-input"></div>
				</div>

				<div class="card-field-row">
					<div class="card-field">
						<label for="expiration-date"><?php esc_html_e( 'Expiration Date', 'bkx-paypal-pro' ); ?></label>
						<div id="expiration-date" class="card-field-input"></div>
					</div>

					<div class="card-field">
						<label for="cvv"><?php esc_html_e( 'CVV', 'bkx-paypal-pro' ); ?></label>
						<div id="cvv" class="card-field-input"></div>
					</div>
				</div>

				<div class="card-field">
					<label for="cardholder-name"><?php esc_html_e( 'Cardholder Name', 'bkx-paypal-pro' ); ?></label>
					<input type="text" id="cardholder-name" class="card-field-input" placeholder="<?php esc_attr_e( 'Full name on card', 'bkx-paypal-pro' ); ?>">
				</div>
			</div>

			<button type="button" id="submit-card-payment" class="button bkx-submit-card-payment">
				<?php esc_html_e( 'Pay Now', 'bkx-paypal-pro' ); ?>
			</button>

			<div class="bkx-paypal-card-errors" style="display: none;"></div>
		</div>

		<!-- Toggle between PayPal and Card -->
		<div class="bkx-payment-toggle">
			<button type="button" class="bkx-toggle-payment" data-target="buttons">
				<?php esc_html_e( 'Pay with PayPal', 'bkx-paypal-pro' ); ?>
			</button>
			<button type="button" class="bkx-toggle-payment" data-target="card">
				<?php esc_html_e( 'Pay with Card', 'bkx-paypal-pro' ); ?>
			</button>
		</div>
	<?php endif; ?>

	<!-- Processing Overlay -->
	<div class="bkx-paypal-processing" style="display: none;">
		<div class="bkx-spinner"></div>
		<p><?php esc_html_e( 'Processing payment...', 'bkx-paypal-pro' ); ?></p>
	</div>

	<!-- Error Messages -->
	<div class="bkx-paypal-errors" style="display: none;"></div>
</div>

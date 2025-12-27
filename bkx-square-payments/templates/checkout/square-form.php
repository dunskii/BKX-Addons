<?php
/**
 * Square Payment Form Template
 *
 * This template displays the Square payment form on checkout.
 *
 * @package BookingX\SquarePayments
 * @var int   $booking_id Booking ID
 * @var float $amount     Payment amount
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div id="bkx-square-payment-form" class="bkx-payment-form">
	<div class="bkx-square-payment-header">
		<h3><?php esc_html_e( 'Pay with Square', 'bkx-square-payments' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Secure payment processed by Square. We accept all major credit cards.', 'bkx-square-payments' ); ?>
		</p>
	</div>

	<div class="bkx-square-payment-methods">
		<!-- Card Payment Method -->
		<div id="bkx-square-card-container" class="bkx-payment-method">
			<div id="bkx-square-card-number" class="bkx-square-card-field"></div>
			<div class="bkx-square-card-row">
				<div id="bkx-square-card-expiry" class="bkx-square-card-field"></div>
				<div id="bkx-square-card-cvv" class="bkx-square-card-field"></div>
			</div>
			<div id="bkx-square-card-postal" class="bkx-square-card-field"></div>
		</div>

		<?php if ( bkx_square_payments()->get_setting( 'enable_apple_pay', false ) ) : ?>
			<!-- Apple Pay Button -->
			<div id="bkx-square-apple-pay-button" class="bkx-payment-method bkx-digital-wallet"></div>
		<?php endif; ?>

		<?php if ( bkx_square_payments()->get_setting( 'enable_google_pay', false ) ) : ?>
			<!-- Google Pay Button -->
			<div id="bkx-square-google-pay-button" class="bkx-payment-method bkx-digital-wallet"></div>
		<?php endif; ?>

		<?php if ( bkx_square_payments()->get_setting( 'enable_cash_app_pay', false ) ) : ?>
			<!-- Cash App Pay Button -->
			<div id="bkx-square-cash-app-pay-button" class="bkx-payment-method bkx-digital-wallet"></div>
		<?php endif; ?>
	</div>

	<div class="bkx-square-messages">
		<div id="bkx-square-error-message" class="bkx-error-message" style="display: none;"></div>
		<div id="bkx-square-success-message" class="bkx-success-message" style="display: none;"></div>
	</div>

	<div class="bkx-square-payment-actions">
		<button type="button" id="bkx-square-pay-button" class="button button-primary bkx-pay-button">
			<?php
			printf(
				/* translators: %s: Formatted payment amount */
				esc_html__( 'Pay %s', 'bkx-square-payments' ),
				esc_html( bkx_format_price( $amount ) )
			);
			?>
		</button>
		<span class="bkx-square-processing" style="display: none;">
			<span class="spinner is-active"></span>
			<?php esc_html_e( 'Processing...', 'bkx-square-payments' ); ?>
		</span>
	</div>

	<div class="bkx-square-security-badge">
		<span class="dashicons dashicons-lock"></span>
		<?php esc_html_e( 'Secure payment powered by Square', 'bkx-square-payments' ); ?>
	</div>

	<!-- Hidden fields -->
	<input type="hidden" id="bkx-square-booking-id" value="<?php echo esc_attr( $booking_id ); ?>">
	<input type="hidden" id="bkx-square-amount" value="<?php echo esc_attr( $amount ); ?>">
	<input type="hidden" id="bkx-square-nonce-field" name="square_nonce" value="">
	<input type="hidden" id="bkx-square-source-id" name="square_source_id" value="">
	<input type="hidden" id="bkx-square-verification-token" name="square_verification_token" value="">
</div>

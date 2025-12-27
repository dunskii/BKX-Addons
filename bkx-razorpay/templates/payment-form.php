<?php
/**
 * Razorpay Payment Form Template
 *
 * This template displays the Razorpay payment button.
 *
 * @package BookingX\Razorpay
 * @since   1.0.0
 *
 * @var int    $booking_id Booking ID.
 * @var float  $amount     Amount to pay.
 * @var string $currency   Currency code.
 * @var array  $settings   Gateway settings.
 */

defined( 'ABSPATH' ) || exit;

// Get business details for checkout.
$business_name = get_bloginfo( 'name' );
$business_logo = '';

// Try to get custom logo URL if set.
$custom_logo_id = get_theme_mod( 'custom_logo' );
if ( $custom_logo_id ) {
	$logo_url = wp_get_attachment_image_url( $custom_logo_id, 'thumbnail' );
	if ( $logo_url ) {
		$business_logo = $logo_url;
	}
}

// Prepare button text.
$button_text = sprintf(
	/* translators: %s: formatted amount */
	__( 'Pay %s with Razorpay', 'bkx-razorpay' ),
	bkx_format_price( $amount, $currency )
);
?>

<div id="bkx-razorpay-payment-form" class="bkx-payment-form bkx-razorpay-form">
	<div class="bkx-razorpay-payment-container">
		<?php
		/**
		 * Fires before the Razorpay payment form.
		 *
		 * @since 1.0.0
		 * @param int   $booking_id Booking ID.
		 * @param float $amount     Amount to pay.
		 */
		do_action( 'bkx_razorpay_before_payment_form', $booking_id, $amount );
		?>

		<div class="bkx-razorpay-amount-display">
			<span class="bkx-razorpay-label"><?php esc_html_e( 'Amount to Pay:', 'bkx-razorpay' ); ?></span>
			<span class="bkx-razorpay-amount"><?php echo esc_html( bkx_format_price( $amount, $currency ) ); ?></span>
		</div>

		<div class="bkx-razorpay-methods">
			<span class="bkx-razorpay-methods-label"><?php esc_html_e( 'Pay securely with:', 'bkx-razorpay' ); ?></span>
			<div class="bkx-razorpay-method-icons">
				<?php if ( ! empty( $settings['enable_upi'] ) ) : ?>
					<span class="bkx-razorpay-method" title="<?php esc_attr_e( 'UPI', 'bkx-razorpay' ); ?>">
						<svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor">
							<path d="M10.5 13.5L7 7l7 3.5-3.5 3zm11.2-7.1l-3.1 15.1c-.1.5-.7.7-1.1.4l-4.5-3.2-2.2 2.1c-.3.3-.8.1-.8-.3v-4l8-7.5-9.9 6.6-4.1-1.5c-.5-.2-.5-.9 0-1.1l16.6-7.1c.5-.2 1 .2.9.7z"/>
						</svg>
					</span>
				<?php endif; ?>
				<?php if ( ! empty( $settings['enable_cards'] ) ) : ?>
					<span class="bkx-razorpay-method" title="<?php esc_attr_e( 'Credit/Debit Cards', 'bkx-razorpay' ); ?>">
						<svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor">
							<path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>
						</svg>
					</span>
				<?php endif; ?>
				<?php if ( ! empty( $settings['enable_netbanking'] ) ) : ?>
					<span class="bkx-razorpay-method" title="<?php esc_attr_e( 'Net Banking', 'bkx-razorpay' ); ?>">
						<svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor">
							<path d="M4 10v7h3v-7H4zm6 0v7h3v-7h-3zM2 22h19v-3H2v3zm14-12v7h3v-7h-3zm-4.5-9L2 6v2h19V6l-9.5-5z"/>
						</svg>
					</span>
				<?php endif; ?>
				<?php if ( ! empty( $settings['enable_wallets'] ) ) : ?>
					<span class="bkx-razorpay-method" title="<?php esc_attr_e( 'Wallets', 'bkx-razorpay' ); ?>">
						<svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor">
							<path d="M21 7.28V5c0-1.1-.9-2-2-2H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-2.28c.59-.35 1-.98 1-1.72V9c0-.74-.41-1.38-1-1.72zM20 9v6h-7V9h7zM5 19V5h14v2h-6c-1.1 0-2 .9-2 2v6c0 1.1.9 2 2 2h6v2H5z"/>
							<circle cx="16" cy="12" r="1.5"/>
						</svg>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<button type="button"
				id="bkx-razorpay-pay-button"
				class="bkx-button bkx-razorpay-button"
				data-booking-id="<?php echo esc_attr( $booking_id ); ?>"
				data-amount="<?php echo esc_attr( $amount ); ?>"
				data-currency="<?php echo esc_attr( $currency ); ?>">
			<span class="bkx-razorpay-button-text"><?php echo esc_html( $button_text ); ?></span>
			<span class="bkx-razorpay-button-spinner" style="display: none;">
				<svg class="bkx-spinner" viewBox="0 0 24 24" width="20" height="20">
					<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4 31.4" stroke-linecap="round">
						<animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
					</circle>
				</svg>
			</span>
		</button>

		<div id="bkx-razorpay-error" class="bkx-razorpay-error" style="display: none;"></div>

		<?php wp_nonce_field( 'bkx_razorpay_payment', 'bkx_razorpay_nonce' ); ?>

		<?php
		/**
		 * Fires after the Razorpay payment form.
		 *
		 * @since 1.0.0
		 * @param int   $booking_id Booking ID.
		 * @param float $amount     Amount to pay.
		 */
		do_action( 'bkx_razorpay_after_payment_form', $booking_id, $amount );
		?>

		<div class="bkx-razorpay-secure-badge">
			<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
				<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/>
			</svg>
			<span><?php esc_html_e( 'Secured by Razorpay', 'bkx-razorpay' ); ?></span>
		</div>
	</div>
</div>
<?php
// Note: The bkxRazorpayConfig is set via wp_localize_script() in RazorpayAddon::enqueue_frontend_scripts().
// This template-specific config merges with the base config for this specific booking.
wp_add_inline_script(
	'bkx-razorpay-payment',
	sprintf(
		'window.bkxRazorpayConfig = Object.assign({}, window.bkxRazorpay || {}, %s);',
		wp_json_encode(
			array(
				'bookingId'    => $booking_id,
				'amount'       => $amount,
				'currency'     => $currency,
				'businessName' => $business_name,
				'businessLogo' => $business_logo,
				'i18n'         => array(
					'processing'    => __( 'Processing...', 'bkx-razorpay' ),
					'error'         => __( 'An error occurred. Please try again.', 'bkx-razorpay' ),
					'paymentFailed' => __( 'Payment failed. Please try again.', 'bkx-razorpay' ),
					'redirecting'   => __( 'Payment successful! Redirecting...', 'bkx-razorpay' ),
				),
			)
		)
	),
	'before'
);
?>

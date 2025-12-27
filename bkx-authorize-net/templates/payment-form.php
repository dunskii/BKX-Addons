<?php
/**
 * Authorize.net Payment Form Template
 *
 * This template renders the credit card payment form using Accept.js.
 *
 * @package BookingX\AuthorizeNet
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Variables available in this template:
 *
 * @var string $booking_id   The booking ID.
 * @var string $amount       The payment amount.
 * @var array  $card_types   Accepted card types.
 * @var bool   $require_cvv  Whether CVV is required.
 * @var string $nonce        Security nonce.
 */
?>
<div id="bkx-authorize-net-payment-form" class="bkx-payment-form">
	<div class="bkx-payment-form-header">
		<h3><?php esc_html_e( 'Credit Card Payment', 'bkx-authorize-net' ); ?></h3>
		<div class="bkx-accepted-cards">
			<?php foreach ( $card_types as $card_type ) : ?>
				<span class="bkx-card-icon bkx-card-<?php echo esc_attr( $card_type ); ?>">
					<span class="screen-reader-text"><?php echo esc_html( ucfirst( $card_type ) ); ?></span>
				</span>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="bkx-payment-form-body">
		<!-- Card Number -->
		<div class="bkx-form-row">
			<label for="bkx-card-number">
				<?php esc_html_e( 'Card Number', 'bkx-authorize-net' ); ?>
				<span class="required">*</span>
			</label>
			<div class="bkx-input-wrapper">
				<input type="text" id="bkx-card-number" name="card_number"
					class="bkx-input"
					placeholder="1234 5678 9012 3456"
					autocomplete="cc-number"
					maxlength="19"
					inputmode="numeric"
					pattern="[0-9\s]*"
					required>
				<span class="bkx-card-icon-detected"></span>
			</div>
		</div>

		<!-- Expiration Date -->
		<div class="bkx-form-row bkx-form-row-half">
			<div class="bkx-form-col">
				<label for="bkx-exp-month">
					<?php esc_html_e( 'Expiration', 'bkx-authorize-net' ); ?>
					<span class="required">*</span>
				</label>
				<div class="bkx-expiry-wrapper">
					<select id="bkx-exp-month" name="exp_month" class="bkx-select" autocomplete="cc-exp-month" required>
						<option value=""><?php esc_html_e( 'MM', 'bkx-authorize-net' ); ?></option>
						<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
							<option value="<?php echo esc_attr( str_pad( $i, 2, '0', STR_PAD_LEFT ) ); ?>">
								<?php echo esc_html( str_pad( $i, 2, '0', STR_PAD_LEFT ) ); ?>
							</option>
						<?php endfor; ?>
					</select>
					<span class="bkx-expiry-separator">/</span>
					<select id="bkx-exp-year" name="exp_year" class="bkx-select" autocomplete="cc-exp-year" required>
						<option value=""><?php esc_html_e( 'YY', 'bkx-authorize-net' ); ?></option>
						<?php
						$current_year = (int) gmdate( 'Y' );
						for ( $i = 0; $i < 15; $i++ ) :
							$year = $current_year + $i;
							$short_year = substr( (string) $year, -2 );
							?>
							<option value="<?php echo esc_attr( $year ); ?>">
								<?php echo esc_html( $short_year ); ?>
							</option>
						<?php endfor; ?>
					</select>
				</div>
			</div>

			<?php if ( $require_cvv ) : ?>
			<!-- CVV -->
			<div class="bkx-form-col">
				<label for="bkx-cvv">
					<?php esc_html_e( 'CVV', 'bkx-authorize-net' ); ?>
					<span class="required">*</span>
				</label>
				<input type="text" id="bkx-cvv" name="cvv"
					class="bkx-input"
					placeholder="123"
					autocomplete="cc-csc"
					maxlength="4"
					inputmode="numeric"
					pattern="[0-9]*"
					required>
			</div>
			<?php endif; ?>
		</div>

		<!-- Cardholder Name (optional but recommended) -->
		<div class="bkx-form-row">
			<label for="bkx-cardholder-name">
				<?php esc_html_e( 'Cardholder Name', 'bkx-authorize-net' ); ?>
			</label>
			<input type="text" id="bkx-cardholder-name" name="cardholder_name"
				class="bkx-input"
				placeholder="<?php esc_attr_e( 'Name on card', 'bkx-authorize-net' ); ?>"
				autocomplete="cc-name">
		</div>
	</div>

	<!-- Hidden fields for Accept.js response -->
	<input type="hidden" id="bkx-opaque-data-descriptor" name="opaque_data_descriptor">
	<input type="hidden" id="bkx-opaque-data-value" name="opaque_data_value">
	<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking_id ); ?>">
	<?php wp_nonce_field( 'bkx_authorize_net_payment', 'bkx_payment_nonce' ); ?>

	<!-- Error display -->
	<div id="bkx-payment-errors" class="bkx-payment-errors" role="alert" aria-live="polite"></div>

	<!-- Submit button -->
	<div class="bkx-form-row bkx-form-actions">
		<button type="submit" id="bkx-submit-payment" class="bkx-button bkx-button-primary">
			<span class="bkx-button-text">
				<?php
				printf(
					/* translators: %s: payment amount */
					esc_html__( 'Pay %s', 'bkx-authorize-net' ),
					esc_html( $amount )
				);
				?>
			</span>
			<span class="bkx-button-spinner" style="display: none;">
				<span class="spinner"></span>
				<?php esc_html_e( 'Processing...', 'bkx-authorize-net' ); ?>
			</span>
		</button>
	</div>

	<!-- Security badges -->
	<div class="bkx-payment-security">
		<span class="bkx-security-badge">
			<span class="dashicons dashicons-lock"></span>
			<?php esc_html_e( 'Secure Payment', 'bkx-authorize-net' ); ?>
		</span>
		<span class="bkx-security-badge">
			<span class="dashicons dashicons-shield"></span>
			<?php esc_html_e( 'PCI Compliant', 'bkx-authorize-net' ); ?>
		</span>
	</div>
</div>

<?php
/**
 * Frontend Coupon Field Template
 *
 * @package BookingX\CouponCodes
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$addon       = \BookingX\CouponCodes\CouponCodesAddon::get_instance();
$field_label = $addon->get_setting( 'coupon_field_label', __( 'Have a coupon code?', 'bkx-coupon-codes' ) );
$placeholder = $addon->get_setting( 'coupon_placeholder', __( 'Enter coupon code', 'bkx-coupon-codes' ) );
$button_text = $addon->get_setting( 'apply_button_text', __( 'Apply', 'bkx-coupon-codes' ) );
?>

<div class="bkx-coupon-field">
	<label for="bkx-coupon-code"><?php echo esc_html( $field_label ); ?></label>

	<div class="bkx-coupon-input-area">
		<input type="text"
			id="bkx-coupon-code"
			class="bkx-coupon-input"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			autocomplete="off"
			spellcheck="false">
		<button type="button" class="bkx-apply-coupon">
			<?php echo esc_html( $button_text ); ?>
		</button>
	</div>

	<div class="bkx-coupon-applied" style="display: none;">
		<!-- Populated by JavaScript when coupon is applied -->
	</div>

	<div class="bkx-coupon-message" style="display: none;"></div>
</div>

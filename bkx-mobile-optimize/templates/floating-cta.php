<?php
/**
 * Floating CTA Template.
 *
 * @package BookingX\MobileOptimize
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\MobileOptimize\MobileOptimizeAddon::get_instance();
$ui       = $addon->get_service( 'mobile_ui' );
$config   = $ui->get_floating_cta_config();
$booking_page = get_option( 'bkx_booking_page' );
$booking_url  = $booking_page ? get_permalink( $booking_page ) : home_url( '/book/' );
?>

<div id="bkx-floating-cta" class="bkx-floating-cta" style="display: none;">
	<a href="<?php echo esc_url( $booking_url ); ?>" class="bkx-floating-cta-button">
		<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
			<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
			<line x1="16" y1="2" x2="16" y2="6"></line>
			<line x1="8" y1="2" x2="8" y2="6"></line>
			<line x1="3" y1="10" x2="21" y2="10"></line>
		</svg>
		<span><?php echo esc_html( $config['text'] ); ?></span>
	</a>
</div>

<style>
.bkx-floating-cta {
	position: fixed;
	bottom: 24px;
	right: 24px;
	z-index: 9999;
}

.bkx-floating-cta-button {
	display: flex;
	align-items: center;
	gap: 8px;
	background: var(--bkx-primary-color, #2563eb);
	color: #fff;
	padding: 14px 24px;
	border-radius: 50px;
	text-decoration: none;
	font-weight: 500;
	font-size: 15px;
	box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
	transition: transform 0.2s, box-shadow 0.2s;
}

.bkx-floating-cta-button:hover,
.bkx-floating-cta-button:focus {
	transform: scale(1.05);
	box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
	color: #fff;
}

.bkx-floating-cta-button:active {
	transform: scale(0.98);
}

.bkx-floating-cta-button svg {
	width: 20px;
	height: 20px;
}

/* Animation */
.bkx-floating-cta.visible {
	animation: bkxFloatIn 0.3s ease-out;
}

@keyframes bkxFloatIn {
	from {
		opacity: 0;
		transform: translateY(20px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}

/* Hide on booking form pages */
.bkx-booking-form ~ .bkx-floating-cta,
.single-bkx_booking .bkx-floating-cta {
	display: none !important;
}

/* Compact mode for smaller screens */
@media (max-width: 400px) {
	.bkx-floating-cta-button span {
		display: none;
	}

	.bkx-floating-cta-button {
		padding: 16px;
		border-radius: 50%;
	}

	.bkx-floating-cta-button svg {
		width: 24px;
		height: 24px;
	}
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
	.bkx-floating-cta-button {
		transition: none;
	}

	.bkx-floating-cta.visible {
		animation: none;
	}
}
</style>

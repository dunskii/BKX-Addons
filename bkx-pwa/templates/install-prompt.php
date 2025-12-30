<?php
/**
 * PWA Install Prompt Template.
 *
 * @package BookingX\PWA
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\PWA\PWAAddon::get_instance();
$settings = get_option( 'bkx_pwa_settings', array() );
$position = $addon->get_setting( 'install_prompt_position', 'bottom' );
?>

<!-- PWA Install Prompt -->
<div id="bkx-pwa-install-prompt" class="bkx-install-prompt bkx-prompt-<?php echo esc_attr( $position ); ?>" style="display: none;">
	<div class="bkx-install-prompt-inner">
		<button type="button" class="bkx-install-close" aria-label="<?php esc_attr_e( 'Close', 'bkx-pwa' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<line x1="18" y1="6" x2="6" y2="18"></line>
				<line x1="6" y1="6" x2="18" y2="18"></line>
			</svg>
		</button>

		<div class="bkx-install-content">
			<?php
			$icon = $addon->get_setting( 'icon_192' );
			if ( ! $icon ) {
				$icon = get_site_icon_url( 192 );
			}
			if ( $icon ) :
				?>
				<img src="<?php echo esc_url( $icon ); ?>" alt="" class="bkx-install-icon">
			<?php endif; ?>

			<div class="bkx-install-text">
				<strong><?php echo esc_html( $addon->get_setting( 'install_prompt_title', __( 'Install Our App', 'bkx-pwa' ) ) ); ?></strong>
				<p><?php echo esc_html( $addon->get_setting( 'install_prompt_description', __( 'Install our app for a better booking experience!', 'bkx-pwa' ) ) ); ?></p>
			</div>
		</div>

		<div class="bkx-install-actions">
			<button type="button" class="bkx-install-dismiss">
				<?php echo esc_html( $addon->get_setting( 'dismiss_button_text', __( 'Not Now', 'bkx-pwa' ) ) ); ?>
			</button>
			<button type="button" class="bkx-install-button">
				<?php echo esc_html( $addon->get_setting( 'install_button_text', __( 'Install', 'bkx-pwa' ) ) ); ?>
			</button>
		</div>
	</div>
</div>

<!-- iOS Install Instructions -->
<div id="bkx-ios-install-prompt" class="bkx-ios-prompt" style="display: none;">
	<div class="bkx-ios-prompt-inner">
		<button type="button" class="bkx-ios-close" aria-label="<?php esc_attr_e( 'Close', 'bkx-pwa' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<line x1="18" y1="6" x2="6" y2="18"></line>
				<line x1="6" y1="6" x2="18" y2="18"></line>
			</svg>
		</button>

		<h3><?php esc_html_e( 'Install on iOS', 'bkx-pwa' ); ?></h3>

		<ol class="bkx-ios-steps">
			<li>
				<span class="bkx-ios-step-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
						<polyline points="16 6 12 2 8 6"></polyline>
						<line x1="12" y1="2" x2="12" y2="15"></line>
					</svg>
				</span>
				<span><?php esc_html_e( 'Tap the Share button', 'bkx-pwa' ); ?></span>
			</li>
			<li>
				<span class="bkx-ios-step-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
						<line x1="12" y1="8" x2="12" y2="16"></line>
						<line x1="8" y1="12" x2="16" y2="12"></line>
					</svg>
				</span>
				<span><?php esc_html_e( 'Select "Add to Home Screen"', 'bkx-pwa' ); ?></span>
			</li>
			<li>
				<span class="bkx-ios-step-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<polyline points="20 6 9 17 4 12"></polyline>
					</svg>
				</span>
				<span><?php esc_html_e( 'Tap "Add" to confirm', 'bkx-pwa' ); ?></span>
			</li>
		</ol>

		<button type="button" class="bkx-ios-dismiss">
			<?php esc_html_e( 'Got it', 'bkx-pwa' ); ?>
		</button>
	</div>
</div>

<style>
.bkx-install-prompt {
	position: fixed;
	left: 0;
	right: 0;
	z-index: 999999;
	padding: 16px;
	animation: bkxSlideIn 0.3s ease-out;
}

.bkx-prompt-bottom {
	bottom: 0;
}

.bkx-prompt-top {
	top: 0;
}

@keyframes bkxSlideIn {
	from {
		transform: translateY(100%);
		opacity: 0;
	}
	to {
		transform: translateY(0);
		opacity: 1;
	}
}

.bkx-install-prompt-inner {
	background: #fff;
	border-radius: 16px;
	box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
	padding: 20px;
	max-width: 500px;
	margin: 0 auto;
	position: relative;
}

.bkx-install-close {
	position: absolute;
	top: 12px;
	right: 12px;
	background: none;
	border: none;
	cursor: pointer;
	padding: 4px;
	color: #9ca3af;
}

.bkx-install-content {
	display: flex;
	align-items: center;
	gap: 16px;
	margin-bottom: 16px;
}

.bkx-install-icon {
	width: 56px;
	height: 56px;
	border-radius: 12px;
	flex-shrink: 0;
}

.bkx-install-text strong {
	display: block;
	font-size: 16px;
	color: #1f2937;
	margin-bottom: 4px;
}

.bkx-install-text p {
	font-size: 14px;
	color: #6b7280;
	margin: 0;
	line-height: 1.4;
}

.bkx-install-actions {
	display: flex;
	gap: 12px;
	justify-content: flex-end;
}

.bkx-install-dismiss {
	background: none;
	border: none;
	color: #6b7280;
	font-size: 14px;
	cursor: pointer;
	padding: 10px 16px;
}

.bkx-install-button {
	background: <?php echo esc_attr( $addon->get_setting( 'theme_color', '#2563eb' ) ); ?>;
	color: #fff;
	border: none;
	border-radius: 8px;
	padding: 10px 24px;
	font-size: 14px;
	font-weight: 500;
	cursor: pointer;
}

/* iOS Prompt */
.bkx-ios-prompt {
	position: fixed;
	inset: 0;
	background: rgba(0, 0, 0, 0.5);
	z-index: 999999;
	display: flex;
	align-items: flex-end;
	justify-content: center;
	padding: 16px;
}

.bkx-ios-prompt-inner {
	background: #fff;
	border-radius: 16px;
	padding: 24px;
	width: 100%;
	max-width: 400px;
	position: relative;
}

.bkx-ios-close {
	position: absolute;
	top: 12px;
	right: 12px;
	background: none;
	border: none;
	cursor: pointer;
	color: #9ca3af;
}

.bkx-ios-prompt h3 {
	font-size: 18px;
	font-weight: 600;
	color: #1f2937;
	margin: 0 0 20px;
	text-align: center;
}

.bkx-ios-steps {
	list-style: none;
	padding: 0;
	margin: 0 0 20px;
}

.bkx-ios-steps li {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 0;
	border-bottom: 1px solid #f3f4f6;
}

.bkx-ios-steps li:last-child {
	border-bottom: none;
}

.bkx-ios-step-icon {
	width: 40px;
	height: 40px;
	background: #f3f4f6;
	border-radius: 10px;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #6b7280;
}

.bkx-ios-dismiss {
	width: 100%;
	background: <?php echo esc_attr( $addon->get_setting( 'theme_color', '#2563eb' ) ); ?>;
	color: #fff;
	border: none;
	border-radius: 8px;
	padding: 14px;
	font-size: 16px;
	font-weight: 500;
	cursor: pointer;
}

@media (prefers-color-scheme: dark) {
	.bkx-install-prompt-inner,
	.bkx-ios-prompt-inner {
		background: #1f2937;
	}

	.bkx-install-text strong,
	.bkx-ios-prompt h3 {
		color: #f9fafb;
	}

	.bkx-install-text p {
		color: #9ca3af;
	}

	.bkx-ios-steps li {
		border-color: #374151;
	}

	.bkx-ios-step-icon {
		background: #374151;
	}
}
</style>

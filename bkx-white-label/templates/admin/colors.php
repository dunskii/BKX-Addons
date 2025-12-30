<?php
/**
 * Colors settings template.
 *
 * @package BookingX\WhiteLabel
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WhiteLabel\WhiteLabelAddon::get_instance();
$settings = $addon->get_settings();

$color_scheme = $addon->get_service( 'color_scheme' );
?>

<div class="bkx-colors-settings">
	<h2><?php esc_html_e( 'Color Scheme', 'bkx-white-label' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Customize the colors used throughout BookingX admin and frontend.', 'bkx-white-label' ); ?></p>

	<div class="bkx-color-grid">
		<div class="bkx-color-section">
			<h3><?php esc_html_e( 'Primary Colors', 'bkx-white-label' ); ?></h3>

			<div class="bkx-color-row">
				<label for="primary_color"><?php esc_html_e( 'Primary Color', 'bkx-white-label' ); ?></label>
				<input type="text" id="primary_color" name="primary_color" value="<?php echo esc_attr( $settings['primary_color'] ?? '#2271b1' ); ?>" class="bkx-color-picker">
				<span class="description"><?php esc_html_e( 'Main brand color for buttons and links.', 'bkx-white-label' ); ?></span>
			</div>

			<div class="bkx-color-row">
				<label for="secondary_color"><?php esc_html_e( 'Secondary Color', 'bkx-white-label' ); ?></label>
				<input type="text" id="secondary_color" name="secondary_color" value="<?php echo esc_attr( $settings['secondary_color'] ?? '#135e96' ); ?>" class="bkx-color-picker">
				<span class="description"><?php esc_html_e( 'Darker shade for hover states.', 'bkx-white-label' ); ?></span>
			</div>

			<div class="bkx-color-row">
				<label for="accent_color"><?php esc_html_e( 'Accent Color', 'bkx-white-label' ); ?></label>
				<input type="text" id="accent_color" name="accent_color" value="<?php echo esc_attr( $settings['accent_color'] ?? '#72aee6' ); ?>" class="bkx-color-picker">
				<span class="description"><?php esc_html_e( 'Lighter shade for highlights.', 'bkx-white-label' ); ?></span>
			</div>
		</div>

		<div class="bkx-color-section">
			<h3><?php esc_html_e( 'Status Colors', 'bkx-white-label' ); ?></h3>

			<div class="bkx-color-row">
				<label for="success_color"><?php esc_html_e( 'Success Color', 'bkx-white-label' ); ?></label>
				<input type="text" id="success_color" name="success_color" value="<?php echo esc_attr( $settings['success_color'] ?? '#00a32a' ); ?>" class="bkx-color-picker">
				<span class="description"><?php esc_html_e( 'Completed bookings, confirmations.', 'bkx-white-label' ); ?></span>
			</div>

			<div class="bkx-color-row">
				<label for="warning_color"><?php esc_html_e( 'Warning Color', 'bkx-white-label' ); ?></label>
				<input type="text" id="warning_color" name="warning_color" value="<?php echo esc_attr( $settings['warning_color'] ?? '#dba617' ); ?>" class="bkx-color-picker">
				<span class="description"><?php esc_html_e( 'Pending bookings, alerts.', 'bkx-white-label' ); ?></span>
			</div>

			<div class="bkx-color-row">
				<label for="error_color"><?php esc_html_e( 'Error Color', 'bkx-white-label' ); ?></label>
				<input type="text" id="error_color" name="error_color" value="<?php echo esc_attr( $settings['error_color'] ?? '#d63638' ); ?>" class="bkx-color-picker">
				<span class="description"><?php esc_html_e( 'Cancelled bookings, errors.', 'bkx-white-label' ); ?></span>
			</div>
		</div>

		<div class="bkx-color-section">
			<h3><?php esc_html_e( 'Base Colors', 'bkx-white-label' ); ?></h3>

			<div class="bkx-color-row">
				<label for="text_color"><?php esc_html_e( 'Text Color', 'bkx-white-label' ); ?></label>
				<input type="text" id="text_color" name="text_color" value="<?php echo esc_attr( $settings['text_color'] ?? '#1d2327' ); ?>" class="bkx-color-picker">
				<span class="description"><?php esc_html_e( 'Main text color.', 'bkx-white-label' ); ?></span>
			</div>

			<div class="bkx-color-row">
				<label for="background_color"><?php esc_html_e( 'Background Color', 'bkx-white-label' ); ?></label>
				<input type="text" id="background_color" name="background_color" value="<?php echo esc_attr( $settings['background_color'] ?? '#ffffff' ); ?>" class="bkx-color-picker">
				<span class="description"><?php esc_html_e( 'Main background color.', 'bkx-white-label' ); ?></span>
			</div>
		</div>
	</div>

	<div class="bkx-color-presets">
		<h3><?php esc_html_e( 'Color Presets', 'bkx-white-label' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Quick-apply popular color schemes.', 'bkx-white-label' ); ?></p>

		<div class="bkx-preset-buttons">
			<button type="button" class="button bkx-preset" data-preset="default">
				<span class="bkx-preset-colors">
					<span style="background: #2271b1;"></span>
					<span style="background: #135e96;"></span>
					<span style="background: #72aee6;"></span>
				</span>
				<?php esc_html_e( 'Default Blue', 'bkx-white-label' ); ?>
			</button>

			<button type="button" class="button bkx-preset" data-preset="green">
				<span class="bkx-preset-colors">
					<span style="background: #00a32a;"></span>
					<span style="background: #007c1e;"></span>
					<span style="background: #6ce597;"></span>
				</span>
				<?php esc_html_e( 'Nature Green', 'bkx-white-label' ); ?>
			</button>

			<button type="button" class="button bkx-preset" data-preset="purple">
				<span class="bkx-preset-colors">
					<span style="background: #7c3aed;"></span>
					<span style="background: #5b21b6;"></span>
					<span style="background: #a78bfa;"></span>
				</span>
				<?php esc_html_e( 'Royal Purple', 'bkx-white-label' ); ?>
			</button>

			<button type="button" class="button bkx-preset" data-preset="orange">
				<span class="bkx-preset-colors">
					<span style="background: #ea580c;"></span>
					<span style="background: #c2410c;"></span>
					<span style="background: #fb923c;"></span>
				</span>
				<?php esc_html_e( 'Sunset Orange', 'bkx-white-label' ); ?>
			</button>

			<button type="button" class="button bkx-preset" data-preset="teal">
				<span class="bkx-preset-colors">
					<span style="background: #0d9488;"></span>
					<span style="background: #0f766e;"></span>
					<span style="background: #5eead4;"></span>
				</span>
				<?php esc_html_e( 'Ocean Teal', 'bkx-white-label' ); ?>
			</button>

			<button type="button" class="button bkx-preset" data-preset="pink">
				<span class="bkx-preset-colors">
					<span style="background: #db2777;"></span>
					<span style="background: #be185d;"></span>
					<span style="background: #f472b6;"></span>
				</span>
				<?php esc_html_e( 'Rose Pink', 'bkx-white-label' ); ?>
			</button>
		</div>
	</div>

	<div class="bkx-color-preview">
		<h3><?php esc_html_e( 'Live Preview', 'bkx-white-label' ); ?></h3>

		<div class="bkx-preview-container">
			<div class="bkx-preview-card">
				<div class="bkx-preview-header">
					<h4><?php esc_html_e( 'Sample Booking Form', 'bkx-white-label' ); ?></h4>
				</div>
				<div class="bkx-preview-content">
					<div class="bkx-preview-service bkx-preview-selected">
						<span class="bkx-preview-check">&#10003;</span>
						<?php esc_html_e( 'Premium Consultation', 'bkx-white-label' ); ?>
					</div>
					<div class="bkx-preview-service">
						<?php esc_html_e( 'Basic Service', 'bkx-white-label' ); ?>
					</div>
					<button type="button" class="bkx-preview-button"><?php esc_html_e( 'Book Now', 'bkx-white-label' ); ?></button>
				</div>
			</div>

			<div class="bkx-preview-statuses">
				<span class="bkx-preview-status success"><?php esc_html_e( 'Completed', 'bkx-white-label' ); ?></span>
				<span class="bkx-preview-status warning"><?php esc_html_e( 'Pending', 'bkx-white-label' ); ?></span>
				<span class="bkx-preview-status error"><?php esc_html_e( 'Cancelled', 'bkx-white-label' ); ?></span>
			</div>
		</div>
	</div>
</div>

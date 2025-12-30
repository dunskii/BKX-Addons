<?php
/**
 * Divi Integration Settings.
 *
 * @package BookingX\Divi
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_divi_settings', array() );
?>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'General Settings', 'bkx-divi' ); ?></h2>

	<form id="bkx-divi-settings-form" method="post">
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Modules', 'bkx-divi' ); ?></th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="enable_modules" value="1"
							   <?php checked( isset( $settings['enable_modules'] ) ? $settings['enable_modules'] : true, true ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enable BookingX modules in Divi Builder.', 'bkx-divi' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable Animations', 'bkx-divi' ); ?></th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="animation_enabled" value="1"
							   <?php checked( isset( $settings['animation_enabled'] ) ? $settings['animation_enabled'] : true, true ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enable animations for BookingX modules.', 'bkx-divi' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Booking Form Style', 'bkx-divi' ); ?></th>
				<td>
					<select name="booking_form_style">
						<option value="default" <?php selected( isset( $settings['booking_form_style'] ) ? $settings['booking_form_style'] : 'default', 'default' ); ?>>
							<?php esc_html_e( 'Default', 'bkx-divi' ); ?>
						</option>
						<option value="modern" <?php selected( isset( $settings['booking_form_style'] ) ? $settings['booking_form_style'] : '', 'modern' ); ?>>
							<?php esc_html_e( 'Modern', 'bkx-divi' ); ?>
						</option>
						<option value="minimal" <?php selected( isset( $settings['booking_form_style'] ) ? $settings['booking_form_style'] : '', 'minimal' ); ?>>
							<?php esc_html_e( 'Minimal', 'bkx-divi' ); ?>
						</option>
						<option value="rounded" <?php selected( isset( $settings['booking_form_style'] ) ? $settings['booking_form_style'] : '', 'rounded' ); ?>>
							<?php esc_html_e( 'Rounded', 'bkx-divi' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Default style for booking form modules.', 'bkx-divi' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Calendar Style', 'bkx-divi' ); ?></th>
				<td>
					<select name="calendar_style">
						<option value="default" <?php selected( isset( $settings['calendar_style'] ) ? $settings['calendar_style'] : 'default', 'default' ); ?>>
							<?php esc_html_e( 'Default', 'bkx-divi' ); ?>
						</option>
						<option value="flat" <?php selected( isset( $settings['calendar_style'] ) ? $settings['calendar_style'] : '', 'flat' ); ?>>
							<?php esc_html_e( 'Flat', 'bkx-divi' ); ?>
						</option>
						<option value="material" <?php selected( isset( $settings['calendar_style'] ) ? $settings['calendar_style'] : '', 'material' ); ?>>
							<?php esc_html_e( 'Material', 'bkx-divi' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Default style for calendar modules.', 'bkx-divi' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" id="bkx-save-settings" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-divi' ); ?>
			</button>
			<span class="spinner"></span>
		</p>
	</form>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Cache', 'bkx-divi' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Clear Divi cache after making changes to module settings.', 'bkx-divi' ); ?>
	</p>
	<p>
		<button type="button" id="bkx-clear-divi-cache" class="button">
			<span class="dashicons dashicons-trash"></span>
			<?php esc_html_e( 'Clear Divi Cache', 'bkx-divi' ); ?>
		</button>
	</p>
</div>

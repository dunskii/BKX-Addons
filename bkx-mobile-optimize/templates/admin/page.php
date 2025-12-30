<?php
/**
 * Mobile Optimization Admin Page.
 *
 * @package BookingX\MobileOptimize
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\MobileOptimize\MobileOptimizeAddon::get_instance();
$settings = get_option( 'bkx_mobile_optimize_settings', array() );

// Handle form submission.
if ( isset( $_POST['bkx_save_mobile_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bkx_save_mobile_settings' ) ) {
	$settings['enabled']              = isset( $_POST['enabled'] );
	$settings['responsive_form']      = isset( $_POST['responsive_form'] );
	$settings['touch_friendly']       = isset( $_POST['touch_friendly'] );
	$settings['swipe_calendar']       = isset( $_POST['swipe_calendar'] );
	$settings['floating_cta']         = isset( $_POST['floating_cta'] );
	$settings['bottom_sheet_picker']  = isset( $_POST['bottom_sheet_picker'] );
	$settings['haptic_feedback']      = isset( $_POST['haptic_feedback'] );
	$settings['one_tap_booking']      = isset( $_POST['one_tap_booking'] );
	$settings['express_checkout']     = isset( $_POST['express_checkout'] );
	$settings['smart_autofill']       = isset( $_POST['smart_autofill'] );
	$settings['location_detection']   = isset( $_POST['location_detection'] );
	$settings['mobile_payments']      = isset( $_POST['mobile_payments'] );
	$settings['lazy_load_images']     = isset( $_POST['lazy_load_images'] );
	$settings['skeleton_loading']     = isset( $_POST['skeleton_loading'] );
	$settings['reduced_motion']       = isset( $_POST['reduced_motion'] );
	$settings['form_step_indicator']  = isset( $_POST['form_step_indicator'] );
	$settings['keyboard_optimization'] = isset( $_POST['keyboard_optimization'] );
	$settings['click_to_call']        = isset( $_POST['click_to_call'] );
	$settings['mobile_breakpoint']    = absint( $_POST['mobile_breakpoint'] ?? 768 );
	$settings['tablet_breakpoint']    = absint( $_POST['tablet_breakpoint'] ?? 1024 );

	update_option( 'bkx_mobile_optimize_settings', $settings );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'bkx-mobile-optimize' ) . '</p></div>';
}
?>

<div class="wrap bkx-mobile-optimize-admin">
	<h1>
		<span class="dashicons dashicons-smartphone"></span>
		<?php esc_html_e( 'Mobile Booking Optimization', 'bkx-mobile-optimize' ); ?>
	</h1>

	<form method="post">
		<?php wp_nonce_field( 'bkx_save_mobile_settings' ); ?>

		<!-- General Settings -->
		<div class="bkx-card">
			<h3><?php esc_html_e( 'General Settings', 'bkx-mobile-optimize' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Mobile Optimization', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ?? true ); ?>>
							<?php esc_html_e( 'Activate mobile-specific enhancements', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mobile_breakpoint"><?php esc_html_e( 'Mobile Breakpoint', 'bkx-mobile-optimize' ); ?></label>
					</th>
					<td>
						<input type="number" id="mobile_breakpoint" name="mobile_breakpoint" class="small-text"
							   value="<?php echo esc_attr( $settings['mobile_breakpoint'] ?? 768 ); ?>"> px
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="tablet_breakpoint"><?php esc_html_e( 'Tablet Breakpoint', 'bkx-mobile-optimize' ); ?></label>
					</th>
					<td>
						<input type="number" id="tablet_breakpoint" name="tablet_breakpoint" class="small-text"
							   value="<?php echo esc_attr( $settings['tablet_breakpoint'] ?? 1024 ); ?>"> px
					</td>
				</tr>
			</table>
		</div>

		<!-- Form Optimization -->
		<div class="bkx-card">
			<h3><?php esc_html_e( 'Form Optimization', 'bkx-mobile-optimize' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Responsive Form', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="responsive_form" value="1" <?php checked( $settings['responsive_form'] ?? true ); ?>>
							<?php esc_html_e( 'Enable mobile-optimized step-by-step form', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Step Indicator', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="form_step_indicator" value="1" <?php checked( $settings['form_step_indicator'] ?? true ); ?>>
							<?php esc_html_e( 'Show progress indicator for multi-step forms', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Smart Autofill', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="smart_autofill" value="1" <?php checked( $settings['smart_autofill'] ?? true ); ?>>
							<?php esc_html_e( 'Pre-fill form fields based on user history', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Keyboard Optimization', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="keyboard_optimization" value="1" <?php checked( $settings['keyboard_optimization'] ?? true ); ?>>
							<?php esc_html_e( 'Show appropriate keyboard for each input type', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- Touch & Gestures -->
		<div class="bkx-card">
			<h3><?php esc_html_e( 'Touch & Gestures', 'bkx-mobile-optimize' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Touch-Friendly UI', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="touch_friendly" value="1" <?php checked( $settings['touch_friendly'] ?? true ); ?>>
							<?php esc_html_e( 'Larger touch targets (44px minimum)', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Swipe Calendar', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="swipe_calendar" value="1" <?php checked( $settings['swipe_calendar'] ?? true ); ?>>
							<?php esc_html_e( 'Enable swipe navigation on calendar', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Haptic Feedback', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="haptic_feedback" value="1" <?php checked( $settings['haptic_feedback'] ?? true ); ?>>
							<?php esc_html_e( 'Enable vibration feedback on actions', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- UI Components -->
		<div class="bkx-card">
			<h3><?php esc_html_e( 'UI Components', 'bkx-mobile-optimize' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Floating Book Button', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="floating_cta" value="1" <?php checked( $settings['floating_cta'] ?? true ); ?>>
							<?php esc_html_e( 'Show floating "Book Now" button', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Bottom Sheet Picker', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bottom_sheet_picker" value="1" <?php checked( $settings['bottom_sheet_picker'] ?? true ); ?>>
							<?php esc_html_e( 'Use iOS-style bottom sheet for selections', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Skeleton Loading', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="skeleton_loading" value="1" <?php checked( $settings['skeleton_loading'] ?? true ); ?>>
							<?php esc_html_e( 'Show skeleton placeholders while loading', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- Booking Features -->
		<div class="bkx-card">
			<h3><?php esc_html_e( 'Booking Features', 'bkx-mobile-optimize' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'One-Tap Booking', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="one_tap_booking" value="1" <?php checked( $settings['one_tap_booking'] ?? false ); ?>>
							<?php esc_html_e( 'Enable one-tap quick booking for returning users', 'bkx-mobile-optimize' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Requires user to be logged in.', 'bkx-mobile-optimize' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Express Checkout', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="express_checkout" value="1" <?php checked( $settings['express_checkout'] ?? true ); ?>>
							<?php esc_html_e( 'Streamlined checkout flow for mobile', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Location Detection', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="location_detection" value="1" <?php checked( $settings['location_detection'] ?? false ); ?>>
							<?php esc_html_e( 'Auto-detect nearest location/service provider', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Click to Call', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="click_to_call" value="1" <?php checked( $settings['click_to_call'] ?? true ); ?>>
							<?php esc_html_e( 'Make phone numbers tappable', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- Performance -->
		<div class="bkx-card">
			<h3><?php esc_html_e( 'Performance', 'bkx-mobile-optimize' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Lazy Load Images', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="lazy_load_images" value="1" <?php checked( $settings['lazy_load_images'] ?? true ); ?>>
							<?php esc_html_e( 'Load images only when visible', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Reduced Motion', 'bkx-mobile-optimize' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="reduced_motion" value="1" <?php checked( $settings['reduced_motion'] ?? true ); ?>>
							<?php esc_html_e( 'Respect user\'s reduced motion preference', 'bkx-mobile-optimize' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" name="bkx_save_mobile_settings" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-mobile-optimize' ); ?>
			</button>
		</p>
	</form>
</div>

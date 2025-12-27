<?php
/**
 * Settings Page
 *
 * Handles admin settings for Multiple Services.
 *
 * @package BookingX\MultipleServices
 * @since   1.0.0
 */

namespace BookingX\MultipleServices\Admin;

use BookingX\MultipleServices\MultipleServicesAddon;

/**
 * Settings Page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var MultipleServicesAddon
	 */
	protected MultipleServicesAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param MultipleServicesAddon $addon Addon instance.
	 */
	public function __construct( MultipleServicesAddon $addon ) {
		$this->addon = $addon;

		add_action( 'bkx_settings_multiple_services_content', array( $this, 'render_settings' ) );
		add_action( 'bkx_save_settings_multiple_services', array( $this, 'save_settings' ) );
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings(): void {
		$settings = $this->addon->get_all_settings();
		?>
		<div class="bkx-settings-section">
			<h2><?php esc_html_e( 'Multiple Services Settings', 'bkx-multiple-services' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="enable_bundles"><?php esc_html_e( 'Enable Bundle Pricing', 'bkx-multiple-services' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="enable_bundles" name="bkx_multiple_services[enable_bundles]" value="1" <?php checked( $settings['enable_bundles'], true ); ?> />
						<p class="description"><?php esc_html_e( 'Apply discount when multiple services are selected.', 'bkx-multiple-services' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bundle_discount_type"><?php esc_html_e( 'Discount Type', 'bkx-multiple-services' ); ?></label>
					</th>
					<td>
						<select id="bundle_discount_type" name="bkx_multiple_services[bundle_discount_type]">
							<option value="percentage" <?php selected( $settings['bundle_discount_type'], 'percentage' ); ?>><?php esc_html_e( 'Percentage', 'bkx-multiple-services' ); ?></option>
							<option value="fixed" <?php selected( $settings['bundle_discount_type'], 'fixed' ); ?>><?php esc_html_e( 'Fixed Amount', 'bkx-multiple-services' ); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bundle_discount_value"><?php esc_html_e( 'Discount Value', 'bkx-multiple-services' ); ?></label>
					</th>
					<td>
						<input type="number" id="bundle_discount_value" name="bkx_multiple_services[bundle_discount_value]" value="<?php echo esc_attr( $settings['bundle_discount_value'] ); ?>" step="0.01" min="0" />
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="max_services_per_booking"><?php esc_html_e( 'Maximum Services Per Booking', 'bkx-multiple-services' ); ?></label>
					</th>
					<td>
						<input type="number" id="max_services_per_booking" name="bkx_multiple_services[max_services_per_booking]" value="<?php echo esc_attr( $settings['max_services_per_booking'] ); ?>" min="1" max="20" />
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="duration_calculation_mode"><?php esc_html_e( 'Duration Calculation Mode', 'bkx-multiple-services' ); ?></label>
					</th>
					<td>
						<select id="duration_calculation_mode" name="bkx_multiple_services[duration_calculation_mode]">
							<option value="sequential" <?php selected( $settings['duration_calculation_mode'], 'sequential' ); ?>><?php esc_html_e( 'Sequential (Add All)', 'bkx-multiple-services' ); ?></option>
							<option value="parallel" <?php selected( $settings['duration_calculation_mode'], 'parallel' ); ?>><?php esc_html_e( 'Parallel (Longest)', 'bkx-multiple-services' ); ?></option>
							<option value="longest" <?php selected( $settings['duration_calculation_mode'], 'longest' ); ?>><?php esc_html_e( 'Longest Service', 'bkx-multiple-services' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'How to calculate total appointment duration.', 'bkx-multiple-services' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="display_mode"><?php esc_html_e( 'Display Mode', 'bkx-multiple-services' ); ?></label>
					</th>
					<td>
						<select id="display_mode" name="bkx_multiple_services[display_mode]">
							<option value="checkbox" <?php selected( $settings['display_mode'], 'checkbox' ); ?>><?php esc_html_e( 'Checkboxes', 'bkx-multiple-services' ); ?></option>
							<option value="dropdown" <?php selected( $settings['display_mode'], 'dropdown' ); ?>><?php esc_html_e( 'Dropdown', 'bkx-multiple-services' ); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="require_same_resource"><?php esc_html_e( 'Require Same Resource', 'bkx-multiple-services' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="require_same_resource" name="bkx_multiple_services[require_same_resource]" value="1" <?php checked( $settings['require_same_resource'], true ); ?> />
						<p class="description"><?php esc_html_e( 'All services must be provided by the same staff member/resource.', 'bkx-multiple-services' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function save_settings(): void {
		// Nonce verification happens in BookingX core
		if ( ! isset( $_POST['bkx_multiple_services'] ) ) {
			return;
		}

		$settings = array_map( 'sanitize_text_field', wp_unslash( $_POST['bkx_multiple_services'] ) );

		$this->addon->save_all_settings( $settings );

		add_settings_error(
			'bkx_multiple_services',
			'settings_updated',
			__( 'Settings saved successfully.', 'bkx-multiple-services' ),
			'success'
		);
	}
}

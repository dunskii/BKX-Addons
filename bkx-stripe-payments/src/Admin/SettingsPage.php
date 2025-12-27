<?php
/**
 * Settings Page Class
 *
 * Handles admin settings page rendering and saving.
 *
 * @package BookingX\StripePayments\Admin
 * @since   1.0.0
 */

namespace BookingX\StripePayments\Admin;

use BookingX\StripePayments\StripePayments;

/**
 * Admin settings page.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Parent addon instance.
	 *
	 * @var StripePayments
	 */
	protected StripePayments $addon;

	/**
	 * Constructor.
	 *
	 * @param StripePayments $addon Parent addon instance.
	 */
	public function __construct( StripePayments $addon ) {
		$this->addon = $addon;

		// Hook into BookingX settings
		add_action( 'bkx_settings_tab_stripe_payments', array( $this, 'render_settings' ) );
		add_action( 'bkx_save_settings_stripe_payments', array( $this, 'save_settings' ) );
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings(): void {
		$settings = $this->addon->get_all_settings();
		$fields   = $this->addon->get_gateway()->get_settings_fields();

		?>
		<div class="bkx-stripe-settings-wrap">
			<h2><?php esc_html_e( 'Stripe Payment Gateway Settings', 'bkx-stripe-payments' ); ?></h2>

			<form method="post" action="">
				<?php wp_nonce_field( 'bkx_stripe_settings', 'bkx_stripe_nonce' ); ?>

				<table class="form-table">
					<?php
					foreach ( $fields as $field ) {
						$this->render_field( $field, $settings );
					}
					?>
				</table>

				<div class="bkx-stripe-webhook-info">
					<h3><?php esc_html_e( 'Webhook URL', 'bkx-stripe-payments' ); ?></h3>
					<p>
						<?php esc_html_e( 'Add this webhook URL to your Stripe account:', 'bkx-stripe-payments' ); ?>
					</p>
					<code><?php echo esc_url( rest_url( 'bookingx/v1/stripe/webhook' ) ); ?></code>
					<p class="description">
						<?php esc_html_e( 'Select the following events: payment_intent.succeeded, payment_intent.payment_failed, charge.refunded, charge.dispute.created', 'bkx-stripe-payments' ); ?>
					</p>
				</div>

				<?php submit_button( __( 'Save Settings', 'bkx-stripe-payments' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a single field.
	 *
	 * @since 1.0.0
	 * @param array $field    Field configuration.
	 * @param array $settings Current settings.
	 * @return void
	 */
	protected function render_field( array $field, array $settings ): void {
		if ( 'title' === $field['type'] ) {
			?>
			<tr>
				<th colspan="2">
					<h3><?php echo esc_html( $field['title'] ); ?></h3>
				</th>
			</tr>
			<?php
			return;
		}

		$id    = $field['id'] ?? '';
		$value = $settings[ $id ] ?? ( $field['default'] ?? '' );
		$type  = $field['type'] ?? 'text';

		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $field['title'] ?? '' ); ?>
				</label>
			</th>
			<td>
				<?php
				switch ( $type ) {
					case 'text':
					case 'password':
						?>
						<input type="<?php echo esc_attr( $type ); ?>"
							   id="<?php echo esc_attr( $id ); ?>"
							   name="bkx_stripe[<?php echo esc_attr( $id ); ?>]"
							   value="<?php echo esc_attr( $value ); ?>"
							   class="regular-text" />
						<?php
						break;

					case 'number':
						?>
						<input type="number"
							   id="<?php echo esc_attr( $id ); ?>"
							   name="bkx_stripe[<?php echo esc_attr( $id ); ?>]"
							   value="<?php echo esc_attr( $value ); ?>"
							   class="small-text" />
						<?php
						break;

					case 'checkbox':
						?>
						<label>
							<input type="checkbox"
								   id="<?php echo esc_attr( $id ); ?>"
								   name="bkx_stripe[<?php echo esc_attr( $id ); ?>]"
								   value="1"
								   <?php checked( $value, true ); ?> />
							<?php echo esc_html( $field['desc'] ?? '' ); ?>
						</label>
						<?php
						break;

					case 'select':
						?>
						<select id="<?php echo esc_attr( $id ); ?>"
								name="bkx_stripe[<?php echo esc_attr( $id ); ?>]">
							<?php
							foreach ( $field['options'] ?? array() as $option_value => $option_label ) {
								printf(
									'<option value="%s" %s>%s</option>',
									esc_attr( $option_value ),
									selected( $value, $option_value, false ),
									esc_html( $option_label )
								);
							}
							?>
						</select>
						<?php
						break;
				}

				if ( ! empty( $field['desc'] ) && 'checkbox' !== $type ) {
					echo '<p class="description">' . esc_html( $field['desc'] ) . '</p>';
				}
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function save_settings(): void {
		// Verify nonce - SECURITY CHECK
		if ( ! isset( $_POST['bkx_stripe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_stripe_nonce'] ) ), 'bkx_stripe_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bkx-stripe-payments' ) );
		}

		// Check permissions - SECURITY CHECK
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to save settings.', 'bkx-stripe-payments' ) );
		}

		// Get and sanitize posted data - SECURITY CRITICAL
		$posted_data = array();
		if ( isset( $_POST['bkx_stripe'] ) && is_array( $_POST['bkx_stripe'] ) ) {
			$raw_data = wp_unslash( $_POST['bkx_stripe'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// Define field types for proper sanitization
			$boolean_fields = array(
				'enable_3d_secure',
				'save_payment_methods',
				'enable_apple_pay',
				'enable_google_pay',
				'enable_link',
				'auto_refund_on_cancel',
				'debug_log',
			);

			$select_fields = array(
				'stripe_mode'    => array( 'test', 'live' ),
				'capture_method' => array( 'automatic', 'manual' ),
			);

			foreach ( $raw_data as $key => $value ) {
				$key = sanitize_key( $key );

				// Boolean fields (checkboxes)
				if ( in_array( $key, $boolean_fields, true ) ) {
					$posted_data[ $key ] = (bool) $value;
				}
				// Select fields with limited options
				elseif ( isset( $select_fields[ $key ] ) ) {
					$posted_data[ $key ] = in_array( $value, $select_fields[ $key ], true ) ? $value : $select_fields[ $key ][0];
				}
				// Numeric fields
				elseif ( 'radar_risk_threshold' === $key ) {
					$posted_data[ $key ] = min( 100, max( 0, absint( $value ) ) );
				}
				// Text fields (API keys, secrets, etc.)
				else {
					$posted_data[ $key ] = sanitize_text_field( $value );
				}
			}
		}

		// Merge with existing settings to preserve unposted fields
		$current_settings = $this->addon->get_all_settings();
		$new_settings     = array_merge( $current_settings, $posted_data );

		// Save settings (will be validated and sanitized by trait)
		$saved = $this->addon->save_all_settings( $new_settings );

		if ( $saved ) {
			add_settings_error(
				'bkx_stripe_settings',
				'settings_saved',
				__( 'Stripe settings saved successfully.', 'bkx-stripe-payments' ),
				'success'
			);
		} else {
			add_settings_error(
				'bkx_stripe_settings',
				'settings_error',
				__( 'Failed to save Stripe settings.', 'bkx-stripe-payments' ),
				'error'
			);
		}
	}
}

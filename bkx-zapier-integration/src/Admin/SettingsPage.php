<?php
/**
 * Zapier Settings Page
 *
 * @package BookingX\ZapierIntegration
 * @since   1.0.0
 */

namespace BookingX\ZapierIntegration\Admin;

use BookingX\ZapierIntegration\ZapierIntegrationAddon;

/**
 * Class SettingsPage
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var ZapierIntegrationAddon
	 */
	private ZapierIntegrationAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ZapierIntegrationAddon $addon Addon instance.
	 */
	public function __construct( ZapierIntegrationAddon $addon ) {
		$this->addon = $addon;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Add menu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Zapier Integration', 'bkx-zapier-integration' ),
			__( 'Zapier', 'bkx-zapier-integration' ),
			'manage_options',
			'bkx-zapier',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'bkx_booking_page_bkx-zapier' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-zapier-admin',
			BKX_ZAPIER_URL . 'assets/css/admin.css',
			array(),
			BKX_ZAPIER_VERSION
		);

		wp_enqueue_script(
			'bkx-zapier-admin',
			BKX_ZAPIER_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_ZAPIER_VERSION,
			true
		);
	}

	/**
	 * Handle admin actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! isset( $_POST['bkx_zapier_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_zapier_nonce'] ) ), 'bkx_zapier_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Regenerate API key.
		if ( isset( $_POST['bkx_zapier_regenerate_key'] ) ) {
			$new_key = wp_generate_password( 32, false );
			update_option( 'bkx_zapier_api_key', $new_key );
			add_settings_error( 'bkx_zapier', 'key_regenerated', __( 'API key regenerated successfully.', 'bkx-zapier-integration' ), 'success' );
		}

		// Clear subscriptions.
		if ( isset( $_POST['bkx_zapier_clear_subscriptions'] ) ) {
			$this->addon->get_webhook_service()->clear_all();
			add_settings_error( 'bkx_zapier', 'subscriptions_cleared', __( 'All webhook subscriptions cleared.', 'bkx-zapier-integration' ), 'success' );
		}

		// Save settings.
		if ( isset( $_POST['bkx_zapier_save_settings'] ) ) {
			$settings = array(
				'enabled'      => isset( $_POST['bkx_zapier_enabled'] ),
				'log_webhooks' => isset( $_POST['bkx_zapier_log_webhooks'] ),
				'retry_failed' => isset( $_POST['bkx_zapier_retry_failed'] ),
				'max_retries'  => absint( $_POST['bkx_zapier_max_retries'] ?? 3 ),
			);

			foreach ( $settings as $key => $value ) {
				$this->addon->update_setting( $key, $value );
			}

			add_settings_error( 'bkx_zapier', 'settings_saved', __( 'Settings saved successfully.', 'bkx-zapier-integration' ), 'success' );
		}

		// License activation.
		if ( isset( $_POST['bkx_zapier_license_activate'] ) ) {
			$license_key = sanitize_text_field( wp_unslash( $_POST['bkx_zapier_license_key'] ?? '' ) );
			$result      = $this->addon->activate_license( $license_key );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'bkx_zapier', 'license_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'bkx_zapier', 'license_activated', __( 'License activated successfully.', 'bkx-zapier-integration' ), 'success' );
			}
		}

		// License deactivation.
		if ( isset( $_POST['bkx_zapier_license_deactivate'] ) ) {
			$result = $this->addon->deactivate_license();

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'bkx_zapier', 'license_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'bkx_zapier', 'license_deactivated', __( 'License deactivated.', 'bkx-zapier-integration' ), 'success' );
			}
		}
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'setup';
		$tabs        = array(
			'setup'         => __( 'Setup', 'bkx-zapier-integration' ),
			'subscriptions' => __( 'Subscriptions', 'bkx-zapier-integration' ),
			'settings'      => __( 'Settings', 'bkx-zapier-integration' ),
			'license'       => __( 'License', 'bkx-zapier-integration' ),
		);
		?>
		<div class="wrap bkx-zapier-settings">
			<h1>
				<img src="<?php echo esc_url( BKX_ZAPIER_URL . 'assets/images/zapier-logo.svg' ); ?>" alt="Zapier" class="bkx-zapier-logo" style="height: 24px; vertical-align: middle; margin-right: 10px;">
				<?php esc_html_e( 'Zapier Integration', 'bkx-zapier-integration' ); ?>
			</h1>

			<?php settings_errors( 'bkx_zapier' ); ?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id ) ); ?>"
					   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="bkx-tab-content">
				<?php
				switch ( $current_tab ) {
					case 'subscriptions':
						$this->render_subscriptions_tab();
						break;
					case 'settings':
						$this->render_settings_tab();
						break;
					case 'license':
						$this->render_license_tab();
						break;
					default:
						$this->render_setup_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render setup tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_setup_tab(): void {
		$api_key  = get_option( 'bkx_zapier_api_key', '' );
		$rest_url = rest_url( 'bookingx/v1/zapier/' );
		?>
		<div class="bkx-zapier-setup">
			<h2><?php esc_html_e( 'Getting Started with Zapier', 'bkx-zapier-integration' ); ?></h2>

			<div class="bkx-setup-steps">
				<div class="bkx-step">
					<div class="bkx-step-number">1</div>
					<div class="bkx-step-content">
						<h3><?php esc_html_e( 'Create a Zap', 'bkx-zapier-integration' ); ?></h3>
						<p><?php esc_html_e( 'Go to Zapier and create a new Zap. Choose "Webhooks by Zapier" as your trigger app.', 'bkx-zapier-integration' ); ?></p>
					</div>
				</div>

				<div class="bkx-step">
					<div class="bkx-step-number">2</div>
					<div class="bkx-step-content">
						<h3><?php esc_html_e( 'Configure the Webhook', 'bkx-zapier-integration' ); ?></h3>
						<p><?php esc_html_e( 'Use the API credentials below to authenticate your webhook requests.', 'bkx-zapier-integration' ); ?></p>
					</div>
				</div>

				<div class="bkx-step">
					<div class="bkx-step-number">3</div>
					<div class="bkx-step-content">
						<h3><?php esc_html_e( 'Add Actions', 'bkx-zapier-integration' ); ?></h3>
						<p><?php esc_html_e( 'Connect to 3,000+ apps and automate your booking workflows.', 'bkx-zapier-integration' ); ?></p>
					</div>
				</div>
			</div>

			<div class="bkx-api-credentials">
				<h3><?php esc_html_e( 'API Credentials', 'bkx-zapier-integration' ); ?></h3>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'API Endpoint', 'bkx-zapier-integration' ); ?></th>
						<td>
							<code class="bkx-copy-field"><?php echo esc_url( $rest_url ); ?></code>
							<button type="button" class="button bkx-copy-btn" data-target="<?php echo esc_attr( $rest_url ); ?>">
								<?php esc_html_e( 'Copy', 'bkx-zapier-integration' ); ?>
							</button>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'API Key', 'bkx-zapier-integration' ); ?></th>
						<td>
							<code class="bkx-copy-field bkx-api-key"><?php echo esc_html( $api_key ); ?></code>
							<button type="button" class="button bkx-copy-btn" data-target="<?php echo esc_attr( $api_key ); ?>">
								<?php esc_html_e( 'Copy', 'bkx-zapier-integration' ); ?>
							</button>
							<form method="post" style="display: inline;">
								<?php wp_nonce_field( 'bkx_zapier_settings', 'bkx_zapier_nonce' ); ?>
								<button type="submit" name="bkx_zapier_regenerate_key" class="button" onclick="return confirm('<?php echo esc_js( __( 'This will invalidate all existing Zaps. Are you sure?', 'bkx-zapier-integration' ) ); ?>');">
									<?php esc_html_e( 'Regenerate', 'bkx-zapier-integration' ); ?>
								</button>
							</form>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Authentication', 'bkx-zapier-integration' ); ?></th>
						<td>
							<p><?php esc_html_e( 'Include the API key in the X-API-Key header or as an api_key query parameter.', 'bkx-zapier-integration' ); ?></p>
							<pre>X-API-Key: <?php echo esc_html( $api_key ); ?></pre>
						</td>
					</tr>
				</table>
			</div>

			<div class="bkx-available-triggers">
				<h3><?php esc_html_e( 'Available Triggers', 'bkx-zapier-integration' ); ?></h3>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Trigger', 'bkx-zapier-integration' ); ?></th>
							<th><?php esc_html_e( 'Description', 'bkx-zapier-integration' ); ?></th>
							<th><?php esc_html_e( 'Subscribe Endpoint', 'bkx-zapier-integration' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $this->addon->get_trigger_service()->get_available_triggers() as $trigger ) : ?>
						<tr>
							<td><code><?php echo esc_html( $trigger['key'] ); ?></code></td>
							<td><?php echo esc_html( $trigger['description'] ); ?></td>
							<td><code>POST /subscribe</code></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="bkx-available-actions">
				<h3><?php esc_html_e( 'Available Actions', 'bkx-zapier-integration' ); ?></h3>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Action', 'bkx-zapier-integration' ); ?></th>
							<th><?php esc_html_e( 'Method', 'bkx-zapier-integration' ); ?></th>
							<th><?php esc_html_e( 'Endpoint', 'bkx-zapier-integration' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Create Booking', 'bkx-zapier-integration' ); ?></td>
							<td><code>POST</code></td>
							<td><code>/bookings</code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Get Booking', 'bkx-zapier-integration' ); ?></td>
							<td><code>GET</code></td>
							<td><code>/bookings/{id}</code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Update Booking', 'bkx-zapier-integration' ); ?></td>
							<td><code>PUT</code></td>
							<td><code>/bookings/{id}</code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Cancel Booking', 'bkx-zapier-integration' ); ?></td>
							<td><code>POST</code></td>
							<td><code>/bookings/{id}/cancel</code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'List Services', 'bkx-zapier-integration' ); ?></td>
							<td><code>GET</code></td>
							<td><code>/services</code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'List Resources', 'bkx-zapier-integration' ); ?></td>
							<td><code>GET</code></td>
							<td><code>/resources</code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'List Customers', 'bkx-zapier-integration' ); ?></td>
							<td><code>GET</code></td>
							<td><code>/customers</code></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render subscriptions tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_subscriptions_tab(): void {
		$subscriptions = $this->addon->get_webhook_service()->get_all_subscriptions();
		?>
		<div class="bkx-subscriptions">
			<h2><?php esc_html_e( 'Active Webhook Subscriptions', 'bkx-zapier-integration' ); ?></h2>

			<?php if ( empty( $subscriptions ) ) : ?>
				<p><?php esc_html_e( 'No active webhook subscriptions. Subscriptions are created automatically when you set up Zaps.', 'bkx-zapier-integration' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'bkx-zapier-integration' ); ?></th>
							<th><?php esc_html_e( 'Trigger', 'bkx-zapier-integration' ); ?></th>
							<th><?php esc_html_e( 'Target URL', 'bkx-zapier-integration' ); ?></th>
							<th><?php esc_html_e( 'Created', 'bkx-zapier-integration' ); ?></th>
							<th><?php esc_html_e( 'Last Sent', 'bkx-zapier-integration' ); ?></th>
							<th><?php esc_html_e( 'Send Count', 'bkx-zapier-integration' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $subscriptions as $id => $sub ) : ?>
						<tr>
							<td><code><?php echo esc_html( substr( $id, 0, 8 ) ); ?>...</code></td>
							<td><code><?php echo esc_html( $sub['trigger'] ); ?></code></td>
							<td><?php echo esc_html( $sub['target_url'] ); ?></td>
							<td><?php echo esc_html( $sub['created_at'] ); ?></td>
							<td><?php echo esc_html( $sub['last_sent'] ?? '-' ); ?></td>
							<td><?php echo esc_html( $sub['send_count'] ?? 0 ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<form method="post" style="margin-top: 20px;">
					<?php wp_nonce_field( 'bkx_zapier_settings', 'bkx_zapier_nonce' ); ?>
					<button type="submit" name="bkx_zapier_clear_subscriptions" class="button" onclick="return confirm('<?php echo esc_js( __( 'This will disconnect all Zaps. Are you sure?', 'bkx-zapier-integration' ) ); ?>');">
						<?php esc_html_e( 'Clear All Subscriptions', 'bkx-zapier-integration' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render settings tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_settings_tab(): void {
		?>
		<form method="post">
			<?php wp_nonce_field( 'bkx_zapier_settings', 'bkx_zapier_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Integration', 'bkx-zapier-integration' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_zapier_enabled" value="1" <?php checked( $this->addon->get_setting( 'enabled', true ) ); ?>>
							<?php esc_html_e( 'Enable Zapier integration', 'bkx-zapier-integration' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Log Webhooks', 'bkx-zapier-integration' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_zapier_log_webhooks" value="1" <?php checked( $this->addon->get_setting( 'log_webhooks', true ) ); ?>>
							<?php esc_html_e( 'Log all webhook deliveries', 'bkx-zapier-integration' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Retry Failed', 'bkx-zapier-integration' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_zapier_retry_failed" value="1" <?php checked( $this->addon->get_setting( 'retry_failed', true ) ); ?>>
							<?php esc_html_e( 'Automatically retry failed webhook deliveries', 'bkx-zapier-integration' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Max Retries', 'bkx-zapier-integration' ); ?></th>
					<td>
						<input type="number" name="bkx_zapier_max_retries" value="<?php echo esc_attr( $this->addon->get_setting( 'max_retries', 3 ) ); ?>" min="1" max="10" class="small-text">
						<p class="description"><?php esc_html_e( 'Maximum number of retry attempts for failed webhooks.', 'bkx-zapier-integration' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="bkx_zapier_save_settings" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'bkx-zapier-integration' ); ?>
				</button>
			</p>
		</form>
		<?php
	}

	/**
	 * Render license tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_license_tab(): void {
		$license_key    = get_option( 'bkx_zapier_license_key', '' );
		$license_status = get_option( 'bkx_zapier_license_status', '' );
		?>
		<div class="bkx-license-settings">
			<h2><?php esc_html_e( 'License Activation', 'bkx-zapier-integration' ); ?></h2>

			<p><?php esc_html_e( 'Enter your license key to receive automatic updates and support.', 'bkx-zapier-integration' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'bkx_zapier_settings', 'bkx_zapier_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="bkx_zapier_license_key"><?php esc_html_e( 'License Key', 'bkx-zapier-integration' ); ?></label>
						</th>
						<td>
							<input type="password"
								   id="bkx_zapier_license_key"
								   name="bkx_zapier_license_key"
								   value="<?php echo esc_attr( $license_key ); ?>"
								   class="regular-text"
								   autocomplete="off">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'bkx-zapier-integration' ); ?></th>
						<td>
							<?php if ( 'valid' === $license_status ) : ?>
								<span class="bkx-license-status bkx-license-active">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Active', 'bkx-zapier-integration' ); ?>
								</span>
							<?php elseif ( 'expired' === $license_status ) : ?>
								<span class="bkx-license-status bkx-license-expired">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'Expired', 'bkx-zapier-integration' ); ?>
								</span>
							<?php else : ?>
								<span class="bkx-license-status bkx-license-inactive">
									<span class="dashicons dashicons-marker"></span>
									<?php esc_html_e( 'Not Activated', 'bkx-zapier-integration' ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<p class="submit">
					<?php if ( 'valid' === $license_status ) : ?>
						<button type="submit" name="bkx_zapier_license_deactivate" class="button">
							<?php esc_html_e( 'Deactivate License', 'bkx-zapier-integration' ); ?>
						</button>
					<?php else : ?>
						<button type="submit" name="bkx_zapier_license_activate" class="button button-primary">
							<?php esc_html_e( 'Activate License', 'bkx-zapier-integration' ); ?>
						</button>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}
}

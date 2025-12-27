<?php
/**
 * ActiveCampaign Settings Page
 *
 * @package BookingX\ActiveCampaign
 * @since   1.0.0
 */

namespace BookingX\ActiveCampaign\Admin;

use BookingX\ActiveCampaign\ActiveCampaignAddon;

/**
 * Class SettingsPage
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var ActiveCampaignAddon
	 */
	private ActiveCampaignAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ActiveCampaignAddon $addon Addon instance.
	 */
	public function __construct( ActiveCampaignAddon $addon ) {
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
			__( 'ActiveCampaign', 'bkx-activecampaign' ),
			__( 'ActiveCampaign', 'bkx-activecampaign' ),
			'manage_options',
			'bkx-activecampaign',
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
		if ( 'bkx_booking_page_bkx-activecampaign' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-activecampaign-admin',
			BKX_ACTIVECAMPAIGN_URL . 'assets/css/admin.css',
			array(),
			BKX_ACTIVECAMPAIGN_VERSION
		);
	}

	/**
	 * Handle admin actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! isset( $_POST['bkx_ac_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_ac_nonce'] ) ), 'bkx_activecampaign_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Connect.
		if ( isset( $_POST['bkx_ac_connect'] ) ) {
			$result = $this->addon->connect(
				array(
					'api_url' => sanitize_url( wp_unslash( $_POST['bkx_ac_api_url'] ?? '' ) ),
					'api_key' => sanitize_text_field( wp_unslash( $_POST['bkx_ac_api_key'] ?? '' ) ),
				)
			);

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'bkx_ac', 'connection_failed', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'bkx_ac', 'connected', __( 'Connected to ActiveCampaign successfully!', 'bkx-activecampaign' ), 'success' );
			}
		}

		// Disconnect.
		if ( isset( $_POST['bkx_ac_disconnect'] ) ) {
			$this->addon->disconnect();
			add_settings_error( 'bkx_ac', 'disconnected', __( 'Disconnected from ActiveCampaign.', 'bkx-activecampaign' ), 'success' );
		}

		// Save settings.
		if ( isset( $_POST['bkx_ac_save_settings'] ) ) {
			$settings = array(
				'default_list_id'         => sanitize_text_field( wp_unslash( $_POST['bkx_ac_list_id'] ?? '' ) ),
				'tag_on_booking'          => isset( $_POST['bkx_ac_tag_on_booking'] ),
				'booking_tag'             => sanitize_text_field( wp_unslash( $_POST['bkx_ac_booking_tag'] ?? '' ) ),
				'completed_tag'           => sanitize_text_field( wp_unslash( $_POST['bkx_ac_completed_tag'] ?? '' ) ),
				'cancelled_tag'           => sanitize_text_field( wp_unslash( $_POST['bkx_ac_cancelled_tag'] ?? '' ) ),
				'create_deals'            => isset( $_POST['bkx_ac_create_deals'] ),
				'deal_pipeline_id'        => sanitize_text_field( wp_unslash( $_POST['bkx_ac_pipeline_id'] ?? '' ) ),
				'deal_stage_id'           => sanitize_text_field( wp_unslash( $_POST['bkx_ac_stage_id'] ?? '' ) ),
				'booking_automation_id'   => sanitize_text_field( wp_unslash( $_POST['bkx_ac_booking_automation'] ?? '' ) ),
				'completed_automation_id' => sanitize_text_field( wp_unslash( $_POST['bkx_ac_completed_automation'] ?? '' ) ),
				'enable_sync'             => isset( $_POST['bkx_ac_enable_sync'] ),
				'sync_users'              => isset( $_POST['bkx_ac_sync_users'] ),
			);

			foreach ( $settings as $key => $value ) {
				$this->addon->update_setting( $key, $value );
			}

			add_settings_error( 'bkx_ac', 'settings_saved', __( 'Settings saved.', 'bkx-activecampaign' ), 'success' );
		}

		// License activation.
		if ( isset( $_POST['bkx_ac_license_activate'] ) ) {
			$license_key = sanitize_text_field( wp_unslash( $_POST['bkx_ac_license_key'] ?? '' ) );
			$result      = $this->addon->activate_license( $license_key );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'bkx_ac', 'license_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'bkx_ac', 'license_activated', __( 'License activated.', 'bkx-activecampaign' ), 'success' );
			}
		}

		// License deactivation.
		if ( isset( $_POST['bkx_ac_license_deactivate'] ) ) {
			$this->addon->deactivate_license();
			add_settings_error( 'bkx_ac', 'license_deactivated', __( 'License deactivated.', 'bkx-activecampaign' ), 'success' );
		}
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'connection';
		$tabs        = array(
			'connection' => __( 'Connection', 'bkx-activecampaign' ),
			'contacts'   => __( 'Contacts', 'bkx-activecampaign' ),
			'deals'      => __( 'Deals', 'bkx-activecampaign' ),
			'automation' => __( 'Automation', 'bkx-activecampaign' ),
			'license'    => __( 'License', 'bkx-activecampaign' ),
		);
		?>
		<div class="wrap bkx-activecampaign-settings">
			<h1><?php esc_html_e( 'ActiveCampaign Integration', 'bkx-activecampaign' ); ?></h1>

			<?php settings_errors( 'bkx_ac' ); ?>

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
					case 'contacts':
						$this->render_contacts_tab();
						break;
					case 'deals':
						$this->render_deals_tab();
						break;
					case 'automation':
						$this->render_automation_tab();
						break;
					case 'license':
						$this->render_license_tab();
						break;
					default:
						$this->render_connection_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render connection tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_connection_tab(): void {
		$is_connected = $this->addon->is_connected();
		$account_info = $this->addon->get_setting( 'account_info', array() );
		?>
		<h2><?php esc_html_e( 'ActiveCampaign Connection', 'bkx-activecampaign' ); ?></h2>

		<?php if ( $is_connected && ! empty( $account_info ) ) : ?>
			<div class="bkx-connection-status bkx-connected">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Connected', 'bkx-activecampaign' ); ?>
			</div>

			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Account', 'bkx-activecampaign' ); ?></th>
					<td><?php echo esc_html( $account_info['name'] ?? '' ); ?></td>
				</tr>
			</table>

			<form method="post">
				<?php wp_nonce_field( 'bkx_activecampaign_settings', 'bkx_ac_nonce' ); ?>
				<button type="submit" name="bkx_ac_disconnect" class="button">
					<?php esc_html_e( 'Disconnect', 'bkx-activecampaign' ); ?>
				</button>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'Connect your ActiveCampaign account to sync contacts, create deals, and trigger automations.', 'bkx-activecampaign' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'bkx_activecampaign_settings', 'bkx_ac_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="bkx_ac_api_url"><?php esc_html_e( 'API URL', 'bkx-activecampaign' ); ?></label>
						</th>
						<td>
							<input type="url"
								   id="bkx_ac_api_url"
								   name="bkx_ac_api_url"
								   value=""
								   class="regular-text"
								   placeholder="https://youraccountname.api-us1.com"
								   required>
							<p class="description">
								<?php esc_html_e( 'Find this in ActiveCampaign under Settings > Developer.', 'bkx-activecampaign' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bkx_ac_api_key"><?php esc_html_e( 'API Key', 'bkx-activecampaign' ); ?></label>
						</th>
						<td>
							<input type="password"
								   id="bkx_ac_api_key"
								   name="bkx_ac_api_key"
								   value=""
								   class="regular-text"
								   required>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="bkx_ac_connect" class="button button-primary">
						<?php esc_html_e( 'Connect', 'bkx-activecampaign' ); ?>
					</button>
				</p>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render contacts tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_contacts_tab(): void {
		if ( ! $this->addon->is_connected() ) {
			echo '<p>' . esc_html__( 'Please connect to ActiveCampaign first.', 'bkx-activecampaign' ) . '</p>';
			return;
		}

		$lists = $this->addon->get_api()->get_lists();
		?>
		<h2><?php esc_html_e( 'Contact Settings', 'bkx-activecampaign' ); ?></h2>

		<form method="post">
			<?php wp_nonce_field( 'bkx_activecampaign_settings', 'bkx_ac_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bkx_ac_list_id"><?php esc_html_e( 'Default List', 'bkx-activecampaign' ); ?></label>
					</th>
					<td>
						<select id="bkx_ac_list_id" name="bkx_ac_list_id">
							<option value=""><?php esc_html_e( '— None —', 'bkx-activecampaign' ); ?></option>
							<?php foreach ( $lists as $list ) : ?>
								<option value="<?php echo esc_attr( $list['id'] ); ?>" <?php selected( $this->addon->get_setting( 'default_list_id' ), $list['id'] ); ?>>
									<?php echo esc_html( $list['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Automatically add new contacts to this list.', 'bkx-activecampaign' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Tagging', 'bkx-activecampaign' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_ac_tag_on_booking" value="1" <?php checked( $this->addon->get_setting( 'tag_on_booking', true ) ); ?>>
							<?php esc_html_e( 'Add tag when booking is created', 'bkx-activecampaign' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_ac_booking_tag"><?php esc_html_e( 'Booking Tag', 'bkx-activecampaign' ); ?></label>
					</th>
					<td>
						<input type="text"
							   id="bkx_ac_booking_tag"
							   name="bkx_ac_booking_tag"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'booking_tag', 'BookingX Customer' ) ); ?>"
							   class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_ac_completed_tag"><?php esc_html_e( 'Completed Tag', 'bkx-activecampaign' ); ?></label>
					</th>
					<td>
						<input type="text"
							   id="bkx_ac_completed_tag"
							   name="bkx_ac_completed_tag"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'completed_tag', '' ) ); ?>"
							   class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_ac_cancelled_tag"><?php esc_html_e( 'Cancelled Tag', 'bkx-activecampaign' ); ?></label>
					</th>
					<td>
						<input type="text"
							   id="bkx_ac_cancelled_tag"
							   name="bkx_ac_cancelled_tag"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'cancelled_tag', '' ) ); ?>"
							   class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sync Options', 'bkx-activecampaign' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_ac_enable_sync" value="1" <?php checked( $this->addon->get_setting( 'enable_sync', false ) ); ?>>
							<?php esc_html_e( 'Enable daily contact sync', 'bkx-activecampaign' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="bkx_ac_sync_users" value="1" <?php checked( $this->addon->get_setting( 'sync_users', false ) ); ?>>
							<?php esc_html_e( 'Sync new WordPress users', 'bkx-activecampaign' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="bkx_ac_save_settings" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'bkx-activecampaign' ); ?>
				</button>
			</p>
		</form>
		<?php
	}

	/**
	 * Render deals tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_deals_tab(): void {
		if ( ! $this->addon->is_connected() ) {
			echo '<p>' . esc_html__( 'Please connect to ActiveCampaign first.', 'bkx-activecampaign' ) . '</p>';
			return;
		}

		$pipelines = $this->addon->get_api()->get_pipelines();
		?>
		<h2><?php esc_html_e( 'Deal Settings', 'bkx-activecampaign' ); ?></h2>

		<form method="post">
			<?php wp_nonce_field( 'bkx_activecampaign_settings', 'bkx_ac_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Create Deals', 'bkx-activecampaign' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_ac_create_deals" value="1" <?php checked( $this->addon->get_setting( 'create_deals', false ) ); ?>>
							<?php esc_html_e( 'Automatically create deals for bookings', 'bkx-activecampaign' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_ac_pipeline_id"><?php esc_html_e( 'Pipeline', 'bkx-activecampaign' ); ?></label>
					</th>
					<td>
						<select id="bkx_ac_pipeline_id" name="bkx_ac_pipeline_id">
							<option value=""><?php esc_html_e( '— Select Pipeline —', 'bkx-activecampaign' ); ?></option>
							<?php foreach ( $pipelines as $pipeline ) : ?>
								<option value="<?php echo esc_attr( $pipeline['id'] ); ?>" <?php selected( $this->addon->get_setting( 'deal_pipeline_id' ), $pipeline['id'] ); ?>>
									<?php echo esc_html( $pipeline['title'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_ac_stage_id"><?php esc_html_e( 'Initial Stage', 'bkx-activecampaign' ); ?></label>
					</th>
					<td>
						<input type="text"
							   id="bkx_ac_stage_id"
							   name="bkx_ac_stage_id"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'deal_stage_id', '' ) ); ?>"
							   class="regular-text"
							   placeholder="<?php esc_attr_e( 'Stage ID', 'bkx-activecampaign' ); ?>">
						<p class="description"><?php esc_html_e( 'Enter the stage ID for new deals.', 'bkx-activecampaign' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="bkx_ac_save_settings" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'bkx-activecampaign' ); ?>
				</button>
			</p>
		</form>
		<?php
	}

	/**
	 * Render automation tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_automation_tab(): void {
		if ( ! $this->addon->is_connected() ) {
			echo '<p>' . esc_html__( 'Please connect to ActiveCampaign first.', 'bkx-activecampaign' ) . '</p>';
			return;
		}

		$automations = $this->addon->get_api()->get_automations();
		?>
		<h2><?php esc_html_e( 'Automation Settings', 'bkx-activecampaign' ); ?></h2>

		<form method="post">
			<?php wp_nonce_field( 'bkx_activecampaign_settings', 'bkx_ac_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bkx_ac_booking_automation"><?php esc_html_e( 'On Booking Created', 'bkx-activecampaign' ); ?></label>
					</th>
					<td>
						<select id="bkx_ac_booking_automation" name="bkx_ac_booking_automation">
							<option value=""><?php esc_html_e( '— None —', 'bkx-activecampaign' ); ?></option>
							<?php foreach ( $automations as $automation ) : ?>
								<option value="<?php echo esc_attr( $automation['id'] ); ?>" <?php selected( $this->addon->get_setting( 'booking_automation_id' ), $automation['id'] ); ?>>
									<?php echo esc_html( $automation['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Trigger this automation when a booking is created.', 'bkx-activecampaign' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_ac_completed_automation"><?php esc_html_e( 'On Booking Completed', 'bkx-activecampaign' ); ?></label>
					</th>
					<td>
						<select id="bkx_ac_completed_automation" name="bkx_ac_completed_automation">
							<option value=""><?php esc_html_e( '— None —', 'bkx-activecampaign' ); ?></option>
							<?php foreach ( $automations as $automation ) : ?>
								<option value="<?php echo esc_attr( $automation['id'] ); ?>" <?php selected( $this->addon->get_setting( 'completed_automation_id' ), $automation['id'] ); ?>>
									<?php echo esc_html( $automation['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Trigger this automation when a booking is completed.', 'bkx-activecampaign' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="bkx_ac_save_settings" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'bkx-activecampaign' ); ?>
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
		$license_key    = get_option( 'bkx_activecampaign_license_key', '' );
		$license_status = get_option( 'bkx_activecampaign_license_status', '' );
		?>
		<div class="bkx-license-settings">
			<h2><?php esc_html_e( 'License Activation', 'bkx-activecampaign' ); ?></h2>

			<p><?php esc_html_e( 'Enter your license key to receive automatic updates and support.', 'bkx-activecampaign' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'bkx_activecampaign_settings', 'bkx_ac_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="bkx_ac_license_key"><?php esc_html_e( 'License Key', 'bkx-activecampaign' ); ?></label>
						</th>
						<td>
							<input type="password"
								   id="bkx_ac_license_key"
								   name="bkx_ac_license_key"
								   value="<?php echo esc_attr( $license_key ); ?>"
								   class="regular-text"
								   autocomplete="off">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'bkx-activecampaign' ); ?></th>
						<td>
							<?php if ( 'valid' === $license_status ) : ?>
								<span class="bkx-license-status bkx-license-active">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Active', 'bkx-activecampaign' ); ?>
								</span>
							<?php elseif ( 'expired' === $license_status ) : ?>
								<span class="bkx-license-status bkx-license-expired">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'Expired', 'bkx-activecampaign' ); ?>
								</span>
							<?php else : ?>
								<span class="bkx-license-status bkx-license-inactive">
									<span class="dashicons dashicons-marker"></span>
									<?php esc_html_e( 'Not Activated', 'bkx-activecampaign' ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<p class="submit">
					<?php if ( 'valid' === $license_status ) : ?>
						<button type="submit" name="bkx_ac_license_deactivate" class="button">
							<?php esc_html_e( 'Deactivate License', 'bkx-activecampaign' ); ?>
						</button>
					<?php else : ?>
						<button type="submit" name="bkx_ac_license_activate" class="button button-primary">
							<?php esc_html_e( 'Activate License', 'bkx-activecampaign' ); ?>
						</button>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}
}

<?php
/**
 * Rewards Points Settings Page
 *
 * @package BookingX\RewardsPoints
 * @since   1.0.0
 */

namespace BookingX\RewardsPoints\Admin;

use BookingX\RewardsPoints\RewardsPointsAddon;

/**
 * Class SettingsPage
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var RewardsPointsAddon
	 */
	private RewardsPointsAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param RewardsPointsAddon $addon Addon instance.
	 */
	public function __construct( RewardsPointsAddon $addon ) {
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
			__( 'Rewards Points', 'bkx-rewards-points' ),
			__( 'Rewards Points', 'bkx-rewards-points' ),
			'manage_options',
			'bkx-rewards',
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
		if ( 'bkx_booking_page_bkx-rewards' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-rewards-admin',
			BKX_REWARDS_URL . 'assets/css/admin.css',
			array(),
			BKX_REWARDS_VERSION
		);
	}

	/**
	 * Handle admin actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! isset( $_POST['bkx_rewards_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_rewards_nonce'] ) ), 'bkx_rewards_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Save settings.
		if ( isset( $_POST['bkx_rewards_save'] ) ) {
			$settings = array(
				'enabled'           => isset( $_POST['bkx_rewards_enabled'] ),
				'points_per_dollar' => floatval( $_POST['bkx_rewards_points_per_dollar'] ?? 1 ),
				'redemption_value'  => floatval( $_POST['bkx_rewards_redemption_value'] ?? 0.01 ),
				'min_redemption'    => absint( $_POST['bkx_rewards_min_redemption'] ?? 100 ),
				'max_redemption'    => absint( $_POST['bkx_rewards_max_redemption'] ?? 0 ),
				'points_on_signup'  => absint( $_POST['bkx_rewards_signup_points'] ?? 0 ),
				'enable_expiration' => isset( $_POST['bkx_rewards_enable_expiration'] ),
				'expiration_days'   => absint( $_POST['bkx_rewards_expiration_days'] ?? 365 ),
			);

			foreach ( $settings as $key => $value ) {
				$this->addon->update_setting( $key, $value );
			}

			add_settings_error( 'bkx_rewards', 'settings_saved', __( 'Settings saved.', 'bkx-rewards-points' ), 'success' );
		}

		// Manual points adjustment.
		if ( isset( $_POST['bkx_rewards_adjust'] ) ) {
			$user_id = absint( $_POST['bkx_rewards_user_id'] ?? 0 );
			$points  = intval( $_POST['bkx_rewards_adjust_points'] ?? 0 );
			$reason  = sanitize_text_field( wp_unslash( $_POST['bkx_rewards_adjust_reason'] ?? '' ) );

			if ( $user_id && $points !== 0 ) {
				$service = $this->addon->get_points_service();

				if ( $points > 0 ) {
					$result = $service->add_points( $user_id, $points, 'manual', $reason );
				} else {
					$result = $service->deduct_points( $user_id, abs( $points ), 'manual', $reason );
				}

				if ( $result ) {
					add_settings_error(
						'bkx_rewards',
						'points_adjusted',
						sprintf(
							/* translators: 1: Points, 2: User ID */
							__( '%1$d points adjusted for user #%2$d.', 'bkx-rewards-points' ),
							$points,
							$user_id
						),
						'success'
					);
				} else {
					add_settings_error( 'bkx_rewards', 'adjustment_failed', __( 'Failed to adjust points.', 'bkx-rewards-points' ), 'error' );
				}
			}
		}

		// License activation.
		if ( isset( $_POST['bkx_rewards_license_activate'] ) ) {
			$license_key = sanitize_text_field( wp_unslash( $_POST['bkx_rewards_license_key'] ?? '' ) );
			$result      = $this->addon->activate_license( $license_key );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'bkx_rewards', 'license_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'bkx_rewards', 'license_activated', __( 'License activated.', 'bkx-rewards-points' ), 'success' );
			}
		}

		// License deactivation.
		if ( isset( $_POST['bkx_rewards_license_deactivate'] ) ) {
			$this->addon->deactivate_license();
			add_settings_error( 'bkx_rewards', 'license_deactivated', __( 'License deactivated.', 'bkx-rewards-points' ), 'success' );
		}
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$tabs        = array(
			'settings'  => __( 'Settings', 'bkx-rewards-points' ),
			'customers' => __( 'Customers', 'bkx-rewards-points' ),
			'adjust'    => __( 'Adjust Points', 'bkx-rewards-points' ),
			'license'   => __( 'License', 'bkx-rewards-points' ),
		);
		?>
		<div class="wrap bkx-rewards-settings">
			<h1><?php esc_html_e( 'Rewards Points', 'bkx-rewards-points' ); ?></h1>

			<?php settings_errors( 'bkx_rewards' ); ?>

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
					case 'customers':
						$this->render_customers_tab();
						break;
					case 'adjust':
						$this->render_adjust_tab();
						break;
					case 'license':
						$this->render_license_tab();
						break;
					default:
						$this->render_settings_tab();
						break;
				}
				?>
			</div>
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
			<?php wp_nonce_field( 'bkx_rewards_settings', 'bkx_rewards_nonce' ); ?>

			<h2><?php esc_html_e( 'Points Configuration', 'bkx-rewards-points' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Rewards', 'bkx-rewards-points' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_rewards_enabled" value="1" <?php checked( $this->addon->get_setting( 'enabled', true ) ); ?>>
							<?php esc_html_e( 'Enable rewards points system', 'bkx-rewards-points' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_rewards_points_per_dollar"><?php esc_html_e( 'Points per Dollar', 'bkx-rewards-points' ); ?></label>
					</th>
					<td>
						<input type="number"
							   id="bkx_rewards_points_per_dollar"
							   name="bkx_rewards_points_per_dollar"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'points_per_dollar', 1 ) ); ?>"
							   step="0.1"
							   min="0"
							   class="small-text">
						<p class="description"><?php esc_html_e( 'Points earned per dollar spent on bookings.', 'bkx-rewards-points' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_rewards_redemption_value"><?php esc_html_e( 'Redemption Value', 'bkx-rewards-points' ); ?></label>
					</th>
					<td>
						$<input type="number"
							   id="bkx_rewards_redemption_value"
							   name="bkx_rewards_redemption_value"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'redemption_value', 0.01 ) ); ?>"
							   step="0.001"
							   min="0"
							   class="small-text"> per point
						<p class="description"><?php esc_html_e( 'Dollar value of each point when redeeming.', 'bkx-rewards-points' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_rewards_min_redemption"><?php esc_html_e( 'Minimum Redemption', 'bkx-rewards-points' ); ?></label>
					</th>
					<td>
						<input type="number"
							   id="bkx_rewards_min_redemption"
							   name="bkx_rewards_min_redemption"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'min_redemption', 100 ) ); ?>"
							   min="0"
							   class="small-text"> <?php esc_html_e( 'points', 'bkx-rewards-points' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_rewards_max_redemption"><?php esc_html_e( 'Maximum Redemption', 'bkx-rewards-points' ); ?></label>
					</th>
					<td>
						<input type="number"
							   id="bkx_rewards_max_redemption"
							   name="bkx_rewards_max_redemption"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'max_redemption', 0 ) ); ?>"
							   min="0"
							   class="small-text"> <?php esc_html_e( 'points (0 = unlimited)', 'bkx-rewards-points' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_rewards_signup_points"><?php esc_html_e( 'Signup Bonus', 'bkx-rewards-points' ); ?></label>
					</th>
					<td>
						<input type="number"
							   id="bkx_rewards_signup_points"
							   name="bkx_rewards_signup_points"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'points_on_signup', 0 ) ); ?>"
							   min="0"
							   class="small-text"> <?php esc_html_e( 'points', 'bkx-rewards-points' ); ?>
						<p class="description"><?php esc_html_e( 'Points awarded when a new user registers (0 = disabled).', 'bkx-rewards-points' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Expiration', 'bkx-rewards-points' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Expiration', 'bkx-rewards-points' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_rewards_enable_expiration" value="1" <?php checked( $this->addon->get_setting( 'enable_expiration', false ) ); ?>>
							<?php esc_html_e( 'Points expire after a set period', 'bkx-rewards-points' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_rewards_expiration_days"><?php esc_html_e( 'Expiration Period', 'bkx-rewards-points' ); ?></label>
					</th>
					<td>
						<input type="number"
							   id="bkx_rewards_expiration_days"
							   name="bkx_rewards_expiration_days"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'expiration_days', 365 ) ); ?>"
							   min="1"
							   class="small-text"> <?php esc_html_e( 'days', 'bkx-rewards-points' ); ?>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="bkx_rewards_save" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'bkx-rewards-points' ); ?>
				</button>
			</p>
		</form>
		<?php
	}

	/**
	 * Render customers tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_customers_tab(): void {
		global $wpdb;

		$table    = $wpdb->prefix . 'bkx_points_balance';
		$per_page = 25;
		$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table ) );

		$customers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, u.user_email, u.display_name
				FROM %i b
				INNER JOIN {$wpdb->users} u ON b.user_id = u.ID
				ORDER BY b.balance DESC
				LIMIT %d OFFSET %d",
				$table,
				$per_page,
				$offset
			),
			ARRAY_A
		);
		?>
		<h2><?php esc_html_e( 'Customer Points', 'bkx-rewards-points' ); ?></h2>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Customer', 'bkx-rewards-points' ); ?></th>
					<th><?php esc_html_e( 'Balance', 'bkx-rewards-points' ); ?></th>
					<th><?php esc_html_e( 'Lifetime Earned', 'bkx-rewards-points' ); ?></th>
					<th><?php esc_html_e( 'Lifetime Redeemed', 'bkx-rewards-points' ); ?></th>
					<th><?php esc_html_e( 'Last Activity', 'bkx-rewards-points' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $customers ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No customers with points yet.', 'bkx-rewards-points' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $customers as $customer ) : ?>
						<tr>
							<td>
								<?php echo esc_html( $customer['display_name'] ); ?><br>
								<small><?php echo esc_html( $customer['user_email'] ); ?></small>
							</td>
							<td><strong><?php echo esc_html( number_format( $customer['balance'] ) ); ?></strong></td>
							<td><?php echo esc_html( number_format( $customer['lifetime_earned'] ) ); ?></td>
							<td><?php echo esc_html( number_format( $customer['lifetime_redeemed'] ) ); ?></td>
							<td><?php echo $customer['last_activity'] ? esc_html( human_time_diff( strtotime( $customer['last_activity'] ) ) ) . ' ago' : '-'; ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php
		$total_pages = ceil( $total / $per_page );
		if ( $total_pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $page,
						'total'   => $total_pages,
					)
				)
			);
			echo '</div></div>';
		}
	}

	/**
	 * Render adjust points tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_adjust_tab(): void {
		?>
		<h2><?php esc_html_e( 'Manually Adjust Points', 'bkx-rewards-points' ); ?></h2>

		<form method="post">
			<?php wp_nonce_field( 'bkx_rewards_settings', 'bkx_rewards_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bkx_rewards_user_id"><?php esc_html_e( 'User', 'bkx-rewards-points' ); ?></label>
					</th>
					<td>
						<select id="bkx_rewards_user_id" name="bkx_rewards_user_id" required>
							<option value=""><?php esc_html_e( 'Select a user', 'bkx-rewards-points' ); ?></option>
							<?php
							$users = get_users( array( 'number' => 100 ) );
							foreach ( $users as $user ) :
								?>
								<option value="<?php echo esc_attr( $user->ID ); ?>">
									<?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_rewards_adjust_points"><?php esc_html_e( 'Points', 'bkx-rewards-points' ); ?></label>
					</th>
					<td>
						<input type="number"
							   id="bkx_rewards_adjust_points"
							   name="bkx_rewards_adjust_points"
							   value="0"
							   class="regular-text"
							   required>
						<p class="description"><?php esc_html_e( 'Use positive number to add, negative to deduct.', 'bkx-rewards-points' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_rewards_adjust_reason"><?php esc_html_e( 'Reason', 'bkx-rewards-points' ); ?></label>
					</th>
					<td>
						<input type="text"
							   id="bkx_rewards_adjust_reason"
							   name="bkx_rewards_adjust_reason"
							   value=""
							   class="regular-text"
							   placeholder="<?php esc_attr_e( 'Optional reason', 'bkx-rewards-points' ); ?>">
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="bkx_rewards_adjust" class="button button-primary">
					<?php esc_html_e( 'Adjust Points', 'bkx-rewards-points' ); ?>
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
		$license_key    = get_option( 'bkx_rewards_license_key', '' );
		$license_status = get_option( 'bkx_rewards_license_status', '' );
		?>
		<div class="bkx-license-settings">
			<h2><?php esc_html_e( 'License Activation', 'bkx-rewards-points' ); ?></h2>

			<p><?php esc_html_e( 'Enter your license key to receive automatic updates and support.', 'bkx-rewards-points' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'bkx_rewards_settings', 'bkx_rewards_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="bkx_rewards_license_key"><?php esc_html_e( 'License Key', 'bkx-rewards-points' ); ?></label>
						</th>
						<td>
							<input type="password"
								   id="bkx_rewards_license_key"
								   name="bkx_rewards_license_key"
								   value="<?php echo esc_attr( $license_key ); ?>"
								   class="regular-text"
								   autocomplete="off">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'bkx-rewards-points' ); ?></th>
						<td>
							<?php if ( 'valid' === $license_status ) : ?>
								<span class="bkx-license-status bkx-license-active">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Active', 'bkx-rewards-points' ); ?>
								</span>
							<?php elseif ( 'expired' === $license_status ) : ?>
								<span class="bkx-license-status bkx-license-expired">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'Expired', 'bkx-rewards-points' ); ?>
								</span>
							<?php else : ?>
								<span class="bkx-license-status bkx-license-inactive">
									<span class="dashicons dashicons-marker"></span>
									<?php esc_html_e( 'Not Activated', 'bkx-rewards-points' ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<p class="submit">
					<?php if ( 'valid' === $license_status ) : ?>
						<button type="submit" name="bkx_rewards_license_deactivate" class="button">
							<?php esc_html_e( 'Deactivate License', 'bkx-rewards-points' ); ?>
						</button>
					<?php else : ?>
						<button type="submit" name="bkx_rewards_license_activate" class="button button-primary">
							<?php esc_html_e( 'Activate License', 'bkx-rewards-points' ); ?>
						</button>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}
}

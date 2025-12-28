<?php
/**
 * Hold Blocks Admin Page
 *
 * @package BookingX\HoldBlocks\Admin
 * @since   1.0.0
 */

namespace BookingX\HoldBlocks\Admin;

/**
 * Admin page for managing hold blocks.
 *
 * @since 1.0.0
 */
class HoldBlocksPage {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\HoldBlocks\HoldBlocksAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param \BookingX\HoldBlocks\HoldBlocksAddon $addon Parent addon.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add menu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'bookingx',
			__( 'Hold Blocks', 'bkx-hold-blocks' ),
			__( 'Hold Blocks', 'bkx-hold-blocks' ),
			'manage_options',
			'bkx-hold-blocks',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'bkx_hold_blocks_settings',
			'bkx_hold_blocks_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @since 1.0.0
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		$sanitized['show_blocked_as_unavailable'] = isset( $input['show_blocked_as_unavailable'] ) ? 1 : 0;
		$sanitized['allow_admin_override']        = isset( $input['allow_admin_override'] ) ? 1 : 0;
		$sanitized['block_reason_required']       = isset( $input['block_reason_required'] ) ? 1 : 0;
		$sanitized['auto_cleanup_days']           = isset( $input['auto_cleanup_days'] ) ? absint( $input['auto_cleanup_days'] ) : 30;

		return $sanitized;
	}

	/**
	 * Render the page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'blocks';
		$settings   = $this->addon->get_settings();

		// Get seats for dropdown.
		$seats = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="wrap bkx-hold-blocks-page">
			<h1><?php esc_html_e( 'Hold Date/Time Blocks', 'bkx-hold-blocks' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hold-blocks&tab=blocks' ) ); ?>"
				   class="nav-tab <?php echo 'blocks' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Blocks', 'bkx-hold-blocks' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hold-blocks&tab=settings' ) ); ?>"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bkx-hold-blocks' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hold-blocks&tab=license' ) ); ?>"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'bkx-hold-blocks' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'settings':
						$this->render_settings_tab( $settings );
						break;
					case 'license':
						$this->addon->render_license_page();
						break;
					default:
						$this->render_blocks_tab( $seats );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render blocks tab.
	 *
	 * @since 1.0.0
	 * @param array $seats Available seats.
	 * @return void
	 */
	private function render_blocks_tab( array $seats ): void {
		$service  = new \BookingX\HoldBlocks\Services\BlockService();
		$seat_id  = isset( $_GET['seat_id'] ) ? absint( $_GET['seat_id'] ) : 0;
		$today    = current_time( 'Y-m-d' );
		$end_date = gmdate( 'Y-m-d', strtotime( '+3 months' ) );
		$blocks   = $service->get_blocks( $seat_id ?: null, $today, $end_date );
		?>
		<div class="bkx-blocks-container">
			<div class="bkx-blocks-sidebar">
				<h3><?php esc_html_e( 'Add New Block', 'bkx-hold-blocks' ); ?></h3>

				<form id="bkx-add-block-form" class="bkx-block-form">
					<div class="bkx-form-row">
						<label for="bkx_block_seat"><?php esc_html_e( 'Resource', 'bkx-hold-blocks' ); ?></label>
						<select id="bkx_block_seat" name="seat_id">
							<option value=""><?php esc_html_e( 'All Resources', 'bkx-hold-blocks' ); ?></option>
							<?php foreach ( $seats as $seat ) : ?>
								<option value="<?php echo esc_attr( $seat->ID ); ?>">
									<?php echo esc_html( $seat->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="bkx-form-row">
						<label for="bkx_block_type"><?php esc_html_e( 'Block Type', 'bkx-hold-blocks' ); ?></label>
						<select id="bkx_block_type" name="block_type">
							<option value="hold"><?php esc_html_e( 'Hold', 'bkx-hold-blocks' ); ?></option>
							<option value="holiday"><?php esc_html_e( 'Holiday', 'bkx-hold-blocks' ); ?></option>
							<option value="maintenance"><?php esc_html_e( 'Maintenance', 'bkx-hold-blocks' ); ?></option>
							<option value="private"><?php esc_html_e( 'Private Event', 'bkx-hold-blocks' ); ?></option>
							<option value="break"><?php esc_html_e( 'Break', 'bkx-hold-blocks' ); ?></option>
							<option value="other"><?php esc_html_e( 'Other', 'bkx-hold-blocks' ); ?></option>
						</select>
					</div>

					<div class="bkx-form-row">
						<label for="bkx_block_start_date"><?php esc_html_e( 'Start Date', 'bkx-hold-blocks' ); ?> <span class="required">*</span></label>
						<input type="date" id="bkx_block_start_date" name="start_date" required>
					</div>

					<div class="bkx-form-row">
						<label for="bkx_block_end_date"><?php esc_html_e( 'End Date', 'bkx-hold-blocks' ); ?></label>
						<input type="date" id="bkx_block_end_date" name="end_date">
						<span class="description"><?php esc_html_e( 'Leave empty for single day', 'bkx-hold-blocks' ); ?></span>
					</div>

					<div class="bkx-form-row">
						<label>
							<input type="checkbox" id="bkx_block_all_day" name="all_day" value="1" checked>
							<?php esc_html_e( 'All Day', 'bkx-hold-blocks' ); ?>
						</label>
					</div>

					<div class="bkx-time-fields" style="display: none;">
						<div class="bkx-form-row bkx-form-inline">
							<div>
								<label for="bkx_block_start_time"><?php esc_html_e( 'Start Time', 'bkx-hold-blocks' ); ?></label>
								<input type="time" id="bkx_block_start_time" name="start_time" value="09:00">
							</div>
							<div>
								<label for="bkx_block_end_time"><?php esc_html_e( 'End Time', 'bkx-hold-blocks' ); ?></label>
								<input type="time" id="bkx_block_end_time" name="end_time" value="17:00">
							</div>
						</div>
					</div>

					<div class="bkx-form-row">
						<label for="bkx_block_recurring"><?php esc_html_e( 'Recurring', 'bkx-hold-blocks' ); ?></label>
						<select id="bkx_block_recurring" name="recurring">
							<option value=""><?php esc_html_e( 'No repeat', 'bkx-hold-blocks' ); ?></option>
							<option value="daily"><?php esc_html_e( 'Daily', 'bkx-hold-blocks' ); ?></option>
							<option value="weekly"><?php esc_html_e( 'Weekly', 'bkx-hold-blocks' ); ?></option>
							<option value="monthly"><?php esc_html_e( 'Monthly', 'bkx-hold-blocks' ); ?></option>
							<option value="yearly"><?php esc_html_e( 'Yearly', 'bkx-hold-blocks' ); ?></option>
						</select>
					</div>

					<div class="bkx-form-row">
						<label for="bkx_block_reason"><?php esc_html_e( 'Reason / Notes', 'bkx-hold-blocks' ); ?></label>
						<textarea id="bkx_block_reason" name="reason" rows="3"></textarea>
					</div>

					<div class="bkx-form-actions">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Add Block', 'bkx-hold-blocks' ); ?>
						</button>
					</div>
				</form>
			</div>

			<div class="bkx-blocks-main">
				<div class="bkx-blocks-filter">
					<label for="bkx_filter_seat"><?php esc_html_e( 'Filter by Resource:', 'bkx-hold-blocks' ); ?></label>
					<select id="bkx_filter_seat">
						<option value=""><?php esc_html_e( 'All Resources', 'bkx-hold-blocks' ); ?></option>
						<?php foreach ( $seats as $seat ) : ?>
							<option value="<?php echo esc_attr( $seat->ID ); ?>" <?php selected( $seat_id, $seat->ID ); ?>>
								<?php echo esc_html( $seat->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<h3><?php esc_html_e( 'Active Blocks', 'bkx-hold-blocks' ); ?></h3>

				<?php if ( empty( $blocks ) ) : ?>
					<p class="bkx-no-blocks"><?php esc_html_e( 'No blocks scheduled.', 'bkx-hold-blocks' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped" id="bkx-blocks-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Type', 'bkx-hold-blocks' ); ?></th>
								<th><?php esc_html_e( 'Resource', 'bkx-hold-blocks' ); ?></th>
								<th><?php esc_html_e( 'Date/Time', 'bkx-hold-blocks' ); ?></th>
								<th><?php esc_html_e( 'Recurring', 'bkx-hold-blocks' ); ?></th>
								<th><?php esc_html_e( 'Reason', 'bkx-hold-blocks' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bkx-hold-blocks' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $blocks as $block ) : ?>
								<tr data-block-id="<?php echo esc_attr( $block['id'] ); ?>">
									<td>
										<span class="bkx-block-type bkx-type-<?php echo esc_attr( $block['block_type'] ); ?>">
											<?php echo esc_html( ucfirst( $block['block_type'] ) ); ?>
										</span>
									</td>
									<td>
										<?php
										if ( $block['seat_id'] ) {
											echo esc_html( get_the_title( $block['seat_id'] ) );
										} else {
											esc_html_e( 'All Resources', 'bkx-hold-blocks' );
										}
										?>
									</td>
									<td>
										<?php
										echo esc_html( wp_date( 'M j, Y', strtotime( $block['start_date'] ) ) );
										if ( $block['end_date'] && $block['end_date'] !== $block['start_date'] ) {
											echo ' - ' . esc_html( wp_date( 'M j, Y', strtotime( $block['end_date'] ) ) );
										}
										if ( ! $block['all_day'] && $block['start_time'] ) {
											echo '<br><small>' . esc_html( wp_date( 'g:i A', strtotime( $block['start_time'] ) ) );
											if ( $block['end_time'] ) {
												echo ' - ' . esc_html( wp_date( 'g:i A', strtotime( $block['end_time'] ) ) );
											}
											echo '</small>';
										} else {
											echo '<br><small>' . esc_html__( 'All day', 'bkx-hold-blocks' ) . '</small>';
										}
										?>
									</td>
									<td>
										<?php
										if ( $block['recurring'] ) {
											echo esc_html( ucfirst( $block['recurring'] ) );
										} else {
											echo '—';
										}
										?>
									</td>
									<td><?php echo esc_html( $block['reason'] ?: '—' ); ?></td>
									<td>
										<button type="button" class="button button-small bkx-delete-block" data-id="<?php echo esc_attr( $block['id'] ); ?>">
											<?php esc_html_e( 'Delete', 'bkx-hold-blocks' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings tab.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_settings_tab( array $settings ): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bkx_hold_blocks_settings' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Show Blocked as Unavailable', 'bkx-hold-blocks' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_hold_blocks_settings[show_blocked_as_unavailable]" value="1"
								   <?php checked( $settings['show_blocked_as_unavailable'] ?? 1, 1 ); ?>>
							<?php esc_html_e( 'Display blocked times as unavailable in the booking form', 'bkx-hold-blocks' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Admin Override', 'bkx-hold-blocks' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_hold_blocks_settings[allow_admin_override]" value="1"
								   <?php checked( $settings['allow_admin_override'] ?? 1, 1 ); ?>>
							<?php esc_html_e( 'Allow administrators to create bookings during blocked times', 'bkx-hold-blocks' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Require Reason', 'bkx-hold-blocks' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_hold_blocks_settings[block_reason_required]" value="1"
								   <?php checked( $settings['block_reason_required'] ?? 0, 1 ); ?>>
							<?php esc_html_e( 'Require a reason when creating blocks', 'bkx-hold-blocks' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-Cleanup', 'bkx-hold-blocks' ); ?></th>
					<td>
						<input type="number" name="bkx_hold_blocks_settings[auto_cleanup_days]"
							   value="<?php echo esc_attr( $settings['auto_cleanup_days'] ?? 30 ); ?>"
							   min="0" max="365" class="small-text">
						<?php esc_html_e( 'days', 'bkx-hold-blocks' ); ?>
						<p class="description">
							<?php esc_html_e( 'Automatically delete non-recurring blocks older than this many days. Set to 0 to disable.', 'bkx-hold-blocks' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}
}

<?php
/**
 * Sliding Pricing Admin Template.
 *
 * @package BookingX\SlidingPricing
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get services.
$addon     = \BookingX\SlidingPricing\SlidingPricingAddon::get_instance();
$rules     = $addon->get_service( 'rules' );
$seasons   = $addon->get_service( 'seasons' );
$timeslots = $addon->get_service( 'timeslots' );

// Get data.
$all_rules     = $rules->get_rules();
$all_seasons   = $seasons->get_seasons();
$all_timeslots = $timeslots->get_timeslots();

// Get services and staff for dropdowns.
$services = get_posts(
	array(
		'post_type'      => 'bkx_base',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);

// Get current tab.
$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'rules';
$tabs        = array(
	'rules'     => __( 'Pricing Rules', 'bkx-sliding-pricing' ),
	'seasons'   => __( 'Seasonal Pricing', 'bkx-sliding-pricing' ),
	'timeslots' => __( 'Time-Based Pricing', 'bkx-sliding-pricing' ),
	'preview'   => __( 'Price Preview', 'bkx-sliding-pricing' ),
	'settings'  => __( 'Settings', 'bkx-sliding-pricing' ),
);
?>
<div class="wrap bkx-pricing-wrap">
	<h1><?php esc_html_e( 'Dynamic Pricing', 'bkx-sliding-pricing' ); ?></h1>

	<nav class="nav-tab-wrapper bkx-pricing-nav">
		<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-pricing-content">

		<?php if ( 'rules' === $current_tab ) : ?>
			<!-- Pricing Rules Tab -->
			<div class="bkx-pricing-rules">
				<div class="bkx-card-header">
					<h2><?php esc_html_e( 'Pricing Rules', 'bkx-sliding-pricing' ); ?></h2>
					<button type="button" class="button button-primary" id="bkx-add-rule">
						<?php esc_html_e( 'Add New Rule', 'bkx-sliding-pricing' ); ?>
					</button>
				</div>

				<div class="bkx-pricing-card">
					<table class="wp-list-table widefat fixed striped" id="bkx-rules-table">
						<thead>
							<tr>
								<th class="column-order" style="width: 30px;"></th>
								<th><?php esc_html_e( 'Name', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Type', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Adjustment', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Applies To', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bkx-sliding-pricing' ); ?></th>
							</tr>
						</thead>
						<tbody id="bkx-rules-list">
							<?php foreach ( $all_rules as $rule ) : ?>
								<tr data-id="<?php echo esc_attr( $rule['id'] ); ?>">
									<td class="column-order"><span class="dashicons dashicons-menu"></span></td>
									<td><strong><?php echo esc_html( $rule['name'] ); ?></strong></td>
									<td><?php echo esc_html( $rule['type_label'] ); ?></td>
									<td>
										<?php
										$sign = $rule['adjustment_value'] >= 0 ? '+' : '';
										if ( 'percentage' === $rule['adjustment_type'] ) {
											echo esc_html( $sign . $rule['adjustment_value'] . '%' );
										} else {
											echo esc_html( $sign . '$' . number_format( $rule['adjustment_value'], 2 ) );
										}
										?>
									</td>
									<td><?php echo 'all' === $rule['applies_to'] ? __( 'All Services', 'bkx-sliding-pricing' ) : __( 'Specific', 'bkx-sliding-pricing' ); ?></td>
									<td>
										<span class="bkx-status <?php echo $rule['is_active'] ? 'active' : 'inactive'; ?>">
											<?php echo $rule['is_active'] ? __( 'Active', 'bkx-sliding-pricing' ) : __( 'Inactive', 'bkx-sliding-pricing' ); ?>
										</span>
									</td>
									<td>
										<button type="button" class="button button-small bkx-edit-rule" data-id="<?php echo esc_attr( $rule['id'] ); ?>">
											<?php esc_html_e( 'Edit', 'bkx-sliding-pricing' ); ?>
										</button>
										<button type="button" class="button button-small bkx-delete-rule" data-id="<?php echo esc_attr( $rule['id'] ); ?>">
											<?php esc_html_e( 'Delete', 'bkx-sliding-pricing' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if ( empty( $all_rules ) ) : ?>
								<tr><td colspan="7" class="bkx-empty"><?php esc_html_e( 'No pricing rules yet. Create your first rule!', 'bkx-sliding-pricing' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

		<?php elseif ( 'seasons' === $current_tab ) : ?>
			<!-- Seasonal Pricing Tab -->
			<div class="bkx-pricing-seasons">
				<div class="bkx-card-header">
					<h2><?php esc_html_e( 'Seasonal Pricing', 'bkx-sliding-pricing' ); ?></h2>
					<button type="button" class="button button-primary" id="bkx-add-season">
						<?php esc_html_e( 'Add Season', 'bkx-sliding-pricing' ); ?>
					</button>
				</div>

				<div class="bkx-pricing-card">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Date Range', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Adjustment', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Recurs', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bkx-sliding-pricing' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $all_seasons as $season ) : ?>
								<tr data-id="<?php echo esc_attr( $season['id'] ); ?>">
									<td>
										<strong><?php echo esc_html( $season['name'] ); ?></strong>
										<?php if ( $season['is_current'] ) : ?>
											<span class="bkx-badge current"><?php esc_html_e( 'Current', 'bkx-sliding-pricing' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php
										echo esc_html(
											gmdate( 'M j', strtotime( $season['start_date'] ) ) . ' - ' .
											gmdate( 'M j', strtotime( $season['end_date'] ) )
										);
										?>
									</td>
									<td>
										<?php
										$sign = $season['adjustment_value'] >= 0 ? '+' : '';
										if ( 'percentage' === $season['adjustment_type'] ) {
											echo esc_html( $sign . $season['adjustment_value'] . '%' );
										} else {
											echo esc_html( $sign . '$' . number_format( $season['adjustment_value'], 2 ) );
										}
										?>
									</td>
									<td><?php echo $season['recurs_yearly'] ? __( 'Yearly', 'bkx-sliding-pricing' ) : __( 'Once', 'bkx-sliding-pricing' ); ?></td>
									<td>
										<span class="bkx-status <?php echo $season['is_active'] ? 'active' : 'inactive'; ?>">
											<?php echo $season['is_active'] ? __( 'Active', 'bkx-sliding-pricing' ) : __( 'Inactive', 'bkx-sliding-pricing' ); ?>
										</span>
									</td>
									<td>
										<button type="button" class="button button-small bkx-edit-season" data-id="<?php echo esc_attr( $season['id'] ); ?>">
											<?php esc_html_e( 'Edit', 'bkx-sliding-pricing' ); ?>
										</button>
										<button type="button" class="button button-small bkx-delete-season" data-id="<?php echo esc_attr( $season['id'] ); ?>">
											<?php esc_html_e( 'Delete', 'bkx-sliding-pricing' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if ( empty( $all_seasons ) ) : ?>
								<tr><td colspan="6" class="bkx-empty"><?php esc_html_e( 'No seasons defined yet.', 'bkx-sliding-pricing' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

		<?php elseif ( 'timeslots' === $current_tab ) : ?>
			<!-- Time-Based Pricing Tab -->
			<div class="bkx-pricing-timeslots">
				<div class="bkx-card-header">
					<h2><?php esc_html_e( 'Time-Based Pricing', 'bkx-sliding-pricing' ); ?></h2>
					<button type="button" class="button button-primary" id="bkx-add-timeslot">
						<?php esc_html_e( 'Add Time Slot', 'bkx-sliding-pricing' ); ?>
					</button>
				</div>

				<div class="bkx-pricing-card">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Days', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Time Range', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Adjustment', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-sliding-pricing' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bkx-sliding-pricing' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $all_timeslots as $slot ) : ?>
								<tr data-id="<?php echo esc_attr( $slot['id'] ); ?>">
									<td><strong><?php echo esc_html( $slot['name'] ); ?></strong></td>
									<td><?php echo esc_html( $slot['day_label'] ); ?></td>
									<td><?php echo esc_html( $slot['time_range'] ); ?></td>
									<td>
										<?php
										$sign  = $slot['adjustment_value'] >= 0 ? '+' : '';
										$class = $slot['adjustment_value'] >= 0 ? 'peak' : 'off-peak';
										?>
										<span class="bkx-adjustment <?php echo esc_attr( $class ); ?>">
											<?php
											if ( 'percentage' === $slot['adjustment_type'] ) {
												echo esc_html( $sign . $slot['adjustment_value'] . '%' );
											} else {
												echo esc_html( $sign . '$' . number_format( $slot['adjustment_value'], 2 ) );
											}
											?>
										</span>
									</td>
									<td>
										<span class="bkx-status <?php echo $slot['is_active'] ? 'active' : 'inactive'; ?>">
											<?php echo $slot['is_active'] ? __( 'Active', 'bkx-sliding-pricing' ) : __( 'Inactive', 'bkx-sliding-pricing' ); ?>
										</span>
									</td>
									<td>
										<button type="button" class="button button-small bkx-edit-timeslot" data-id="<?php echo esc_attr( $slot['id'] ); ?>">
											<?php esc_html_e( 'Edit', 'bkx-sliding-pricing' ); ?>
										</button>
										<button type="button" class="button button-small bkx-delete-timeslot" data-id="<?php echo esc_attr( $slot['id'] ); ?>">
											<?php esc_html_e( 'Delete', 'bkx-sliding-pricing' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if ( empty( $all_timeslots ) ) : ?>
								<tr><td colspan="6" class="bkx-empty"><?php esc_html_e( 'No time slots defined yet.', 'bkx-sliding-pricing' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<!-- Price Heatmap -->
				<div class="bkx-pricing-card bkx-heatmap-card">
					<h2><?php esc_html_e( 'Weekly Price Heatmap', 'bkx-sliding-pricing' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Visual representation of price adjustments throughout the week.', 'bkx-sliding-pricing' ); ?></p>
					<div id="bkx-price-heatmap" class="bkx-heatmap"></div>
				</div>
			</div>

		<?php elseif ( 'preview' === $current_tab ) : ?>
			<!-- Price Preview Tab -->
			<div class="bkx-pricing-preview">
				<div class="bkx-pricing-card">
					<h2><?php esc_html_e( 'Price Calculator', 'bkx-sliding-pricing' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Preview how prices will be calculated for different dates and times.', 'bkx-sliding-pricing' ); ?></p>

					<div class="bkx-preview-form">
						<div class="bkx-form-row">
							<div class="bkx-form-field">
								<label for="preview-service"><?php esc_html_e( 'Service', 'bkx-sliding-pricing' ); ?></label>
								<select id="preview-service">
									<?php foreach ( $services as $service ) : ?>
										<option value="<?php echo esc_attr( $service->ID ); ?>" data-price="<?php echo esc_attr( get_post_meta( $service->ID, 'base_price', true ) ); ?>">
											<?php echo esc_html( $service->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="bkx-form-field">
								<label for="preview-date"><?php esc_html_e( 'Date', 'bkx-sliding-pricing' ); ?></label>
								<input type="date" id="preview-date" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
							</div>
							<div class="bkx-form-field">
								<label for="preview-time"><?php esc_html_e( 'Time', 'bkx-sliding-pricing' ); ?></label>
								<input type="time" id="preview-time" value="10:00">
							</div>
							<div class="bkx-form-field">
								<button type="button" class="button button-primary" id="bkx-calculate-preview">
									<?php esc_html_e( 'Calculate Price', 'bkx-sliding-pricing' ); ?>
								</button>
							</div>
						</div>
					</div>

					<div id="bkx-preview-result" class="bkx-preview-result" style="display: none;">
						<div class="bkx-preview-prices">
							<div class="bkx-price-box base">
								<span class="label"><?php esc_html_e( 'Base Price', 'bkx-sliding-pricing' ); ?></span>
								<span class="value" id="preview-base">$0.00</span>
							</div>
							<div class="bkx-price-arrow">→</div>
							<div class="bkx-price-box final">
								<span class="label"><?php esc_html_e( 'Final Price', 'bkx-sliding-pricing' ); ?></span>
								<span class="value" id="preview-final">$0.00</span>
							</div>
							<div class="bkx-price-box savings">
								<span class="label"><?php esc_html_e( 'Difference', 'bkx-sliding-pricing' ); ?></span>
								<span class="value" id="preview-diff">$0.00</span>
							</div>
						</div>
						<div class="bkx-adjustments-list">
							<h3><?php esc_html_e( 'Applied Adjustments', 'bkx-sliding-pricing' ); ?></h3>
							<ul id="preview-adjustments"></ul>
						</div>
					</div>
				</div>
			</div>

		<?php elseif ( 'settings' === $current_tab ) : ?>
			<!-- Settings Tab -->
			<div class="bkx-pricing-settings">
				<form method="post" action="options.php">
					<?php settings_fields( 'bkx_sliding_pricing_settings' ); ?>

					<div class="bkx-pricing-card">
						<h2><?php esc_html_e( 'Display Settings', 'bkx-sliding-pricing' ); ?></h2>

						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Show Original Price', 'bkx-sliding-pricing' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="bkx_sliding_pricing_show_original" value="yes"
											<?php checked( get_option( 'bkx_sliding_pricing_show_original', 'yes' ), 'yes' ); ?>>
										<?php esc_html_e( 'Display crossed-out original price when discounted', 'bkx-sliding-pricing' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Show Savings', 'bkx-sliding-pricing' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="bkx_sliding_pricing_show_savings" value="yes"
											<?php checked( get_option( 'bkx_sliding_pricing_show_savings', 'yes' ), 'yes' ); ?>>
										<?php esc_html_e( 'Display "Save X%" badge when discounted', 'bkx-sliding-pricing' ); ?>
									</label>
								</td>
							</tr>
						</table>
					</div>

					<div class="bkx-pricing-card">
						<h2><?php esc_html_e( 'Calculation Settings', 'bkx-sliding-pricing' ); ?></h2>

						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Stack Rules', 'bkx-sliding-pricing' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="bkx_sliding_pricing_stack_rules" value="yes"
											<?php checked( get_option( 'bkx_sliding_pricing_stack_rules', 'yes' ), 'yes' ); ?>>
										<?php esc_html_e( 'Apply multiple rules cumulatively (otherwise only highest priority rule applies)', 'bkx-sliding-pricing' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Maximum Discount', 'bkx-sliding-pricing' ); ?></th>
								<td>
									<input type="number" name="bkx_sliding_pricing_max_discount" class="small-text"
										value="<?php echo esc_attr( get_option( 'bkx_sliding_pricing_max_discount', 50 ) ); ?>"
										min="0" max="100" step="1">
									<span>%</span>
									<p class="description"><?php esc_html_e( 'Maximum discount that can be applied (prevents prices going too low)', 'bkx-sliding-pricing' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

					<?php submit_button(); ?>
				</form>
			</div>

		<?php endif; ?>

	</div>
</div>

<!-- Rule Modal -->
<div id="bkx-rule-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content bkx-modal-large">
		<span class="bkx-modal-close">&times;</span>
		<h2 id="bkx-rule-modal-title"><?php esc_html_e( 'Add Pricing Rule', 'bkx-sliding-pricing' ); ?></h2>
		<form id="bkx-rule-form">
			<input type="hidden" name="rule_id" id="rule-id" value="">
			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="rule-name"><?php esc_html_e( 'Rule Name', 'bkx-sliding-pricing' ); ?></label>
					<input type="text" name="name" id="rule-name" required>
				</div>
				<div class="bkx-form-field">
					<label for="rule-type"><?php esc_html_e( 'Rule Type', 'bkx-sliding-pricing' ); ?></label>
					<select name="rule_type" id="rule-type">
						<?php foreach ( $rules->get_rule_types() as $type => $label ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="rule-adjustment-type"><?php esc_html_e( 'Adjustment Type', 'bkx-sliding-pricing' ); ?></label>
					<select name="adjustment_type" id="rule-adjustment-type">
						<option value="percentage"><?php esc_html_e( 'Percentage', 'bkx-sliding-pricing' ); ?></option>
						<option value="fixed"><?php esc_html_e( 'Fixed Amount', 'bkx-sliding-pricing' ); ?></option>
						<option value="set"><?php esc_html_e( 'Set Price', 'bkx-sliding-pricing' ); ?></option>
					</select>
				</div>
				<div class="bkx-form-field">
					<label for="rule-adjustment-value"><?php esc_html_e( 'Value', 'bkx-sliding-pricing' ); ?></label>
					<input type="number" name="adjustment_value" id="rule-adjustment-value" step="0.01">
					<p class="description"><?php esc_html_e( 'Negative for discount, positive for surcharge', 'bkx-sliding-pricing' ); ?></p>
				</div>
			</div>
			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="rule-applies-to"><?php esc_html_e( 'Applies To', 'bkx-sliding-pricing' ); ?></label>
					<select name="applies_to" id="rule-applies-to">
						<option value="all"><?php esc_html_e( 'All Services', 'bkx-sliding-pricing' ); ?></option>
						<option value="specific"><?php esc_html_e( 'Specific Services', 'bkx-sliding-pricing' ); ?></option>
					</select>
				</div>
				<div class="bkx-form-field" id="rule-services-field" style="display: none;">
					<label for="rule-services"><?php esc_html_e( 'Services', 'bkx-sliding-pricing' ); ?></label>
					<select name="service_ids[]" id="rule-services" multiple>
						<?php foreach ( $services as $service ) : ?>
							<option value="<?php echo esc_attr( $service->ID ); ?>">
								<?php echo esc_html( $service->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="rule-start-date"><?php esc_html_e( 'Start Date', 'bkx-sliding-pricing' ); ?></label>
					<input type="date" name="start_date" id="rule-start-date">
				</div>
				<div class="bkx-form-field">
					<label for="rule-end-date"><?php esc_html_e( 'End Date', 'bkx-sliding-pricing' ); ?></label>
					<input type="date" name="end_date" id="rule-end-date">
				</div>
			</div>
			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label>
						<input type="checkbox" name="is_active" id="rule-active" value="1" checked>
						<?php esc_html_e( 'Active', 'bkx-sliding-pricing' ); ?>
					</label>
				</div>
			</div>
			<p class="bkx-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Rule', 'bkx-sliding-pricing' ); ?></button>
				<button type="button" class="button bkx-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-sliding-pricing' ); ?></button>
			</p>
		</form>
	</div>
</div>

<!-- Season Modal -->
<div id="bkx-season-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<span class="bkx-modal-close">&times;</span>
		<h2 id="bkx-season-modal-title"><?php esc_html_e( 'Add Season', 'bkx-sliding-pricing' ); ?></h2>
		<form id="bkx-season-form">
			<input type="hidden" name="season_id" id="season-id" value="">
			<p>
				<label for="season-name"><?php esc_html_e( 'Season Name', 'bkx-sliding-pricing' ); ?></label>
				<input type="text" name="name" id="season-name" required>
			</p>
			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="season-start"><?php esc_html_e( 'Start Date', 'bkx-sliding-pricing' ); ?></label>
					<input type="date" name="start_date" id="season-start" required>
				</div>
				<div class="bkx-form-field">
					<label for="season-end"><?php esc_html_e( 'End Date', 'bkx-sliding-pricing' ); ?></label>
					<input type="date" name="end_date" id="season-end" required>
				</div>
			</div>
			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="season-adjustment-type"><?php esc_html_e( 'Adjustment', 'bkx-sliding-pricing' ); ?></label>
					<select name="adjustment_type" id="season-adjustment-type">
						<option value="percentage"><?php esc_html_e( 'Percentage', 'bkx-sliding-pricing' ); ?></option>
						<option value="fixed"><?php esc_html_e( 'Fixed Amount', 'bkx-sliding-pricing' ); ?></option>
					</select>
				</div>
				<div class="bkx-form-field">
					<label for="season-adjustment-value"><?php esc_html_e( 'Value', 'bkx-sliding-pricing' ); ?></label>
					<input type="number" name="adjustment_value" id="season-adjustment-value" step="0.01" required>
				</div>
			</div>
			<p>
				<label>
					<input type="checkbox" name="recurs_yearly" id="season-recurs" value="1">
					<?php esc_html_e( 'Recurs every year', 'bkx-sliding-pricing' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="is_active" id="season-active" value="1" checked>
					<?php esc_html_e( 'Active', 'bkx-sliding-pricing' ); ?>
				</label>
			</p>
			<p class="bkx-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Season', 'bkx-sliding-pricing' ); ?></button>
				<button type="button" class="button bkx-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-sliding-pricing' ); ?></button>
			</p>
		</form>
	</div>
</div>

<!-- Timeslot Modal -->
<div id="bkx-timeslot-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<span class="bkx-modal-close">&times;</span>
		<h2 id="bkx-timeslot-modal-title"><?php esc_html_e( 'Add Time Slot', 'bkx-sliding-pricing' ); ?></h2>
		<form id="bkx-timeslot-form">
			<input type="hidden" name="timeslot_id" id="timeslot-id" value="">
			<p>
				<label for="timeslot-name"><?php esc_html_e( 'Name', 'bkx-sliding-pricing' ); ?></label>
				<input type="text" name="name" id="timeslot-name" required>
			</p>
			<p>
				<label for="timeslot-day"><?php esc_html_e( 'Days', 'bkx-sliding-pricing' ); ?></label>
				<select name="day_of_week" id="timeslot-day">
					<?php foreach ( $timeslots->get_days() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="timeslot-start"><?php esc_html_e( 'Start Time', 'bkx-sliding-pricing' ); ?></label>
					<input type="time" name="start_time" id="timeslot-start" required>
				</div>
				<div class="bkx-form-field">
					<label for="timeslot-end"><?php esc_html_e( 'End Time', 'bkx-sliding-pricing' ); ?></label>
					<input type="time" name="end_time" id="timeslot-end" required>
				</div>
			</div>
			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="timeslot-adjustment-type"><?php esc_html_e( 'Adjustment', 'bkx-sliding-pricing' ); ?></label>
					<select name="adjustment_type" id="timeslot-adjustment-type">
						<option value="percentage"><?php esc_html_e( 'Percentage', 'bkx-sliding-pricing' ); ?></option>
						<option value="fixed"><?php esc_html_e( 'Fixed Amount', 'bkx-sliding-pricing' ); ?></option>
					</select>
				</div>
				<div class="bkx-form-field">
					<label for="timeslot-adjustment-value"><?php esc_html_e( 'Value', 'bkx-sliding-pricing' ); ?></label>
					<input type="number" name="adjustment_value" id="timeslot-adjustment-value" step="0.01" required>
					<p class="description"><?php esc_html_e( 'Use negative for off-peak discount', 'bkx-sliding-pricing' ); ?></p>
				</div>
			</div>
			<p>
				<label>
					<input type="checkbox" name="is_active" id="timeslot-active" value="1" checked>
					<?php esc_html_e( 'Active', 'bkx-sliding-pricing' ); ?>
				</label>
			</p>
			<p class="bkx-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Time Slot', 'bkx-sliding-pricing' ); ?></button>
				<button type="button" class="button bkx-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-sliding-pricing' ); ?></button>
			</p>
		</form>
	</div>
</div>

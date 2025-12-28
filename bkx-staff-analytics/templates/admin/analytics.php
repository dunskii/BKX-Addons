<?php
/**
 * Staff Analytics Admin Template.
 *
 * @package BookingX\StaffAnalytics
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get services.
$addon        = \BookingX\StaffAnalytics\StaffAnalyticsAddon::get_instance();
$leaderboard  = $addon->get_service( 'leaderboard' );
$goals        = $addon->get_service( 'goals' );
$reviews      = $addon->get_service( 'reviews' );
$time         = $addon->get_service( 'time' );

// Get all staff.
$staff_members = get_posts(
	array(
		'post_type'      => 'bkx_seat',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);

// Get current tab.
$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
$tabs        = array(
	'dashboard'   => __( 'Dashboard', 'bkx-staff-analytics' ),
	'leaderboard' => __( 'Leaderboard', 'bkx-staff-analytics' ),
	'goals'       => __( 'Goals', 'bkx-staff-analytics' ),
	'reviews'     => __( 'Reviews', 'bkx-staff-analytics' ),
	'time'        => __( 'Time Tracking', 'bkx-staff-analytics' ),
	'reports'     => __( 'Reports', 'bkx-staff-analytics' ),
);

// Get initial data.
$top_performers   = $leaderboard->get_top_performers( 'month' );
$goals_at_risk    = $goals->get_goals_at_risk();
$pending_reviews  = $reviews->get_pending_reviews( array( 'limit' => 5 ) );
$clocked_in_staff = $time->get_clocked_in_staff();
?>
<div class="wrap bkx-staff-wrap">
	<h1><?php esc_html_e( 'Staff Performance Analytics', 'bkx-staff-analytics' ); ?></h1>

	<nav class="nav-tab-wrapper bkx-staff-nav">
		<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-staff-content">

		<?php if ( 'dashboard' === $current_tab ) : ?>
			<!-- Dashboard Tab -->
			<div class="bkx-staff-dashboard">
				<!-- Quick Stats -->
				<div class="bkx-staff-row">
					<div class="bkx-staff-col-3">
						<div class="bkx-staff-card bkx-staff-stat">
							<span class="bkx-stat-icon dashicons dashicons-groups"></span>
							<div class="bkx-stat-content">
								<span class="bkx-stat-value"><?php echo count( $staff_members ); ?></span>
								<span class="bkx-stat-label"><?php esc_html_e( 'Staff Members', 'bkx-staff-analytics' ); ?></span>
							</div>
						</div>
					</div>
					<div class="bkx-staff-col-3">
						<div class="bkx-staff-card bkx-staff-stat">
							<span class="bkx-stat-icon dashicons dashicons-clock"></span>
							<div class="bkx-stat-content">
								<span class="bkx-stat-value"><?php echo count( $clocked_in_staff ); ?></span>
								<span class="bkx-stat-label"><?php esc_html_e( 'Currently Working', 'bkx-staff-analytics' ); ?></span>
							</div>
						</div>
					</div>
					<div class="bkx-staff-col-3">
						<div class="bkx-staff-card bkx-staff-stat">
							<span class="bkx-stat-icon dashicons dashicons-flag"></span>
							<div class="bkx-stat-content">
								<span class="bkx-stat-value"><?php echo count( $goals_at_risk ); ?></span>
								<span class="bkx-stat-label"><?php esc_html_e( 'Goals at Risk', 'bkx-staff-analytics' ); ?></span>
							</div>
						</div>
					</div>
					<div class="bkx-staff-col-3">
						<div class="bkx-staff-card bkx-staff-stat">
							<span class="bkx-stat-icon dashicons dashicons-star-filled"></span>
							<div class="bkx-stat-content">
								<span class="bkx-stat-value"><?php echo count( $pending_reviews ); ?></span>
								<span class="bkx-stat-label"><?php esc_html_e( 'Pending Reviews', 'bkx-staff-analytics' ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<!-- Top Performers -->
				<div class="bkx-staff-row">
					<div class="bkx-staff-col-6">
						<div class="bkx-staff-card">
							<h2><?php esc_html_e( 'Top Revenue Generators', 'bkx-staff-analytics' ); ?></h2>
							<ul class="bkx-top-list">
								<?php foreach ( $top_performers['revenue']['rankings'] as $staff ) : ?>
									<li>
										<span class="bkx-rank">#<?php echo esc_html( $staff['rank'] ); ?></span>
										<span class="bkx-name"><?php echo esc_html( $staff['staff_name'] ); ?></span>
										<span class="bkx-value">$<?php echo number_format( $staff['total_revenue'], 2 ); ?></span>
									</li>
								<?php endforeach; ?>
								<?php if ( empty( $top_performers['revenue']['rankings'] ) ) : ?>
									<li class="bkx-empty"><?php esc_html_e( 'No data available', 'bkx-staff-analytics' ); ?></li>
								<?php endif; ?>
							</ul>
						</div>
					</div>
					<div class="bkx-staff-col-6">
						<div class="bkx-staff-card">
							<h2><?php esc_html_e( 'Highest Rated Staff', 'bkx-staff-analytics' ); ?></h2>
							<ul class="bkx-top-list">
								<?php foreach ( $top_performers['rating']['rankings'] as $staff ) : ?>
									<li>
										<span class="bkx-rank">#<?php echo esc_html( $staff['rank'] ); ?></span>
										<span class="bkx-name"><?php echo esc_html( $staff['staff_name'] ); ?></span>
										<span class="bkx-value">
											<?php echo esc_html( $staff['avg_rating'] ); ?>/5
											<span class="bkx-stars"><?php echo str_repeat( '★', round( $staff['avg_rating'] ) ); ?></span>
										</span>
									</li>
								<?php endforeach; ?>
								<?php if ( empty( $top_performers['rating']['rankings'] ) ) : ?>
									<li class="bkx-empty"><?php esc_html_e( 'No data available', 'bkx-staff-analytics' ); ?></li>
								<?php endif; ?>
							</ul>
						</div>
					</div>
				</div>

				<!-- Staff Performance Chart -->
				<div class="bkx-staff-card">
					<div class="bkx-card-header">
						<h2><?php esc_html_e( 'Staff Performance Overview', 'bkx-staff-analytics' ); ?></h2>
						<div class="bkx-card-actions">
							<select id="bkx-chart-staff">
								<option value="0"><?php esc_html_e( 'All Staff', 'bkx-staff-analytics' ); ?></option>
								<?php foreach ( $staff_members as $staff ) : ?>
									<option value="<?php echo esc_attr( $staff->ID ); ?>">
										<?php echo esc_html( $staff->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<select id="bkx-chart-period">
								<option value="week"><?php esc_html_e( 'This Week', 'bkx-staff-analytics' ); ?></option>
								<option value="month" selected><?php esc_html_e( 'This Month', 'bkx-staff-analytics' ); ?></option>
								<option value="quarter"><?php esc_html_e( 'This Quarter', 'bkx-staff-analytics' ); ?></option>
								<option value="year"><?php esc_html_e( 'This Year', 'bkx-staff-analytics' ); ?></option>
							</select>
						</div>
					</div>
					<div class="bkx-chart-container">
						<canvas id="bkx-performance-chart"></canvas>
					</div>
				</div>

				<!-- Goals at Risk -->
				<?php if ( ! empty( $goals_at_risk ) ) : ?>
					<div class="bkx-staff-card bkx-warning-card">
						<h2><?php esc_html_e( 'Goals at Risk', 'bkx-staff-analytics' ); ?></h2>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Staff', 'bkx-staff-analytics' ); ?></th>
									<th><?php esc_html_e( 'Goal', 'bkx-staff-analytics' ); ?></th>
									<th><?php esc_html_e( 'Progress', 'bkx-staff-analytics' ); ?></th>
									<th><?php esc_html_e( 'Days Left', 'bkx-staff-analytics' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $goals_at_risk as $goal ) : ?>
									<tr>
										<td><?php echo esc_html( $goal['staff_name'] ); ?></td>
										<td><?php echo esc_html( $goal['type_label'] ); ?>: <?php echo esc_html( $goal['target_value'] ); ?></td>
										<td>
											<div class="bkx-progress-bar">
												<div class="bkx-progress-fill" style="width: <?php echo esc_attr( $goal['progress']['percentage'] ); ?>%"></div>
											</div>
											<span class="bkx-progress-text"><?php echo esc_html( $goal['progress']['percentage'] ); ?>%</span>
										</td>
										<td><?php echo esc_html( $goal['progress']['days_remaining'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>

		<?php elseif ( 'leaderboard' === $current_tab ) : ?>
			<!-- Leaderboard Tab -->
			<div class="bkx-staff-leaderboard">
				<div class="bkx-card-header">
					<h2><?php esc_html_e( 'Staff Leaderboard', 'bkx-staff-analytics' ); ?></h2>
					<div class="bkx-card-actions">
						<select id="bkx-lb-metric">
							<option value="revenue"><?php esc_html_e( 'Revenue', 'bkx-staff-analytics' ); ?></option>
							<option value="bookings"><?php esc_html_e( 'Bookings', 'bkx-staff-analytics' ); ?></option>
							<option value="rating"><?php esc_html_e( 'Rating', 'bkx-staff-analytics' ); ?></option>
							<option value="hours"><?php esc_html_e( 'Hours Worked', 'bkx-staff-analytics' ); ?></option>
							<option value="new_customers"><?php esc_html_e( 'New Customers', 'bkx-staff-analytics' ); ?></option>
						</select>
						<select id="bkx-lb-period">
							<option value="week"><?php esc_html_e( 'This Week', 'bkx-staff-analytics' ); ?></option>
							<option value="month" selected><?php esc_html_e( 'This Month', 'bkx-staff-analytics' ); ?></option>
							<option value="quarter"><?php esc_html_e( 'This Quarter', 'bkx-staff-analytics' ); ?></option>
							<option value="year"><?php esc_html_e( 'This Year', 'bkx-staff-analytics' ); ?></option>
						</select>
					</div>
				</div>

				<div id="bkx-leaderboard-container" class="bkx-staff-card">
					<div class="bkx-loading"><?php esc_html_e( 'Loading...', 'bkx-staff-analytics' ); ?></div>
				</div>
			</div>

		<?php elseif ( 'goals' === $current_tab ) : ?>
			<!-- Goals Tab -->
			<div class="bkx-staff-goals">
				<div class="bkx-card-header">
					<h2><?php esc_html_e( 'Staff Goals', 'bkx-staff-analytics' ); ?></h2>
					<button type="button" class="button button-primary" id="bkx-add-goal">
						<?php esc_html_e( 'Add New Goal', 'bkx-staff-analytics' ); ?>
					</button>
				</div>

				<div class="bkx-staff-card">
					<table class="wp-list-table widefat fixed striped" id="bkx-goals-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Staff', 'bkx-staff-analytics' ); ?></th>
								<th><?php esc_html_e( 'Goal Type', 'bkx-staff-analytics' ); ?></th>
								<th><?php esc_html_e( 'Target', 'bkx-staff-analytics' ); ?></th>
								<th><?php esc_html_e( 'Progress', 'bkx-staff-analytics' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-staff-analytics' ); ?></th>
								<th><?php esc_html_e( 'End Date', 'bkx-staff-analytics' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bkx-staff-analytics' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$all_goals = $goals->get_all_goals( array( 'limit' => 50 ) );
							foreach ( $all_goals as $goal ) :
								$status_class = '';
								switch ( $goal['progress']['status'] ) {
									case 'achieved':
										$status_class = 'bkx-status-achieved';
										break;
									case 'ahead':
										$status_class = 'bkx-status-ahead';
										break;
									case 'behind':
										$status_class = 'bkx-status-behind';
										break;
								}
								?>
								<tr data-goal-id="<?php echo esc_attr( $goal['id'] ); ?>">
									<td><?php echo esc_html( $goal['staff_name'] ); ?></td>
									<td><?php echo esc_html( $goal['type_label'] ); ?></td>
									<td><?php echo esc_html( $goal['target_value'] ); ?></td>
									<td>
										<div class="bkx-progress-bar">
											<div class="bkx-progress-fill <?php echo esc_attr( $status_class ); ?>"
												 style="width: <?php echo esc_attr( $goal['progress']['percentage'] ); ?>%"></div>
										</div>
										<span class="bkx-progress-text"><?php echo esc_html( $goal['progress']['percentage'] ); ?>%</span>
									</td>
									<td>
										<span class="bkx-status <?php echo esc_attr( $status_class ); ?>">
											<?php echo esc_html( ucfirst( str_replace( '_', ' ', $goal['progress']['status'] ) ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $goal['end_date'] ); ?></td>
									<td>
										<button type="button" class="button bkx-edit-goal" data-id="<?php echo esc_attr( $goal['id'] ); ?>">
											<?php esc_html_e( 'Edit', 'bkx-staff-analytics' ); ?>
										</button>
										<button type="button" class="button bkx-delete-goal" data-id="<?php echo esc_attr( $goal['id'] ); ?>">
											<?php esc_html_e( 'Delete', 'bkx-staff-analytics' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if ( empty( $all_goals ) ) : ?>
								<tr><td colspan="7" class="bkx-empty"><?php esc_html_e( 'No goals set yet', 'bkx-staff-analytics' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Goal Modal -->
			<div id="bkx-goal-modal" class="bkx-modal" style="display: none;">
				<div class="bkx-modal-content">
					<span class="bkx-modal-close">&times;</span>
					<h2 id="bkx-goal-modal-title"><?php esc_html_e( 'Add Goal', 'bkx-staff-analytics' ); ?></h2>
					<form id="bkx-goal-form">
						<input type="hidden" name="goal_id" id="goal-id" value="">
						<p>
							<label for="goal-staff"><?php esc_html_e( 'Staff Member', 'bkx-staff-analytics' ); ?></label>
							<select name="staff_id" id="goal-staff" required>
								<option value=""><?php esc_html_e( 'Select Staff', 'bkx-staff-analytics' ); ?></option>
								<?php foreach ( $staff_members as $staff ) : ?>
									<option value="<?php echo esc_attr( $staff->ID ); ?>">
										<?php echo esc_html( $staff->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</p>
						<p>
							<label for="goal-type"><?php esc_html_e( 'Goal Type', 'bkx-staff-analytics' ); ?></label>
							<select name="goal_type" id="goal-type" required>
								<option value="revenue"><?php esc_html_e( 'Revenue Target', 'bkx-staff-analytics' ); ?></option>
								<option value="bookings"><?php esc_html_e( 'Booking Count', 'bkx-staff-analytics' ); ?></option>
								<option value="hours"><?php esc_html_e( 'Billable Hours', 'bkx-staff-analytics' ); ?></option>
								<option value="rating"><?php esc_html_e( 'Average Rating', 'bkx-staff-analytics' ); ?></option>
								<option value="new_customers"><?php esc_html_e( 'New Customers', 'bkx-staff-analytics' ); ?></option>
								<option value="completion"><?php esc_html_e( 'Completion Rate', 'bkx-staff-analytics' ); ?></option>
							</select>
						</p>
						<p>
							<label for="goal-target"><?php esc_html_e( 'Target Value', 'bkx-staff-analytics' ); ?></label>
							<input type="number" name="target_value" id="goal-target" step="0.01" min="0" required>
						</p>
						<p>
							<label for="goal-start"><?php esc_html_e( 'Start Date', 'bkx-staff-analytics' ); ?></label>
							<input type="date" name="start_date" id="goal-start" required>
						</p>
						<p>
							<label for="goal-end"><?php esc_html_e( 'End Date', 'bkx-staff-analytics' ); ?></label>
							<input type="date" name="end_date" id="goal-end" required>
						</p>
						<p class="bkx-form-actions">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Goal', 'bkx-staff-analytics' ); ?></button>
							<button type="button" class="button bkx-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-staff-analytics' ); ?></button>
						</p>
					</form>
				</div>
			</div>

		<?php elseif ( 'reviews' === $current_tab ) : ?>
			<!-- Reviews Tab -->
			<div class="bkx-staff-reviews">
				<div class="bkx-staff-row">
					<div class="bkx-staff-col-8">
						<div class="bkx-staff-card">
							<h2><?php esc_html_e( 'Pending Reviews', 'bkx-staff-analytics' ); ?></h2>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Staff', 'bkx-staff-analytics' ); ?></th>
										<th><?php esc_html_e( 'Customer', 'bkx-staff-analytics' ); ?></th>
										<th><?php esc_html_e( 'Rating', 'bkx-staff-analytics' ); ?></th>
										<th><?php esc_html_e( 'Review', 'bkx-staff-analytics' ); ?></th>
										<th><?php esc_html_e( 'Date', 'bkx-staff-analytics' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'bkx-staff-analytics' ); ?></th>
									</tr>
								</thead>
								<tbody id="bkx-pending-reviews">
									<?php
									$all_pending = $reviews->get_pending_reviews( array( 'limit' => 20 ) );
									foreach ( $all_pending as $review ) :
										?>
										<tr data-review-id="<?php echo esc_attr( $review['id'] ); ?>">
											<td><?php echo esc_html( $review['staff_name'] ); ?></td>
											<td><?php echo esc_html( $review['customer_name'] ); ?></td>
											<td>
												<span class="bkx-stars">
													<?php echo str_repeat( '★', $review['rating'] ); ?><?php echo str_repeat( '☆', 5 - $review['rating'] ); ?>
												</span>
											</td>
											<td><?php echo esc_html( wp_trim_words( $review['review_text'], 15 ) ); ?></td>
											<td><?php echo esc_html( gmdate( 'M j, Y', strtotime( $review['reviewed_at'] ) ) ); ?></td>
											<td>
												<button type="button" class="button button-small bkx-approve-review" data-id="<?php echo esc_attr( $review['id'] ); ?>">
													<?php esc_html_e( 'Approve', 'bkx-staff-analytics' ); ?>
												</button>
												<button type="button" class="button button-small bkx-reject-review" data-id="<?php echo esc_attr( $review['id'] ); ?>">
													<?php esc_html_e( 'Reject', 'bkx-staff-analytics' ); ?>
												</button>
											</td>
										</tr>
									<?php endforeach; ?>
									<?php if ( empty( $all_pending ) ) : ?>
										<tr><td colspan="6" class="bkx-empty"><?php esc_html_e( 'No pending reviews', 'bkx-staff-analytics' ); ?></td></tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
					<div class="bkx-staff-col-4">
						<div class="bkx-staff-card">
							<h2><?php esc_html_e( 'Recent Approved Reviews', 'bkx-staff-analytics' ); ?></h2>
							<ul class="bkx-review-list">
								<?php
								$recent_reviews = $reviews->get_recent_reviews( 5 );
								foreach ( $recent_reviews as $review ) :
									?>
									<li>
										<div class="bkx-review-header">
											<strong><?php echo esc_html( $review['staff_name'] ); ?></strong>
											<span class="bkx-stars"><?php echo str_repeat( '★', $review['rating'] ); ?></span>
										</div>
										<p class="bkx-review-text"><?php echo esc_html( wp_trim_words( $review['review_text'], 20 ) ); ?></p>
										<span class="bkx-review-meta">
											<?php echo esc_html( $review['customer_name'] ); ?> -
											<?php echo esc_html( gmdate( 'M j', strtotime( $review['reviewed_at'] ) ) ); ?>
										</span>
									</li>
								<?php endforeach; ?>
								<?php if ( empty( $recent_reviews ) ) : ?>
									<li class="bkx-empty"><?php esc_html_e( 'No reviews yet', 'bkx-staff-analytics' ); ?></li>
								<?php endif; ?>
							</ul>
						</div>
					</div>
				</div>
			</div>

		<?php elseif ( 'time' === $current_tab ) : ?>
			<!-- Time Tracking Tab -->
			<div class="bkx-staff-time">
				<div class="bkx-staff-row">
					<div class="bkx-staff-col-4">
						<div class="bkx-staff-card">
							<h2><?php esc_html_e( 'Currently Working', 'bkx-staff-analytics' ); ?></h2>
							<ul class="bkx-working-list">
								<?php foreach ( $clocked_in_staff as $staff ) : ?>
									<li>
										<span class="bkx-working-name"><?php echo esc_html( $staff['staff_name'] ); ?></span>
										<span class="bkx-working-time">
											<?php
											$hours   = floor( $staff['minutes_elapsed'] / 60 );
											$minutes = $staff['minutes_elapsed'] % 60;
											printf( '%dh %dm', $hours, $minutes );
											?>
										</span>
									</li>
								<?php endforeach; ?>
								<?php if ( empty( $clocked_in_staff ) ) : ?>
									<li class="bkx-empty"><?php esc_html_e( 'No one currently working', 'bkx-staff-analytics' ); ?></li>
								<?php endif; ?>
							</ul>
						</div>
					</div>
					<div class="bkx-staff-col-8">
						<div class="bkx-staff-card">
							<div class="bkx-card-header">
								<h2><?php esc_html_e( 'Time Logs', 'bkx-staff-analytics' ); ?></h2>
								<div class="bkx-card-actions">
									<select id="bkx-time-staff">
										<option value="0"><?php esc_html_e( 'All Staff', 'bkx-staff-analytics' ); ?></option>
										<?php foreach ( $staff_members as $staff ) : ?>
											<option value="<?php echo esc_attr( $staff->ID ); ?>">
												<?php echo esc_html( $staff->post_title ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<input type="date" id="bkx-time-start" value="<?php echo esc_attr( gmdate( 'Y-m-01' ) ); ?>">
									<input type="date" id="bkx-time-end" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
									<button type="button" class="button" id="bkx-filter-time"><?php esc_html_e( 'Filter', 'bkx-staff-analytics' ); ?></button>
								</div>
							</div>
							<table class="wp-list-table widefat fixed striped" id="bkx-time-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Staff', 'bkx-staff-analytics' ); ?></th>
										<th><?php esc_html_e( 'Date', 'bkx-staff-analytics' ); ?></th>
										<th><?php esc_html_e( 'Clock In', 'bkx-staff-analytics' ); ?></th>
										<th><?php esc_html_e( 'Clock Out', 'bkx-staff-analytics' ); ?></th>
										<th><?php esc_html_e( 'Break', 'bkx-staff-analytics' ); ?></th>
										<th><?php esc_html_e( 'Total Hours', 'bkx-staff-analytics' ); ?></th>
									</tr>
								</thead>
								<tbody id="bkx-time-logs">
									<?php
									$time_logs = $time->get_time_logs( 0, array( 'limit' => 30 ) );
									foreach ( $time_logs as $log ) :
										?>
										<tr>
											<td><?php echo esc_html( $log['staff_name'] ); ?></td>
											<td><?php echo esc_html( gmdate( 'M j, Y', strtotime( $log['log_date'] ) ) ); ?></td>
											<td><?php echo esc_html( gmdate( 'g:i A', strtotime( $log['clock_in'] ) ) ); ?></td>
											<td><?php echo $log['clock_out'] ? esc_html( gmdate( 'g:i A', strtotime( $log['clock_out'] ) ) ) : '-'; ?></td>
											<td><?php echo esc_html( $log['break_minutes'] ); ?> min</td>
											<td><?php echo $log['total_hours'] ? esc_html( $log['total_hours'] ) : '-'; ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

		<?php elseif ( 'reports' === $current_tab ) : ?>
			<!-- Reports Tab -->
			<div class="bkx-staff-reports">
				<div class="bkx-staff-card">
					<h2><?php esc_html_e( 'Generate Report', 'bkx-staff-analytics' ); ?></h2>
					<form id="bkx-export-form">
						<div class="bkx-form-row">
							<div class="bkx-form-field">
								<label for="export-staff"><?php esc_html_e( 'Staff Member', 'bkx-staff-analytics' ); ?></label>
								<select name="staff_id" id="export-staff">
									<option value="0"><?php esc_html_e( 'All Staff', 'bkx-staff-analytics' ); ?></option>
									<?php foreach ( $staff_members as $staff ) : ?>
										<option value="<?php echo esc_attr( $staff->ID ); ?>">
											<?php echo esc_html( $staff->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="bkx-form-field">
								<label for="export-type"><?php esc_html_e( 'Report Type', 'bkx-staff-analytics' ); ?></label>
								<select name="report_type" id="export-type">
									<option value="performance"><?php esc_html_e( 'Performance Report', 'bkx-staff-analytics' ); ?></option>
									<option value="goals"><?php esc_html_e( 'Goals Report', 'bkx-staff-analytics' ); ?></option>
									<option value="reviews"><?php esc_html_e( 'Reviews Report', 'bkx-staff-analytics' ); ?></option>
									<option value="time"><?php esc_html_e( 'Time Tracking Report', 'bkx-staff-analytics' ); ?></option>
									<option value="comprehensive"><?php esc_html_e( 'Comprehensive Report', 'bkx-staff-analytics' ); ?></option>
								</select>
							</div>
							<div class="bkx-form-field">
								<label for="export-format"><?php esc_html_e( 'Format', 'bkx-staff-analytics' ); ?></label>
								<select name="format" id="export-format">
									<option value="csv"><?php esc_html_e( 'CSV', 'bkx-staff-analytics' ); ?></option>
									<option value="pdf"><?php esc_html_e( 'PDF (HTML)', 'bkx-staff-analytics' ); ?></option>
								</select>
							</div>
						</div>
						<div class="bkx-form-row">
							<div class="bkx-form-field">
								<label for="export-start"><?php esc_html_e( 'Start Date', 'bkx-staff-analytics' ); ?></label>
								<input type="date" name="start_date" id="export-start" value="<?php echo esc_attr( gmdate( 'Y-m-01' ) ); ?>">
							</div>
							<div class="bkx-form-field">
								<label for="export-end"><?php esc_html_e( 'End Date', 'bkx-staff-analytics' ); ?></label>
								<input type="date" name="end_date" id="export-end" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
							</div>
							<div class="bkx-form-field bkx-form-submit">
								<button type="submit" class="button button-primary button-hero">
									<?php esc_html_e( 'Generate Report', 'bkx-staff-analytics' ); ?>
								</button>
							</div>
						</div>
					</form>
				</div>
			</div>

		<?php endif; ?>

	</div>
</div>

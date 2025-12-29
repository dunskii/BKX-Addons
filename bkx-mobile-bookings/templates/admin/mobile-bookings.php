<?php
/**
 * Mobile Bookings Admin Template.
 *
 * @package BookingX\MobileBookings
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings      = get_option( 'bkx_mobile_bookings_settings', array() );
$has_api_key   = ! empty( $settings['google_maps_api_key'] );
$active_tab    = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview';

// Get service areas.
global $wpdb;
$service_areas = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}bkx_service_areas ORDER BY name ASC",
	ARRAY_A
);

// Get services for dropdown.
$services = get_posts(
	array(
		'post_type'      => 'bkx_base',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);

// Get providers (seats) for dropdown.
$providers = get_posts(
	array(
		'post_type'      => 'bkx_seat',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
?>

<div class="wrap bkx-mobile-wrap">
	<h1><?php esc_html_e( 'Mobile Bookings Advanced', 'bkx-mobile-bookings' ); ?></h1>

	<?php if ( ! $has_api_key ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Google Maps API Key Required', 'bkx-mobile-bookings' ); ?></strong><br>
				<?php esc_html_e( 'Please configure your Google Maps API key in the Settings tab to enable location features.', 'bkx-mobile-bookings' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper bkx-nav-tabs">
		<a href="#overview" class="nav-tab <?php echo 'overview' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Overview', 'bkx-mobile-bookings' ); ?>
		</a>
		<a href="#service-areas" class="nav-tab <?php echo 'service-areas' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Service Areas', 'bkx-mobile-bookings' ); ?>
		</a>
		<a href="#routes" class="nav-tab <?php echo 'routes' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Route Optimization', 'bkx-mobile-bookings' ); ?>
		</a>
		<a href="#checkins" class="nav-tab <?php echo 'checkins' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'GPS Check-ins', 'bkx-mobile-bookings' ); ?>
		</a>
		<a href="#settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-mobile-bookings' ); ?>
		</a>
	</nav>

	<!-- Overview Tab -->
	<div id="overview" class="bkx-tab-content <?php echo 'overview' === $active_tab ? 'active' : ''; ?>">
		<div class="bkx-dashboard-grid">
			<!-- Stats Cards -->
			<div class="bkx-stats-row">
				<div class="bkx-stat-card">
					<div class="bkx-stat-icon"><span class="dashicons dashicons-location"></span></div>
					<div class="bkx-stat-content">
						<span class="bkx-stat-value"><?php echo count( $service_areas ); ?></span>
						<span class="bkx-stat-label"><?php esc_html_e( 'Service Areas', 'bkx-mobile-bookings' ); ?></span>
					</div>
				</div>

				<div class="bkx-stat-card">
					<div class="bkx-stat-icon"><span class="dashicons dashicons-admin-users"></span></div>
					<div class="bkx-stat-content">
						<span class="bkx-stat-value"><?php echo count( $providers ); ?></span>
						<span class="bkx-stat-label"><?php esc_html_e( 'Providers', 'bkx-mobile-bookings' ); ?></span>
					</div>
				</div>

				<div class="bkx-stat-card">
					<div class="bkx-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
					<div class="bkx-stat-content">
						<span class="bkx-stat-value" id="today-checkins">0</span>
						<span class="bkx-stat-label"><?php esc_html_e( 'Check-ins Today', 'bkx-mobile-bookings' ); ?></span>
					</div>
				</div>

				<div class="bkx-stat-card">
					<div class="bkx-stat-icon"><span class="dashicons dashicons-car"></span></div>
					<div class="bkx-stat-content">
						<span class="bkx-stat-value" id="total-distance">0</span>
						<span class="bkx-stat-label"><?php esc_html_e( 'Miles Today', 'bkx-mobile-bookings' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Coverage Map -->
			<div class="bkx-card bkx-map-card">
				<h2><?php esc_html_e( 'Service Coverage Map', 'bkx-mobile-bookings' ); ?></h2>
				<?php if ( $has_api_key ) : ?>
					<div id="bkx-coverage-map" class="bkx-map-container"></div>
				<?php else : ?>
					<div class="bkx-map-placeholder">
						<span class="dashicons dashicons-location-alt"></span>
						<p><?php esc_html_e( 'Configure Google Maps API key to view coverage map.', 'bkx-mobile-bookings' ); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<!-- Quick Actions -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'Quick Actions', 'bkx-mobile-bookings' ); ?></h2>
				<div class="bkx-quick-actions">
					<a href="#service-areas" class="button bkx-action-btn">
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Add Service Area', 'bkx-mobile-bookings' ); ?>
					</a>
					<a href="#routes" class="button bkx-action-btn">
						<span class="dashicons dashicons-randomize"></span>
						<?php esc_html_e( 'Optimize Routes', 'bkx-mobile-bookings' ); ?>
					</a>
					<button type="button" class="button bkx-action-btn" id="bkx-test-api">
						<span class="dashicons dashicons-admin-site"></span>
						<?php esc_html_e( 'Test API Connection', 'bkx-mobile-bookings' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Service Areas Tab -->
	<div id="service-areas" class="bkx-tab-content <?php echo 'service-areas' === $active_tab ? 'active' : ''; ?>">
		<div class="bkx-card">
			<div class="bkx-card-header">
				<h2><?php esc_html_e( 'Service Areas', 'bkx-mobile-bookings' ); ?></h2>
				<button type="button" class="button button-primary bkx-add-area">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Service Area', 'bkx-mobile-bookings' ); ?>
				</button>
			</div>

			<?php if ( $has_api_key ) : ?>
				<div id="bkx-areas-map" class="bkx-map-container bkx-map-medium"></div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped" id="bkx-areas-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'bkx-mobile-bookings' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-mobile-bookings' ); ?></th>
						<th><?php esc_html_e( 'Coverage', 'bkx-mobile-bookings' ); ?></th>
						<th><?php esc_html_e( 'Distance Pricing', 'bkx-mobile-bookings' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-mobile-bookings' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-mobile-bookings' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $service_areas ) ) : ?>
						<tr>
							<td colspan="6" class="bkx-empty"><?php esc_html_e( 'No service areas defined yet.', 'bkx-mobile-bookings' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $service_areas as $area ) : ?>
							<tr data-id="<?php echo esc_attr( $area['id'] ); ?>">
								<td><strong><?php echo esc_html( $area['name'] ); ?></strong></td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $area['area_type'] ) ) ); ?></td>
								<td>
									<?php
									if ( 'radius' === $area['area_type'] ) {
										echo esc_html( $area['radius_miles'] . ' mi radius' );
									} elseif ( 'zip_codes' === $area['area_type'] ) {
										$zips = explode( ',', $area['zip_codes'] );
										echo esc_html( count( $zips ) . ' zip codes' );
									} else {
										echo esc_html( ucfirst( $area['area_type'] ) );
									}
									?>
								</td>
								<td>
									<?php if ( $area['distance_pricing_enabled'] ) : ?>
										<span class="bkx-badge enabled"><?php esc_html_e( 'Enabled', 'bkx-mobile-bookings' ); ?></span>
									<?php else : ?>
										<span class="bkx-badge disabled"><?php esc_html_e( 'Disabled', 'bkx-mobile-bookings' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<span class="bkx-status <?php echo 'active' === $area['status'] ? 'active' : 'inactive'; ?>">
										<?php echo esc_html( ucfirst( $area['status'] ) ); ?>
									</span>
								</td>
								<td>
									<button type="button" class="button button-small bkx-edit-area" data-id="<?php echo esc_attr( $area['id'] ); ?>">
										<?php esc_html_e( 'Edit', 'bkx-mobile-bookings' ); ?>
									</button>
									<button type="button" class="button button-small bkx-delete-area" data-id="<?php echo esc_attr( $area['id'] ); ?>">
										<?php esc_html_e( 'Delete', 'bkx-mobile-bookings' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Routes Tab -->
	<div id="routes" class="bkx-tab-content <?php echo 'routes' === $active_tab ? 'active' : ''; ?>">
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Route Optimization', 'bkx-mobile-bookings' ); ?></h2>

			<div class="bkx-route-controls">
				<div class="bkx-form-row">
					<div class="bkx-form-field">
						<label for="route_provider"><?php esc_html_e( 'Provider', 'bkx-mobile-bookings' ); ?></label>
						<select id="route_provider" name="route_provider">
							<option value=""><?php esc_html_e( 'Select Provider', 'bkx-mobile-bookings' ); ?></option>
							<?php foreach ( $providers as $provider ) : ?>
								<option value="<?php echo esc_attr( $provider->ID ); ?>">
									<?php echo esc_html( $provider->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="bkx-form-field">
						<label for="route_date"><?php esc_html_e( 'Date', 'bkx-mobile-bookings' ); ?></label>
						<input type="date" id="route_date" name="route_date" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" />
					</div>

					<div class="bkx-form-field bkx-form-actions-inline">
						<button type="button" class="button" id="bkx-load-route">
							<?php esc_html_e( 'Load Route', 'bkx-mobile-bookings' ); ?>
						</button>
						<button type="button" class="button button-primary" id="bkx-optimize-route">
							<?php esc_html_e( 'Optimize Route', 'bkx-mobile-bookings' ); ?>
						</button>
					</div>
				</div>
			</div>

			<?php if ( $has_api_key ) : ?>
				<div id="bkx-route-map" class="bkx-map-container bkx-map-large"></div>
			<?php endif; ?>

			<div id="bkx-route-details" class="bkx-route-details" style="display:none;">
				<h3><?php esc_html_e( 'Route Summary', 'bkx-mobile-bookings' ); ?></h3>
				<div class="bkx-route-summary">
					<div class="bkx-summary-item">
						<span class="label"><?php esc_html_e( 'Total Bookings:', 'bkx-mobile-bookings' ); ?></span>
						<span class="value" id="route-bookings">0</span>
					</div>
					<div class="bkx-summary-item">
						<span class="label"><?php esc_html_e( 'Total Distance:', 'bkx-mobile-bookings' ); ?></span>
						<span class="value" id="route-distance">0 mi</span>
					</div>
					<div class="bkx-summary-item">
						<span class="label"><?php esc_html_e( 'Travel Time:', 'bkx-mobile-bookings' ); ?></span>
						<span class="value" id="route-time">0 min</span>
					</div>
				</div>

				<div id="bkx-route-stops" class="bkx-route-stops"></div>

				<div class="bkx-export-buttons">
					<button type="button" class="button" data-app="google_maps" id="export-google">
						<span class="dashicons dashicons-location"></span>
						<?php esc_html_e( 'Open in Google Maps', 'bkx-mobile-bookings' ); ?>
					</button>
					<button type="button" class="button" data-app="waze" id="export-waze">
						<?php esc_html_e( 'Open in Waze', 'bkx-mobile-bookings' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- GPS Check-ins Tab -->
	<div id="checkins" class="bkx-tab-content <?php echo 'checkins' === $active_tab ? 'active' : ''; ?>">
		<div class="bkx-card">
			<h2><?php esc_html_e( 'GPS Check-in Log', 'bkx-mobile-bookings' ); ?></h2>

			<div class="bkx-checkin-filters">
				<select id="checkin_provider">
					<option value=""><?php esc_html_e( 'All Providers', 'bkx-mobile-bookings' ); ?></option>
					<?php foreach ( $providers as $provider ) : ?>
						<option value="<?php echo esc_attr( $provider->ID ); ?>">
							<?php echo esc_html( $provider->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<input type="date" id="checkin_date" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" />

				<button type="button" class="button" id="bkx-filter-checkins">
					<?php esc_html_e( 'Filter', 'bkx-mobile-bookings' ); ?>
				</button>
			</div>

			<table class="wp-list-table widefat fixed striped" id="bkx-checkins-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'bkx-mobile-bookings' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'bkx-mobile-bookings' ); ?></th>
						<th><?php esc_html_e( 'Booking', 'bkx-mobile-bookings' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-mobile-bookings' ); ?></th>
						<th><?php esc_html_e( 'Distance', 'bkx-mobile-bookings' ); ?></th>
						<th><?php esc_html_e( 'Verified', 'bkx-mobile-bookings' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td colspan="6" class="bkx-empty"><?php esc_html_e( 'No check-ins found.', 'bkx-mobile-bookings' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Settings Tab -->
	<div id="settings" class="bkx-tab-content <?php echo 'settings' === $active_tab ? 'active' : ''; ?>">
		<form id="bkx-mobile-settings-form" method="post">
			<input type="hidden" name="action" value="bkx_save_mobile_settings" />
			<?php wp_nonce_field( 'bkx_mobile_bookings_nonce', 'nonce' ); ?>

			<!-- Google Maps Settings -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'Google Maps Settings', 'bkx-mobile-bookings' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="google_maps_api_key"><?php esc_html_e( 'API Key', 'bkx-mobile-bookings' ); ?></label>
						</th>
						<td>
							<input type="text" id="google_maps_api_key" name="google_maps_api_key"
								value="<?php echo esc_attr( $settings['google_maps_api_key'] ?? '' ); ?>"
								class="regular-text" />
							<button type="button" class="button" id="bkx-test-api-key">
								<?php esc_html_e( 'Test Connection', 'bkx-mobile-bookings' ); ?>
							</button>
							<p class="description">
								<?php
								printf(
									/* translators: %s: Google Cloud Console link */
									esc_html__( 'Get your API key from the %s', 'bkx-mobile-bookings' ),
									'<a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Maps', 'bkx-mobile-bookings' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_maps" value="1"
									<?php checked( $settings['enable_maps'] ?? 1 ); ?> />
								<?php esc_html_e( 'Display interactive maps on booking forms', 'bkx-mobile-bookings' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="map_style"><?php esc_html_e( 'Map Style', 'bkx-mobile-bookings' ); ?></label>
						</th>
						<td>
							<select id="map_style" name="map_style">
								<option value="roadmap" <?php selected( $settings['map_style'] ?? 'roadmap', 'roadmap' ); ?>>
									<?php esc_html_e( 'Roadmap', 'bkx-mobile-bookings' ); ?>
								</option>
								<option value="satellite" <?php selected( $settings['map_style'] ?? '', 'satellite' ); ?>>
									<?php esc_html_e( 'Satellite', 'bkx-mobile-bookings' ); ?>
								</option>
								<option value="hybrid" <?php selected( $settings['map_style'] ?? '', 'hybrid' ); ?>>
									<?php esc_html_e( 'Hybrid', 'bkx-mobile-bookings' ); ?>
								</option>
								<option value="terrain" <?php selected( $settings['map_style'] ?? '', 'terrain' ); ?>>
									<?php esc_html_e( 'Terrain', 'bkx-mobile-bookings' ); ?>
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Traffic Layer', 'bkx-mobile-bookings' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_traffic_layer" value="1"
									<?php checked( $settings['enable_traffic_layer'] ?? 1 ); ?> />
								<?php esc_html_e( 'Show traffic conditions on maps', 'bkx-mobile-bookings' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<!-- Distance Settings -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'Distance & Travel Settings', 'bkx-mobile-bookings' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="distance_unit"><?php esc_html_e( 'Distance Unit', 'bkx-mobile-bookings' ); ?></label>
						</th>
						<td>
							<select id="distance_unit" name="distance_unit">
								<option value="miles" <?php selected( $settings['distance_unit'] ?? 'miles', 'miles' ); ?>>
									<?php esc_html_e( 'Miles', 'bkx-mobile-bookings' ); ?>
								</option>
								<option value="km" <?php selected( $settings['distance_unit'] ?? '', 'km' ); ?>>
									<?php esc_html_e( 'Kilometers', 'bkx-mobile-bookings' ); ?>
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Include Traffic', 'bkx-mobile-bookings' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="include_traffic" value="1"
									<?php checked( $settings['include_traffic'] ?? 1 ); ?> />
								<?php esc_html_e( 'Consider real-time traffic in travel time calculations', 'bkx-mobile-bookings' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Travel Buffer', 'bkx-mobile-bookings' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="add_travel_buffer" value="1"
									<?php checked( $settings['add_travel_buffer'] ?? 1 ); ?> />
								<?php esc_html_e( 'Add buffer time between appointments', 'bkx-mobile-bookings' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="travel_buffer_percentage"><?php esc_html_e( 'Buffer Percentage', 'bkx-mobile-bookings' ); ?></label>
						</th>
						<td>
							<input type="number" id="travel_buffer_percentage" name="travel_buffer_percentage"
								value="<?php echo esc_attr( $settings['travel_buffer_percentage'] ?? 20 ); ?>"
								min="0" max="100" class="small-text" /> %
							<p class="description"><?php esc_html_e( 'Extra time added to travel estimates.', 'bkx-mobile-bookings' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Distance Pricing -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'Distance-Based Pricing', 'bkx-mobile-bookings' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Distance Pricing', 'bkx-mobile-bookings' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_distance_pricing" value="1"
									<?php checked( $settings['enable_distance_pricing'] ?? 0 ); ?> />
								<?php esc_html_e( 'Charge customers based on travel distance', 'bkx-mobile-bookings' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="base_travel_fee"><?php esc_html_e( 'Base Travel Fee', 'bkx-mobile-bookings' ); ?></label>
						</th>
						<td>
							<input type="number" id="base_travel_fee" name="base_travel_fee"
								value="<?php echo esc_attr( $settings['base_travel_fee'] ?? 0 ); ?>"
								min="0" step="0.01" class="small-text" />
							<p class="description"><?php esc_html_e( 'Flat fee applied to all bookings.', 'bkx-mobile-bookings' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="per_mile_rate"><?php esc_html_e( 'Per Mile Rate', 'bkx-mobile-bookings' ); ?></label>
						</th>
						<td>
							<input type="number" id="per_mile_rate" name="per_mile_rate"
								value="<?php echo esc_attr( $settings['per_mile_rate'] ?? 0 ); ?>"
								min="0" step="0.01" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="free_distance_miles"><?php esc_html_e( 'Free Distance', 'bkx-mobile-bookings' ); ?></label>
						</th>
						<td>
							<input type="number" id="free_distance_miles" name="free_distance_miles"
								value="<?php echo esc_attr( $settings['free_distance_miles'] ?? 0 ); ?>"
								min="0" step="0.1" class="small-text" /> <?php esc_html_e( 'miles', 'bkx-mobile-bookings' ); ?>
							<p class="description"><?php esc_html_e( 'Distance traveled before charges apply.', 'bkx-mobile-bookings' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="max_distance_miles"><?php esc_html_e( 'Maximum Distance', 'bkx-mobile-bookings' ); ?></label>
						</th>
						<td>
							<input type="number" id="max_distance_miles" name="max_distance_miles"
								value="<?php echo esc_attr( $settings['max_distance_miles'] ?? 50 ); ?>"
								min="0" step="1" class="small-text" /> <?php esc_html_e( 'miles', 'bkx-mobile-bookings' ); ?>
							<p class="description"><?php esc_html_e( 'Maximum service distance (0 = unlimited).', 'bkx-mobile-bookings' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- GPS Settings -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'GPS & Check-in Settings', 'bkx-mobile-bookings' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable GPS Check-in', 'bkx-mobile-bookings' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_gps_checkin" value="1"
									<?php checked( $settings['enable_gps_checkin'] ?? 1 ); ?> />
								<?php esc_html_e( 'Allow providers to check in via GPS at booking locations', 'bkx-mobile-bookings' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="verification_radius_meters"><?php esc_html_e( 'Verification Radius', 'bkx-mobile-bookings' ); ?></label>
						</th>
						<td>
							<input type="number" id="verification_radius_meters" name="verification_radius_meters"
								value="<?php echo esc_attr( $settings['verification_radius_meters'] ?? 100 ); ?>"
								min="10" max="1000" class="small-text" /> <?php esc_html_e( 'meters', 'bkx-mobile-bookings' ); ?>
							<p class="description"><?php esc_html_e( 'Maximum distance from booking location for verified check-in.', 'bkx-mobile-bookings' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Require GPS Verification', 'bkx-mobile-bookings' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="require_gps_verification" value="1"
									<?php checked( $settings['require_gps_verification'] ?? 0 ); ?> />
								<?php esc_html_e( 'Only allow check-in when provider is within verification radius', 'bkx-mobile-bookings' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Track Provider Location', 'bkx-mobile-bookings' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="track_provider_location" value="1"
									<?php checked( $settings['track_provider_location'] ?? 0 ); ?> />
								<?php esc_html_e( 'Enable real-time provider location tracking', 'bkx-mobile-bookings' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<!-- Data Settings -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'Data Settings', 'bkx-mobile-bookings' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Delete on Uninstall', 'bkx-mobile-bookings' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="delete_data_on_uninstall" value="1"
									<?php checked( $settings['delete_data_on_uninstall'] ?? 0 ); ?> />
								<?php esc_html_e( 'Delete all plugin data when uninstalling', 'bkx-mobile-bookings' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Warning: This will permanently delete all locations, routes, and check-in data.', 'bkx-mobile-bookings' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'bkx-mobile-bookings' ); ?>
				</button>
			</p>
		</form>
	</div>
</div>

<!-- Service Area Modal -->
<div id="bkx-area-modal" class="bkx-modal" style="display:none;">
	<div class="bkx-modal-content bkx-modal-large">
		<span class="bkx-modal-close">&times;</span>
		<h2><?php esc_html_e( 'Service Area', 'bkx-mobile-bookings' ); ?></h2>

		<form id="bkx-area-form" method="post">
			<input type="hidden" name="action" value="bkx_save_service_area" />
			<input type="hidden" name="id" value="" />
			<?php wp_nonce_field( 'bkx_mobile_bookings_nonce', 'nonce' ); ?>

			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="area_name"><?php esc_html_e( 'Name', 'bkx-mobile-bookings' ); ?></label>
					<input type="text" id="area_name" name="name" required />
				</div>
				<div class="bkx-form-field">
					<label for="area_type"><?php esc_html_e( 'Area Type', 'bkx-mobile-bookings' ); ?></label>
					<select id="area_type" name="area_type">
						<option value="radius"><?php esc_html_e( 'Radius', 'bkx-mobile-bookings' ); ?></option>
						<option value="zip_codes"><?php esc_html_e( 'Zip Codes', 'bkx-mobile-bookings' ); ?></option>
						<option value="polygon"><?php esc_html_e( 'Draw on Map', 'bkx-mobile-bookings' ); ?></option>
					</select>
				</div>
			</div>

			<div class="bkx-form-field">
				<label for="area_description"><?php esc_html_e( 'Description', 'bkx-mobile-bookings' ); ?></label>
				<textarea id="area_description" name="description" rows="2"></textarea>
			</div>

			<!-- Radius Options -->
			<div id="radius-options" class="bkx-area-type-options">
				<div class="bkx-form-row">
					<div class="bkx-form-field">
						<label for="area_address"><?php esc_html_e( 'Center Address', 'bkx-mobile-bookings' ); ?></label>
						<input type="text" id="area_address" placeholder="<?php esc_attr_e( 'Enter address or click map', 'bkx-mobile-bookings' ); ?>" />
					</div>
					<div class="bkx-form-field">
						<label for="area_radius"><?php esc_html_e( 'Radius (miles)', 'bkx-mobile-bookings' ); ?></label>
						<input type="number" id="area_radius" name="radius_miles" value="25" min="1" max="500" step="0.5" />
					</div>
				</div>
				<input type="hidden" id="center_latitude" name="center_latitude" />
				<input type="hidden" id="center_longitude" name="center_longitude" />
			</div>

			<!-- Zip Code Options -->
			<div id="zipcode-options" class="bkx-area-type-options" style="display:none;">
				<div class="bkx-form-field">
					<label for="area_zipcodes"><?php esc_html_e( 'Zip Codes', 'bkx-mobile-bookings' ); ?></label>
					<textarea id="area_zipcodes" name="zip_codes" rows="3" placeholder="<?php esc_attr_e( 'Enter zip codes, separated by commas', 'bkx-mobile-bookings' ); ?>"></textarea>
				</div>
			</div>

			<!-- Polygon Options -->
			<div id="polygon-options" class="bkx-area-type-options" style="display:none;">
				<p class="description"><?php esc_html_e( 'Click on the map to draw your service area boundary.', 'bkx-mobile-bookings' ); ?></p>
				<input type="hidden" id="polygon_coordinates" name="polygon_coordinates" />
			</div>

			<?php if ( $has_api_key ) : ?>
				<div id="bkx-area-map" class="bkx-map-container bkx-map-medium"></div>
			<?php endif; ?>

			<!-- Distance Pricing Options -->
			<div class="bkx-form-section">
				<h3><?php esc_html_e( 'Distance Pricing', 'bkx-mobile-bookings' ); ?></h3>
				<label>
					<input type="checkbox" name="distance_pricing_enabled" id="area_distance_pricing" />
					<?php esc_html_e( 'Enable distance-based pricing for this area', 'bkx-mobile-bookings' ); ?>
				</label>

				<div id="area-pricing-options" style="display:none;">
					<div class="bkx-form-row">
						<div class="bkx-form-field">
							<label for="area_base_fee"><?php esc_html_e( 'Base Fee', 'bkx-mobile-bookings' ); ?></label>
							<input type="number" id="area_base_fee" name="base_travel_fee" value="0" min="0" step="0.01" />
						</div>
						<div class="bkx-form-field">
							<label for="area_per_mile"><?php esc_html_e( 'Per Mile Rate', 'bkx-mobile-bookings' ); ?></label>
							<input type="number" id="area_per_mile" name="per_mile_rate" value="0" min="0" step="0.01" />
						</div>
					</div>
				</div>
			</div>

			<div class="bkx-form-row">
				<div class="bkx-form-field">
					<label for="area_status"><?php esc_html_e( 'Status', 'bkx-mobile-bookings' ); ?></label>
					<select id="area_status" name="status">
						<option value="active"><?php esc_html_e( 'Active', 'bkx-mobile-bookings' ); ?></option>
						<option value="inactive"><?php esc_html_e( 'Inactive', 'bkx-mobile-bookings' ); ?></option>
					</select>
				</div>
			</div>

			<div class="bkx-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Service Area', 'bkx-mobile-bookings' ); ?></button>
				<button type="button" class="button bkx-modal-close"><?php esc_html_e( 'Cancel', 'bkx-mobile-bookings' ); ?></button>
			</div>
		</form>
	</div>
</div>

<?php
/**
 * Settings tab template.
 *
 * @package BookingX\MultiLocation
 */

defined( 'ABSPATH' ) || exit;

// Handle form submission.
if ( isset( $_POST['bkx_ml_save_settings'] ) && check_admin_referer( 'bkx_ml_settings' ) ) {
	update_option( 'bkx_ml_google_maps_key', sanitize_text_field( $_POST['google_maps_key'] ?? '' ) );
	update_option( 'bkx_ml_default_location', absint( $_POST['default_location'] ?? 0 ) );
	update_option( 'bkx_ml_show_map', sanitize_text_field( $_POST['show_map'] ?? 'no' ) );
	update_option( 'bkx_ml_enable_geolocation', sanitize_text_field( $_POST['enable_geolocation'] ?? 'no' ) );
	update_option( 'bkx_ml_distance_unit', sanitize_text_field( $_POST['distance_unit'] ?? 'miles' ) );
	update_option( 'bkx_ml_search_radius', absint( $_POST['search_radius'] ?? 25 ) );

	echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'bkx-multi-location' ) . '</p></div>';
}

$addon     = \BookingX\MultiLocation\MultiLocationAddon::get_instance();
$locations = $addon->get_service( 'locations' )->get_all( array( 'status' => 'active' ) );

$google_maps_key    = get_option( 'bkx_ml_google_maps_key', '' );
$default_location   = get_option( 'bkx_ml_default_location', 0 );
$show_map           = get_option( 'bkx_ml_show_map', 'no' );
$enable_geolocation = get_option( 'bkx_ml_enable_geolocation', 'no' );
$distance_unit      = get_option( 'bkx_ml_distance_unit', 'miles' );
$search_radius      = get_option( 'bkx_ml_search_radius', 25 );
?>

<div class="bkx-ml-settings">
	<form method="post">
		<?php wp_nonce_field( 'bkx_ml_settings' ); ?>

		<div class="bkx-ml-card">
			<h2><?php esc_html_e( 'Google Maps', 'bkx-multi-location' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Google Maps is used to display location maps and enable address geocoding.', 'bkx-multi-location' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th><label for="google_maps_key"><?php esc_html_e( 'API Key', 'bkx-multi-location' ); ?></label></th>
					<td>
						<input type="text" id="google_maps_key" name="google_maps_key" value="<?php echo esc_attr( $google_maps_key ); ?>" class="regular-text">
						<p class="description">
							<?php
							printf(
								/* translators: %s: Google Cloud Console URL */
								esc_html__( 'Get your API key from the %s. Enable Maps JavaScript API and Geocoding API.', 'bkx-multi-location' ),
								'<a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-ml-card">
			<h2><?php esc_html_e( 'Display Settings', 'bkx-multi-location' ); ?></h2>

			<table class="form-table">
				<tr>
					<th><label for="default_location"><?php esc_html_e( 'Default Location', 'bkx-multi-location' ); ?></label></th>
					<td>
						<select id="default_location" name="default_location">
							<option value=""><?php esc_html_e( '— Let user select —', 'bkx-multi-location' ); ?></option>
							<?php foreach ( $locations as $location ) : ?>
								<option value="<?php echo esc_attr( $location->id ); ?>" <?php selected( $default_location, $location->id ); ?>>
									<?php echo esc_html( $location->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'The location to use by default if none is selected.', 'bkx-multi-location' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="show_map"><?php esc_html_e( 'Show Map', 'bkx-multi-location' ); ?></label></th>
					<td>
						<select id="show_map" name="show_map">
							<option value="no" <?php selected( $show_map, 'no' ); ?>><?php esc_html_e( 'No', 'bkx-multi-location' ); ?></option>
							<option value="yes" <?php selected( $show_map, 'yes' ); ?>><?php esc_html_e( 'Yes', 'bkx-multi-location' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Show a map on the location selector.', 'bkx-multi-location' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-ml-card">
			<h2><?php esc_html_e( 'Geolocation', 'bkx-multi-location' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Allow customers to find the nearest location based on their current position.', 'bkx-multi-location' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th><label for="enable_geolocation"><?php esc_html_e( 'Enable Geolocation', 'bkx-multi-location' ); ?></label></th>
					<td>
						<select id="enable_geolocation" name="enable_geolocation">
							<option value="no" <?php selected( $enable_geolocation, 'no' ); ?>><?php esc_html_e( 'No', 'bkx-multi-location' ); ?></option>
							<option value="yes" <?php selected( $enable_geolocation, 'yes' ); ?>><?php esc_html_e( 'Yes', 'bkx-multi-location' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="distance_unit"><?php esc_html_e( 'Distance Unit', 'bkx-multi-location' ); ?></label></th>
					<td>
						<select id="distance_unit" name="distance_unit">
							<option value="miles" <?php selected( $distance_unit, 'miles' ); ?>><?php esc_html_e( 'Miles', 'bkx-multi-location' ); ?></option>
							<option value="km" <?php selected( $distance_unit, 'km' ); ?>><?php esc_html_e( 'Kilometers', 'bkx-multi-location' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="search_radius"><?php esc_html_e( 'Search Radius', 'bkx-multi-location' ); ?></label></th>
					<td>
						<input type="number" id="search_radius" name="search_radius" value="<?php echo esc_attr( $search_radius ); ?>" min="1" max="500" style="width: 80px;">
						<span><?php echo 'miles' === $distance_unit ? esc_html__( 'miles', 'bkx-multi-location' ) : esc_html__( 'km', 'bkx-multi-location' ); ?></span>
						<p class="description">
							<?php esc_html_e( 'Maximum distance to search for nearby locations.', 'bkx-multi-location' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-ml-card">
			<h2><?php esc_html_e( 'Shortcodes', 'bkx-multi-location' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Use these shortcodes to display location information on your site.', 'bkx-multi-location' ); ?>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Shortcode', 'bkx-multi-location' ); ?></th>
						<th><?php esc_html_e( 'Description', 'bkx-multi-location' ); ?></th>
						<th><?php esc_html_e( 'Parameters', 'bkx-multi-location' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>[bkx_location_selector]</code></td>
						<td><?php esc_html_e( 'Displays a location selector dropdown or list.', 'bkx-multi-location' ); ?></td>
						<td>
							<code>style="dropdown|list|cards"</code><br>
							<code>show_map="yes|no"</code><br>
							<code>redirect_to="url"</code>
						</td>
					</tr>
					<tr>
						<td><code>[bkx_location_map]</code></td>
						<td><?php esc_html_e( 'Displays a Google Map with location markers.', 'bkx-multi-location' ); ?></td>
						<td>
							<code>height="400px"</code><br>
							<code>zoom="10"</code><br>
							<code>location_id="123"</code>
						</td>
					</tr>
					<tr>
						<td><code>[bkx_location_list]</code></td>
						<td><?php esc_html_e( 'Displays a grid of location cards with details.', 'bkx-multi-location' ); ?></td>
						<td>
							<code>columns="3"</code><br>
							<code>show_address="yes|no"</code><br>
							<code>show_phone="yes|no"</code><br>
							<code>show_hours="yes|no"</code>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<p class="submit">
			<button type="submit" name="bkx_ml_save_settings" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-multi-location' ); ?>
			</button>
		</p>
	</form>
</div>

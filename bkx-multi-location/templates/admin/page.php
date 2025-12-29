<?php
/**
 * Main admin page template.
 *
 * @package BookingX\MultiLocation
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'locations' => __( 'Locations', 'bkx-multi-location' ),
	'settings'  => __( 'Settings', 'bkx-multi-location' ),
);

$current_tab = $tab;
?>

<div class="wrap bkx-ml-admin">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Multi-Location Management', 'bkx-multi-location' ); ?>
	</h1>

	<?php if ( 'locations' === $current_tab ) : ?>
		<a href="#" class="page-title-action" id="bkx-ml-add-location">
			<?php esc_html_e( 'Add Location', 'bkx-multi-location' ); ?>
		</a>
	<?php endif; ?>

	<hr class="wp-header-end">

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-locations&tab=' . $tab_key ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-ml-content">
		<?php
		switch ( $current_tab ) {
			case 'settings':
				include BKX_ML_PLUGIN_DIR . 'templates/admin/settings.php';
				break;

			default:
				include BKX_ML_PLUGIN_DIR . 'templates/admin/locations.php';
				break;
		}
		?>
	</div>
</div>

<!-- Location Modal -->
<div id="bkx-ml-location-modal" class="bkx-ml-modal" style="display: none;">
	<div class="bkx-ml-modal-content">
		<span class="bkx-ml-modal-close">&times;</span>
		<h2 id="bkx-ml-modal-title"><?php esc_html_e( 'Add Location', 'bkx-multi-location' ); ?></h2>

		<form id="bkx-ml-location-form">
			<input type="hidden" name="location_id" id="bkx-ml-location-id" value="0">

			<div class="bkx-ml-tabs">
				<button type="button" class="bkx-ml-tab active" data-tab="details">
					<?php esc_html_e( 'Details', 'bkx-multi-location' ); ?>
				</button>
				<button type="button" class="bkx-ml-tab" data-tab="address">
					<?php esc_html_e( 'Address', 'bkx-multi-location' ); ?>
				</button>
				<button type="button" class="bkx-ml-tab" data-tab="hours">
					<?php esc_html_e( 'Hours', 'bkx-multi-location' ); ?>
				</button>
				<button type="button" class="bkx-ml-tab" data-tab="staff">
					<?php esc_html_e( 'Staff', 'bkx-multi-location' ); ?>
				</button>
				<button type="button" class="bkx-ml-tab" data-tab="services">
					<?php esc_html_e( 'Services', 'bkx-multi-location' ); ?>
				</button>
			</div>

			<!-- Details Tab -->
			<div class="bkx-ml-tab-content active" data-tab="details">
				<table class="form-table">
					<tr>
						<th><label for="bkx-ml-name"><?php esc_html_e( 'Name', 'bkx-multi-location' ); ?> <span class="required">*</span></label></th>
						<td><input type="text" id="bkx-ml-name" name="name" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="bkx-ml-slug"><?php esc_html_e( 'Slug', 'bkx-multi-location' ); ?></label></th>
						<td>
							<input type="text" id="bkx-ml-slug" name="slug" class="regular-text">
							<p class="description"><?php esc_html_e( 'URL-friendly identifier. Leave blank to auto-generate.', 'bkx-multi-location' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="bkx-ml-description"><?php esc_html_e( 'Description', 'bkx-multi-location' ); ?></label></th>
						<td><textarea id="bkx-ml-description" name="description" rows="3" class="large-text"></textarea></td>
					</tr>
					<tr>
						<th><label for="bkx-ml-phone"><?php esc_html_e( 'Phone', 'bkx-multi-location' ); ?></label></th>
						<td><input type="tel" id="bkx-ml-phone" name="phone" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="bkx-ml-email"><?php esc_html_e( 'Email', 'bkx-multi-location' ); ?></label></th>
						<td><input type="email" id="bkx-ml-email" name="email" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="bkx-ml-timezone"><?php esc_html_e( 'Timezone', 'bkx-multi-location' ); ?></label></th>
						<td>
							<select id="bkx-ml-timezone" name="timezone">
								<option value=""><?php esc_html_e( '— Use Site Timezone —', 'bkx-multi-location' ); ?></option>
								<?php echo wp_timezone_choice( '' ); ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="bkx-ml-status"><?php esc_html_e( 'Status', 'bkx-multi-location' ); ?></label></th>
						<td>
							<select id="bkx-ml-status" name="status">
								<option value="active"><?php esc_html_e( 'Active', 'bkx-multi-location' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'Inactive', 'bkx-multi-location' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<!-- Address Tab -->
			<div class="bkx-ml-tab-content" data-tab="address">
				<table class="form-table">
					<tr>
						<th><label for="bkx-ml-address1"><?php esc_html_e( 'Address Line 1', 'bkx-multi-location' ); ?></label></th>
						<td><input type="text" id="bkx-ml-address1" name="address_line_1" class="large-text"></td>
					</tr>
					<tr>
						<th><label for="bkx-ml-address2"><?php esc_html_e( 'Address Line 2', 'bkx-multi-location' ); ?></label></th>
						<td><input type="text" id="bkx-ml-address2" name="address_line_2" class="large-text"></td>
					</tr>
					<tr>
						<th><label for="bkx-ml-city"><?php esc_html_e( 'City', 'bkx-multi-location' ); ?></label></th>
						<td><input type="text" id="bkx-ml-city" name="city" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="bkx-ml-state"><?php esc_html_e( 'State/Province', 'bkx-multi-location' ); ?></label></th>
						<td><input type="text" id="bkx-ml-state" name="state" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="bkx-ml-postal"><?php esc_html_e( 'Postal Code', 'bkx-multi-location' ); ?></label></th>
						<td><input type="text" id="bkx-ml-postal" name="postal_code" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="bkx-ml-country"><?php esc_html_e( 'Country', 'bkx-multi-location' ); ?></label></th>
						<td><input type="text" id="bkx-ml-country" name="country" class="regular-text" maxlength="2" placeholder="US"></td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Coordinates', 'bkx-multi-location' ); ?></label></th>
						<td>
							<input type="text" id="bkx-ml-lat" name="latitude" placeholder="<?php esc_attr_e( 'Latitude', 'bkx-multi-location' ); ?>" style="width: 120px;">
							<input type="text" id="bkx-ml-lng" name="longitude" placeholder="<?php esc_attr_e( 'Longitude', 'bkx-multi-location' ); ?>" style="width: 120px;">
							<button type="button" class="button" id="bkx-ml-geocode">
								<?php esc_html_e( 'Lookup from Address', 'bkx-multi-location' ); ?>
							</button>
						</td>
					</tr>
				</table>
			</div>

			<!-- Hours Tab -->
			<div class="bkx-ml-tab-content" data-tab="hours">
				<table class="bkx-ml-hours-table widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Day', 'bkx-multi-location' ); ?></th>
							<th><?php esc_html_e( 'Open', 'bkx-multi-location' ); ?></th>
							<th><?php esc_html_e( 'Open Time', 'bkx-multi-location' ); ?></th>
							<th><?php esc_html_e( 'Close Time', 'bkx-multi-location' ); ?></th>
							<th><?php esc_html_e( 'Break Start', 'bkx-multi-location' ); ?></th>
							<th><?php esc_html_e( 'Break End', 'bkx-multi-location' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$days = array(
							0 => __( 'Sunday', 'bkx-multi-location' ),
							1 => __( 'Monday', 'bkx-multi-location' ),
							2 => __( 'Tuesday', 'bkx-multi-location' ),
							3 => __( 'Wednesday', 'bkx-multi-location' ),
							4 => __( 'Thursday', 'bkx-multi-location' ),
							5 => __( 'Friday', 'bkx-multi-location' ),
							6 => __( 'Saturday', 'bkx-multi-location' ),
						);

						foreach ( $days as $day_num => $day_name ) :
							$default_open = ( $day_num >= 1 && $day_num <= 5 );
							?>
							<tr>
								<td><?php echo esc_html( $day_name ); ?></td>
								<td>
									<input type="checkbox" name="hours[<?php echo esc_attr( $day_num ); ?>][is_open]" value="1"
										   class="bkx-ml-day-open" data-day="<?php echo esc_attr( $day_num ); ?>"
										   <?php checked( $default_open ); ?>>
								</td>
								<td>
									<input type="time" name="hours[<?php echo esc_attr( $day_num ); ?>][open_time]"
										   class="bkx-ml-time" value="<?php echo $default_open ? '09:00' : ''; ?>">
								</td>
								<td>
									<input type="time" name="hours[<?php echo esc_attr( $day_num ); ?>][close_time]"
										   class="bkx-ml-time" value="<?php echo $default_open ? '17:00' : ''; ?>">
								</td>
								<td>
									<input type="time" name="hours[<?php echo esc_attr( $day_num ); ?>][break_start]"
										   class="bkx-ml-time">
								</td>
								<td>
									<input type="time" name="hours[<?php echo esc_attr( $day_num ); ?>][break_end]"
										   class="bkx-ml-time">
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h4><?php esc_html_e( 'Holidays', 'bkx-multi-location' ); ?></h4>
				<div id="bkx-ml-holidays-list"></div>
				<div class="bkx-ml-add-holiday">
					<input type="text" id="bkx-ml-holiday-name" placeholder="<?php esc_attr_e( 'Holiday name', 'bkx-multi-location' ); ?>">
					<input type="date" id="bkx-ml-holiday-date">
					<label>
						<input type="checkbox" id="bkx-ml-holiday-recurring">
						<?php esc_html_e( 'Recurring annually', 'bkx-multi-location' ); ?>
					</label>
					<button type="button" class="button" id="bkx-ml-add-holiday-btn">
						<?php esc_html_e( 'Add Holiday', 'bkx-multi-location' ); ?>
					</button>
				</div>
			</div>

			<!-- Staff Tab -->
			<div class="bkx-ml-tab-content" data-tab="staff">
				<div id="bkx-ml-staff-list">
					<p class="bkx-ml-loading"><?php esc_html_e( 'Loading staff...', 'bkx-multi-location' ); ?></p>
				</div>

				<h4><?php esc_html_e( 'Assign Staff', 'bkx-multi-location' ); ?></h4>
				<div class="bkx-ml-assign-staff">
					<select id="bkx-ml-staff-select">
						<option value=""><?php esc_html_e( '— Select Staff —', 'bkx-multi-location' ); ?></option>
						<?php
						$seats = get_posts(
							array(
								'post_type'      => 'bkx_seat',
								'posts_per_page' => -1,
								'post_status'    => 'publish',
								'orderby'        => 'title',
								'order'          => 'ASC',
							)
						);
						foreach ( $seats as $seat ) :
							?>
							<option value="<?php echo esc_attr( $seat->ID ); ?>">
								<?php echo esc_html( $seat->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<label>
						<input type="checkbox" id="bkx-ml-staff-primary">
						<?php esc_html_e( 'Primary location', 'bkx-multi-location' ); ?>
					</label>
					<button type="button" class="button" id="bkx-ml-assign-staff-btn">
						<?php esc_html_e( 'Assign', 'bkx-multi-location' ); ?>
					</button>
				</div>
			</div>

			<!-- Services Tab -->
			<div class="bkx-ml-tab-content" data-tab="services">
				<p class="description">
					<?php esc_html_e( 'Configure which services are available at this location and optionally override prices or durations.', 'bkx-multi-location' ); ?>
				</p>
				<div id="bkx-ml-services-list">
					<p class="bkx-ml-loading"><?php esc_html_e( 'Loading services...', 'bkx-multi-location' ); ?></p>
				</div>
			</div>

			<div class="bkx-ml-modal-footer">
				<button type="button" class="button bkx-ml-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-multi-location' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Location', 'bkx-multi-location' ); ?></button>
			</div>
		</form>
	</div>
</div>

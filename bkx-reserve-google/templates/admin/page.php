<?php
/**
 * Admin page template for Reserve with Google settings.
 *
 * @package BookingX\ReserveGoogle
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_reserve_google_settings', array() );
$tab      = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification
?>

<div class="wrap bkx-rwg-wrap">
	<h1>
		<span class="dashicons dashicons-location"></span>
		<?php esc_html_e( 'Reserve with Google', 'bkx-reserve-google' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-reserve-google&tab=settings' ) ); ?>"
		   class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-reserve-google' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-reserve-google&tab=services' ) ); ?>"
		   class="nav-tab <?php echo 'services' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Services', 'bkx-reserve-google' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-reserve-google&tab=feeds' ) ); ?>"
		   class="nav-tab <?php echo 'feeds' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Feeds', 'bkx-reserve-google' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-reserve-google&tab=logs' ) ); ?>"
		   class="nav-tab <?php echo 'logs' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Logs', 'bkx-reserve-google' ); ?>
		</a>
	</nav>

	<div class="bkx-rwg-content">
		<?php if ( 'settings' === $tab ) : ?>
			<!-- Settings Tab -->
			<form id="bkx-rwg-settings-form" class="bkx-settings-form">
				<?php wp_nonce_field( 'bkx_reserve_google', 'bkx_nonce' ); ?>

				<div class="bkx-card">
					<h2><?php esc_html_e( 'Integration Status', 'bkx-reserve-google' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="enabled"><?php esc_html_e( 'Enable Integration', 'bkx-reserve-google' ); ?></label>
							</th>
							<td>
								<label class="bkx-toggle">
									<input type="checkbox" id="enabled" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Enable Reserve with Google integration.', 'bkx-reserve-google' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dev_mode"><?php esc_html_e( 'Development Mode', 'bkx-reserve-google' ); ?></label>
							</th>
							<td>
								<label class="bkx-toggle">
									<input type="checkbox" id="dev_mode" name="dev_mode" value="1" <?php checked( ! empty( $settings['dev_mode'] ) ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Skip API authentication for testing.', 'bkx-reserve-google' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="bkx-card">
					<h2><?php esc_html_e( 'Business Information', 'bkx-reserve-google' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="business_name"><?php esc_html_e( 'Business Name', 'bkx-reserve-google' ); ?></label>
							</th>
							<td>
								<input type="text" id="business_name" name="business_name" class="regular-text"
									   value="<?php echo esc_attr( $settings['business_name'] ?? get_bloginfo( 'name' ) ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="place_id"><?php esc_html_e( 'Google Place ID', 'bkx-reserve-google' ); ?></label>
							</th>
							<td>
								<input type="text" id="place_id" name="place_id" class="large-text"
									   value="<?php echo esc_attr( $settings['place_id'] ?? '' ); ?>"
									   placeholder="ChIJ...">
								<p class="description">
									<?php esc_html_e( 'Your Google Place ID. Find it at', 'bkx-reserve-google' ); ?>
									<a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank">
										<?php esc_html_e( 'Place ID Finder', 'bkx-reserve-google' ); ?>
									</a>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="business_address"><?php esc_html_e( 'Business Address', 'bkx-reserve-google' ); ?></label>
							</th>
							<td>
								<textarea id="business_address" name="business_address" class="large-text" rows="3"><?php
									echo esc_textarea( $settings['business_address'] ?? '' );
								?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="business_phone"><?php esc_html_e( 'Phone Number', 'bkx-reserve-google' ); ?></label>
							</th>
							<td>
								<input type="tel" id="business_phone" name="business_phone" class="regular-text"
									   value="<?php echo esc_attr( $settings['business_phone'] ?? '' ); ?>"
									   placeholder="+1-234-567-8900">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="business_category"><?php esc_html_e( 'Business Category', 'bkx-reserve-google' ); ?></label>
							</th>
							<td>
								<select id="business_category" name="business_category">
									<option value="gcid:beauty_salon" <?php selected( $settings['business_category'] ?? '', 'gcid:beauty_salon' ); ?>><?php esc_html_e( 'Beauty Salon', 'bkx-reserve-google' ); ?></option>
									<option value="gcid:hair_salon" <?php selected( $settings['business_category'] ?? '', 'gcid:hair_salon' ); ?>><?php esc_html_e( 'Hair Salon', 'bkx-reserve-google' ); ?></option>
									<option value="gcid:spa" <?php selected( $settings['business_category'] ?? '', 'gcid:spa' ); ?>><?php esc_html_e( 'Spa', 'bkx-reserve-google' ); ?></option>
									<option value="gcid:barbershop" <?php selected( $settings['business_category'] ?? '', 'gcid:barbershop' ); ?>><?php esc_html_e( 'Barbershop', 'bkx-reserve-google' ); ?></option>
									<option value="gcid:nail_salon" <?php selected( $settings['business_category'] ?? '', 'gcid:nail_salon' ); ?>><?php esc_html_e( 'Nail Salon', 'bkx-reserve-google' ); ?></option>
									<option value="gcid:fitness_center" <?php selected( $settings['business_category'] ?? '', 'gcid:fitness_center' ); ?>><?php esc_html_e( 'Fitness Center', 'bkx-reserve-google' ); ?></option>
									<option value="gcid:yoga_studio" <?php selected( $settings['business_category'] ?? '', 'gcid:yoga_studio' ); ?>><?php esc_html_e( 'Yoga Studio', 'bkx-reserve-google' ); ?></option>
									<option value="gcid:massage_therapist" <?php selected( $settings['business_category'] ?? '', 'gcid:massage_therapist' ); ?>><?php esc_html_e( 'Massage Therapist', 'bkx-reserve-google' ); ?></option>
									<option value="gcid:dental_clinic" <?php selected( $settings['business_category'] ?? '', 'gcid:dental_clinic' ); ?>><?php esc_html_e( 'Dental Clinic', 'bkx-reserve-google' ); ?></option>
									<option value="gcid:medical_clinic" <?php selected( $settings['business_category'] ?? '', 'gcid:medical_clinic' ); ?>><?php esc_html_e( 'Medical Clinic', 'bkx-reserve-google' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="timezone"><?php esc_html_e( 'Timezone', 'bkx-reserve-google' ); ?></label>
							</th>
							<td>
								<select id="timezone" name="timezone">
									<?php echo wp_timezone_choice( $settings['timezone'] ?? wp_timezone_string() ); ?>
								</select>
							</td>
						</tr>
					</table>

					<p>
						<button type="button" id="bkx-verify-merchant" class="button">
							<?php esc_html_e( 'Verify Merchant', 'bkx-reserve-google' ); ?>
						</button>
						<span id="verify-status"></span>
					</p>
				</div>

				<div class="bkx-card">
					<h2><?php esc_html_e( 'API Configuration', 'bkx-reserve-google' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="partner_id"><?php esc_html_e( 'Partner ID', 'bkx-reserve-google' ); ?></label>
							</th>
							<td>
								<input type="text" id="partner_id" name="partner_id" class="regular-text"
									   value="<?php echo esc_attr( $settings['partner_id'] ?? '' ); ?>">
								<p class="description"><?php esc_html_e( 'Your Reserve with Google Partner ID.', 'bkx-reserve-google' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="api_key"><?php esc_html_e( 'API Key', 'bkx-reserve-google' ); ?></label>
							</th>
							<td>
								<input type="password" id="api_key" name="api_key" class="large-text"
									   value="<?php echo esc_attr( $settings['api_key'] ?? '' ); ?>">
								<p class="description"><?php esc_html_e( 'API key for authenticating Google requests.', 'bkx-reserve-google' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Basic Auth (Alternative)', 'bkx-reserve-google' ); ?></th>
							<td>
								<input type="text" id="api_username" name="api_username" class="regular-text"
									   value="<?php echo esc_attr( $settings['api_username'] ?? '' ); ?>"
									   placeholder="<?php esc_attr_e( 'Username', 'bkx-reserve-google' ); ?>">
								<input type="password" id="api_password" name="api_password" class="regular-text"
									   value="<?php echo esc_attr( $settings['api_password'] ?? '' ); ?>"
									   placeholder="<?php esc_attr_e( 'Password', 'bkx-reserve-google' ); ?>">
							</td>
						</tr>
					</table>
				</div>

				<div class="bkx-card">
					<h2><?php esc_html_e( 'Booking Rules', 'bkx-reserve-google' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="advance_booking_days"><?php esc_html_e( 'Advance Booking', 'bkx-reserve-google' ); ?></label>
							</th>
							<td>
								<input type="number" id="advance_booking_days" name="advance_booking_days" class="small-text"
									   value="<?php echo esc_attr( $settings['advance_booking_days'] ?? 30 ); ?>"
									   min="1" max="365">
								<?php esc_html_e( 'days in advance', 'bkx-reserve-google' ); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="min_advance_hours"><?php esc_html_e( 'Minimum Notice', 'bkx-reserve-google' ); ?></label>
							</th>
							<td>
								<input type="number" id="min_advance_hours" name="min_advance_hours" class="small-text"
									   value="<?php echo esc_attr( $settings['min_advance_hours'] ?? 1 ); ?>"
									   min="0" max="168">
								<?php esc_html_e( 'hours before appointment', 'bkx-reserve-google' ); ?>
							</td>
						</tr>
					</table>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'bkx-reserve-google' ); ?>
					</button>
				</p>
			</form>

		<?php elseif ( 'services' === $tab ) : ?>
			<!-- Services Tab -->
			<div class="bkx-card">
				<h2>
					<?php esc_html_e( 'Synced Services', 'bkx-reserve-google' ); ?>
					<button type="button" id="bkx-sync-services" class="button" style="margin-left: 10px;">
						<?php esc_html_e( 'Sync Now', 'bkx-reserve-google' ); ?>
					</button>
				</h2>

				<?php
				global $wpdb;
				$services = $wpdb->get_results(
					"SELECT * FROM {$wpdb->prefix}bkx_rwg_services ORDER BY name ASC" // phpcs:ignore
				);
				?>

				<?php if ( ! empty( $services ) ) : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Service', 'bkx-reserve-google' ); ?></th>
								<th><?php esc_html_e( 'Price', 'bkx-reserve-google' ); ?></th>
								<th><?php esc_html_e( 'Duration', 'bkx-reserve-google' ); ?></th>
								<th><?php esc_html_e( 'RWG ID', 'bkx-reserve-google' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-reserve-google' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $services as $service ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $service->name ); ?></strong></td>
									<td><?php echo esc_html( '$' . number_format( $service->price, 2 ) ); ?></td>
									<td><?php echo esc_html( $service->duration_minutes . ' min' ); ?></td>
									<td><code><?php echo esc_html( $service->rwg_service_id ); ?></code></td>
									<td>
										<?php if ( $service->enabled ) : ?>
											<span class="bkx-status bkx-status-active"><?php esc_html_e( 'Enabled', 'bkx-reserve-google' ); ?></span>
										<?php else : ?>
											<span class="bkx-status bkx-status-inactive"><?php esc_html_e( 'Disabled', 'bkx-reserve-google' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="bkx-empty-state">
						<?php esc_html_e( 'No services synced yet. Click "Sync Now" to import services from BookingX.', 'bkx-reserve-google' ); ?>
					</p>
				<?php endif; ?>
			</div>

		<?php elseif ( 'feeds' === $tab ) : ?>
			<!-- Feeds Tab -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'Data Feeds', 'bkx-reserve-google' ); ?></h2>

				<p><?php esc_html_e( 'Reserve with Google uses these feed endpoints to retrieve your business data.', 'bkx-reserve-google' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Merchants Feed', 'bkx-reserve-google' ); ?></th>
						<td>
							<code id="feed-merchants"><?php echo esc_url( rest_url( 'bkx-rwg/v2/feeds/merchants' ) ); ?></code>
							<button type="button" class="button button-small bkx-copy-btn" data-copy="feed-merchants">
								<?php esc_html_e( 'Copy', 'bkx-reserve-google' ); ?>
							</button>
							<button type="button" class="button button-small bkx-test-feed" data-feed="merchants">
								<?php esc_html_e( 'Test', 'bkx-reserve-google' ); ?>
							</button>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Services Feed', 'bkx-reserve-google' ); ?></th>
						<td>
							<code id="feed-services"><?php echo esc_url( rest_url( 'bkx-rwg/v2/feeds/services' ) ); ?></code>
							<button type="button" class="button button-small bkx-copy-btn" data-copy="feed-services">
								<?php esc_html_e( 'Copy', 'bkx-reserve-google' ); ?>
							</button>
							<button type="button" class="button button-small bkx-test-feed" data-feed="services">
								<?php esc_html_e( 'Test', 'bkx-reserve-google' ); ?>
							</button>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Availability Feed', 'bkx-reserve-google' ); ?></th>
						<td>
							<code id="feed-availability"><?php echo esc_url( rest_url( 'bkx-rwg/v2/feeds/availability' ) ); ?></code>
							<button type="button" class="button button-small bkx-copy-btn" data-copy="feed-availability">
								<?php esc_html_e( 'Copy', 'bkx-reserve-google' ); ?>
							</button>
							<button type="button" class="button button-small bkx-test-feed" data-feed="availability">
								<?php esc_html_e( 'Test', 'bkx-reserve-google' ); ?>
							</button>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Booking API Endpoints', 'bkx-reserve-google' ); ?></h3>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Health Check', 'bkx-reserve-google' ); ?></th>
						<td>
							<code><?php echo esc_url( rest_url( 'bkx-rwg/v2/health' ) ); ?></code>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Check Availability', 'bkx-reserve-google' ); ?></th>
						<td>
							<code><?php echo esc_url( rest_url( 'bkx-rwg/v2/CheckAvailability' ) ); ?></code>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Create Booking', 'bkx-reserve-google' ); ?></th>
						<td>
							<code><?php echo esc_url( rest_url( 'bkx-rwg/v2/CreateBooking' ) ); ?></code>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Update Booking', 'bkx-reserve-google' ); ?></th>
						<td>
							<code><?php echo esc_url( rest_url( 'bkx-rwg/v2/UpdateBooking' ) ); ?></code>
						</td>
					</tr>
				</table>

				<div id="feed-preview" style="display: none; margin-top: 20px;">
					<h3><?php esc_html_e( 'Feed Preview', 'bkx-reserve-google' ); ?></h3>
					<pre id="feed-preview-content" style="background: #f0f0f1; padding: 15px; overflow: auto; max-height: 400px;"></pre>
				</div>
			</div>

		<?php elseif ( 'logs' === $tab ) : ?>
			<!-- Logs Tab -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'Integration Stats', 'bkx-reserve-google' ); ?></h2>

				<div class="bkx-stats-row">
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-total">-</span>
						<span class="stat-label"><?php esc_html_e( 'Total Bookings', 'bkx-reserve-google' ); ?></span>
					</div>
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-confirmed">-</span>
						<span class="stat-label"><?php esc_html_e( 'Confirmed', 'bkx-reserve-google' ); ?></span>
					</div>
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-cancelled">-</span>
						<span class="stat-label"><?php esc_html_e( 'Cancelled', 'bkx-reserve-google' ); ?></span>
					</div>
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-services">-</span>
						<span class="stat-label"><?php esc_html_e( 'Services', 'bkx-reserve-google' ); ?></span>
					</div>
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-today">-</span>
						<span class="stat-label"><?php esc_html_e( 'Today', 'bkx-reserve-google' ); ?></span>
					</div>
				</div>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'API Request Logs', 'bkx-reserve-google' ); ?></h2>

				<?php
				global $wpdb;
				$logs = $wpdb->get_results(
					"SELECT * FROM {$wpdb->prefix}bkx_rwg_logs ORDER BY created_at DESC LIMIT 50" // phpcs:ignore
				);
				?>

				<?php if ( ! empty( $logs ) ) : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Time', 'bkx-reserve-google' ); ?></th>
								<th><?php esc_html_e( 'Endpoint', 'bkx-reserve-google' ); ?></th>
								<th><?php esc_html_e( 'Method', 'bkx-reserve-google' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-reserve-google' ); ?></th>
								<th><?php esc_html_e( 'Time (ms)', 'bkx-reserve-google' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<tr>
									<td>
										<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->created_at ) ) ); ?>
									</td>
									<td><code><?php echo esc_html( $log->endpoint ); ?></code></td>
									<td><?php echo esc_html( $log->method ); ?></td>
									<td>
										<?php if ( $log->response_code === 200 ) : ?>
											<span class="bkx-status bkx-status-success">200 OK</span>
										<?php else : ?>
											<span class="bkx-status bkx-status-error"><?php echo esc_html( $log->response_code ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $log->processing_time ?: '-' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="bkx-empty-state">
						<?php esc_html_e( 'No API requests logged yet.', 'bkx-reserve-google' ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

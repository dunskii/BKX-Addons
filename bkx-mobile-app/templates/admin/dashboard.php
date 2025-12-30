<?php
/**
 * Mobile App Dashboard.
 *
 * @package BookingX\MobileApp
 */

defined( 'ABSPATH' ) || exit;

$addon          = \BookingX\MobileApp\MobileAppAddon::get_instance();
$device_manager = $addon->get_service( 'device_manager' );
$api_manager    = $addon->get_service( 'api_manager' );

$device_stats = $device_manager->get_statistics();
$api_keys     = $api_manager->get_api_keys();
$settings     = get_option( 'bkx_mobile_app_settings', array() );
?>

<div class="bkx-dashboard">
	<!-- Status Banner -->
	<div class="bkx-status-banner <?php echo ! empty( $settings['enabled'] ) ? 'bkx-enabled' : 'bkx-disabled'; ?>">
		<?php if ( ! empty( $settings['enabled'] ) ) : ?>
			<span class="dashicons dashicons-smartphone"></span>
			<strong><?php esc_html_e( 'Mobile App API: Active', 'bkx-mobile-app' ); ?></strong>
			<p><?php esc_html_e( 'Your mobile apps can connect to this site.', 'bkx-mobile-app' ); ?></p>
		<?php else : ?>
			<span class="dashicons dashicons-warning"></span>
			<strong><?php esc_html_e( 'Mobile App API: Inactive', 'bkx-mobile-app' ); ?></strong>
			<p><?php esc_html_e( 'Enable the Mobile App API in settings.', 'bkx-mobile-app' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Stats Grid -->
	<div class="bkx-stats-grid">
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $device_stats['total'] ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Registered Devices', 'bkx-mobile-app' ); ?></span>
		</div>
		<div class="bkx-stat-card bkx-stat-ios">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $device_stats['ios'] ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'iOS Devices', 'bkx-mobile-app' ); ?></span>
		</div>
		<div class="bkx-stat-card bkx-stat-android">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $device_stats['android'] ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Android Devices', 'bkx-mobile-app' ); ?></span>
		</div>
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $device_stats['active_today'] ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Active Today', 'bkx-mobile-app' ); ?></span>
		</div>
	</div>

	<div class="bkx-dashboard-grid">
		<!-- API Endpoint -->
		<div class="bkx-dashboard-card">
			<h3><?php esc_html_e( 'API Endpoint', 'bkx-mobile-app' ); ?></h3>
			<div class="bkx-api-endpoint">
				<code><?php echo esc_html( rest_url( 'bkx-mobile/v1' ) ); ?></code>
				<button type="button" class="button bkx-copy-btn" data-copy="<?php echo esc_attr( rest_url( 'bkx-mobile/v1' ) ); ?>">
					<span class="dashicons dashicons-clipboard"></span>
				</button>
			</div>
			<p class="description">
				<?php esc_html_e( 'Use this endpoint in your mobile app configuration.', 'bkx-mobile-app' ); ?>
			</p>
		</div>

		<!-- Quick Links -->
		<div class="bkx-dashboard-card">
			<h3><?php esc_html_e( 'Quick Links', 'bkx-mobile-app' ); ?></h3>
			<ul class="bkx-quick-links">
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-mobile-app&tab=api' ) ); ?>">
						<span class="dashicons dashicons-admin-network"></span>
						<?php esc_html_e( 'Generate API Key', 'bkx-mobile-app' ); ?>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-mobile-app&tab=push' ) ); ?>">
						<span class="dashicons dashicons-megaphone"></span>
						<?php esc_html_e( 'Configure Push Notifications', 'bkx-mobile-app' ); ?>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-mobile-app&tab=devices' ) ); ?>">
						<span class="dashicons dashicons-smartphone"></span>
						<?php esc_html_e( 'View Registered Devices', 'bkx-mobile-app' ); ?>
					</a>
				</li>
			</ul>
		</div>

		<!-- Available Endpoints -->
		<div class="bkx-dashboard-card bkx-card-wide">
			<h3><?php esc_html_e( 'Available API Endpoints', 'bkx-mobile-app' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Endpoint', 'bkx-mobile-app' ); ?></th>
						<th><?php esc_html_e( 'Method', 'bkx-mobile-app' ); ?></th>
						<th><?php esc_html_e( 'Description', 'bkx-mobile-app' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>/auth/login</code></td>
						<td><span class="bkx-method-post">POST</span></td>
						<td><?php esc_html_e( 'Authenticate user and get token', 'bkx-mobile-app' ); ?></td>
					</tr>
					<tr>
						<td><code>/auth/register</code></td>
						<td><span class="bkx-method-post">POST</span></td>
						<td><?php esc_html_e( 'Register new user', 'bkx-mobile-app' ); ?></td>
					</tr>
					<tr>
						<td><code>/devices</code></td>
						<td><span class="bkx-method-post">POST</span></td>
						<td><?php esc_html_e( 'Register device for push notifications', 'bkx-mobile-app' ); ?></td>
					</tr>
					<tr>
						<td><code>/bookings</code></td>
						<td><span class="bkx-method-get">GET</span></td>
						<td><?php esc_html_e( 'Get user bookings', 'bkx-mobile-app' ); ?></td>
					</tr>
					<tr>
						<td><code>/bookings</code></td>
						<td><span class="bkx-method-post">POST</span></td>
						<td><?php esc_html_e( 'Create new booking', 'bkx-mobile-app' ); ?></td>
					</tr>
					<tr>
						<td><code>/services</code></td>
						<td><span class="bkx-method-get">GET</span></td>
						<td><?php esc_html_e( 'Get available services', 'bkx-mobile-app' ); ?></td>
					</tr>
					<tr>
						<td><code>/resources</code></td>
						<td><span class="bkx-method-get">GET</span></td>
						<td><?php esc_html_e( 'Get available resources', 'bkx-mobile-app' ); ?></td>
					</tr>
					<tr>
						<td><code>/availability</code></td>
						<td><span class="bkx-method-get">GET</span></td>
						<td><?php esc_html_e( 'Get availability for date/service', 'bkx-mobile-app' ); ?></td>
					</tr>
					<tr>
						<td><code>/config</code></td>
						<td><span class="bkx-method-get">GET</span></td>
						<td><?php esc_html_e( 'Get app configuration', 'bkx-mobile-app' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php
/**
 * PWA Offline Settings Tab.
 *
 * @package BookingX\PWA
 */

defined( 'ABSPATH' ) || exit;

$settings      = get_option( 'bkx_pwa_settings', array() );
$cache_manager = \BookingX\PWA\PWAAddon::get_instance()->get_service( 'cache_manager' );
$cache_stats   = $cache_manager->get_cache_stats();
?>

<div class="bkx-card">
	<h3><?php esc_html_e( 'Caching Strategy', 'bkx-pwa' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="cache_strategy"><?php esc_html_e( 'Strategy', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<select id="cache_strategy" name="cache_strategy">
					<option value="network-first" <?php selected( $settings['cache_strategy'] ?? '', 'network-first' ); ?>>
						<?php esc_html_e( 'Network First (Recommended)', 'bkx-pwa' ); ?>
					</option>
					<option value="cache-first" <?php selected( $settings['cache_strategy'] ?? '', 'cache-first' ); ?>>
						<?php esc_html_e( 'Cache First', 'bkx-pwa' ); ?>
					</option>
					<option value="stale-while-revalidate" <?php selected( $settings['cache_strategy'] ?? '', 'stale-while-revalidate' ); ?>>
						<?php esc_html_e( 'Stale While Revalidate', 'bkx-pwa' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How the service worker handles requests.', 'bkx-pwa' ); ?>
				</p>

				<div class="bkx-strategy-info">
					<details>
						<summary><?php esc_html_e( 'Strategy explanations', 'bkx-pwa' ); ?></summary>
						<ul>
							<li><strong><?php esc_html_e( 'Network First:', 'bkx-pwa' ); ?></strong>
								<?php esc_html_e( 'Always tries the network first, falls back to cache if offline. Best for dynamic content.', 'bkx-pwa' ); ?>
							</li>
							<li><strong><?php esc_html_e( 'Cache First:', 'bkx-pwa' ); ?></strong>
								<?php esc_html_e( 'Serves from cache immediately, updates in background. Best for static content.', 'bkx-pwa' ); ?>
							</li>
							<li><strong><?php esc_html_e( 'Stale While Revalidate:', 'bkx-pwa' ); ?></strong>
								<?php esc_html_e( 'Returns cached version immediately while fetching update. Good balance of speed and freshness.', 'bkx-pwa' ); ?>
							</li>
						</ul>
					</details>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="cache_expiry"><?php esc_html_e( 'Cache Expiry', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<select id="cache_expiry" name="cache_expiry">
					<option value="3600" <?php selected( $settings['cache_expiry'] ?? 86400, 3600 ); ?>>
						<?php esc_html_e( '1 hour', 'bkx-pwa' ); ?>
					</option>
					<option value="21600" <?php selected( $settings['cache_expiry'] ?? 86400, 21600 ); ?>>
						<?php esc_html_e( '6 hours', 'bkx-pwa' ); ?>
					</option>
					<option value="43200" <?php selected( $settings['cache_expiry'] ?? 86400, 43200 ); ?>>
						<?php esc_html_e( '12 hours', 'bkx-pwa' ); ?>
					</option>
					<option value="86400" <?php selected( $settings['cache_expiry'] ?? 86400, 86400 ); ?>>
						<?php esc_html_e( '24 hours', 'bkx-pwa' ); ?>
					</option>
					<option value="604800" <?php selected( $settings['cache_expiry'] ?? 86400, 604800 ); ?>>
						<?php esc_html_e( '7 days', 'bkx-pwa' ); ?>
					</option>
				</select>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-card">
	<h3><?php esc_html_e( 'Offline Bookings', 'bkx-pwa' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Offline Bookings', 'bkx-pwa' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="offline_bookings" value="1"
						   <?php checked( $settings['offline_bookings'] ?? true ); ?>>
					<?php esc_html_e( 'Allow users to create bookings while offline', 'bkx-pwa' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Bookings are stored locally and synced when back online.', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<div class="bkx-offline-info">
		<h4><?php esc_html_e( 'How Offline Bookings Work', 'bkx-pwa' ); ?></h4>
		<ol>
			<li><?php esc_html_e( 'User creates a booking while offline', 'bkx-pwa' ); ?></li>
			<li><?php esc_html_e( 'Booking is stored in the browser\'s IndexedDB', 'bkx-pwa' ); ?></li>
			<li><?php esc_html_e( 'When online, bookings automatically sync to server', 'bkx-pwa' ); ?></li>
			<li><?php esc_html_e( 'User receives confirmation after sync', 'bkx-pwa' ); ?></li>
		</ol>
	</div>
</div>

<div class="bkx-card">
	<h3><?php esc_html_e( 'Offline Page', 'bkx-pwa' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="offline_page"><?php esc_html_e( 'Custom Offline Page', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'             => 'offline_page_id',
						'id'               => 'offline_page',
						'selected'         => $settings['offline_page_id'] ?? 0,
						'show_option_none' => __( 'Use default offline page', 'bkx-pwa' ),
					)
				);
				?>
				<p class="description">
					<?php esc_html_e( 'Select a page to show when offline, or use the built-in page.', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-card">
	<h3><?php esc_html_e( 'Cache Status', 'bkx-pwa' ); ?></h3>

	<div class="bkx-cache-stats">
		<div class="bkx-stat-item">
			<span class="bkx-stat-value"><?php echo esc_html( $cache_stats['precache_count'] ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Precached URLs', 'bkx-pwa' ); ?></span>
		</div>
		<div class="bkx-stat-item">
			<span class="bkx-stat-value"><?php echo esc_html( $cache_stats['cache_version'] ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Cache Version', 'bkx-pwa' ); ?></span>
		</div>
	</div>

	<p>
		<button type="button" class="button" id="bkx-clear-cache">
			<?php esc_html_e( 'Clear Cache', 'bkx-pwa' ); ?>
		</button>
		<span class="bkx-cache-status"></span>
	</p>
	<p class="description">
		<?php esc_html_e( 'Clears the service worker cache. Users will need to reload to get fresh content.', 'bkx-pwa' ); ?>
	</p>
</div>

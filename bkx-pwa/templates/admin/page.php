<?php
/**
 * PWA Admin Page.
 *
 * @package BookingX\PWA
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\PWA\PWAAddon::get_instance();
$settings = get_option( 'bkx_pwa_settings', array() );

// Handle form submission.
if ( isset( $_POST['bkx_save_pwa_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bkx_save_pwa_settings' ) ) {
	$settings['enabled']              = isset( $_POST['enabled'] );
	$settings['app_name']             = sanitize_text_field( wp_unslash( $_POST['app_name'] ?? '' ) );
	$settings['app_short_name']       = sanitize_text_field( wp_unslash( $_POST['app_short_name'] ?? '' ) );
	$settings['app_description']      = sanitize_textarea_field( wp_unslash( $_POST['app_description'] ?? '' ) );
	$settings['theme_color']          = sanitize_hex_color( wp_unslash( $_POST['theme_color'] ?? '#2563eb' ) );
	$settings['background_color']     = sanitize_hex_color( wp_unslash( $_POST['background_color'] ?? '#ffffff' ) );
	$settings['display']              = sanitize_text_field( wp_unslash( $_POST['display'] ?? 'standalone' ) );
	$settings['orientation']          = sanitize_text_field( wp_unslash( $_POST['orientation'] ?? 'any' ) );
	$settings['start_url']            = sanitize_text_field( wp_unslash( $_POST['start_url'] ?? '/' ) );
	$settings['cache_strategy']       = sanitize_text_field( wp_unslash( $_POST['cache_strategy'] ?? 'network-first' ) );
	$settings['cache_expiry']         = absint( $_POST['cache_expiry'] ?? 86400 );
	$settings['offline_bookings']     = isset( $_POST['offline_bookings'] );
	$settings['install_prompt']       = isset( $_POST['install_prompt'] );
	$settings['install_prompt_delay'] = absint( $_POST['install_prompt_delay'] ?? 30 );
	$settings['ios_splash_screens']   = isset( $_POST['ios_splash_screens'] );

	// Icons.
	$settings['icon_192'] = esc_url_raw( wp_unslash( $_POST['icon_192'] ?? '' ) );
	$settings['icon_512'] = esc_url_raw( wp_unslash( $_POST['icon_512'] ?? '' ) );

	update_option( 'bkx_pwa_settings', $settings );

	// Update cache version to invalidate service worker cache.
	update_option( 'bkx_pwa_cache_version', time() );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'PWA settings saved.', 'bkx-pwa' ) . '</p></div>';
}

$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
?>

<div class="wrap bkx-pwa-admin">
	<h1>
		<span class="bkx-pwa-logo"></span>
		<?php esc_html_e( 'Progressive Web App', 'bkx-pwa' ); ?>
	</h1>

	<!-- Status Banner -->
	<div class="bkx-status-banner <?php echo ! empty( $settings['enabled'] ) ? 'bkx-enabled' : 'bkx-disabled'; ?>">
		<?php if ( ! empty( $settings['enabled'] ) ) : ?>
			<span class="dashicons dashicons-yes-alt"></span>
			<div class="bkx-status-content">
				<strong><?php esc_html_e( 'PWA is Active', 'bkx-pwa' ); ?></strong>
				<p><?php esc_html_e( 'Your site is now an installable Progressive Web App.', 'bkx-pwa' ); ?></p>
			</div>
		<?php else : ?>
			<span class="dashicons dashicons-warning"></span>
			<div class="bkx-status-content">
				<strong><?php esc_html_e( 'PWA is Disabled', 'bkx-pwa' ); ?></strong>
				<p><?php esc_html_e( 'Enable PWA to make your site installable.', 'bkx-pwa' ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pwa&tab=general' ) ); ?>"
		   class="nav-tab <?php echo 'general' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'General', 'bkx-pwa' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pwa&tab=appearance' ) ); ?>"
		   class="nav-tab <?php echo 'appearance' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Appearance', 'bkx-pwa' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pwa&tab=offline' ) ); ?>"
		   class="nav-tab <?php echo 'offline' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Offline', 'bkx-pwa' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pwa&tab=install' ) ); ?>"
		   class="nav-tab <?php echo 'install' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Install Prompt', 'bkx-pwa' ); ?>
		</a>
	</nav>

	<form method="post" class="bkx-pwa-form">
		<?php wp_nonce_field( 'bkx_save_pwa_settings' ); ?>

		<div class="bkx-pwa-content">
			<?php
			switch ( $current_tab ) {
				case 'appearance':
					include BKX_PWA_PLUGIN_DIR . 'templates/admin/appearance.php';
					break;
				case 'offline':
					include BKX_PWA_PLUGIN_DIR . 'templates/admin/offline.php';
					break;
				case 'install':
					include BKX_PWA_PLUGIN_DIR . 'templates/admin/install.php';
					break;
				default:
					include BKX_PWA_PLUGIN_DIR . 'templates/admin/general.php';
					break;
			}
			?>
		</div>

		<p class="submit">
			<button type="submit" name="bkx_save_pwa_settings" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-pwa' ); ?>
			</button>
		</p>
	</form>
</div>

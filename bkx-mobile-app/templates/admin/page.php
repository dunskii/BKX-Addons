<?php
/**
 * Mobile App Admin Page.
 *
 * @package BookingX\MobileApp
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\MobileApp\MobileAppAddon::get_instance();
$settings = get_option( 'bkx_mobile_app_settings', array() );

$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
?>

<div class="wrap bkx-mobile-app-admin">
	<h1>
		<span class="bkx-mobile-logo"></span>
		<?php esc_html_e( 'Mobile App Framework', 'bkx-mobile-app' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-mobile-app&tab=dashboard' ) ); ?>"
		   class="nav-tab <?php echo 'dashboard' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Dashboard', 'bkx-mobile-app' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-mobile-app&tab=api' ) ); ?>"
		   class="nav-tab <?php echo 'api' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'API Keys', 'bkx-mobile-app' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-mobile-app&tab=devices' ) ); ?>"
		   class="nav-tab <?php echo 'devices' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Devices', 'bkx-mobile-app' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-mobile-app&tab=push' ) ); ?>"
		   class="nav-tab <?php echo 'push' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Push Notifications', 'bkx-mobile-app' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-mobile-app&tab=settings' ) ); ?>"
		   class="nav-tab <?php echo 'settings' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-mobile-app' ); ?>
		</a>
	</nav>

	<div class="bkx-mobile-app-content">
		<?php
		switch ( $current_tab ) {
			case 'api':
				include BKX_MOBILE_APP_PLUGIN_DIR . 'templates/admin/api.php';
				break;
			case 'devices':
				include BKX_MOBILE_APP_PLUGIN_DIR . 'templates/admin/devices.php';
				break;
			case 'push':
				include BKX_MOBILE_APP_PLUGIN_DIR . 'templates/admin/push.php';
				break;
			case 'settings':
				include BKX_MOBILE_APP_PLUGIN_DIR . 'templates/admin/settings.php';
				break;
			default:
				include BKX_MOBILE_APP_PLUGIN_DIR . 'templates/admin/dashboard.php';
				break;
		}
		?>
	</div>
</div>

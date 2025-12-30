<?php
/**
 * HIPAA Compliance Admin Page.
 *
 * @package BookingX\HIPAA
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\HIPAA\HIPAAAddon::get_instance();
$settings = get_option( 'bkx_hipaa_settings', array() );

$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
?>

<div class="wrap bkx-hipaa-admin">
	<h1>
		<span class="bkx-hipaa-logo"></span>
		<?php esc_html_e( 'HIPAA Compliance', 'bkx-hipaa' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hipaa&tab=dashboard' ) ); ?>"
		   class="nav-tab <?php echo 'dashboard' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Dashboard', 'bkx-hipaa' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hipaa&tab=audit' ) ); ?>"
		   class="nav-tab <?php echo 'audit' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Audit Log', 'bkx-hipaa' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hipaa&tab=access' ) ); ?>"
		   class="nav-tab <?php echo 'access' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Access Control', 'bkx-hipaa' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hipaa&tab=baa' ) ); ?>"
		   class="nav-tab <?php echo 'baa' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'BAA Management', 'bkx-hipaa' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hipaa&tab=settings' ) ); ?>"
		   class="nav-tab <?php echo 'settings' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-hipaa' ); ?>
		</a>
	</nav>

	<div class="bkx-hipaa-content">
		<?php
		switch ( $current_tab ) {
			case 'audit':
				include BKX_HIPAA_PLUGIN_DIR . 'templates/admin/audit.php';
				break;
			case 'access':
				include BKX_HIPAA_PLUGIN_DIR . 'templates/admin/access.php';
				break;
			case 'baa':
				include BKX_HIPAA_PLUGIN_DIR . 'templates/admin/baa.php';
				break;
			case 'settings':
				include BKX_HIPAA_PLUGIN_DIR . 'templates/admin/settings.php';
				break;
			default:
				include BKX_HIPAA_PLUGIN_DIR . 'templates/admin/dashboard.php';
				break;
		}
		?>
	</div>
</div>

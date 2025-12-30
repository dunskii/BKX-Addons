<?php
/**
 * Divi Integration Admin Page.
 *
 * @package BookingX\Divi
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\Divi\DiviAddon::get_instance();
$settings = get_option( 'bkx_divi_settings', array() );

$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
?>

<div class="wrap bkx-divi-admin">
	<h1>
		<span class="bkx-divi-logo"></span>
		<?php esc_html_e( 'Divi Integration', 'bkx-divi' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-divi&tab=settings' ) ); ?>"
		   class="nav-tab <?php echo 'settings' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-divi' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-divi&tab=modules' ) ); ?>"
		   class="nav-tab <?php echo 'modules' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Modules', 'bkx-divi' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-divi&tab=styling' ) ); ?>"
		   class="nav-tab <?php echo 'styling' === $current_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Styling', 'bkx-divi' ); ?>
		</a>
	</nav>

	<div class="bkx-divi-content">
		<?php
		switch ( $current_tab ) {
			case 'modules':
				include BKX_DIVI_PLUGIN_DIR . 'templates/admin/modules.php';
				break;
			case 'styling':
				include BKX_DIVI_PLUGIN_DIR . 'templates/admin/styling.php';
				break;
			default:
				include BKX_DIVI_PLUGIN_DIR . 'templates/admin/settings.php';
				break;
		}
		?>
	</div>
</div>

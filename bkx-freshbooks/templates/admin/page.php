<?php
/**
 * FreshBooks Integration Admin Page.
 *
 * @package BookingX\FreshBooks
 */

defined( 'ABSPATH' ) || exit;

$addon      = \BookingX\FreshBooks\FreshBooksAddon::get_instance();
$settings   = get_option( 'bkx_freshbooks_settings', array() );
$connected  = $addon->is_connected();
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';

$tabs = array(
	'settings' => __( 'Settings', 'bkx-freshbooks' ),
	'sync'     => __( 'Sync', 'bkx-freshbooks' ),
	'logs'     => __( 'Logs', 'bkx-freshbooks' ),
);
?>
<div class="wrap bkx-freshbooks-admin">
	<h1>
		<span class="bkx-freshbooks-logo"></span>
		<?php esc_html_e( 'BookingX FreshBooks Integration', 'bkx-freshbooks' ); ?>
	</h1>

	<?php if ( isset( $_GET['connected'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Successfully connected to FreshBooks!', 'bkx-freshbooks' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_slug => $tab_name ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-freshbooks&tab=' . $tab_slug ) ); ?>"
			   class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-freshbooks-content">
		<?php
		$template = BKX_FRESHBOOKS_PLUGIN_DIR . 'templates/admin/' . $active_tab . '.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		?>
	</div>
</div>

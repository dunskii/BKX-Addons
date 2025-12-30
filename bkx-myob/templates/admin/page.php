<?php
/**
 * MYOB Integration Admin Page.
 *
 * @package BookingX\MYOB
 */

defined( 'ABSPATH' ) || exit;

$addon      = \BookingX\MYOB\MYOBAddon::get_instance();
$settings   = get_option( 'bkx_myob_settings', array() );
$connected  = $addon->is_connected();
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';

$tabs = array(
	'settings' => __( 'Settings', 'bkx-myob' ),
	'sync'     => __( 'Sync', 'bkx-myob' ),
	'logs'     => __( 'Logs', 'bkx-myob' ),
);
?>
<div class="wrap bkx-myob-admin">
	<h1>
		<span class="bkx-myob-logo"></span>
		<?php esc_html_e( 'BookingX MYOB Integration', 'bkx-myob' ); ?>
	</h1>

	<?php if ( isset( $_GET['connected'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Successfully connected to MYOB!', 'bkx-myob' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_slug => $tab_name ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-myob&tab=' . $tab_slug ) ); ?>"
			   class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-myob-content">
		<?php if ( 'settings' === $active_tab ) : ?>
			<?php include BKX_MYOB_PLUGIN_DIR . 'templates/admin/settings.php'; ?>
		<?php elseif ( 'sync' === $active_tab ) : ?>
			<?php include BKX_MYOB_PLUGIN_DIR . 'templates/admin/sync.php'; ?>
		<?php elseif ( 'logs' === $active_tab ) : ?>
			<?php include BKX_MYOB_PLUGIN_DIR . 'templates/admin/logs.php'; ?>
		<?php endif; ?>
	</div>
</div>

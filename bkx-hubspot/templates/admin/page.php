<?php
/**
 * Main admin page template.
 *
 * @package BookingX\HubSpot
 */

defined( 'ABSPATH' ) || exit;

$addon        = \BookingX\HubSpot\HubSpotAddon::get_instance();
$is_connected = $addon->is_connected();
$settings     = $addon->get_settings();
$current_tab  = $tab ?? 'dashboard';

$tabs = array(
	'dashboard' => __( 'Dashboard', 'bkx-hubspot' ),
	'mappings'  => __( 'Property Mappings', 'bkx-hubspot' ),
	'logs'      => __( 'Sync Logs', 'bkx-hubspot' ),
	'settings'  => __( 'Settings', 'bkx-hubspot' ),
);
?>

<div class="wrap bkx-hs-admin">
	<h1><?php esc_html_e( 'HubSpot Integration', 'bkx-hubspot' ); ?></h1>

	<?php if ( isset( $_GET['success'] ) && 'connected' === $_GET['success'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Successfully connected to HubSpot!', 'bkx-hubspot' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['error'] ) && 'oauth_failed' === $_GET['error'] ) : ?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php esc_html_e( 'Failed to connect to HubSpot.', 'bkx-hubspot' ); ?>
				<?php if ( isset( $_GET['message'] ) ) : ?>
					<?php echo esc_html( urldecode( $_GET['message'] ) ); ?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-hubspot&tab=' . $tab_id ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-hs-content">
		<?php
		switch ( $current_tab ) {
			case 'mappings':
				include BKX_HUBSPOT_PATH . 'templates/admin/mappings.php';
				break;
			case 'logs':
				include BKX_HUBSPOT_PATH . 'templates/admin/logs.php';
				break;
			case 'settings':
				include BKX_HUBSPOT_PATH . 'templates/admin/settings.php';
				break;
			default:
				include BKX_HUBSPOT_PATH . 'templates/admin/dashboard.php';
				break;
		}
		?>
	</div>
</div>

<?php
/**
 * Main admin page template.
 *
 * @package BookingX\Salesforce
 */

defined( 'ABSPATH' ) || exit;

$addon        = \BookingX\Salesforce\SalesforceAddon::get_instance();
$is_connected = $addon->is_connected();
$settings     = $addon->get_settings();
$current_tab  = $tab ?? 'dashboard';

$tabs = array(
	'dashboard' => __( 'Dashboard', 'bkx-salesforce' ),
	'mappings'  => __( 'Field Mappings', 'bkx-salesforce' ),
	'logs'      => __( 'Sync Logs', 'bkx-salesforce' ),
	'settings'  => __( 'Settings', 'bkx-salesforce' ),
);
?>

<div class="wrap bkx-sf-admin">
	<h1><?php esc_html_e( 'Salesforce Integration', 'bkx-salesforce' ); ?></h1>

	<?php if ( isset( $_GET['success'] ) && 'connected' === $_GET['success'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Successfully connected to Salesforce!', 'bkx-salesforce' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['error'] ) && 'oauth_failed' === $_GET['error'] ) : ?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php esc_html_e( 'Failed to connect to Salesforce.', 'bkx-salesforce' ); ?>
				<?php if ( isset( $_GET['message'] ) ) : ?>
					<?php echo esc_html( urldecode( $_GET['message'] ) ); ?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-salesforce&tab=' . $tab_id ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-sf-content">
		<?php
		switch ( $current_tab ) {
			case 'mappings':
				include BKX_SALESFORCE_PATH . 'templates/admin/mappings.php';
				break;
			case 'logs':
				include BKX_SALESFORCE_PATH . 'templates/admin/logs.php';
				break;
			case 'settings':
				include BKX_SALESFORCE_PATH . 'templates/admin/settings.php';
				break;
			default:
				include BKX_SALESFORCE_PATH . 'templates/admin/dashboard.php';
				break;
		}
		?>
	</div>
</div>

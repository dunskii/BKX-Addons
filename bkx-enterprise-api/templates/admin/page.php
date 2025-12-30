<?php
/**
 * Enterprise API Admin Page Template.
 *
 * @package BookingX\EnterpriseAPI
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'dashboard' => __( 'Dashboard', 'bkx-enterprise-api' ),
	'api-keys'  => __( 'API Keys', 'bkx-enterprise-api' ),
	'oauth'     => __( 'OAuth Clients', 'bkx-enterprise-api' ),
	'webhooks'  => __( 'Webhooks', 'bkx-enterprise-api' ),
	'logs'      => __( 'Request Logs', 'bkx-enterprise-api' ),
	'docs'      => __( 'Documentation', 'bkx-enterprise-api' ),
	'settings'  => __( 'Settings', 'bkx-enterprise-api' ),
);
?>
<div class="wrap bkx-enterprise-api">
	<h1><?php esc_html_e( 'Enterprise API Suite', 'bkx-enterprise-api' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-enterprise-api&tab=' . $tab_id ) ); ?>" class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="tab-content">
		<?php
		switch ( $tab ) {
			case 'api-keys':
				include BKX_ENTERPRISE_API_PATH . 'templates/admin/api-keys.php';
				break;
			case 'oauth':
				include BKX_ENTERPRISE_API_PATH . 'templates/admin/oauth.php';
				break;
			case 'webhooks':
				include BKX_ENTERPRISE_API_PATH . 'templates/admin/webhooks.php';
				break;
			case 'logs':
				include BKX_ENTERPRISE_API_PATH . 'templates/admin/logs.php';
				break;
			case 'docs':
				include BKX_ENTERPRISE_API_PATH . 'templates/admin/docs.php';
				break;
			case 'settings':
				include BKX_ENTERPRISE_API_PATH . 'templates/admin/settings.php';
				break;
			default:
				include BKX_ENTERPRISE_API_PATH . 'templates/admin/dashboard.php';
		}
		?>
	</div>
</div>

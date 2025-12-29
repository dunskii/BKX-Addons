<?php
/**
 * Admin settings page template.
 *
 * @package BookingX\Slack
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_slack_settings', array() );
$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';

// Get workspaces.
global $wpdb;
$workspaces = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bkx_slack_workspaces ORDER BY connected_at DESC" );

// Check for success/error messages.
$success = isset( $_GET['success'] ) ? sanitize_text_field( wp_unslash( $_GET['success'] ) ) : '';
$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
?>

<div class="wrap bkx-slack-admin">
	<h1>
		<span class="bkx-slack-logo"></span>
		<?php esc_html_e( 'Slack Integration', 'bkx-slack' ); ?>
	</h1>

	<?php if ( $success ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				switch ( $success ) {
					case 'connected':
						esc_html_e( 'Workspace connected successfully!', 'bkx-slack' );
						break;
					default:
						echo esc_html( $success );
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $error ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-slack&tab=settings' ) ); ?>"
		   class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-slack' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-slack&tab=workspaces' ) ); ?>"
		   class="nav-tab <?php echo 'workspaces' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Workspaces', 'bkx-slack' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-slack&tab=notifications' ) ); ?>"
		   class="nav-tab <?php echo 'notifications' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Notifications', 'bkx-slack' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-slack&tab=logs' ) ); ?>"
		   class="nav-tab <?php echo 'logs' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Logs', 'bkx-slack' ); ?>
		</a>
	</nav>

	<div class="bkx-slack-content">
		<?php
		switch ( $tab ) {
			case 'workspaces':
				include BKX_SLACK_PATH . 'templates/admin/workspaces.php';
				break;

			case 'notifications':
				include BKX_SLACK_PATH . 'templates/admin/notifications.php';
				break;

			case 'logs':
				include BKX_SLACK_PATH . 'templates/admin/logs.php';
				break;

			default:
				include BKX_SLACK_PATH . 'templates/admin/settings.php';
				break;
		}
		?>
	</div>
</div>

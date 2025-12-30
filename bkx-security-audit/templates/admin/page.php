<?php
/**
 * Security & Audit admin page.
 *
 * @package BookingX\SecurityAudit
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'dashboard' => __( 'Dashboard', 'bkx-security-audit' ),
	'audit-log' => __( 'Audit Log', 'bkx-security-audit' ),
	'lockouts'  => __( 'Lockouts', 'bkx-security-audit' ),
	'events'    => __( 'Security Events', 'bkx-security-audit' ),
	'scanner'   => __( 'Scanner', 'bkx-security-audit' ),
	'files'     => __( 'File Integrity', 'bkx-security-audit' ),
	'settings'  => __( 'Settings', 'bkx-security-audit' ),
);
?>

<div class="wrap bkx-security-admin">
	<h1><?php esc_html_e( 'Security & Audit', 'bkx-security-audit' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_slug => $tab_title ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-security-audit&tab=' . $tab_slug ) ); ?>"
			   class="nav-tab <?php echo $tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_title ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-security-content">
		<?php
		$template = BKX_SECURITY_AUDIT_PATH . 'templates/admin/' . $tab . '.php';
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			include BKX_SECURITY_AUDIT_PATH . 'templates/admin/dashboard.php';
		}
		?>
	</div>
</div>

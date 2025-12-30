<?php
/**
 * Main admin page template.
 *
 * @package BookingX\BackupRecovery
 */

defined( 'ABSPATH' ) || exit;

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'backups';
$tabs       = array(
	'backups'  => __( 'Backups', 'bkx-backup-recovery' ),
	'restore'  => __( 'Restore History', 'bkx-backup-recovery' ),
	'export'   => __( 'Export Data', 'bkx-backup-recovery' ),
	'import'   => __( 'Import Data', 'bkx-backup-recovery' ),
	'settings' => __( 'Settings', 'bkx-backup-recovery' ),
);
?>
<div class="wrap bkx-backup-recovery">
	<h1><?php esc_html_e( 'Data Backup & Recovery', 'bkx-backup-recovery' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id ) ); ?>"
			   class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="tab-content">
		<?php
		switch ( $active_tab ) {
			case 'backups':
				include BKX_BACKUP_PATH . 'templates/admin/backups.php';
				break;

			case 'restore':
				include BKX_BACKUP_PATH . 'templates/admin/restore-history.php';
				break;

			case 'export':
				include BKX_BACKUP_PATH . 'templates/admin/export.php';
				break;

			case 'import':
				include BKX_BACKUP_PATH . 'templates/admin/import.php';
				break;

			case 'settings':
				include BKX_BACKUP_PATH . 'templates/admin/settings.php';
				break;
		}
		?>
	</div>
</div>

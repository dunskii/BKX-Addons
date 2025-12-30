<?php
/**
 * Security dashboard template.
 *
 * @package BookingX\SecurityAudit
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\SecurityAudit\SecurityAuditAddon::get_instance();
$scanner  = $addon->get_service( 'scanner' );
$login    = $addon->get_service( 'login' );
$files    = $addon->get_service( 'files' );

$last_scan = $scanner ? $scanner->get_last_scan() : null;
$score     = $last_scan ? $last_scan['score'] : null;

// Get stats.
global $wpdb;

// Pending security events.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$pending_events = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_security_events WHERE resolved = 0"
);

// Active lockouts.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$active_lockouts = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_ip_lockouts WHERE locked_until > %s AND unlocked_at IS NULL",
		current_time( 'mysql' )
	)
);

// Failed logins today.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$failed_logins = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_login_attempts WHERE success = 0 AND created_at >= %s",
		gmdate( 'Y-m-d 00:00:00' )
	)
);

// Recent audit logs.
$audit = $addon->get_service( 'audit' );
$recent_logs = $audit ? $audit->get_logs( array( 'per_page' => 5 ) ) : array( 'logs' => array() );
?>

<div class="bkx-security-dashboard">
	<!-- Stats Row -->
	<div class="bkx-security-stats-row">
		<div class="bkx-security-stat-card <?php echo ( $score !== null && $score < 70 ) ? 'bkx-stat-warning' : ''; ?>">
			<span class="bkx-security-stat-value <?php echo ( $score !== null && $score >= 70 ) ? 'bkx-stat-ok' : ''; ?>">
				<?php echo $score !== null ? esc_html( $score ) : '—'; ?>
			</span>
			<span class="bkx-security-stat-label"><?php esc_html_e( 'Security Score', 'bkx-security-audit' ); ?></span>
			<?php if ( $last_scan ) : ?>
				<span class="bkx-security-stat-date">
					<?php
					printf(
						/* translators: %s: date */
						esc_html__( 'Last scan: %s', 'bkx-security-audit' ),
						esc_html( human_time_diff( strtotime( $last_scan['timestamp'] ) ) . ' ago' )
					);
					?>
				</span>
			<?php endif; ?>
		</div>

		<div class="bkx-security-stat-card <?php echo $pending_events > 0 ? 'bkx-stat-warning' : ''; ?>">
			<span class="bkx-security-stat-value"><?php echo esc_html( $pending_events ); ?></span>
			<span class="bkx-security-stat-label"><?php esc_html_e( 'Pending Events', 'bkx-security-audit' ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-security-audit&tab=events' ) ); ?>" class="bkx-security-stat-link">
				<?php esc_html_e( 'View all', 'bkx-security-audit' ); ?>
			</a>
		</div>

		<div class="bkx-security-stat-card">
			<span class="bkx-security-stat-value"><?php echo esc_html( $active_lockouts ); ?></span>
			<span class="bkx-security-stat-label"><?php esc_html_e( 'Active Lockouts', 'bkx-security-audit' ); ?></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-security-audit&tab=lockouts' ) ); ?>" class="bkx-security-stat-link">
				<?php esc_html_e( 'Manage', 'bkx-security-audit' ); ?>
			</a>
		</div>

		<div class="bkx-security-stat-card <?php echo $failed_logins > 50 ? 'bkx-stat-warning' : ''; ?>">
			<span class="bkx-security-stat-value"><?php echo esc_html( $failed_logins ); ?></span>
			<span class="bkx-security-stat-label"><?php esc_html_e( 'Failed Logins Today', 'bkx-security-audit' ); ?></span>
		</div>
	</div>

	<div class="bkx-security-row">
		<!-- Quick Actions -->
		<div class="bkx-security-half">
			<div class="bkx-security-card">
				<h2><?php esc_html_e( 'Quick Actions', 'bkx-security-audit' ); ?></h2>
				<div class="bkx-security-quick-actions">
					<button type="button" class="button button-primary" id="bkx-run-scan">
						<span class="dashicons dashicons-shield-alt"></span>
						<?php esc_html_e( 'Run Security Scan', 'bkx-security-audit' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-security-audit&tab=audit-log' ) ); ?>" class="button">
						<span class="dashicons dashicons-list-view"></span>
						<?php esc_html_e( 'View Audit Log', 'bkx-security-audit' ); ?>
					</a>
					<button type="button" class="button" id="bkx-export-logs">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Export Logs', 'bkx-security-audit' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-security-audit&tab=settings' ) ); ?>" class="button">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Settings', 'bkx-security-audit' ); ?>
					</a>
				</div>
			</div>

			<!-- Recent Issues -->
			<?php if ( $last_scan && ! empty( $last_scan['issues'] ) ) : ?>
				<div class="bkx-security-card">
					<h2><?php esc_html_e( 'Recent Issues', 'bkx-security-audit' ); ?></h2>
					<ul class="bkx-security-issues-list">
						<?php foreach ( array_slice( $last_scan['issues'], 0, 5 ) as $issue ) : ?>
							<li class="bkx-security-issue bkx-severity-<?php echo esc_attr( $issue['severity'] ); ?>">
								<span class="bkx-security-issue-badge"><?php echo esc_html( ucfirst( $issue['severity'] ) ); ?></span>
								<div class="bkx-security-issue-content">
									<strong><?php echo esc_html( $issue['title'] ); ?></strong>
									<p><?php echo esc_html( $issue['description'] ); ?></p>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>

		<!-- Recent Activity -->
		<div class="bkx-security-half">
			<div class="bkx-security-card">
				<div class="bkx-security-card-header">
					<h2><?php esc_html_e( 'Recent Activity', 'bkx-security-audit' ); ?></h2>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-security-audit&tab=audit-log' ) ); ?>" class="button button-small">
						<?php esc_html_e( 'View All', 'bkx-security-audit' ); ?>
					</a>
				</div>
				<?php if ( ! empty( $recent_logs['logs'] ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Action', 'bkx-security-audit' ); ?></th>
								<th><?php esc_html_e( 'User', 'bkx-security-audit' ); ?></th>
								<th><?php esc_html_e( 'Time', 'bkx-security-audit' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_logs['logs'] as $log ) : ?>
								<tr>
									<td>
										<span class="bkx-action-badge bkx-action-<?php echo esc_attr( str_replace( '_', '-', $log['action'] ) ); ?>">
											<?php echo esc_html( str_replace( '_', ' ', $log['action'] ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $log['user_email'] ?: __( 'Guest', 'bkx-security-audit' ) ); ?></td>
									<td><?php echo esc_html( human_time_diff( strtotime( $log['created_at'] ) ) . ' ago' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="bkx-security-no-data"><?php esc_html_e( 'No recent activity.', 'bkx-security-audit' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Security Checklist -->
			<div class="bkx-security-card">
				<h2><?php esc_html_e( 'Security Checklist', 'bkx-security-audit' ); ?></h2>
				<ul class="bkx-security-checklist">
					<li class="<?php echo is_ssl() ? 'complete' : 'incomplete'; ?>">
						<?php esc_html_e( 'SSL/HTTPS enabled', 'bkx-security-audit' ); ?>
					</li>
					<li class="<?php echo ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) ? 'complete' : 'incomplete'; ?>">
						<?php esc_html_e( 'File editor disabled', 'bkx-security-audit' ); ?>
					</li>
					<li class="<?php echo ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ? 'complete' : 'incomplete'; ?>">
						<?php esc_html_e( 'Debug mode disabled', 'bkx-security-audit' ); ?>
					</li>
					<li class="<?php echo $score !== null && $score >= 80 ? 'complete' : 'incomplete'; ?>">
						<?php esc_html_e( 'Security scan passed', 'bkx-security-audit' ); ?>
					</li>
					<li class="<?php echo $pending_events === '0' ? 'complete' : 'incomplete'; ?>">
						<?php esc_html_e( 'No pending security events', 'bkx-security-audit' ); ?>
					</li>
				</ul>
			</div>
		</div>
	</div>
</div>

<!-- Scan Results Modal -->
<div id="bkx-scan-results-modal" class="bkx-security-modal" style="display: none;">
	<div class="bkx-security-modal-content">
		<span class="bkx-security-modal-close">&times;</span>
		<h2><?php esc_html_e( 'Security Scan Results', 'bkx-security-audit' ); ?></h2>
		<div id="bkx-scan-results"></div>
	</div>
</div>

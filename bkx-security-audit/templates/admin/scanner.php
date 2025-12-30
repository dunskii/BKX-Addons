<?php
/**
 * Security scanner template.
 *
 * @package BookingX\SecurityAudit
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\SecurityAudit\SecurityAuditAddon::get_instance();
$scanner   = $addon->get_service( 'scanner' );
$last_scan = $scanner ? $scanner->get_last_scan() : null;
?>

<div class="bkx-security-scanner">
	<div class="bkx-security-row">
		<div class="bkx-security-half">
			<div class="bkx-security-card">
				<h2><?php esc_html_e( 'Security Scanner', 'bkx-security-audit' ); ?></h2>
				<p><?php esc_html_e( 'Run a comprehensive security scan to identify vulnerabilities and misconfigurations.', 'bkx-security-audit' ); ?></p>

				<div class="bkx-scanner-action">
					<button type="button" class="button button-primary button-hero" id="bkx-run-full-scan">
						<span class="dashicons dashicons-shield-alt"></span>
						<?php esc_html_e( 'Run Security Scan', 'bkx-security-audit' ); ?>
					</button>
				</div>

				<div id="bkx-scan-progress" style="display: none;">
					<div class="bkx-progress-bar">
						<div class="bkx-progress-fill"></div>
					</div>
					<p class="bkx-scan-status"><?php esc_html_e( 'Scanning...', 'bkx-security-audit' ); ?></p>
				</div>

				<?php if ( $last_scan ) : ?>
					<p class="bkx-last-scan">
						<?php
						printf(
							/* translators: %s: time ago */
							esc_html__( 'Last scan: %s', 'bkx-security-audit' ),
							esc_html( human_time_diff( strtotime( $last_scan['timestamp'] ) ) . ' ago' )
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<!-- Scan checks info -->
			<div class="bkx-security-card">
				<h2><?php esc_html_e( 'What We Check', 'bkx-security-audit' ); ?></h2>
				<ul class="bkx-check-list">
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'WordPress version and updates', 'bkx-security-audit' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'PHP version security', 'bkx-security-audit' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'SSL/HTTPS configuration', 'bkx-security-audit' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'File permissions', 'bkx-security-audit' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Debug mode settings', 'bkx-security-audit' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Database prefix security', 'bkx-security-audit' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Security keys strength', 'bkx-security-audit' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'File editor status', 'bkx-security-audit' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Directory listing exposure', 'bkx-security-audit' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'User enumeration protection', 'bkx-security-audit' ); ?></li>
				</ul>
			</div>
		</div>

		<div class="bkx-security-half">
			<?php if ( $last_scan ) : ?>
				<!-- Score -->
				<div class="bkx-security-card bkx-score-card">
					<div class="bkx-score-circle <?php echo $last_scan['score'] >= 80 ? 'bkx-score-good' : ( $last_scan['score'] >= 60 ? 'bkx-score-warning' : 'bkx-score-bad' ); ?>">
						<span class="bkx-score-value"><?php echo esc_html( $last_scan['score'] ); ?></span>
						<span class="bkx-score-label"><?php esc_html_e( 'Score', 'bkx-security-audit' ); ?></span>
					</div>
					<div class="bkx-score-summary">
						<p>
							<?php if ( $last_scan['score'] >= 80 ) : ?>
								<?php esc_html_e( 'Good security posture. Keep up the good work!', 'bkx-security-audit' ); ?>
							<?php elseif ( $last_scan['score'] >= 60 ) : ?>
								<?php esc_html_e( 'Some improvements recommended.', 'bkx-security-audit' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Critical issues detected. Immediate action required.', 'bkx-security-audit' ); ?>
							<?php endif; ?>
						</p>
						<ul>
							<li><?php echo esc_html( count( $last_scan['issues'] ) ); ?> <?php esc_html_e( 'issues', 'bkx-security-audit' ); ?></li>
							<li><?php echo esc_html( count( $last_scan['warnings'] ) ); ?> <?php esc_html_e( 'warnings', 'bkx-security-audit' ); ?></li>
						</ul>
					</div>
				</div>

				<!-- Issues -->
				<?php if ( ! empty( $last_scan['issues'] ) ) : ?>
					<div class="bkx-security-card">
						<h2><?php esc_html_e( 'Issues Found', 'bkx-security-audit' ); ?></h2>
						<ul class="bkx-issues-list">
							<?php foreach ( $last_scan['issues'] as $issue ) : ?>
								<li class="bkx-issue bkx-severity-<?php echo esc_attr( $issue['severity'] ); ?>">
									<span class="bkx-issue-badge"><?php echo esc_html( ucfirst( $issue['severity'] ) ); ?></span>
									<div class="bkx-issue-content">
										<strong><?php echo esc_html( $issue['title'] ); ?></strong>
										<p><?php echo esc_html( $issue['description'] ); ?></p>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<!-- Warnings -->
				<?php if ( ! empty( $last_scan['warnings'] ) ) : ?>
					<div class="bkx-security-card">
						<h2><?php esc_html_e( 'Warnings', 'bkx-security-audit' ); ?></h2>
						<ul class="bkx-warnings-list">
							<?php foreach ( $last_scan['warnings'] as $warning ) : ?>
								<li class="bkx-warning">
									<span class="dashicons dashicons-warning"></span>
									<div>
										<strong><?php echo esc_html( $warning['title'] ); ?></strong>
										<p><?php echo esc_html( $warning['description'] ); ?></p>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<!-- Info -->
				<?php if ( ! empty( $last_scan['info'] ) ) : ?>
					<div class="bkx-security-card">
						<h2><?php esc_html_e( 'System Information', 'bkx-security-audit' ); ?></h2>
						<table class="widefat">
							<?php foreach ( $last_scan['info'] as $info ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $info['title'] ); ?></strong></td>
									<td><?php echo esc_html( $info['value'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</table>
					</div>
				<?php endif; ?>

			<?php else : ?>
				<div class="bkx-security-card">
					<div class="bkx-no-scan">
						<span class="dashicons dashicons-shield-alt"></span>
						<h3><?php esc_html_e( 'No Scan Results', 'bkx-security-audit' ); ?></h3>
						<p><?php esc_html_e( 'Run your first security scan to see results here.', 'bkx-security-audit' ); ?></p>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

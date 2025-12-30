<?php
/**
 * PCI Compliance Dashboard template.
 *
 * @package BookingX\PCICompliance
 */

defined( 'ABSPATH' ) || exit;

$scanner        = new \BookingX\PCICompliance\Services\ComplianceScanner();
$audit_logger   = new \BookingX\PCICompliance\Services\PCIAuditLogger();
$vuln_scanner   = new \BookingX\PCICompliance\Services\VulnerabilityScanner();
$key_manager    = new \BookingX\PCICompliance\Services\KeyManager();

$scan_history   = $scanner->get_scan_history( 1 );
$last_scan      = ! empty( $scan_history ) ? $scan_history[0] : null;
$stats          = $audit_logger->get_statistics( 30 );
$vulnerabilities = $vuln_scanner->get_vulnerabilities( array( 'status' => 'open' ) );
$key_status     = $key_manager->get_key_status();
?>
<div class="wrap bkx-pci-compliance">
	<h1><?php esc_html_e( 'PCI DSS Compliance Dashboard', 'bkx-pci-compliance' ); ?></h1>

	<div class="bkx-dashboard-grid">
		<!-- Compliance Score Card -->
		<div class="bkx-card bkx-card-large">
			<div class="bkx-card-header">
				<h2><?php esc_html_e( 'Compliance Score', 'bkx-pci-compliance' ); ?></h2>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pci-scan' ) ); ?>" class="button">
					<?php esc_html_e( 'Run Scan', 'bkx-pci-compliance' ); ?>
				</a>
			</div>
			<div class="bkx-card-body">
				<?php if ( $last_scan ) : ?>
					<div class="bkx-score-display">
						<div class="bkx-score-circle <?php echo $last_scan['overall_score'] >= 80 ? 'bkx-score-good' : ( $last_scan['overall_score'] >= 60 ? 'bkx-score-warning' : 'bkx-score-poor' ); ?>">
							<span class="bkx-score-value"><?php echo esc_html( round( $last_scan['overall_score'] ) ); ?>%</span>
						</div>
						<div class="bkx-score-details">
							<div class="bkx-score-item bkx-passed">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php
								printf(
									/* translators: %d: Number of passed requirements */
									esc_html__( '%d Passed', 'bkx-pci-compliance' ),
									$last_scan['requirements_passed']
								);
								?>
							</div>
							<div class="bkx-score-item bkx-failed">
								<span class="dashicons dashicons-dismiss"></span>
								<?php
								printf(
									/* translators: %d: Number of failed requirements */
									esc_html__( '%d Failed', 'bkx-pci-compliance' ),
									$last_scan['requirements_failed']
								);
								?>
							</div>
							<div class="bkx-score-item">
								<span class="dashicons dashicons-minus"></span>
								<?php
								printf(
									/* translators: %d: Number of N/A requirements */
									esc_html__( '%d N/A', 'bkx-pci-compliance' ),
									$last_scan['requirements_na']
								);
								?>
							</div>
						</div>
					</div>
					<p class="bkx-last-scan">
						<?php
						printf(
							/* translators: %s: Last scan date */
							esc_html__( 'Last scan: %s', 'bkx-pci-compliance' ),
							esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_scan['completed_at'] ) ) )
						);
						?>
					</p>
				<?php else : ?>
					<div class="bkx-no-data">
						<span class="dashicons dashicons-shield"></span>
						<p><?php esc_html_e( 'No compliance scan has been run yet.', 'bkx-pci-compliance' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pci-scan' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Run First Scan', 'bkx-pci-compliance' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Vulnerabilities Card -->
		<div class="bkx-card">
			<div class="bkx-card-header">
				<h2><?php esc_html_e( 'Open Vulnerabilities', 'bkx-pci-compliance' ); ?></h2>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pci-vulnerabilities' ) ); ?>" class="button-link">
					<?php esc_html_e( 'View All', 'bkx-pci-compliance' ); ?>
				</a>
			</div>
			<div class="bkx-card-body">
				<div class="bkx-vuln-summary">
					<div class="bkx-vuln-count bkx-vuln-critical">
						<span class="count"><?php echo esc_html( $vulnerabilities['counts']['critical'] ); ?></span>
						<span class="label"><?php esc_html_e( 'Critical', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-vuln-count bkx-vuln-high">
						<span class="count"><?php echo esc_html( $vulnerabilities['counts']['high'] ); ?></span>
						<span class="label"><?php esc_html_e( 'High', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-vuln-count bkx-vuln-medium">
						<span class="count"><?php echo esc_html( $vulnerabilities['counts']['medium'] ); ?></span>
						<span class="label"><?php esc_html_e( 'Medium', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-vuln-count bkx-vuln-low">
						<span class="count"><?php echo esc_html( $vulnerabilities['counts']['low'] ); ?></span>
						<span class="label"><?php esc_html_e( 'Low', 'bkx-pci-compliance' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Audit Log Stats -->
		<div class="bkx-card">
			<div class="bkx-card-header">
				<h2><?php esc_html_e( 'Audit Activity (30 Days)', 'bkx-pci-compliance' ); ?></h2>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pci-audit-log' ) ); ?>" class="button-link">
					<?php esc_html_e( 'View Log', 'bkx-pci-compliance' ); ?>
				</a>
			</div>
			<div class="bkx-card-body">
				<div class="bkx-audit-stats">
					<div class="bkx-stat">
						<span class="value"><?php echo esc_html( number_format_i18n( $stats['total_events'] ) ); ?></span>
						<span class="label"><?php esc_html_e( 'Total Events', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-stat bkx-warning">
						<span class="value"><?php echo esc_html( $stats['failed_logins'] ); ?></span>
						<span class="label"><?php esc_html_e( 'Failed Logins', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-stat bkx-critical">
						<span class="value"><?php echo esc_html( $stats['critical_events'] ); ?></span>
						<span class="label"><?php esc_html_e( 'Critical Events', 'bkx-pci-compliance' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- Key Status -->
		<div class="bkx-card">
			<div class="bkx-card-header">
				<h2><?php esc_html_e( 'Encryption Keys', 'bkx-pci-compliance' ); ?></h2>
			</div>
			<div class="bkx-card-body">
				<table class="bkx-key-status">
					<?php foreach ( $key_status as $type => $status ) : ?>
						<tr>
							<td class="key-type"><?php echo esc_html( ucfirst( $type ) ); ?></td>
							<td class="key-status">
								<?php if ( $status['needs_rotation'] ) : ?>
									<span class="bkx-badge bkx-badge-warning"><?php esc_html_e( 'Needs Rotation', 'bkx-pci-compliance' ); ?></span>
								<?php else : ?>
									<span class="bkx-badge bkx-badge-success"><?php esc_html_e( 'Current', 'bkx-pci-compliance' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="key-age">
								<?php if ( $status['days_since'] !== null ) : ?>
									<?php
									printf(
										/* translators: %d: Days since rotation */
										esc_html__( '%d days', 'bkx-pci-compliance' ),
										$status['days_since']
									);
									?>
								<?php else : ?>
									-
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		</div>

		<!-- PCI Requirements Quick Reference -->
		<div class="bkx-card bkx-card-full">
			<div class="bkx-card-header">
				<h2><?php esc_html_e( 'PCI DSS 4.0 Requirements', 'bkx-pci-compliance' ); ?></h2>
			</div>
			<div class="bkx-card-body">
				<div class="bkx-requirements-grid">
					<div class="bkx-requirement">
						<span class="req-number">1</span>
						<span class="req-title"><?php esc_html_e( 'Firewall Configuration', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-requirement">
						<span class="req-number">2</span>
						<span class="req-title"><?php esc_html_e( 'Default Passwords', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-requirement">
						<span class="req-number">3</span>
						<span class="req-title"><?php esc_html_e( 'Protect Stored Data', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-requirement">
						<span class="req-number">4</span>
						<span class="req-title"><?php esc_html_e( 'Encrypt Transmission', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-requirement">
						<span class="req-number">5</span>
						<span class="req-title"><?php esc_html_e( 'Malware Protection', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-requirement">
						<span class="req-number">6</span>
						<span class="req-title"><?php esc_html_e( 'Secure Systems', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-requirement">
						<span class="req-number">7</span>
						<span class="req-title"><?php esc_html_e( 'Access Control', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-requirement">
						<span class="req-number">8</span>
						<span class="req-title"><?php esc_html_e( 'Authentication', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-requirement">
						<span class="req-number">9</span>
						<span class="req-title"><?php esc_html_e( 'Physical Access', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-requirement">
						<span class="req-number">10</span>
						<span class="req-title"><?php esc_html_e( 'Logging & Monitoring', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-requirement">
						<span class="req-number">11</span>
						<span class="req-title"><?php esc_html_e( 'Security Testing', 'bkx-pci-compliance' ); ?></span>
					</div>
					<div class="bkx-requirement">
						<span class="req-number">12</span>
						<span class="req-title"><?php esc_html_e( 'Security Policy', 'bkx-pci-compliance' ); ?></span>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

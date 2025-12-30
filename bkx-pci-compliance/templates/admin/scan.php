<?php
/**
 * Compliance Scan template.
 *
 * @package BookingX\PCICompliance
 */

defined( 'ABSPATH' ) || exit;

$scanner      = new \BookingX\PCICompliance\Services\ComplianceScanner();
$scan_history = $scanner->get_scan_history( 10 );
$last_scan    = ! empty( $scan_history ) ? $scan_history[0] : null;

if ( $last_scan && 'completed' === $last_scan['status'] ) {
	$last_scan['results']         = json_decode( $last_scan['results'], true );
	$last_scan['recommendations'] = json_decode( $last_scan['recommendations'], true );
}
?>
<div class="wrap bkx-pci-compliance">
	<h1><?php esc_html_e( 'PCI DSS Compliance Scan', 'bkx-pci-compliance' ); ?></h1>

	<div class="bkx-scan-section">
		<div class="bkx-scan-actions">
			<h2><?php esc_html_e( 'Run Compliance Scan', 'bkx-pci-compliance' ); ?></h2>
			<p><?php esc_html_e( 'Scan your site against PCI DSS requirements to identify compliance gaps and security issues.', 'bkx-pci-compliance' ); ?></p>

			<div class="bkx-scan-types">
				<div class="bkx-scan-type">
					<h3><?php esc_html_e( 'Full Scan', 'bkx-pci-compliance' ); ?></h3>
					<p><?php esc_html_e( 'Complete scan of all 12 PCI DSS requirements.', 'bkx-pci-compliance' ); ?></p>
					<button type="button" class="button button-primary bkx-run-scan" data-type="full">
						<?php esc_html_e( 'Run Full Scan', 'bkx-pci-compliance' ); ?>
					</button>
				</div>
				<div class="bkx-scan-type">
					<h3><?php esc_html_e( 'Quick Scan', 'bkx-pci-compliance' ); ?></h3>
					<p><?php esc_html_e( 'Fast scan of critical requirements only.', 'bkx-pci-compliance' ); ?></p>
					<button type="button" class="button bkx-run-scan" data-type="quick">
						<?php esc_html_e( 'Run Quick Scan', 'bkx-pci-compliance' ); ?>
					</button>
				</div>
				<div class="bkx-scan-type">
					<h3><?php esc_html_e( 'Vulnerability Scan', 'bkx-pci-compliance' ); ?></h3>
					<p><?php esc_html_e( 'Scan for security vulnerabilities.', 'bkx-pci-compliance' ); ?></p>
					<button type="button" class="button bkx-run-scan" data-type="vulnerability">
						<?php esc_html_e( 'Scan Vulnerabilities', 'bkx-pci-compliance' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div id="bkx-scan-progress" style="display: none;">
			<div class="bkx-progress">
				<div class="bkx-progress-bar"></div>
			</div>
			<p class="bkx-progress-text"><?php esc_html_e( 'Scanning...', 'bkx-pci-compliance' ); ?></p>
		</div>

		<?php if ( $last_scan && 'completed' === $last_scan['status'] ) : ?>
			<div class="bkx-scan-results">
				<h2><?php esc_html_e( 'Latest Scan Results', 'bkx-pci-compliance' ); ?></h2>
				<p class="bkx-scan-meta">
					<?php
					printf(
						/* translators: 1: Scan type, 2: Scan date */
						esc_html__( '%1$s scan completed on %2$s', 'bkx-pci-compliance' ),
						esc_html( ucfirst( $last_scan['scan_type'] ) ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_scan['completed_at'] ) ) )
					);
					?>
				</p>

				<div class="bkx-score-banner <?php echo $last_scan['overall_score'] >= 80 ? 'bkx-good' : ( $last_scan['overall_score'] >= 60 ? 'bkx-warning' : 'bkx-poor' ); ?>">
					<div class="bkx-score"><?php echo esc_html( round( $last_scan['overall_score'] ) ); ?>%</div>
					<div class="bkx-score-label">
						<?php
						if ( $last_scan['overall_score'] >= 80 ) {
							esc_html_e( 'Good Compliance', 'bkx-pci-compliance' );
						} elseif ( $last_scan['overall_score'] >= 60 ) {
							esc_html_e( 'Needs Improvement', 'bkx-pci-compliance' );
						} else {
							esc_html_e( 'Non-Compliant', 'bkx-pci-compliance' );
						}
						?>
					</div>
				</div>

				<?php if ( ! empty( $last_scan['recommendations'] ) ) : ?>
					<h3><?php esc_html_e( 'Recommendations', 'bkx-pci-compliance' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Requirement', 'bkx-pci-compliance' ); ?></th>
								<th><?php esc_html_e( 'Issue', 'bkx-pci-compliance' ); ?></th>
								<th><?php esc_html_e( 'Severity', 'bkx-pci-compliance' ); ?></th>
								<th><?php esc_html_e( 'Action', 'bkx-pci-compliance' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $last_scan['recommendations'] as $rec ) : ?>
								<tr>
									<td><?php echo esc_html( $rec['check_id'] ); ?></td>
									<td><?php echo esc_html( $rec['title'] ); ?></td>
									<td>
										<span class="bkx-severity-badge bkx-severity-<?php echo esc_attr( $rec['severity'] ); ?>">
											<?php echo esc_html( ucfirst( $rec['severity'] ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $rec['action'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="bkx-success-message">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'No issues found! All scanned requirements passed.', 'bkx-pci-compliance' ); ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $last_scan['results'] ) ) : ?>
					<h3><?php esc_html_e( 'Detailed Results by Requirement', 'bkx-pci-compliance' ); ?></h3>
					<?php foreach ( $last_scan['results'] as $req_num => $checks ) : ?>
						<div class="bkx-requirement-results">
							<h4><?php printf( esc_html__( 'Requirement %s', 'bkx-pci-compliance' ), esc_html( $req_num ) ); ?></h4>
							<table class="bkx-checks-table">
								<?php foreach ( $checks as $check ) : ?>
									<tr class="bkx-check-<?php echo esc_attr( $check['status'] ); ?>">
										<td class="check-id"><?php echo esc_html( $check['id'] ); ?></td>
										<td class="check-title"><?php echo esc_html( $check['title'] ); ?></td>
										<td class="check-status">
											<?php if ( 'pass' === $check['status'] ) : ?>
												<span class="dashicons dashicons-yes-alt bkx-pass"></span>
											<?php elseif ( 'fail' === $check['status'] ) : ?>
												<span class="dashicons dashicons-dismiss bkx-fail"></span>
											<?php else : ?>
												<span class="dashicons dashicons-minus"></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</table>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="bkx-scan-history">
			<h2><?php esc_html_e( 'Scan History', 'bkx-pci-compliance' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'bkx-pci-compliance' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-pci-compliance' ); ?></th>
						<th><?php esc_html_e( 'Score', 'bkx-pci-compliance' ); ?></th>
						<th><?php esc_html_e( 'Passed', 'bkx-pci-compliance' ); ?></th>
						<th><?php esc_html_e( 'Failed', 'bkx-pci-compliance' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-pci-compliance' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $scan_history ) ) : ?>
						<tr>
							<td colspan="6" class="no-items"><?php esc_html_e( 'No scans have been run yet.', 'bkx-pci-compliance' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $scan_history as $scan ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $scan['created_at'] ) ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $scan['scan_type'] ) ); ?></td>
								<td>
									<?php if ( $scan['overall_score'] !== null ) : ?>
										<?php echo esc_html( round( $scan['overall_score'] ) ); ?>%
									<?php else : ?>
										-
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $scan['requirements_passed'] ); ?></td>
								<td><?php echo esc_html( $scan['requirements_failed'] ); ?></td>
								<td>
									<span class="bkx-status-badge bkx-status-<?php echo esc_attr( $scan['status'] ); ?>">
										<?php echo esc_html( ucfirst( $scan['status'] ) ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php
/**
 * Reports template.
 *
 * @package BookingX\PCICompliance
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap bkx-pci-compliance">
	<h1><?php esc_html_e( 'PCI Compliance Reports', 'bkx-pci-compliance' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Generate compliance reports for audits and documentation purposes.', 'bkx-pci-compliance' ); ?>
	</p>

	<div class="bkx-reports-grid">
		<div class="bkx-report-card">
			<div class="bkx-report-icon">
				<span class="dashicons dashicons-media-document"></span>
			</div>
			<h3><?php esc_html_e( 'Compliance Assessment Report', 'bkx-pci-compliance' ); ?></h3>
			<p><?php esc_html_e( 'Complete PCI DSS compliance assessment with all requirement checks and scores.', 'bkx-pci-compliance' ); ?></p>
			<button type="button" class="button button-primary bkx-generate-report" data-type="compliance">
				<?php esc_html_e( 'Generate Report', 'bkx-pci-compliance' ); ?>
			</button>
		</div>

		<div class="bkx-report-card">
			<div class="bkx-report-icon">
				<span class="dashicons dashicons-shield"></span>
			</div>
			<h3><?php esc_html_e( 'Vulnerability Report', 'bkx-pci-compliance' ); ?></h3>
			<p><?php esc_html_e( 'Summary of discovered vulnerabilities and remediation status.', 'bkx-pci-compliance' ); ?></p>
			<button type="button" class="button button-primary bkx-generate-report" data-type="vulnerability">
				<?php esc_html_e( 'Generate Report', 'bkx-pci-compliance' ); ?>
			</button>
		</div>

		<div class="bkx-report-card">
			<div class="bkx-report-icon">
				<span class="dashicons dashicons-list-view"></span>
			</div>
			<h3><?php esc_html_e( 'Audit Log Export', 'bkx-pci-compliance' ); ?></h3>
			<p><?php esc_html_e( 'Export complete audit log for external review or archival.', 'bkx-pci-compliance' ); ?></p>
			<form id="bkx-audit-export-form">
				<label>
					<?php esc_html_e( 'Date Range:', 'bkx-pci-compliance' ); ?>
					<input type="date" name="date_from" id="report-date-from">
					<?php esc_html_e( 'to', 'bkx-pci-compliance' ); ?>
					<input type="date" name="date_to" id="report-date-to">
				</label>
				<br><br>
				<button type="button" class="button button-primary bkx-generate-report" data-type="audit">
					<?php esc_html_e( 'Export Audit Log', 'bkx-pci-compliance' ); ?>
				</button>
			</form>
		</div>

		<div class="bkx-report-card">
			<div class="bkx-report-icon">
				<span class="dashicons dashicons-lock"></span>
			</div>
			<h3><?php esc_html_e( 'Key Rotation Log', 'bkx-pci-compliance' ); ?></h3>
			<p><?php esc_html_e( 'History of encryption key rotations for compliance documentation.', 'bkx-pci-compliance' ); ?></p>
			<button type="button" class="button button-primary bkx-generate-report" data-type="key_rotation">
				<?php esc_html_e( 'Generate Report', 'bkx-pci-compliance' ); ?>
			</button>
		</div>

		<div class="bkx-report-card">
			<div class="bkx-report-icon">
				<span class="dashicons dashicons-visibility"></span>
			</div>
			<h3><?php esc_html_e( 'Data Access Report', 'bkx-pci-compliance' ); ?></h3>
			<p><?php esc_html_e( 'Log of all access to cardholder data and sensitive information.', 'bkx-pci-compliance' ); ?></p>
			<button type="button" class="button button-primary bkx-generate-report" data-type="data_access">
				<?php esc_html_e( 'Generate Report', 'bkx-pci-compliance' ); ?>
			</button>
		</div>

		<div class="bkx-report-card">
			<div class="bkx-report-icon">
				<span class="dashicons dashicons-chart-bar"></span>
			</div>
			<h3><?php esc_html_e( 'Executive Summary', 'bkx-pci-compliance' ); ?></h3>
			<p><?php esc_html_e( 'High-level compliance summary for management review.', 'bkx-pci-compliance' ); ?></p>
			<button type="button" class="button button-primary bkx-generate-report" data-type="executive">
				<?php esc_html_e( 'Generate Report', 'bkx-pci-compliance' ); ?>
			</button>
		</div>
	</div>

	<div id="bkx-report-result" style="display: none;">
		<div class="bkx-success-box">
			<span class="dashicons dashicons-yes-alt"></span>
			<div>
				<strong><?php esc_html_e( 'Report Generated!', 'bkx-pci-compliance' ); ?></strong>
				<p><?php esc_html_e( 'Your report is ready for download.', 'bkx-pci-compliance' ); ?></p>
				<a href="#" id="bkx-report-download" class="button button-primary" download>
					<?php esc_html_e( 'Download Report', 'bkx-pci-compliance' ); ?>
				</a>
			</div>
		</div>
	</div>

	<hr>

	<h2><?php esc_html_e( 'PCI DSS Compliance Documentation', 'bkx-pci-compliance' ); ?></h2>
	<p><?php esc_html_e( 'These reports can help demonstrate compliance with PCI DSS requirements:', 'bkx-pci-compliance' ); ?></p>

	<table class="wp-list-table widefat fixed">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Requirement', 'bkx-pci-compliance' ); ?></th>
				<th><?php esc_html_e( 'Documentation', 'bkx-pci-compliance' ); ?></th>
				<th><?php esc_html_e( 'Report Type', 'bkx-pci-compliance' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>10.2 - Automated audit trails</td>
				<td><?php esc_html_e( 'Record all user access to cardholder data', 'bkx-pci-compliance' ); ?></td>
				<td><?php esc_html_e( 'Audit Log Export', 'bkx-pci-compliance' ); ?></td>
			</tr>
			<tr>
				<td>10.7 - Retain audit trail history</td>
				<td><?php esc_html_e( 'Maintain at least 1 year of audit history', 'bkx-pci-compliance' ); ?></td>
				<td><?php esc_html_e( 'Audit Log Export', 'bkx-pci-compliance' ); ?></td>
			</tr>
			<tr>
				<td>3.6 - Key management</td>
				<td><?php esc_html_e( 'Document cryptographic key management procedures', 'bkx-pci-compliance' ); ?></td>
				<td><?php esc_html_e( 'Key Rotation Log', 'bkx-pci-compliance' ); ?></td>
			</tr>
			<tr>
				<td>11.2 - Vulnerability scans</td>
				<td><?php esc_html_e( 'Run vulnerability scans at least quarterly', 'bkx-pci-compliance' ); ?></td>
				<td><?php esc_html_e( 'Vulnerability Report', 'bkx-pci-compliance' ); ?></td>
			</tr>
			<tr>
				<td>7.1 - Access control</td>
				<td><?php esc_html_e( 'Restrict access to cardholder data by business need', 'bkx-pci-compliance' ); ?></td>
				<td><?php esc_html_e( 'Data Access Report', 'bkx-pci-compliance' ); ?></td>
			</tr>
		</tbody>
	</table>
</div>

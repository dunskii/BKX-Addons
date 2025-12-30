<?php
/**
 * Vulnerabilities template.
 *
 * @package BookingX\PCICompliance
 */

defined( 'ABSPATH' ) || exit;

$vuln_scanner = new \BookingX\PCICompliance\Services\VulnerabilityScanner();

$status        = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
$severity      = isset( $_GET['severity'] ) ? sanitize_key( $_GET['severity'] ) : '';
$result        = $vuln_scanner->get_vulnerabilities( array(
	'status'   => $status ?: null,
	'severity' => $severity ?: null,
) );
$vulnerabilities = $result['vulnerabilities'];
$counts          = $result['counts'];
?>
<div class="wrap bkx-pci-compliance">
	<h1><?php esc_html_e( 'Vulnerabilities', 'bkx-pci-compliance' ); ?></h1>

	<div class="bkx-vuln-summary-bar">
		<div class="bkx-vuln-stat bkx-vuln-critical">
			<span class="count"><?php echo esc_html( $counts['critical'] ); ?></span>
			<span class="label"><?php esc_html_e( 'Critical', 'bkx-pci-compliance' ); ?></span>
		</div>
		<div class="bkx-vuln-stat bkx-vuln-high">
			<span class="count"><?php echo esc_html( $counts['high'] ); ?></span>
			<span class="label"><?php esc_html_e( 'High', 'bkx-pci-compliance' ); ?></span>
		</div>
		<div class="bkx-vuln-stat bkx-vuln-medium">
			<span class="count"><?php echo esc_html( $counts['medium'] ); ?></span>
			<span class="label"><?php esc_html_e( 'Medium', 'bkx-pci-compliance' ); ?></span>
		</div>
		<div class="bkx-vuln-stat bkx-vuln-low">
			<span class="count"><?php echo esc_html( $counts['low'] ); ?></span>
			<span class="label"><?php esc_html_e( 'Low', 'bkx-pci-compliance' ); ?></span>
		</div>
	</div>

	<div class="bkx-vuln-filters">
		<form method="get">
			<input type="hidden" name="page" value="bkx-pci-vulnerabilities">
			<select name="status">
				<option value=""><?php esc_html_e( 'All Statuses', 'bkx-pci-compliance' ); ?></option>
				<option value="open" <?php selected( $status, 'open' ); ?>><?php esc_html_e( 'Open', 'bkx-pci-compliance' ); ?></option>
				<option value="in_progress" <?php selected( $status, 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'bkx-pci-compliance' ); ?></option>
				<option value="resolved" <?php selected( $status, 'resolved' ); ?>><?php esc_html_e( 'Resolved', 'bkx-pci-compliance' ); ?></option>
				<option value="accepted" <?php selected( $status, 'accepted' ); ?>><?php esc_html_e( 'Accepted Risk', 'bkx-pci-compliance' ); ?></option>
				<option value="false_positive" <?php selected( $status, 'false_positive' ); ?>><?php esc_html_e( 'False Positive', 'bkx-pci-compliance' ); ?></option>
			</select>
			<select name="severity">
				<option value=""><?php esc_html_e( 'All Severities', 'bkx-pci-compliance' ); ?></option>
				<option value="critical" <?php selected( $severity, 'critical' ); ?>><?php esc_html_e( 'Critical', 'bkx-pci-compliance' ); ?></option>
				<option value="high" <?php selected( $severity, 'high' ); ?>><?php esc_html_e( 'High', 'bkx-pci-compliance' ); ?></option>
				<option value="medium" <?php selected( $severity, 'medium' ); ?>><?php esc_html_e( 'Medium', 'bkx-pci-compliance' ); ?></option>
				<option value="low" <?php selected( $severity, 'low' ); ?>><?php esc_html_e( 'Low', 'bkx-pci-compliance' ); ?></option>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-pci-compliance' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pci-vulnerabilities' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'bkx-pci-compliance' ); ?></a>
		</form>

		<button type="button" class="button button-primary" id="bkx-scan-vuln">
			<span class="dashicons dashicons-search"></span>
			<?php esc_html_e( 'Run Vulnerability Scan', 'bkx-pci-compliance' ); ?>
		</button>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th class="column-severity"><?php esc_html_e( 'Severity', 'bkx-pci-compliance' ); ?></th>
				<th class="column-title"><?php esc_html_e( 'Vulnerability', 'bkx-pci-compliance' ); ?></th>
				<th class="column-component"><?php esc_html_e( 'Affected Component', 'bkx-pci-compliance' ); ?></th>
				<th class="column-pci"><?php esc_html_e( 'PCI Req.', 'bkx-pci-compliance' ); ?></th>
				<th class="column-status"><?php esc_html_e( 'Status', 'bkx-pci-compliance' ); ?></th>
				<th class="column-discovered"><?php esc_html_e( 'Discovered', 'bkx-pci-compliance' ); ?></th>
				<th class="column-actions"><?php esc_html_e( 'Actions', 'bkx-pci-compliance' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $vulnerabilities ) ) : ?>
				<tr>
					<td colspan="7" class="no-items">
						<?php esc_html_e( 'No vulnerabilities found.', 'bkx-pci-compliance' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $vulnerabilities as $vuln ) : ?>
					<tr data-id="<?php echo esc_attr( $vuln['id'] ); ?>">
						<td class="column-severity">
							<span class="bkx-severity-badge bkx-severity-<?php echo esc_attr( $vuln['severity'] ); ?>">
								<?php echo esc_html( ucfirst( $vuln['severity'] ) ); ?>
							</span>
						</td>
						<td class="column-title">
							<strong><?php echo esc_html( $vuln['title'] ); ?></strong>
							<p class="description"><?php echo esc_html( $vuln['description'] ); ?></p>
						</td>
						<td class="column-component">
							<?php echo esc_html( $vuln['affected_component'] ); ?>
						</td>
						<td class="column-pci">
							<?php echo esc_html( $vuln['pci_requirements'] ?: '-' ); ?>
						</td>
						<td class="column-status">
							<span class="bkx-status-badge bkx-status-<?php echo esc_attr( $vuln['status'] ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $vuln['status'] ) ) ); ?>
							</span>
						</td>
						<td class="column-discovered">
							<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $vuln['discovered_at'] ) ) ); ?>
						</td>
						<td class="column-actions">
							<?php if ( 'open' === $vuln['status'] || 'in_progress' === $vuln['status'] ) : ?>
								<button type="button" class="button button-small bkx-resolve-vuln" data-id="<?php echo esc_attr( $vuln['id'] ); ?>">
									<?php esc_html_e( 'Resolve', 'bkx-pci-compliance' ); ?>
								</button>
							<?php endif; ?>
							<button type="button" class="button button-small bkx-view-vuln" data-id="<?php echo esc_attr( $vuln['id'] ); ?>" data-details="<?php echo esc_attr( wp_json_encode( $vuln ) ); ?>">
								<?php esc_html_e( 'View', 'bkx-pci-compliance' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<!-- Resolve Modal -->
<div id="bkx-resolve-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Resolve Vulnerability', 'bkx-pci-compliance' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<form id="bkx-resolve-form">
				<input type="hidden" name="vulnerability_id" id="resolve-vuln-id">
				<table class="form-table">
					<tr>
						<th><label for="resolve-status"><?php esc_html_e( 'Resolution Status', 'bkx-pci-compliance' ); ?></label></th>
						<td>
							<select name="status" id="resolve-status">
								<option value="resolved"><?php esc_html_e( 'Resolved', 'bkx-pci-compliance' ); ?></option>
								<option value="in_progress"><?php esc_html_e( 'In Progress', 'bkx-pci-compliance' ); ?></option>
								<option value="accepted"><?php esc_html_e( 'Risk Accepted', 'bkx-pci-compliance' ); ?></option>
								<option value="false_positive"><?php esc_html_e( 'False Positive', 'bkx-pci-compliance' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="resolve-notes"><?php esc_html_e( 'Resolution Notes', 'bkx-pci-compliance' ); ?></label></th>
						<td>
							<textarea name="resolution" id="resolve-notes" rows="4" class="large-text"></textarea>
							<p class="description"><?php esc_html_e( 'Document the remediation steps taken.', 'bkx-pci-compliance' ); ?></p>
						</td>
					</tr>
				</table>
			</form>
		</div>
		<div class="bkx-modal-footer">
			<button type="button" class="button bkx-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-pci-compliance' ); ?></button>
			<button type="button" class="button button-primary" id="bkx-submit-resolve"><?php esc_html_e( 'Update Status', 'bkx-pci-compliance' ); ?></button>
		</div>
	</div>
</div>

<!-- View Modal -->
<div id="bkx-view-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content bkx-modal-wide">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Vulnerability Details', 'bkx-pci-compliance' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body" id="bkx-vuln-details"></div>
	</div>
</div>

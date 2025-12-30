<?php
/**
 * BookingX settings tab integration template.
 *
 * @package BookingX\PCICompliance
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_pci_compliance_settings', array() );

// Get latest scan.
$scanner      = new \BookingX\PCICompliance\Services\ComplianceScanner();
$scan_history = $scanner->get_scan_history( 1 );
$last_scan    = ! empty( $scan_history ) ? $scan_history[0] : null;
?>
<div class="bkx-settings-pci-tab">
	<h2><?php esc_html_e( 'PCI DSS Compliance', 'bkx-pci-compliance' ); ?></h2>

	<?php if ( $last_scan ) : ?>
		<div class="bkx-compliance-status">
			<div class="bkx-score-display-small <?php echo $last_scan['overall_score'] >= 80 ? 'bkx-good' : ( $last_scan['overall_score'] >= 60 ? 'bkx-warning' : 'bkx-poor' ); ?>">
				<span class="score"><?php echo esc_html( round( $last_scan['overall_score'] ) ); ?>%</span>
				<span class="label"><?php esc_html_e( 'Compliance Score', 'bkx-pci-compliance' ); ?></span>
			</div>
			<p class="last-scan">
				<?php
				printf(
					/* translators: %s: Last scan date */
					esc_html__( 'Last scan: %s', 'bkx-pci-compliance' ),
					esc_html( wp_date( get_option( 'date_format' ), strtotime( $last_scan['completed_at'] ) ) )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="bkx_pci_compliance_level"><?php esc_html_e( 'Compliance Level', 'bkx-pci-compliance' ); ?></label>
			</th>
			<td>
				<select name="bkx_pci_compliance_settings[compliance_level]" id="bkx_pci_compliance_level">
					<option value="saq_a" <?php selected( $settings['compliance_level'] ?? '', 'saq_a' ); ?>>
						<?php esc_html_e( 'SAQ A (Recommended for most)', 'bkx-pci-compliance' ); ?>
					</option>
					<option value="saq_a_ep" <?php selected( $settings['compliance_level'] ?? '', 'saq_a_ep' ); ?>>
						<?php esc_html_e( 'SAQ A-EP', 'bkx-pci-compliance' ); ?>
					</option>
					<option value="saq_d" <?php selected( $settings['compliance_level'] ?? '', 'saq_d' ); ?>>
						<?php esc_html_e( 'SAQ D', 'bkx-pci-compliance' ); ?>
					</option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'SSL Enforcement', 'bkx-pci-compliance' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="bkx_pci_compliance_settings[ssl_enforcement]" value="1"
						<?php checked( $settings['ssl_enforcement'] ?? false ); ?>>
					<?php esc_html_e( 'Force HTTPS on payment pages', 'bkx-pci-compliance' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="bkx_pci_session_timeout"><?php esc_html_e( 'Session Timeout', 'bkx-pci-compliance' ); ?></label>
			</th>
			<td>
				<input type="number" name="bkx_pci_compliance_settings[session_timeout]" id="bkx_pci_session_timeout"
					   value="<?php echo esc_attr( $settings['session_timeout'] ?? 15 ); ?>"
					   min="5" max="60" class="small-text">
				<?php esc_html_e( 'minutes (PCI requires max 15)', 'bkx-pci-compliance' ); ?>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Quick Actions', 'bkx-pci-compliance' ); ?></h3>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pci-compliance' ) ); ?>" class="button">
			<span class="dashicons dashicons-shield" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'PCI Dashboard', 'bkx-pci-compliance' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pci-scan' ) ); ?>" class="button">
			<span class="dashicons dashicons-search" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Run Compliance Scan', 'bkx-pci-compliance' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pci-audit-log' ) ); ?>" class="button">
			<span class="dashicons dashicons-list-view" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'View Audit Log', 'bkx-pci-compliance' ); ?>
		</a>
	</p>

	<div class="bkx-pci-requirements-summary">
		<h3><?php esc_html_e( 'PCI DSS Requirements Overview', 'bkx-pci-compliance' ); ?></h3>
		<p class="description"><?php esc_html_e( 'BookingX PCI Compliance Tools helps you meet these requirements:', 'bkx-pci-compliance' ); ?></p>
		<ul>
			<li><strong>Req. 3:</strong> <?php esc_html_e( 'Protect stored cardholder data', 'bkx-pci-compliance' ); ?></li>
			<li><strong>Req. 4:</strong> <?php esc_html_e( 'Encrypt transmission of cardholder data', 'bkx-pci-compliance' ); ?></li>
			<li><strong>Req. 6:</strong> <?php esc_html_e( 'Develop and maintain secure systems', 'bkx-pci-compliance' ); ?></li>
			<li><strong>Req. 8:</strong> <?php esc_html_e( 'Identify and authenticate access', 'bkx-pci-compliance' ); ?></li>
			<li><strong>Req. 10:</strong> <?php esc_html_e( 'Track and monitor all access', 'bkx-pci-compliance' ); ?></li>
			<li><strong>Req. 11:</strong> <?php esc_html_e( 'Regularly test security systems', 'bkx-pci-compliance' ); ?></li>
		</ul>
	</div>
</div>

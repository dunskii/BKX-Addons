<?php
/**
 * Settings template.
 *
 * @package BookingX\PCICompliance
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_pci_compliance_settings', array() );
$pass_req = $settings['password_requirements'] ?? array();
?>
<div class="wrap bkx-pci-compliance">
	<h1><?php esc_html_e( 'PCI Compliance Settings', 'bkx-pci-compliance' ); ?></h1>

	<form id="bkx-pci-settings-form" method="post">
		<h2><?php esc_html_e( 'Compliance Level', 'bkx-pci-compliance' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="compliance_level"><?php esc_html_e( 'SAQ Level', 'bkx-pci-compliance' ); ?></label>
				</th>
				<td>
					<select name="compliance_level" id="compliance_level">
						<option value="saq_a" <?php selected( $settings['compliance_level'] ?? '', 'saq_a' ); ?>>
							<?php esc_html_e( 'SAQ A - Card-not-present, all processing outsourced', 'bkx-pci-compliance' ); ?>
						</option>
						<option value="saq_a_ep" <?php selected( $settings['compliance_level'] ?? '', 'saq_a_ep' ); ?>>
							<?php esc_html_e( 'SAQ A-EP - E-commerce with website payment page', 'bkx-pci-compliance' ); ?>
						</option>
						<option value="saq_d" <?php selected( $settings['compliance_level'] ?? '', 'saq_d' ); ?>>
							<?php esc_html_e( 'SAQ D - All other merchants', 'bkx-pci-compliance' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'Select your PCI DSS Self-Assessment Questionnaire level.', 'bkx-pci-compliance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="card_data_storage"><?php esc_html_e( 'Card Data Storage', 'bkx-pci-compliance' ); ?></label>
				</th>
				<td>
					<select name="card_data_storage" id="card_data_storage">
						<option value="none" <?php selected( $settings['card_data_storage'] ?? '', 'none' ); ?>>
							<?php esc_html_e( 'None - No card data stored (recommended)', 'bkx-pci-compliance' ); ?>
						</option>
						<option value="tokenized" <?php selected( $settings['card_data_storage'] ?? '', 'tokenized' ); ?>>
							<?php esc_html_e( 'Tokenized - Tokens from payment processor only', 'bkx-pci-compliance' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'Never store full card numbers, CVV, or sensitive authentication data.', 'bkx-pci-compliance' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Security Settings', 'bkx-pci-compliance' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'SSL/TLS Enforcement', 'bkx-pci-compliance' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="ssl_enforcement" value="1" <?php checked( $settings['ssl_enforcement'] ?? false ); ?>>
						<?php esc_html_e( 'Force SSL on payment pages', 'bkx-pci-compliance' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Automatically redirect to HTTPS on pages with payment forms.', 'bkx-pci-compliance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="session_timeout"><?php esc_html_e( 'Session Timeout', 'bkx-pci-compliance' ); ?></label>
				</th>
				<td>
					<input type="number" name="session_timeout" id="session_timeout"
						   value="<?php echo esc_attr( $settings['session_timeout'] ?? 15 ); ?>"
						   min="5" max="60" class="small-text">
					<?php esc_html_e( 'minutes', 'bkx-pci-compliance' ); ?>
					<p class="description"><?php esc_html_e( 'PCI DSS requires 15 minutes maximum for idle sessions (Req. 8.1.8).', 'bkx-pci-compliance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="failed_login_lockout"><?php esc_html_e( 'Failed Login Lockout', 'bkx-pci-compliance' ); ?></label>
				</th>
				<td>
					<input type="number" name="failed_login_lockout" id="failed_login_lockout"
						   value="<?php echo esc_attr( $settings['failed_login_lockout'] ?? 5 ); ?>"
						   min="3" max="10" class="small-text">
					<?php esc_html_e( 'attempts', 'bkx-pci-compliance' ); ?>
					<p class="description"><?php esc_html_e( 'Lock accounts after this many failed login attempts (Req. 8.1.6).', 'bkx-pci-compliance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Data Masking', 'bkx-pci-compliance' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="data_masking_enabled" value="1" <?php checked( $settings['data_masking_enabled'] ?? true ); ?>>
						<?php esc_html_e( 'Mask sensitive data in logs and displays', 'bkx-pci-compliance' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Automatically mask card numbers, CVV, and other sensitive data (Req. 3.3).', 'bkx-pci-compliance' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Password Policy', 'bkx-pci-compliance' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="password_min_length"><?php esc_html_e( 'Minimum Length', 'bkx-pci-compliance' ); ?></label>
				</th>
				<td>
					<input type="number" name="password_min_length" id="password_min_length"
						   value="<?php echo esc_attr( $pass_req['min_length'] ?? 12 ); ?>"
						   min="8" max="32" class="small-text">
					<?php esc_html_e( 'characters', 'bkx-pci-compliance' ); ?>
					<p class="description"><?php esc_html_e( 'PCI DSS 4.0 requires minimum 12 characters (Req. 8.3.6).', 'bkx-pci-compliance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Complexity Requirements', 'bkx-pci-compliance' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="password_require_upper" value="1" <?php checked( $pass_req['require_upper'] ?? true ); ?>>
							<?php esc_html_e( 'Require uppercase letter', 'bkx-pci-compliance' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="password_require_lower" value="1" <?php checked( $pass_req['require_lower'] ?? true ); ?>>
							<?php esc_html_e( 'Require lowercase letter', 'bkx-pci-compliance' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="password_require_number" value="1" <?php checked( $pass_req['require_number'] ?? true ); ?>>
							<?php esc_html_e( 'Require number', 'bkx-pci-compliance' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="password_require_special" value="1" <?php checked( $pass_req['require_special'] ?? true ); ?>>
							<?php esc_html_e( 'Require special character', 'bkx-pci-compliance' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Audit & Monitoring', 'bkx-pci-compliance' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="pci_scan_frequency"><?php esc_html_e( 'Scan Frequency', 'bkx-pci-compliance' ); ?></label>
				</th>
				<td>
					<select name="pci_scan_frequency" id="pci_scan_frequency">
						<option value="daily" <?php selected( $settings['pci_scan_frequency'] ?? '', 'daily' ); ?>><?php esc_html_e( 'Daily', 'bkx-pci-compliance' ); ?></option>
						<option value="weekly" <?php selected( $settings['pci_scan_frequency'] ?? '', 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'bkx-pci-compliance' ); ?></option>
						<option value="monthly" <?php selected( $settings['pci_scan_frequency'] ?? '', 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'bkx-pci-compliance' ); ?></option>
						<option value="quarterly" <?php selected( $settings['pci_scan_frequency'] ?? '', 'quarterly' ); ?>><?php esc_html_e( 'Quarterly', 'bkx-pci-compliance' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'How often to run automated compliance scans.', 'bkx-pci-compliance' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="audit_log_retention"><?php esc_html_e( 'Log Retention', 'bkx-pci-compliance' ); ?></label>
				</th>
				<td>
					<input type="number" name="audit_log_retention" id="audit_log_retention"
						   value="<?php echo esc_attr( $settings['audit_log_retention'] ?? 365 ); ?>"
						   min="90" max="730" class="small-text">
					<?php esc_html_e( 'days', 'bkx-pci-compliance' ); ?>
					<p class="description"><?php esc_html_e( 'PCI DSS requires minimum 1 year (365 days) of audit history (Req. 10.7).', 'bkx-pci-compliance' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Notifications', 'bkx-pci-compliance' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Vulnerability Alerts', 'bkx-pci-compliance' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="vulnerability_alerts" value="1" <?php checked( $settings['vulnerability_alerts'] ?? true ); ?>>
						<?php esc_html_e( 'Send email alerts for critical vulnerabilities', 'bkx-pci-compliance' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="alert_email"><?php esc_html_e( 'Alert Email', 'bkx-pci-compliance' ); ?></label>
				</th>
				<td>
					<input type="email" name="alert_email" id="alert_email"
						   value="<?php echo esc_attr( $settings['alert_email'] ?? get_option( 'admin_email' ) ); ?>"
						   class="regular-text">
				</td>
			</tr>
		</table>

		<?php wp_nonce_field( 'bkx_pci_settings', 'bkx_pci_nonce' ); ?>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'bkx-pci-compliance' ); ?></button>
		</p>
	</form>
</div>

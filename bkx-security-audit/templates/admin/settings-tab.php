<?php
/**
 * BookingX settings tab integration.
 *
 * @package BookingX\SecurityAudit
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_security_audit_settings', array() );
$addon    = \BookingX\SecurityAudit\SecurityAuditAddon::get_instance();
$scanner  = $addon->get_service( 'scanner' );
$last_scan = $scanner ? $scanner->get_last_scan() : null;
?>

<div class="bkx-security-settings-section">
	<h2><?php esc_html_e( 'Security & Audit Settings', 'bkx-security-audit' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure security settings for your booking system.', 'bkx-security-audit' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-security-audit' ) ); ?>">
			<?php esc_html_e( 'View full security dashboard →', 'bkx-security-audit' ); ?>
		</a>
	</p>

	<!-- Quick Status -->
	<?php if ( $last_scan ) : ?>
		<div class="bkx-security-quick-status" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid <?php echo $last_scan['score'] >= 80 ? '#46b450' : ( $last_scan['score'] >= 60 ? '#dba617' : '#dc3232' ); ?>;">
			<strong><?php esc_html_e( 'Security Score:', 'bkx-security-audit' ); ?></strong>
			<span style="font-size: 1.5em; margin-left: 10px;"><?php echo esc_html( $last_scan['score'] ); ?>/100</span>
			<span style="color: #666; margin-left: 15px;">
				(<?php echo esc_html( count( $last_scan['issues'] ) ); ?> issues, <?php echo esc_html( count( $last_scan['warnings'] ) ); ?> warnings)
			</span>
		</div>
	<?php endif; ?>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="bkx_security_login_protection"><?php esc_html_e( 'Login Protection', 'bkx-security-audit' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="bkx_security_login_protection" name="bkx_security_audit_settings[enable_login_protection]" value="1"
						<?php checked( ! empty( $settings['enable_login_protection'] ) ); ?>>
					<?php esc_html_e( 'Enable brute force protection', 'bkx-security-audit' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Lock out IPs after too many failed login attempts.', 'bkx-security-audit' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_security_audit_logging"><?php esc_html_e( 'Audit Logging', 'bkx-security-audit' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="bkx_security_audit_logging" name="bkx_security_audit_settings[enable_audit_logging]" value="1"
						<?php checked( ! empty( $settings['enable_audit_logging'] ) ); ?>>
					<?php esc_html_e( 'Log all booking and user activities', 'bkx-security-audit' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Keep a detailed audit trail of all actions.', 'bkx-security-audit' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_security_headers"><?php esc_html_e( 'Security Headers', 'bkx-security-audit' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="bkx_security_headers" name="bkx_security_audit_settings[security_headers]" value="1"
						<?php checked( ! empty( $settings['security_headers'] ) ); ?>>
					<?php esc_html_e( 'Add security headers (X-Frame-Options, CSP, etc.)', 'bkx-security-audit' ); ?>
				</label>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="bkx_security_xmlrpc"><?php esc_html_e( 'XML-RPC', 'bkx-security-audit' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="bkx_security_xmlrpc" name="bkx_security_audit_settings[disable_xmlrpc]" value="1"
						<?php checked( ! empty( $settings['disable_xmlrpc'] ) ); ?>>
					<?php esc_html_e( 'Disable XML-RPC (recommended)', 'bkx-security-audit' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'XML-RPC is often targeted by attackers. Disable if not needed.', 'bkx-security-audit' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<div class="bkx-security-quick-links" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
		<h4 style="margin-top: 0;"><?php esc_html_e( 'Quick Links', 'bkx-security-audit' ); ?></h4>
		<ul style="margin: 0; list-style: disc; padding-left: 20px;">
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-security-audit&tab=scanner' ) ); ?>">
					<?php esc_html_e( 'Run Security Scan', 'bkx-security-audit' ); ?>
				</a>
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-security-audit&tab=audit-log' ) ); ?>">
					<?php esc_html_e( 'View Audit Log', 'bkx-security-audit' ); ?>
				</a>
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-security-audit&tab=lockouts' ) ); ?>">
					<?php esc_html_e( 'Manage IP Lockouts', 'bkx-security-audit' ); ?>
				</a>
			</li>
		</ul>
	</div>
</div>

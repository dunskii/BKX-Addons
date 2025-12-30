<?php
/**
 * Security settings template.
 *
 * @package BookingX\SecurityAudit
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_security_audit_settings', array() );
?>

<div class="bkx-security-settings">
	<form method="post" action="options.php">
		<?php settings_fields( 'bkx_security_audit' ); ?>

		<div class="bkx-security-card">
			<h2><?php esc_html_e( 'Login Protection', 'bkx-security-audit' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Login Protection', 'bkx-security-audit' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_security_audit_settings[enable_login_protection]" value="1"
								<?php checked( ! empty( $settings['enable_login_protection'] ) ); ?>>
							<?php esc_html_e( 'Protect login page from brute force attacks', 'bkx-security-audit' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Max Login Attempts', 'bkx-security-audit' ); ?></th>
					<td>
						<input type="number" name="bkx_security_audit_settings[max_login_attempts]" class="small-text"
							value="<?php echo esc_attr( $settings['max_login_attempts'] ?? 5 ); ?>" min="1" max="20">
						<p class="description">
							<?php esc_html_e( 'Number of failed attempts before lockout.', 'bkx-security-audit' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Lockout Duration', 'bkx-security-audit' ); ?></th>
					<td>
						<input type="number" name="bkx_security_audit_settings[lockout_duration]" class="small-text"
							value="<?php echo esc_attr( $settings['lockout_duration'] ?? 30 ); ?>" min="1" max="1440">
						<?php esc_html_e( 'minutes', 'bkx-security-audit' ); ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Notify on Lockout', 'bkx-security-audit' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_security_audit_settings[notify_admin_on_lockout]" value="1"
								<?php checked( ! empty( $settings['notify_admin_on_lockout'] ) ); ?>>
							<?php esc_html_e( 'Send email to admin when IP is locked out', 'bkx-security-audit' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Two-Factor Authentication', 'bkx-security-audit' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_security_audit_settings[two_factor_enabled]" value="1"
								<?php checked( ! empty( $settings['two_factor_enabled'] ) ); ?>>
							<?php esc_html_e( 'Enable TOTP-based two-factor authentication', 'bkx-security-audit' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Users can enable 2FA from their profile page.', 'bkx-security-audit' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-security-card">
			<h2><?php esc_html_e( 'Security Features', 'bkx-security-audit' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Audit Logging', 'bkx-security-audit' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_security_audit_settings[enable_audit_logging]" value="1"
								<?php checked( ! empty( $settings['enable_audit_logging'] ) ); ?>>
							<?php esc_html_e( 'Log user activities and system changes', 'bkx-security-audit' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'File Integrity Monitoring', 'bkx-security-audit' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_security_audit_settings[enable_file_monitoring]" value="1"
								<?php checked( ! empty( $settings['enable_file_monitoring'] ) ); ?>>
							<?php esc_html_e( 'Monitor core files for unauthorized changes', 'bkx-security-audit' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Security Scanning', 'bkx-security-audit' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_security_audit_settings[enable_security_scanning]" value="1"
								<?php checked( ! empty( $settings['enable_security_scanning'] ) ); ?>>
							<?php esc_html_e( 'Enable automated security scanning', 'bkx-security-audit' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Security Headers', 'bkx-security-audit' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_security_audit_settings[security_headers]" value="1"
								<?php checked( ! empty( $settings['security_headers'] ) ); ?>>
							<?php esc_html_e( 'Add security headers (X-Frame-Options, CSP, etc.)', 'bkx-security-audit' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Disable XML-RPC', 'bkx-security-audit' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_security_audit_settings[disable_xmlrpc]" value="1"
								<?php checked( ! empty( $settings['disable_xmlrpc'] ) ); ?>>
							<?php esc_html_e( 'Disable XML-RPC to prevent attacks', 'bkx-security-audit' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-security-card">
			<h2><?php esc_html_e( 'IP Management', 'bkx-security-audit' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Whitelisted IPs', 'bkx-security-audit' ); ?></th>
					<td>
						<textarea name="bkx_security_audit_settings[allowed_ips]" rows="5" class="large-text code"
							placeholder="<?php esc_attr_e( 'One IP per line', 'bkx-security-audit' ); ?>"><?php echo esc_textarea( $settings['allowed_ips'] ?? '' ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'These IPs will never be locked out.', 'bkx-security-audit' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Blocked IPs', 'bkx-security-audit' ); ?></th>
					<td>
						<textarea name="bkx_security_audit_settings[blocked_ips]" rows="5" class="large-text code"
							placeholder="<?php esc_attr_e( 'One IP per line', 'bkx-security-audit' ); ?>"><?php echo esc_textarea( $settings['blocked_ips'] ?? '' ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'These IPs will be permanently blocked from the site.', 'bkx-security-audit' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-security-card">
			<h2><?php esc_html_e( 'Data Retention', 'bkx-security-audit' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Audit Log Retention', 'bkx-security-audit' ); ?></th>
					<td>
						<select name="bkx_security_audit_settings[audit_retention_days]">
							<?php
							$options = array(
								30  => __( '30 days', 'bkx-security-audit' ),
								60  => __( '60 days', 'bkx-security-audit' ),
								90  => __( '90 days', 'bkx-security-audit' ),
								180 => __( '6 months', 'bkx-security-audit' ),
								365 => __( '1 year', 'bkx-security-audit' ),
								0   => __( 'Forever', 'bkx-security-audit' ),
							);
							$current = $settings['audit_retention_days'] ?? 90;
							foreach ( $options as $value => $label ) :
							?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'How long to keep audit log entries.', 'bkx-security-audit' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Notify on Breach', 'bkx-security-audit' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_security_audit_settings[notify_admin_on_breach]" value="1"
								<?php checked( ! empty( $settings['notify_admin_on_breach'] ) ); ?>>
							<?php esc_html_e( 'Send email when critical security events occur', 'bkx-security-audit' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button(); ?>
	</form>
</div>

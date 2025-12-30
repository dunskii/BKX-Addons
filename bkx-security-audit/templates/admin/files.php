<?php
/**
 * File integrity template.
 *
 * @package BookingX\SecurityAudit
 */

defined( 'ABSPATH' ) || exit;

$addon  = \BookingX\SecurityAudit\SecurityAuditAddon::get_instance();
$files  = $addon->get_service( 'files' );
$status = $files ? $files->get_status() : null;
$changes = $files ? $files->get_recent_changes() : array();
?>

<div class="bkx-security-files">
	<div class="bkx-security-row">
		<div class="bkx-security-half">
			<div class="bkx-security-card">
				<h2><?php esc_html_e( 'File Integrity Monitoring', 'bkx-security-audit' ); ?></h2>
				<p><?php esc_html_e( 'Monitor WordPress core files and plugin files for unauthorized modifications.', 'bkx-security-audit' ); ?></p>

				<div class="bkx-file-actions">
					<?php if ( $status && $status['baseline_date'] ) : ?>
						<button type="button" class="button button-primary" id="bkx-check-integrity">
							<span class="dashicons dashicons-search"></span>
							<?php esc_html_e( 'Check Files', 'bkx-security-audit' ); ?>
						</button>
						<button type="button" class="button" id="bkx-reset-baseline">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Reset Baseline', 'bkx-security-audit' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button button-primary" id="bkx-init-baseline">
							<span class="dashicons dashicons-database-add"></span>
							<?php esc_html_e( 'Initialize Baseline', 'bkx-security-audit' ); ?>
						</button>
					<?php endif; ?>
				</div>

				<?php if ( $status && $status['baseline_date'] ) : ?>
					<div class="bkx-file-status">
						<h3><?php esc_html_e( 'Status', 'bkx-security-audit' ); ?></h3>
						<table class="widefat">
							<tr>
								<td><?php esc_html_e( 'Baseline Created', 'bkx-security-audit' ); ?></td>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $status['baseline_date'] ) ) ); ?></td>
							</tr>
							<?php if ( $status['last_check'] ) : ?>
								<tr>
									<td><?php esc_html_e( 'Last Check', 'bkx-security-audit' ); ?></td>
									<td><?php echo esc_html( human_time_diff( strtotime( $status['last_check']['timestamp'] ) ) . ' ago' ); ?></td>
								</tr>
							<?php endif; ?>
							<tr>
								<td><?php esc_html_e( 'Total Files', 'bkx-security-audit' ); ?></td>
								<td><?php echo esc_html( $status['stats']['total'] ?? 0 ); ?></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Modified', 'bkx-security-audit' ); ?></td>
								<td>
									<?php if ( ( $status['stats']['modified'] ?? 0 ) > 0 ) : ?>
										<span class="bkx-count-warning"><?php echo esc_html( $status['stats']['modified'] ); ?></span>
									<?php else : ?>
										<?php echo esc_html( $status['stats']['modified'] ?? 0 ); ?>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'New Files', 'bkx-security-audit' ); ?></td>
								<td>
									<?php if ( ( $status['stats']['new_files'] ?? 0 ) > 0 ) : ?>
										<span class="bkx-count-warning"><?php echo esc_html( $status['stats']['new_files'] ); ?></span>
									<?php else : ?>
										<?php echo esc_html( $status['stats']['new_files'] ?? 0 ); ?>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Deleted', 'bkx-security-audit' ); ?></td>
								<td>
									<?php if ( ( $status['stats']['deleted'] ?? 0 ) > 0 ) : ?>
										<span class="bkx-count-warning"><?php echo esc_html( $status['stats']['deleted'] ); ?></span>
									<?php else : ?>
										<?php echo esc_html( $status['stats']['deleted'] ?? 0 ); ?>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>
				<?php else : ?>
					<div class="bkx-no-baseline">
						<span class="dashicons dashicons-database"></span>
						<h3><?php esc_html_e( 'No Baseline Set', 'bkx-security-audit' ); ?></h3>
						<p><?php esc_html_e( 'Initialize a baseline to start monitoring file changes.', 'bkx-security-audit' ); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<div class="bkx-security-card">
				<h2><?php esc_html_e( 'Monitored Directories', 'bkx-security-audit' ); ?></h2>
				<ul class="bkx-dir-list">
					<li><code>wp-admin/</code></li>
					<li><code>wp-includes/</code></li>
					<li><code>wp-content/plugins/bookingx/</code></li>
				</ul>
				<p class="description">
					<?php esc_html_e( 'PHP, JS, CSS, and HTML files are monitored for changes.', 'bkx-security-audit' ); ?>
				</p>
			</div>
		</div>

		<div class="bkx-security-half">
			<div class="bkx-security-card">
				<h2><?php esc_html_e( 'Recent Changes', 'bkx-security-audit' ); ?></h2>

				<?php if ( ! empty( $changes ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'File', 'bkx-security-audit' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-security-audit' ); ?></th>
								<th><?php esc_html_e( 'Detected', 'bkx-security-audit' ); ?></th>
								<th><?php esc_html_e( 'Action', 'bkx-security-audit' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $changes as $change ) : ?>
								<tr>
									<td>
										<code class="bkx-file-path" title="<?php echo esc_attr( $change['file_path'] ); ?>">
											<?php echo esc_html( basename( $change['file_path'] ) ); ?>
										</code>
									</td>
									<td>
										<span class="bkx-file-status bkx-file-<?php echo esc_attr( $change['status'] ); ?>">
											<?php echo esc_html( ucfirst( $change['status'] ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( human_time_diff( strtotime( $change['checked_at'] ) ) . ' ago' ); ?></td>
									<td>
										<button type="button" class="button button-small bkx-accept-change" data-path="<?php echo esc_attr( $change['file_path'] ); ?>">
											<?php esc_html_e( 'Accept', 'bkx-security-audit' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="bkx-security-no-data">
						<?php if ( $status && $status['baseline_date'] ) : ?>
							<?php esc_html_e( 'No file changes detected.', 'bkx-security-audit' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Initialize baseline to start monitoring.', 'bkx-security-audit' ); ?>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

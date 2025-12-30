<?php
/**
 * HIPAA Compliance Dashboard.
 *
 * @package BookingX\HIPAA
 */

defined( 'ABSPATH' ) || exit;

$addon        = \BookingX\HIPAA\HIPAAAddon::get_instance();
$audit_logger = $addon->get_service( 'audit_logger' );
$baa_manager  = $addon->get_service( 'baa_manager' );
$settings     = get_option( 'bkx_hipaa_settings', array() );

$phi_summary  = $audit_logger->get_phi_access_summary( 30 );
$baa_stats    = $baa_manager->get_statistics();
$total_logs   = $audit_logger->count_logs();
$phi_logs     = $audit_logger->count_logs( array( 'phi_accessed' => true ) );
?>

<div class="bkx-dashboard">
	<!-- Status Banner -->
	<div class="bkx-status-banner <?php echo ! empty( $settings['enabled'] ) ? 'bkx-enabled' : 'bkx-disabled'; ?>">
		<?php if ( ! empty( $settings['enabled'] ) ) : ?>
			<span class="dashicons dashicons-shield-alt"></span>
			<strong><?php esc_html_e( 'HIPAA Compliance Mode: Active', 'bkx-hipaa' ); ?></strong>
			<p><?php esc_html_e( 'All PHI is encrypted and access is being logged.', 'bkx-hipaa' ); ?></p>
		<?php else : ?>
			<span class="dashicons dashicons-warning"></span>
			<strong><?php esc_html_e( 'HIPAA Compliance Mode: Inactive', 'bkx-hipaa' ); ?></strong>
			<p><?php esc_html_e( 'Enable HIPAA compliance in settings to protect PHI.', 'bkx-hipaa' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Stats Grid -->
	<div class="bkx-stats-grid">
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $total_logs ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Total Audit Events', 'bkx-hipaa' ); ?></span>
		</div>
		<div class="bkx-stat-card bkx-stat-phi">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $phi_logs ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'PHI Access Events', 'bkx-hipaa' ); ?></span>
		</div>
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( $baa_stats['active'] ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Active BAAs', 'bkx-hipaa' ); ?></span>
		</div>
		<div class="bkx-stat-card <?php echo $baa_stats['expiring'] > 0 ? 'bkx-stat-warning' : ''; ?>">
			<span class="bkx-stat-value"><?php echo esc_html( $baa_stats['expiring'] ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'BAAs Expiring Soon', 'bkx-hipaa' ); ?></span>
		</div>
	</div>

	<div class="bkx-dashboard-grid">
		<!-- Compliance Checklist -->
		<div class="bkx-dashboard-card">
			<h3><?php esc_html_e( 'Compliance Checklist', 'bkx-hipaa' ); ?></h3>
			<ul class="bkx-checklist">
				<li class="<?php echo ! empty( $settings['enabled'] ) ? 'bkx-check-pass' : 'bkx-check-fail'; ?>">
					<span class="dashicons <?php echo ! empty( $settings['enabled'] ) ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
					<?php esc_html_e( 'PHI Encryption Enabled', 'bkx-hipaa' ); ?>
				</li>
				<li class="<?php echo ! empty( $settings['require_strong_password'] ) ? 'bkx-check-pass' : 'bkx-check-fail'; ?>">
					<span class="dashicons <?php echo ! empty( $settings['require_strong_password'] ) ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
					<?php esc_html_e( 'Strong Password Required', 'bkx-hipaa' ); ?>
				</li>
				<li class="<?php echo ! empty( $settings['auto_logout_minutes'] ) ? 'bkx-check-pass' : 'bkx-check-fail'; ?>">
					<span class="dashicons <?php echo ! empty( $settings['auto_logout_minutes'] ) ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
					<?php esc_html_e( 'Auto-Logout Enabled', 'bkx-hipaa' ); ?>
				</li>
				<li class="<?php echo $baa_stats['active'] > 0 ? 'bkx-check-pass' : 'bkx-check-warn'; ?>">
					<span class="dashicons <?php echo $baa_stats['active'] > 0 ? 'dashicons-yes' : 'dashicons-warning'; ?>"></span>
					<?php esc_html_e( 'BAAs in Place', 'bkx-hipaa' ); ?>
				</li>
				<li class="bkx-check-pass">
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'Audit Logging Active', 'bkx-hipaa' ); ?>
				</li>
				<li class="<?php echo extension_loaded( 'sodium' ) ? 'bkx-check-pass' : 'bkx-check-fail'; ?>">
					<span class="dashicons <?php echo extension_loaded( 'sodium' ) ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
					<?php esc_html_e( 'Libsodium Encryption Available', 'bkx-hipaa' ); ?>
				</li>
			</ul>
		</div>

		<!-- PHI Access by User -->
		<div class="bkx-dashboard-card">
			<h3><?php esc_html_e( 'PHI Access by User (30 Days)', 'bkx-hipaa' ); ?></h3>
			<?php if ( ! empty( $phi_summary['by_user'] ) ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'User', 'bkx-hipaa' ); ?></th>
							<th><?php esc_html_e( 'Access Count', 'bkx-hipaa' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $phi_summary['by_user'] as $row ) : ?>
							<?php $user = get_user_by( 'id', $row->user_id ); ?>
							<tr>
								<td>
									<?php echo $user ? esc_html( $user->display_name ) : esc_html__( 'Unknown', 'bkx-hipaa' ); ?>
								</td>
								<td><?php echo esc_html( number_format( $row->access_count ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="bkx-no-data"><?php esc_html_e( 'No PHI access in the last 30 days.', 'bkx-hipaa' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Recent Activity -->
		<div class="bkx-dashboard-card bkx-card-wide">
			<h3><?php esc_html_e( 'Recent Activity', 'bkx-hipaa' ); ?></h3>
			<?php
			$recent_logs = $audit_logger->get_logs( array( 'limit' => 10 ) );
			if ( ! empty( $recent_logs ) ) :
				?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'bkx-hipaa' ); ?></th>
							<th><?php esc_html_e( 'Event', 'bkx-hipaa' ); ?></th>
							<th><?php esc_html_e( 'User', 'bkx-hipaa' ); ?></th>
							<th><?php esc_html_e( 'PHI', 'bkx-hipaa' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_logs as $log ) : ?>
							<?php $user = $log->user_id ? get_user_by( 'id', $log->user_id ) : null; ?>
							<tr>
								<td><?php echo esc_html( human_time_diff( strtotime( $log->created_at ), current_time( 'timestamp' ) ) . ' ago' ); ?></td>
								<td>
									<span class="bkx-event-type"><?php echo esc_html( $log->event_type ); ?></span>
									<span class="bkx-event-action"><?php echo esc_html( $log->event_action ); ?></span>
								</td>
								<td><?php echo $user ? esc_html( $user->display_name ) : '—'; ?></td>
								<td>
									<?php if ( $log->phi_accessed ) : ?>
										<span class="bkx-phi-badge"><?php esc_html_e( 'PHI', 'bkx-hipaa' ); ?></span>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-hipaa&tab=audit' ) ); ?>">
						<?php esc_html_e( 'View Full Audit Log', 'bkx-hipaa' ); ?> &rarr;
					</a>
				</p>
			<?php else : ?>
				<p class="bkx-no-data"><?php esc_html_e( 'No activity recorded yet.', 'bkx-hipaa' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>

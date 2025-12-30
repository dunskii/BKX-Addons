<?php
/**
 * Dashboard template.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

$addon = \BookingX\GdprCompliance\GdprComplianceAddon::get_instance();

$request_counts = $addon->get_service( 'requests' )->count_by_status();
$breach_counts  = $addon->get_service( 'breaches' )->count_by_status();
$retention      = $addon->get_service( 'retention' )->get_retention_policies();

$settings = get_option( 'bkx_gdpr_settings', array() );
?>

<div class="bkx-gdpr-dashboard">
	<!-- Status Overview -->
	<div class="bkx-gdpr-stats-row">
		<div class="bkx-gdpr-stat-card">
			<span class="bkx-gdpr-stat-value"><?php echo esc_html( $request_counts['verified'] ); ?></span>
			<span class="bkx-gdpr-stat-label"><?php esc_html_e( 'Pending Requests', 'bkx-gdpr-compliance' ); ?></span>
			<?php if ( $request_counts['verified'] > 0 ) : ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-gdpr&tab=requests' ) ); ?>" class="bkx-gdpr-stat-link">
					<?php esc_html_e( 'Review', 'bkx-gdpr-compliance' ); ?> &rarr;
				</a>
			<?php endif; ?>
		</div>
		<div class="bkx-gdpr-stat-card">
			<span class="bkx-gdpr-stat-value"><?php echo esc_html( $request_counts['completed'] ); ?></span>
			<span class="bkx-gdpr-stat-label"><?php esc_html_e( 'Completed Requests', 'bkx-gdpr-compliance' ); ?></span>
		</div>
		<div class="bkx-gdpr-stat-card <?php echo $breach_counts['open'] > 0 ? 'bkx-gdpr-stat-warning' : ''; ?>">
			<span class="bkx-gdpr-stat-value"><?php echo esc_html( $breach_counts['open'] ); ?></span>
			<span class="bkx-gdpr-stat-label"><?php esc_html_e( 'Open Breaches', 'bkx-gdpr-compliance' ); ?></span>
			<?php if ( $breach_counts['open'] > 0 ) : ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-gdpr&tab=breaches' ) ); ?>" class="bkx-gdpr-stat-link">
					<?php esc_html_e( 'View', 'bkx-gdpr-compliance' ); ?> &rarr;
				</a>
			<?php endif; ?>
		</div>
		<div class="bkx-gdpr-stat-card">
			<span class="bkx-gdpr-stat-value bkx-gdpr-stat-ok">
				<?php echo ! empty( $settings['enabled'] ) ? '&#10003;' : '&#10007;'; ?>
			</span>
			<span class="bkx-gdpr-stat-label"><?php esc_html_e( 'GDPR Active', 'bkx-gdpr-compliance' ); ?></span>
		</div>
	</div>

	<div class="bkx-gdpr-row">
		<!-- Compliance Checklist -->
		<div class="bkx-gdpr-card bkx-gdpr-half">
			<h2><?php esc_html_e( 'Compliance Checklist', 'bkx-gdpr-compliance' ); ?></h2>
			<ul class="bkx-gdpr-checklist">
				<li class="<?php echo ! empty( $settings['privacy_policy_page'] ) ? 'complete' : 'incomplete'; ?>">
					<?php esc_html_e( 'Privacy Policy page configured', 'bkx-gdpr-compliance' ); ?>
				</li>
				<li class="<?php echo ! empty( $settings['cookie_banner_enabled'] ) ? 'complete' : 'incomplete'; ?>">
					<?php esc_html_e( 'Cookie consent banner enabled', 'bkx-gdpr-compliance' ); ?>
				</li>
				<li class="<?php echo ! empty( $settings['consent_required'] ) ? 'complete' : 'incomplete'; ?>">
					<?php esc_html_e( 'Consent checkboxes on booking form', 'bkx-gdpr-compliance' ); ?>
				</li>
				<li class="<?php echo ! empty( $settings['dpo_email'] ) ? 'complete' : 'incomplete'; ?>">
					<?php esc_html_e( 'Data Protection Officer contact set', 'bkx-gdpr-compliance' ); ?>
				</li>
				<li class="<?php echo $settings['data_retention_days'] > 0 ? 'complete' : 'incomplete'; ?>">
					<?php esc_html_e( 'Data retention policy configured', 'bkx-gdpr-compliance' ); ?>
				</li>
				<li class="complete">
					<?php esc_html_e( 'Data export functionality available', 'bkx-gdpr-compliance' ); ?>
				</li>
				<li class="complete">
					<?php esc_html_e( 'Right to erasure functionality available', 'bkx-gdpr-compliance' ); ?>
				</li>
				<li class="<?php echo ! empty( $settings['breach_notification_emails'] ) ? 'complete' : 'incomplete'; ?>">
					<?php esc_html_e( 'Breach notification contacts configured', 'bkx-gdpr-compliance' ); ?>
				</li>
			</ul>
			<p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-gdpr&tab=settings' ) ); ?>" class="button">
					<?php esc_html_e( 'Configure Settings', 'bkx-gdpr-compliance' ); ?>
				</a>
			</p>
		</div>

		<!-- Quick Actions -->
		<div class="bkx-gdpr-card bkx-gdpr-half">
			<h2><?php esc_html_e( 'Quick Actions', 'bkx-gdpr-compliance' ); ?></h2>
			<div class="bkx-gdpr-quick-actions">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-gdpr&tab=policies' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Generate Privacy Policy', 'bkx-gdpr-compliance' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-gdpr&tab=policies' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Generate Cookie Policy', 'bkx-gdpr-compliance' ); ?>
				</a>
				<button type="button" class="button button-secondary" id="bkx-gdpr-export-all">
					<?php esc_html_e( 'Export All Data Requests', 'bkx-gdpr-compliance' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="bkx-gdpr-run-retention">
					<?php esc_html_e( 'Run Data Retention Check', 'bkx-gdpr-compliance' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Data Retention Policies -->
	<div class="bkx-gdpr-card">
		<h2><?php esc_html_e( 'Data Retention Policies', 'bkx-gdpr-compliance' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Data Type', 'bkx-gdpr-compliance' ); ?></th>
					<th><?php esc_html_e( 'Retention Period', 'bkx-gdpr-compliance' ); ?></th>
					<th><?php esc_html_e( 'Action', 'bkx-gdpr-compliance' ); ?></th>
					<th><?php esc_html_e( 'Legal Basis', 'bkx-gdpr-compliance' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $retention as $policy ) : ?>
					<tr>
						<td><?php echo esc_html( $policy['data_type'] ); ?></td>
						<td><?php echo esc_html( $policy['retention'] ); ?></td>
						<td><?php echo esc_html( $policy['action'] ); ?></td>
						<td><?php echo esc_html( $policy['legal_basis'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<!-- Recent Activity -->
	<div class="bkx-gdpr-card">
		<h2><?php esc_html_e( 'Recent Data Requests', 'bkx-gdpr-compliance' ); ?></h2>
		<?php
		$recent_requests = $addon->get_service( 'requests' )->get_requests(
			array(
				'per_page' => 5,
				'page'     => 1,
			)
		);
		?>
		<?php if ( empty( $recent_requests ) ) : ?>
			<p class="bkx-gdpr-no-items"><?php esc_html_e( 'No data requests yet.', 'bkx-gdpr-compliance' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'Email', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-gdpr-compliance' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_requests as $request ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $request->created_at ) ) ); ?></td>
							<td><?php echo esc_html( ucfirst( $request->request_type ) ); ?></td>
							<td><?php echo esc_html( $request->email ); ?></td>
							<td>
								<span class="bkx-gdpr-status bkx-gdpr-status-<?php echo esc_attr( $request->status ); ?>">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $request->status ) ) ); ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-gdpr&tab=requests' ) ); ?>">
					<?php esc_html_e( 'View all requests', 'bkx-gdpr-compliance' ); ?> &rarr;
				</a>
			</p>
		<?php endif; ?>
	</div>
</div>

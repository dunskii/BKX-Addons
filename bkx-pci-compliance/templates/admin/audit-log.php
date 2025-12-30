<?php
/**
 * Audit Log template.
 *
 * @package BookingX\PCICompliance
 */

defined( 'ABSPATH' ) || exit;

$audit_logger = new \BookingX\PCICompliance\Services\PCIAuditLogger();

$page     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$filters  = array(
	'category' => isset( $_GET['category'] ) ? sanitize_key( $_GET['category'] ) : '',
	'severity' => isset( $_GET['severity'] ) ? sanitize_key( $_GET['severity'] ) : '',
	'search'   => isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '',
);
$result   = $audit_logger->get_logs( $page, 50, $filters );
$logs     = $result['logs'];
$total    = $result['total'];
$pages    = $result['pages'];
?>
<div class="wrap bkx-pci-compliance">
	<h1><?php esc_html_e( 'PCI Audit Log', 'bkx-pci-compliance' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Complete audit trail of all security-relevant events as required by PCI DSS Requirement 10.', 'bkx-pci-compliance' ); ?>
	</p>

	<div class="bkx-audit-filters">
		<form method="get">
			<input type="hidden" name="page" value="bkx-pci-audit-log">
			<select name="category">
				<option value=""><?php esc_html_e( 'All Categories', 'bkx-pci-compliance' ); ?></option>
				<option value="authentication" <?php selected( $filters['category'], 'authentication' ); ?>><?php esc_html_e( 'Authentication', 'bkx-pci-compliance' ); ?></option>
				<option value="data_access" <?php selected( $filters['category'], 'data_access' ); ?>><?php esc_html_e( 'Data Access', 'bkx-pci-compliance' ); ?></option>
				<option value="configuration" <?php selected( $filters['category'], 'configuration' ); ?>><?php esc_html_e( 'Configuration', 'bkx-pci-compliance' ); ?></option>
				<option value="payment" <?php selected( $filters['category'], 'payment' ); ?>><?php esc_html_e( 'Payment', 'bkx-pci-compliance' ); ?></option>
				<option value="security" <?php selected( $filters['category'], 'security' ); ?>><?php esc_html_e( 'Security', 'bkx-pci-compliance' ); ?></option>
			</select>
			<select name="severity">
				<option value=""><?php esc_html_e( 'All Severities', 'bkx-pci-compliance' ); ?></option>
				<option value="critical" <?php selected( $filters['severity'], 'critical' ); ?>><?php esc_html_e( 'Critical', 'bkx-pci-compliance' ); ?></option>
				<option value="warning" <?php selected( $filters['severity'], 'warning' ); ?>><?php esc_html_e( 'Warning', 'bkx-pci-compliance' ); ?></option>
				<option value="info" <?php selected( $filters['severity'], 'info' ); ?>><?php esc_html_e( 'Info', 'bkx-pci-compliance' ); ?></option>
			</select>
			<input type="search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search...', 'bkx-pci-compliance' ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-pci-compliance' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-pci-audit-log' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'bkx-pci-compliance' ); ?></a>
		</form>

		<div class="bkx-export-actions">
			<button type="button" class="button" id="bkx-export-audit-csv">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export CSV', 'bkx-pci-compliance' ); ?>
			</button>
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th class="column-date"><?php esc_html_e( 'Date/Time', 'bkx-pci-compliance' ); ?></th>
				<th class="column-event"><?php esc_html_e( 'Event', 'bkx-pci-compliance' ); ?></th>
				<th class="column-category"><?php esc_html_e( 'Category', 'bkx-pci-compliance' ); ?></th>
				<th class="column-severity"><?php esc_html_e( 'Severity', 'bkx-pci-compliance' ); ?></th>
				<th class="column-user"><?php esc_html_e( 'User', 'bkx-pci-compliance' ); ?></th>
				<th class="column-ip"><?php esc_html_e( 'IP Address', 'bkx-pci-compliance' ); ?></th>
				<th class="column-pci"><?php esc_html_e( 'PCI Req.', 'bkx-pci-compliance' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr>
					<td colspan="7" class="no-items"><?php esc_html_e( 'No audit log entries found.', 'bkx-pci-compliance' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td class="column-date">
							<?php echo esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $log['created_at'] ) ) ); ?>
						</td>
						<td class="column-event">
							<strong><?php echo esc_html( str_replace( '_', ' ', ucfirst( $log['event_type'] ) ) ); ?></strong>
							<?php if ( ! empty( $log['details'] ) ) : ?>
								<button type="button" class="button-link bkx-show-details" data-details="<?php echo esc_attr( wp_json_encode( $log['details'] ) ); ?>">
									<?php esc_html_e( 'Details', 'bkx-pci-compliance' ); ?>
								</button>
							<?php endif; ?>
						</td>
						<td class="column-category">
							<span class="bkx-category-badge bkx-category-<?php echo esc_attr( $log['event_category'] ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $log['event_category'] ) ) ); ?>
							</span>
						</td>
						<td class="column-severity">
							<span class="bkx-severity-badge bkx-severity-<?php echo esc_attr( $log['severity'] ); ?>">
								<?php echo esc_html( ucfirst( $log['severity'] ) ); ?>
							</span>
						</td>
						<td class="column-user">
							<?php echo esc_html( $log['user_display'] ); ?>
						</td>
						<td class="column-ip">
							<code><?php echo esc_html( $log['ip_address'] ); ?></code>
						</td>
						<td class="column-pci">
							<?php if ( ! empty( $log['pci_requirement'] ) ) : ?>
								<span class="bkx-pci-req"><?php echo esc_html( $log['pci_requirement'] ); ?></span>
							<?php else : ?>
								-
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo paginate_links( array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'total'     => $pages,
					'current'   => $page,
				) );
				?>
			</div>
		</div>
	<?php endif; ?>

	<p class="bkx-retention-notice">
		<?php
		$settings  = get_option( 'bkx_pci_compliance_settings', array() );
		$retention = $settings['audit_log_retention'] ?? 365;
		printf(
			/* translators: %d: Retention days */
			esc_html__( 'Logs are retained for %d days as required by PCI DSS 10.7.', 'bkx-pci-compliance' ),
			$retention
		);
		?>
	</p>
</div>

<!-- Details Modal -->
<div id="bkx-details-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Event Details', 'bkx-pci-compliance' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<pre id="bkx-details-content"></pre>
		</div>
	</div>
</div>

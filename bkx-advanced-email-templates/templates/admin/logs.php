<?php
/**
 * Email Logs Template.
 *
 * @package BookingX\AdvancedEmailTemplates
 * @var array  $logs         Email logs.
 * @var int    $current_page Current page.
 * @var int    $pages        Total pages.
 * @var int    $total        Total logs.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap bkx-email-logs">
	<h1><?php esc_html_e( 'Email Logs', 'bkx-advanced-email-templates' ); ?></h1>

	<div class="bkx-log-stats">
		<div class="stat-card">
			<span class="stat-number"><?php echo esc_html( $total ); ?></span>
			<span class="stat-label"><?php esc_html_e( 'Total Sent', 'bkx-advanced-email-templates' ); ?></span>
		</div>
	</div>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 60px;"><?php esc_html_e( 'ID', 'bkx-advanced-email-templates' ); ?></th>
				<th><?php esc_html_e( 'Template', 'bkx-advanced-email-templates' ); ?></th>
				<th><?php esc_html_e( 'Recipient', 'bkx-advanced-email-templates' ); ?></th>
				<th><?php esc_html_e( 'Subject', 'bkx-advanced-email-templates' ); ?></th>
				<th style="width: 80px;"><?php esc_html_e( 'Status', 'bkx-advanced-email-templates' ); ?></th>
				<th style="width: 80px;"><?php esc_html_e( 'Opened', 'bkx-advanced-email-templates' ); ?></th>
				<th style="width: 150px;"><?php esc_html_e( 'Sent At', 'bkx-advanced-email-templates' ); ?></th>
				<th style="width: 80px;"><?php esc_html_e( 'Actions', 'bkx-advanced-email-templates' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr>
					<td colspan="8"><?php esc_html_e( 'No email logs found.', 'bkx-advanced-email-templates' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : ?>
					<tr data-id="<?php echo esc_attr( $log->id ); ?>">
						<td>#<?php echo esc_html( $log->id ); ?></td>
						<td><?php echo esc_html( $log->template_name ?? '-' ); ?></td>
						<td><?php echo esc_html( $log->recipient ); ?></td>
						<td><?php echo esc_html( $log->subject ); ?></td>
						<td>
							<span class="bkx-status-badge <?php echo esc_attr( $log->status ); ?>">
								<?php echo esc_html( ucfirst( $log->status ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $log->opened_at ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="<?php echo esc_attr( $log->opened_at ); ?>"></span>
							<?php else : ?>
								<span class="dashicons dashicons-minus" style="color: #999;"></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->sent_at ) ) ); ?>
						</td>
						<td>
							<button type="button" class="button button-small bkx-view-log" data-id="<?php echo esc_attr( $log->id ); ?>">
								<?php esc_html_e( 'View', 'bkx-advanced-email-templates' ); ?>
							</button>
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
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $current_page,
							'total'   => $pages,
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>

<!-- Log Detail Modal -->
<div id="bkx-log-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content bkx-modal-large">
		<span class="bkx-modal-close">&times;</span>
		<h3><?php esc_html_e( 'Email Details', 'bkx-advanced-email-templates' ); ?></h3>
		<div id="bkx-log-detail"></div>
	</div>
</div>

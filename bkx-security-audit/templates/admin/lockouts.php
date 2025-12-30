<?php
/**
 * Lockouts management template.
 *
 * @package BookingX\SecurityAudit
 */

defined( 'ABSPATH' ) || exit;

$addon = \BookingX\SecurityAudit\SecurityAuditAddon::get_instance();
$login = $addon->get_service( 'login' );

$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$active_only  = isset( $_GET['active_only'] ) && $_GET['active_only'] === '1';

$lockouts = $login ? $login->get_lockouts( array(
	'page'        => $current_page,
	'per_page'    => 20,
	'active_only' => $active_only,
) ) : array( 'lockouts' => array(), 'total' => 0, 'pages' => 0 );
?>

<div class="bkx-security-lockouts">
	<div class="bkx-security-card">
		<div class="bkx-security-card-header">
			<h2><?php esc_html_e( 'IP Lockouts', 'bkx-security-audit' ); ?></h2>
		</div>

		<!-- Filters -->
		<form method="get" class="bkx-security-filters">
			<input type="hidden" name="page" value="bkx-security-audit">
			<input type="hidden" name="tab" value="lockouts">

			<label>
				<input type="checkbox" name="active_only" value="1" <?php checked( $active_only ); ?>>
				<?php esc_html_e( 'Active only', 'bkx-security-audit' ); ?>
			</label>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-security-audit' ); ?></button>
		</form>

		<?php if ( ! empty( $lockouts['lockouts'] ) ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'IP Address', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'Reason', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'Attempts', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'Locked Until', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-security-audit' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-security-audit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $lockouts['lockouts'] as $lockout ) : ?>
						<?php
						$is_active = strtotime( $lockout['locked_until'] ) > time() && empty( $lockout['unlocked_at'] );
						?>
						<tr>
							<td><code><?php echo esc_html( $lockout['ip_address'] ); ?></code></td>
							<td><?php echo esc_html( str_replace( '_', ' ', $lockout['lockout_reason'] ) ); ?></td>
							<td><?php echo esc_html( $lockout['attempts_count'] ); ?></td>
							<td>
								<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $lockout['locked_until'] ) ) ); ?>
								<?php if ( $is_active ) : ?>
									<br><small>(<?php echo esc_html( human_time_diff( time(), strtotime( $lockout['locked_until'] ) ) ); ?> remaining)</small>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $is_active ) : ?>
									<span class="bkx-status bkx-status-active"><?php esc_html_e( 'Active', 'bkx-security-audit' ); ?></span>
								<?php elseif ( ! empty( $lockout['unlocked_at'] ) ) : ?>
									<span class="bkx-status bkx-status-cleared"><?php esc_html_e( 'Cleared', 'bkx-security-audit' ); ?></span>
								<?php else : ?>
									<span class="bkx-status bkx-status-expired"><?php esc_html_e( 'Expired', 'bkx-security-audit' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $is_active ) : ?>
									<button type="button" class="button button-small bkx-clear-lockout" data-ip="<?php echo esc_attr( $lockout['ip_address'] ); ?>">
										<?php esc_html_e( 'Clear', 'bkx-security-audit' ); ?>
									</button>
								<?php endif; ?>
								<button type="button" class="button button-small bkx-block-ip" data-ip="<?php echo esc_attr( $lockout['ip_address'] ); ?>">
									<?php esc_html_e( 'Block', 'bkx-security-audit' ); ?>
								</button>
								<button type="button" class="button button-small bkx-whitelist-ip" data-ip="<?php echo esc_attr( $lockout['ip_address'] ); ?>">
									<?php esc_html_e( 'Whitelist', 'bkx-security-audit' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $lockouts['pages'] > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo wp_kses_post( paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $lockouts['pages'],
							'current'   => $current_page,
						) ) );
						?>
					</div>
				</div>
			<?php endif; ?>

		<?php else : ?>
			<p class="bkx-security-no-data"><?php esc_html_e( 'No lockouts found.', 'bkx-security-audit' ); ?></p>
		<?php endif; ?>
	</div>
</div>

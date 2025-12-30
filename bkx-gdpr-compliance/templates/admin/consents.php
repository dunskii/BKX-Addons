<?php
/**
 * Consents template.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 50;
$offset   = ( $page - 1 ) * $per_page;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$consents = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT email, consent_type, consent_given, given_at, withdrawn_at, source
		FROM {$wpdb->prefix}bkx_consent_records
		ORDER BY created_at DESC
		LIMIT %d OFFSET %d",
		$per_page,
		$offset
	)
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bkx_consent_records" );
$pages = ceil( $total / $per_page );
?>

<div class="bkx-gdpr-consents">
	<div class="bkx-gdpr-card">
		<h2><?php esc_html_e( 'Consent Records', 'bkx-gdpr-compliance' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'View all recorded consents from customers. These records are required for GDPR compliance.', 'bkx-gdpr-compliance' ); ?>
		</p>

		<?php if ( empty( $consents ) ) : ?>
			<p class="bkx-gdpr-no-items"><?php esc_html_e( 'No consent records found.', 'bkx-gdpr-compliance' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Email', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'Consent Type', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'Given', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'Withdrawn', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'Source', 'bkx-gdpr-compliance' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $consents as $consent ) : ?>
						<tr>
							<td><?php echo esc_html( $consent->email ); ?></td>
							<td>
								<span class="bkx-gdpr-badge bkx-gdpr-badge-<?php echo esc_attr( $consent->consent_type ); ?>">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $consent->consent_type ) ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $consent->consent_given ) : ?>
									<span class="bkx-gdpr-status bkx-gdpr-status-active"><?php esc_html_e( 'Active', 'bkx-gdpr-compliance' ); ?></span>
								<?php else : ?>
									<span class="bkx-gdpr-status bkx-gdpr-status-withdrawn"><?php esc_html_e( 'Withdrawn', 'bkx-gdpr-compliance' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo $consent->given_at ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $consent->given_at ) ) ) : '—'; ?>
							</td>
							<td>
								<?php echo $consent->withdrawn_at ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $consent->withdrawn_at ) ) ) : '—'; ?>
							</td>
							<td><?php echo esc_html( ucfirst( $consent->source ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %s: number of items */
								esc_html( _n( '%s item', '%s items', $total, 'bkx-gdpr-compliance' ) ),
								number_format_i18n( $total )
							);
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

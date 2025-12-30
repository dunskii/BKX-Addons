<?php
/**
 * Breaches template.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\GdprCompliance\GdprComplianceAddon::get_instance();
$breaches = $addon->get_service( 'breaches' )->get_breaches();
$counts   = $addon->get_service( 'breaches' )->count_by_status();
?>

<div class="bkx-gdpr-breaches">
	<div class="bkx-gdpr-card">
		<div class="bkx-gdpr-card-header">
			<h2><?php esc_html_e( 'Data Breach Log', 'bkx-gdpr-compliance' ); ?></h2>
			<button type="button" class="button button-primary" id="bkx-gdpr-report-breach">
				<?php esc_html_e( 'Report Breach', 'bkx-gdpr-compliance' ); ?>
			</button>
		</div>
		<p class="description">
			<?php esc_html_e( 'GDPR Article 33 requires breach notification to the supervisory authority within 72 hours. Use this log to track and document all data breaches.', 'bkx-gdpr-compliance' ); ?>
		</p>

		<?php if ( empty( $breaches ) ) : ?>
			<p class="bkx-gdpr-no-items"><?php esc_html_e( 'No data breaches recorded.', 'bkx-gdpr-compliance' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'Nature', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'Subjects', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'DPA Notified', 'bkx-gdpr-compliance' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-gdpr-compliance' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $breaches as $breach ) : ?>
						<tr>
							<td>
								<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $breach->breach_date ) ) ); ?>
								<br><small><?php esc_html_e( 'Discovered:', 'bkx-gdpr-compliance' ); ?> <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $breach->discovered_date ) ) ); ?></small>
							</td>
							<td><?php echo esc_html( wp_trim_words( $breach->nature, 10 ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $breach->subjects_affected ) ); ?></td>
							<td>
								<span class="bkx-gdpr-status bkx-gdpr-status-<?php echo esc_attr( $breach->status ); ?>">
									<?php echo esc_html( ucfirst( $breach->status ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $breach->dpa_notified ) : ?>
									<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
									<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $breach->dpa_notified_at ) ) ); ?>
								<?php else : ?>
									<span class="dashicons dashicons-warning" style="color: orange;"></span>
									<?php esc_html_e( 'Not yet', 'bkx-gdpr-compliance' ); ?>
								<?php endif; ?>
							</td>
							<td>
								<button type="button" class="button button-small bkx-gdpr-view-breach" data-id="<?php echo esc_attr( $breach->id ); ?>">
									<?php esc_html_e( 'View', 'bkx-gdpr-compliance' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<!-- Report Breach Modal -->
<div id="bkx-gdpr-breach-modal" class="bkx-gdpr-modal" style="display: none;">
	<div class="bkx-gdpr-modal-content">
		<span class="bkx-gdpr-modal-close">&times;</span>
		<h2><?php esc_html_e( 'Report Data Breach', 'bkx-gdpr-compliance' ); ?></h2>

		<form id="bkx-gdpr-breach-form">
			<table class="form-table">
				<tr>
					<th><label for="breach_date"><?php esc_html_e( 'Breach Date', 'bkx-gdpr-compliance' ); ?> <span class="required">*</span></label></th>
					<td><input type="datetime-local" id="breach_date" name="breach_date" required></td>
				</tr>
				<tr>
					<th><label for="discovered_date"><?php esc_html_e( 'Discovered Date', 'bkx-gdpr-compliance' ); ?> <span class="required">*</span></label></th>
					<td><input type="datetime-local" id="discovered_date" name="discovered_date" required></td>
				</tr>
				<tr>
					<th><label for="nature"><?php esc_html_e( 'Nature of Breach', 'bkx-gdpr-compliance' ); ?> <span class="required">*</span></label></th>
					<td><textarea id="nature" name="nature" rows="3" class="large-text" required></textarea></td>
				</tr>
				<tr>
					<th><label for="data_affected"><?php esc_html_e( 'Data Categories Affected', 'bkx-gdpr-compliance' ); ?> <span class="required">*</span></label></th>
					<td><textarea id="data_affected" name="data_affected" rows="3" class="large-text" required></textarea></td>
				</tr>
				<tr>
					<th><label for="subjects_affected"><?php esc_html_e( 'Number of Subjects Affected', 'bkx-gdpr-compliance' ); ?></label></th>
					<td><input type="number" id="subjects_affected" name="subjects_affected" min="0" class="small-text"></td>
				</tr>
				<tr>
					<th><label for="consequences"><?php esc_html_e( 'Likely Consequences', 'bkx-gdpr-compliance' ); ?></label></th>
					<td><textarea id="consequences" name="consequences" rows="3" class="large-text"></textarea></td>
				</tr>
				<tr>
					<th><label for="measures_taken"><?php esc_html_e( 'Measures Taken', 'bkx-gdpr-compliance' ); ?></label></th>
					<td><textarea id="measures_taken" name="measures_taken" rows="3" class="large-text"></textarea></td>
				</tr>
			</table>

			<div class="bkx-gdpr-modal-footer">
				<button type="button" class="button bkx-gdpr-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-gdpr-compliance' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Report Breach', 'bkx-gdpr-compliance' ); ?></button>
			</div>
		</form>
	</div>
</div>

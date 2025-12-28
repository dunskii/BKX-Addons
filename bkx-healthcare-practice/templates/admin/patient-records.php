<?php
/**
 * Patient Records Admin Page Template.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$patient_id = isset( $_GET['patient_id'] ) ? absint( $_GET['patient_id'] ) : 0;

if ( $patient_id ) {
	// Show individual patient record.
	$patient_data = \BookingX\HealthcarePractice\Services\PatientPortalService::get_instance()->get_patient_history( $patient_id );
	?>
	<div class="wrap bkx-patient-records">
		<h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-patient-records' ) ); ?>" class="page-title-action">
				&larr; <?php esc_html_e( 'Back to Patient List', 'bkx-healthcare-practice' ); ?>
			</a>
			<?php
			printf(
				/* translators: %s: Patient name */
				esc_html__( 'Patient Record: %s', 'bkx-healthcare-practice' ),
				esc_html( $patient_data['profile']['name'] ?? 'Unknown' )
			);
			?>
		</h1>

		<div class="bkx-patient-detail">
			<div class="bkx-patient-sidebar">
				<h3><?php esc_html_e( 'Patient Information', 'bkx-healthcare-practice' ); ?></h3>

				<table class="widefat">
					<tr>
						<th><?php esc_html_e( 'Name', 'bkx-healthcare-practice' ); ?></th>
						<td><?php echo esc_html( $patient_data['profile']['name'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Email', 'bkx-healthcare-practice' ); ?></th>
						<td><?php echo esc_html( $patient_data['profile']['email'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Phone', 'bkx-healthcare-practice' ); ?></th>
						<td><?php echo esc_html( $patient_data['profile']['phone'] ?? '' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Date of Birth', 'bkx-healthcare-practice' ); ?></th>
						<td><?php echo esc_html( $patient_data['profile']['date_of_birth'] ?? '' ); ?></td>
					</tr>
				</table>

				<h4><?php esc_html_e( 'Actions', 'bkx-healthcare-practice' ); ?></h4>
				<p>
					<button class="button bkx-export-patient" data-patient-id="<?php echo esc_attr( $patient_id ); ?>">
						<?php esc_html_e( 'Export Data (GDPR)', 'bkx-healthcare-practice' ); ?>
					</button>
				</p>
			</div>

			<div class="bkx-patient-main">
				<h3><?php esc_html_e( 'Appointment History', 'bkx-healthcare-practice' ); ?></h3>

				<?php if ( empty( $patient_data['appointments'] ) ) : ?>
					<p><?php esc_html_e( 'No appointments found.', 'bkx-healthcare-practice' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'bkx-healthcare-practice' ); ?></th>
								<th><?php esc_html_e( 'Service', 'bkx-healthcare-practice' ); ?></th>
								<th><?php esc_html_e( 'Provider', 'bkx-healthcare-practice' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-healthcare-practice' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bkx-healthcare-practice' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $patient_data['appointments'] as $apt ) : ?>
								<tr>
									<td><?php echo esc_html( $apt['date'] . ' ' . $apt['time'] ); ?></td>
									<td><?php echo esc_html( $apt['service'] ); ?></td>
									<td><?php echo esc_html( $apt['provider'] ); ?></td>
									<td><?php echo esc_html( $apt['status'] ); ?></td>
									<td>
										<a href="<?php echo esc_url( get_edit_post_link( $apt['id'] ) ); ?>">
											<?php esc_html_e( 'View', 'bkx-healthcare-practice' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<h3><?php esc_html_e( 'Intake Forms', 'bkx-healthcare-practice' ); ?></h3>

				<?php if ( empty( $patient_data['intake_forms'] ) ) : ?>
					<p><?php esc_html_e( 'No intake forms submitted.', 'bkx-healthcare-practice' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Form', 'bkx-healthcare-practice' ); ?></th>
								<th><?php esc_html_e( 'Submitted', 'bkx-healthcare-practice' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-healthcare-practice' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bkx-healthcare-practice' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $patient_data['intake_forms'] as $intake ) : ?>
								<tr>
									<td><?php echo esc_html( $intake['form_name'] ?? 'Intake Form' ); ?></td>
									<td><?php echo esc_html( $intake['created_at'] ); ?></td>
									<td><?php echo esc_html( $intake['status'] ); ?></td>
									<td>
										<a href="#" class="bkx-view-intake" data-intake-id="<?php echo esc_attr( $intake['id'] ); ?>">
											<?php esc_html_e( 'View', 'bkx-healthcare-practice' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<h3><?php esc_html_e( 'Consent History', 'bkx-healthcare-practice' ); ?></h3>

				<?php if ( empty( $patient_data['consents'] ) ) : ?>
					<p><?php esc_html_e( 'No consent records.', 'bkx-healthcare-practice' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Form', 'bkx-healthcare-practice' ); ?></th>
								<th><?php esc_html_e( 'Version', 'bkx-healthcare-practice' ); ?></th>
								<th><?php esc_html_e( 'Consented', 'bkx-healthcare-practice' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-healthcare-practice' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $patient_data['consents'] as $consent ) : ?>
								<tr>
									<td><?php echo esc_html( $consent['form_name'] ); ?></td>
									<td><?php echo esc_html( $consent['form_version'] ); ?></td>
									<td><?php echo esc_html( $consent['consented_at'] ); ?></td>
									<td>
										<span class="bkx-status-badge bkx-status-<?php echo esc_attr( $consent['status'] ); ?>">
											<?php echo esc_html( ucfirst( $consent['status'] ) ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
} else {
	// Show patient list.
	?>
	<div class="wrap bkx-patient-records">
		<h1><?php esc_html_e( 'Patient Records', 'bkx-healthcare-practice' ); ?></h1>

		<div class="bkx-patient-search">
			<input type="text" class="bkx-patient-search-input regular-text"
				   placeholder="<?php esc_attr_e( 'Search patients by name or email...', 'bkx-healthcare-practice' ); ?>">
		</div>

		<div class="bkx-patient-list">
			<p><?php esc_html_e( 'Enter a search term to find patients.', 'bkx-healthcare-practice' ); ?></p>
		</div>
	</div>
	<?php
}

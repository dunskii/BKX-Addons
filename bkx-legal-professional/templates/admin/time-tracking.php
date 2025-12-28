<?php
/**
 * Admin Time Tracking Template.
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BookingX\LegalProfessional\Services\BillingService;

$billing_service = BillingService::get_instance();
$activity_codes  = $billing_service->get_activity_codes();

// Get filter values.
$matter_filter   = isset( $_GET['matter_id'] ) ? absint( $_GET['matter_id'] ) : 0;
$user_filter     = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
$start_date      = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
$end_date        = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
$billable_filter = isset( $_GET['billable'] ) ? sanitize_text_field( wp_unslash( $_GET['billable'] ) ) : '';

// Get time entries.
$entries = array();
if ( $matter_filter ) {
	$entries = $billing_service->get_matter_time_entries(
		$matter_filter,
		$start_date,
		$end_date,
		'billable' === $billable_filter,
		'unbilled' === $billable_filter
	);
} elseif ( $user_filter ) {
	$entries = $billing_service->get_user_time_entries( $user_filter, $start_date, $end_date );
}

// Get matters and users for filters.
$matters = get_posts( array(
	'post_type'      => 'bkx_matter',
	'posts_per_page' => -1,
	'orderby'        => 'title',
	'order'          => 'ASC',
) );

$users = get_users( array(
	'role__in' => array( 'administrator', 'editor', 'author' ),
	'orderby'  => 'display_name',
) );
?>
<div class="wrap bkx-time-tracking">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Time Tracking', 'bkx-legal-professional' ); ?></h1>

	<!-- Filters -->
	<div class="bkx-filters">
		<form method="get">
			<input type="hidden" name="post_type" value="bkx_booking">
			<input type="hidden" name="page" value="bkx-time-tracking">

			<select name="matter_id">
				<option value=""><?php esc_html_e( 'All Matters', 'bkx-legal-professional' ); ?></option>
				<?php foreach ( $matters as $matter ) : ?>
					<?php $number = get_post_meta( $matter->ID, '_bkx_matter_number', true ); ?>
					<option value="<?php echo esc_attr( $matter->ID ); ?>" <?php selected( $matter_filter, $matter->ID ); ?>>
						<?php echo esc_html( $number . ' - ' . $matter->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="user_id">
				<option value=""><?php esc_html_e( 'All Users', 'bkx-legal-professional' ); ?></option>
				<?php foreach ( $users as $user ) : ?>
					<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $user_filter, $user->ID ); ?>>
						<?php echo esc_html( $user->display_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" placeholder="<?php esc_attr_e( 'Start Date', 'bkx-legal-professional' ); ?>">
			<input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" placeholder="<?php esc_attr_e( 'End Date', 'bkx-legal-professional' ); ?>">

			<select name="billable">
				<option value=""><?php esc_html_e( 'All Entries', 'bkx-legal-professional' ); ?></option>
				<option value="billable" <?php selected( $billable_filter, 'billable' ); ?>><?php esc_html_e( 'Billable Only', 'bkx-legal-professional' ); ?></option>
				<option value="unbilled" <?php selected( $billable_filter, 'unbilled' ); ?>><?php esc_html_e( 'Unbilled Only', 'bkx-legal-professional' ); ?></option>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-legal-professional' ); ?></button>
		</form>
	</div>

	<?php if ( ! empty( $entries ) ) : ?>
		<!-- Summary -->
		<?php
		$total_minutes   = 0;
		$billable_amount = 0;
		$billed_amount   = 0;

		foreach ( $entries as $entry ) {
			$total_minutes += (int) $entry['minutes'];
			if ( $entry['billable'] ) {
				if ( $entry['billed'] ) {
					$billed_amount += (float) $entry['amount'];
				} else {
					$billable_amount += (float) $entry['amount'];
				}
			}
		}
		?>
		<div class="bkx-billing-summary">
			<div class="bkx-billing-card">
				<h4><?php esc_html_e( 'Total Time', 'bkx-legal-professional' ); ?></h4>
				<div class="amount">
					<?php
					$hours = floor( $total_minutes / 60 );
					$mins  = $total_minutes % 60;
					printf( '%dh %dm', $hours, $mins );
					?>
				</div>
			</div>
			<div class="bkx-billing-card">
				<h4><?php esc_html_e( 'Unbilled', 'bkx-legal-professional' ); ?></h4>
				<div class="amount"><?php echo esc_html( '$' . number_format( $billable_amount, 2 ) ); ?></div>
			</div>
			<div class="bkx-billing-card">
				<h4><?php esc_html_e( 'Billed', 'bkx-legal-professional' ); ?></h4>
				<div class="amount positive"><?php echo esc_html( '$' . number_format( $billed_amount, 2 ) ); ?></div>
			</div>
		</div>

		<!-- Time Entries Table -->
		<table class="bkx-time-entries-table wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'User', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Description', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Activity', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Time', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Rate', 'bkx-legal-professional' ); ?></th>
					<th class="amount"><?php esc_html_e( 'Amount', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bkx-legal-professional' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( gmdate( 'M j, Y', strtotime( $entry['entry_date'] ) ) ); ?></td>
						<td><?php echo esc_html( $entry['user_name'] ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $entry['description'], 15 ) ); ?></td>
						<td><?php echo esc_html( $entry['activity_code'] ); ?></td>
						<td><?php echo esc_html( $entry['hours_display'] ); ?></td>
						<td><?php echo esc_html( '$' . number_format( $entry['rate'], 2 ) ); ?></td>
						<td class="amount"><?php echo esc_html( '$' . number_format( $entry['amount'], 2 ) ); ?></td>
						<td>
							<?php if ( $entry['billed'] ) : ?>
								<span class="bkx-status-badge bkx-status-active"><?php esc_html_e( 'Billed', 'bkx-legal-professional' ); ?></span>
							<?php elseif ( $entry['billable'] ) : ?>
								<span class="bkx-status-badge bkx-status-pending"><?php esc_html_e( 'Billable', 'bkx-legal-professional' ); ?></span>
							<?php else : ?>
								<span class="bkx-status-badge bkx-status-closed"><?php esc_html_e( 'Non-billable', 'bkx-legal-professional' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<div class="bkx-no-results">
			<p><?php esc_html_e( 'Select a matter or user to view time entries.', 'bkx-legal-professional' ); ?></p>
		</div>
	<?php endif; ?>
</div>

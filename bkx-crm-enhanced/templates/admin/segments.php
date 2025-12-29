<?php
/**
 * Segments tab template.
 *
 * @package BookingX\CRM
 */

defined( 'ABSPATH' ) || exit;

$segment_service = new \BookingX\CRM\Services\SegmentService();
$segments = $segment_service->get_all();
?>

<div class="bkx-crm-segments-page">
	<!-- Add Segment -->
	<div class="bkx-card">
		<h2><?php esc_html_e( 'Create Segment', 'bkx-crm' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Segments allow you to group customers based on specific criteria for targeted marketing and follow-ups.', 'bkx-crm' ); ?>
		</p>

		<form id="bkx-segment-form">
			<input type="hidden" name="segment_id" value="">

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="segment-name"><?php esc_html_e( 'Name', 'bkx-crm' ); ?></label>
					</th>
					<td>
						<input type="text" id="segment-name" name="name" class="regular-text" required
							   placeholder="<?php esc_attr_e( 'e.g., VIP Customers', 'bkx-crm' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="segment-description"><?php esc_html_e( 'Description', 'bkx-crm' ); ?></label>
					</th>
					<td>
						<textarea id="segment-description" name="description" rows="2" class="large-text"
								  placeholder="<?php esc_attr_e( 'Optional description for this segment', 'bkx-crm' ); ?>"></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Conditions', 'bkx-crm' ); ?></th>
					<td>
						<div id="bkx-conditions-builder">
							<div class="bkx-conditions-list"></div>
							<button type="button" class="button" id="bkx-add-condition">
								<?php esc_html_e( '+ Add Condition', 'bkx-crm' ); ?>
							</button>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Dynamic', 'bkx-crm' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="is_dynamic" value="1" checked>
							<?php esc_html_e( 'Automatically update when customers match conditions', 'bkx-crm' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<div class="bkx-segment-preview">
				<h4><?php esc_html_e( 'Preview', 'bkx-crm' ); ?></h4>
				<div id="bkx-segment-preview-results">
					<p class="bkx-muted"><?php esc_html_e( 'Add conditions to see matching customers', 'bkx-crm' ); ?></p>
				</div>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Segment', 'bkx-crm' ); ?>
				</button>
				<button type="button" class="button" id="bkx-preview-segment">
					<?php esc_html_e( 'Preview', 'bkx-crm' ); ?>
				</button>
			</p>
		</form>
	</div>

	<!-- Segments List -->
	<div class="bkx-card">
		<h2><?php esc_html_e( 'Saved Segments', 'bkx-crm' ); ?></h2>

		<?php if ( empty( $segments ) ) : ?>
			<p><?php esc_html_e( 'No segments created yet.', 'bkx-crm' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'bkx-crm' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Customers', 'bkx-crm' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Type', 'bkx-crm' ); ?></th>
						<th style="width: 150px;"><?php esc_html_e( 'Actions', 'bkx-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $segments as $segment ) : ?>
						<tr data-segment-id="<?php echo esc_attr( $segment->id ); ?>">
							<td>
								<strong><?php echo esc_html( $segment->name ); ?></strong>
								<?php if ( $segment->description ) : ?>
									<br><small><?php echo esc_html( $segment->description ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $segment->customer_count ); ?></td>
							<td>
								<?php if ( $segment->is_dynamic ) : ?>
									<span class="bkx-badge bkx-badge-dynamic"><?php esc_html_e( 'Dynamic', 'bkx-crm' ); ?></span>
								<?php else : ?>
									<span class="bkx-badge"><?php esc_html_e( 'Static', 'bkx-crm' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-crm&tab=customers&segment=' . $segment->id ) ); ?>"
								   class="button button-small">
									<?php esc_html_e( 'View', 'bkx-crm' ); ?>
								</a>
								<button type="button" class="button button-small button-link-delete bkx-delete-segment"
										data-segment-id="<?php echo esc_attr( $segment->id ); ?>">
									<?php esc_html_e( 'Delete', 'bkx-crm' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<!-- Condition Template -->
<script type="text/template" id="bkx-condition-template">
	<div class="bkx-condition-row">
		<select name="conditions[{{index}}][field]" class="bkx-condition-field">
			<option value=""><?php esc_html_e( 'Select Field', 'bkx-crm' ); ?></option>
			<option value="email"><?php esc_html_e( 'Email', 'bkx-crm' ); ?></option>
			<option value="first_name"><?php esc_html_e( 'First Name', 'bkx-crm' ); ?></option>
			<option value="last_name"><?php esc_html_e( 'Last Name', 'bkx-crm' ); ?></option>
			<option value="city"><?php esc_html_e( 'City', 'bkx-crm' ); ?></option>
			<option value="country"><?php esc_html_e( 'Country', 'bkx-crm' ); ?></option>
			<option value="total_bookings"><?php esc_html_e( 'Total Bookings', 'bkx-crm' ); ?></option>
			<option value="lifetime_value"><?php esc_html_e( 'Lifetime Value', 'bkx-crm' ); ?></option>
			<option value="status"><?php esc_html_e( 'Status', 'bkx-crm' ); ?></option>
			<option value="source"><?php esc_html_e( 'Source', 'bkx-crm' ); ?></option>
			<option value="last_booking_date"><?php esc_html_e( 'Last Booking Date', 'bkx-crm' ); ?></option>
		</select>

		<select name="conditions[{{index}}][operator]" class="bkx-condition-operator">
			<option value="equals"><?php esc_html_e( 'Equals', 'bkx-crm' ); ?></option>
			<option value="not_equals"><?php esc_html_e( 'Does not equal', 'bkx-crm' ); ?></option>
			<option value="contains"><?php esc_html_e( 'Contains', 'bkx-crm' ); ?></option>
			<option value="not_contains"><?php esc_html_e( 'Does not contain', 'bkx-crm' ); ?></option>
			<option value="greater_than"><?php esc_html_e( 'Greater than', 'bkx-crm' ); ?></option>
			<option value="less_than"><?php esc_html_e( 'Less than', 'bkx-crm' ); ?></option>
			<option value="in_last_days"><?php esc_html_e( 'In last X days', 'bkx-crm' ); ?></option>
			<option value="not_in_last_days"><?php esc_html_e( 'Not in last X days', 'bkx-crm' ); ?></option>
		</select>

		<input type="text" name="conditions[{{index}}][value]" class="bkx-condition-value regular-text"
			   placeholder="<?php esc_attr_e( 'Value', 'bkx-crm' ); ?>">

		<button type="button" class="button bkx-remove-condition">&times;</button>
	</div>
</script>

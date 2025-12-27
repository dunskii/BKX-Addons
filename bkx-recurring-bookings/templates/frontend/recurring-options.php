<?php
/**
 * Recurring Options Template
 *
 * @package BookingX\RecurringBookings
 * @since   1.0.0
 *
 * @var array $patterns Available patterns.
 * @var int   $max_occurrences Maximum occurrences.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="bkx-recurring-options" style="display: none;">
	<h4><?php esc_html_e( 'Repeat Booking', 'bkx-recurring-bookings' ); ?></h4>

	<div class="bkx-recurring-pattern">
		<label for="bkx-recurring-pattern"><?php esc_html_e( 'Repeat', 'bkx-recurring-bookings' ); ?></label>
		<select id="bkx-recurring-pattern" name="recurring_pattern">
			<?php foreach ( $patterns as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="bkx-recurring-config" style="display: none;">
		<!-- Daily options -->
		<div class="bkx-pattern-options bkx-pattern-daily" style="display: none;">
			<label>
				<input type="checkbox" name="recurring_options[skip_weekends]" value="1">
				<?php esc_html_e( 'Skip weekends', 'bkx-recurring-bookings' ); ?>
			</label>
		</div>

		<!-- Weekly options -->
		<div class="bkx-pattern-options bkx-pattern-weekly" style="display: none;">
			<label><?php esc_html_e( 'Repeat on', 'bkx-recurring-bookings' ); ?></label>
			<div class="bkx-day-selector">
				<?php
				$days = array(
					0 => __( 'S', 'bkx-recurring-bookings' ),
					1 => __( 'M', 'bkx-recurring-bookings' ),
					2 => __( 'T', 'bkx-recurring-bookings' ),
					3 => __( 'W', 'bkx-recurring-bookings' ),
					4 => __( 'T', 'bkx-recurring-bookings' ),
					5 => __( 'F', 'bkx-recurring-bookings' ),
					6 => __( 'S', 'bkx-recurring-bookings' ),
				);
				foreach ( $days as $num => $label ) :
					?>
					<label class="bkx-day-checkbox">
						<input type="checkbox" name="recurring_options[days][]" value="<?php echo esc_attr( $num ); ?>">
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Monthly options -->
		<div class="bkx-pattern-options bkx-pattern-monthly" style="display: none;">
			<div class="bkx-monthly-type">
				<label>
					<input type="radio" name="recurring_options[type]" value="day_of_month" checked>
					<?php esc_html_e( 'On day', 'bkx-recurring-bookings' ); ?>
					<select name="recurring_options[day_of_month]">
						<?php for ( $i = 1; $i <= 31; $i++ ) : ?>
							<option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></option>
						<?php endfor; ?>
					</select>
				</label>
			</div>
			<div class="bkx-monthly-type">
				<label>
					<input type="radio" name="recurring_options[type]" value="day_of_week">
					<?php esc_html_e( 'On the', 'bkx-recurring-bookings' ); ?>
					<select name="recurring_options[week_number]">
						<option value="1"><?php esc_html_e( 'First', 'bkx-recurring-bookings' ); ?></option>
						<option value="2"><?php esc_html_e( 'Second', 'bkx-recurring-bookings' ); ?></option>
						<option value="3"><?php esc_html_e( 'Third', 'bkx-recurring-bookings' ); ?></option>
						<option value="4"><?php esc_html_e( 'Fourth', 'bkx-recurring-bookings' ); ?></option>
						<option value="-1"><?php esc_html_e( 'Last', 'bkx-recurring-bookings' ); ?></option>
					</select>
					<select name="recurring_options[day_of_week]">
						<option value="0"><?php esc_html_e( 'Sunday', 'bkx-recurring-bookings' ); ?></option>
						<option value="1"><?php esc_html_e( 'Monday', 'bkx-recurring-bookings' ); ?></option>
						<option value="2"><?php esc_html_e( 'Tuesday', 'bkx-recurring-bookings' ); ?></option>
						<option value="3"><?php esc_html_e( 'Wednesday', 'bkx-recurring-bookings' ); ?></option>
						<option value="4"><?php esc_html_e( 'Thursday', 'bkx-recurring-bookings' ); ?></option>
						<option value="5"><?php esc_html_e( 'Friday', 'bkx-recurring-bookings' ); ?></option>
						<option value="6"><?php esc_html_e( 'Saturday', 'bkx-recurring-bookings' ); ?></option>
					</select>
				</label>
			</div>
		</div>

		<!-- Custom options -->
		<div class="bkx-pattern-options bkx-pattern-custom" style="display: none;">
			<div class="bkx-custom-interval">
				<label><?php esc_html_e( 'Every', 'bkx-recurring-bookings' ); ?></label>
				<input type="number" name="recurring_options[interval]" value="1" min="1" max="365" class="small-text">
				<select name="recurring_options[unit]">
					<option value="days"><?php esc_html_e( 'days', 'bkx-recurring-bookings' ); ?></option>
					<option value="weeks"><?php esc_html_e( 'weeks', 'bkx-recurring-bookings' ); ?></option>
					<option value="months"><?php esc_html_e( 'months', 'bkx-recurring-bookings' ); ?></option>
				</select>
			</div>
		</div>

		<!-- End options -->
		<div class="bkx-recurring-end">
			<label><?php esc_html_e( 'Ends', 'bkx-recurring-bookings' ); ?></label>
			<div class="bkx-end-options">
				<label>
					<input type="radio" name="recurring_end_type" value="occurrences" checked>
					<?php esc_html_e( 'After', 'bkx-recurring-bookings' ); ?>
					<input type="number" name="recurring_options[occurrences]"
						   value="10" min="2" max="<?php echo esc_attr( $max_occurrences ); ?>" class="small-text">
					<?php esc_html_e( 'occurrences', 'bkx-recurring-bookings' ); ?>
				</label>
				<label>
					<input type="radio" name="recurring_end_type" value="date">
					<?php esc_html_e( 'On date', 'bkx-recurring-bookings' ); ?>
					<input type="date" name="recurring_options[end_date]" class="bkx-end-date">
				</label>
			</div>
		</div>
	</div>

	<!-- Preview -->
	<div class="bkx-recurring-preview" style="display: none;">
		<h5><?php esc_html_e( 'Preview', 'bkx-recurring-bookings' ); ?></h5>
		<p class="bkx-preview-description"></p>
		<ul class="bkx-preview-dates"></ul>
		<p class="bkx-preview-more" style="display: none;"></p>
	</div>
</div>

<style>
.bkx-recurring-options {
	margin: 20px 0;
	padding: 15px;
	background: #f9f9f9;
	border: 1px solid #ddd;
	border-radius: 4px;
}

.bkx-recurring-options h4 {
	margin: 0 0 15px;
}

.bkx-recurring-pattern {
	margin-bottom: 15px;
}

.bkx-recurring-pattern select {
	margin-left: 10px;
}

.bkx-recurring-config {
	padding: 15px;
	background: #fff;
	border: 1px solid #eee;
	border-radius: 4px;
	margin-bottom: 15px;
}

.bkx-pattern-options {
	margin-bottom: 15px;
}

.bkx-day-selector {
	display: flex;
	gap: 5px;
	margin-top: 10px;
}

.bkx-day-checkbox {
	cursor: pointer;
}

.bkx-day-checkbox input {
	display: none;
}

.bkx-day-checkbox span {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	border: 1px solid #ddd;
	border-radius: 50%;
	font-size: 12px;
	font-weight: 600;
	transition: all 0.2s;
}

.bkx-day-checkbox input:checked + span {
	background: #0073aa;
	border-color: #0073aa;
	color: #fff;
}

.bkx-monthly-type {
	margin-bottom: 10px;
}

.bkx-monthly-type select {
	margin: 0 5px;
}

.bkx-custom-interval {
	display: flex;
	align-items: center;
	gap: 10px;
}

.bkx-recurring-end {
	margin-top: 15px;
	padding-top: 15px;
	border-top: 1px solid #eee;
}

.bkx-end-options label {
	display: block;
	margin: 10px 0;
}

.bkx-recurring-preview {
	margin-top: 15px;
	padding: 15px;
	background: #fff;
	border: 1px solid #0073aa;
	border-radius: 4px;
}

.bkx-recurring-preview h5 {
	margin: 0 0 10px;
	color: #0073aa;
}

.bkx-preview-description {
	font-style: italic;
	color: #666;
}

.bkx-preview-dates {
	margin: 10px 0;
	padding-left: 20px;
}

.bkx-preview-dates li {
	margin: 5px 0;
}

.bkx-preview-more {
	font-size: 12px;
	color: #666;
}
</style>

<?php
/**
 * Apple Siri testing template.
 *
 * @package BookingX\AppleSiri
 */

defined( 'ABSPATH' ) || exit;

$addon = \BookingX\AppleSiri\AppleSiriAddon::get_instance();
?>
<div class="bkx-testing-section">
	<h2><?php esc_html_e( 'API Testing', 'bkx-apple-siri' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Test the Siri integration endpoints to ensure everything is working correctly.', 'bkx-apple-siri' ); ?>
	</p>

	<div class="bkx-test-cards">
		<!-- Connection Test -->
		<div class="bkx-test-card">
			<h3><?php esc_html_e( 'Connection Test', 'bkx-apple-siri' ); ?></h3>
			<p><?php esc_html_e( 'Verify Apple credentials and API connectivity.', 'bkx-apple-siri' ); ?></p>
			<button type="button" class="button" id="bkx-test-connection-btn">
				<?php esc_html_e( 'Test Connection', 'bkx-apple-siri' ); ?>
			</button>
			<div class="bkx-test-result" id="connection-test-result"></div>
		</div>

		<!-- Shortcuts Endpoint -->
		<div class="bkx-test-card">
			<h3><?php esc_html_e( 'Shortcuts Endpoint', 'bkx-apple-siri' ); ?></h3>
			<p><?php esc_html_e( 'Test the shortcuts API endpoint.', 'bkx-apple-siri' ); ?></p>
			<button type="button" class="button" id="bkx-test-shortcuts-btn">
				<?php esc_html_e( 'Test Shortcuts', 'bkx-apple-siri' ); ?>
			</button>
			<div class="bkx-test-result" id="shortcuts-test-result"></div>
		</div>

		<!-- Availability Endpoint -->
		<div class="bkx-test-card">
			<h3><?php esc_html_e( 'Availability Endpoint', 'bkx-apple-siri' ); ?></h3>
			<p><?php esc_html_e( 'Test availability checking for tomorrow.', 'bkx-apple-siri' ); ?></p>
			<button type="button" class="button" id="bkx-test-availability-btn">
				<?php esc_html_e( 'Test Availability', 'bkx-apple-siri' ); ?>
			</button>
			<div class="bkx-test-result" id="availability-test-result"></div>
		</div>
	</div>
</div>

<div class="bkx-testing-section">
	<h2><?php esc_html_e( 'Intent Simulator', 'bkx-apple-siri' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Simulate Siri intent requests to test the booking flow.', 'bkx-apple-siri' ); ?>
	</p>

	<form id="bkx-intent-simulator" class="bkx-simulator-form">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="sim-intent-type"><?php esc_html_e( 'Intent Type', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<select name="intent_type" id="sim-intent-type">
						<option value="BookAppointmentIntent"><?php esc_html_e( 'Book Appointment', 'bkx-apple-siri' ); ?></option>
						<option value="CheckAvailabilityIntent"><?php esc_html_e( 'Check Availability', 'bkx-apple-siri' ); ?></option>
						<option value="RescheduleAppointmentIntent"><?php esc_html_e( 'Reschedule Appointment', 'bkx-apple-siri' ); ?></option>
						<option value="CancelAppointmentIntent"><?php esc_html_e( 'Cancel Appointment', 'bkx-apple-siri' ); ?></option>
						<option value="GetUpcomingAppointmentsIntent"><?php esc_html_e( 'Upcoming Appointments', 'bkx-apple-siri' ); ?></option>
					</select>
				</td>
			</tr>

			<tr class="bkx-sim-field bkx-sim-field-date">
				<th scope="row">
					<label for="sim-date"><?php esc_html_e( 'Date', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<input type="date" name="date" id="sim-date" value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>">
				</td>
			</tr>

			<tr class="bkx-sim-field bkx-sim-field-time">
				<th scope="row">
					<label for="sim-time"><?php esc_html_e( 'Time', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<input type="time" name="time" id="sim-time" value="10:00">
				</td>
			</tr>

			<tr class="bkx-sim-field bkx-sim-field-service">
				<th scope="row">
					<label for="sim-service"><?php esc_html_e( 'Service', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<input type="text" name="service" id="sim-service" class="regular-text" placeholder="<?php esc_attr_e( 'Service name or ID', 'bkx-apple-siri' ); ?>">
				</td>
			</tr>

			<tr class="bkx-sim-field bkx-sim-field-booking-id" style="display: none;">
				<th scope="row">
					<label for="sim-booking-id"><?php esc_html_e( 'Booking ID', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<input type="number" name="booking_id" id="sim-booking-id" class="regular-text">
				</td>
			</tr>

			<tr class="bkx-sim-field bkx-sim-field-voice-input">
				<th scope="row">
					<label for="sim-voice-input"><?php esc_html_e( 'Voice Input (optional)', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<input type="text" name="voice_input" id="sim-voice-input" class="large-text"
						   placeholder="<?php esc_attr_e( 'e.g., "Book me an appointment for tomorrow at 2pm"', 'bkx-apple-siri' ); ?>">
					<p class="description">
						<?php esc_html_e( 'Simulates natural language voice input.', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Simulate Intent', 'bkx-apple-siri' ); ?>
			</button>
		</p>
	</form>

	<div id="bkx-simulator-result" class="bkx-simulator-result" style="display: none;">
		<h3><?php esc_html_e( 'Response', 'bkx-apple-siri' ); ?></h3>
		<div class="bkx-result-header">
			<span class="bkx-result-status"></span>
			<button type="button" class="button button-small" id="bkx-copy-response">
				<?php esc_html_e( 'Copy', 'bkx-apple-siri' ); ?>
			</button>
		</div>
		<pre class="bkx-result-body"></pre>

		<div class="bkx-spoken-response">
			<h4><?php esc_html_e( 'Siri Would Say:', 'bkx-apple-siri' ); ?></h4>
			<blockquote class="bkx-spoken-text"></blockquote>
		</div>
	</div>
</div>

<div class="bkx-testing-section">
	<h2><?php esc_html_e( 'Voice Input Parser', 'bkx-apple-siri' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Test how natural language input is parsed into booking data.', 'bkx-apple-siri' ); ?>
	</p>

	<form id="bkx-voice-parser" class="bkx-parser-form">
		<div class="bkx-parser-input">
			<input type="text" name="voice_input" id="parser-voice-input" class="large-text"
				   placeholder="<?php esc_attr_e( 'e.g., "I need a haircut next Tuesday at 3pm"', 'bkx-apple-siri' ); ?>">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Parse', 'bkx-apple-siri' ); ?>
			</button>
		</div>
	</form>

	<div id="bkx-parser-result" class="bkx-parser-result" style="display: none;">
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Field', 'bkx-apple-siri' ); ?></th>
					<th><?php esc_html_e( 'Parsed Value', 'bkx-apple-siri' ); ?></th>
					<th><?php esc_html_e( 'Confidence', 'bkx-apple-siri' ); ?></th>
				</tr>
			</thead>
			<tbody id="bkx-parser-table-body">
			</tbody>
		</table>
	</div>
</div>

<div class="bkx-testing-section">
	<h2><?php esc_html_e( 'Example Voice Commands', 'bkx-apple-siri' ); ?></h2>

	<div class="bkx-examples-grid">
		<div class="bkx-example-card">
			<div class="bkx-example-icon">
				<span class="dashicons dashicons-calendar-alt"></span>
			</div>
			<div class="bkx-example-content">
				<h4><?php esc_html_e( 'Book an Appointment', 'bkx-apple-siri' ); ?></h4>
				<ul>
					<li>"Book an appointment for tomorrow at 2pm"</li>
					<li>"Schedule a haircut next Monday"</li>
					<li>"I need to make an appointment for next week"</li>
				</ul>
			</div>
		</div>

		<div class="bkx-example-card">
			<div class="bkx-example-icon">
				<span class="dashicons dashicons-clock"></span>
			</div>
			<div class="bkx-example-content">
				<h4><?php esc_html_e( 'Check Availability', 'bkx-apple-siri' ); ?></h4>
				<ul>
					<li>"What times are available tomorrow?"</li>
					<li>"Check availability for next Friday"</li>
					<li>"When can I book an appointment?"</li>
				</ul>
			</div>
		</div>

		<div class="bkx-example-card">
			<div class="bkx-example-icon">
				<span class="dashicons dashicons-update"></span>
			</div>
			<div class="bkx-example-content">
				<h4><?php esc_html_e( 'Reschedule', 'bkx-apple-siri' ); ?></h4>
				<ul>
					<li>"Move my appointment to Thursday"</li>
					<li>"Reschedule to 3pm instead"</li>
					<li>"Change my booking to next week"</li>
				</ul>
			</div>
		</div>

		<div class="bkx-example-card">
			<div class="bkx-example-icon">
				<span class="dashicons dashicons-list-view"></span>
			</div>
			<div class="bkx-example-content">
				<h4><?php esc_html_e( 'Manage Appointments', 'bkx-apple-siri' ); ?></h4>
				<ul>
					<li>"Show my upcoming appointments"</li>
					<li>"What appointments do I have?"</li>
					<li>"Cancel my appointment"</li>
				</ul>
			</div>
		</div>
	</div>
</div>

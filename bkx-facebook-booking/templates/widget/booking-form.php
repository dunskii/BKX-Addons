<?php
/**
 * Embedded booking form template.
 *
 * @package BookingX\FacebookBooking
 */

defined( 'ABSPATH' ) || exit;

$page_id = isset( $_GET['page_id'] ) ? sanitize_text_field( wp_unslash( $_GET['page_id'] ) ) : '';
$settings = get_option( 'bkx_fb_booking_settings', array() );
$business_name = $settings['business_name'] ?? get_bloginfo( 'name' );

// Get services for this page.
$page_manager = new \BookingX\FacebookBooking\Services\PageManager(
	new \BookingX\FacebookBooking\Services\FacebookApi()
);
$services = $page_manager->get_page_services( $page_id );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( sprintf( __( 'Book with %s', 'bkx-facebook-booking' ), $business_name ) ); ?></title>
	<style>
		* {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			background: #f0f2f5;
			color: #1c1e21;
			line-height: 1.5;
		}

		.bkx-widget {
			max-width: 480px;
			margin: 0 auto;
			padding: 20px;
		}

		.bkx-widget-header {
			text-align: center;
			padding: 30px 20px;
			background: #1877f2;
			color: white;
			border-radius: 12px 12px 0 0;
		}

		.bkx-widget-header h1 {
			font-size: 24px;
			font-weight: 600;
			margin-bottom: 8px;
		}

		.bkx-widget-header p {
			opacity: 0.9;
			font-size: 14px;
		}

		.bkx-widget-body {
			background: white;
			padding: 20px;
			border-radius: 0 0 12px 12px;
			box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
		}

		.bkx-step {
			display: none;
		}

		.bkx-step.active {
			display: block;
		}

		.bkx-step-header {
			font-size: 18px;
			font-weight: 600;
			margin-bottom: 16px;
			color: #1c1e21;
		}

		.bkx-services {
			display: flex;
			flex-direction: column;
			gap: 12px;
		}

		.bkx-service {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 16px;
			border: 2px solid #e4e6eb;
			border-radius: 8px;
			cursor: pointer;
			transition: all 0.2s ease;
		}

		.bkx-service:hover {
			border-color: #1877f2;
			background: #f0f7ff;
		}

		.bkx-service.selected {
			border-color: #1877f2;
			background: #e7f3ff;
		}

		.bkx-service-info h3 {
			font-size: 16px;
			font-weight: 600;
			margin-bottom: 4px;
		}

		.bkx-service-info p {
			font-size: 14px;
			color: #65676b;
		}

		.bkx-service-price {
			font-size: 18px;
			font-weight: 600;
			color: #1877f2;
		}

		.bkx-calendar {
			margin-bottom: 20px;
		}

		.bkx-calendar-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 12px;
		}

		.bkx-calendar-header button {
			background: none;
			border: none;
			font-size: 20px;
			cursor: pointer;
			padding: 8px;
			color: #1877f2;
		}

		.bkx-calendar-header h4 {
			font-size: 16px;
			font-weight: 600;
		}

		.bkx-calendar-grid {
			display: grid;
			grid-template-columns: repeat(7, 1fr);
			gap: 4px;
		}

		.bkx-calendar-day-name {
			text-align: center;
			font-size: 12px;
			font-weight: 600;
			color: #65676b;
			padding: 8px 0;
		}

		.bkx-calendar-day {
			aspect-ratio: 1;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 14px;
			border-radius: 50%;
			cursor: pointer;
			transition: all 0.2s ease;
		}

		.bkx-calendar-day:hover:not(.disabled) {
			background: #e7f3ff;
		}

		.bkx-calendar-day.selected {
			background: #1877f2;
			color: white;
		}

		.bkx-calendar-day.disabled {
			color: #bec3c9;
			cursor: not-allowed;
		}

		.bkx-calendar-day.empty {
			visibility: hidden;
		}

		.bkx-times {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 8px;
		}

		.bkx-time {
			padding: 12px;
			text-align: center;
			border: 2px solid #e4e6eb;
			border-radius: 8px;
			cursor: pointer;
			font-size: 14px;
			transition: all 0.2s ease;
		}

		.bkx-time:hover {
			border-color: #1877f2;
			background: #f0f7ff;
		}

		.bkx-time.selected {
			border-color: #1877f2;
			background: #1877f2;
			color: white;
		}

		.bkx-form-group {
			margin-bottom: 16px;
		}

		.bkx-form-group label {
			display: block;
			font-size: 14px;
			font-weight: 600;
			margin-bottom: 6px;
		}

		.bkx-form-group input {
			width: 100%;
			padding: 12px;
			border: 2px solid #e4e6eb;
			border-radius: 8px;
			font-size: 16px;
			transition: border-color 0.2s ease;
		}

		.bkx-form-group input:focus {
			outline: none;
			border-color: #1877f2;
		}

		.bkx-summary {
			background: #f0f2f5;
			padding: 16px;
			border-radius: 8px;
			margin-bottom: 20px;
		}

		.bkx-summary-row {
			display: flex;
			justify-content: space-between;
			margin-bottom: 8px;
			font-size: 14px;
		}

		.bkx-summary-row:last-child {
			margin-bottom: 0;
			padding-top: 8px;
			border-top: 1px solid #dadde1;
			font-weight: 600;
			font-size: 16px;
		}

		.bkx-btn {
			width: 100%;
			padding: 14px 24px;
			border: none;
			border-radius: 8px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
			transition: all 0.2s ease;
		}

		.bkx-btn-primary {
			background: #1877f2;
			color: white;
		}

		.bkx-btn-primary:hover {
			background: #166fe5;
		}

		.bkx-btn-primary:disabled {
			background: #bec3c9;
			cursor: not-allowed;
		}

		.bkx-btn-secondary {
			background: #e4e6eb;
			color: #1c1e21;
			margin-top: 8px;
		}

		.bkx-btn-secondary:hover {
			background: #dadde1;
		}

		.bkx-success {
			text-align: center;
			padding: 40px 20px;
		}

		.bkx-success-icon {
			width: 80px;
			height: 80px;
			background: #31a24c;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			margin: 0 auto 20px;
		}

		.bkx-success-icon svg {
			width: 40px;
			height: 40px;
			fill: white;
		}

		.bkx-success h2 {
			font-size: 24px;
			margin-bottom: 8px;
		}

		.bkx-success p {
			color: #65676b;
			margin-bottom: 20px;
		}

		.bkx-loading {
			display: flex;
			justify-content: center;
			padding: 40px;
		}

		.bkx-spinner {
			width: 40px;
			height: 40px;
			border: 4px solid #e4e6eb;
			border-top-color: #1877f2;
			border-radius: 50%;
			animation: spin 1s linear infinite;
		}

		@keyframes spin {
			to {
				transform: rotate(360deg);
			}
		}

		.bkx-error {
			background: #ffebe9;
			border: 1px solid #ff8785;
			color: #c0392b;
			padding: 12px 16px;
			border-radius: 8px;
			margin-bottom: 16px;
			font-size: 14px;
		}
	</style>
</head>
<body>
	<div class="bkx-widget">
		<div class="bkx-widget-header">
			<h1><?php echo esc_html( $business_name ); ?></h1>
			<p><?php esc_html_e( 'Book your appointment online', 'bkx-facebook-booking' ); ?></p>
		</div>

		<div class="bkx-widget-body">
			<!-- Step 1: Select Service -->
			<div class="bkx-step active" id="step-service">
				<h2 class="bkx-step-header"><?php esc_html_e( 'Select a Service', 'bkx-facebook-booking' ); ?></h2>
				<div class="bkx-services">
					<?php if ( empty( $services ) ) : ?>
						<p><?php esc_html_e( 'No services available at this time.', 'bkx-facebook-booking' ); ?></p>
					<?php else : ?>
						<?php foreach ( $services as $service ) : ?>
							<div class="bkx-service"
								 data-service-id="<?php echo esc_attr( $service->bkx_service_id ); ?>"
								 data-service-name="<?php echo esc_attr( $service->name ); ?>"
								 data-service-price="<?php echo esc_attr( $service->price ); ?>"
								 data-service-duration="<?php echo esc_attr( $service->duration_minutes ); ?>">
								<div class="bkx-service-info">
									<h3><?php echo esc_html( $service->name ); ?></h3>
									<p><?php echo esc_html( $service->duration_minutes ); ?> <?php esc_html_e( 'min', 'bkx-facebook-booking' ); ?></p>
								</div>
								<div class="bkx-service-price">
									<?php
									if ( $service->price > 0 ) {
										echo esc_html( '$' . number_format( $service->price, 2 ) );
									} else {
										esc_html_e( 'Free', 'bkx-facebook-booking' );
									}
									?>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- Step 2: Select Date -->
			<div class="bkx-step" id="step-date">
				<h2 class="bkx-step-header"><?php esc_html_e( 'Select a Date', 'bkx-facebook-booking' ); ?></h2>
				<div class="bkx-calendar" id="calendar"></div>
				<button type="button" class="bkx-btn bkx-btn-secondary" onclick="goToStep('service')">
					<?php esc_html_e( 'Back', 'bkx-facebook-booking' ); ?>
				</button>
			</div>

			<!-- Step 3: Select Time -->
			<div class="bkx-step" id="step-time">
				<h2 class="bkx-step-header"><?php esc_html_e( 'Select a Time', 'bkx-facebook-booking' ); ?></h2>
				<div class="bkx-times" id="times"></div>
				<button type="button" class="bkx-btn bkx-btn-secondary" onclick="goToStep('date')">
					<?php esc_html_e( 'Back', 'bkx-facebook-booking' ); ?>
				</button>
			</div>

			<!-- Step 4: Your Details -->
			<div class="bkx-step" id="step-details">
				<h2 class="bkx-step-header"><?php esc_html_e( 'Your Details', 'bkx-facebook-booking' ); ?></h2>
				<div id="form-error" class="bkx-error" style="display: none;"></div>
				<div class="bkx-form-group">
					<label for="customer_name"><?php esc_html_e( 'Your Name', 'bkx-facebook-booking' ); ?></label>
					<input type="text" id="customer_name" name="customer_name" required>
				</div>
				<div class="bkx-form-group">
					<label for="customer_email"><?php esc_html_e( 'Email Address', 'bkx-facebook-booking' ); ?></label>
					<input type="email" id="customer_email" name="customer_email" required>
				</div>
				<div class="bkx-form-group">
					<label for="customer_phone"><?php esc_html_e( 'Phone Number', 'bkx-facebook-booking' ); ?></label>
					<input type="tel" id="customer_phone" name="customer_phone">
				</div>
				<button type="button" class="bkx-btn bkx-btn-primary" onclick="goToStep('confirm')">
					<?php esc_html_e( 'Continue', 'bkx-facebook-booking' ); ?>
				</button>
				<button type="button" class="bkx-btn bkx-btn-secondary" onclick="goToStep('time')">
					<?php esc_html_e( 'Back', 'bkx-facebook-booking' ); ?>
				</button>
			</div>

			<!-- Step 5: Confirm -->
			<div class="bkx-step" id="step-confirm">
				<h2 class="bkx-step-header"><?php esc_html_e( 'Confirm Booking', 'bkx-facebook-booking' ); ?></h2>
				<div class="bkx-summary" id="booking-summary"></div>
				<button type="button" class="bkx-btn bkx-btn-primary" id="confirm-booking">
					<?php esc_html_e( 'Confirm Booking', 'bkx-facebook-booking' ); ?>
				</button>
				<button type="button" class="bkx-btn bkx-btn-secondary" onclick="goToStep('details')">
					<?php esc_html_e( 'Back', 'bkx-facebook-booking' ); ?>
				</button>
			</div>

			<!-- Step 6: Success -->
			<div class="bkx-step" id="step-success">
				<div class="bkx-success">
					<div class="bkx-success-icon">
						<svg viewBox="0 0 24 24">
							<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
						</svg>
					</div>
					<h2><?php esc_html_e( 'Booking Confirmed!', 'bkx-facebook-booking' ); ?></h2>
					<p id="success-message"></p>
					<button type="button" class="bkx-btn bkx-btn-primary" onclick="location.reload()">
						<?php esc_html_e( 'Book Another', 'bkx-facebook-booking' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<script>
		(function() {
			var bookingData = {
				pageId: '<?php echo esc_js( $page_id ); ?>',
				serviceId: null,
				serviceName: '',
				servicePrice: 0,
				serviceDuration: 0,
				date: null,
				time: null,
				customerName: '',
				customerEmail: '',
				customerPhone: ''
			};

			var currentMonth = new Date();

			// Service selection
			document.querySelectorAll('.bkx-service').forEach(function(el) {
				el.addEventListener('click', function() {
					document.querySelectorAll('.bkx-service').forEach(function(s) {
						s.classList.remove('selected');
					});
					this.classList.add('selected');

					bookingData.serviceId = this.dataset.serviceId;
					bookingData.serviceName = this.dataset.serviceName;
					bookingData.servicePrice = parseFloat(this.dataset.servicePrice) || 0;
					bookingData.serviceDuration = parseInt(this.dataset.serviceDuration) || 60;

					goToStep('date');
					renderCalendar();
				});
			});

			// Calendar rendering
			function renderCalendar() {
				var container = document.getElementById('calendar');
				var today = new Date();
				var firstDay = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1);
				var lastDay = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 0);

				var html = '<div class="bkx-calendar-header">';
				html += '<button onclick="changeMonth(-1)">&larr;</button>';
				html += '<h4>' + firstDay.toLocaleString('default', { month: 'long', year: 'numeric' }) + '</h4>';
				html += '<button onclick="changeMonth(1)">&rarr;</button>';
				html += '</div>';

				html += '<div class="bkx-calendar-grid">';

				var dayNames = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
				dayNames.forEach(function(d) {
					html += '<div class="bkx-calendar-day-name">' + d + '</div>';
				});

				for (var i = 0; i < firstDay.getDay(); i++) {
					html += '<div class="bkx-calendar-day empty"></div>';
				}

				for (var day = 1; day <= lastDay.getDate(); day++) {
					var date = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
					var dateStr = date.toISOString().split('T')[0];
					var isPast = date < today && date.toDateString() !== today.toDateString();
					var isSunday = date.getDay() === 0;

					html += '<div class="bkx-calendar-day' + (isPast || isSunday ? ' disabled' : '') + '" ';
					html += 'data-date="' + dateStr + '" onclick="selectDate(\'' + dateStr + '\')">';
					html += day;
					html += '</div>';
				}

				html += '</div>';
				container.innerHTML = html;
			}

			window.changeMonth = function(delta) {
				currentMonth.setMonth(currentMonth.getMonth() + delta);
				renderCalendar();
			};

			window.selectDate = function(dateStr) {
				var el = document.querySelector('[data-date="' + dateStr + '"]');
				if (el && !el.classList.contains('disabled')) {
					document.querySelectorAll('.bkx-calendar-day').forEach(function(d) {
						d.classList.remove('selected');
					});
					el.classList.add('selected');
					bookingData.date = dateStr;
					loadTimes();
					goToStep('time');
				}
			};

			// Load available times
			function loadTimes() {
				var container = document.getElementById('times');
				container.innerHTML = '<div class="bkx-loading"><div class="bkx-spinner"></div></div>';

				// Simulated available times (in production, this would be an API call)
				var times = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'];

				var html = '';
				times.forEach(function(time) {
					html += '<div class="bkx-time" data-time="' + time + '" onclick="selectTime(\'' + time + '\')">';
					html += formatTime(time);
					html += '</div>';
				});

				container.innerHTML = html;
			}

			window.selectTime = function(time) {
				document.querySelectorAll('.bkx-time').forEach(function(t) {
					t.classList.remove('selected');
				});
				document.querySelector('[data-time="' + time + '"]').classList.add('selected');
				bookingData.time = time;
				goToStep('details');
			};

			// Step navigation
			window.goToStep = function(step) {
				document.querySelectorAll('.bkx-step').forEach(function(s) {
					s.classList.remove('active');
				});
				document.getElementById('step-' + step).classList.add('active');

				if (step === 'confirm') {
					bookingData.customerName = document.getElementById('customer_name').value;
					bookingData.customerEmail = document.getElementById('customer_email').value;
					bookingData.customerPhone = document.getElementById('customer_phone').value;

					if (!bookingData.customerName || !bookingData.customerEmail) {
						document.getElementById('form-error').textContent = '<?php esc_html_e( 'Please fill in all required fields.', 'bkx-facebook-booking' ); ?>';
						document.getElementById('form-error').style.display = 'block';
						document.getElementById('step-confirm').classList.remove('active');
						document.getElementById('step-details').classList.add('active');
						return;
					}

					renderSummary();
				}
			};

			function renderSummary() {
				var date = new Date(bookingData.date + 'T12:00:00');
				var html = '';
				html += '<div class="bkx-summary-row"><span><?php esc_html_e( 'Service:', 'bkx-facebook-booking' ); ?></span><span>' + bookingData.serviceName + '</span></div>';
				html += '<div class="bkx-summary-row"><span><?php esc_html_e( 'Date:', 'bkx-facebook-booking' ); ?></span><span>' + date.toLocaleDateString('default', { weekday: 'long', month: 'long', day: 'numeric' }) + '</span></div>';
				html += '<div class="bkx-summary-row"><span><?php esc_html_e( 'Time:', 'bkx-facebook-booking' ); ?></span><span>' + formatTime(bookingData.time) + '</span></div>';
				html += '<div class="bkx-summary-row"><span><?php esc_html_e( 'Duration:', 'bkx-facebook-booking' ); ?></span><span>' + bookingData.serviceDuration + ' <?php esc_html_e( 'min', 'bkx-facebook-booking' ); ?></span></div>';
				html += '<div class="bkx-summary-row"><span><?php esc_html_e( 'Total:', 'bkx-facebook-booking' ); ?></span><span>' + (bookingData.servicePrice > 0 ? '$' + bookingData.servicePrice.toFixed(2) : '<?php esc_html_e( 'Free', 'bkx-facebook-booking' ); ?>') + '</span></div>';
				document.getElementById('booking-summary').innerHTML = html;
			}

			function formatTime(time) {
				var parts = time.split(':');
				var hours = parseInt(parts[0]);
				var minutes = parts[1];
				var ampm = hours >= 12 ? 'PM' : 'AM';
				hours = hours % 12 || 12;
				return hours + ':' + minutes + ' ' + ampm;
			}

			// Submit booking
			document.getElementById('confirm-booking').addEventListener('click', function() {
				this.disabled = true;
				this.textContent = '<?php esc_html_e( 'Processing...', 'bkx-facebook-booking' ); ?>';

				fetch('<?php echo esc_url( rest_url( 'bkx-fb/v1/widget/book' ) ); ?>', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({
						page_id: bookingData.pageId,
						service_id: bookingData.serviceId,
						booking_date: bookingData.date,
						start_time: bookingData.time + ':00',
						customer_name: bookingData.customerName,
						customer_email: bookingData.customerEmail,
						customer_phone: bookingData.customerPhone
					})
				})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					if (data.success) {
						var date = new Date(bookingData.date + 'T12:00:00');
						document.getElementById('success-message').textContent =
							'<?php esc_html_e( 'Your appointment has been scheduled for', 'bkx-facebook-booking' ); ?> ' +
							date.toLocaleDateString('default', { weekday: 'long', month: 'long', day: 'numeric' }) +
							' <?php esc_html_e( 'at', 'bkx-facebook-booking' ); ?> ' + formatTime(bookingData.time) + '.';
						goToStep('success');
					} else {
						alert(data.message || '<?php esc_html_e( 'An error occurred. Please try again.', 'bkx-facebook-booking' ); ?>');
						document.getElementById('confirm-booking').disabled = false;
						document.getElementById('confirm-booking').textContent = '<?php esc_html_e( 'Confirm Booking', 'bkx-facebook-booking' ); ?>';
					}
				})
				.catch(function(error) {
					alert('<?php esc_html_e( 'An error occurred. Please try again.', 'bkx-facebook-booking' ); ?>');
					document.getElementById('confirm-booking').disabled = false;
					document.getElementById('confirm-booking').textContent = '<?php esc_html_e( 'Confirm Booking', 'bkx-facebook-booking' ); ?>';
				});
			});
		})();
	</script>
</body>
</html>

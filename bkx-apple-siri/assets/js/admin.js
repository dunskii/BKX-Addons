/**
 * Apple Siri Admin JavaScript
 *
 * @package BookingX\AppleSiri
 */

(function($) {
	'use strict';

	var BkxAppleSiri = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.initIntentSimulator();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Save settings.
			$('#bkx-apple-siri-settings-form').on('submit', this.saveSettings.bind(this));

			// Test connection.
			$('#bkx-test-connection, #bkx-test-connection-btn').on('click', this.testConnection.bind(this));

			// Regenerate secret.
			$('#bkx-regenerate-secret').on('click', this.regenerateSecret.bind(this));

			// Download shortcut.
			$('[data-shortcut]').on('click', this.downloadShortcut.bind(this));

			// Copy URL.
			$('#bkx-copy-url').on('click', this.copyUrl.bind(this));

			// Copy AASA.
			$('#bkx-copy-aasa').on('click', this.copyAasa.bind(this));

			// Test endpoints.
			$('#bkx-test-shortcuts-btn').on('click', this.testShortcuts.bind(this));
			$('#bkx-test-availability-btn').on('click', this.testAvailability.bind(this));

			// Intent simulator.
			$('#bkx-intent-simulator').on('submit', this.simulateIntent.bind(this));
			$('#sim-intent-type').on('change', this.updateSimulatorFields.bind(this));

			// Voice parser.
			$('#bkx-voice-parser').on('submit', this.parseVoice.bind(this));

			// Copy response.
			$('#bkx-copy-response').on('click', this.copyResponse.bind(this));

			// Logs.
			$('#bkx-refresh-logs').on('click', this.refreshLogs.bind(this));
			$('#bkx-clear-logs').on('click', this.clearLogs.bind(this));
			$(document).on('click', '.bkx-view-log-details', this.viewLogDetails.bind(this));
			$(document).on('click', '.bkx-modal-close', this.closeModal.bind(this));
			$(document).on('click', '.bkx-modal', function(e) {
				if ($(e.target).hasClass('bkx-modal')) {
					BkxAppleSiri.closeModal();
				}
			});
		},

		/**
		 * Save settings.
		 */
		saveSettings: function(e) {
			e.preventDefault();

			var $form = $(e.target);
			var $button = $('#bkx-save-settings');
			var $spinner = $form.find('.spinner');
			var $status = $('#bkx-save-status');

			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			$status.removeClass('success error').text('');

			var settings = {
				enabled: $('#enabled').is(':checked'),
				team_id: $('#team_id').val(),
				key_id: $('#key_id').val(),
				bundle_identifier: $('#bundle_identifier').val(),
				private_key: $('#private_key').val(),
				intent_types: [],
				default_service_id: $('#default_service_id').val(),
				require_confirmation: $('#require_confirmation').is(':checked'),
				voice_phrases: {},
				shortcuts_enabled: $('#shortcuts_enabled').is(':checked'),
				send_booking_to_reminders: $('#send_booking_to_reminders').is(':checked'),
				webhook_secret: $('#webhook_secret').val(),
				log_requests: $('#log_requests').is(':checked')
			};

			// Collect intent types.
			$('input[name="intent_types[]"]:checked').each(function() {
				settings.intent_types.push($(this).val());
			});

			// Collect voice phrases.
			$('input[name^="voice_phrases["]').each(function() {
				var name = $(this).attr('name').match(/\[([^\]]+)\]/)[1];
				settings.voice_phrases[name] = $(this).val();
			});

			$.ajax({
				url: bkxAppleSiri.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_apple_siri_save_settings',
					nonce: bkxAppleSiri.nonce,
					settings: settings
				},
				success: function(response) {
					if (response.success) {
						$status.addClass('success').text(response.data.message);
					} else {
						$status.addClass('error').text(response.data.message || bkxAppleSiri.i18n.error);
					}
				},
				error: function() {
					$status.addClass('error').text(bkxAppleSiri.i18n.error);
				},
				complete: function() {
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		},

		/**
		 * Test connection.
		 */
		testConnection: function(e) {
			e.preventDefault();

			var $button = $(e.target);
			var $status = $('#bkx-connection-status, #connection-test-result');

			$button.prop('disabled', true);
			$status.removeClass('success error').text(bkxAppleSiri.i18n.testing);

			$.ajax({
				url: bkxAppleSiri.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_apple_siri_test_connection',
					nonce: bkxAppleSiri.nonce
				},
				success: function(response) {
					if (response.success) {
						$status.addClass('success').text(response.data.message).show();
					} else {
						$status.addClass('error').text(response.data.message || bkxAppleSiri.i18n.connectionFailed).show();
					}
				},
				error: function() {
					$status.addClass('error').text(bkxAppleSiri.i18n.error).show();
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Regenerate secret.
		 */
		regenerateSecret: function(e) {
			e.preventDefault();

			if (!confirm('Are you sure you want to regenerate the webhook secret? Existing integrations will need to be updated.')) {
				return;
			}

			var newSecret = this.generateRandomString(32);
			$('#webhook_secret').val(newSecret);
		},

		/**
		 * Generate random string.
		 */
		generateRandomString: function(length) {
			var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
			var result = '';
			for (var i = 0; i < length; i++) {
				result += chars.charAt(Math.floor(Math.random() * chars.length));
			}
			return result;
		},

		/**
		 * Download shortcut.
		 */
		downloadShortcut: function(e) {
			e.preventDefault();

			var $button = $(e.target);
			var type = $button.data('shortcut');

			$button.prop('disabled', true).text('Generating...');

			$.ajax({
				url: bkxAppleSiri.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_apple_siri_generate_shortcut',
					nonce: bkxAppleSiri.nonce,
					shortcut_type: type
				},
				success: function(response) {
					if (response.success) {
						// Create downloadable file.
						var blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
						var url = URL.createObjectURL(blob);
						var a = document.createElement('a');
						a.href = url;
						a.download = 'bkx-' + type + '.shortcut';
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
						URL.revokeObjectURL(url);
					} else {
						alert(response.data.message || 'Failed to generate shortcut.');
					}
				},
				error: function() {
					alert('Failed to generate shortcut.');
				},
				complete: function() {
					$button.prop('disabled', false).text('Download Shortcut');
				}
			});
		},

		/**
		 * Copy URL.
		 */
		copyUrl: function(e) {
			e.preventDefault();

			var $input = $(e.target).siblings('input');
			$input.select();
			document.execCommand('copy');

			var $button = $(e.target);
			var originalText = $button.text();
			$button.text('Copied!');
			setTimeout(function() {
				$button.text(originalText);
			}, 2000);
		},

		/**
		 * Copy AASA.
		 */
		copyAasa: function(e) {
			e.preventDefault();

			var content = $('#bkx-aasa-content').text();
			navigator.clipboard.writeText(content).then(function() {
				var $button = $(e.target);
				var originalText = $button.text();
				$button.text('Copied!');
				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			});
		},

		/**
		 * Test shortcuts endpoint.
		 */
		testShortcuts: function(e) {
			e.preventDefault();

			var $result = $('#shortcuts-test-result');
			$result.removeClass('success error').hide();

			$.ajax({
				url: bkxAppleSiri.ajaxUrl.replace('admin-ajax.php', 'wp-json/bkx-apple-siri/v1/shortcuts'),
				type: 'GET',
				success: function(response) {
					if (response.shortcuts) {
						$result.addClass('success').text('Success! Found ' + response.shortcuts.length + ' shortcuts.').show();
					} else {
						$result.addClass('error').text('Unexpected response format.').show();
					}
				},
				error: function(xhr) {
					$result.addClass('error').text('Error: ' + (xhr.responseJSON?.message || 'Request failed.')).show();
				}
			});
		},

		/**
		 * Test availability endpoint.
		 */
		testAvailability: function(e) {
			e.preventDefault();

			var $result = $('#availability-test-result');
			var tomorrow = new Date();
			tomorrow.setDate(tomorrow.getDate() + 1);
			var dateStr = tomorrow.toISOString().split('T')[0];

			$result.removeClass('success error').hide();

			$.ajax({
				url: bkxAppleSiri.ajaxUrl.replace('admin-ajax.php', 'wp-json/bkx-apple-siri/v1/availability?date=' + dateStr),
				type: 'GET',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('Authorization', 'Bearer test-token');
				},
				success: function(response) {
					if (response.slots !== undefined) {
						$result.addClass('success').text('Success! Found ' + response.slots.length + ' available slots.').show();
					} else {
						$result.addClass('error').text('Unexpected response format.').show();
					}
				},
				error: function(xhr) {
					var msg = xhr.status === 401 ? 'Authorization required (expected in testing).' :
						(xhr.responseJSON?.message || 'Request failed.');
					$result.addClass(xhr.status === 401 ? 'success' : 'error').text(msg).show();
				}
			});
		},

		/**
		 * Initialize intent simulator.
		 */
		initIntentSimulator: function() {
			this.updateSimulatorFields();
		},

		/**
		 * Update simulator fields based on intent type.
		 */
		updateSimulatorFields: function() {
			var type = $('#sim-intent-type').val();

			// Show/hide fields based on intent type.
			$('.bkx-sim-field').hide();
			$('.bkx-sim-field-voice-input').show();

			switch (type) {
				case 'BookAppointmentIntent':
					$('.bkx-sim-field-date, .bkx-sim-field-time, .bkx-sim-field-service').show();
					break;
				case 'CheckAvailabilityIntent':
					$('.bkx-sim-field-date').show();
					break;
				case 'RescheduleAppointmentIntent':
					$('.bkx-sim-field-booking-id, .bkx-sim-field-date, .bkx-sim-field-time').show();
					break;
				case 'CancelAppointmentIntent':
					$('.bkx-sim-field-booking-id').show();
					break;
				case 'GetUpcomingAppointmentsIntent':
					// No additional fields.
					break;
			}
		},

		/**
		 * Simulate intent.
		 */
		simulateIntent: function(e) {
			e.preventDefault();

			var $form = $(e.target);
			var $result = $('#bkx-simulator-result');

			var intentData = {
				intent: {
					type: $('#sim-intent-type').val(),
					data: {
						date: $('#sim-date').val(),
						time: $('#sim-time').val(),
						service: $('#sim-service').val(),
						booking_id: $('#sim-booking-id').val()
					}
				},
				voice_input: $('#sim-voice-input').val()
			};

			// Simulate the response (in production this would call the API).
			var response = this.buildSimulatedResponse(intentData);

			$result.show();
			$result.find('.bkx-result-status')
				.removeClass('success error')
				.addClass(response.responseCode === 'success' ? 'success' : 'error')
				.text(response.responseCode);
			$result.find('.bkx-result-body').text(JSON.stringify(response, null, 2));
			$result.find('.bkx-spoken-text').text(response.dialog?.speakableText || '');
		},

		/**
		 * Build simulated response.
		 */
		buildSimulatedResponse: function(data) {
			var type = data.intent.type;
			var intentData = data.intent.data;

			switch (type) {
				case 'BookAppointmentIntent':
					return {
						responseCode: 'success',
						intent: {
							type: type,
							status: 'confirmed',
							bookingID: Math.floor(Math.random() * 10000),
							bookingDate: intentData.date,
							bookingTime: intentData.time,
							serviceName: intentData.service || 'Appointment'
						},
						dialog: {
							speakableText: 'I\'ve booked your appointment for ' + intentData.date + ' at ' + intentData.time + '.',
							displayString: 'Booking confirmed'
						}
					};

				case 'CheckAvailabilityIntent':
					return {
						responseCode: 'success',
						intent: {
							type: type,
							date: intentData.date,
							slotCount: 5,
							slots: [
								{ time: '09:00', available: true },
								{ time: '10:00', available: true },
								{ time: '11:00', available: true },
								{ time: '14:00', available: true },
								{ time: '15:00', available: true }
							]
						},
						dialog: {
							speakableText: 'There are 5 available times on ' + intentData.date + '. The first few are 9 AM, 10 AM, and 11 AM.',
							displayString: '5 slots available'
						}
					};

				case 'CancelAppointmentIntent':
					return {
						responseCode: 'success',
						intent: {
							type: type,
							status: 'cancelled',
							bookingID: intentData.booking_id
						},
						dialog: {
							speakableText: 'I\'ve cancelled your appointment.',
							displayString: 'Appointment cancelled'
						}
					};

				case 'GetUpcomingAppointmentsIntent':
					return {
						responseCode: 'success',
						intent: {
							type: type,
							count: 2,
							bookings: [
								{ id: 1, date: '2024-01-15', time: '10:00', service_name: 'Consultation' },
								{ id: 2, date: '2024-01-20', time: '14:00', service_name: 'Follow-up' }
							]
						},
						dialog: {
							speakableText: 'You have 2 upcoming appointments. The next one is a Consultation on January 15th at 10 AM.',
							displayString: '2 upcoming appointments'
						}
					};

				default:
					return {
						responseCode: 'error',
						error: { code: 'unknown_intent', message: 'Unknown intent type' },
						dialog: {
							speakableText: 'Sorry, I couldn\'t understand that request.',
							displayString: 'Error'
						}
					};
			}
		},

		/**
		 * Parse voice input.
		 */
		parseVoice: function(e) {
			e.preventDefault();

			var input = $('#parser-voice-input').val();
			var $result = $('#bkx-parser-result');
			var $tbody = $('#bkx-parser-table-body');

			// Simple parsing simulation.
			var parsed = this.parseVoiceInput(input);

			$tbody.empty();

			for (var field in parsed) {
				var confidence = parsed[field].confidence;
				var confidenceClass = confidence > 0.8 ? 'success' : (confidence > 0.5 ? 'warning' : 'low');

				$tbody.append(
					'<tr>' +
					'<td>' + field + '</td>' +
					'<td><strong>' + (parsed[field].value || '-') + '</strong></td>' +
					'<td><span class="bkx-confidence ' + confidenceClass + '">' + Math.round(confidence * 100) + '%</span></td>' +
					'</tr>'
				);
			}

			$result.show();
		},

		/**
		 * Parse voice input (simulated).
		 */
		parseVoiceInput: function(input) {
			var result = {
				intent: { value: '', confidence: 0 },
				date: { value: '', confidence: 0 },
				time: { value: '', confidence: 0 },
				service: { value: '', confidence: 0 }
			};

			input = input.toLowerCase();

			// Detect intent.
			if (input.includes('book') || input.includes('schedule') || input.includes('make an appointment')) {
				result.intent.value = 'BookAppointmentIntent';
				result.intent.confidence = 0.9;
			} else if (input.includes('cancel')) {
				result.intent.value = 'CancelAppointmentIntent';
				result.intent.confidence = 0.95;
			} else if (input.includes('reschedule') || input.includes('move') || input.includes('change')) {
				result.intent.value = 'RescheduleAppointmentIntent';
				result.intent.confidence = 0.85;
			} else if (input.includes('available') || input.includes('availability') || input.includes('times')) {
				result.intent.value = 'CheckAvailabilityIntent';
				result.intent.confidence = 0.9;
			} else if (input.includes('show') || input.includes('upcoming') || input.includes('my appointments')) {
				result.intent.value = 'GetUpcomingAppointmentsIntent';
				result.intent.confidence = 0.85;
			}

			// Detect date.
			if (input.includes('tomorrow')) {
				var tomorrow = new Date();
				tomorrow.setDate(tomorrow.getDate() + 1);
				result.date.value = tomorrow.toISOString().split('T')[0];
				result.date.confidence = 0.95;
			} else if (input.includes('today')) {
				result.date.value = new Date().toISOString().split('T')[0];
				result.date.confidence = 0.95;
			} else if (input.includes('monday') || input.includes('tuesday') || input.includes('wednesday') ||
				input.includes('thursday') || input.includes('friday') || input.includes('saturday') || input.includes('sunday')) {
				result.date.value = 'Next ' + input.match(/(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i)[0];
				result.date.confidence = 0.8;
			}

			// Detect time.
			var timeMatch = input.match(/(\d{1,2})\s*(am|pm|:)/i);
			if (timeMatch) {
				var hour = parseInt(timeMatch[1]);
				var period = timeMatch[2].toLowerCase();
				if (period === 'pm' && hour < 12) hour += 12;
				if (period === 'am' && hour === 12) hour = 0;
				result.time.value = hour.toString().padStart(2, '0') + ':00';
				result.time.confidence = 0.85;
			}

			// Detect service.
			var services = ['haircut', 'consultation', 'massage', 'appointment', 'meeting', 'checkup'];
			for (var i = 0; i < services.length; i++) {
				if (input.includes(services[i])) {
					result.service.value = services[i].charAt(0).toUpperCase() + services[i].slice(1);
					result.service.confidence = 0.75;
					break;
				}
			}

			return result;
		},

		/**
		 * Copy response.
		 */
		copyResponse: function(e) {
			e.preventDefault();

			var content = $('.bkx-result-body').text();
			navigator.clipboard.writeText(content).then(function() {
				var $button = $(e.target);
				var originalText = $button.text();
				$button.text('Copied!');
				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			});
		},

		/**
		 * Refresh logs.
		 */
		refreshLogs: function(e) {
			e.preventDefault();
			window.location.reload();
		},

		/**
		 * Clear logs.
		 */
		clearLogs: function(e) {
			e.preventDefault();

			if (!confirm('Are you sure you want to clear all logs?')) {
				return;
			}

			$.ajax({
				url: bkxAppleSiri.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_apple_siri_clear_logs',
					nonce: bkxAppleSiri.nonce
				},
				success: function(response) {
					if (response.success) {
						window.location.reload();
					} else {
						alert(response.data.message || 'Failed to clear logs.');
					}
				},
				error: function() {
					alert('Failed to clear logs.');
				}
			});
		},

		/**
		 * View log details.
		 */
		viewLogDetails: function(e) {
			e.preventDefault();

			var log = $(e.target).data('log');
			$('#bkx-log-details-content').text(JSON.stringify(log, null, 2));
			$('#bkx-log-modal').show();
		},

		/**
		 * Close modal.
		 */
		closeModal: function() {
			$('#bkx-log-modal').hide();
		}
	};

	$(document).ready(function() {
		BkxAppleSiri.init();
	});

})(jQuery);

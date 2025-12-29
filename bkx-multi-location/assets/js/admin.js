/**
 * Multi-Location Management Admin JavaScript
 *
 * @package BookingX\MultiLocation
 */

(function($) {
	'use strict';

	var BkxML = {
		currentLocationId: 0,

		init: function() {
			this.bindEvents();
			this.initSortable();
		},

		bindEvents: function() {
			// Add location button
			$('#bkx-ml-add-location').on('click', this.openNewLocationModal.bind(this));

			// Edit location
			$(document).on('click', '.bkx-ml-edit-location', this.openEditLocationModal.bind(this));

			// Delete location
			$(document).on('click', '.bkx-ml-delete-location', this.deleteLocation.bind(this));

			// Modal close
			$('.bkx-ml-modal-close, .bkx-ml-modal-cancel').on('click', this.closeModal);
			$('.bkx-ml-modal').on('click', function(e) {
				if (e.target === this) {
					BkxML.closeModal();
				}
			});

			// Tab switching
			$(document).on('click', '.bkx-ml-tab', this.switchTab);

			// Form submission
			$('#bkx-ml-location-form').on('submit', this.saveLocation.bind(this));

			// Geocode button
			$('#bkx-ml-geocode').on('click', this.geocodeAddress.bind(this));

			// Add holiday
			$('#bkx-ml-add-holiday-btn').on('click', this.addHoliday.bind(this));

			// Delete holiday
			$(document).on('click', '.holiday-delete', this.deleteHoliday.bind(this));

			// Assign staff
			$('#bkx-ml-assign-staff-btn').on('click', this.assignStaff.bind(this));

			// Remove staff
			$(document).on('click', '.staff-remove', this.removeStaff.bind(this));

			// Service availability change
			$(document).on('change', '.service-available-checkbox', this.saveService.bind(this));

			// Service price/duration change
			$(document).on('blur', '.service-price-input, .service-duration-input', this.saveService.bind(this));
		},

		initSortable: function() {
			$('#bkx-ml-locations-sortable').sortable({
				handle: '.bkx-ml-col-handle',
				axis: 'y',
				update: function(event, ui) {
					BkxML.saveOrder();
				}
			});
		},

		openNewLocationModal: function(e) {
			e.preventDefault();

			this.currentLocationId = 0;
			$('#bkx-ml-modal-title').text(bkxML.strings.add_location || 'Add Location');
			$('#bkx-ml-location-id').val(0);

			// Reset form
			$('#bkx-ml-location-form')[0].reset();
			$('#bkx-ml-holidays-list').empty();
			$('#bkx-ml-staff-list').html('<p class="bkx-ml-no-items">No staff assigned yet.</p>');
			this.loadServices(0);

			// Set default hours
			$('.bkx-ml-day-open').each(function() {
				var day = $(this).data('day');
				var isWeekday = day >= 1 && day <= 5;
				$(this).prop('checked', isWeekday);
			});

			// Switch to details tab
			$('.bkx-ml-tab').removeClass('active').first().addClass('active');
			$('.bkx-ml-tab-content').removeClass('active').first().addClass('active');

			$('#bkx-ml-location-modal').show();
		},

		openEditLocationModal: function(e) {
			e.preventDefault();

			var locationId = $(e.currentTarget).data('id');
			this.currentLocationId = locationId;

			$('#bkx-ml-modal-title').text(bkxML.strings.edit_location || 'Edit Location');
			$('#bkx-ml-location-id').val(locationId);

			// Load location data
			$.ajax({
				url: bkxML.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_ml_get_location',
					nonce: bkxML.nonce,
					location_id: locationId
				},
				success: function(response) {
					if (response.success) {
						BkxML.populateForm(response.data.location);
						$('#bkx-ml-location-modal').show();
					} else {
						alert(response.data.message || bkxML.strings.error);
					}
				},
				error: function() {
					alert(bkxML.strings.error);
				}
			});
		},

		populateForm: function(location) {
			// Basic details
			$('#bkx-ml-name').val(location.name);
			$('#bkx-ml-slug').val(location.slug);
			$('#bkx-ml-description').val(location.description);
			$('#bkx-ml-phone').val(location.phone);
			$('#bkx-ml-email').val(location.email);
			$('#bkx-ml-timezone').val(location.timezone);
			$('#bkx-ml-status').val(location.status);

			// Address
			$('#bkx-ml-address1').val(location.address_line_1);
			$('#bkx-ml-address2').val(location.address_line_2);
			$('#bkx-ml-city').val(location.city);
			$('#bkx-ml-state').val(location.state);
			$('#bkx-ml-postal').val(location.postal_code);
			$('#bkx-ml-country').val(location.country);
			$('#bkx-ml-lat').val(location.latitude);
			$('#bkx-ml-lng').val(location.longitude);

			// Hours
			if (location.hours) {
				location.hours.forEach(function(hour) {
					var dayRow = $('[name="hours[' + hour.day_of_week + '][is_open]"]').closest('tr');
					dayRow.find('[name="hours[' + hour.day_of_week + '][is_open]"]').prop('checked', hour.is_open == 1);
					dayRow.find('[name="hours[' + hour.day_of_week + '][open_time]"]').val(hour.open_time ? hour.open_time.substring(0, 5) : '');
					dayRow.find('[name="hours[' + hour.day_of_week + '][close_time]"]').val(hour.close_time ? hour.close_time.substring(0, 5) : '');
					dayRow.find('[name="hours[' + hour.day_of_week + '][break_start]"]').val(hour.break_start ? hour.break_start.substring(0, 5) : '');
					dayRow.find('[name="hours[' + hour.day_of_week + '][break_end]"]').val(hour.break_end ? hour.break_end.substring(0, 5) : '');
				});
			}

			// Holidays
			this.renderHolidays(location.holidays || []);

			// Staff
			this.renderStaff(location.staff || []);

			// Services
			this.loadServices(location.id);
		},

		renderHolidays: function(holidays) {
			var html = '';

			holidays.forEach(function(holiday) {
				html += '<div class="bkx-ml-holiday-item" data-id="' + holiday.id + '">' +
					'<span class="holiday-name">' + holiday.name + '</span>' +
					'<span class="holiday-date">' + holiday.date + '</span>' +
					(holiday.is_recurring == 1 ? '<span class="holiday-recurring">Recurring</span>' : '') +
					'<span class="holiday-delete dashicons dashicons-trash"></span>' +
					'</div>';
			});

			if (!html) {
				html = '<p class="bkx-ml-no-items">No holidays configured.</p>';
			}

			$('#bkx-ml-holidays-list').html(html);
		},

		renderStaff: function(staff) {
			var html = '';

			staff.forEach(function(member) {
				html += '<div class="bkx-ml-staff-item" data-seat-id="' + member.seat_id + '">' +
					'<span class="staff-name">' + member.seat_name + '</span>' +
					(member.is_primary == 1 ? '<span class="staff-primary">Primary</span>' : '') +
					'<span class="staff-remove dashicons dashicons-no"></span>' +
					'</div>';
			});

			if (!html) {
				html = '<p class="bkx-ml-no-items">No staff assigned yet.</p>';
			}

			$('#bkx-ml-staff-list').html(html);
		},

		loadServices: function(locationId) {
			if (!locationId) {
				$('#bkx-ml-services-list').html('<p class="bkx-ml-no-items">Save the location first to configure services.</p>');
				return;
			}

			// Services will be loaded via AJAX when the tab is opened
			$.ajax({
				url: bkxML.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_ml_get_location',
					nonce: bkxML.nonce,
					location_id: locationId
				},
				success: function(response) {
					if (response.success && response.data.location.services) {
						BkxML.renderServices(response.data.location.services);
					}
				}
			});
		},

		renderServices: function(services) {
			var html = '';

			services.forEach(function(service) {
				html += '<div class="bkx-ml-service-item" data-base-id="' + service.base_id + '">' +
					'<div class="service-available">' +
						'<input type="checkbox" class="service-available-checkbox" ' + (service.is_available == 1 ? 'checked' : '') + '>' +
					'</div>' +
					'<div class="service-name">' +
						service.name +
						'<span class="default-price"> (Default: $' + (service.default_price || '0') + ' / ' + (service.default_duration || '30') + ' min)</span>' +
					'</div>' +
					'<div class="service-price">' +
						'<input type="number" step="0.01" class="service-price-input" placeholder="Price" value="' + (service.price_override || '') + '">' +
					'</div>' +
					'<div class="service-duration">' +
						'<input type="number" class="service-duration-input" placeholder="Min" value="' + (service.duration_override || '') + '">' +
					'</div>' +
				'</div>';
			});

			if (!html) {
				html = '<p class="bkx-ml-no-items">No services found.</p>';
			}

			$('#bkx-ml-services-list').html(html);
		},

		closeModal: function() {
			$('#bkx-ml-location-modal').hide();
		},

		switchTab: function(e) {
			e.preventDefault();

			var tab = $(this).data('tab');

			$('.bkx-ml-tab').removeClass('active');
			$(this).addClass('active');

			$('.bkx-ml-tab-content').removeClass('active');
			$('.bkx-ml-tab-content[data-tab="' + tab + '"]').addClass('active');
		},

		saveLocation: function(e) {
			e.preventDefault();

			var $form = $('#bkx-ml-location-form');
			var $submitBtn = $form.find('button[type="submit"]');

			$submitBtn.prop('disabled', true).text(bkxML.strings.saving);

			var formData = $form.serialize();
			formData += '&action=bkx_ml_save_location&nonce=' + bkxML.nonce;

			// Also save hours
			var hours = {};
			$('.bkx-ml-hours-table tbody tr').each(function() {
				var $row = $(this);
				var day = $row.find('.bkx-ml-day-open').data('day');
				hours[day] = {
					is_open: $row.find('.bkx-ml-day-open').is(':checked') ? 1 : 0,
					open_time: $row.find('[name="hours[' + day + '][open_time]"]').val(),
					close_time: $row.find('[name="hours[' + day + '][close_time]"]').val(),
					break_start: $row.find('[name="hours[' + day + '][break_start]"]').val(),
					break_end: $row.find('[name="hours[' + day + '][break_end]"]').val()
				};
			});

			$.ajax({
				url: bkxML.ajax_url,
				type: 'POST',
				data: formData,
				success: function(response) {
					if (response.success) {
						// Save hours
						$.ajax({
							url: bkxML.ajax_url,
							type: 'POST',
							data: {
								action: 'bkx_ml_save_hours',
								nonce: bkxML.nonce,
								location_id: response.data.location_id,
								hours: hours
							},
							complete: function() {
								BkxML.closeModal();
								location.reload();
							}
						});
					} else {
						alert(response.data.message || bkxML.strings.error);
						$submitBtn.prop('disabled', false).text('Save Location');
					}
				},
				error: function() {
					alert(bkxML.strings.error);
					$submitBtn.prop('disabled', false).text('Save Location');
				}
			});
		},

		deleteLocation: function(e) {
			e.preventDefault();

			if (!confirm(bkxML.strings.confirm_delete)) {
				return;
			}

			var locationId = $(e.currentTarget).data('id');

			$.ajax({
				url: bkxML.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_ml_delete_location',
					nonce: bkxML.nonce,
					location_id: locationId
				},
				success: function(response) {
					if (response.success) {
						$('tr[data-location-id="' + locationId + '"]').fadeOut(function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message || bkxML.strings.error);
					}
				},
				error: function() {
					alert(bkxML.strings.error);
				}
			});
		},

		saveOrder: function() {
			var order = [];

			$('#bkx-ml-locations-sortable tr').each(function() {
				order.push($(this).data('location-id'));
			});

			$.ajax({
				url: bkxML.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_ml_reorder_locations',
					nonce: bkxML.nonce,
					order: order
				}
			});
		},

		geocodeAddress: function() {
			var address = [
				$('#bkx-ml-address1').val(),
				$('#bkx-ml-city').val(),
				$('#bkx-ml-state').val(),
				$('#bkx-ml-postal').val(),
				$('#bkx-ml-country').val()
			].filter(Boolean).join(', ');

			if (!address) {
				alert('Please enter an address first.');
				return;
			}

			var $btn = $('#bkx-ml-geocode');
			$btn.prop('disabled', true).text(bkxML.strings.geocoding);

			$.ajax({
				url: bkxML.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_ml_geocode_address',
					nonce: bkxML.nonce,
					address: address
				},
				success: function(response) {
					if (response.success) {
						$('#bkx-ml-lat').val(response.data.latitude);
						$('#bkx-ml-lng').val(response.data.longitude);
					} else {
						alert(response.data.message || bkxML.strings.geocode_error);
					}
				},
				error: function() {
					alert(bkxML.strings.geocode_error);
				},
				complete: function() {
					$btn.prop('disabled', false).text('Lookup from Address');
				}
			});
		},

		addHoliday: function() {
			var name = $('#bkx-ml-holiday-name').val();
			var date = $('#bkx-ml-holiday-date').val();
			var isRecurring = $('#bkx-ml-holiday-recurring').is(':checked') ? 1 : 0;

			if (!name || !date) {
				alert('Please enter holiday name and date.');
				return;
			}

			if (!this.currentLocationId) {
				alert('Please save the location first.');
				return;
			}

			$.ajax({
				url: bkxML.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_ml_save_holiday',
					nonce: bkxML.nonce,
					location_id: this.currentLocationId,
					name: name,
					date: date,
					is_recurring: isRecurring
				},
				success: function(response) {
					if (response.success) {
						// Add to list
						var html = '<div class="bkx-ml-holiday-item" data-id="' + response.data.holiday_id + '">' +
							'<span class="holiday-name">' + name + '</span>' +
							'<span class="holiday-date">' + date + '</span>' +
							(isRecurring ? '<span class="holiday-recurring">Recurring</span>' : '') +
							'<span class="holiday-delete dashicons dashicons-trash"></span>' +
							'</div>';

						$('#bkx-ml-holidays-list .bkx-ml-no-items').remove();
						$('#bkx-ml-holidays-list').append(html);

						// Clear inputs
						$('#bkx-ml-holiday-name').val('');
						$('#bkx-ml-holiday-date').val('');
						$('#bkx-ml-holiday-recurring').prop('checked', false);
					} else {
						alert(response.data.message || bkxML.strings.error);
					}
				}
			});
		},

		deleteHoliday: function(e) {
			if (!confirm(bkxML.strings.confirm_delete_holiday)) {
				return;
			}

			var $item = $(e.currentTarget).closest('.bkx-ml-holiday-item');
			var holidayId = $item.data('id');

			$.ajax({
				url: bkxML.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_ml_delete_holiday',
					nonce: bkxML.nonce,
					holiday_id: holidayId
				},
				success: function(response) {
					if (response.success) {
						$item.fadeOut(function() {
							$(this).remove();
						});
					}
				}
			});
		},

		assignStaff: function() {
			var seatId = $('#bkx-ml-staff-select').val();
			var isPrimary = $('#bkx-ml-staff-primary').is(':checked') ? 1 : 0;

			if (!seatId) {
				alert('Please select a staff member.');
				return;
			}

			if (!this.currentLocationId) {
				alert('Please save the location first.');
				return;
			}

			$.ajax({
				url: bkxML.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_ml_assign_staff',
					nonce: bkxML.nonce,
					location_id: this.currentLocationId,
					seat_id: seatId,
					is_primary: isPrimary
				},
				success: function(response) {
					if (response.success) {
						// Reload staff list
						$.ajax({
							url: bkxML.ajax_url,
							type: 'POST',
							data: {
								action: 'bkx_ml_get_location',
								nonce: bkxML.nonce,
								location_id: BkxML.currentLocationId
							},
							success: function(res) {
								if (res.success) {
									BkxML.renderStaff(res.data.location.staff || []);
								}
							}
						});

						$('#bkx-ml-staff-select').val('');
						$('#bkx-ml-staff-primary').prop('checked', false);
					} else {
						alert(response.data.message || bkxML.strings.error);
					}
				}
			});
		},

		removeStaff: function(e) {
			var $item = $(e.currentTarget).closest('.bkx-ml-staff-item');
			var seatId = $item.data('seat-id');

			$.ajax({
				url: bkxML.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_ml_remove_staff',
					nonce: bkxML.nonce,
					location_id: this.currentLocationId,
					seat_id: seatId
				},
				success: function(response) {
					if (response.success) {
						$item.fadeOut(function() {
							$(this).remove();
						});
					}
				}
			});
		},

		saveService: function(e) {
			var $item = $(e.currentTarget).closest('.bkx-ml-service-item');
			var baseId = $item.data('base-id');
			var isAvailable = $item.find('.service-available-checkbox').is(':checked') ? 1 : 0;
			var priceOverride = $item.find('.service-price-input').val();
			var durationOverride = $item.find('.service-duration-input').val();

			if (!this.currentLocationId) {
				return;
			}

			$.ajax({
				url: bkxML.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_ml_save_service',
					nonce: bkxML.nonce,
					location_id: this.currentLocationId,
					base_id: baseId,
					is_available: isAvailable,
					price_override: priceOverride,
					duration_override: durationOverride
				}
			});
		}
	};

	$(document).ready(function() {
		BkxML.init();
	});

})(jQuery);

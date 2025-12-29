/**
 * Multi-Location Management Frontend JavaScript
 *
 * @package BookingX\MultiLocation
 */

(function($) {
	'use strict';

	var BkxMLFront = {
		selectedLocationId: null,

		init: function() {
			this.bindEvents();
			this.initMaps();
		},

		bindEvents: function() {
			// Location dropdown change
			$('.bkx-location-dropdown').on('change', this.onLocationSelect.bind(this));

			// Location link click
			$(document).on('click', '.bkx-location-link, .bkx-location-select-btn', this.onLocationClick.bind(this));

			// Book at location button
			$(document).on('click', '.bkx-book-at-location', this.onBookAtLocation.bind(this));

			// Find nearest location
			$('.bkx-find-nearest').on('click', this.findNearestLocation.bind(this));

			// Location card click
			$(document).on('click', '.bkx-location-card', function(e) {
				if (!$(e.target).is('.bkx-location-select-btn')) {
					$(this).find('.bkx-location-select-btn').click();
				}
			});
		},

		initMaps: function() {
			// Initialize selector map if present
			if ($('#bkx-location-selector-map').length && typeof google !== 'undefined' && window.bkxLocations) {
				this.initSelectorMap();
			}
		},

		initSelectorMap: function() {
			var locations = window.bkxLocations;

			if (!locations || !locations.length) {
				return;
			}

			var bounds = new google.maps.LatLngBounds();
			var map = new google.maps.Map(document.getElementById('bkx-location-selector-map'), {
				zoom: 10,
				center: { lat: locations[0].lat, lng: locations[0].lng }
			});

			var infoWindow = new google.maps.InfoWindow();
			var markers = {};

			locations.forEach(function(location) {
				if (!location.lat || !location.lng) {
					return;
				}

				var marker = new google.maps.Marker({
					position: { lat: location.lat, lng: location.lng },
					map: map,
					title: location.name
				});

				bounds.extend(marker.getPosition());
				markers[location.id] = marker;

				marker.addListener('click', function() {
					infoWindow.setContent('<div><strong>' + location.name + '</strong></div>');
					infoWindow.open(map, marker);

					// Select this location
					BkxMLFront.selectLocation(location.id);
				});
			});

			if (locations.length > 1) {
				map.fitBounds(bounds);
			}

			// Store for later use
			this.selectorMap = map;
			this.selectorMarkers = markers;
		},

		onLocationSelect: function(e) {
			var locationId = $(e.currentTarget).val();
			var redirect = $(e.currentTarget).data('redirect');

			if (locationId) {
				this.selectLocation(locationId);

				if (redirect) {
					window.location.href = redirect + (redirect.indexOf('?') > -1 ? '&' : '?') + 'location=' + locationId;
				}
			}
		},

		onLocationClick: function(e) {
			e.preventDefault();

			var locationId = $(e.currentTarget).data('id');
			this.selectLocation(locationId);

			var redirect = $(e.currentTarget).closest('.bkx-location-selector').find('[data-redirect]').data('redirect');
			if (redirect) {
				window.location.href = redirect + (redirect.indexOf('?') > -1 ? '&' : '?') + 'location=' + locationId;
			}
		},

		onBookAtLocation: function(e) {
			e.preventDefault();

			var locationId = $(e.currentTarget).data('location-id');
			this.selectLocation(locationId);

			// Store in session/cookie for booking form
			if (typeof sessionStorage !== 'undefined') {
				sessionStorage.setItem('bkx_selected_location', locationId);
			}

			// Check for booking page redirect
			var bookingPage = $(e.currentTarget).data('booking-page');
			if (bookingPage) {
				window.location.href = bookingPage + (bookingPage.indexOf('?') > -1 ? '&' : '?') + 'location=' + locationId;
			} else {
				// Trigger custom event for booking form integration
				$(document).trigger('bkx_location_selected', [locationId]);
			}
		},

		selectLocation: function(locationId) {
			this.selectedLocationId = locationId;

			// Update visual state
			$('.bkx-location-card, .bkx-location-item').removeClass('selected');
			$('.bkx-location-card[data-id="' + locationId + '"], .bkx-location-item[data-location-id="' + locationId + '"]').addClass('selected');

			// Update dropdown if exists
			$('.bkx-location-dropdown').val(locationId);

			// Update hidden form field if exists
			$('input[name="bkx_location"], select[name="bkx_location"]').val(locationId);

			// Center map on location if map exists
			if (this.selectorMarkers && this.selectorMarkers[locationId]) {
				this.selectorMap.setCenter(this.selectorMarkers[locationId].getPosition());
				this.selectorMap.setZoom(14);
			}

			// Store selection
			if (typeof sessionStorage !== 'undefined') {
				sessionStorage.setItem('bkx_selected_location', locationId);
			}

			// Trigger event for other scripts
			$(document).trigger('bkx_location_changed', [locationId]);
		},

		findNearestLocation: function(e) {
			e.preventDefault();

			var $btn = $(e.currentTarget);
			var $status = $btn.siblings('.bkx-geolocation-status');

			if (!navigator.geolocation) {
				$status.text('Geolocation is not supported by your browser.');
				return;
			}

			$btn.prop('disabled', true);
			$status.text('Getting your location...');

			navigator.geolocation.getCurrentPosition(
				function(position) {
					BkxMLFront.findNearestByCoords(position.coords.latitude, position.coords.longitude, $status, $btn);
				},
				function(error) {
					var message = 'Unable to get your location.';
					switch (error.code) {
						case error.PERMISSION_DENIED:
							message = 'Location access denied.';
							break;
						case error.POSITION_UNAVAILABLE:
							message = 'Location unavailable.';
							break;
						case error.TIMEOUT:
							message = 'Location request timed out.';
							break;
					}
					$status.text(message);
					$btn.prop('disabled', false);
				},
				{
					enableHighAccuracy: true,
					timeout: 10000,
					maximumAge: 300000
				}
			);
		},

		findNearestByCoords: function(lat, lng, $status, $btn) {
			// If we have locations data, calculate client-side
			if (window.bkxLocations && window.bkxLocations.length) {
				var nearest = null;
				var nearestDistance = Infinity;

				window.bkxLocations.forEach(function(location) {
					if (!location.lat || !location.lng) {
						return;
					}

					var distance = BkxMLFront.calculateDistance(lat, lng, location.lat, location.lng);

					if (distance < nearestDistance) {
						nearestDistance = distance;
						nearest = location;
					}
				});

				if (nearest) {
					$status.text('Nearest: ' + nearest.name + ' (' + nearestDistance.toFixed(1) + ' mi)');
					BkxMLFront.selectLocation(nearest.id);
				} else {
					$status.text('No locations found nearby.');
				}

				$btn.prop('disabled', false);
				return;
			}

			// Otherwise, query server
			$.ajax({
				url: bkxMLFront.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_ml_get_locations',
					nonce: bkxMLFront.nonce,
					lat: lat,
					lng: lng
				},
				success: function(response) {
					if (response.success && response.data.locations.length) {
						var nearest = null;
						var nearestDistance = Infinity;

						response.data.locations.forEach(function(location) {
							if (!location.latitude || !location.longitude) {
								return;
							}

							var distance = BkxMLFront.calculateDistance(lat, lng, parseFloat(location.latitude), parseFloat(location.longitude));

							if (distance < nearestDistance) {
								nearestDistance = distance;
								nearest = location;
							}
						});

						if (nearest) {
							$status.text('Nearest: ' + nearest.name + ' (' + nearestDistance.toFixed(1) + ' mi)');
							BkxMLFront.selectLocation(nearest.id);
						} else {
							$status.text('No locations found nearby.');
						}
					} else {
						$status.text('No locations found.');
					}
				},
				error: function() {
					$status.text('Error finding locations.');
				},
				complete: function() {
					$btn.prop('disabled', false);
				}
			});
		},

		calculateDistance: function(lat1, lon1, lat2, lon2) {
			// Haversine formula for distance in miles
			var R = 3959; // Earth's radius in miles
			var dLat = this.toRad(lat2 - lat1);
			var dLon = this.toRad(lon2 - lon1);
			var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
				Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) *
				Math.sin(dLon / 2) * Math.sin(dLon / 2);
			var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
			return R * c;
		},

		toRad: function(deg) {
			return deg * (Math.PI / 180);
		},

		// Public method to get selected location
		getSelectedLocation: function() {
			return this.selectedLocationId;
		},

		// Public method to load availability for a location
		loadAvailability: function(locationId, date, serviceId, callback) {
			$.ajax({
				url: bkxMLFront.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_ml_get_location_availability',
					nonce: bkxMLFront.nonce,
					location_id: locationId,
					date: date,
					service_id: serviceId || 0
				},
				success: function(response) {
					if (response.success && typeof callback === 'function') {
						callback(response.data);
					}
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		BkxMLFront.init();

		// Restore previous selection if any
		if (typeof sessionStorage !== 'undefined') {
			var savedLocation = sessionStorage.getItem('bkx_selected_location');
			if (savedLocation) {
				BkxMLFront.selectLocation(savedLocation);
			}
		}
	});

	// Expose for external use
	window.BkxMLFront = BkxMLFront;

})(jQuery);

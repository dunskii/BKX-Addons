/**
 * Mobile Bookings Frontend JavaScript.
 *
 * @package BookingX\MobileBookings
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Mobile Bookings Frontend Object.
	 */
	var BkxMobileFrontend = {

		/**
		 * Map instance.
		 */
		map: null,

		/**
		 * Markers.
		 */
		markers: {},

		/**
		 * Autocomplete instance.
		 */
		autocomplete: null,

		/**
		 * Customer location.
		 */
		customerLocation: null,

		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.initMaps();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Address autocomplete.
			$( document ).on( 'focus', '.bkx-address-input', this.initAutocomplete );

			// Use my location button.
			$( document ).on( 'click', '.bkx-location-btn', this.useMyLocation );

			// Address change.
			$( document ).on( 'blur', '.bkx-address-input', this.handleAddressChange );

			// GPS Check-in.
			$( document ).on( 'click', '.bkx-checkin-btn', this.handleCheckin );

			// Provider selection.
			$( document ).on( 'click', '.bkx-select-provider', this.selectProvider );
		},

		/**
		 * Initialize maps.
		 */
		initMaps: function() {
			if ( typeof google === 'undefined' ) {
				return;
			}

			var $mapContainer = $( '.bkx-booking-map' );

			if ( $mapContainer.length ) {
				var settings = bkxMobileBookingsFront.settings;

				this.map = new google.maps.Map( $mapContainer[0], {
					center: {
						lat: parseFloat( settings.default_center_lat ) || 40.7128,
						lng: parseFloat( settings.default_center_lng ) || -74.0060
					},
					zoom: parseInt( settings.default_zoom ) || 12,
					mapTypeId: 'roadmap',
					disableDefaultUI: false,
					zoomControl: true,
					mapTypeControl: false,
					streetViewControl: false
				} );
			}
		},

		/**
		 * Initialize autocomplete.
		 *
		 * @param {Event} e Focus event.
		 */
		initAutocomplete: function( e ) {
			if ( typeof google === 'undefined' || BkxMobileFrontend.autocomplete ) {
				return;
			}

			var input = e.target;

			BkxMobileFrontend.autocomplete = new google.maps.places.Autocomplete( input, {
				types: [ 'address' ]
			} );

			BkxMobileFrontend.autocomplete.addListener( 'place_changed', function() {
				var place = BkxMobileFrontend.autocomplete.getPlace();

				if ( place.geometry ) {
					BkxMobileFrontend.customerLocation = {
						lat: place.geometry.location.lat(),
						lng: place.geometry.location.lng(),
						address: place.formatted_address
					};

					BkxMobileFrontend.updateMapMarker();
					BkxMobileFrontend.checkServiceArea();
					BkxMobileFrontend.calculateDistance();
				}
			} );
		},

		/**
		 * Use my location.
		 *
		 * @param {Event} e Click event.
		 */
		useMyLocation: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var $input = $btn.siblings( '.bkx-address-input' );

			if ( ! navigator.geolocation ) {
				BkxMobileFrontend.showMessage( bkxMobileBookingsFront.strings.location_not_found, 'error' );
				return;
			}

			$btn.addClass( 'loading' );

			navigator.geolocation.getCurrentPosition(
				function( position ) {
					$btn.removeClass( 'loading' );

					BkxMobileFrontend.customerLocation = {
						lat: position.coords.latitude,
						lng: position.coords.longitude
					};

					// Reverse geocode to get address.
					BkxMobileFrontend.reverseGeocode(
						position.coords.latitude,
						position.coords.longitude,
						function( address ) {
							$input.val( address );
							BkxMobileFrontend.customerLocation.address = address;
							BkxMobileFrontend.updateMapMarker();
							BkxMobileFrontend.checkServiceArea();
							BkxMobileFrontend.calculateDistance();
						}
					);
				},
				function( error ) {
					$btn.removeClass( 'loading' );
					BkxMobileFrontend.showMessage( bkxMobileBookingsFront.strings.location_not_found, 'error' );
				},
				{
					enableHighAccuracy: true,
					timeout: 10000
				}
			);
		},

		/**
		 * Handle address change.
		 *
		 * @param {Event} e Blur event.
		 */
		handleAddressChange: function( e ) {
			var address = $( this ).val();

			if ( ! address || BkxMobileFrontend.customerLocation ) {
				return;
			}

			// Geocode the address.
			BkxMobileFrontend.geocodeAddress( address );
		},

		/**
		 * Geocode address.
		 *
		 * @param {string} address Address to geocode.
		 */
		geocodeAddress: function( address ) {
			$.ajax( {
				url: bkxMobileBookingsFront.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_geocode_address',
					nonce: bkxMobileBookingsFront.nonce,
					address: address
				},
				success: function( response ) {
					if ( response.success ) {
						BkxMobileFrontend.customerLocation = {
							lat: response.data.lat,
							lng: response.data.lng,
							address: response.data.formatted_address
						};

						BkxMobileFrontend.updateMapMarker();
						BkxMobileFrontend.checkServiceArea();
						BkxMobileFrontend.calculateDistance();
					}
				}
			} );
		},

		/**
		 * Reverse geocode coordinates.
		 *
		 * @param {number}   lat      Latitude.
		 * @param {number}   lng      Longitude.
		 * @param {Function} callback Callback function.
		 */
		reverseGeocode: function( lat, lng, callback ) {
			if ( typeof google === 'undefined' ) {
				callback( '' );
				return;
			}

			var geocoder = new google.maps.Geocoder();

			geocoder.geocode(
				{ location: { lat: lat, lng: lng } },
				function( results, status ) {
					if ( status === 'OK' && results[0] ) {
						callback( results[0].formatted_address );
					} else {
						callback( '' );
					}
				}
			);
		},

		/**
		 * Update map marker.
		 */
		updateMapMarker: function() {
			if ( ! this.map || ! this.customerLocation ) {
				return;
			}

			// Remove existing marker.
			if ( this.markers.customer ) {
				this.markers.customer.setMap( null );
			}

			// Add new marker.
			this.markers.customer = new google.maps.Marker( {
				position: {
					lat: this.customerLocation.lat,
					lng: this.customerLocation.lng
				},
				map: this.map,
				title: 'Your Location'
			} );

			// Center map.
			this.map.setCenter( {
				lat: this.customerLocation.lat,
				lng: this.customerLocation.lng
			} );
			this.map.setZoom( 14 );
		},

		/**
		 * Check service area.
		 */
		checkServiceArea: function() {
			if ( ! this.customerLocation ) {
				return;
			}

			var serviceId = $( 'input[name="service_id"]' ).val() || 0;
			var providerId = $( 'input[name="provider_id"]' ).val() || 0;

			$.ajax( {
				url: bkxMobileBookingsFront.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_check_service_area',
					nonce: bkxMobileBookingsFront.nonce,
					lat: this.customerLocation.lat,
					lng: this.customerLocation.lng,
					service_id: serviceId,
					provider_id: providerId
				},
				success: function( response ) {
					if ( response.success ) {
						BkxMobileFrontend.displayServiceAreaResult( response.data );
					}
				}
			} );
		},

		/**
		 * Display service area result.
		 *
		 * @param {Object} result Service area check result.
		 */
		displayServiceAreaResult: function( result ) {
			var $container = $( '.bkx-service-area-notice' );

			if ( ! $container.length ) {
				$container = $( '<div class="bkx-service-area-notice"></div>' );
				$( '.bkx-address-field' ).after( $container );
			}

			$container.removeClass( 'success error' );

			if ( result.in_service_area ) {
				$container.addClass( 'success' );
				$container.html(
					'<span class="dashicons dashicons-yes-alt"></span> ' +
					result.message
				);

				// Enable booking form.
				$( '.bkx-booking-submit' ).prop( 'disabled', false );
			} else {
				$container.addClass( 'error' );
				$container.html(
					'<span class="dashicons dashicons-dismiss"></span> ' +
					result.message
				);

				// Disable booking form if enforced.
				if ( result.is_enforced ) {
					$( '.bkx-booking-submit' ).prop( 'disabled', true );
				}
			}
		},

		/**
		 * Calculate distance and show pricing.
		 */
		calculateDistance: function() {
			if ( ! this.customerLocation ) {
				return;
			}

			var providerId = $( 'input[name="provider_id"]' ).val() || 0;

			// Get provider location from data attribute or hidden field.
			var providerLat = $( '#provider_lat' ).val();
			var providerLng = $( '#provider_lng' ).val();

			if ( ! providerLat || ! providerLng ) {
				return;
			}

			$( '.bkx-distance-info' ).addClass( 'loading' );

			$.ajax( {
				url: bkxMobileBookingsFront.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_calculate_distance',
					nonce: bkxMobileBookingsFront.nonce,
					from_lat: providerLat,
					from_lng: providerLng,
					to_lat: this.customerLocation.lat,
					to_lng: this.customerLocation.lng
				},
				success: function( response ) {
					$( '.bkx-distance-info' ).removeClass( 'loading' );

					if ( response.success ) {
						BkxMobileFrontend.displayDistanceInfo( response.data );
					}
				},
				error: function() {
					$( '.bkx-distance-info' ).removeClass( 'loading' );
				}
			} );
		},

		/**
		 * Display distance information.
		 *
		 * @param {Object} data Distance data.
		 */
		displayDistanceInfo: function( data ) {
			var $container = $( '.bkx-distance-info' );

			if ( ! $container.length ) {
				$container = $( '<div class="bkx-distance-info"></div>' );
				$( '.bkx-service-area-notice' ).after( $container );
			}

			var html = '<div class="bkx-distance-row">' +
				'<span class="bkx-distance-label">' + 'Distance:' + '</span>' +
				'<span class="bkx-distance-value">' + data.distance_text + '</span>' +
			'</div>';

			html += '<div class="bkx-distance-row">' +
				'<span class="bkx-distance-label">' + 'Travel Time:' + '</span>' +
				'<span class="bkx-distance-value">' + data.duration_text + '</span>' +
			'</div>';

			if ( data.duration_in_traffic_text ) {
				html += '<div class="bkx-distance-row">' +
					'<span class="bkx-distance-label">' + 'With Traffic:' + '</span>' +
					'<span class="bkx-distance-value">' + data.duration_in_traffic_text + '</span>' +
				'</div>';
			}

			$container.html( html );

			// Store distance for form submission.
			$( 'input[name="distance_miles"]' ).val( data.distance_miles );
			$( 'input[name="travel_duration"]' ).val( data.duration_minutes );
		},

		/**
		 * Handle GPS check-in.
		 *
		 * @param {Event} e Click event.
		 */
		handleCheckin: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var bookingId = $btn.data( 'booking-id' );
			var checkinType = $btn.data( 'type' ) || 'arrival';

			if ( ! navigator.geolocation ) {
				BkxMobileFrontend.showMessage( 'GPS is not available on this device.', 'error' );
				return;
			}

			$btn.prop( 'disabled', true ).text( 'Getting location...' );

			navigator.geolocation.getCurrentPosition(
				function( position ) {
					BkxMobileFrontend.submitCheckin(
						bookingId,
						position.coords.latitude,
						position.coords.longitude,
						position.coords.accuracy,
						checkinType,
						$btn
					);
				},
				function( error ) {
					$btn.prop( 'disabled', false ).text( 'Check In' );
					BkxMobileFrontend.showMessage( 'Unable to get your location. Please enable GPS.', 'error' );
				},
				{
					enableHighAccuracy: true,
					timeout: 15000
				}
			);
		},

		/**
		 * Submit check-in.
		 *
		 * @param {number} bookingId   Booking ID.
		 * @param {number} lat         Latitude.
		 * @param {number} lng         Longitude.
		 * @param {number} accuracy    GPS accuracy.
		 * @param {string} checkinType Check-in type.
		 * @param {jQuery} $btn        Button element.
		 */
		submitCheckin: function( bookingId, lat, lng, accuracy, checkinType, $btn ) {
			$btn.text( 'Verifying location...' );

			$.ajax( {
				url: bkxMobileBookingsFront.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_gps_checkin',
					nonce: bkxMobileBookingsFront.nonce,
					booking_id: bookingId,
					lat: lat,
					lng: lng,
					accuracy: accuracy,
					checkin_type: checkinType
				},
				success: function( response ) {
					$btn.prop( 'disabled', false );

					if ( response.success ) {
						BkxMobileFrontend.displayCheckinResult( response.data, $btn );
					} else {
						$btn.text( 'Check In' );
						BkxMobileFrontend.showMessage( response.data.message, 'error' );
					}
				},
				error: function() {
					$btn.prop( 'disabled', false ).text( 'Check In' );
					BkxMobileFrontend.showMessage( 'Failed to record check-in. Please try again.', 'error' );
				}
			} );
		},

		/**
		 * Display check-in result.
		 *
		 * @param {Object} result Check-in result.
		 * @param {jQuery} $btn   Button element.
		 */
		displayCheckinResult: function( result, $btn ) {
			var $container = $btn.closest( '.bkx-checkin-container' );

			$btn.hide();

			var statusClass = result.is_verified ? 'verified' : 'unverified';
			var statusTitle = result.is_verified ? 'Verified Check-in' : 'Check-in Recorded';
			var statusMessage = result.is_verified
				? 'Your location has been verified.'
				: 'Location recorded but outside verification radius (' + result.distance_from_location + 'm away).';

			var $status = $( '<div class="bkx-checkin-status ' + statusClass + '">' +
				'<h4>' + statusTitle + '</h4>' +
				'<p>' + statusMessage + '</p>' +
			'</div>' );

			$container.append( $status );
		},

		/**
		 * Select provider.
		 *
		 * @param {Event} e Click event.
		 */
		selectProvider: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var providerId = $btn.data( 'provider-id' );
			var providerName = $btn.closest( '.bkx-provider-item' ).find( '.bkx-provider-name' ).text();

			// Update hidden input.
			$( 'input[name="provider_id"]' ).val( providerId );

			// Highlight selected.
			$( '.bkx-provider-item' ).removeClass( 'selected' );
			$btn.closest( '.bkx-provider-item' ).addClass( 'selected' );

			// Trigger event.
			$( document ).trigger( 'bkx_provider_selected', [ providerId, providerName ] );
		},

		/**
		 * Load nearby providers.
		 *
		 * @param {number} lat       Latitude.
		 * @param {number} lng       Longitude.
		 * @param {number} serviceId Service ID.
		 */
		loadNearbyProviders: function( lat, lng, serviceId ) {
			var $container = $( '.bkx-nearby-providers' );

			if ( ! $container.length ) {
				return;
			}

			$container.html( '<div class="bkx-loading"><span class="bkx-loading-spinner"></span> Finding providers...</div>' );

			$.ajax( {
				url: bkxMobileBookingsFront.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_get_nearby_providers',
					nonce: bkxMobileBookingsFront.nonce,
					lat: lat,
					lng: lng,
					service_id: serviceId
				},
				success: function( response ) {
					if ( response.success && response.data.providers.length > 0 ) {
						BkxMobileFrontend.displayNearbyProviders( response.data.providers );
					} else {
						$container.html( '<p>No providers found nearby.</p>' );
					}
				},
				error: function() {
					$container.html( '<p>Failed to load providers.</p>' );
				}
			} );
		},

		/**
		 * Display nearby providers.
		 *
		 * @param {Array} providers Array of providers.
		 */
		displayNearbyProviders: function( providers ) {
			var $container = $( '.bkx-nearby-providers' );
			var html = '<h4>Nearby Providers</h4><ul class="bkx-provider-list">';

			providers.forEach( function( provider ) {
				html += '<li class="bkx-provider-item">';

				if ( provider.avatar_url ) {
					html += '<img src="' + provider.avatar_url + '" alt="" class="bkx-provider-avatar" />';
				}

				html += '<div class="bkx-provider-info">' +
					'<div class="bkx-provider-name">' + provider.provider_name + '</div>' +
					'<div class="bkx-provider-distance">' + provider.distance_text + '</div>' +
					'<div class="bkx-provider-eta">ETA: ' + provider.eta_text + '</div>' +
				'</div>';

				html += '<button type="button" class="button bkx-select-provider" data-provider-id="' + provider.provider_id + '">Select</button>';
				html += '</li>';
			} );

			html += '</ul>';
			$container.html( html );
		},

		/**
		 * Show message.
		 *
		 * @param {string} message Message text.
		 * @param {string} type    Message type.
		 */
		showMessage: function( message, type ) {
			var $message = $( '<div class="bkx-service-area-notice ' + type + '">' + message + '</div>' );
			$( '.bkx-address-field' ).after( $message );

			setTimeout( function() {
				$message.fadeOut( 300, function() {
					$( this ).remove();
				} );
			}, 5000 );
		}
	};

	// Initialize on document ready.
	$( document ).ready( function() {
		BkxMobileFrontend.init();
	} );

} )( jQuery );

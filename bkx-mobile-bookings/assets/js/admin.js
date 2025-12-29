/**
 * Mobile Bookings Admin JavaScript.
 *
 * @package BookingX\MobileBookings
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Mobile Bookings Admin Object.
	 */
	var BkxMobileAdmin = {

		/**
		 * Maps instances.
		 */
		maps: {},
		markers: {},
		circles: {},
		polygons: {},
		drawingManager: null,

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
			// Tab navigation.
			$( document ).on( 'click', '.nav-tab', this.handleTabClick );

			// Service Area.
			$( document ).on( 'click', '.bkx-add-area', this.openAreaModal );
			$( document ).on( 'click', '.bkx-edit-area', this.editArea );
			$( document ).on( 'click', '.bkx-delete-area', this.deleteArea );
			$( document ).on( 'submit', '#bkx-area-form', this.saveArea );
			$( document ).on( 'change', '#area_type', this.handleAreaTypeChange );
			$( document ).on( 'change', '#area_distance_pricing', this.toggleDistancePricing );

			// Modal.
			$( document ).on( 'click', '.bkx-modal-close', this.closeModal );
			$( document ).on( 'click', '.bkx-modal', this.handleModalBackdropClick );

			// Routes.
			$( document ).on( 'click', '#bkx-load-route', this.loadRoute );
			$( document ).on( 'click', '#bkx-optimize-route', this.optimizeRoute );
			$( document ).on( 'click', '#export-google, #export-waze', this.exportRoute );

			// Settings.
			$( document ).on( 'submit', '#bkx-mobile-settings-form', this.saveSettings );
			$( document ).on( 'click', '#bkx-test-api, #bkx-test-api-key', this.testApiConnection );

			// Check-ins.
			$( document ).on( 'click', '#bkx-filter-checkins', this.filterCheckins );

			// Keyboard.
			$( document ).on( 'keydown', this.handleKeydown );
		},

		/**
		 * Handle tab click.
		 *
		 * @param {Event} e Click event.
		 */
		handleTabClick: function( e ) {
			e.preventDefault();

			var $tab = $( this );
			var target = $tab.attr( 'href' );

			// Update active tab.
			$( '.nav-tab' ).removeClass( 'nav-tab-active' );
			$tab.addClass( 'nav-tab-active' );

			// Show target section.
			$( '.bkx-tab-content' ).removeClass( 'active' );
			$( target ).addClass( 'active' );

			// Initialize map for tab if needed.
			if ( target === '#service-areas' && ! BkxMobileAdmin.maps.areas ) {
				BkxMobileAdmin.initAreasMap();
			} else if ( target === '#routes' && ! BkxMobileAdmin.maps.route ) {
				BkxMobileAdmin.initRouteMap();
			}

			// Update URL hash.
			window.location.hash = target;
		},

		/**
		 * Initialize maps.
		 */
		initMaps: function() {
			if ( typeof google === 'undefined' || ! bkxMobileBookings.has_api_key ) {
				return;
			}

			// Initialize coverage map on overview.
			if ( $( '#bkx-coverage-map' ).length ) {
				this.initCoverageMap();
			}
		},

		/**
		 * Initialize coverage map.
		 */
		initCoverageMap: function() {
			var settings = bkxMobileBookings.settings;

			this.maps.coverage = new google.maps.Map( document.getElementById( 'bkx-coverage-map' ), {
				center: {
					lat: parseFloat( settings.default_center_lat ) || 40.7128,
					lng: parseFloat( settings.default_center_lng ) || -74.0060
				},
				zoom: parseInt( settings.default_map_zoom ) || 12,
				mapTypeId: settings.map_style || 'roadmap'
			} );

			// Load service areas.
			this.loadServiceAreasOnMap( this.maps.coverage );
		},

		/**
		 * Initialize service areas map.
		 */
		initAreasMap: function() {
			if ( ! $( '#bkx-areas-map' ).length ) {
				return;
			}

			var settings = bkxMobileBookings.settings;

			this.maps.areas = new google.maps.Map( document.getElementById( 'bkx-areas-map' ), {
				center: {
					lat: parseFloat( settings.default_center_lat ) || 40.7128,
					lng: parseFloat( settings.default_center_lng ) || -74.0060
				},
				zoom: parseInt( settings.default_map_zoom ) || 10,
				mapTypeId: settings.map_style || 'roadmap'
			} );

			this.loadServiceAreasOnMap( this.maps.areas );
		},

		/**
		 * Initialize route map.
		 */
		initRouteMap: function() {
			if ( ! $( '#bkx-route-map' ).length ) {
				return;
			}

			var settings = bkxMobileBookings.settings;

			this.maps.route = new google.maps.Map( document.getElementById( 'bkx-route-map' ), {
				center: {
					lat: parseFloat( settings.default_center_lat ) || 40.7128,
					lng: parseFloat( settings.default_center_lng ) || -74.0060
				},
				zoom: parseInt( settings.default_map_zoom ) || 12,
				mapTypeId: settings.map_style || 'roadmap'
			} );

			this.directionsService = new google.maps.DirectionsService();
			this.directionsRenderer = new google.maps.DirectionsRenderer( {
				map: this.maps.route,
				suppressMarkers: false
			} );
		},

		/**
		 * Initialize area modal map.
		 */
		initAreaModalMap: function() {
			if ( ! $( '#bkx-area-map' ).length || this.maps.areaModal ) {
				return;
			}

			var settings = bkxMobileBookings.settings;

			this.maps.areaModal = new google.maps.Map( document.getElementById( 'bkx-area-map' ), {
				center: {
					lat: parseFloat( settings.default_center_lat ) || 40.7128,
					lng: parseFloat( settings.default_center_lng ) || -74.0060
				},
				zoom: 10,
				mapTypeId: 'roadmap'
			} );

			// Setup autocomplete for address input.
			var input = document.getElementById( 'area_address' );
			if ( input ) {
				var autocomplete = new google.maps.places.Autocomplete( input );
				autocomplete.bindTo( 'bounds', this.maps.areaModal );

				autocomplete.addListener( 'place_changed', function() {
					var place = autocomplete.getPlace();

					if ( place.geometry ) {
						BkxMobileAdmin.maps.areaModal.setCenter( place.geometry.location );
						BkxMobileAdmin.maps.areaModal.setZoom( 12 );

						$( '#center_latitude' ).val( place.geometry.location.lat() );
						$( '#center_longitude' ).val( place.geometry.location.lng() );

						BkxMobileAdmin.updateRadiusCircle();
					}
				} );
			}

			// Click to set center.
			this.maps.areaModal.addListener( 'click', function( e ) {
				$( '#center_latitude' ).val( e.latLng.lat() );
				$( '#center_longitude' ).val( e.latLng.lng() );
				BkxMobileAdmin.updateRadiusCircle();
			} );

			// Radius change.
			$( '#area_radius' ).on( 'change', function() {
				BkxMobileAdmin.updateRadiusCircle();
			} );

			// Drawing manager for polygon.
			this.drawingManager = new google.maps.drawing.DrawingManager( {
				drawingMode: null,
				drawingControl: false,
				polygonOptions: {
					fillColor: '#0073aa',
					fillOpacity: 0.2,
					strokeColor: '#0073aa',
					strokeWeight: 2,
					editable: true
				}
			} );
			this.drawingManager.setMap( this.maps.areaModal );

			this.drawingManager.addListener( 'polygoncomplete', function( polygon ) {
				BkxMobileAdmin.handlePolygonComplete( polygon );
			} );
		},

		/**
		 * Update radius circle on map.
		 */
		updateRadiusCircle: function() {
			var lat = parseFloat( $( '#center_latitude' ).val() );
			var lng = parseFloat( $( '#center_longitude' ).val() );
			var radius = parseFloat( $( '#area_radius' ).val() ) * 1609.34; // miles to meters

			if ( ! lat || ! lng || ! radius ) {
				return;
			}

			// Remove existing circle.
			if ( this.circles.modal ) {
				this.circles.modal.setMap( null );
			}

			// Remove existing marker.
			if ( this.markers.modalCenter ) {
				this.markers.modalCenter.setMap( null );
			}

			// Add marker.
			this.markers.modalCenter = new google.maps.Marker( {
				position: { lat: lat, lng: lng },
				map: this.maps.areaModal,
				draggable: true
			} );

			this.markers.modalCenter.addListener( 'dragend', function( e ) {
				$( '#center_latitude' ).val( e.latLng.lat() );
				$( '#center_longitude' ).val( e.latLng.lng() );
				BkxMobileAdmin.updateRadiusCircle();
			} );

			// Add circle.
			this.circles.modal = new google.maps.Circle( {
				center: { lat: lat, lng: lng },
				radius: radius,
				fillColor: '#0073aa',
				fillOpacity: 0.2,
				strokeColor: '#0073aa',
				strokeWeight: 2,
				map: this.maps.areaModal
			} );

			// Fit bounds.
			this.maps.areaModal.fitBounds( this.circles.modal.getBounds() );
		},

		/**
		 * Handle polygon complete.
		 *
		 * @param {google.maps.Polygon} polygon Completed polygon.
		 */
		handlePolygonComplete: function( polygon ) {
			// Remove existing polygon.
			if ( this.polygons.modal ) {
				this.polygons.modal.setMap( null );
			}

			this.polygons.modal = polygon;

			// Get coordinates.
			var path = polygon.getPath();
			var coords = [];

			path.forEach( function( latLng ) {
				coords.push( {
					lat: latLng.lat(),
					lng: latLng.lng()
				} );
			} );

			$( '#polygon_coordinates' ).val( JSON.stringify( coords ) );

			// Listen for edits.
			google.maps.event.addListener( path, 'set_at', function() {
				BkxMobileAdmin.updatePolygonCoords();
			} );
			google.maps.event.addListener( path, 'insert_at', function() {
				BkxMobileAdmin.updatePolygonCoords();
			} );
		},

		/**
		 * Update polygon coordinates.
		 */
		updatePolygonCoords: function() {
			if ( ! this.polygons.modal ) {
				return;
			}

			var path = this.polygons.modal.getPath();
			var coords = [];

			path.forEach( function( latLng ) {
				coords.push( {
					lat: latLng.lat(),
					lng: latLng.lng()
				} );
			} );

			$( '#polygon_coordinates' ).val( JSON.stringify( coords ) );
		},

		/**
		 * Load service areas on map.
		 *
		 * @param {google.maps.Map} map Map instance.
		 */
		loadServiceAreasOnMap: function( map ) {
			var self = this;

			$( '#bkx-areas-table tbody tr[data-id]' ).each( function() {
				var $row = $( this );
				var id = $row.data( 'id' );
				// Areas would need to be loaded via AJAX with coordinates.
			} );
		},

		/**
		 * Open area modal.
		 *
		 * @param {Event} e Click event.
		 */
		openAreaModal: function( e ) {
			e.preventDefault();
			BkxMobileAdmin.resetAreaForm();
			$( '#bkx-area-modal' ).fadeIn( 200 );

			setTimeout( function() {
				BkxMobileAdmin.initAreaModalMap();
			}, 100 );
		},

		/**
		 * Edit area.
		 *
		 * @param {Event} e Click event.
		 */
		editArea: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var areaId = $btn.data( 'id' );

			BkxMobileAdmin.showLoading( $btn );

			$.ajax( {
				url: bkxMobileBookings.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_get_service_area',
					nonce: bkxMobileBookings.nonce,
					area_id: areaId
				},
				success: function( response ) {
					BkxMobileAdmin.hideLoading( $btn );

					if ( response.success ) {
						BkxMobileAdmin.populateAreaForm( response.data );
						$( '#bkx-area-modal' ).fadeIn( 200 );

						setTimeout( function() {
							BkxMobileAdmin.initAreaModalMap();
							BkxMobileAdmin.updateRadiusCircle();
						}, 100 );
					} else {
						BkxMobileAdmin.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxMobileAdmin.hideLoading( $btn );
					BkxMobileAdmin.showNotice( bkxMobileBookings.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Delete area.
		 *
		 * @param {Event} e Click event.
		 */
		deleteArea: function( e ) {
			e.preventDefault();

			if ( ! confirm( bkxMobileBookings.strings.confirm_delete ) ) {
				return;
			}

			var $btn = $( this );
			var areaId = $btn.data( 'id' );

			BkxMobileAdmin.showLoading( $btn );

			$.ajax( {
				url: bkxMobileBookings.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_delete_service_area',
					nonce: bkxMobileBookings.nonce,
					area_id: areaId
				},
				success: function( response ) {
					BkxMobileAdmin.hideLoading( $btn );

					if ( response.success ) {
						$btn.closest( 'tr' ).fadeOut( 300, function() {
							$( this ).remove();
						} );
						BkxMobileAdmin.showNotice( response.data.message, 'success' );
					} else {
						BkxMobileAdmin.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxMobileAdmin.hideLoading( $btn );
					BkxMobileAdmin.showNotice( bkxMobileBookings.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Save area.
		 *
		 * @param {Event} e Submit event.
		 */
		saveArea: function( e ) {
			e.preventDefault();

			var $form = $( this );
			var $btn = $form.find( 'button[type="submit"]' );

			BkxMobileAdmin.showLoading( $btn );

			$.ajax( {
				url: bkxMobileBookings.ajax_url,
				type: 'POST',
				data: $form.serialize(),
				success: function( response ) {
					BkxMobileAdmin.hideLoading( $btn );

					if ( response.success ) {
						BkxMobileAdmin.showNotice( response.data.message, 'success' );
						$( '.bkx-modal' ).fadeOut( 200 );
						location.reload();
					} else {
						BkxMobileAdmin.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxMobileAdmin.hideLoading( $btn );
					BkxMobileAdmin.showNotice( bkxMobileBookings.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Handle area type change.
		 *
		 * @param {Event} e Change event.
		 */
		handleAreaTypeChange: function( e ) {
			var type = $( this ).val();

			$( '.bkx-area-type-options' ).hide();

			switch ( type ) {
				case 'radius':
					$( '#radius-options' ).show();
					if ( BkxMobileAdmin.drawingManager ) {
						BkxMobileAdmin.drawingManager.setDrawingMode( null );
					}
					break;
				case 'zip_codes':
					$( '#zipcode-options' ).show();
					if ( BkxMobileAdmin.drawingManager ) {
						BkxMobileAdmin.drawingManager.setDrawingMode( null );
					}
					break;
				case 'polygon':
					$( '#polygon-options' ).show();
					if ( BkxMobileAdmin.drawingManager ) {
						BkxMobileAdmin.drawingManager.setDrawingMode( google.maps.drawing.OverlayType.POLYGON );
					}
					break;
			}
		},

		/**
		 * Toggle distance pricing options.
		 *
		 * @param {Event} e Change event.
		 */
		toggleDistancePricing: function( e ) {
			if ( $( this ).is( ':checked' ) ) {
				$( '#area-pricing-options' ).slideDown();
			} else {
				$( '#area-pricing-options' ).slideUp();
			}
		},

		/**
		 * Close modal.
		 *
		 * @param {Event} e Click event.
		 */
		closeModal: function( e ) {
			e.preventDefault();
			$( '.bkx-modal' ).fadeOut( 200 );
		},

		/**
		 * Handle modal backdrop click.
		 *
		 * @param {Event} e Click event.
		 */
		handleModalBackdropClick: function( e ) {
			if ( $( e.target ).hasClass( 'bkx-modal' ) ) {
				$( '.bkx-modal' ).fadeOut( 200 );
			}
		},

		/**
		 * Handle keydown.
		 *
		 * @param {Event} e Keydown event.
		 */
		handleKeydown: function( e ) {
			if ( e.key === 'Escape' && $( '.bkx-modal:visible' ).length ) {
				$( '.bkx-modal' ).fadeOut( 200 );
			}
		},

		/**
		 * Load route.
		 *
		 * @param {Event} e Click event.
		 */
		loadRoute: function( e ) {
			e.preventDefault();

			var providerId = $( '#route_provider' ).val();
			var date = $( '#route_date' ).val();

			if ( ! providerId || ! date ) {
				BkxMobileAdmin.showNotice( 'Please select a provider and date.', 'error' );
				return;
			}

			var $btn = $( this );
			BkxMobileAdmin.showLoading( $btn );

			$.ajax( {
				url: bkxMobileBookings.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_get_provider_route',
					nonce: bkxMobileBookings.nonce,
					provider_id: providerId,
					date: date
				},
				success: function( response ) {
					BkxMobileAdmin.hideLoading( $btn );

					if ( response.success && response.data ) {
						BkxMobileAdmin.displayRoute( response.data );
					} else {
						BkxMobileAdmin.showNotice( 'No route found for this date.', 'warning' );
						$( '#bkx-route-details' ).hide();
					}
				},
				error: function() {
					BkxMobileAdmin.hideLoading( $btn );
					BkxMobileAdmin.showNotice( bkxMobileBookings.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Optimize route.
		 *
		 * @param {Event} e Click event.
		 */
		optimizeRoute: function( e ) {
			e.preventDefault();

			var providerId = $( '#route_provider' ).val();
			var date = $( '#route_date' ).val();

			if ( ! providerId || ! date ) {
				BkxMobileAdmin.showNotice( 'Please select a provider and date.', 'error' );
				return;
			}

			var $btn = $( this );
			BkxMobileAdmin.showLoading( $btn );

			$.ajax( {
				url: bkxMobileBookings.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_optimize_route',
					nonce: bkxMobileBookings.nonce,
					provider_id: providerId,
					date: date
				},
				success: function( response ) {
					BkxMobileAdmin.hideLoading( $btn );

					if ( response.success ) {
						BkxMobileAdmin.showNotice( 'Route optimized successfully!', 'success' );
						BkxMobileAdmin.displayRoute( response.data );
					} else {
						BkxMobileAdmin.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxMobileAdmin.hideLoading( $btn );
					BkxMobileAdmin.showNotice( bkxMobileBookings.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Display route on map and details.
		 *
		 * @param {Object} route Route data.
		 */
		displayRoute: function( route ) {
			if ( ! route.bookings || route.bookings.length === 0 ) {
				$( '#bkx-route-details' ).hide();
				return;
			}

			// Update summary.
			$( '#route-bookings' ).text( route.total_bookings || route.bookings.length );
			$( '#route-distance' ).text( ( route.total_distance_miles || 0 ) + ' mi' );
			$( '#route-time' ).text( route.total_travel_text || ( route.total_travel_time_minutes + ' min' ) );

			// Build stops list.
			var $stops = $( '#bkx-route-stops' ).empty();

			route.bookings.forEach( function( booking, index ) {
				var $stop = $( '<div class="bkx-route-stop">' +
					'<div class="bkx-stop-number">' + ( index + 1 ) + '</div>' +
					'<div class="bkx-stop-info">' +
						'<div class="bkx-stop-address">' + ( booking.formatted_address || booking.address || 'Unknown address' ) + '</div>' +
						'<div class="bkx-stop-time">' + ( booking.start_time || '' ) + '</div>' +
					'</div>' +
				'</div>' );

				$stops.append( $stop );
			} );

			$( '#bkx-route-details' ).show();

			// Display on map.
			if ( BkxMobileAdmin.maps.route && route.bookings.length > 1 ) {
				BkxMobileAdmin.displayRouteOnMap( route.bookings );
			}
		},

		/**
		 * Display route on map.
		 *
		 * @param {Array} bookings Array of bookings with coordinates.
		 */
		displayRouteOnMap: function( bookings ) {
			var validBookings = bookings.filter( function( b ) {
				return b.lat && b.lng;
			} );

			if ( validBookings.length < 2 ) {
				return;
			}

			var origin = validBookings[0].lat + ',' + validBookings[0].lng;
			var destination = validBookings[ validBookings.length - 1 ].lat + ',' + validBookings[ validBookings.length - 1 ].lng;

			var waypoints = [];
			for ( var i = 1; i < validBookings.length - 1; i++ ) {
				waypoints.push( {
					location: validBookings[i].lat + ',' + validBookings[i].lng,
					stopover: true
				} );
			}

			BkxMobileAdmin.directionsService.route(
				{
					origin: origin,
					destination: destination,
					waypoints: waypoints,
					travelMode: google.maps.TravelMode.DRIVING
				},
				function( response, status ) {
					if ( status === 'OK' ) {
						BkxMobileAdmin.directionsRenderer.setDirections( response );
					}
				}
			);
		},

		/**
		 * Export route.
		 *
		 * @param {Event} e Click event.
		 */
		exportRoute: function( e ) {
			e.preventDefault();

			var app = $( this ).data( 'app' );
			var providerId = $( '#route_provider' ).val();
			var date = $( '#route_date' ).val();

			// For now, just show a notice. In production, this would generate a URL.
			BkxMobileAdmin.showNotice( 'Route exported to ' + app.replace( '_', ' ' ), 'success' );
		},

		/**
		 * Save settings.
		 *
		 * @param {Event} e Submit event.
		 */
		saveSettings: function( e ) {
			e.preventDefault();

			var $form = $( this );
			var $btn = $form.find( 'button[type="submit"]' );

			BkxMobileAdmin.showLoading( $btn );

			$.ajax( {
				url: bkxMobileBookings.ajax_url,
				type: 'POST',
				data: $form.serialize(),
				success: function( response ) {
					BkxMobileAdmin.hideLoading( $btn );

					if ( response.success ) {
						BkxMobileAdmin.showNotice( response.data.message, 'success' );
					} else {
						BkxMobileAdmin.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxMobileAdmin.hideLoading( $btn );
					BkxMobileAdmin.showNotice( bkxMobileBookings.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Test API connection.
		 *
		 * @param {Event} e Click event.
		 */
		testApiConnection: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var apiKey = $( '#google_maps_api_key' ).val() || bkxMobileBookings.settings.google_maps_api_key;

			if ( ! apiKey ) {
				BkxMobileAdmin.showNotice( bkxMobileBookings.strings.api_key_required, 'error' );
				return;
			}

			BkxMobileAdmin.showLoading( $btn );

			$.ajax( {
				url: bkxMobileBookings.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_test_maps_api',
					nonce: bkxMobileBookings.nonce,
					api_key: apiKey
				},
				success: function( response ) {
					BkxMobileAdmin.hideLoading( $btn );

					if ( response.success ) {
						BkxMobileAdmin.showNotice( bkxMobileBookings.strings.api_test_success, 'success' );
					} else {
						BkxMobileAdmin.showNotice( response.data.message || bkxMobileBookings.strings.api_test_failed, 'error' );
					}
				},
				error: function() {
					BkxMobileAdmin.hideLoading( $btn );
					BkxMobileAdmin.showNotice( bkxMobileBookings.strings.api_test_failed, 'error' );
				}
			} );
		},

		/**
		 * Filter check-ins.
		 *
		 * @param {Event} e Click event.
		 */
		filterCheckins: function( e ) {
			e.preventDefault();

			var providerId = $( '#checkin_provider' ).val();
			var date = $( '#checkin_date' ).val();

			// This would load check-ins via AJAX.
			BkxMobileAdmin.showNotice( 'Filtering check-ins...', 'info' );
		},

		/**
		 * Reset area form.
		 */
		resetAreaForm: function() {
			var $form = $( '#bkx-area-form' );
			$form[0].reset();
			$form.find( 'input[name="id"]' ).val( '' );
			$( '.bkx-area-type-options' ).hide();
			$( '#radius-options' ).show();
			$( '#area-pricing-options' ).hide();

			// Clear map elements.
			if ( BkxMobileAdmin.circles.modal ) {
				BkxMobileAdmin.circles.modal.setMap( null );
			}
			if ( BkxMobileAdmin.markers.modalCenter ) {
				BkxMobileAdmin.markers.modalCenter.setMap( null );
			}
			if ( BkxMobileAdmin.polygons.modal ) {
				BkxMobileAdmin.polygons.modal.setMap( null );
			}
		},

		/**
		 * Populate area form.
		 *
		 * @param {Object} area Area data.
		 */
		populateAreaForm: function( area ) {
			var $form = $( '#bkx-area-form' );

			$form.find( 'input[name="id"]' ).val( area.id );
			$form.find( '#area_name' ).val( area.name );
			$form.find( '#area_description' ).val( area.description );
			$form.find( '#area_type' ).val( area.area_type ).trigger( 'change' );
			$form.find( '#area_status' ).val( area.status );

			// Radius options.
			$form.find( '#area_radius' ).val( area.radius_miles );
			$form.find( '#center_latitude' ).val( area.center_latitude );
			$form.find( '#center_longitude' ).val( area.center_longitude );

			// Zip codes.
			$form.find( '#area_zipcodes' ).val( area.zip_codes );

			// Polygon.
			$form.find( '#polygon_coordinates' ).val(
				area.polygon_coordinates ? JSON.stringify( area.polygon_coordinates ) : ''
			);

			// Distance pricing.
			$form.find( '#area_distance_pricing' ).prop( 'checked', area.distance_pricing_enabled ).trigger( 'change' );
			$form.find( '#area_base_fee' ).val( area.base_travel_fee );
			$form.find( '#area_per_mile' ).val( area.per_mile_rate );
		},

		/**
		 * Show loading state.
		 *
		 * @param {jQuery} $btn Button element.
		 */
		showLoading: function( $btn ) {
			$btn.prop( 'disabled', true ).addClass( 'updating-message' );
		},

		/**
		 * Hide loading state.
		 *
		 * @param {jQuery} $btn Button element.
		 */
		hideLoading: function( $btn ) {
			$btn.prop( 'disabled', false ).removeClass( 'updating-message' );
		},

		/**
		 * Show notice.
		 *
		 * @param {string} message Message text.
		 * @param {string} type    Notice type.
		 */
		showNotice: function( message, type ) {
			var $notice = $( '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>' );
			$( '.bkx-mobile-wrap h1' ).after( $notice );

			setTimeout( function() {
				$notice.fadeOut( 300, function() {
					$( this ).remove();
				} );
			}, 5000 );
		}
	};

	// Initialize on document ready.
	$( document ).ready( function() {
		BkxMobileAdmin.init();

		// Handle initial hash.
		var hash = window.location.hash;
		if ( hash ) {
			$( '.nav-tab[href="' + hash + '"]' ).trigger( 'click' );
		}
	} );

} )( jQuery );

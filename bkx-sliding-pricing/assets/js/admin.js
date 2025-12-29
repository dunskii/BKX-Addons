/**
 * Sliding Pricing Admin JavaScript.
 *
 * @package BookingX\SlidingPricing
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Sliding Pricing Admin Object.
	 */
	var BkxSlidingPricing = {

		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.initSortable();
			this.initHeatmap();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Tab navigation.
			$( document ).on( 'click', '.nav-tab', this.handleTabClick );

			// Modal triggers.
			$( document ).on( 'click', '.bkx-add-rule', this.openRuleModal );
			$( document ).on( 'click', '.bkx-add-season', this.openSeasonModal );
			$( document ).on( 'click', '.bkx-add-timeslot', this.openTimeslotModal );
			$( document ).on( 'click', '.bkx-edit-rule', this.editRule );
			$( document ).on( 'click', '.bkx-edit-season', this.editSeason );
			$( document ).on( 'click', '.bkx-edit-timeslot', this.editTimeslot );
			$( document ).on( 'click', '.bkx-modal-close', this.closeModal );
			$( document ).on( 'click', '.bkx-modal', this.handleModalBackdropClick );

			// Form submissions.
			$( document ).on( 'submit', '#bkx-rule-form', this.saveRule );
			$( document ).on( 'submit', '#bkx-season-form', this.saveSeason );
			$( document ).on( 'submit', '#bkx-timeslot-form', this.saveTimeslot );

			// Delete actions.
			$( document ).on( 'click', '.bkx-delete-rule', this.deleteRule );
			$( document ).on( 'click', '.bkx-delete-season', this.deleteSeason );
			$( document ).on( 'click', '.bkx-delete-timeslot', this.deleteTimeslot );

			// Toggle actions.
			$( document ).on( 'click', '.bkx-toggle-rule', this.toggleRule );
			$( document ).on( 'click', '.bkx-toggle-season', this.toggleSeason );
			$( document ).on( 'click', '.bkx-toggle-timeslot', this.toggleTimeslot );

			// Duplicate.
			$( document ).on( 'click', '.bkx-duplicate-rule', this.duplicateRule );

			// Preview calculator.
			$( document ).on( 'click', '#bkx-calculate-preview', this.calculatePreview );

			// Condition builder.
			$( document ).on( 'click', '.bkx-add-condition', this.addCondition );
			$( document ).on( 'click', '.bkx-remove-condition', this.removeCondition );

			// Rule type change.
			$( document ).on( 'change', '#rule_type', this.handleRuleTypeChange );

			// Applies to change.
			$( document ).on( 'change', 'select[name="applies_to"]', this.handleAppliesToChange );

			// Settings form.
			$( document ).on( 'submit', '#bkx-pricing-settings-form', this.saveSettings );

			// Keyboard events.
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

			// Update URL hash.
			window.location.hash = target;
		},

		/**
		 * Open rule modal.
		 *
		 * @param {Event} e Click event.
		 */
		openRuleModal: function( e ) {
			e.preventDefault();
			BkxSlidingPricing.resetRuleForm();
			$( '#bkx-rule-modal' ).fadeIn( 200 );
			$( '#rule_name' ).focus();
		},

		/**
		 * Open season modal.
		 *
		 * @param {Event} e Click event.
		 */
		openSeasonModal: function( e ) {
			e.preventDefault();
			BkxSlidingPricing.resetSeasonForm();
			$( '#bkx-season-modal' ).fadeIn( 200 );
			$( '#season_name' ).focus();
		},

		/**
		 * Open timeslot modal.
		 *
		 * @param {Event} e Click event.
		 */
		openTimeslotModal: function( e ) {
			e.preventDefault();
			BkxSlidingPricing.resetTimeslotForm();
			$( '#bkx-timeslot-modal' ).fadeIn( 200 );
			$( '#timeslot_name' ).focus();
		},

		/**
		 * Edit rule.
		 *
		 * @param {Event} e Click event.
		 */
		editRule: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var ruleId = $btn.data( 'id' );

			BkxSlidingPricing.showLoading( $btn );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_get_pricing_rule',
					nonce: bkxSlidingPricing.nonce,
					rule_id: ruleId
				},
				success: function( response ) {
					BkxSlidingPricing.hideLoading( $btn );

					if ( response.success ) {
						BkxSlidingPricing.populateRuleForm( response.data );
						$( '#bkx-rule-modal' ).fadeIn( 200 );
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxSlidingPricing.hideLoading( $btn );
					BkxSlidingPricing.showNotice( bkxSlidingPricing.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Edit season.
		 *
		 * @param {Event} e Click event.
		 */
		editSeason: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var seasonId = $btn.data( 'id' );

			BkxSlidingPricing.showLoading( $btn );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_get_pricing_season',
					nonce: bkxSlidingPricing.nonce,
					season_id: seasonId
				},
				success: function( response ) {
					BkxSlidingPricing.hideLoading( $btn );

					if ( response.success ) {
						BkxSlidingPricing.populateSeasonForm( response.data );
						$( '#bkx-season-modal' ).fadeIn( 200 );
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxSlidingPricing.hideLoading( $btn );
					BkxSlidingPricing.showNotice( bkxSlidingPricing.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Edit timeslot.
		 *
		 * @param {Event} e Click event.
		 */
		editTimeslot: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var timeslotId = $btn.data( 'id' );

			BkxSlidingPricing.showLoading( $btn );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_get_pricing_timeslot',
					nonce: bkxSlidingPricing.nonce,
					timeslot_id: timeslotId
				},
				success: function( response ) {
					BkxSlidingPricing.hideLoading( $btn );

					if ( response.success ) {
						BkxSlidingPricing.populateTimeslotForm( response.data );
						$( '#bkx-timeslot-modal' ).fadeIn( 200 );
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxSlidingPricing.hideLoading( $btn );
					BkxSlidingPricing.showNotice( bkxSlidingPricing.strings.error, 'error' );
				}
			} );
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
			// Close modal on Escape.
			if ( e.key === 'Escape' && $( '.bkx-modal:visible' ).length ) {
				$( '.bkx-modal' ).fadeOut( 200 );
			}
		},

		/**
		 * Save rule.
		 *
		 * @param {Event} e Submit event.
		 */
		saveRule: function( e ) {
			e.preventDefault();

			var $form = $( this );
			var $btn = $form.find( 'button[type="submit"]' );

			BkxSlidingPricing.showLoading( $btn );

			// Gather conditions.
			var conditions = [];
			$( '.bkx-condition-row' ).each( function() {
				var $row = $( this );
				conditions.push( {
					type: $row.find( '.condition-type' ).val(),
					operator: $row.find( '.condition-operator' ).val(),
					value: $row.find( '.condition-value' ).val()
				} );
			} );

			var formData = $form.serializeArray();
			formData.push( { name: 'conditions', value: JSON.stringify( conditions ) } );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: formData,
				success: function( response ) {
					BkxSlidingPricing.hideLoading( $btn );

					if ( response.success ) {
						BkxSlidingPricing.showNotice( response.data.message, 'success' );
						$( '.bkx-modal' ).fadeOut( 200 );
						location.reload();
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxSlidingPricing.hideLoading( $btn );
					BkxSlidingPricing.showNotice( bkxSlidingPricing.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Save season.
		 *
		 * @param {Event} e Submit event.
		 */
		saveSeason: function( e ) {
			e.preventDefault();

			var $form = $( this );
			var $btn = $form.find( 'button[type="submit"]' );

			BkxSlidingPricing.showLoading( $btn );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: $form.serialize(),
				success: function( response ) {
					BkxSlidingPricing.hideLoading( $btn );

					if ( response.success ) {
						BkxSlidingPricing.showNotice( response.data.message, 'success' );
						$( '.bkx-modal' ).fadeOut( 200 );
						location.reload();
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxSlidingPricing.hideLoading( $btn );
					BkxSlidingPricing.showNotice( bkxSlidingPricing.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Save timeslot.
		 *
		 * @param {Event} e Submit event.
		 */
		saveTimeslot: function( e ) {
			e.preventDefault();

			var $form = $( this );
			var $btn = $form.find( 'button[type="submit"]' );

			BkxSlidingPricing.showLoading( $btn );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: $form.serialize(),
				success: function( response ) {
					BkxSlidingPricing.hideLoading( $btn );

					if ( response.success ) {
						BkxSlidingPricing.showNotice( response.data.message, 'success' );
						$( '.bkx-modal' ).fadeOut( 200 );
						location.reload();
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxSlidingPricing.hideLoading( $btn );
					BkxSlidingPricing.showNotice( bkxSlidingPricing.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Delete rule.
		 *
		 * @param {Event} e Click event.
		 */
		deleteRule: function( e ) {
			e.preventDefault();

			if ( ! confirm( bkxSlidingPricing.strings.confirm_delete ) ) {
				return;
			}

			var $btn = $( this );
			var ruleId = $btn.data( 'id' );

			BkxSlidingPricing.showLoading( $btn );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_delete_pricing_rule',
					nonce: bkxSlidingPricing.nonce,
					rule_id: ruleId
				},
				success: function( response ) {
					BkxSlidingPricing.hideLoading( $btn );

					if ( response.success ) {
						$btn.closest( 'tr' ).fadeOut( 300, function() {
							$( this ).remove();
							BkxSlidingPricing.checkEmptyTable( '#bkx-rules-table' );
						} );
						BkxSlidingPricing.showNotice( response.data.message, 'success' );
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxSlidingPricing.hideLoading( $btn );
					BkxSlidingPricing.showNotice( bkxSlidingPricing.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Delete season.
		 *
		 * @param {Event} e Click event.
		 */
		deleteSeason: function( e ) {
			e.preventDefault();

			if ( ! confirm( bkxSlidingPricing.strings.confirm_delete ) ) {
				return;
			}

			var $btn = $( this );
			var seasonId = $btn.data( 'id' );

			BkxSlidingPricing.showLoading( $btn );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_delete_pricing_season',
					nonce: bkxSlidingPricing.nonce,
					season_id: seasonId
				},
				success: function( response ) {
					BkxSlidingPricing.hideLoading( $btn );

					if ( response.success ) {
						$btn.closest( 'tr' ).fadeOut( 300, function() {
							$( this ).remove();
							BkxSlidingPricing.checkEmptyTable( '#bkx-seasons-table' );
						} );
						BkxSlidingPricing.showNotice( response.data.message, 'success' );
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxSlidingPricing.hideLoading( $btn );
					BkxSlidingPricing.showNotice( bkxSlidingPricing.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Delete timeslot.
		 *
		 * @param {Event} e Click event.
		 */
		deleteTimeslot: function( e ) {
			e.preventDefault();

			if ( ! confirm( bkxSlidingPricing.strings.confirm_delete ) ) {
				return;
			}

			var $btn = $( this );
			var timeslotId = $btn.data( 'id' );

			BkxSlidingPricing.showLoading( $btn );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_delete_pricing_timeslot',
					nonce: bkxSlidingPricing.nonce,
					timeslot_id: timeslotId
				},
				success: function( response ) {
					BkxSlidingPricing.hideLoading( $btn );

					if ( response.success ) {
						$btn.closest( 'tr' ).fadeOut( 300, function() {
							$( this ).remove();
							BkxSlidingPricing.checkEmptyTable( '#bkx-timeslots-table' );
						} );
						BkxSlidingPricing.showNotice( response.data.message, 'success' );
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxSlidingPricing.hideLoading( $btn );
					BkxSlidingPricing.showNotice( bkxSlidingPricing.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Toggle rule.
		 *
		 * @param {Event} e Click event.
		 */
		toggleRule: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var ruleId = $btn.data( 'id' );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_toggle_pricing_rule',
					nonce: bkxSlidingPricing.nonce,
					rule_id: ruleId
				},
				success: function( response ) {
					if ( response.success ) {
						var $status = $btn.closest( 'tr' ).find( '.bkx-status' );
						$status.toggleClass( 'active inactive' );
						$status.text( $status.hasClass( 'active' ) ? 'Active' : 'Inactive' );
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				}
			} );
		},

		/**
		 * Toggle season.
		 *
		 * @param {Event} e Click event.
		 */
		toggleSeason: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var seasonId = $btn.data( 'id' );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_toggle_pricing_season',
					nonce: bkxSlidingPricing.nonce,
					season_id: seasonId
				},
				success: function( response ) {
					if ( response.success ) {
						var $status = $btn.closest( 'tr' ).find( '.bkx-status' );
						$status.toggleClass( 'active inactive' );
						$status.text( $status.hasClass( 'active' ) ? 'Active' : 'Inactive' );
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				}
			} );
		},

		/**
		 * Toggle timeslot.
		 *
		 * @param {Event} e Click event.
		 */
		toggleTimeslot: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var timeslotId = $btn.data( 'id' );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_toggle_pricing_timeslot',
					nonce: bkxSlidingPricing.nonce,
					timeslot_id: timeslotId
				},
				success: function( response ) {
					if ( response.success ) {
						var $status = $btn.closest( 'tr' ).find( '.bkx-status' );
						$status.toggleClass( 'active inactive' );
						$status.text( $status.hasClass( 'active' ) ? 'Active' : 'Inactive' );
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				}
			} );
		},

		/**
		 * Duplicate rule.
		 *
		 * @param {Event} e Click event.
		 */
		duplicateRule: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var ruleId = $btn.data( 'id' );

			BkxSlidingPricing.showLoading( $btn );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_duplicate_pricing_rule',
					nonce: bkxSlidingPricing.nonce,
					rule_id: ruleId
				},
				success: function( response ) {
					BkxSlidingPricing.hideLoading( $btn );

					if ( response.success ) {
						BkxSlidingPricing.showNotice( response.data.message, 'success' );
						location.reload();
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxSlidingPricing.hideLoading( $btn );
					BkxSlidingPricing.showNotice( bkxSlidingPricing.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Calculate preview.
		 *
		 * @param {Event} e Click event.
		 */
		calculatePreview: function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var serviceId = $( '#preview_service' ).val();
			var staffId = $( '#preview_staff' ).val();
			var date = $( '#preview_date' ).val();
			var time = $( '#preview_time' ).val();

			if ( ! serviceId || ! date || ! time ) {
				BkxSlidingPricing.showNotice( 'Please select service, date and time.', 'error' );
				return;
			}

			BkxSlidingPricing.showLoading( $btn );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: {
					action: 'bkx_calculate_price_preview',
					nonce: bkxSlidingPricing.nonce,
					service_id: serviceId,
					staff_id: staffId,
					date: date,
					time: time
				},
				success: function( response ) {
					BkxSlidingPricing.hideLoading( $btn );

					if ( response.success ) {
						BkxSlidingPricing.displayPreviewResult( response.data );
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxSlidingPricing.hideLoading( $btn );
					BkxSlidingPricing.showNotice( bkxSlidingPricing.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Display preview result.
		 *
		 * @param {Object} data Price data.
		 */
		displayPreviewResult: function( data ) {
			var $result = $( '#bkx-preview-result' );
			var currency = bkxSlidingPricing.currency_symbol;

			// Update price boxes.
			$result.find( '.bkx-price-box.base .value' ).text( currency + data.base_price.toFixed( 2 ) );
			$result.find( '.bkx-price-box.final .value' ).text( currency + data.final_price.toFixed( 2 ) );

			var savings = data.base_price - data.final_price;
			var savingsText = savings >= 0 ? '-' + currency + savings.toFixed( 2 ) : '+' + currency + Math.abs( savings ).toFixed( 2 );
			$result.find( '.bkx-price-box.savings .value' ).text( savingsText );

			// Update adjustments list.
			var $list = $result.find( '.bkx-adjustments-list ul' ).empty();

			if ( data.adjustments && data.adjustments.length ) {
				$.each( data.adjustments, function( i, adj ) {
					var adjustmentText = adj.value > 0 ? '+' + adj.value : adj.value;
					if ( adj.type === 'percentage' ) {
						adjustmentText += '%';
					} else {
						adjustmentText = currency + adjustmentText;
					}

					$list.append(
						'<li><span class="adj-name">' + adj.name + '</span>' +
						'<span class="bkx-adjustment ' + ( adj.value > 0 ? 'peak' : 'off-peak' ) + '">' +
						adjustmentText + '</span></li>'
					);
				} );
			} else {
				$list.append( '<li class="bkx-empty">No adjustments applied</li>' );
			}

			$result.slideDown();
		},

		/**
		 * Add condition.
		 *
		 * @param {Event} e Click event.
		 */
		addCondition: function( e ) {
			e.preventDefault();

			var $container = $( '#bkx-conditions-container' );
			var index = $container.find( '.bkx-condition-row' ).length;

			var html = '<div class="bkx-condition-row bkx-form-row">' +
				'<div class="bkx-form-field">' +
				'<select class="condition-type" name="conditions[' + index + '][type]">' +
				'<option value="days_before">Days Before Booking</option>' +
				'<option value="day_of_week">Day of Week</option>' +
				'<option value="booking_count">Booking Count</option>' +
				'<option value="availability">Availability %</option>' +
				'</select>' +
				'</div>' +
				'<div class="bkx-form-field">' +
				'<select class="condition-operator" name="conditions[' + index + '][operator]">' +
				'<option value="=">=</option>' +
				'<option value="!=">!=</option>' +
				'<option value="<"><</option>' +
				'<option value="<="><=</option>' +
				'<option value=">">></option>' +
				'<option value=">=">>=</option>' +
				'</select>' +
				'</div>' +
				'<div class="bkx-form-field">' +
				'<input type="text" class="condition-value" name="conditions[' + index + '][value]" />' +
				'</div>' +
				'<button type="button" class="button bkx-remove-condition">&times;</button>' +
				'</div>';

			$container.append( html );
		},

		/**
		 * Remove condition.
		 *
		 * @param {Event} e Click event.
		 */
		removeCondition: function( e ) {
			e.preventDefault();
			$( this ).closest( '.bkx-condition-row' ).remove();
		},

		/**
		 * Handle rule type change.
		 *
		 * @param {Event} e Change event.
		 */
		handleRuleTypeChange: function( e ) {
			var ruleType = $( this ).val();
			var $container = $( '#bkx-conditions-container' );

			// Clear existing conditions.
			$container.empty();

			// Add default conditions based on rule type.
			if ( ruleType === 'early_bird' ) {
				BkxSlidingPricing.addDefaultCondition( 'days_before', '>=', '14' );
			} else if ( ruleType === 'last_minute' ) {
				BkxSlidingPricing.addDefaultCondition( 'days_before', '<=', '1' );
			} else if ( ruleType === 'demand_based' ) {
				BkxSlidingPricing.addDefaultCondition( 'availability', '<', '20' );
			}
		},

		/**
		 * Add default condition.
		 *
		 * @param {string} type     Condition type.
		 * @param {string} operator Operator.
		 * @param {string} value    Value.
		 */
		addDefaultCondition: function( type, operator, value ) {
			$( '.bkx-add-condition' ).trigger( 'click' );

			var $row = $( '.bkx-condition-row' ).last();
			$row.find( '.condition-type' ).val( type );
			$row.find( '.condition-operator' ).val( operator );
			$row.find( '.condition-value' ).val( value );
		},

		/**
		 * Handle applies to change.
		 *
		 * @param {Event} e Change event.
		 */
		handleAppliesToChange: function( e ) {
			var $select = $( this );
			var value = $select.val();
			var $serviceSelect = $select.closest( 'form' ).find( '.bkx-service-select' );

			if ( value === 'specific' ) {
				$serviceSelect.slideDown();
			} else {
				$serviceSelect.slideUp();
			}
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

			BkxSlidingPricing.showLoading( $btn );

			$.ajax( {
				url: bkxSlidingPricing.ajax_url,
				type: 'POST',
				data: $form.serialize(),
				success: function( response ) {
					BkxSlidingPricing.hideLoading( $btn );

					if ( response.success ) {
						BkxSlidingPricing.showNotice( response.data.message, 'success' );
					} else {
						BkxSlidingPricing.showNotice( response.data.message, 'error' );
					}
				},
				error: function() {
					BkxSlidingPricing.hideLoading( $btn );
					BkxSlidingPricing.showNotice( bkxSlidingPricing.strings.error, 'error' );
				}
			} );
		},

		/**
		 * Initialize sortable.
		 */
		initSortable: function() {
			if ( ! $.fn.sortable ) {
				return;
			}

			$( '#bkx-rules-table tbody' ).sortable( {
				handle: '.column-order',
				update: function( event, ui ) {
					var order = [];
					$( this ).find( 'tr' ).each( function() {
						order.push( $( this ).data( 'id' ) );
					} );

					$.ajax( {
						url: bkxSlidingPricing.ajax_url,
						type: 'POST',
						data: {
							action: 'bkx_reorder_pricing_rules',
							nonce: bkxSlidingPricing.nonce,
							order: order
						}
					} );
				}
			} );
		},

		/**
		 * Initialize heatmap.
		 */
		initHeatmap: function() {
			var $heatmap = $( '.bkx-heatmap' );

			if ( ! $heatmap.length ) {
				return;
			}

			// Heatmap is rendered via PHP, this adds interactivity.
			$heatmap.find( '.bkx-heatmap-value' ).on( 'click', function() {
				var $cell = $( this );
				var day = $cell.data( 'day' );
				var hour = $cell.data( 'hour' );

				// Open timeslot modal with pre-filled values.
				BkxSlidingPricing.resetTimeslotForm();
				$( '#timeslot_day_of_week' ).val( day );
				$( '#timeslot_start_time' ).val( hour + ':00' );
				$( '#timeslot_end_time' ).val( ( parseInt( hour ) + 1 ) + ':00' );
				$( '#bkx-timeslot-modal' ).fadeIn( 200 );
			} );
		},

		/**
		 * Reset rule form.
		 */
		resetRuleForm: function() {
			var $form = $( '#bkx-rule-form' );
			$form[ 0 ].reset();
			$form.find( 'input[name="rule_id"]' ).val( '' );
			$( '#bkx-conditions-container' ).empty();
			$( '.bkx-service-select' ).hide();
		},

		/**
		 * Reset season form.
		 */
		resetSeasonForm: function() {
			var $form = $( '#bkx-season-form' );
			$form[ 0 ].reset();
			$form.find( 'input[name="season_id"]' ).val( '' );
			$( '.bkx-service-select' ).hide();
		},

		/**
		 * Reset timeslot form.
		 */
		resetTimeslotForm: function() {
			var $form = $( '#bkx-timeslot-form' );
			$form[ 0 ].reset();
			$form.find( 'input[name="timeslot_id"]' ).val( '' );
			$( '.bkx-service-select' ).hide();
		},

		/**
		 * Populate rule form.
		 *
		 * @param {Object} rule Rule data.
		 */
		populateRuleForm: function( rule ) {
			var $form = $( '#bkx-rule-form' );

			$form.find( 'input[name="rule_id"]' ).val( rule.id );
			$form.find( '#rule_name' ).val( rule.name );
			$form.find( '#rule_type' ).val( rule.rule_type );
			$form.find( 'select[name="applies_to"]' ).val( rule.applies_to ).trigger( 'change' );
			$form.find( '#rule_priority' ).val( rule.priority );
			$form.find( '#adjustment_type' ).val( rule.adjustment_type );
			$form.find( '#adjustment_value' ).val( rule.adjustment_value );
			$form.find( '#rule_start_date' ).val( rule.start_date );
			$form.find( '#rule_end_date' ).val( rule.end_date );
			$form.find( 'input[name="is_active"]' ).prop( 'checked', rule.is_active == 1 );

			// Populate conditions.
			$( '#bkx-conditions-container' ).empty();
			if ( rule.conditions && rule.conditions.length ) {
				$.each( rule.conditions, function( i, condition ) {
					$( '.bkx-add-condition' ).trigger( 'click' );
					var $row = $( '.bkx-condition-row' ).last();
					$row.find( '.condition-type' ).val( condition.type );
					$row.find( '.condition-operator' ).val( condition.operator );
					$row.find( '.condition-value' ).val( condition.value );
				} );
			}

			// Populate service IDs.
			if ( rule.service_ids && rule.service_ids.length ) {
				$form.find( 'select[name="service_ids[]"]' ).val( rule.service_ids );
			}
		},

		/**
		 * Populate season form.
		 *
		 * @param {Object} season Season data.
		 */
		populateSeasonForm: function( season ) {
			var $form = $( '#bkx-season-form' );

			$form.find( 'input[name="season_id"]' ).val( season.id );
			$form.find( '#season_name' ).val( season.name );
			$form.find( '#season_start_date' ).val( season.start_date );
			$form.find( '#season_end_date' ).val( season.end_date );
			$form.find( '#season_adjustment_type' ).val( season.adjustment_type );
			$form.find( '#season_adjustment_value' ).val( season.adjustment_value );
			$form.find( 'select[name="applies_to"]' ).val( season.applies_to ).trigger( 'change' );
			$form.find( 'input[name="recurs_yearly"]' ).prop( 'checked', season.recurs_yearly == 1 );
			$form.find( 'input[name="is_active"]' ).prop( 'checked', season.is_active == 1 );

			if ( season.service_ids && season.service_ids.length ) {
				$form.find( 'select[name="service_ids[]"]' ).val( season.service_ids );
			}
		},

		/**
		 * Populate timeslot form.
		 *
		 * @param {Object} timeslot Timeslot data.
		 */
		populateTimeslotForm: function( timeslot ) {
			var $form = $( '#bkx-timeslot-form' );

			$form.find( 'input[name="timeslot_id"]' ).val( timeslot.id );
			$form.find( '#timeslot_name' ).val( timeslot.name );
			$form.find( '#timeslot_day_of_week' ).val( timeslot.day_of_week );
			$form.find( '#timeslot_start_time' ).val( timeslot.start_time );
			$form.find( '#timeslot_end_time' ).val( timeslot.end_time );
			$form.find( '#timeslot_adjustment_type' ).val( timeslot.adjustment_type );
			$form.find( '#timeslot_adjustment_value' ).val( timeslot.adjustment_value );
			$form.find( 'select[name="applies_to"]' ).val( timeslot.applies_to ).trigger( 'change' );
			$form.find( 'input[name="is_active"]' ).prop( 'checked', timeslot.is_active == 1 );

			if ( timeslot.service_ids && timeslot.service_ids.length ) {
				$form.find( 'select[name="service_ids[]"]' ).val( timeslot.service_ids );
			}
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
		 * @param {string} type    Notice type (success, error, warning).
		 */
		showNotice: function( message, type ) {
			var $notice = $( '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>' );
			$( '.bkx-pricing-wrap h1' ).after( $notice );

			// Auto-dismiss after 5 seconds.
			setTimeout( function() {
				$notice.fadeOut( 300, function() {
					$( this ).remove();
				} );
			}, 5000 );

			// WordPress dismiss button.
			$notice.find( '.notice-dismiss' ).on( 'click', function() {
				$notice.fadeOut( 300, function() {
					$( this ).remove();
				} );
			} );
		},

		/**
		 * Check for empty table.
		 *
		 * @param {string} tableSelector Table selector.
		 */
		checkEmptyTable: function( tableSelector ) {
			var $table = $( tableSelector );
			if ( $table.find( 'tbody tr' ).length === 0 ) {
				$table.find( 'tbody' ).html(
					'<tr><td colspan="' + $table.find( 'thead th' ).length + '" class="bkx-empty">No items found.</td></tr>'
				);
			}
		}
	};

	// Initialize on document ready.
	$( document ).ready( function() {
		BkxSlidingPricing.init();

		// Handle initial hash.
		var hash = window.location.hash;
		if ( hash ) {
			$( '.nav-tab[href="' + hash + '"]' ).trigger( 'click' );
		}
	} );

} )( jQuery );

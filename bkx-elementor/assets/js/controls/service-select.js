/**
 * Service Select Control Script
 *
 * @package BookingX\Elementor
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Initialize service select control
	 */
	function initServiceSelectControl() {
		var ControlServiceSelectItemView = elementor.modules.controls.BaseData.extend({
			onReady: function() {
				var $select = this.$el.find('.elementor-control-bkx-service-select');

				// Populate options from localized data
				if (typeof bkxServiceSelectControl !== 'undefined' && bkxServiceSelectControl.services) {
					var currentValue = this.getControlValue();

					// Clear existing options except the first one
					$select.find('option:not(:first)').remove();

					// Add service options
					$.each(bkxServiceSelectControl.services, function(i, service) {
						var label = service.title;
						if (service.price) {
							label += ' - $' + parseFloat(service.price).toFixed(2);
						}

						var $option = $('<option>')
							.val(service.id)
							.text(label)
							.data('price', service.price)
							.data('duration', service.duration);

						if (currentValue == service.id) {
							$option.prop('selected', true);
						}

						$select.append($option);
					});
				}

				// Initialize as Select2 if available
				if ($.fn.select2) {
					$select.select2({
						width: '100%',
						placeholder: bkxServiceSelectControl.i18n.selectService || 'Select a service',
						allowClear: true
					});
				}
			},

			onBeforeDestroy: function() {
				var $select = this.$el.find('.elementor-control-bkx-service-select');
				if ($.fn.select2 && $select.data('select2')) {
					$select.select2('destroy');
				}
			}
		});

		// Register control
		elementor.addControlView('bkx_service_select', ControlServiceSelectItemView);
	}

	// Initialize when Elementor is ready
	$(window).on('elementor:init', initServiceSelectControl);

})(jQuery);

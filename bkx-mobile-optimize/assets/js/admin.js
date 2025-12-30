/**
 * Mobile Optimization Admin JavaScript
 *
 * @package BookingX\MobileOptimize
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Toggle dependent options
		$('input[name="enabled"]').on('change', function() {
			var enabled = $(this).is(':checked');
			$('.bkx-card').not(':first').find('input').prop('disabled', !enabled);
		});
	});

})(jQuery);

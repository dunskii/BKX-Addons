/**
 * Rewards Points Frontend JavaScript
 *
 * @package BookingX\RewardsPoints
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Rewards Handler
     */
    var BKXRewards = {
        /**
         * Initialize
         */
        init: function() {
            this.redemptionValue = 0.01;
            this.bindEvents();
            this.loadUserPoints();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Points input change
            $(document).on('input', '#bkx-redeem-points', function() {
                self.updateDiscountPreview();
            });

            // Quick select buttons
            $(document).on('click', '.bkx-quick-points', function(e) {
                e.preventDefault();
                var points = $(this).data('points');
                $('#bkx-redeem-points').val(points);
                self.updateDiscountPreview();
            });

            // Apply points
            $(document).on('click', '.bkx-apply-points', function(e) {
                e.preventDefault();
                self.applyPoints();
            });

            // Remove points
            $(document).on('click', '.bkx-remove-points', function(e) {
                e.preventDefault();
                self.removePoints();
            });
        },

        /**
         * Load user points balance
         */
        loadUserPoints: function() {
            var self = this;

            $.ajax({
                url: bkxRewards.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_get_user_points',
                    nonce: bkxRewards.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.redemptionValue = response.data.redemption_value;
                        self.updateDiscountPreview();
                    }
                }
            });
        },

        /**
         * Update discount preview
         */
        updateDiscountPreview: function() {
            var points = parseInt($('#bkx-redeem-points').val()) || 0;
            var discount = points * this.redemptionValue;

            $('.bkx-discount-value').text('$' + discount.toFixed(2));
        },

        /**
         * Apply points to booking
         */
        applyPoints: function() {
            var self = this;
            var points = parseInt($('#bkx-redeem-points').val()) || 0;

            if (points <= 0) {
                alert(bkxRewards.i18n.error);
                return;
            }

            var $button = $('.bkx-apply-points');
            var originalText = $button.text();
            $button.prop('disabled', true).text(bkxRewards.i18n.loading);

            $.ajax({
                url: bkxRewards.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_calculate_redemption',
                    nonce: bkxRewards.nonce,
                    points: points
                },
                success: function(response) {
                    if (response.success) {
                        // Show applied state
                        $('.bkx-points-selector-body').hide();
                        $('.bkx-points-applied').show();
                        $('.bkx-applied-text').text(
                            points.toLocaleString() + ' ' + bkxRewards.i18n.points +
                            ' = $' + response.data.value.toFixed(2) + ' ' + bkxRewards.i18n.discount
                        );

                        // Store redemption info
                        $('#bkx-points-redemption-id').val(points);

                        // Trigger event for booking form to update total
                        $(document).trigger('bkx_points_applied', [points, response.data.value]);
                    } else {
                        alert(response.data.message || bkxRewards.i18n.error);
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert(bkxRewards.i18n.error);
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Remove applied points
         */
        removePoints: function() {
            // Reset UI
            $('.bkx-points-applied').hide();
            $('.bkx-points-selector-body').show();
            $('#bkx-redeem-points').val(0);
            $('#bkx-points-redemption-id').val('');
            this.updateDiscountPreview();

            // Re-enable apply button
            $('.bkx-apply-points').prop('disabled', false);

            // Trigger event
            $(document).trigger('bkx_points_removed');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.bkx-points-selector').length || $('.bkx-my-points').length) {
            BKXRewards.init();
        }
    });

})(jQuery);

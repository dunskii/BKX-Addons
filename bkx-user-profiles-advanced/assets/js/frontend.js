/**
 * User Profiles Advanced Frontend JavaScript
 *
 * @package BookingX\UserProfilesAdvanced
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Profile form handling
     */
    function initProfileForm() {
        var $form = $('#bkx-profile-form');

        $form.on('submit', function(e) {
            e.preventDefault();

            var $button = $form.find('button[type="submit"]');
            var $message = $form.find('.bkx-form-message');

            $button.prop('disabled', true);
            $message.removeClass('success error').text('');

            $.ajax({
                url: bkxProfiles.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_profile_update',
                    nonce: bkxProfiles.nonce,
                    phone: $('#bkx-phone').val(),
                    preferred_time: $('#bkx-preferred-time').val(),
                    communication_preference: $('#bkx-communication').val(),
                    notes: $('#bkx-notes').val()
                },
                success: function(response) {
                    if (response.success) {
                        $message.addClass('success').text(response.data.message);
                    } else {
                        $message.addClass('error').text(response.data.message);
                    }
                },
                error: function() {
                    $message.addClass('error').text('Request failed.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    }

    /**
     * Favorites handling
     */
    function initFavorites() {
        $(document).on('click', '.bkx-toggle-favorite, .bkx-remove-favorite', function() {
            var $button = $(this);
            var itemId = $button.data('id');
            var itemType = $button.data('type') || 'service';

            $button.prop('disabled', true);

            $.ajax({
                url: bkxProfiles.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_toggle_favorite',
                    nonce: bkxProfiles.nonce,
                    item_id: itemId,
                    type: itemType
                },
                success: function(response) {
                    if (response.success) {
                        if ($button.hasClass('bkx-remove-favorite')) {
                            $button.closest('.bkx-favorite-card').fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            $button.toggleClass('is-favorite', response.data.is_favorite);
                        }
                    }
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    }

    /**
     * Points redemption
     */
    function initPointsRedemption() {
        var $form = $('#bkx-redeem-form');
        var $pointsInput = $('#bkx-redeem-points');
        var $discountAmount = $('#bkx-discount-amount');

        // Calculate discount on input change
        $pointsInput.on('input', function() {
            var points = parseInt($(this).val()) || 0;
            var rate = 100; // This should come from settings
            var value = 1;
            var discount = (points / rate) * value;
            $discountAmount.text('$' + discount.toFixed(2));
        });

        $form.on('submit', function(e) {
            e.preventDefault();

            var $button = $form.find('button[type="submit"]');
            var $message = $form.find('.bkx-form-message');
            var points = $pointsInput.val();

            $button.prop('disabled', true);
            $message.removeClass('success error').text('');

            $.ajax({
                url: bkxProfiles.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_redeem_points',
                    nonce: bkxProfiles.nonce,
                    points: points
                },
                success: function(response) {
                    if (response.success) {
                        $message.addClass('success').text(response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $message.addClass('error').text(response.data.message);
                    }
                },
                error: function() {
                    $message.addClass('error').text('Request failed.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    }

    /**
     * Booking cancellation
     */
    function initBookingCancellation() {
        $(document).on('click', '.bkx-cancel-booking', function() {
            var $button = $(this);
            var bookingId = $button.data('id');

            if (!confirm(bkxProfiles.i18n.confirm_cancel)) {
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: bkxProfiles.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_cancel_booking',
                    nonce: bkxProfiles.nonce,
                    booking_id: bookingId
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('.bkx-booking-card').find('.bkx-status')
                            .removeClass()
                            .addClass('bkx-status bkx-status-cancelled')
                            .text('Cancelled');
                        $button.remove();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('Request failed.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    }

    /**
     * Rebook functionality
     */
    function initRebook() {
        $(document).on('click', '.bkx-rebook', function() {
            var $button = $(this);
            var bookingId = $button.data('id');

            if (!confirm(bkxProfiles.i18n.confirm_rebook)) {
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: bkxProfiles.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_rebook',
                    nonce: bkxProfiles.nonce,
                    booking_id: bookingId
                },
                success: function(response) {
                    if (response.success && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        alert(response.data.message || 'Unable to rebook.');
                    }
                },
                error: function() {
                    alert('Request failed.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    }

    /**
     * Copy referral code
     */
    function initCopyCode() {
        $(document).on('click', '.bkx-copy-code', function() {
            var $button = $(this);
            var code = $button.data('code');

            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(function() {
                    var originalText = $button.text();
                    $button.text('Copied!');
                    setTimeout(function() {
                        $button.text(originalText);
                    }, 2000);
                });
            } else {
                // Fallback for older browsers
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(code).select();
                document.execCommand('copy');
                $temp.remove();

                var originalText = $button.text();
                $button.text('Copied!');
                setTimeout(function() {
                    $button.text(originalText);
                }, 2000);
            }
        });
    }

    /**
     * Initialize
     */
    $(document).ready(function() {
        initProfileForm();
        initFavorites();
        initPointsRedemption();
        initBookingCancellation();
        initRebook();
        initCopyCode();
    });

})(jQuery);

/**
 * BookingX FreshBooks Integration - Admin JavaScript
 *
 * @package BookingX\FreshBooks
 */

(function($) {
    'use strict';

    var BKX_FB = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#bkx-freshbooks-settings-form').on('submit', this.saveSettings);
            $('#bkx-freshbooks-disconnect').on('click', this.disconnect);
            $(document).on('click', '.bkx-sync-booking', this.syncBooking);
            $('#bkx-sync-all').on('click', this.syncAll);
        },

        saveSettings: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('#bkx-save-settings');
            var $spinner = $form.find('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: bkxFreshBooksAdmin.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=bkx_freshbooks_save_settings&nonce=' + bkxFreshBooksAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        BKX_FB.showNotice('success', bkxFreshBooksAdmin.strings.settingsSaved);
                    } else {
                        BKX_FB.showNotice('error', response.data || bkxFreshBooksAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_FB.showNotice('error', bkxFreshBooksAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        disconnect: function() {
            if (!confirm(bkxFreshBooksAdmin.strings.confirmDisconnect)) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true);

            $.ajax({
                url: bkxFreshBooksAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_freshbooks_disconnect',
                    nonce: bkxFreshBooksAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BKX_FB.showNotice('success', bkxFreshBooksAdmin.strings.disconnected);
                        location.reload();
                    } else {
                        BKX_FB.showNotice('error', response.data || bkxFreshBooksAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_FB.showNotice('error', bkxFreshBooksAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        syncBooking: function() {
            var $button = $(this);
            var bookingId = $button.data('booking-id');
            var originalText = $button.html();

            $button.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0;"></span> ' + bkxFreshBooksAdmin.strings.syncing);

            $.ajax({
                url: bkxFreshBooksAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_freshbooks_sync_booking',
                    nonce: bkxFreshBooksAdmin.nonce,
                    booking_id: bookingId
                },
                success: function(response) {
                    if (response.success) {
                        BKX_FB.showNotice('success', bkxFreshBooksAdmin.strings.syncSuccess);
                        var $row = $button.closest('tr');
                        if ($row.length && $row.closest('.bkx-sync-section').length) {
                            $row.fadeOut(function() {
                                $(this).remove();
                            });
                        } else {
                            location.reload();
                        }
                    } else {
                        BKX_FB.showNotice('error', response.data || bkxFreshBooksAdmin.strings.syncFailed);
                        $button.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    BKX_FB.showNotice('error', bkxFreshBooksAdmin.strings.error);
                    $button.prop('disabled', false).html(originalText);
                }
            });
        },

        syncAll: function() {
            var $button = $(this);
            var $rows = $('table tbody tr[data-booking-id]');
            var total = $rows.length;
            var synced = 0;

            if (total === 0) return;

            $button.prop('disabled', true);

            function syncNext() {
                if (synced >= total) {
                    $button.prop('disabled', false);
                    BKX_FB.showNotice('success', 'Synced ' + synced + ' bookings');
                    return;
                }

                var $row = $rows.eq(synced);
                var bookingId = $row.data('booking-id');

                $.ajax({
                    url: bkxFreshBooksAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'bkx_freshbooks_sync_booking',
                        nonce: bkxFreshBooksAdmin.nonce,
                        booking_id: bookingId
                    },
                    complete: function() {
                        synced++;
                        $row.fadeOut();
                        syncNext();
                    }
                });
            }

            syncNext();
        },

        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.bkx-freshbooks-admin h1').after($notice);
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    $(document).ready(function() {
        BKX_FB.init();
    });

})(jQuery);

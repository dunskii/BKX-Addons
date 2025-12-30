/**
 * BookingX MYOB Integration - Admin JavaScript
 *
 * @package BookingX\MYOB
 */

(function($) {
    'use strict';

    var BKX_MYOB = {
        /**
         * Initialize.
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events.
         */
        bindEvents: function() {
            // Settings form.
            $('#bkx-myob-settings-form').on('submit', this.saveSettings);
            $('#bkx-myob-disconnect').on('click', this.disconnect);
            $('#bkx-load-accounts').on('click', this.loadAccounts);
            $('#bkx-load-tax-codes').on('click', this.loadTaxCodes);

            // Sync actions.
            $(document).on('click', '.bkx-sync-booking', this.syncBooking);
            $('#bkx-sync-all').on('click', this.syncAll);

            // Logs.
            $('#bkx-clear-logs').on('click', this.clearLogs);
        },

        /**
         * Save settings.
         */
        saveSettings: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('#bkx-save-settings');
            var $spinner = $form.find('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: bkxMyobAdmin.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=bkx_myob_save_settings&nonce=' + bkxMyobAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        BKX_MYOB.showNotice('success', bkxMyobAdmin.strings.settingsSaved);
                    } else {
                        BKX_MYOB.showNotice('error', response.data || bkxMyobAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_MYOB.showNotice('error', bkxMyobAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Disconnect from MYOB.
         */
        disconnect: function() {
            if (!confirm(bkxMyobAdmin.strings.confirmDisconnect)) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true);

            $.ajax({
                url: bkxMyobAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_myob_disconnect',
                    nonce: bkxMyobAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BKX_MYOB.showNotice('success', bkxMyobAdmin.strings.disconnected);
                        location.reload();
                    } else {
                        BKX_MYOB.showNotice('error', response.data || bkxMyobAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_MYOB.showNotice('error', bkxMyobAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Load accounts from MYOB.
         */
        loadAccounts: function() {
            var $button = $(this);
            var $select = $('#bkx-myob-income-account');

            $button.prop('disabled', true);
            $select.prop('disabled', true);

            $.ajax({
                url: bkxMyobAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_myob_get_accounts',
                    nonce: bkxMyobAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var items = response.data.Items || response.data.items || response.data;
                        $select.empty().append('<option value="">-- Select Account --</option>');

                        if (Array.isArray(items)) {
                            items.forEach(function(account) {
                                var uid = account.UID || account.uid || account.Id;
                                var name = account.Name || account.name || account.DisplayName;
                                var number = account.Number || account.number || '';
                                var label = number ? number + ' - ' + name : name;
                                $select.append('<option value="' + uid + '">' + label + '</option>');
                            });
                        }
                    } else {
                        BKX_MYOB.showNotice('error', response.data || 'Could not load accounts');
                    }
                },
                error: function() {
                    BKX_MYOB.showNotice('error', bkxMyobAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $select.prop('disabled', false);
                }
            });
        },

        /**
         * Load tax codes from MYOB.
         */
        loadTaxCodes: function() {
            var $button = $(this);
            var $select = $('#bkx-myob-tax-code');

            $button.prop('disabled', true);
            $select.prop('disabled', true);

            $.ajax({
                url: bkxMyobAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_myob_get_tax_codes',
                    nonce: bkxMyobAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var items = response.data.Items || response.data.items || response.data;
                        $select.empty().append('<option value="">-- Select Tax Code --</option>');

                        if (Array.isArray(items)) {
                            items.forEach(function(taxCode) {
                                var uid = taxCode.UID || taxCode.uid || taxCode.Id;
                                var code = taxCode.Code || taxCode.code || '';
                                var desc = taxCode.Description || taxCode.description || taxCode.Name || '';
                                var label = code ? code + ' - ' + desc : desc;
                                $select.append('<option value="' + uid + '">' + label + '</option>');
                            });
                        }
                    } else {
                        BKX_MYOB.showNotice('error', response.data || 'Could not load tax codes');
                    }
                },
                error: function() {
                    BKX_MYOB.showNotice('error', bkxMyobAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $select.prop('disabled', false);
                }
            });
        },

        /**
         * Sync a single booking.
         */
        syncBooking: function() {
            var $button = $(this);
            var bookingId = $button.data('booking-id');
            var originalText = $button.html();

            $button.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0;"></span> ' + bkxMyobAdmin.strings.syncing);

            $.ajax({
                url: bkxMyobAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_myob_sync_booking',
                    nonce: bkxMyobAdmin.nonce,
                    booking_id: bookingId
                },
                success: function(response) {
                    if (response.success) {
                        BKX_MYOB.showNotice('success', bkxMyobAdmin.strings.syncSuccess);
                        // Update row if in sync table.
                        var $row = $button.closest('tr');
                        if ($row.length && $row.closest('.bkx-sync-section').length) {
                            $row.fadeOut(function() {
                                $(this).remove();
                            });
                        } else {
                            // Refresh meta box.
                            location.reload();
                        }
                    } else {
                        BKX_MYOB.showNotice('error', response.data || bkxMyobAdmin.strings.syncFailed);
                        $button.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    BKX_MYOB.showNotice('error', bkxMyobAdmin.strings.error);
                    $button.prop('disabled', false).html(originalText);
                }
            });
        },

        /**
         * Sync all pending bookings.
         */
        syncAll: function() {
            var $button = $(this);
            var $rows = $('table tbody tr[data-booking-id]');
            var total = $rows.length;
            var synced = 0;

            if (total === 0) {
                return;
            }

            $button.prop('disabled', true);

            function syncNext() {
                if (synced >= total) {
                    $button.prop('disabled', false);
                    BKX_MYOB.showNotice('success', 'Synced ' + synced + ' bookings');
                    return;
                }

                var $row = $rows.eq(synced);
                var bookingId = $row.data('booking-id');

                $.ajax({
                    url: bkxMyobAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'bkx_myob_sync_booking',
                        nonce: bkxMyobAdmin.nonce,
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

        /**
         * Clear logs.
         */
        clearLogs: function() {
            if (!confirm('Are you sure you want to clear all logs?')) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true);

            $.ajax({
                url: bkxMyobAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_myob_clear_logs',
                    nonce: bkxMyobAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        BKX_MYOB.showNotice('error', response.data || bkxMyobAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_MYOB.showNotice('error', bkxMyobAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Show notice.
         */
        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

            $('.bkx-myob-admin h1').after($notice);

            // Auto-dismiss after 3 seconds.
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    $(document).ready(function() {
        BKX_MYOB.init();
    });

})(jQuery);

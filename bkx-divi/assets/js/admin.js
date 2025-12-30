/**
 * BookingX Divi Integration - Admin JavaScript
 *
 * @package BookingX\Divi
 */

(function($) {
    'use strict';

    var BKX_Divi = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#bkx-divi-settings-form').on('submit', this.saveSettings);
            $('#bkx-divi-styling-form').on('submit', this.saveStyling);
            $('#bkx-clear-divi-cache').on('click', this.clearCache);
        },

        saveSettings: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('#bkx-save-settings');
            var $spinner = $form.find('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: bkxDiviAdmin.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=bkx_divi_save_settings&nonce=' + bkxDiviAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        BKX_Divi.showNotice('success', bkxDiviAdmin.strings.settingsSaved);
                    } else {
                        BKX_Divi.showNotice('error', response.data || bkxDiviAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_Divi.showNotice('error', bkxDiviAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        saveStyling: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('#bkx-save-styling');
            var $spinner = $form.find('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: bkxDiviAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_divi_save_settings',
                    nonce: bkxDiviAdmin.nonce,
                    custom_css: $form.find('#bkx-custom-css').val()
                },
                success: function(response) {
                    if (response.success) {
                        BKX_Divi.showNotice('success', bkxDiviAdmin.strings.settingsSaved);
                    } else {
                        BKX_Divi.showNotice('error', response.data || bkxDiviAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_Divi.showNotice('error', bkxDiviAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        clearCache: function() {
            var $button = $(this);
            $button.prop('disabled', true);

            $.ajax({
                url: bkxDiviAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_divi_clear_cache',
                    nonce: bkxDiviAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BKX_Divi.showNotice('success', bkxDiviAdmin.strings.cacheCleared);
                    } else {
                        BKX_Divi.showNotice('error', response.data || bkxDiviAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_Divi.showNotice('error', bkxDiviAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.bkx-divi-admin h1').after($notice);
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    $(document).ready(function() {
        BKX_Divi.init();
    });

})(jQuery);

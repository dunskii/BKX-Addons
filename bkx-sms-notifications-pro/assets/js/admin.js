/**
 * SMS Notifications Pro Admin JavaScript
 *
 * @package BookingX\SmsNotificationsPro
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Provider switching
     */
    function initProviderSwitch() {
        var $providerSelect = $('#bkx-sms-provider');

        $providerSelect.on('change', function() {
            var provider = $(this).val();

            $('.provider-settings').hide();
            $('#' + provider + '-settings').show();
        });
    }

    /**
     * Test SMS sending
     */
    function initTestSms() {
        var $button = $('#bkx-send-test');
        var $phone = $('#bkx-test-phone');
        var $result = $('#bkx-test-result');

        $button.on('click', function() {
            var phone = $phone.val().trim();

            if (!phone) {
                $result.html('<span class="error">Please enter a phone number.</span>');
                return;
            }

            $button.prop('disabled', true).text('Sending...');
            $result.html('');

            $.ajax({
                url: bkxSmsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_sms_send_test',
                    nonce: bkxSmsAdmin.nonce,
                    phone: phone
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span class="success">✓ Message sent successfully!</span>');
                    } else {
                        $result.html('<span class="error">✗ ' + (response.data.message || 'Failed to send message.') + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span class="error">✗ Request failed.</span>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Send Test');
                }
            });
        });
    }

    /**
     * Template editing
     */
    function initTemplateEditor() {
        var $modal = $('#bkx-template-modal');
        var $form = $('#bkx-template-form');
        var $content = $('#template-content');
        var $charCount = $('#char-count');

        // Character counter
        $content.on('input', function() {
            var length = $(this).val().length;
            var maxLength = bkxSmsAdmin.maxLength || 160;
            var segments = Math.ceil(length / maxLength);

            $charCount.text(length + ' characters (' + segments + ' segment' + (segments > 1 ? 's' : '') + ')');

            if (length > maxLength) {
                $charCount.addClass('warning');
            } else {
                $charCount.removeClass('warning');
            }
        });

        // Open modal
        $('.bkx-edit-template').on('click', function() {
            var id = $(this).data('id');

            $.ajax({
                url: bkxSmsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_sms_get_template',
                    nonce: bkxSmsAdmin.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        var template = response.data;
                        $('#template-id').val(template.id);
                        $('#template-name').val(template.name);
                        $('#template-content').val(template.content).trigger('input');
                        $('#template-active').prop('checked', template.is_active == 1);
                        $modal.show();
                    }
                }
            });
        });

        // Close modal
        $('.bkx-modal-close').on('click', function() {
            $modal.hide();
        });

        $(window).on('click', function(e) {
            if ($(e.target).is($modal)) {
                $modal.hide();
            }
        });

        // Save template
        $form.on('submit', function(e) {
            e.preventDefault();

            var data = {
                action: 'bkx_sms_save_template',
                nonce: bkxSmsAdmin.nonce,
                id: $('#template-id').val(),
                name: $('#template-name').val(),
                content: $('#template-content').val(),
                is_active: $('#template-active').is(':checked') ? 1 : 0
            };

            $.ajax({
                url: bkxSmsAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || 'Failed to save template.');
                    }
                }
            });
        });

        // Preview template
        $('.bkx-preview-template').on('click', function() {
            var content = $(this).data('content');

            $.ajax({
                url: bkxSmsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_sms_preview_template',
                    nonce: bkxSmsAdmin.nonce,
                    content: content
                },
                success: function(response) {
                    if (response.success) {
                        alert('Preview:\n\n' + response.data.preview);
                    }
                }
            });
        });
    }

    /**
     * Resend failed messages
     */
    function initResend() {
        $('.bkx-resend').on('click', function() {
            var $button = $(this);
            var id = $button.data('id');

            $button.prop('disabled', true).text('Sending...');

            $.ajax({
                url: bkxSmsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_sms_resend',
                    nonce: bkxSmsAdmin.nonce,
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || 'Failed to resend message.');
                        $button.prop('disabled', false).text('Resend');
                    }
                },
                error: function() {
                    alert('Request failed.');
                    $button.prop('disabled', false).text('Resend');
                }
            });
        });
    }

    /**
     * Balance check
     */
    function initBalanceCheck() {
        var $balance = $('#bkx-balance');

        if ($balance.length) {
            $.ajax({
                url: bkxSmsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_sms_get_balance',
                    nonce: bkxSmsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $balance.html(response.data.balance + ' ' + response.data.currency);
                    } else {
                        $balance.html('<span class="error">Unable to fetch balance</span>');
                    }
                }
            });
        }
    }

    /**
     * Initialize
     */
    $(document).ready(function() {
        initProviderSwitch();
        initTestSms();
        initTemplateEditor();
        initResend();
        initBalanceCheck();
    });

})(jQuery);

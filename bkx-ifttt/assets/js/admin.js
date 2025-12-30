/**
 * BookingX IFTTT Integration - Admin JavaScript
 *
 * @package BookingX\IFTTT
 */

(function($) {
    'use strict';

    var BKX_IFTTT = {
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
            $('#bkx-ifttt-settings-form').on('submit', this.saveSettings);
            $('#bkx-regenerate-key').on('click', this.regenerateKey);
            $('#bkx-copy-key').on('click', this.copyKey);
            $('#bkx-test-connection').on('click', this.testConnection);
            $('.bkx-copy-endpoint').on('click', this.copyEndpoint);

            // Triggers form.
            $('#bkx-ifttt-triggers-form').on('submit', this.saveTriggers);
            $('.bkx-view-fields').on('click', this.viewTriggerFields);
            $('.bkx-test-trigger').on('click', this.testTrigger);

            // Actions form.
            $('#bkx-ifttt-actions-form').on('submit', this.saveActions);
            $('.bkx-view-action-fields').on('click', this.viewActionFields);

            // Webhooks.
            $('#bkx-add-webhook-form').on('submit', this.addWebhook);
            $(document).on('change', '.bkx-webhook-toggle', this.toggleWebhook);
            $(document).on('click', '.bkx-test-webhook', this.testWebhook);
            $(document).on('click', '.bkx-view-secret', this.viewSecret);
            $(document).on('click', '.bkx-delete-webhook', this.deleteWebhook);
            $('#bkx-copy-secret').on('click', this.copySecret);

            // Logs.
            $('#bkx-refresh-logs').on('click', this.refreshLogs);
            $('#bkx-clear-logs').on('click', this.clearLogs);

            // Modals.
            $('.bkx-modal-close').on('click', this.closeModal);
            $(document).on('click', '.bkx-modal', function(e) {
                if ($(e.target).hasClass('bkx-modal')) {
                    BKX_IFTTT.closeModal();
                }
            });
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
                url: bkxIftttAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_ifttt_save_settings',
                    nonce: bkxIftttAdmin.nonce,
                    enabled: $('#bkx-ifttt-enabled').is(':checked') ? 1 : 0,
                    service_key: $('#bkx-ifttt-service-key').val(),
                    rate_limit: $('#bkx-ifttt-rate-limit').val(),
                    log_requests: $('#bkx-ifttt-log-requests').is(':checked') ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        BKX_IFTTT.showNotice('success', bkxIftttAdmin.strings.settingsSaved);
                    } else {
                        BKX_IFTTT.showNotice('error', response.data || bkxIftttAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_IFTTT.showNotice('error', bkxIftttAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Regenerate service key.
         */
        regenerateKey: function() {
            if (!confirm(bkxIftttAdmin.strings.confirmRegenerate)) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true);

            $.ajax({
                url: bkxIftttAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_ifttt_regenerate_key',
                    nonce: bkxIftttAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#bkx-ifttt-service-key').val(response.data.key);
                        BKX_IFTTT.showNotice('success', bkxIftttAdmin.strings.keyRegenerated);
                    } else {
                        BKX_IFTTT.showNotice('error', response.data || bkxIftttAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_IFTTT.showNotice('error', bkxIftttAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Copy service key.
         */
        copyKey: function() {
            var key = $('#bkx-ifttt-service-key').val();
            BKX_IFTTT.copyToClipboard(key, bkxIftttAdmin.strings.keyCopied);
        },

        /**
         * Copy endpoint URL.
         */
        copyEndpoint: function() {
            var target = $(this).data('target');
            var text = $('#' + target).text();
            BKX_IFTTT.copyToClipboard(text, bkxIftttAdmin.strings.copied);
        },

        /**
         * Test connection.
         */
        testConnection: function() {
            var $button = $(this);
            var $status = $('#bkx-connection-status');

            $button.prop('disabled', true);
            $status.html('<span class="spinner is-active" style="float: none;"></span>');

            $.ajax({
                url: bkxIftttAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_ifttt_test_connection',
                    nonce: bkxIftttAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span class="bkx-status-success"><span class="dashicons dashicons-yes-alt"></span> ' + bkxIftttAdmin.strings.connectionSuccess + '</span>');
                    } else {
                        $status.html('<span class="bkx-status-error"><span class="dashicons dashicons-warning"></span> ' + (response.data || bkxIftttAdmin.strings.connectionFailed) + '</span>');
                    }
                },
                error: function() {
                    $status.html('<span class="bkx-status-error"><span class="dashicons dashicons-warning"></span> ' + bkxIftttAdmin.strings.connectionFailed + '</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Save triggers.
         */
        saveTriggers: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('#bkx-save-triggers');
            var $spinner = $form.find('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: bkxIftttAdmin.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=bkx_ifttt_save_triggers&nonce=' + bkxIftttAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        BKX_IFTTT.showNotice('success', bkxIftttAdmin.strings.triggersSaved);
                    } else {
                        BKX_IFTTT.showNotice('error', response.data || bkxIftttAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_IFTTT.showNotice('error', bkxIftttAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * View trigger fields.
         */
        viewTriggerFields: function() {
            var trigger = $(this).data('trigger');
            var fields = bkxTriggerFields[trigger] || [];

            var $tbody = $('#bkx-fields-table tbody');
            $tbody.empty();

            fields.forEach(function(field) {
                $tbody.append(
                    '<tr>' +
                    '<td>' + field.name + '</td>' +
                    '<td><code>' + field.slug + '</code></td>' +
                    '<td>' + field.type + '</td>' +
                    '</tr>'
                );
            });

            $('#bkx-fields-modal').show();
        },

        /**
         * Test trigger.
         */
        testTrigger: function() {
            var $button = $(this);
            var trigger = $button.data('trigger');

            $button.prop('disabled', true);

            $.ajax({
                url: bkxIftttAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_ifttt_test_trigger',
                    nonce: bkxIftttAdmin.nonce,
                    trigger: trigger
                },
                success: function(response) {
                    if (response.success) {
                        BKX_IFTTT.showNotice('success', bkxIftttAdmin.strings.triggerTested);
                    } else {
                        BKX_IFTTT.showNotice('error', response.data || bkxIftttAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_IFTTT.showNotice('error', bkxIftttAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Save actions.
         */
        saveActions: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('#bkx-save-actions');
            var $spinner = $form.find('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: bkxIftttAdmin.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=bkx_ifttt_save_actions&nonce=' + bkxIftttAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        BKX_IFTTT.showNotice('success', bkxIftttAdmin.strings.actionsSaved);
                    } else {
                        BKX_IFTTT.showNotice('error', response.data || bkxIftttAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_IFTTT.showNotice('error', bkxIftttAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * View action fields.
         */
        viewActionFields: function() {
            var action = $(this).data('action');
            var fields = bkxActionFields[action] || [];

            var $tbody = $('#bkx-action-fields-table tbody');
            $tbody.empty();

            fields.forEach(function(field) {
                $tbody.append(
                    '<tr>' +
                    '<td>' + field.name + '</td>' +
                    '<td><code>' + field.slug + '</code></td>' +
                    '<td>' + field.type + '</td>' +
                    '<td>' + (field.required ? '<span class="dashicons dashicons-yes"></span>' : '') + '</td>' +
                    '</tr>'
                );
            });

            $('#bkx-action-fields-modal').show();
        },

        /**
         * Add webhook.
         */
        addWebhook: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('.button-primary');
            var $spinner = $form.find('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: bkxIftttAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_ifttt_add_webhook',
                    nonce: bkxIftttAdmin.nonce,
                    url: $('#webhook-url').val(),
                    trigger: $('#webhook-trigger').val()
                },
                success: function(response) {
                    if (response.success) {
                        BKX_IFTTT.showNotice('success', bkxIftttAdmin.strings.webhookAdded);
                        location.reload();
                    } else {
                        BKX_IFTTT.showNotice('error', response.data || bkxIftttAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_IFTTT.showNotice('error', bkxIftttAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        /**
         * Toggle webhook.
         */
        toggleWebhook: function() {
            var $checkbox = $(this);
            var webhookId = $checkbox.data('webhook-id');
            var active = $checkbox.is(':checked');

            $.ajax({
                url: bkxIftttAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_ifttt_toggle_webhook',
                    nonce: bkxIftttAdmin.nonce,
                    webhook_id: webhookId,
                    active: active ? 1 : 0
                },
                success: function(response) {
                    if (!response.success) {
                        $checkbox.prop('checked', !active);
                        BKX_IFTTT.showNotice('error', response.data || bkxIftttAdmin.strings.error);
                    }
                },
                error: function() {
                    $checkbox.prop('checked', !active);
                    BKX_IFTTT.showNotice('error', bkxIftttAdmin.strings.error);
                }
            });
        },

        /**
         * Test webhook.
         */
        testWebhook: function() {
            var $button = $(this);
            var webhookId = $button.data('webhook-id');

            $button.prop('disabled', true);

            $.ajax({
                url: bkxIftttAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_ifttt_test_webhook',
                    nonce: bkxIftttAdmin.nonce,
                    webhook_id: webhookId
                },
                success: function(response) {
                    if (response.success) {
                        BKX_IFTTT.showNotice('success', bkxIftttAdmin.strings.webhookTested);
                    } else {
                        BKX_IFTTT.showNotice('error', response.data || bkxIftttAdmin.strings.webhookTestFailed);
                    }
                },
                error: function() {
                    BKX_IFTTT.showNotice('error', bkxIftttAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * View webhook secret.
         */
        viewSecret: function() {
            var secret = $(this).data('secret');
            $('#bkx-webhook-secret').text(secret);
            $('#bkx-secret-modal').show();
        },

        /**
         * Copy secret.
         */
        copySecret: function() {
            var secret = $('#bkx-webhook-secret').text();
            BKX_IFTTT.copyToClipboard(secret, bkxIftttAdmin.strings.secretCopied);
        },

        /**
         * Delete webhook.
         */
        deleteWebhook: function() {
            if (!confirm(bkxIftttAdmin.strings.confirmDelete)) {
                return;
            }

            var $button = $(this);
            var webhookId = $button.data('webhook-id');
            var $row = $button.closest('tr');

            $button.prop('disabled', true);

            $.ajax({
                url: bkxIftttAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_ifttt_delete_webhook',
                    nonce: bkxIftttAdmin.nonce,
                    webhook_id: webhookId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                        });
                        BKX_IFTTT.showNotice('success', bkxIftttAdmin.strings.webhookDeleted);
                    } else {
                        BKX_IFTTT.showNotice('error', response.data || bkxIftttAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_IFTTT.showNotice('error', bkxIftttAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Refresh logs.
         */
        refreshLogs: function() {
            location.reload();
        },

        /**
         * Clear logs.
         */
        clearLogs: function() {
            if (!confirm(bkxIftttAdmin.strings.confirmClearLogs)) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true);

            $.ajax({
                url: bkxIftttAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_ifttt_clear_logs',
                    nonce: bkxIftttAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        BKX_IFTTT.showNotice('error', response.data || bkxIftttAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_IFTTT.showNotice('error', bkxIftttAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * Close modal.
         */
        closeModal: function() {
            $('.bkx-modal').hide();
        },

        /**
         * Copy to clipboard.
         */
        copyToClipboard: function(text, successMessage) {
            navigator.clipboard.writeText(text).then(function() {
                BKX_IFTTT.showNotice('success', successMessage);
            }).catch(function() {
                // Fallback for older browsers.
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                BKX_IFTTT.showNotice('success', successMessage);
            });
        },

        /**
         * Show notice.
         */
        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

            $('.bkx-ifttt-admin h1').after($notice);

            // Auto-dismiss after 3 seconds.
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);

            // WordPress dismissible handler.
            if (typeof wp !== 'undefined' && wp.notices) {
                wp.notices.init();
            }
        }
    };

    $(document).ready(function() {
        BKX_IFTTT.init();
    });

})(jQuery);

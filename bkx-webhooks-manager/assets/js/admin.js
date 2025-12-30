/**
 * Webhooks Manager Admin JavaScript
 *
 * @package BookingX\WebhooksManager
 */

(function($) {
    'use strict';

    const BKXWebhooks = {
        /**
         * Initialize.
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
        },

        /**
         * Bind events.
         */
        bindEvents: function() {
            // Webhook actions
            $(document).on('click', '#bkx-add-webhook, #bkx-add-webhook-empty', this.openAddModal.bind(this));
            $(document).on('click', '.bkx-edit-webhook', this.openEditModal.bind(this));
            $(document).on('click', '.bkx-delete-webhook', this.deleteWebhook.bind(this));
            $(document).on('click', '.bkx-toggle-webhook', this.toggleWebhook.bind(this));
            $(document).on('click', '.bkx-test-webhook', this.testWebhook.bind(this));
            $(document).on('submit', '#bkx-webhook-form', this.saveWebhook.bind(this));
            $(document).on('click', '#bkx-regenerate-secret', this.regenerateSecret.bind(this));

            // Modal
            $(document).on('click', '.bkx-modal-close', this.closeModal.bind(this));
            $(document).on('click', '.bkx-modal', this.handleModalClick.bind(this));

            // Form tabs
            $(document).on('click', '.bkx-form-tab', this.switchFormTab.bind(this));

            // Event category toggle
            $(document).on('change', '.bkx-category-toggle', this.toggleCategory.bind(this));
            $(document).on('change', '.bkx-event-item input', this.updateCategoryState.bind(this));

            // Custom headers
            $(document).on('click', '#bkx-add-header', this.addHeader.bind(this));
            $(document).on('click', '.bkx-remove-header', this.removeHeader.bind(this));

            // Delivery actions
            $(document).on('click', '.bkx-view-delivery', this.viewDelivery.bind(this));
            $(document).on('click', '.bkx-retry-delivery', this.retryDelivery.bind(this));
            $(document).on('click', '.bkx-delivery-tab', this.switchDeliveryTab.bind(this));

            // Settings code tabs
            $(document).on('click', '.bkx-code-tab', this.switchCodeTab.bind(this));

            // Settings form
            $(document).on('submit', '#bkx-webhooks-settings-form', this.saveSettings.bind(this));
        },

        /**
         * Initialize tabs.
         */
        initTabs: function() {
            // Show first tab content by default
            $('.bkx-form-tab-content').first().addClass('active');
        },

        /**
         * Open add webhook modal.
         */
        openAddModal: function(e) {
            e.preventDefault();

            // Reset form
            $('#bkx-webhook-form')[0].reset();
            $('#webhook_id').val('');
            $('#bkx-modal-title').text(bkxWebhooks.strings.add_webhook);
            $('.bkx-secret-row').hide();

            // Reset tabs
            $('.bkx-form-tab').first().trigger('click');

            // Clear custom headers except first
            $('#webhook_headers_container').find('.bkx-header-row:not(:first)').remove();
            $('#webhook_headers_container .bkx-header-row input').val('');

            $('#bkx-webhook-modal').show();
        },

        /**
         * Open edit webhook modal.
         */
        openEditModal: function(e) {
            e.preventDefault();

            const webhookId = $(e.currentTarget).data('id');

            $.ajax({
                url: bkxWebhooks.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_get_webhook',
                    webhook_id: webhookId,
                    nonce: bkxWebhooks.nonce
                },
                beforeSend: function() {
                    $('#bkx-webhook-modal').show();
                    $('.bkx-modal-body').addClass('loading');
                },
                success: function(response) {
                    $('.bkx-modal-body').removeClass('loading');

                    if (response.success) {
                        BKXWebhooks.populateForm(response.data);
                        $('#bkx-modal-title').text(bkxWebhooks.strings.edit_webhook);
                        $('.bkx-secret-row').show();
                    } else {
                        alert(response.data.message || bkxWebhooks.strings.error);
                        BKXWebhooks.closeModal();
                    }
                },
                error: function() {
                    $('.bkx-modal-body').removeClass('loading');
                    alert(bkxWebhooks.strings.error);
                    BKXWebhooks.closeModal();
                }
            });
        },

        /**
         * Populate form with webhook data.
         */
        populateForm: function(webhook) {
            $('#webhook_id').val(webhook.id);
            $('#webhook_name').val(webhook.name);
            $('#webhook_url').val(webhook.url);
            $('#webhook_status').val(webhook.status);
            $('#webhook_secret_display').text(webhook.secret);
            $('#webhook_method').val(webhook.http_method);
            $('#webhook_format').val(webhook.payload_format);
            $('#webhook_timeout').val(webhook.timeout);
            $('#webhook_retry_count').val(webhook.retry_count);
            $('#webhook_retry_delay').val(webhook.retry_delay);
            $('#webhook_verify_ssl').prop('checked', webhook.verify_ssl);
            $('#webhook_start_time').val(webhook.active_start_time || '');
            $('#webhook_end_time').val(webhook.active_end_time || '');

            // Clear and set events
            $('input[name="events[]"]').prop('checked', false);
            if (webhook.events && webhook.events.length) {
                webhook.events.forEach(function(event) {
                    $('input[name="events[]"][value="' + event + '"]').prop('checked', true);
                });
            }

            // Update category states
            $('.bkx-category-toggle').each(function() {
                BKXWebhooks.updateCategoryState.call(this);
            });

            // Set active days
            $('input[name="active_days[]"]').prop('checked', false);
            if (webhook.active_days && webhook.active_days.length) {
                webhook.active_days.forEach(function(day) {
                    $('input[name="active_days[]"][value="' + day + '"]').prop('checked', true);
                });
            }

            // Set custom headers
            $('#webhook_headers_container').find('.bkx-header-row:not(:first)').remove();
            const firstRow = $('#webhook_headers_container .bkx-header-row').first();
            firstRow.find('input').val('');

            if (webhook.headers && Object.keys(webhook.headers).length) {
                let first = true;
                Object.entries(webhook.headers).forEach(function([key, value]) {
                    if (first) {
                        firstRow.find('input[name="header_key[]"]').val(key);
                        firstRow.find('input[name="header_value[]"]').val(value);
                        first = false;
                    } else {
                        BKXWebhooks.addHeaderRow(key, value);
                    }
                });
            }
        },

        /**
         * Save webhook.
         */
        saveWebhook: function(e) {
            e.preventDefault();

            const form = $(e.currentTarget);
            const webhookId = $('#webhook_id').val();

            // Collect form data
            const data = {
                action: webhookId ? 'bkx_update_webhook' : 'bkx_save_webhook',
                nonce: bkxWebhooks.nonce,
                webhook_id: webhookId,
                name: $('#webhook_name').val(),
                url: $('#webhook_url').val(),
                status: $('#webhook_status').val(),
                http_method: $('#webhook_method').val(),
                payload_format: $('#webhook_format').val(),
                timeout: $('#webhook_timeout').val(),
                retry_count: $('#webhook_retry_count').val(),
                retry_delay: $('#webhook_retry_delay').val(),
                verify_ssl: $('#webhook_verify_ssl').is(':checked') ? 1 : 0,
                active_start_time: $('#webhook_start_time').val(),
                active_end_time: $('#webhook_end_time').val(),
                events: [],
                active_days: [],
                headers: {}
            };

            // Collect events
            $('input[name="events[]"]:checked').each(function() {
                data.events.push($(this).val());
            });

            // Collect active days
            $('input[name="active_days[]"]:checked').each(function() {
                data.active_days.push($(this).val());
            });

            // Collect custom headers
            $('#webhook_headers_container .bkx-header-row').each(function() {
                const key = $(this).find('input[name="header_key[]"]').val();
                const value = $(this).find('input[name="header_value[]"]').val();
                if (key && value) {
                    data.headers[key] = value;
                }
            });

            $.ajax({
                url: bkxWebhooks.ajaxurl,
                type: 'POST',
                data: data,
                beforeSend: function() {
                    $('#bkx-save-webhook').prop('disabled', true).text(bkxWebhooks.strings.saving);
                },
                success: function(response) {
                    $('#bkx-save-webhook').prop('disabled', false).text(bkxWebhooks.strings.save);

                    if (response.success) {
                        BKXWebhooks.closeModal();
                        location.reload();
                    } else {
                        alert(response.data.message || bkxWebhooks.strings.error);
                    }
                },
                error: function() {
                    $('#bkx-save-webhook').prop('disabled', false).text(bkxWebhooks.strings.save);
                    alert(bkxWebhooks.strings.error);
                }
            });
        },

        /**
         * Delete webhook.
         */
        deleteWebhook: function(e) {
            e.preventDefault();

            if (!confirm(bkxWebhooks.strings.confirm_delete)) {
                return;
            }

            const webhookId = $(e.currentTarget).data('id');

            $.ajax({
                url: bkxWebhooks.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_delete_webhook',
                    webhook_id: webhookId,
                    nonce: bkxWebhooks.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('tr[data-webhook-id="' + webhookId + '"]').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || bkxWebhooks.strings.error);
                    }
                },
                error: function() {
                    alert(bkxWebhooks.strings.error);
                }
            });
        },

        /**
         * Toggle webhook status.
         */
        toggleWebhook: function(e) {
            e.preventDefault();

            const button = $(e.currentTarget);
            const webhookId = button.data('id');

            $.ajax({
                url: bkxWebhooks.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_toggle_webhook',
                    webhook_id: webhookId,
                    nonce: bkxWebhooks.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || bkxWebhooks.strings.error);
                    }
                },
                error: function() {
                    alert(bkxWebhooks.strings.error);
                }
            });
        },

        /**
         * Test webhook.
         */
        testWebhook: function(e) {
            e.preventDefault();

            const webhookId = $(e.currentTarget).data('id');

            $('#bkx-test-loading').show();
            $('#bkx-test-result').hide();
            $('#bkx-test-modal').show();

            $.ajax({
                url: bkxWebhooks.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_test_webhook',
                    webhook_id: webhookId,
                    nonce: bkxWebhooks.nonce
                },
                success: function(response) {
                    $('#bkx-test-loading').hide();
                    $('#bkx-test-result').show();

                    if (response.success && response.data.success) {
                        $('.bkx-test-status').removeClass('error').addClass('success').text(bkxWebhooks.strings.test_success);
                    } else {
                        $('.bkx-test-status').removeClass('success').addClass('error').text(bkxWebhooks.strings.test_failed);
                    }

                    $('#bkx-test-code').text(response.data.response_code || '-');
                    $('#bkx-test-time').text(response.data.response_time ? response.data.response_time + ' ms' : '-');

                    if (response.data.error) {
                        $('#bkx-test-error').text(response.data.error);
                        $('#bkx-test-error-row').show();
                    } else {
                        $('#bkx-test-error-row').hide();
                    }
                },
                error: function() {
                    $('#bkx-test-loading').hide();
                    $('#bkx-test-result').show();
                    $('.bkx-test-status').removeClass('success').addClass('error').text(bkxWebhooks.strings.error);
                }
            });
        },

        /**
         * Regenerate secret.
         */
        regenerateSecret: function(e) {
            e.preventDefault();

            if (!confirm(bkxWebhooks.strings.confirm_regenerate)) {
                return;
            }

            const webhookId = $('#webhook_id').val();
            if (!webhookId) {
                return;
            }

            $.ajax({
                url: bkxWebhooks.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_regenerate_secret',
                    webhook_id: webhookId,
                    nonce: bkxWebhooks.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#webhook_secret_display').text(response.data.secret);
                    } else {
                        alert(response.data.message || bkxWebhooks.strings.error);
                    }
                },
                error: function() {
                    alert(bkxWebhooks.strings.error);
                }
            });
        },

        /**
         * Close modal.
         */
        closeModal: function(e) {
            if (e) e.preventDefault();
            $('.bkx-modal').hide();
        },

        /**
         * Handle modal background click.
         */
        handleModalClick: function(e) {
            if ($(e.target).hasClass('bkx-modal')) {
                this.closeModal();
            }
        },

        /**
         * Switch form tab.
         */
        switchFormTab: function(e) {
            e.preventDefault();

            const tab = $(e.currentTarget).data('tab');

            $('.bkx-form-tab').removeClass('active');
            $(e.currentTarget).addClass('active');

            $('.bkx-form-tab-content').removeClass('active');
            $('.bkx-form-tab-content[data-tab="' + tab + '"]').addClass('active');
        },

        /**
         * Toggle event category.
         */
        toggleCategory: function(e) {
            const category = $(e.currentTarget).data('category');
            const checked = $(e.currentTarget).is(':checked');

            $('input[name="events[]"][data-category="' + category + '"]').prop('checked', checked);
        },

        /**
         * Update category checkbox state.
         */
        updateCategoryState: function() {
            const checkbox = $(this);
            const category = checkbox.data('category');

            if (!category) return;

            const total = $('input[name="events[]"][data-category="' + category + '"]').length;
            const checked = $('input[name="events[]"][data-category="' + category + '"]:checked').length;

            const categoryToggle = $('.bkx-category-toggle[data-category="' + category + '"]');
            categoryToggle.prop('checked', checked === total);
            categoryToggle.prop('indeterminate', checked > 0 && checked < total);
        },

        /**
         * Add custom header row.
         */
        addHeader: function(e) {
            e.preventDefault();
            this.addHeaderRow('', '');
        },

        /**
         * Add header row with values.
         */
        addHeaderRow: function(key, value) {
            const row = $(`
                <div class="bkx-header-row">
                    <input type="text" name="header_key[]" placeholder="${bkxWebhooks.strings.header_name}" class="regular-text" value="${key}">
                    <input type="text" name="header_value[]" placeholder="${bkxWebhooks.strings.header_value}" class="regular-text" value="${value}">
                    <button type="button" class="button bkx-remove-header">&times;</button>
                </div>
            `);
            $('#webhook_headers_container').append(row);
        },

        /**
         * Remove header row.
         */
        removeHeader: function(e) {
            e.preventDefault();

            const container = $('#webhook_headers_container');
            if (container.find('.bkx-header-row').length > 1) {
                $(e.currentTarget).closest('.bkx-header-row').remove();
            } else {
                $(e.currentTarget).closest('.bkx-header-row').find('input').val('');
            }
        },

        /**
         * View delivery details.
         */
        viewDelivery: function(e) {
            e.preventDefault();

            const deliveryId = $(e.currentTarget).data('id');

            $('#bkx-delivery-loading').show();
            $('#bkx-delivery-content').hide();
            $('#bkx-delivery-modal').show();

            $.ajax({
                url: bkxWebhooks.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_get_delivery',
                    delivery_id: deliveryId,
                    nonce: bkxWebhooks.nonce
                },
                success: function(response) {
                    $('#bkx-delivery-loading').hide();
                    $('#bkx-delivery-content').show();

                    if (response.success) {
                        const delivery = response.data;

                        // Format and display data
                        $('#bkx-request-headers').text(BKXWebhooks.formatJson(delivery.request_headers));
                        $('#bkx-request-payload').text(BKXWebhooks.formatJson(delivery.payload));
                        $('#bkx-response-headers').text(BKXWebhooks.formatJson(delivery.response_headers));
                        $('#bkx-response-body').text(delivery.response_body || '-');

                        // Show first tab
                        $('.bkx-delivery-tab').first().trigger('click');
                    } else {
                        alert(response.data.message || bkxWebhooks.strings.error);
                        BKXWebhooks.closeModal();
                    }
                },
                error: function() {
                    alert(bkxWebhooks.strings.error);
                    BKXWebhooks.closeModal();
                }
            });
        },

        /**
         * Retry delivery.
         */
        retryDelivery: function(e) {
            e.preventDefault();

            const deliveryId = $(e.currentTarget).data('id');

            $.ajax({
                url: bkxWebhooks.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_retry_delivery',
                    delivery_id: deliveryId,
                    nonce: bkxWebhooks.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || bkxWebhooks.strings.error);
                    }
                },
                error: function() {
                    alert(bkxWebhooks.strings.error);
                }
            });
        },

        /**
         * Switch delivery detail tab.
         */
        switchDeliveryTab: function(e) {
            e.preventDefault();

            const tab = $(e.currentTarget).data('tab');

            $('.bkx-delivery-tab').removeClass('active');
            $(e.currentTarget).addClass('active');

            $('.bkx-delivery-tab-content').removeClass('active');
            $('.bkx-delivery-tab-content[data-tab="' + tab + '"]').addClass('active');
        },

        /**
         * Switch code example tab.
         */
        switchCodeTab: function(e) {
            e.preventDefault();

            const lang = $(e.currentTarget).data('lang');

            $('.bkx-code-tab').removeClass('active');
            $(e.currentTarget).addClass('active');

            $('.bkx-code-example').hide();
            $('.bkx-code-example[data-lang="' + lang + '"]').show();
        },

        /**
         * Save settings.
         */
        saveSettings: function(e) {
            e.preventDefault();

            const form = $(e.currentTarget);

            $.ajax({
                url: bkxWebhooks.ajaxurl,
                type: 'POST',
                data: form.serialize() + '&action=bkx_save_webhooks_settings&nonce=' + bkxWebhooks.nonce,
                beforeSend: function() {
                    form.find('input[type="submit"]').prop('disabled', true).val(bkxWebhooks.strings.saving);
                },
                success: function(response) {
                    form.find('input[type="submit"]').prop('disabled', false).val(bkxWebhooks.strings.save_settings);

                    if (response.success) {
                        // Show success notice
                        const notice = $('<div class="notice notice-success is-dismissible"><p>' + bkxWebhooks.strings.settings_saved + '</p></div>');
                        form.before(notice);
                        setTimeout(function() {
                            notice.fadeOut();
                        }, 3000);
                    } else {
                        alert(response.data.message || bkxWebhooks.strings.error);
                    }
                },
                error: function() {
                    form.find('input[type="submit"]').prop('disabled', false).val(bkxWebhooks.strings.save_settings);
                    alert(bkxWebhooks.strings.error);
                }
            });
        },

        /**
         * Format JSON for display.
         */
        formatJson: function(data) {
            if (typeof data === 'string') {
                try {
                    data = JSON.parse(data);
                } catch (e) {
                    return data;
                }
            }
            return JSON.stringify(data, null, 2);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BKXWebhooks.init();
    });

})(jQuery);

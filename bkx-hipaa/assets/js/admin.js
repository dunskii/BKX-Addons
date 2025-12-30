/**
 * BookingX HIPAA Compliance - Admin JavaScript
 *
 * @package BookingX\HIPAA
 */

(function($) {
    'use strict';

    var BKX_HIPAA = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#bkx-hipaa-settings-form').on('submit', this.saveSettings);
            $('#bkx-export-audit').on('click', this.exportAudit);
            $('#bkx-access-form').on('submit', this.grantAccess);
            $('#bkx-baa-form').on('submit', this.createBAA);
            $(document).on('click', '.bkx-revoke-access', this.revokeAccess);
            $(document).on('click', '.bkx-revoke-baa', this.revokeBAA);
        },

        saveSettings: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $spinner = $form.find('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: bkxHipaaAdmin.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=bkx_hipaa_save_settings&nonce=' + bkxHipaaAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        BKX_HIPAA.showNotice('success', bkxHipaaAdmin.strings.settingsSaved);
                    } else {
                        BKX_HIPAA.showNotice('error', response.data || bkxHipaaAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_HIPAA.showNotice('error', bkxHipaaAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        exportAudit: function() {
            var $button = $(this);
            $button.prop('disabled', true);

            var dateFrom = $('input[name="date_from"]').val() || '';
            var dateTo = $('input[name="date_to"]').val() || '';

            $.ajax({
                url: bkxHipaaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_hipaa_export_audit',
                    nonce: bkxHipaaAdmin.nonce,
                    date_from: dateFrom,
                    date_to: dateTo
                },
                success: function(response) {
                    if (response.success) {
                        BKX_HIPAA.downloadCSV(response.data.logs, response.data.filename);
                        BKX_HIPAA.showNotice('success', bkxHipaaAdmin.strings.exportSuccess);
                    } else {
                        BKX_HIPAA.showNotice('error', response.data || bkxHipaaAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_HIPAA.showNotice('error', bkxHipaaAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        downloadCSV: function(logs, filename) {
            var csv = 'Date,Event Type,Event Action,User ID,IP Address,Resource Type,Resource ID,PHI Accessed\n';

            logs.forEach(function(log) {
                csv += '"' + log.created_at + '",';
                csv += '"' + log.event_type + '",';
                csv += '"' + log.event_action + '",';
                csv += '"' + (log.user_id || '') + '",';
                csv += '"' + (log.user_ip || '') + '",';
                csv += '"' + (log.resource_type || '') + '",';
                csv += '"' + (log.resource_id || '') + '",';
                csv += '"' + (log.phi_accessed ? 'Yes' : 'No') + '"\n';
            });

            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            if (link.download !== undefined) {
                var url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        },

        grantAccess: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $spinner = $form.find('.spinner');

            var phiFields = [];
            $form.find('input[name="phi_fields[]"]:checked').each(function() {
                phiFields.push($(this).val());
            });

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: bkxHipaaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_hipaa_update_access',
                    nonce: bkxHipaaAdmin.nonce,
                    user_id: $form.find('#bkx-access-user').val(),
                    access_level: $form.find('#bkx-access-level').val(),
                    phi_fields: phiFields
                },
                success: function(response) {
                    if (response.success) {
                        BKX_HIPAA.showNotice('success', response.data);
                        location.reload();
                    } else {
                        BKX_HIPAA.showNotice('error', response.data || bkxHipaaAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_HIPAA.showNotice('error', bkxHipaaAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        revokeAccess: function() {
            if (!confirm(bkxHipaaAdmin.strings.confirmDelete)) {
                return;
            }

            var $button = $(this);
            var userId = $button.data('user-id');

            $button.prop('disabled', true);

            $.ajax({
                url: bkxHipaaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_hipaa_update_access',
                    nonce: bkxHipaaAdmin.nonce,
                    user_id: userId,
                    access_level: 'none',
                    phi_fields: []
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        BKX_HIPAA.showNotice('error', response.data || bkxHipaaAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_HIPAA.showNotice('error', bkxHipaaAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        createBAA: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var $spinner = $form.find('.spinner');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: bkxHipaaAdmin.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=bkx_hipaa_create_baa&nonce=' + bkxHipaaAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        BKX_HIPAA.showNotice('success', response.data);
                        location.reload();
                    } else {
                        BKX_HIPAA.showNotice('error', response.data || bkxHipaaAdmin.strings.error);
                    }
                },
                error: function() {
                    BKX_HIPAA.showNotice('error', bkxHipaaAdmin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },

        revokeBAA: function() {
            if (!confirm(bkxHipaaAdmin.strings.confirmDelete)) {
                return;
            }

            var $button = $(this);
            var baaId = $button.data('baa-id');

            $button.prop('disabled', true);

            // This would call an AJAX endpoint to revoke the BAA
            BKX_HIPAA.showNotice('info', 'BAA revocation would be processed here.');
            $button.prop('disabled', false);
        },

        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.bkx-hipaa-admin h1').after($notice);
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    $(document).ready(function() {
        BKX_HIPAA.init();
    });

})(jQuery);

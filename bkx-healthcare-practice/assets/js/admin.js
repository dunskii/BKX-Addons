/**
 * Healthcare Practice Admin JavaScript
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Healthcare Admin Module
     */
    const BkxHealthcareAdmin = {
        /**
         * Initialize the module.
         */
        init: function() {
            this.initConditionalFields();
            this.initProviderSettings();
            this.initCredentialsManager();
            this.initIntakeFormBuilder();
            this.initPatientRecords();
            this.initAuditLog();
        },

        /**
         * Initialize conditional field visibility.
         */
        initConditionalFields: function() {
            const conditionalSettings = {
                'enable_patient_intake': ['default_intake_form', 'intake_required_before_booking'],
                'enable_consent_forms': ['require_consent_before_booking', 'consent_expiry_reminder_days'],
                'enable_telemedicine': ['telemedicine_provider', 'telemedicine_early_join'],
                'enable_insurance_verification': ['insurance_api_provider'],
                'enable_patient_portal': ['portal_page_id'],
                'enable_appointment_reminders': ['reminder_hours', 'reminder_methods']
            };

            $.each(conditionalSettings, function(parentField, childRows) {
                const $parent = $('input[name="bkx_healthcare_settings[' + parentField + ']"]');

                if (!$parent.length) return;

                function toggleFields() {
                    const isChecked = $parent.is(':checked');

                    $.each(childRows, function(i, childField) {
                        const $row = $('[name*="' + childField + '"]').closest('tr');
                        $row.toggle(isChecked);
                    });
                }

                $parent.on('change', toggleFields);
                toggleFields();
            });

            // Telemedicine provider settings.
            const $providerSelect = $('select[name="bkx_healthcare_settings[telemedicine_provider]"]');

            if ($providerSelect.length) {
                function toggleProviderSettings() {
                    const provider = $providerSelect.val();

                    $('.bkx-provider-settings').hide();
                    $('.bkx-provider-' + provider).show();
                }

                $providerSelect.on('change', toggleProviderSettings);
                toggleProviderSettings();
            }
        },

        /**
         * Initialize provider settings on Providers post type.
         */
        initProviderSettings: function() {
            // Toggle availability inputs based on enabled checkbox.
            $('.bkx-availability-table input[type="checkbox"]').on('change', function() {
                const $row = $(this).closest('tr');
                const isEnabled = $(this).is(':checked');

                $row.find('input[type="time"]').prop('disabled', !isEnabled);
            }).trigger('change');
        },

        /**
         * Initialize credentials manager.
         */
        initCredentialsManager: function() {
            const $list = $('#bkx-credentials-list');

            if (!$list.length) return;

            let credentialIndex = $list.find('.bkx-credential-item').length;

            // Add credential.
            $('.bkx-add-credential').on('click', function() {
                const template = $('#bkx-credential-template').html();
                const html = template.replace(/\{\{index\}\}/g, credentialIndex);
                $list.append(html);
                credentialIndex++;
            });

            // Remove credential.
            $(document).on('click', '.bkx-remove-credential', function() {
                $(this).closest('.bkx-credential-item').fadeOut(200, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Initialize intake form builder.
         */
        initIntakeFormBuilder: function() {
            const $list = $('#bkx-form-fields-list');

            if (!$list.length) return;

            let fieldIndex = $list.find('.bkx-field-row').length;

            // Add field.
            $('#bkx-add-field').on('click', function() {
                const template = $('#bkx-field-row-template').html();
                const html = template.replace(/\{\{index\}\}/g, fieldIndex);
                $list.append(html);
                fieldIndex++;
            });

            // Remove field.
            $(document).on('click', '.bkx-remove-field', function(e) {
                e.stopPropagation();

                if (!confirm(bkxHealthcareAdmin.i18n.confirmDelete)) {
                    return;
                }

                $(this).closest('.bkx-field-row').fadeOut(200, function() {
                    $(this).remove();
                });
            });

            // Toggle field settings.
            $(document).on('click', '.bkx-toggle-field-settings, .bkx-field-row-header', function(e) {
                if ($(e.target).hasClass('bkx-remove-field')) return;

                const $row = $(this).closest('.bkx-field-row');
                $row.find('.bkx-field-row-settings').slideToggle(200);
                $row.find('.bkx-toggle-field-settings .dashicons')
                    .toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
            });

            // Update preview when label changes.
            $(document).on('input', '.bkx-field-label-input', function() {
                const $row = $(this).closest('.bkx-field-row');
                $row.find('.bkx-field-label-preview').text($(this).val() || 'New Field');
            });

            // Update type preview and show/hide options.
            $(document).on('change', '.bkx-field-type-select', function() {
                const $row = $(this).closest('.bkx-field-row');
                const type = $(this).val();
                const typeLabel = $(this).find('option:selected').text();

                $row.find('.bkx-field-type-preview').text(typeLabel);

                // Show/hide options field.
                const showOptions = ['select', 'radio', 'checkboxes'].includes(type);
                $row.find('.bkx-options-setting').toggle(showOptions);
            });

            // Make fields sortable.
            if (typeof $.fn.sortable !== 'undefined') {
                $list.sortable({
                    handle: '.bkx-field-drag-handle',
                    placeholder: 'bkx-field-row ui-sortable-placeholder',
                    update: function() {
                        // Re-index fields after sorting.
                        $list.find('.bkx-field-row').each(function(index) {
                            $(this).find('input, select, textarea').each(function() {
                                const name = $(this).attr('name');
                                if (name) {
                                    const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                                    $(this).attr('name', newName);
                                }
                            });
                        });
                    }
                });
            }

            // Load template.
            $(document).on('click', '.bkx-load-template', function() {
                if ($list.find('.bkx-field-row').length > 0) {
                    if (!confirm('This will replace existing fields. Continue?')) {
                        return;
                    }
                }

                const template = $(this).data('template');
                // Template loading would be handled via AJAX in real implementation.
                alert('Template "' + template + '" would be loaded. This requires AJAX implementation.');
            });
        },

        /**
         * Initialize patient records functionality.
         */
        initPatientRecords: function() {
            const self = this;

            // Patient search.
            let searchTimeout;
            $(document).on('input', '.bkx-patient-search-input', function() {
                const $input = $(this);
                const search = $input.val();

                clearTimeout(searchTimeout);

                if (search.length < 2) {
                    return;
                }

                searchTimeout = setTimeout(function() {
                    self.searchPatients(search);
                }, 300);
            });

            // View patient history.
            $(document).on('click', '.bkx-view-patient', function(e) {
                e.preventDefault();
                const patientId = $(this).data('patient-id');
                self.viewPatientHistory(patientId);
            });

            // Export patient data.
            $(document).on('click', '.bkx-export-patient', function(e) {
                e.preventDefault();
                const patientId = $(this).data('patient-id');
                self.exportPatientData(patientId);
            });

            // View intake details.
            $(document).on('click', '.bkx-view-intake', function(e) {
                e.preventDefault();
                const intakeId = $(this).data('intake-id');
                self.viewIntakeDetails(intakeId);
            });
        },

        /**
         * Search patients.
         */
        searchPatients: function(search) {
            const $results = $('.bkx-patient-list');

            $.ajax({
                url: bkxHealthcareAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_search_patients',
                    nonce: bkxHealthcareAdmin.nonce,
                    search: search
                },
                beforeSend: function() {
                    $results.html('<div class="bkx-loading">Searching...</div>');
                },
                success: function(response) {
                    if (response.success && response.data.length) {
                        let html = '';
                        $.each(response.data, function(i, patient) {
                            html += '<div class="bkx-patient-row">';
                            html += '<div class="bkx-patient-info">';
                            html += '<div class="bkx-patient-name">' + patient.name + '</div>';
                            html += '<div class="bkx-patient-email">' + patient.email + '</div>';
                            html += '</div>';
                            html += '<div class="bkx-patient-actions">';
                            html += '<button class="button bkx-view-patient" data-patient-id="' + patient.id + '">View</button>';
                            html += '<button class="button bkx-export-patient" data-patient-id="' + patient.id + '">Export</button>';
                            html += '</div>';
                            html += '</div>';
                        });
                        $results.html(html);
                    } else {
                        $results.html('<div class="bkx-no-results">No patients found</div>');
                    }
                }
            });
        },

        /**
         * View patient history.
         */
        viewPatientHistory: function(patientId) {
            $.ajax({
                url: bkxHealthcareAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_get_patient_history',
                    nonce: bkxHealthcareAdmin.nonce,
                    patient_id: patientId
                },
                success: function(response) {
                    if (response.success) {
                        // Display patient history in modal or panel.
                        console.log('Patient history:', response.data);
                        // Implementation would show a modal with the data.
                    } else {
                        alert(response.data.message || bkxHealthcareAdmin.i18n.error);
                    }
                }
            });
        },

        /**
         * Export patient data.
         */
        exportPatientData: function(patientId) {
            $.ajax({
                url: bkxHealthcareAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_export_patient_data',
                    nonce: bkxHealthcareAdmin.nonce,
                    patient_id: patientId
                },
                success: function(response) {
                    if (response.success) {
                        // Create download.
                        const blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'patient-' + patientId + '-data.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                    } else {
                        alert(response.data.message || bkxHealthcareAdmin.i18n.error);
                    }
                }
            });
        },

        /**
         * View intake details.
         */
        viewIntakeDetails: function(intakeId) {
            // Would open a modal showing full intake form data.
            console.log('View intake:', intakeId);
        },

        /**
         * Initialize audit log functionality.
         */
        initAuditLog: function() {
            // Filter audit log.
            $('.bkx-audit-filters select, .bkx-audit-filters input').on('change', function() {
                $(this).closest('form').submit();
            });

            // Export audit log.
            $(document).on('click', '.bkx-export-audit-log', function(e) {
                e.preventDefault();

                const filters = $('.bkx-audit-filters').serialize();

                window.location.href = bkxHealthcareAdmin.ajaxUrl + '?action=bkx_export_audit_log&' + filters + '&nonce=' + bkxHealthcareAdmin.nonce;
            });
        }
    };

    /**
     * License Management
     */
    const BkxHealthcareLicense = {
        init: function() {
            $('.bkx-activate-license').on('click', this.toggleLicense.bind(this));
        },

        toggleLicense: function(e) {
            const $btn = $(e.target);
            const $input = $('#bkx_healthcare_license_key');
            const licenseKey = $input.val();
            const isDeactivate = $btn.text().includes('Deactivate');

            if (!licenseKey && !isDeactivate) {
                alert('Please enter a license key');
                return;
            }

            $btn.prop('disabled', true).text('Processing...');

            $.ajax({
                url: bkxHealthcareAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: isDeactivate ? 'bkx_healthcare_deactivate_license' : 'bkx_healthcare_activate_license',
                    nonce: bkxHealthcareAdmin.nonce,
                    license_key: licenseKey
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || 'License operation failed');
                    }
                },
                error: function() {
                    alert('An error occurred');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(isDeactivate ? 'Deactivate' : 'Activate');
                }
            });
        }
    };

    /**
     * Initialize on document ready.
     */
    $(document).ready(function() {
        BkxHealthcareAdmin.init();
        BkxHealthcareLicense.init();
    });

})(jQuery);

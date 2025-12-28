/**
 * Healthcare Practice Frontend JavaScript
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Healthcare Frontend Module
     */
    const BkxHealthcareFrontend = {
        /**
         * Initialize the module.
         */
        init: function() {
            this.initPortalTabs();
            this.initIntakeForms();
            this.initConsentForms();
            this.initTelemedicine();
            this.initPatientPortal();
        },

        /**
         * Initialize portal tabs.
         */
        initPortalTabs: function() {
            const $tabs = $('.bkx-portal-tabs');

            if (!$tabs.length) return;

            $tabs.on('click', '.bkx-tab-btn', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const tabId = $btn.data('tab');

                // Update active states.
                $tabs.find('.bkx-tab-btn').removeClass('active');
                $btn.addClass('active');

                // Show corresponding pane.
                $('.bkx-tab-pane').removeClass('active');
                $('#tab-' + tabId).addClass('active');
            });
        },

        /**
         * Initialize intake forms.
         */
        initIntakeForms: function() {
            const self = this;

            // Form submission.
            $(document).on('submit', '.bkx-intake-form', function(e) {
                e.preventDefault();
                self.submitIntakeForm($(this));
            });

            // Save as draft.
            $(document).on('click', '.bkx-save-draft', function(e) {
                e.preventDefault();
                const $form = $(this).closest('.bkx-intake-form');
                self.submitIntakeForm($form, true);
            });

            // Add medication.
            $(document).on('click', '.bkx-add-medication', function() {
                const $list = $(this).siblings('.bkx-medications-list');
                const fieldName = $list.data('field');
                const index = $list.find('.bkx-medication-item').length;

                const html = `
                    <div class="bkx-medication-item">
                        <input type="text" name="form_data[${fieldName}][${index}][name]"
                               placeholder="${bkxHealthcare.i18n.medicationName || 'Medication name'}">
                        <input type="text" name="form_data[${fieldName}][${index}][dosage]"
                               placeholder="${bkxHealthcare.i18n.dosage || 'Dosage'}">
                        <input type="text" name="form_data[${fieldName}][${index}][frequency]"
                               placeholder="${bkxHealthcare.i18n.frequency || 'Frequency'}">
                        <button type="button" class="bkx-remove-medication">&times;</button>
                    </div>
                `;

                $list.append(html);
            });

            // Remove medication.
            $(document).on('click', '.bkx-remove-medication', function() {
                $(this).closest('.bkx-medication-item').fadeOut(200, function() {
                    $(this).remove();
                });
            });

            // Add allergy.
            $(document).on('click', '.bkx-add-allergy', function() {
                const $list = $(this).siblings('.bkx-allergies-list');
                const fieldName = $list.data('field');
                const index = $list.find('.bkx-allergy-item').length;

                const html = `
                    <div class="bkx-allergy-item">
                        <input type="text" name="form_data[${fieldName}][${index}][allergen]"
                               placeholder="${bkxHealthcare.i18n.allergen || 'Allergen'}">
                        <select name="form_data[${fieldName}][${index}][severity]">
                            <option value="mild">${bkxHealthcare.i18n.mild || 'Mild'}</option>
                            <option value="moderate">${bkxHealthcare.i18n.moderate || 'Moderate'}</option>
                            <option value="severe">${bkxHealthcare.i18n.severe || 'Severe'}</option>
                        </select>
                        <input type="text" name="form_data[${fieldName}][${index}][reaction]"
                               placeholder="${bkxHealthcare.i18n.reaction || 'Reaction type'}">
                        <button type="button" class="bkx-remove-allergy">&times;</button>
                    </div>
                `;

                $list.append(html);
            });

            // Remove allergy.
            $(document).on('click', '.bkx-remove-allergy', function() {
                $(this).closest('.bkx-allergy-item').fadeOut(200, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Submit intake form.
         */
        submitIntakeForm: function($form, isDraft = false) {
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();

            // Validate required fields.
            if (!isDraft && !this.validateForm($form)) {
                return;
            }

            $submitBtn.prop('disabled', true).text(bkxHealthcare.i18n.submitting);

            const formData = new FormData($form[0]);
            formData.append('action', 'bkx_submit_intake_form');
            formData.append('nonce', bkxHealthcare.nonce);
            formData.append('is_draft', isDraft ? '1' : '0');

            $.ajax({
                url: bkxHealthcare.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        if (isDraft) {
                            alert(bkxHealthcare.i18n.draftSaved || 'Draft saved');
                        } else {
                            $form.html('<div class="bkx-success">' +
                                (response.data.message || bkxHealthcare.i18n.formComplete) +
                                '</div>');
                        }
                    } else {
                        alert(response.data.message || bkxHealthcare.i18n.error);
                    }
                },
                error: function() {
                    alert(bkxHealthcare.i18n.error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Validate form.
         */
        validateForm: function($form) {
            let isValid = true;

            $form.find('[required]').each(function() {
                const $field = $(this);
                const value = $field.val();

                if (!value || (Array.isArray(value) && value.length === 0)) {
                    isValid = false;
                    $field.addClass('bkx-field-error');
                } else {
                    $field.removeClass('bkx-field-error');
                }
            });

            if (!isValid) {
                $form.find('.bkx-field-error').first().focus();
            }

            return isValid;
        },

        /**
         * Initialize consent forms.
         */
        initConsentForms: function() {
            const self = this;

            // Initialize signature pads.
            this.initSignaturePads();

            // Form submission.
            $(document).on('submit', '.bkx-consent-submission', function(e) {
                e.preventDefault();
                self.submitConsentForm($(this));
            });

            // Print consent form.
            $(document).on('click', '.bkx-print-consent', function() {
                const $content = $(this).closest('.bkx-consent-form').find('.bkx-consent-content');
                const printWindow = window.open('', '_blank');

                printWindow.document.write('<html><head><title>Consent Form</title>');
                printWindow.document.write('<style>body { font-family: Arial, sans-serif; padding: 20px; }</style>');
                printWindow.document.write('</head><body>');
                printWindow.document.write($content.html());
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.print();
            });
        },

        /**
         * Initialize signature pads.
         */
        initSignaturePads: function() {
            $('.bkx-signature-pad').each(function() {
                const canvas = this;
                const $canvas = $(canvas);
                const formId = $canvas.attr('id').replace('bkx-signature-pad-', '');
                const $dataInput = $('#bkx-signature-data-' + formId);

                const ctx = canvas.getContext('2d');
                let isDrawing = false;
                let lastX = 0;
                let lastY = 0;

                // Set canvas size.
                canvas.width = $canvas.parent().width();
                canvas.height = 150;

                // Drawing functions.
                function startDrawing(e) {
                    isDrawing = true;
                    const coords = getCoords(e);
                    lastX = coords.x;
                    lastY = coords.y;
                }

                function draw(e) {
                    if (!isDrawing) return;

                    const coords = getCoords(e);

                    ctx.beginPath();
                    ctx.moveTo(lastX, lastY);
                    ctx.lineTo(coords.x, coords.y);
                    ctx.strokeStyle = '#000';
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.stroke();

                    lastX = coords.x;
                    lastY = coords.y;
                }

                function stopDrawing() {
                    if (isDrawing) {
                        isDrawing = false;
                        $dataInput.val(canvas.toDataURL());
                    }
                }

                function getCoords(e) {
                    const rect = canvas.getBoundingClientRect();
                    let x, y;

                    if (e.type.includes('touch')) {
                        x = e.touches[0].clientX - rect.left;
                        y = e.touches[0].clientY - rect.top;
                    } else {
                        x = e.clientX - rect.left;
                        y = e.clientY - rect.top;
                    }

                    return { x, y };
                }

                // Mouse events.
                $canvas.on('mousedown', startDrawing);
                $canvas.on('mousemove', draw);
                $canvas.on('mouseup mouseout', stopDrawing);

                // Touch events.
                $canvas.on('touchstart', function(e) {
                    e.preventDefault();
                    startDrawing(e.originalEvent);
                });
                $canvas.on('touchmove', function(e) {
                    e.preventDefault();
                    draw(e.originalEvent);
                });
                $canvas.on('touchend', stopDrawing);

                // Clear signature button.
                $canvas.closest('.bkx-signature-section').find('.bkx-clear-signature').on('click', function() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    $dataInput.val('');
                });
            });
        },

        /**
         * Submit consent form.
         */
        submitConsentForm: function($form) {
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            const formId = $form.find('input[name="form_id"]').val();
            const agreed = $form.find('input[name="consent_agreed"]').is(':checked');

            if (!agreed) {
                alert(bkxHealthcare.i18n.consentRequired);
                return;
            }

            // Check for signature if required.
            const $signatureInput = $form.find('input[name="signature"]');
            if ($signatureInput.length && !$signatureInput.val()) {
                alert(bkxHealthcare.i18n.signatureRequired || 'Signature is required');
                return;
            }

            $submitBtn.prop('disabled', true).text(bkxHealthcare.i18n.submitting);

            $.ajax({
                url: bkxHealthcare.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_submit_consent',
                    nonce: bkxHealthcare.nonce,
                    form_id: formId,
                    signature: $signatureInput.val(),
                    agreed: 'true'
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || bkxHealthcare.i18n.error);
                    }
                },
                error: function() {
                    alert(bkxHealthcare.i18n.error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Initialize telemedicine features.
         */
        initTelemedicine: function() {
            const self = this;

            // Join session button.
            $(document).on('click', '.bkx-join-session', function(e) {
                e.preventDefault();
                const bookingId = $(this).data('booking-id');
                self.startTelemedicineSession(bookingId);
            });

            // End session button.
            $(document).on('click', '.bkx-end-session', function(e) {
                e.preventDefault();

                if (!confirm('Are you sure you want to end this session?')) {
                    return;
                }

                const bookingId = $(this).data('booking-id');

                // End Jitsi session if active.
                if (window.bkxJitsiApi) {
                    window.bkxJitsiApi.executeCommand('hangup');
                }

                $.ajax({
                    url: bkxHealthcare.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'bkx_end_telemedicine',
                        nonce: bkxHealthcare.nonce,
                        booking_id: bookingId
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    }
                });
            });
        },

        /**
         * Start telemedicine session.
         */
        startTelemedicineSession: function(bookingId) {
            $.ajax({
                url: bkxHealthcare.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_start_telemedicine',
                    nonce: bkxHealthcare.nonce,
                    booking_id: bookingId
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.room_url;
                    } else {
                        alert(response.data.message || bkxHealthcare.i18n.error);
                    }
                },
                error: function() {
                    alert(bkxHealthcare.i18n.error);
                }
            });
        },

        /**
         * Initialize patient portal features.
         */
        initPatientPortal: function() {
            const self = this;

            // Profile form submission.
            $(document).on('submit', '#bkx-patient-profile-form', function(e) {
                e.preventDefault();
                self.savePatientProfile($(this));
            });

            // Export data button.
            $(document).on('click', '.bkx-export-data', function(e) {
                e.preventDefault();
                self.exportPatientData();
            });

            // Reschedule appointment.
            $(document).on('click', '.bkx-reschedule', function(e) {
                e.preventDefault();
                const bookingId = $(this).data('booking-id');
                // Redirect to booking form with reschedule parameter.
                window.location.href = '?reschedule=' + bookingId;
            });
        },

        /**
         * Save patient profile.
         */
        savePatientProfile: function($form) {
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();

            $submitBtn.prop('disabled', true).text(bkxHealthcare.i18n.saving || 'Saving...');

            $.ajax({
                url: bkxHealthcare.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=bkx_save_patient_profile&nonce=' + bkxHealthcare.nonce,
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || 'Profile saved successfully');
                    } else {
                        alert(response.data.message || bkxHealthcare.i18n.error);
                    }
                },
                error: function() {
                    alert(bkxHealthcare.i18n.error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Export patient data.
         */
        exportPatientData: function() {
            $.ajax({
                url: bkxHealthcare.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_export_my_data',
                    nonce: bkxHealthcare.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create download.
                        const blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'my-patient-data.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                    } else {
                        alert(response.data.message || bkxHealthcare.i18n.error);
                    }
                },
                error: function() {
                    alert(bkxHealthcare.i18n.error);
                }
            });
        }
    };

    /**
     * Insurance Verification Module
     */
    const BkxInsuranceVerification = {
        init: function() {
            $(document).on('submit', '.bkx-insurance-form', this.verifyInsurance.bind(this));
        },

        verifyInsurance: function(e) {
            e.preventDefault();

            const $form = $(e.target);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();

            $submitBtn.prop('disabled', true).text('Verifying...');

            $.ajax({
                url: bkxHealthcare.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=bkx_verify_insurance&nonce=' + bkxHealthcare.nonce,
                success: function(response) {
                    const $result = $form.find('.bkx-insurance-result');

                    if (response.success) {
                        if (response.data.status === 'pending') {
                            $result.html('<div class="bkx-notice bkx-notice-info">' + response.data.message + '</div>');
                        } else if (response.data.eligible) {
                            $result.html('<div class="bkx-notice bkx-notice-success">Insurance verified! Coverage is active.</div>');
                        } else {
                            $result.html('<div class="bkx-notice bkx-notice-warning">Coverage could not be verified. Please contact your insurance provider.</div>');
                        }
                    } else {
                        $result.html('<div class="bkx-notice bkx-notice-error">' + (response.data.message || 'Verification failed') + '</div>');
                    }
                },
                error: function() {
                    alert(bkxHealthcare.i18n.error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        }
    };

    /**
     * Initialize on document ready.
     */
    $(document).ready(function() {
        BkxHealthcareFrontend.init();
        BkxInsuranceVerification.init();
    });

})(jQuery);

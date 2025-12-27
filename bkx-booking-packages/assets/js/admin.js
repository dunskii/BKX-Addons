/**
 * Booking Packages Admin JavaScript
 *
 * @package BookingX\BookingPackages
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin Handler
     */
    var BKXPackagesAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Assign package to customer
            $(document).on('click', '.bkx-assign-package', function(e) {
                e.preventDefault();
                self.openAssignModal();
            });

            // Submit assign form
            $(document).on('submit', '#bkx-assign-package-form', function(e) {
                e.preventDefault();
                self.assignPackage($(this));
            });
        },

        /**
         * Open assign modal
         */
        openAssignModal: function() {
            var $modal = $('#bkx-assign-modal');

            if (!$modal.length) {
                var html = '<div id="bkx-assign-modal" class="bkx-modal">' +
                    '<div class="bkx-modal-overlay"></div>' +
                    '<div class="bkx-modal-content">' +
                    '<h3>Assign Package to Customer</h3>' +
                    '<form id="bkx-assign-package-form">' +
                    '<p>' +
                    '<label>Package:</label><br>' +
                    '<select name="package_id" required></select>' +
                    '</p>' +
                    '<p>' +
                    '<label>Customer Email:</label><br>' +
                    '<input type="email" name="customer_email" required>' +
                    '</p>' +
                    '<p>' +
                    '<button type="submit" class="button button-primary">Assign</button>' +
                    '<button type="button" class="button bkx-modal-close">Cancel</button>' +
                    '</p>' +
                    '</form>' +
                    '</div></div>';

                $('body').append(html);
                $modal = $('#bkx-assign-modal');

                $modal.on('click', '.bkx-modal-close, .bkx-modal-overlay', function() {
                    $modal.removeClass('is-open');
                });

                // Load packages
                this.loadPackagesForSelect($modal.find('select[name="package_id"]'));
            }

            $modal.addClass('is-open');
        },

        /**
         * Load packages for select
         *
         * @param {jQuery} $select Select element
         */
        loadPackagesForSelect: function($select) {
            $.ajax({
                url: bkxPackagesAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_get_packages_for_select',
                    nonce: bkxPackagesAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        $select.empty();
                        $.each(response.data, function(id, title) {
                            $select.append('<option value="' + id + '">' + title + '</option>');
                        });
                    }
                }
            });
        },

        /**
         * Assign package
         *
         * @param {jQuery} $form Form element
         */
        assignPackage: function($form) {
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();

            $button.prop('disabled', true).text('Assigning...');

            $.ajax({
                url: bkxPackagesAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_admin_assign_package',
                    nonce: bkxPackagesAdmin.nonce,
                    package_id: $form.find('select[name="package_id"]').val(),
                    customer_email: $form.find('input[name="customer_email"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        $('#bkx-assign-modal').removeClass('is-open');
                        location.reload();
                    } else {
                        alert(response.data?.message || 'Error assigning package');
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert('Error assigning package');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BKXPackagesAdmin.init();
    });

})(jQuery);

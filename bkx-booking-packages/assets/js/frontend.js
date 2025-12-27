/**
 * Booking Packages Frontend JavaScript
 *
 * @package BookingX\BookingPackages
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Packages Handler
     */
    var BKXPackages = {
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

            // Purchase package
            $(document).on('click', '.bkx-purchase-package', function(e) {
                e.preventDefault();
                var packageId = $(this).data('package-id');
                self.purchasePackage(packageId, $(this));
            });

            // View package details
            $(document).on('click', '.bkx-view-package', function(e) {
                e.preventDefault();
                var packageId = $(this).data('package-id');
                self.showPackageDetails(packageId);
            });

            // Package selector on booking form
            $(document).on('change', '.bkx-package-selector select', function() {
                var packageId = $(this).val();
                if (packageId) {
                    self.applyPackage(packageId, $(this).closest('form'));
                } else {
                    self.removePackage($(this).closest('form'));
                }
            });
        },

        /**
         * Purchase package
         *
         * @param {int} packageId Package ID
         * @param {jQuery} $button Button element
         */
        purchasePackage: function(packageId, $button) {
            var originalText = $button.text();
            $button.prop('disabled', true).text(bkxPackages.i18n.loading);

            $.ajax({
                url: bkxPackages.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_purchase_package',
                    nonce: bkxPackages.nonce,
                    package_id: packageId
                },
                success: function(response) {
                    if (response.success && response.data.checkout_url) {
                        window.location.href = response.data.checkout_url;
                    } else {
                        alert(response.data?.message || bkxPackages.i18n.error);
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    alert(bkxPackages.i18n.error);
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Show package details
         *
         * @param {int} packageId Package ID
         */
        showPackageDetails: function(packageId) {
            var self = this;

            $.ajax({
                url: bkxPackages.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_get_package_details',
                    nonce: bkxPackages.nonce,
                    package_id: packageId
                },
                success: function(response) {
                    if (response.success) {
                        self.openModal(response.data);
                    } else {
                        alert(response.data?.message || bkxPackages.i18n.error);
                    }
                },
                error: function() {
                    alert(bkxPackages.i18n.error);
                }
            });
        },

        /**
         * Open package modal
         *
         * @param {object} packageData Package data
         */
        openModal: function(packageData) {
            var $modal = $('#bkx-package-modal');

            if (!$modal.length) {
                $modal = $('<div id="bkx-package-modal" class="bkx-modal">' +
                    '<div class="bkx-modal-overlay"></div>' +
                    '<div class="bkx-modal-content">' +
                    '<button class="bkx-modal-close">&times;</button>' +
                    '<div class="bkx-modal-body"></div>' +
                    '</div></div>');
                $('body').append($modal);

                // Close handlers
                $modal.on('click', '.bkx-modal-close, .bkx-modal-overlay', function() {
                    $modal.removeClass('is-open');
                });
            }

            // Build content
            var html = '';

            if (packageData.image) {
                html += '<div class="bkx-modal-image"><img src="' + packageData.image + '" alt="' + packageData.title + '"></div>';
            }

            html += '<h2>' + packageData.title + '</h2>';

            if (packageData.description) {
                html += '<div class="bkx-modal-description">' + packageData.description + '</div>';
            }

            html += '<div class="bkx-modal-meta">';
            html += '<p><strong>' + packageData.uses + '</strong> ' + bkxPackages.i18n.uses_remaining + '</p>';

            if (packageData.validity_days > 0) {
                html += '<p>' + bkxPackages.i18n.expires + ': ' + packageData.validity_days + ' days from purchase</p>';
            } else {
                html += '<p>' + bkxPackages.i18n.expires + ': ' + bkxPackages.i18n.unlimited + '</p>';
            }

            html += '</div>';

            html += '<div class="bkx-modal-price">';
            if (packageData.regular_price && packageData.regular_price > packageData.price) {
                html += '<span class="regular-price">$' + parseFloat(packageData.regular_price).toFixed(2) + '</span>';
            }
            html += '<span class="sale-price">$' + parseFloat(packageData.price).toFixed(2) + '</span>';
            html += '</div>';

            html += '<button class="bkx-btn bkx-btn-primary bkx-purchase-package" data-package-id="' + packageData.id + '">Buy Now</button>';

            $modal.find('.bkx-modal-body').html(html);
            $modal.addClass('is-open');
        },

        /**
         * Apply package to booking
         *
         * @param {int} packageId Package ID
         * @param {jQuery} $form Form element
         */
        applyPackage: function(packageId, $form) {
            var serviceId = $form.find('input[name="service_id"]').val() || 0;

            $.ajax({
                url: bkxPackages.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_apply_package',
                    nonce: bkxPackages.nonce,
                    package_id: packageId,
                    service_id: serviceId
                },
                success: function(response) {
                    if (response.success) {
                        // Update form with package
                        $form.find('input[name="applied_package_id"]').remove();
                        $form.append('<input type="hidden" name="applied_package_id" value="' + packageId + '">');

                        // Show applied message
                        var $selector = $form.find('.bkx-package-selector');
                        $selector.find('.bkx-package-applied').remove();
                        $selector.append(
                            '<div class="bkx-package-applied">' +
                            '<span class="dashicons dashicons-yes-alt"></span>' +
                            response.data.message +
                            ' (' + response.data.uses_remaining + ' uses remaining)' +
                            '</div>'
                        );

                        // Update price display
                        $form.find('.bkx-total-price').addClass('bkx-covered-by-package').text('$0.00');

                        // Trigger event
                        $(document).trigger('bkx_package_applied', [packageId, response.data]);
                    } else {
                        alert(response.data?.message || bkxPackages.i18n.error);
                        $form.find('.bkx-package-selector select').val('');
                    }
                },
                error: function() {
                    alert(bkxPackages.i18n.error);
                    $form.find('.bkx-package-selector select').val('');
                }
            });
        },

        /**
         * Remove package from booking
         *
         * @param {jQuery} $form Form element
         */
        removePackage: function($form) {
            $form.find('input[name="applied_package_id"]').remove();
            $form.find('.bkx-package-applied').remove();
            $form.find('.bkx-total-price').removeClass('bkx-covered-by-package');

            // Recalculate price
            $(document).trigger('bkx_package_removed');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BKXPackages.init();
    });

})(jQuery);

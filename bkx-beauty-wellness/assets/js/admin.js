/**
 * Beauty & Wellness Admin JavaScript
 *
 * @package BookingX\BeautyWellness
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Beauty & Wellness Admin Module
     */
    const BkxBeautyWellnessAdmin = {
        /**
         * Initialize the module.
         */
        init: function() {
            this.initSettings();
            this.initVariations();
            this.initPortfolioManager();
            this.initMediaUploaders();
        },

        /**
         * Initialize settings page functionality.
         */
        initSettings: function() {
            // Tab navigation (if using custom tabs)
            $('.bkx-beauty-wellness-settings .nav-tab').on('click', function(e) {
                const href = $(this).attr('href');

                // If it's a hash link, handle with JS
                if (href.indexOf('#') === 0) {
                    e.preventDefault();
                    const target = href.substring(1);

                    // Update active tab
                    $('.nav-tab').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');

                    // Show/hide sections
                    $('.bkx-settings-section').hide();
                    $('#section-' + target).show();
                }
            });

            // Conditional field visibility
            this.setupConditionalFields();
        },

        /**
         * Setup conditional field visibility.
         */
        setupConditionalFields: function() {
            // Show/hide related fields based on checkbox state
            const conditionalFields = {
                'enable_treatment_menu': ['menu_style', 'show_duration', 'show_prices'],
                'enable_client_preferences': ['skin_type_tracking', 'allergy_alerts', 'product_recommendations'],
                'enable_service_addons': ['addon_display_style', 'show_recommended', 'enable_bundles'],
                'enable_stylist_portfolio': ['before_after_photos', 'portfolio_columns', 'enable_lightbox'],
                'enable_consultation_form': ['consultation_treatments', 'send_form_reminder']
            };

            $.each(conditionalFields, function(parentField, childFields) {
                const $parent = $('input[name="bkx_beauty_wellness_settings[' + parentField + ']"]');

                function toggleFields() {
                    const isChecked = $parent.prop('checked');

                    $.each(childFields, function(index, childField) {
                        const $row = $('input[name="bkx_beauty_wellness_settings[' + childField + ']"], select[name="bkx_beauty_wellness_settings[' + childField + ']"]')
                            .closest('tr');

                        if (isChecked) {
                            $row.show();
                        } else {
                            $row.hide();
                        }
                    });
                }

                $parent.on('change', toggleFields);
                toggleFields(); // Initial state
            });
        },

        /**
         * Initialize variation management.
         */
        initVariations: function() {
            const $container = $('#bkx-variations-table tbody');
            let variationIndex = $container.find('tr').length;

            // Add variation
            $('#bkx-add-variation').on('click', function() {
                const row = BkxBeautyWellnessAdmin.getVariationRowTemplate(variationIndex);
                $container.append(row);
                variationIndex++;
            });

            // Remove variation
            $(document).on('click', '.bkx-remove-variation', function() {
                $(this).closest('tr').fadeOut(200, function() {
                    $(this).remove();
                });
            });

            // Sortable variations
            if (typeof $.fn.sortable !== 'undefined') {
                $container.sortable({
                    handle: '.bkx-variation-handle',
                    placeholder: 'bkx-variation-placeholder',
                    update: function() {
                        BkxBeautyWellnessAdmin.reindexVariations();
                    }
                });
            }
        },

        /**
         * Get variation row template.
         *
         * @param {number} index Row index.
         * @return {string} HTML template.
         */
        getVariationRowTemplate: function(index) {
            return '<tr class="bkx-variation-row">' +
                '<td><span class="bkx-variation-handle dashicons dashicons-menu"></span></td>' +
                '<td><input type="text" name="bkx_variations[' + index + '][name]" class="regular-text" placeholder="e.g., Express, Deluxe"></td>' +
                '<td><input type="number" name="bkx_variations[' + index + '][duration]" min="0" step="5" placeholder="30"></td>' +
                '<td><input type="number" name="bkx_variations[' + index + '][price]" min="0" step="0.01" placeholder="0.00"></td>' +
                '<td style="text-align: center;"><input type="checkbox" name="bkx_variations[' + index + '][enabled]" value="1" checked></td>' +
                '<td><button type="button" class="button bkx-remove-variation">&times;</button></td>' +
                '</tr>';
        },

        /**
         * Reindex variations after sorting.
         */
        reindexVariations: function() {
            $('#bkx-variations-table tbody tr').each(function(index) {
                $(this).find('input').each(function() {
                    const name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                    }
                });
            });
        },

        /**
         * Initialize portfolio manager.
         */
        initPortfolioManager: function() {
            // Portfolio item form toggle
            $('.bkx-add-portfolio-item').on('click', function(e) {
                e.preventDefault();
                $('.bkx-portfolio-form').slideToggle();
            });

            // Portfolio type toggle
            $('input[name="bkx_portfolio_type"]').on('change', function() {
                const type = $(this).val();

                $('.bkx-portfolio-type-fields').hide();
                $('.bkx-portfolio-type-fields[data-type="' + type + '"]').show();
            });

            // Featured toggle
            $(document).on('click', '.bkx-toggle-featured', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const itemId = $btn.data('item-id');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bkx_toggle_portfolio_featured',
                        nonce: bkxBeautyWellnessAdmin.nonce,
                        item_id: itemId
                    },
                    success: function(response) {
                        if (response.success) {
                            $btn.toggleClass('featured');
                            $btn.find('.dashicons').toggleClass('dashicons-star-empty dashicons-star-filled');
                        }
                    }
                });
            });

            // Delete portfolio item
            $(document).on('click', '.bkx-delete-portfolio-item', function(e) {
                e.preventDefault();

                if (!confirm(bkxBeautyWellnessAdmin.i18n.confirmDelete || 'Are you sure you want to delete this item?')) {
                    return;
                }

                const $card = $(this).closest('.bkx-portfolio-card');
                const itemId = $card.data('item-id');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bkx_delete_portfolio_item',
                        nonce: bkxBeautyWellnessAdmin.nonce,
                        item_id: itemId
                    },
                    success: function(response) {
                        if (response.success) {
                            $card.fadeOut(300, function() {
                                $(this).remove();
                            });
                        }
                    }
                });
            });
        },

        /**
         * Initialize WordPress media uploaders.
         */
        initMediaUploaders: function() {
            // Before image uploader
            this.setupMediaUploader({
                buttonSelector: '.bkx-upload-before-image',
                previewSelector: '.bkx-before-image-preview',
                inputSelector: 'input[name="bkx_before_image_id"]',
                removeSelector: '.bkx-remove-before-image'
            });

            // After image uploader
            this.setupMediaUploader({
                buttonSelector: '.bkx-upload-after-image',
                previewSelector: '.bkx-after-image-preview',
                inputSelector: 'input[name="bkx_after_image_id"]',
                removeSelector: '.bkx-remove-after-image'
            });

            // Single image uploader
            this.setupMediaUploader({
                buttonSelector: '.bkx-upload-single-image',
                previewSelector: '.bkx-single-image-preview',
                inputSelector: 'input[name="bkx_image_id"]',
                removeSelector: '.bkx-remove-single-image'
            });
        },

        /**
         * Setup a media uploader.
         *
         * @param {Object} config Configuration object.
         */
        setupMediaUploader: function(config) {
            let mediaUploader;

            $(document).on('click', config.buttonSelector, function(e) {
                e.preventDefault();

                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                mediaUploader = wp.media({
                    title: bkxBeautyWellnessAdmin.i18n.selectImage || 'Select Image',
                    button: {
                        text: bkxBeautyWellnessAdmin.i18n.useImage || 'Use This Image'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });

                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();

                    // Update preview
                    const $preview = $(config.previewSelector);
                    $preview.find('img').remove();
                    $preview.prepend('<img src="' + (attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) + '" alt="">');
                    $preview.find('.bkx-upload-placeholder').hide();

                    // Update hidden input
                    $(config.inputSelector).val(attachment.id);

                    // Show remove button
                    $(config.removeSelector).show();
                });

                mediaUploader.open();
            });

            // Remove image
            $(document).on('click', config.removeSelector, function(e) {
                e.preventDefault();

                const $preview = $(config.previewSelector);
                $preview.find('img').remove();
                $preview.find('.bkx-upload-placeholder').show();
                $(config.inputSelector).val('');
                $(this).hide();
            });
        }
    };

    /**
     * Initialize on document ready.
     */
    $(document).ready(function() {
        BkxBeautyWellnessAdmin.init();
    });

})(jQuery);

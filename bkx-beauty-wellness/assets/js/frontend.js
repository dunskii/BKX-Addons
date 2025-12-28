/**
 * Beauty & Wellness Frontend JavaScript
 *
 * @package BookingX\BeautyWellness
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Beauty & Wellness Module
     */
    const BkxBeautyWellness = {
        /**
         * Initialize the module.
         */
        init: function() {
            this.bindEvents();
            this.initPortfolioLightbox();
            this.initBeforeAfterSlider();
            this.initAddonSelection();
            this.initConsultationForm();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            // Addon item selection
            $(document).on('click', '.bkx-addon-item', this.handleAddonClick.bind(this));

            // Treatment filter
            $(document).on('change', '.bkx-treatment-filter', this.handleFilterChange.bind(this));

            // Portfolio category filter
            $(document).on('click', '.bkx-portfolio-filter-btn', this.handlePortfolioFilter.bind(this));

            // Allergy tag management
            $(document).on('click', '.bkx-allergy-tag-remove', this.removeAllergyTag.bind(this));
            $(document).on('keypress', '.bkx-allergy-input', this.addAllergyTag.bind(this));
        },

        /**
         * Initialize portfolio lightbox.
         */
        initPortfolioLightbox: function() {
            if (typeof $.fn.magnificPopup === 'undefined') {
                return;
            }

            $('.bkx-portfolio-gallery').magnificPopup({
                delegate: '.bkx-portfolio-item:not(.bkx-portfolio-type-video)',
                type: 'image',
                gallery: {
                    enabled: true,
                    navigateByImgClick: true,
                    preload: [0, 1]
                },
                image: {
                    titleSrc: function(item) {
                        return item.el.find('.bkx-portfolio-title').text();
                    }
                },
                callbacks: {
                    elementParse: function(item) {
                        // Handle before/after items
                        if (item.el.hasClass('bkx-portfolio-type-before_after')) {
                            var afterImg = item.el.find('.bkx-after-image img').attr('src');
                            item.src = afterImg.replace('-medium', '-full').replace(/\-\d+x\d+/, '');
                        } else {
                            var img = item.el.find('.bkx-single-image img').attr('src');
                            item.src = img ? img.replace('-medium', '-full').replace(/\-\d+x\d+/, '') : '';
                        }
                    }
                }
            });
        },

        /**
         * Initialize before/after slider comparison.
         */
        initBeforeAfterSlider: function() {
            $('.bkx-before-after-slider').each(function() {
                const $container = $(this);
                const $slider = $container.find('.bkx-slider-handle');
                const $beforeImage = $container.find('.bkx-before-image');

                let isDragging = false;

                $slider.on('mousedown touchstart', function(e) {
                    e.preventDefault();
                    isDragging = true;
                    $container.addClass('is-dragging');
                });

                $(document).on('mouseup touchend', function() {
                    isDragging = false;
                    $container.removeClass('is-dragging');
                });

                $(document).on('mousemove touchmove', function(e) {
                    if (!isDragging) return;

                    const containerRect = $container[0].getBoundingClientRect();
                    const clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
                    let position = ((clientX - containerRect.left) / containerRect.width) * 100;

                    // Clamp between 0 and 100
                    position = Math.max(0, Math.min(100, position));

                    $slider.css('left', position + '%');
                    $beforeImage.css('clip-path', 'inset(0 ' + (100 - position) + '% 0 0)');
                });
            });
        },

        /**
         * Initialize addon selection.
         */
        initAddonSelection: function() {
            this.updateAddonTotal();
        },

        /**
         * Handle addon item click.
         *
         * @param {Event} e Click event.
         */
        handleAddonClick: function(e) {
            const $item = $(e.currentTarget);
            const $checkbox = $item.find('.bkx-addon-checkbox');

            // Toggle checkbox
            $checkbox.prop('checked', !$checkbox.prop('checked'));

            // Toggle selected class
            $item.toggleClass('selected', $checkbox.prop('checked'));

            // Update total
            this.updateAddonTotal();

            // Trigger event for booking form integration
            $(document).trigger('bkx:addonChanged', {
                addonId: $item.data('addon-id'),
                selected: $checkbox.prop('checked'),
                price: parseFloat($item.data('addon-price')) || 0,
                duration: parseInt($item.data('addon-duration')) || 0
            });
        },

        /**
         * Update addon total display.
         */
        updateAddonTotal: function() {
            const $container = $('.bkx-addons-section');
            let totalPrice = 0;
            let totalDuration = 0;

            $container.find('.bkx-addon-item.selected').each(function() {
                totalPrice += parseFloat($(this).data('addon-price')) || 0;
                totalDuration += parseInt($(this).data('addon-duration')) || 0;
            });

            // Update display
            $('.bkx-addons-total-price').text(this.formatPrice(totalPrice));
            $('.bkx-addons-total-duration').text(this.formatDuration(totalDuration));

            // Update hidden inputs for form submission
            $('input[name="bkx_addons_total_price"]').val(totalPrice);
            $('input[name="bkx_addons_total_duration"]').val(totalDuration);
        },

        /**
         * Format price.
         *
         * @param {number} price Price value.
         * @return {string} Formatted price.
         */
        formatPrice: function(price) {
            // Use WooCommerce formatting if available
            if (typeof wc_price_params !== 'undefined') {
                return wc_price_params.currency_symbol + price.toFixed(wc_price_params.num_decimals);
            }
            return '$' + price.toFixed(2);
        },

        /**
         * Format duration.
         *
         * @param {number} minutes Duration in minutes.
         * @return {string} Formatted duration.
         */
        formatDuration: function(minutes) {
            if (minutes < 60) {
                return minutes + ' min';
            }
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return hours + 'h' + (mins > 0 ? ' ' + mins + 'm' : '');
        },

        /**
         * Handle treatment filter change.
         *
         * @param {Event} e Change event.
         */
        handleFilterChange: function(e) {
            const $select = $(e.currentTarget);
            const filterType = $select.data('filter-type');
            const value = $select.val();

            // Build filter data
            const filters = {};
            $('.bkx-treatment-filter').each(function() {
                const type = $(this).data('filter-type');
                const val = $(this).val();
                if (val) {
                    filters[type] = val;
                }
            });

            // AJAX request to filter treatments
            $.ajax({
                url: bkxBeautyWellness.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_filter_treatments',
                    nonce: bkxBeautyWellness.nonce,
                    filters: filters
                },
                beforeSend: function() {
                    $('.bkx-treatments-grid').addClass('loading');
                },
                success: function(response) {
                    if (response.success) {
                        $('.bkx-treatments-grid').html(response.data.html);
                    }
                },
                complete: function() {
                    $('.bkx-treatments-grid').removeClass('loading');
                }
            });
        },

        /**
         * Handle portfolio filter.
         *
         * @param {Event} e Click event.
         */
        handlePortfolioFilter: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const category = $btn.data('category');

            // Update active state
            $('.bkx-portfolio-filter-btn').removeClass('active');
            $btn.addClass('active');

            // Filter items
            const $items = $('.bkx-portfolio-item');

            if (category === 'all') {
                $items.show();
            } else {
                $items.hide().filter('[data-category="' + category + '"]').show();
            }
        },

        /**
         * Remove allergy tag.
         *
         * @param {Event} e Click event.
         */
        removeAllergyTag: function(e) {
            const $tag = $(e.currentTarget).closest('.bkx-allergy-tag');
            const allergy = $tag.data('allergy');

            // Remove from hidden input
            const $input = $('input[name="bkx_allergies"]');
            const allergies = $input.val().split(',').filter(function(a) {
                return a.trim() !== allergy;
            });
            $input.val(allergies.join(','));

            // Remove tag
            $tag.fadeOut(200, function() {
                $(this).remove();
            });
        },

        /**
         * Add allergy tag.
         *
         * @param {Event} e Keypress event.
         */
        addAllergyTag: function(e) {
            if (e.which !== 13) return; // Enter key
            e.preventDefault();

            const $input = $(e.currentTarget);
            const allergy = $input.val().trim();

            if (!allergy) return;

            // Check for duplicates
            if ($('.bkx-allergy-tag[data-allergy="' + allergy + '"]').length > 0) {
                $input.val('');
                return;
            }

            // Add to hidden input
            const $hiddenInput = $('input[name="bkx_allergies"]');
            const allergies = $hiddenInput.val() ? $hiddenInput.val().split(',') : [];
            allergies.push(allergy);
            $hiddenInput.val(allergies.join(','));

            // Add tag
            const $tag = $(
                '<span class="bkx-allergy-tag" data-allergy="' + allergy + '">' +
                    allergy +
                    '<span class="bkx-allergy-tag-remove">&times;</span>' +
                '</span>'
            );
            $('.bkx-allergy-tags').append($tag);

            // Clear input
            $input.val('');
        },

        /**
         * Initialize consultation form.
         */
        initConsultationForm: function() {
            const $form = $('.bkx-consultation-form');

            if (!$form.length) return;

            // Form validation
            $form.on('submit', function(e) {
                let isValid = true;
                const $requiredFields = $form.find('[required]');

                $requiredFields.each(function() {
                    const $field = $(this);
                    if (!$field.val()) {
                        isValid = false;
                        $field.addClass('error');
                    } else {
                        $field.removeClass('error');
                    }
                });

                // Check consent checkbox
                const $consent = $form.find('input[name="bkx_consent"]');
                if ($consent.length && !$consent.prop('checked')) {
                    isValid = false;
                    $consent.closest('.bkx-consent-section').addClass('error');
                }

                if (!isValid) {
                    e.preventDefault();
                    $form.find('.bkx-form-error').show().text(
                        bkxBeautyWellness.i18n.formError || 'Please fill in all required fields.'
                    );
                }
            });

            // Clear error on input
            $form.on('input change', 'input, select, textarea', function() {
                $(this).removeClass('error');
                $(this).closest('.bkx-consent-section').removeClass('error');
            });

            // Signature pad initialization
            this.initSignaturePad();
        },

        /**
         * Initialize signature pad.
         */
        initSignaturePad: function() {
            const canvas = document.querySelector('.bkx-signature-canvas');

            if (!canvas || typeof SignaturePad === 'undefined') return;

            const signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgb(255, 255, 255)',
                penColor: 'rgb(0, 0, 0)'
            });

            // Resize canvas
            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);
                signaturePad.clear();
            }

            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();

            // Clear button
            $('.bkx-signature-clear').on('click', function() {
                signaturePad.clear();
            });

            // Save signature data on form submit
            $('.bkx-consultation-form').on('submit', function() {
                if (!signaturePad.isEmpty()) {
                    $('input[name="bkx_signature_data"]').val(signaturePad.toDataURL());
                }
            });
        }
    };

    /**
     * Initialize on document ready.
     */
    $(document).ready(function() {
        BkxBeautyWellness.init();
    });

})(jQuery);

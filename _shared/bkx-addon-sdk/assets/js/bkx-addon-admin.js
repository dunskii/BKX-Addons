/**
 * BookingX Add-on SDK Admin JavaScript
 *
 * Shared JavaScript for all BookingX add-ons.
 *
 * @package BookingX\AddonSDK
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * BookingX SDK namespace
     */
    window.BKX_SDK = window.BKX_SDK || {};

    /**
     * Initialize SDK components
     */
    BKX_SDK.init = function() {
        this.initColorPickers();
        this.initTabs();
        this.initModals();
        this.initNotices();
        this.initToggles();
        this.initLicenseManager();
    };

    /**
     * Initialize color pickers
     */
    BKX_SDK.initColorPickers = function() {
        if ($.fn.wpColorPicker) {
            $('.bkx-color-picker').wpColorPicker();
        }
    };

    /**
     * Initialize tabs
     */
    BKX_SDK.initTabs = function() {
        $(document).on('click', '.bkx-tab-nav-item', function(e) {
            e.preventDefault();

            var $this = $(this);
            var $tabs = $this.closest('.bkx-tabs');
            var targetId = $this.data('tab');

            // Update nav
            $tabs.find('.bkx-tab-nav-item').removeClass('active');
            $this.addClass('active');

            // Update content
            $tabs.find('.bkx-tab-content').removeClass('active');
            $tabs.find('#' + targetId).addClass('active');

            // Trigger event
            $(document).trigger('bkx_tab_changed', [targetId, $tabs]);
        });
    };

    /**
     * Initialize modals
     */
    BKX_SDK.initModals = function() {
        // Open modal
        $(document).on('click', '[data-bkx-modal]', function(e) {
            e.preventDefault();
            var modalId = $(this).data('bkx-modal');
            BKX_SDK.openModal(modalId);
        });

        // Close modal on X button
        $(document).on('click', '.bkx-modal-close', function(e) {
            e.preventDefault();
            BKX_SDK.closeModal($(this).closest('.bkx-modal-overlay'));
        });

        // Close modal on overlay click
        $(document).on('click', '.bkx-modal-overlay', function(e) {
            if (e.target === this) {
                BKX_SDK.closeModal($(this));
            }
        });

        // Close modal on ESC
        $(document).on('keyup', function(e) {
            if (e.key === 'Escape') {
                $('.bkx-modal-overlay.active').each(function() {
                    BKX_SDK.closeModal($(this));
                });
            }
        });
    };

    /**
     * Open a modal
     */
    BKX_SDK.openModal = function(modalId) {
        var $modal = $('#' + modalId);
        if ($modal.length) {
            $modal.addClass('active');
            $('body').addClass('bkx-modal-open');
            $(document).trigger('bkx_modal_opened', [modalId, $modal]);
        }
    };

    /**
     * Close a modal
     */
    BKX_SDK.closeModal = function($overlay) {
        $overlay.removeClass('active');

        // Only remove body class if no modals are open
        if (!$('.bkx-modal-overlay.active').length) {
            $('body').removeClass('bkx-modal-open');
        }

        $(document).trigger('bkx_modal_closed', [$overlay.attr('id'), $overlay]);
    };

    /**
     * Initialize dismissible notices
     */
    BKX_SDK.initNotices = function() {
        $(document).on('click', '.bkx-admin-notice.is-dismissible .notice-dismiss', function() {
            var $notice = $(this).closest('.bkx-admin-notice');
            var noticeId = $notice.data('notice-id');

            if (noticeId && typeof bkxSdkData !== 'undefined') {
                $.ajax({
                    url: bkxSdkData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'bkx_dismiss_notice',
                        notice_id: noticeId,
                        nonce: bkxSdkData.dismissNonce
                    }
                });
            }
        });
    };

    /**
     * Initialize toggle switches
     */
    BKX_SDK.initToggles = function() {
        // Add toggle switch markup for checkbox fields with toggle type
        $('.bkx-field-toggle input[type="checkbox"]').each(function() {
            if (!$(this).next('.bkx-toggle-switch').length) {
                $(this).after('<span class="bkx-toggle-switch"></span>');
            }
        });
    };

    /**
     * Initialize license manager
     */
    BKX_SDK.initLicenseManager = function() {
        // Activate license
        $(document).on('click', '.bkx-license-activate', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $box = $btn.closest('.bkx-license-box');
            var $input = $box.find('.bkx-license-key');
            var licenseKey = $input.val().trim();
            var addonSlug = $box.data('addon-slug');

            if (!licenseKey) {
                BKX_SDK.showNotice($box, 'Please enter a license key.', 'error');
                return;
            }

            BKX_SDK.setLoading($btn, true);

            $.ajax({
                url: bkxSdkData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_activate_license',
                    license_key: licenseKey,
                    addon_slug: addonSlug,
                    nonce: bkxSdkData.licenseNonce
                },
                success: function(response) {
                    if (response.success) {
                        BKX_SDK.showNotice($box, response.data.message, 'success');
                        location.reload();
                    } else {
                        BKX_SDK.showNotice($box, response.data.message || 'Activation failed.', 'error');
                    }
                },
                error: function() {
                    BKX_SDK.showNotice($box, 'An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    BKX_SDK.setLoading($btn, false);
                }
            });
        });

        // Deactivate license
        $(document).on('click', '.bkx-license-deactivate', function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to deactivate this license?')) {
                return;
            }

            var $btn = $(this);
            var $box = $btn.closest('.bkx-license-box');
            var addonSlug = $box.data('addon-slug');

            BKX_SDK.setLoading($btn, true);

            $.ajax({
                url: bkxSdkData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_deactivate_license',
                    addon_slug: addonSlug,
                    nonce: bkxSdkData.licenseNonce
                },
                success: function(response) {
                    if (response.success) {
                        BKX_SDK.showNotice($box, response.data.message, 'success');
                        location.reload();
                    } else {
                        BKX_SDK.showNotice($box, response.data.message || 'Deactivation failed.', 'error');
                    }
                },
                error: function() {
                    BKX_SDK.showNotice($box, 'An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    BKX_SDK.setLoading($btn, false);
                }
            });
        });
    };

    /**
     * Set loading state on button
     */
    BKX_SDK.setLoading = function($btn, loading) {
        if (loading) {
            $btn.prop('disabled', true);
            $btn.data('original-text', $btn.text());
            $btn.html('<span class="bkx-spinner"></span> Loading...');
        } else {
            $btn.prop('disabled', false);
            $btn.text($btn.data('original-text') || 'Submit');
        }
    };

    /**
     * Show inline notice
     */
    BKX_SDK.showNotice = function($container, message, type) {
        type = type || 'info';

        // Remove existing notices
        $container.find('.bkx-inline-notice').remove();

        var $notice = $('<div class="bkx-inline-notice notice-' + type + '">' + message + '</div>');
        $container.prepend($notice);

        // Auto-hide after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    };

    /**
     * AJAX helper
     */
    BKX_SDK.ajax = function(action, data, options) {
        options = options || {};

        var settings = $.extend({
            url: bkxSdkData.ajaxUrl,
            type: 'POST',
            data: $.extend({
                action: action,
                nonce: bkxSdkData.nonce
            }, data),
            dataType: 'json'
        }, options);

        return $.ajax(settings);
    };

    /**
     * Format currency
     */
    BKX_SDK.formatCurrency = function(amount, currency) {
        currency = currency || 'USD';

        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency
            }).format(amount);
        } catch (e) {
            return currency + ' ' + parseFloat(amount).toFixed(2);
        }
    };

    /**
     * Format date
     */
    BKX_SDK.formatDate = function(dateString, options) {
        options = options || {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        };

        try {
            var date = new Date(dateString);
            return date.toLocaleDateString('en-US', options);
        } catch (e) {
            return dateString;
        }
    };

    /**
     * Debounce function
     */
    BKX_SDK.debounce = function(func, wait) {
        var timeout;
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    };

    /**
     * Throttle function
     */
    BKX_SDK.throttle = function(func, limit) {
        var inThrottle;
        return function() {
            var context = this;
            var args = arguments;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(function() {
                    inThrottle = false;
                }, limit);
            }
        };
    };

    /**
     * Copy to clipboard
     */
    BKX_SDK.copyToClipboard = function(text, callback) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                if (callback) callback(true);
            }).catch(function() {
                if (callback) callback(false);
            });
        } else {
            // Fallback
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                if (callback) callback(true);
            } catch (e) {
                if (callback) callback(false);
            }

            document.body.removeChild(textarea);
        }
    };

    /**
     * Confirm dialog
     */
    BKX_SDK.confirm = function(message, callback) {
        var confirmed = confirm(message);
        if (callback) {
            callback(confirmed);
        }
        return confirmed;
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        BKX_SDK.init();
    });

})(jQuery);

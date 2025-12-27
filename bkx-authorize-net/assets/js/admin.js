/**
 * Authorize.net Admin JavaScript
 *
 * Handles admin settings page functionality.
 *
 * @package BookingX\AuthorizeNet
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin Settings Handler
     */
    var BkxAuthorizeNetAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Copy webhook URL to clipboard
            $('.bkx-copy-webhook-url').on('click', this.copyWebhookUrl);

            // Toggle visibility for password fields
            this.initPasswordToggle();

            // Mode change warning
            $('#authnet_mode').on('change', this.handleModeChange);
        },

        /**
         * Copy webhook URL to clipboard
         *
         * @param {Event} e Click event
         */
        copyWebhookUrl: function(e) {
            e.preventDefault();

            var $button = $(this);
            var webhookUrl = $button.data('clipboard-text');

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(webhookUrl).then(function() {
                    BkxAuthorizeNetAdmin.showCopySuccess($button);
                }).catch(function() {
                    BkxAuthorizeNetAdmin.fallbackCopy(webhookUrl, $button);
                });
            } else {
                BkxAuthorizeNetAdmin.fallbackCopy(webhookUrl, $button);
            }
        },

        /**
         * Fallback copy method
         *
         * @param {string} text Text to copy
         * @param {jQuery} $button Button element
         */
        fallbackCopy: function(text, $button) {
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();

            try {
                document.execCommand('copy');
                this.showCopySuccess($button);
            } catch (err) {
                console.error('Failed to copy:', err);
            }

            $temp.remove();
        },

        /**
         * Show copy success feedback
         *
         * @param {jQuery} $button Button element
         */
        showCopySuccess: function($button) {
            var originalText = $button.text();
            $button.text('Copied!').addClass('button-primary');

            setTimeout(function() {
                $button.text(originalText).removeClass('button-primary');
            }, 2000);
        },

        /**
         * Initialize password field toggle
         */
        initPasswordToggle: function() {
            $('input[type="password"]').each(function() {
                var $input = $(this);
                var $toggle = $('<button type="button" class="button button-secondary bkx-password-toggle">Show</button>');

                $input.after($toggle);

                $toggle.on('click', function(e) {
                    e.preventDefault();

                    if ($input.attr('type') === 'password') {
                        $input.attr('type', 'text');
                        $(this).text('Hide');
                    } else {
                        $input.attr('type', 'password');
                        $(this).text('Show');
                    }
                });
            });
        },

        /**
         * Handle mode change
         */
        handleModeChange: function() {
            var mode = $(this).val();

            if (mode === 'live') {
                var confirmed = confirm(
                    'You are switching to Live mode. Please ensure you have entered your production API credentials.\n\n' +
                    'Are you sure you want to enable Live mode?'
                );

                if (!confirmed) {
                    $(this).val('sandbox');
                }
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BkxAuthorizeNetAdmin.init();
    });

})(jQuery);

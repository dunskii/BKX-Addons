/**
 * Zapier Integration Admin JavaScript
 *
 * @package BookingX\ZapierIntegration
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin Handler
     */
    var BKXZapierAdmin = {
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

            // Copy button
            $(document).on('click', '.bkx-copy-btn', function(e) {
                e.preventDefault();
                var text = $(this).data('target');
                self.copyToClipboard(text, $(this));
            });
        },

        /**
         * Copy to clipboard
         *
         * @param {string} text Text to copy
         * @param {jQuery} $button Button element
         */
        copyToClipboard: function(text, $button) {
            var originalText = $button.text();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    $button.text('Copied!');
                    setTimeout(function() {
                        $button.text(originalText);
                    }, 2000);
                }).catch(function() {
                    // Fallback
                    this.fallbackCopy(text, $button, originalText);
                }.bind(this));
            } else {
                this.fallbackCopy(text, $button, originalText);
            }
        },

        /**
         * Fallback copy method
         *
         * @param {string} text Text to copy
         * @param {jQuery} $button Button element
         * @param {string} originalText Original button text
         */
        fallbackCopy: function(text, $button, originalText) {
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();

            try {
                document.execCommand('copy');
                $button.text('Copied!');
            } catch (err) {
                $button.text('Failed');
            }

            $temp.remove();

            setTimeout(function() {
                $button.text(originalText);
            }, 2000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BKXZapierAdmin.init();
    });

})(jQuery);

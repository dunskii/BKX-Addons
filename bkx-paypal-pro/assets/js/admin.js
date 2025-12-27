/**
 * PayPal Pro Admin JavaScript
 *
 * Handles admin settings page functionality.
 *
 * @package BookingX\PayPalPro
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    const BkxPayPalProAdmin = {
        /**
         * Initialize.
         */
        init: function () {
            this.toggleModeFields();
            this.bindEvents();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function () {
            // Toggle sandbox/live fields
            $('#paypal_mode').on('change', function () {
                BkxPayPalProAdmin.toggleModeFields();
            });

            // Test connection
            $('#test-paypal-connection').on('click', function (e) {
                e.preventDefault();
                BkxPayPalProAdmin.testConnection();
            });
        },

        /**
         * Toggle sandbox/live credential fields.
         */
        toggleModeFields: function () {
            const mode = $('#paypal_mode').val();

            if (mode === 'live') {
                $('.sandbox-row').hide();
                $('.live-row').show();
            } else {
                $('.sandbox-row').show();
                $('.live-row').hide();
            }
        },

        /**
         * Test PayPal API connection.
         */
        testConnection: function () {
            const $button = $('#test-paypal-connection');
            const $spinner = $button.next('.spinner');
            const $result = $('#connection-result');

            // Show spinner
            $spinner.addClass('is-active');
            $button.prop('disabled', true);
            $result.html('');

            $.ajax({
                url: bkxPayPalProAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bkx_paypal_pro_test_connection',
                    nonce: bkxPayPalProAdmin.nonce
                },
                success: function (response) {
                    $spinner.removeClass('is-active');
                    $button.prop('disabled', false);

                    if (response.success) {
                        $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    } else {
                        $result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                    }

                    // Clear message after 5 seconds
                    setTimeout(function () {
                        $result.fadeOut(function () {
                            $(this).html('').show();
                        });
                    }, 5000);
                },
                error: function () {
                    $spinner.removeClass('is-active');
                    $button.prop('disabled', false);
                    $result.html('<span style="color: red;">✗ Connection test failed</span>');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        BkxPayPalProAdmin.init();
    });

})(jQuery);

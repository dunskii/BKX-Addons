/**
 * PayPal Checkout JavaScript
 *
 * Handles PayPal button rendering and payment processing.
 *
 * @package BookingX\PayPalPro
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    const BkxPayPalCheckout = {
        /**
         * Initialize.
         */
        init: function () {
            if (typeof paypal === 'undefined') {
                console.error('PayPal SDK not loaded');
                return;
            }

            this.renderButtons();

            if (bkxPayPalPro.enableCards) {
                this.initCardFields();
                this.initPaymentToggle();
            }
        },

        /**
         * Render PayPal buttons.
         */
        renderButtons: function () {
            const container = document.getElementById('paypal-button-container');
            if (!container) {
                return;
            }

            const bookingId = $('.bkx-paypal-checkout-container').data('booking-id');
            const amount = $('.bkx-paypal-checkout-container').data('amount');

            paypal.Buttons({
                style: {
                    color: bkxPayPalPro.buttonColor,
                    shape: bkxPayPalPro.buttonShape,
                    label: 'paypal',
                    layout: 'vertical'
                },

                /**
                 * Create order.
                 */
                createOrder: function (data, actions) {
                    BkxPayPalCheckout.showProcessing();

                    return $.ajax({
                        url: bkxPayPalPro.restUrl + '/paypal-pro/create-order',
                        method: 'POST',
                        headers: {
                            'X-WP-Nonce': bkxPayPalPro.nonce
                        },
                        data: JSON.stringify({
                            booking_id: bookingId,
                            amount: amount
                        }),
                        contentType: 'application/json'
                    }).then(function (response) {
                        BkxPayPalCheckout.hideProcessing();

                        if (response.success && response.data.order_id) {
                            return response.data.order_id;
                        } else {
                            throw new Error(response.error || bkxPayPalPro.i18n.error);
                        }
                    }).catch(function (error) {
                        BkxPayPalCheckout.hideProcessing();
                        BkxPayPalCheckout.showError(error.message || bkxPayPalPro.i18n.error);
                        throw error;
                    });
                },

                /**
                 * Approve order.
                 */
                onApprove: function (data, actions) {
                    BkxPayPalCheckout.showProcessing();

                    return $.ajax({
                        url: bkxPayPalPro.restUrl + '/paypal-pro/capture-order',
                        method: 'POST',
                        headers: {
                            'X-WP-Nonce': bkxPayPalPro.nonce
                        },
                        data: JSON.stringify({
                            order_id: data.orderID,
                            booking_id: bookingId
                        }),
                        contentType: 'application/json'
                    }).then(function (response) {
                        BkxPayPalCheckout.hideProcessing();

                        if (response.success) {
                            BkxPayPalCheckout.showSuccess(bkxPayPalPro.i18n.success);
                            // Redirect to success page
                            setTimeout(function () {
                                window.location.href = response.data.redirect_url;
                            }, 2000);
                        } else {
                            BkxPayPalCheckout.showError(response.error || bkxPayPalPro.i18n.error);
                        }
                    }).catch(function (error) {
                        BkxPayPalCheckout.hideProcessing();
                        BkxPayPalCheckout.showError(error.message || bkxPayPalPro.i18n.error);
                    });
                },

                /**
                 * Handle errors.
                 */
                onError: function (err) {
                    BkxPayPalCheckout.hideProcessing();
                    BkxPayPalCheckout.showError(bkxPayPalPro.i18n.error);
                    console.error('PayPal error:', err);
                },

                /**
                 * Handle cancel.
                 */
                onCancel: function (data) {
                    BkxPayPalCheckout.hideProcessing();
                    console.log('Payment cancelled by user');
                }
            }).render('#paypal-button-container');
        },

        /**
         * Initialize card fields.
         */
        initCardFields: function () {
            const cardFields = paypal.HostedFields;
            if (!cardFields) {
                console.error('PayPal Hosted Fields not available');
                return;
            }

            // Card fields configuration would go here
            // This requires additional PayPal configuration
        },

        /**
         * Initialize payment method toggle.
         */
        initPaymentToggle: function () {
            $('.bkx-toggle-payment').on('click', function () {
                const target = $(this).data('target');

                if (target === 'card') {
                    $('#paypal-button-container').hide();
                    $('.bkx-paypal-card-container').show();
                } else {
                    $('#paypal-button-container').show();
                    $('.bkx-paypal-card-container').hide();
                }

                $('.bkx-toggle-payment').removeClass('active');
                $(this).addClass('active');
            });
        },

        /**
         * Show processing overlay.
         */
        showProcessing: function () {
            $('.bkx-paypal-processing').show();
            $('.bkx-paypal-errors').hide();
        },

        /**
         * Hide processing overlay.
         */
        hideProcessing: function () {
            $('.bkx-paypal-processing').hide();
        },

        /**
         * Show error message.
         */
        showError: function (message) {
            $('.bkx-paypal-errors')
                .html('<p class="error">' + message + '</p>')
                .show();
        },

        /**
         * Show success message.
         */
        showSuccess: function (message) {
            $('.bkx-paypal-errors')
                .html('<p class="success">' + message + '</p>')
                .show();
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        BkxPayPalCheckout.init();
    });

})(jQuery);

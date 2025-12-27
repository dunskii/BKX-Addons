/**
 * Authorize.net Accept.js Payment Form Handler
 *
 * Handles tokenization of card data using Accept.js and form submission.
 *
 * @package BookingX\AuthorizeNet
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Payment Form Handler
     */
    var BkxAuthorizeNetPayment = {
        /**
         * Configuration
         */
        config: window.bkxAuthorizeNet || {},

        /**
         * Form element
         */
        $form: null,

        /**
         * Submit button
         */
        $submitButton: null,

        /**
         * Error container
         */
        $errorContainer: null,

        /**
         * Whether form is processing
         */
        isProcessing: false,

        /**
         * Initialize the payment form
         */
        init: function() {
            this.$form = $('#bkx-authorize-net-payment-form').closest('form');
            this.$submitButton = $('#bkx-submit-payment');
            this.$errorContainer = $('#bkx-payment-errors');

            if (this.$form.length === 0) {
                return;
            }

            this.bindEvents();
            this.initCardFormatting();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Form submission
            this.$form.on('submit', function(e) {
                e.preventDefault();
                self.handleSubmit();
            });

            // Clear errors on input
            this.$form.on('input', 'input, select', function() {
                self.clearErrors();
            });
        },

        /**
         * Initialize card number formatting
         */
        initCardFormatting: function() {
            var $cardNumber = $('#bkx-card-number');

            // Format card number with spaces
            $cardNumber.on('input', function() {
                var value = $(this).val().replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                var formatted = value.match(/.{1,4}/g);
                $(this).val(formatted ? formatted.join(' ') : value);

                // Detect card type
                BkxAuthorizeNetPayment.detectCardType(value);
            });

            // CVV formatting
            $('#bkx-cvv').on('input', function() {
                var value = $(this).val().replace(/[^0-9]/gi, '');
                $(this).val(value);
            });
        },

        /**
         * Detect card type from number
         *
         * @param {string} number Card number
         */
        detectCardType: function(number) {
            var cardType = '';
            var patterns = {
                visa: /^4/,
                mastercard: /^(5[1-5]|2[2-7])/,
                amex: /^3[47]/,
                discover: /^(6011|65|64[4-9])/,
                jcb: /^35/,
                diners: /^3(0[0-5]|[68])/
            };

            for (var type in patterns) {
                if (patterns[type].test(number)) {
                    cardType = type;
                    break;
                }
            }

            var $detected = $('.bkx-card-icon-detected');
            if (cardType && this.config.acceptedCards.indexOf(cardType) !== -1) {
                $detected.attr('class', 'bkx-card-icon-detected bkx-card-' + cardType);
            } else {
                $detected.attr('class', 'bkx-card-icon-detected');
            }
        },

        /**
         * Handle form submission
         */
        handleSubmit: function() {
            if (this.isProcessing) {
                return;
            }

            // Validate form
            if (!this.validateForm()) {
                return;
            }

            this.isProcessing = true;
            this.setLoading(true);
            this.clearErrors();

            // Get card data
            var cardNumber = $('#bkx-card-number').val().replace(/\s+/g, '');
            var expMonth = $('#bkx-exp-month').val();
            var expYear = $('#bkx-exp-year').val();
            var cvv = $('#bkx-cvv').val();

            // Build secure data for Accept.js
            var authData = {
                clientKey: this.config.clientKey,
                apiLoginID: this.config.apiLoginId
            };

            var cardData = {
                cardNumber: cardNumber,
                month: expMonth,
                year: expYear
            };

            if (this.config.requireCvv && cvv) {
                cardData.cardCode = cvv;
            }

            var secureData = {
                authData: authData,
                cardData: cardData
            };

            // Call Accept.js to tokenize card
            Accept.dispatchData(secureData, this.handleAcceptResponse.bind(this));
        },

        /**
         * Handle Accept.js response
         *
         * @param {Object} response Accept.js response
         */
        handleAcceptResponse: function(response) {
            if (response.messages.resultCode === 'Error') {
                var errors = [];
                for (var i = 0; i < response.messages.message.length; i++) {
                    errors.push(response.messages.message[i].text);
                }
                this.showError(errors.join('<br>'));
                this.setLoading(false);
                this.isProcessing = false;
                return;
            }

            // Set opaque data values
            $('#bkx-opaque-data-descriptor').val(response.opaqueData.dataDescriptor);
            $('#bkx-opaque-data-value').val(response.opaqueData.dataValue);

            // Clear sensitive data before submission
            $('#bkx-card-number').val('');
            $('#bkx-exp-month').val('');
            $('#bkx-exp-year').val('');
            $('#bkx-cvv').val('');

            // Submit the form via AJAX
            this.submitPayment();
        },

        /**
         * Submit payment to server
         */
        submitPayment: function() {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_authorize_net_process_payment',
                    nonce: this.config.nonce,
                    booking_id: this.$form.find('input[name="booking_id"]').val(),
                    opaque_data_descriptor: $('#bkx-opaque-data-descriptor').val(),
                    opaque_data_value: $('#bkx-opaque-data-value').val()
                },
                success: function(response) {
                    if (response.success) {
                        self.handleSuccess(response.data);
                    } else {
                        self.showError(response.data.error || self.config.i18n.paymentFailed);
                        self.setLoading(false);
                        self.isProcessing = false;
                    }
                },
                error: function() {
                    self.showError(self.config.i18n.paymentFailed);
                    self.setLoading(false);
                    self.isProcessing = false;
                }
            });
        },

        /**
         * Handle successful payment
         *
         * @param {Object} data Response data
         */
        handleSuccess: function(data) {
            // Redirect to confirmation page
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
            } else {
                // Trigger success event
                $(document).trigger('bkx_payment_success', [data]);
            }
        },

        /**
         * Validate form fields
         *
         * @return {boolean} Whether form is valid
         */
        validateForm: function() {
            var isValid = true;
            var errors = [];

            // Card number
            var cardNumber = $('#bkx-card-number').val().replace(/\s+/g, '');
            if (!cardNumber || !this.isValidCardNumber(cardNumber)) {
                errors.push(this.config.i18n.invalidCard);
                isValid = false;
            }

            // Expiration
            var expMonth = $('#bkx-exp-month').val();
            var expYear = $('#bkx-exp-year').val();
            if (!expMonth || !expYear || !this.isValidExpiry(expMonth, expYear)) {
                errors.push(this.config.i18n.expirationDate + ' is invalid.');
                isValid = false;
            }

            // CVV
            if (this.config.requireCvv) {
                var cvv = $('#bkx-cvv').val();
                if (!cvv || cvv.length < 3) {
                    errors.push(this.config.i18n.cvv + ' is required.');
                    isValid = false;
                }
            }

            if (!isValid) {
                this.showError(errors.join('<br>'));
            }

            return isValid;
        },

        /**
         * Validate card number using Luhn algorithm
         *
         * @param {string} number Card number
         * @return {boolean} Whether card number is valid
         */
        isValidCardNumber: function(number) {
            if (!/^\d{13,19}$/.test(number)) {
                return false;
            }

            // Luhn algorithm
            var sum = 0;
            var isEven = false;

            for (var i = number.length - 1; i >= 0; i--) {
                var digit = parseInt(number.charAt(i), 10);

                if (isEven) {
                    digit *= 2;
                    if (digit > 9) {
                        digit -= 9;
                    }
                }

                sum += digit;
                isEven = !isEven;
            }

            return sum % 10 === 0;
        },

        /**
         * Validate expiration date
         *
         * @param {string} month Expiration month
         * @param {string} year Expiration year
         * @return {boolean} Whether expiration is valid
         */
        isValidExpiry: function(month, year) {
            var now = new Date();
            var currentYear = now.getFullYear();
            var currentMonth = now.getMonth() + 1;

            month = parseInt(month, 10);
            year = parseInt(year, 10);

            if (month < 1 || month > 12) {
                return false;
            }

            if (year < currentYear) {
                return false;
            }

            if (year === currentYear && month < currentMonth) {
                return false;
            }

            return true;
        },

        /**
         * Show error message
         *
         * @param {string} message Error message
         */
        showError: function(message) {
            this.$errorContainer.html(message).show();
            $('html, body').animate({
                scrollTop: this.$errorContainer.offset().top - 100
            }, 300);
        },

        /**
         * Clear error messages
         */
        clearErrors: function() {
            this.$errorContainer.hide().empty();
        },

        /**
         * Set loading state
         *
         * @param {boolean} loading Whether loading
         */
        setLoading: function(loading) {
            if (loading) {
                this.$submitButton.prop('disabled', true);
                this.$submitButton.find('.bkx-button-text').hide();
                this.$submitButton.find('.bkx-button-spinner').show();
            } else {
                this.$submitButton.prop('disabled', false);
                this.$submitButton.find('.bkx-button-text').show();
                this.$submitButton.find('.bkx-button-spinner').hide();
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BkxAuthorizeNetPayment.init();
    });

})(jQuery);

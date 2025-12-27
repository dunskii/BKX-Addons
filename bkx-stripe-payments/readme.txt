=== BookingX - Stripe Payments ===
Contributors: bookingx
Tags: stripe, payment, booking, credit card, payments
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Accept credit card payments via Stripe for BookingX bookings with support for 3D Secure, Apple Pay, Google Pay, and subscriptions.

== Description ==

The BookingX Stripe Payments add-on seamlessly integrates Stripe payment processing into your BookingX booking system. Accept credit card payments securely with support for the latest payment methods and security features.

= Features =

* **Payment Methods**: Accept all major credit cards, debit cards, Apple Pay, Google Pay, and Stripe Link
* **Security**: Built-in 3D Secure (SCA) support for enhanced security and fraud prevention
* **Subscriptions**: Support for recurring bookings with Stripe Subscriptions
* **Refunds**: Process full or partial refunds directly from WordPress admin
* **Saved Payment Methods**: Allow customers to save payment methods for faster checkout
* **Webhooks**: Real-time payment status updates via Stripe webhooks
* **Test Mode**: Fully functional test mode for development and testing
* **Manual Capture**: Option to authorize payments and capture them later
* **Dispute Management**: Automatic notification of payment disputes
* **Comprehensive Logging**: Detailed transaction and webhook logs for debugging

= Requirements =

* BookingX 2.0 or higher
* WordPress 5.8 or higher
* PHP 7.4 or higher
* SSL certificate (required for live payments)
* Active Stripe account

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/bkx-stripe-payments/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to BookingX → Settings → Stripe Payments
4. Enter your Stripe API keys (available in your Stripe dashboard)
5. Configure webhook URL in your Stripe account
6. Save settings and test with test mode first

== Frequently Asked Questions ==

= Do I need a Stripe account? =

Yes, you need an active Stripe account to use this add-on. Sign up at https://stripe.com

= Is SSL required? =

Yes, SSL is required for accepting live payments. You can use test mode without SSL for development.

= Which countries does Stripe support? =

Stripe is available in 40+ countries. Check https://stripe.com/global for availability.

= How do I set up webhooks? =

1. Go to your Stripe Dashboard → Developers → Webhooks
2. Click "Add endpoint"
3. Enter the webhook URL shown in plugin settings
4. Select events: payment_intent.succeeded, payment_intent.payment_failed, charge.refunded, charge.dispute.created
5. Copy the signing secret to plugin settings

= Can I process refunds? =

Yes, you can process full or partial refunds from the booking edit screen or they can be processed automatically when a booking is cancelled.

= Is 3D Secure supported? =

Yes, 3D Secure (SCA - Strong Customer Authentication) is fully supported and enabled by default for enhanced security.

== Screenshots ==

1. Stripe payment gateway settings
2. Checkout form with card element
3. Payment details in booking admin
4. Transaction log

== Changelog ==

= 1.0.0 =
* Initial release
* Stripe PaymentIntents API integration
* 3D Secure support
* Apple Pay and Google Pay support
* Subscription support
* Refund processing
* Webhook handling
* Manual capture option
* Comprehensive transaction logging

== Upgrade Notice ==

= 1.0.0 =
Initial release of BookingX Stripe Payments add-on.

== Additional Information ==

= Support =

For support, please visit https://bookingx.com/support

= Documentation =

Full documentation available at https://bookingx.com/docs/stripe-payments

= Privacy =

This plugin integrates with Stripe to process payments. Customer payment information is sent directly to Stripe and is not stored on your server. Transaction metadata (booking ID, amounts, status) is stored in your WordPress database.

By using this plugin, you agree to Stripe's Terms of Service and Privacy Policy.

=== BookingX - PayPal Pro ===
Contributors: bookingx
Tags: paypal, payment, bookingx, booking, appointments
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments via PayPal Commerce Platform with advanced card processing, Pay Later, and comprehensive webhook support.

== Description ==

PayPal Pro for BookingX integrates the latest PayPal Commerce Platform to provide a seamless payment experience for your booking system. Accept PayPal, credit/debit cards, and Pay in 4 payments directly on your site.

= Features =

* **PayPal Commerce Platform Integration** - Uses the latest PayPal REST API v2
* **Multiple Payment Methods** - PayPal, credit/debit cards, Pay Later (Pay in 4)
* **Advanced Card Processing** - Accept cards directly on your site (no redirect)
* **Instant & Delayed Capture** - Choose between immediate capture or authorize for later
* **Smart Payment Buttons** - Automatically shows available payment methods
* **Comprehensive Webhook Support** - Real-time payment status updates
* **Full Refund Support** - Process full and partial refunds
* **Multi-Currency** - Support for 100+ currencies
* **Sandbox Mode** - Test payments safely before going live
* **Debug Logging** - Detailed logs for troubleshooting
* **GDPR Compliant** - Secure handling of payment data

= Payment Flow =

1. Customer selects PayPal as payment method
2. PayPal Smart Payment Buttons appear
3. Customer pays via PayPal, card, or Pay Later
4. Payment is captured and booking confirmed
5. Webhooks update booking status in real-time

= Requirements =

* BookingX 2.0.0 or higher
* WordPress 5.8 or higher
* PHP 7.4 or higher
* PayPal Business account
* SSL certificate (HTTPS required for live payments)

= Getting Started =

1. Install and activate the plugin
2. Get your API credentials from PayPal Developer Dashboard
3. Navigate to BookingX > Settings > PayPal Pro
4. Enter your Client ID and Client Secret
5. Configure webhook URL in PayPal Dashboard
6. Test in sandbox mode before going live

= PayPal Developer Resources =

* Developer Dashboard: https://developer.paypal.com/dashboard/
* API Documentation: https://developer.paypal.com/api/rest/
* Webhook Events: https://developer.paypal.com/api/rest/webhooks/

== Installation ==

= Automatic Installation =

1. Go to Plugins > Add New
2. Search for "BookingX PayPal Pro"
3. Click Install Now and then Activate

= Manual Installation =

1. Download the plugin zip file
2. Upload to wp-content/plugins/ directory
3. Activate through the Plugins menu

= Configuration =

1. Go to BookingX > Settings > PayPal Pro
2. Enable PayPal payment gateway
3. Select Sandbox or Live mode
4. Enter your PayPal API credentials
5. Configure payment options (buttons, currency, etc.)
6. Set up webhook in PayPal Dashboard
7. Test payments in sandbox mode
8. Switch to Live mode when ready

== Frequently Asked Questions ==

= Do I need a PayPal Business account? =

Yes, you need a PayPal Business account to use the PayPal Commerce Platform API.

= How do I get API credentials? =

1. Log in to PayPal Developer Dashboard
2. Go to My Apps & Credentials
3. Create a new app or use existing one
4. Copy the Client ID and Secret

= What is the difference between Capture and Authorize? =

* **Capture** - Immediately charges the customer's payment method
* **Authorize** - Holds funds for later capture (useful for pre-orders or bookings that can be cancelled)

= Can I accept credit cards without a PayPal account? =

Yes! Enable "Advanced Card Processing" to accept cards directly on your site. Customers don't need a PayPal account.

= How do I set up webhooks? =

1. Go to PayPal Developer Dashboard > My Apps & Credentials
2. Select your app
3. Scroll to Webhooks section
4. Add webhook URL (shown in plugin settings)
5. Subscribe to relevant events
6. Copy Webhook ID to plugin settings

= Which webhook events are supported? =

* PAYMENT.CAPTURE.COMPLETED
* PAYMENT.CAPTURE.DENIED
* PAYMENT.CAPTURE.REFUNDED
* CHECKOUT.ORDER.APPROVED
* CHECKOUT.ORDER.COMPLETED

= Can I process refunds? =

Yes, full and partial refunds are supported. Process refunds from the booking edit screen.

= Is this PCI compliant? =

Yes, card data is handled securely by PayPal using their Hosted Fields, so your site never touches sensitive card information.

= What currencies are supported? =

PayPal supports 100+ currencies. The plugin defaults to USD but you can select from major currencies in settings.

== Screenshots ==

1. PayPal Pro settings page
2. PayPal Smart Payment Buttons on checkout
3. Advanced card processing form
4. Payment confirmation
5. Webhook event logs
6. Transaction history

== Changelog ==

= 1.0.0 - 2024-12-26 =
* Initial release
* PayPal Commerce Platform integration
* Advanced card processing support
* Pay Later (Pay in 4) support
* Webhook event handling
* Full refund support
* Multi-currency support
* Sandbox testing mode
* Debug logging

== Upgrade Notice ==

= 1.0.0 =
Initial release of PayPal Pro for BookingX.

== Support ==

For support, documentation, and feature requests, visit:
https://bookingx.com/support/

== Privacy Policy ==

This plugin integrates with PayPal Commerce Platform. When processing payments:
* Customer payment data is sent to PayPal for processing
* PayPal's Privacy Policy applies: https://www.paypal.com/privacy
* No payment card data is stored on your server
* Transaction records are stored in your database
* See BookingX Privacy Policy for more details

== Developer Notes ==

This plugin uses:
* PayPal REST API v2 (no deprecated SDK)
* WordPress HTTP API for all requests
* OAuth 2.0 for authentication
* Webhook signature verification
* PSR-4 autoloading
* WordPress coding standards

Hooks and filters available for developers - see documentation.

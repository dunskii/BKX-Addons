=== BookingX - Square Payments ===
Contributors: bookingx
Tags: square, payment, gateway, bookingx, appointments
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments via Square for BookingX bookings with support for credit cards, Apple Pay, Google Pay, and Cash App Pay.

== Description ==

Square Payments for BookingX enables you to accept secure online payments through Square's payment platform. Perfect for businesses that want a trusted, PCI-compliant payment solution with modern payment methods.

= Features =

* **Credit Card Payments**: Accept all major credit cards (Visa, Mastercard, Amex, Discover)
* **Digital Wallets**: Support for Apple Pay, Google Pay, and Cash App Pay
* **Secure Tokenization**: Card data never touches your server (PCI compliant)
* **SCA/3DS Support**: Built-in Strong Customer Authentication for European payments
* **Refund Management**: Process full and partial refunds directly from WordPress
* **Customer Sync**: Optional synchronization of customer data to Square
* **Webhook Support**: Real-time payment status updates via Square webhooks
* **Sandbox Mode**: Test payments in a safe sandbox environment
* **Multi-Currency**: Support for USD, CAD, GBP, EUR, AUD, and JPY
* **Detailed Logging**: Optional debug mode for troubleshooting

= Requirements =

* BookingX 2.0 or higher
* WordPress 5.8 or higher
* PHP 7.4 or higher
* Square account (create at squareup.com)

= Getting Started =

1. Install and activate the plugin
2. Create a Square account at squareup.com
3. Get your API credentials from the Square Developer Dashboard
4. Configure the plugin settings under BookingX > Settings > Square Payments
5. Enable the gateway and start accepting payments!

= Developer Friendly =

Built on the BookingX Add-on SDK with extensive hooks and filters for customization:

* `bkx_square_payment_completed` - Fires after successful payment
* `bkx_square_refund_completed` - Fires after successful refund
* `bkx_square_payment_webhook` - Fires when payment webhook is received
* `bkx_square_refund_webhook` - Fires when refund webhook is received

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins > Add New
3. Search for "BookingX Square Payments"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Log in to your WordPress admin panel
3. Navigate to Plugins > Add New > Upload Plugin
4. Choose the zip file and click "Install Now"
5. Activate the plugin

= Configuration =

1. Navigate to BookingX > Settings > Square Payments
2. Enter your Square API credentials:
   - Application ID
   - Access Token
   - Location ID
3. Configure webhook settings (optional but recommended)
4. Enable payment methods (Apple Pay, Google Pay, Cash App Pay)
5. Save settings and test in sandbox mode
6. Switch to production mode when ready

== Frequently Asked Questions ==

= Do I need a Square account? =

Yes, you need a Square account to use this plugin. You can sign up for free at squareup.com.

= Where do I get my API credentials? =

Log in to your Square Developer Dashboard at developer.squareup.com, create an application, and you'll find your credentials there.

= Is this plugin PCI compliant? =

Yes! The plugin uses Square's Web Payments SDK, which means card data is tokenized on the client side and never touches your server, making your integration PCI-DSS SAQ-A compliant.

= Does this support refunds? =

Yes, you can process full and partial refunds directly from the WordPress admin panel.

= Can I test payments before going live? =

Absolutely! The plugin includes a sandbox mode that lets you test payments using Square's test environment.

= What currencies are supported? =

The plugin supports USD, CAD, GBP, EUR, AUD, and JPY. Make sure your Square account is configured for your desired currency.

= Does this work with Apple Pay? =

Yes! Apple Pay is supported and can be enabled in the plugin settings. You'll need to verify your domain with Apple through the Square Dashboard.

= How do webhooks work? =

Webhooks allow Square to notify your site about payment events in real-time. Configure the webhook URL in your Square Developer Dashboard to enable this feature.

== Screenshots ==

1. Square payment form with credit card fields
2. Admin settings page
3. Payment methods configuration
4. Transaction details in database
5. Refund processing interface

== Changelog ==

= 1.0.0 - 2024-01-15 =
* Initial release
* Credit card payment support
* Apple Pay, Google Pay, and Cash App Pay support
* Refund functionality
* Customer synchronization
* Webhook integration
* Sandbox and production modes
* Multi-currency support
* Detailed transaction logging

== Upgrade Notice ==

= 1.0.0 =
Initial release of Square Payments for BookingX.

== Support ==

For support, please visit https://bookingx.com/support or email support@bookingx.com.

== Privacy ==

This plugin connects to the Square API to process payments. When processing a payment:
- Customer payment information is sent directly to Square (tokenized, never stored on your server)
- Customer contact information may be synced to Square if enabled in settings
- Transaction details are stored in your WordPress database

Square's privacy policy: https://squareup.com/us/en/legal/general/privacy

== Credits ==

Developed by the BookingX team.
Built using the Square PHP SDK: https://github.com/square/square-php-sdk

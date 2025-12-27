# Changelog

All notable changes to BookingX - Stripe Payments will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-12-26

### Added
- Initial release of BookingX Stripe Payments add-on
- Stripe PaymentIntents API integration
- Support for credit/debit card payments
- 3D Secure (SCA) authentication support
- Apple Pay integration
- Google Pay integration
- Stripe Link support
- Manual and automatic payment capture
- Full and partial refund processing
- Automatic refunds on booking cancellation
- Stripe Customer management and creation
- Saved payment method support
- Subscription support for recurring bookings
- Webhook event handling with signature verification
- Real-time payment status updates via webhooks
- Dispute notification and tracking
- Comprehensive transaction logging
- Four custom database tables for data management
- Admin meta box for payment details on bookings
- Settings page integration with BookingX
- Test mode for development
- Debug logging option
- REST API endpoints for payment processing
- Template system for customizable checkout form
- Frontend JavaScript integration with Stripe.js
- Support for zero-decimal currencies (JPY, KRW, etc.)
- Encrypted storage of API keys
- WordPress coding standards compliance
- PHPDoc documentation for all methods
- Security measures (nonce verification, capability checks, input sanitization)

### Security
- Webhook signature verification using Stripe's signing secret
- CSRF protection via WordPress nonces
- Capability checks on all admin operations
- Input sanitization and output escaping
- Encrypted API key storage using AES-256
- No card data touches the server (PCI compliance)

### Database
- `wp_bkx_stripe_transactions` - Payment transaction records
- `wp_bkx_stripe_subscriptions` - Recurring subscription records
- `wp_bkx_stripe_refunds` - Refund records
- `wp_bkx_stripe_webhook_events` - Webhook event logs

### Developer
- Extends BookingX Add-on SDK AbstractPaymentGateway
- Uses traits: HasSettings, HasLicense, HasDatabase, HasRestApi, HasWebhooks
- Action hooks for extensibility
- PSR-4 autoloading
- Composer dependency management
- Compatible with PHP 7.4+
- Compatible with WordPress 5.8+
- Requires BookingX 2.0+

[1.0.0]: https://github.com/bookingx/bkx-stripe-payments/releases/tag/v1.0.0

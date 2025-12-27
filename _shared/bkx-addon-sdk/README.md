# BookingX Add-on SDK

SDK for building BookingX add-ons with standardized architecture, common utilities, and integration with the BookingX Framework.

## Requirements

- PHP 7.4+
- WordPress 5.8+
- BookingX 2.0+

## Installation

Include the SDK in your add-on:

```php
// In your add-on's main file
require_once dirname( __FILE__ ) . '/../_shared/bkx-addon-sdk/bkx-addon-sdk.php';
```

## Quick Start

### Creating an Add-on

```php
<?php
namespace MyAddon;

use BookingX\AddonSDK\Abstracts\AbstractAddon;

class MyAddon extends AbstractAddon {

    protected string $addon_id = 'my-addon';
    protected string $addon_name = 'My Addon';
    protected string $version = '1.0.0';
    protected string $text_domain = 'my-addon';
    protected string $min_bkx_version = '2.0.0';

    protected function register_with_framework(): void {
        // Register with BookingX Framework
    }

    protected function init_hooks(): void {
        // Add WordPress hooks
    }

    protected function init_admin(): void {
        // Initialize admin features
    }

    protected function init_frontend(): void {
        // Initialize frontend features
    }

    public function get_migrations(): array {
        return [
            '1.0.0' => new Migrations\CreateMyTable(),
        ];
    }
}
```

### Creating a Payment Gateway

```php
<?php
namespace MyGateway;

use BookingX\AddonSDK\Abstracts\AbstractPaymentGateway;

class MyGateway extends AbstractPaymentGateway {

    protected string $gateway_id = 'my_gateway';
    protected string $gateway_name = 'My Gateway';
    protected bool $supports_refunds = true;

    public function process_payment( int $booking_id, array $payment_data ): array {
        // Process payment logic
        return [
            'success' => true,
            'transaction_id' => 'txn_123',
        ];
    }

    public function process_refund( int $booking_id, float $amount, string $reason = '', string $transaction_id = '' ): array {
        // Process refund logic
        return [
            'success' => true,
            'refund_id' => 'ref_123',
        ];
    }

    public function handle_webhook( array $payload ): array {
        // Handle incoming webhooks
        return [ 'success' => true ];
    }

    public function get_settings_fields(): array {
        return [
            'api_key' => [
                'title' => 'API Key',
                'type' => 'password',
            ],
        ];
    }
}
```

## Available Abstract Classes

- `AbstractAddon` - Base class for all add-ons
- `AbstractPaymentGateway` - Base class for payment gateways
- `AbstractIntegration` - Base class for third-party integrations
- `AbstractNotificationProvider` - Base class for notification providers
- `AbstractCalendarProvider` - Base class for calendar integrations
- `AbstractAnalytics` - Base class for analytics modules

## Available Traits

- `HasSettings` - Settings management with get/set methods
- `HasLicense` - EDD license management
- `HasDatabase` - Database migrations and schema management
- `HasRestApi` - REST API route registration
- `HasWebhooks` - Webhook handling with signature verification
- `HasCron` - Cron and Action Scheduler integration
- `HasAjax` - AJAX handler registration

## Services

### Logger

```php
use BookingX\AddonSDK\Services\LoggerService;

$logger = LoggerService::instance( 'my-addon' );
$logger->info( 'Something happened' );
$logger->error( 'Something went wrong', [ 'details' => $error ] );
```

### HTTP Client

```php
use BookingX\AddonSDK\Services\HttpClient;

$client = new HttpClient( 'https://api.example.com' );
$response = $client->get( '/endpoint', [ 'param' => 'value' ] );
$response = $client->post( '/endpoint', [ 'data' => 'value' ] );
```

### Encryption

```php
use BookingX\AddonSDK\Services\EncryptionService;

$encryption = EncryptionService::instance();
$encrypted = $encryption->encrypt( 'secret_api_key' );
$decrypted = $encryption->decrypt( $encrypted );
```

## Database Migrations

```php
<?php
namespace MyAddon\Migrations;

use BookingX\AddonSDK\Database\Migration;
use BookingX\AddonSDK\Database\Schema;

class CreateMyTable extends Migration {

    public function up(): void {
        $sql = Schema::create()
            ->id()
            ->bigint( 'booking_id' )->unsigned()->notNull()
            ->string( 'status', 50 )->default( 'pending' )
            ->decimal( 'amount', 10, 2 )->notNull()
            ->timestamps()
            ->index( 'idx_booking', 'booking_id' )
            ->build();

        $this->create_table( 'my_table', $sql );
    }

    public function down(): void {
        $this->drop_table( 'my_table' );
    }
}
```

## Validation

```php
use BookingX\AddonSDK\Utilities\Validator;

$validator = Validator::make( $_POST );
$validator->validate( [
    'email' => 'required|email',
    'amount' => 'required|numeric|min:1',
    'status' => 'in:pending,active,completed',
] );

if ( $validator->fails() ) {
    $errors = $validator->errors();
}
```

## Admin Components

### Settings Page

```php
use BookingX\AddonSDK\Admin\SettingsPage;

$settings = new SettingsPage( 'my-addon', 'My Addon Settings' );
$settings->add_section( 'general', 'General Settings' )
    ->add_field( 'general', 'api_key', 'API Key', 'password' )
    ->add_field( 'general', 'enabled', 'Enable', 'checkbox' )
    ->register();
```

### Meta Boxes

```php
use BookingX\AddonSDK\Admin\MetaBox;

$metabox = new MetaBox( 'my-meta', 'My Meta Box', 'bkx_booking' );
$metabox->add_field( 'custom_field', 'Custom Field', 'text' )
    ->add_field( 'notes', 'Notes', 'textarea' )
    ->register();
```

## License

GPL-2.0-or-later

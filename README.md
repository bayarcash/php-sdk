# Bayarcash Payment Gateway PHP SDK

## Introduction

The [Bayarcash](https://bayarcash.com/) SDK provides an expressive interface for interacting with Bayarcash's Payment Gateway API. This SDK supports both API v2 and v3, with enhanced features available in v3.

## Official Documentation

### Installation

To install the SDK in your project you need to require the package via composer:

```bash
composer require bayarcash/php-sdk
```

> **Note:** This package was previously published as `webimpian/bayarcash-php-sdk`. Existing installations using the old name continue to work, but new integrations should use `bayarcash/php-sdk`.

### Basic Usage

You can create an instance of the SDK like so:

```php
$bayarcash = new Webimpian\BayarcashSdk\Bayarcash(TOKEN_HERE);
$bayarcash->useSandbox(); // call this method to switch to use sandbox
```

For Laravel user:

```php
// set value for BAYARCASH_API_TOKEN and BAYARCASH_API_SECRET_KEY in the .env file
BAYARCASH_API_TOKEN=
BAYARCASH_API_SECRET_KEY=
```

```php
$bayarcash = app(\Webimpian\BayarcashSdk\Bayarcash::class);
$bayarcash->useSandbox(); // call this method to switch to use sandbox
```

### Configuration Options

```php
// Optional: Set API version (default is 'v2')
$bayarcash->setApiVersion('v3');
```

## Available Payment Channels

The SDK supports multiple payment channels:

```php
Bayarcash::FPX                 // FPX Online Banking
Bayarcash::MANUAL_TRANSFER     // Manual Bank Transfer
Bayarcash::FPX_DIRECT_DEBIT    // FPX Direct Debit
Bayarcash::FPX_LINE_OF_CREDIT  // FPX Line of Credit
Bayarcash::DUITNOW_DOBW        // DuitNow Online Banking
Bayarcash::DUITNOW_QR          // DuitNow QR
Bayarcash::SPAYLATER           // ShopeePayLater
Bayarcash::BOOST_PAYFLEX       // Boost PayFlex
Bayarcash::QRISOB              // QRIS Online Banking
Bayarcash::QRISWALLET          // QRIS Wallet
Bayarcash::NETS                // NETS
Bayarcash::CREDIT_CARD         // Credit Card
Bayarcash::ALIPAY              // Alipay
Bayarcash::WECHATPAY           // WeChat Pay
Bayarcash::PROMPTPAY           // PromptPay
Bayarcash::TOUCH_N_GO          // Touch 'n Go eWallet
Bayarcash::BOOST_WALLET        // Boost Wallet
Bayarcash::GRABPAY             // GrabPay
Bayarcash::GRABPL              // Grab PayLater
Bayarcash::SHOPEE_PAY          // ShopeePay
```

## Core Features

### Portal Management

```php
// Get all available portals
$portals = $bayarcash->getPortals();

// Get available payment channels for a specific portal
$channels = $bayarcash->getChannels('your_portal_key');
```

### FPX Bank Integration

```php
// Get list of available FPX banks
$banks = $bayarcash->fpxBanksList();
```

### Payment Processing

> Note: The checksum value and checksum validation are optional, but it is recommended for enhanced security.

```php
// Generate checksum for payment intent
$paymentIntentRequestChecksum = $bayarcash->createPaymentIntenChecksumValue(API_SECRET_KEY, REQUEST_DATA); 

// append checksum value to your REQUEST_DATA
$data['checksum'] = $paymentIntentRequestChecksum;

// Send payment request
$response = $bayarcash->createPaymentIntent(REQUEST_DATA);
header("Location: " . $response->url); // redirect payer to Bayarcash checkout page.
```

### Callback Verification

```php
// Verify callbacks
// pre-transaction callback
$validPreTransaction = $bayarcash->verifyPreTransactionCallbackData(CALLBACK_DATA, API_SECRET_KEY);

// transaction callback
$validTransaction = $bayarcash->verifyTransactionCallbackData(CALLBACK_DATA, API_SECRET_KEY);

// return URL callback
$validReturnUrl = $bayarcash->verifyReturnUrlCallbackData(CALLBACK_DATA, API_SECRET_KEY);
```

### Payment Intent Management

```php
// Get payment intent details (V3 API only)
$paymentIntent = $bayarcash->getPaymentIntent('payment_intent_id');
```

### Transaction Management

```php
// Get single transaction
$transaction = $bayarcash->getTransaction('transaction_id');

// V3 API Features
if ($bayarcash->getApiVersion() === 'v3') {
    // Get all transactions with filters
    $transactions = $bayarcash->getAllTransactions([
        'order_number' => 'ORDER123',
        'status' => '3',
        'payment_channel' => Bayarcash::FPX,
        'exchange_reference_number' => 'REF123',
        'payer_email' => 'customer@example.com'
    ]);

    // Specialized transaction queries
    $orderTransactions = $bayarcash->getTransactionByOrderNumber('ORDER123');
    $emailTransactions = $bayarcash->getTransactionsByPayerEmail('customer@example.com');
    $statusTransactions = $bayarcash->getTransactionsByStatus('3');
    $channelTransactions = $bayarcash->getTransactionsByPaymentChannel(Bayarcash::FPX);
    $refTransaction = $bayarcash->getTransactionByReferenceNumber('REF123');
}
```

### FPX Direct Debit Features

#### 1. FPX Direct Debit Enrolment

```php
$enrolmentRequestChecksum = $bayarcash->createFpxDirectDebitEnrolmentChecksumValue(API_SECRET_KEY, REQUEST_DATA); 

// append checksum value to your REQUEST_DATA
$data['checksum'] = $enrolmentRequestChecksum;

$response = $bayarcash->createFpxDirectDebitEnrollmentIntent($data);
header("Location: " . $response->url); // redirect payer to Bayarcash Fpx Direct Debit enrolment page.
```

#### 2. FPX Direct Debit Maintenance

```php
$maintenanceRequestChecksum = $bayarcash->createFpxDirectDebitMaintenanceChecksumValue(API_SECRET_KEY, REQUEST_DATA); 

// append checksum value to your REQUEST_DATA
$data['checksum'] = $maintenanceRequestChecksum;

$response = $bayarcash->createFpxDirectDebitMaintenanceIntent($data);
header("Location: " . $response->url); // redirect payer to Bayarcash Fpx Direct Debit maintenance page.
```

#### 3. FPX Direct Debit Termination

```php
$response = $bayarcash->createFpxDirectDebitTerminationIntent($data);
header("Location: " . $response->url); // redirect payer to Bayarcash Fpx Direct Debit termination page.
```

## Security Recommendations

1. Always use checksums for payment requests when possible
2. Verify all callbacks using the provided verification methods
3. Store and validate transaction IDs to prevent duplicate processing
4. Use HTTPS for all API communications
5. Keep your API tokens and secret keys secure

## API Documentation

Please refer to the [Official Bayarcash API Documentation](https://api.webimpian.support/bayarcash) for detailed information about our API.

## Support

For support questions, please contact Bayarcash support or raise an issue in the repository.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for version update history.

## License

This SDK is open-sourced software licensed under the MIT license.
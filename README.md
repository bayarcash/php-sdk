# Bayarcash Payment Gateway PHP SDK

[![Latest Stable Version](https://img.shields.io/packagist/v/bayarcash/php-sdk.svg)](https://packagist.org/packages/bayarcash/php-sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/bayarcash/php-sdk.svg)](https://packagist.org/packages/bayarcash/php-sdk)
[![Downloads (legacy)](https://img.shields.io/packagist/dt/webimpian/bayarcash-php-sdk.svg?label=downloads%20%28legacy%29)](https://packagist.org/packages/webimpian/bayarcash-php-sdk)
[![PHP Version Require](https://img.shields.io/packagist/php-v/bayarcash/php-sdk.svg)](https://packagist.org/packages/bayarcash/php-sdk)
[![License](https://img.shields.io/packagist/l/bayarcash/php-sdk.svg)](https://packagist.org/packages/bayarcash/php-sdk)

The [Bayarcash](https://bayarcash.com/) SDK provides an expressive interface for interacting with Bayarcash's Payment Gateway API. It supports both API **v2** (default) and **v3**, with additional features available in v3.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Getting Started](#getting-started)
  - [Plain PHP](#plain-php)
  - [Laravel](#laravel)
  - [Configuration](#configuration)
- [Quick Start: Accept a Payment](#quick-start-accept-a-payment)
- [Payment Channels](#payment-channels)
- [Payment Intents](#payment-intents)
- [Handling Callbacks](#handling-callbacks)
- [Payment & Transaction Status](#payment--transaction-status)
- [Transactions](#transactions)
- [DuitNow QR](#duitnow-qr)
- [FPX Direct Debit](#fpx-direct-debit)
- [Manual Bank Transfer](#manual-bank-transfer)
- [Portals & FPX Banks](#portals--fpx-banks)
- [Error Handling](#error-handling)
- [Response Objects](#response-objects)
- [Security Recommendations](#security-recommendations)
- [Support](#support)

## Requirements

- PHP 7.4 – 8.5
- `ext-json`
- Guzzle 7 (installed automatically)

## Installation

Install via Composer:

```bash
composer require bayarcash/php-sdk
```

> **Note:** This package was previously published as `webimpian/bayarcash-php-sdk`. Existing installations using the old name continue to work, but new integrations should use `bayarcash/php-sdk`.

You will need two credentials from your Bayarcash console:

- **API token** — used to authenticate SDK requests.
- **API secret key** — used to generate request checksums and verify callbacks.

## Getting Started

### Plain PHP

```php
use Bayarcash\Bayarcash;

$bayarcash = new Bayarcash('YOUR_API_TOKEN');
$bayarcash->useSandbox(); // remove this line in production
```

### Laravel

The service provider is auto-discovered. Add your credentials to `.env`:

```dotenv
BAYARCASH_API_TOKEN=your_api_token
BAYARCASH_API_SECRET_KEY=your_api_secret_key
```

Then resolve the SDK from the container:

```php
use Bayarcash\Bayarcash;

$bayarcash = app(Bayarcash::class);
$bayarcash->useSandbox(); // remove this line in production
```

To publish the config file:

```bash
php artisan vendor:publish --tag=bayarcash-sdk-config
```

### Configuration

```php
$bayarcash
    ->useSandbox()          // switch to the sandbox environment
    ->setApiVersion('v3')   // 'v2' (default) or 'v3'
    ->setTimeout(60);       // request timeout in seconds (default 30)

$bayarcash->getApiVersion(); // read back the current version
```

> Call `useSandbox()` / `setApiVersion()` **before** making requests. Omit `useSandbox()` in production to hit the live gateway.

## Quick Start: Accept a Payment

A complete FPX payment flow, from creating the payment to verifying the result:

```php
use Bayarcash\Bayarcash;
use Bayarcash\Fpx;

$bayarcash = new Bayarcash('YOUR_API_TOKEN');
$bayarcash->useSandbox();

$apiSecretKey = 'YOUR_API_SECRET_KEY';

// 1. Build the payment request
$data = [
    'portal_key'             => 'your_portal_key',
    'payment_channel'        => Bayarcash::FPX,
    'order_number'           => 'INV-1001',
    'amount'                 => '10.00',
    'payer_name'             => 'Ahmad bin Abdullah',
    'payer_email'            => 'ahmad@example.com',
    'payer_telephone_number' => '0123456789',
    'return_url'             => 'https://your-site.com/payment/return',
    'callback_url'           => 'https://your-site.com/payment/callback',
];

// 2. Sign it (recommended)
$data['checksum'] = $bayarcash->createPaymentIntentChecksumValue($apiSecretKey, $data);

// 3. Create the payment intent and redirect the payer to Bayarcash
$paymentIntent = $bayarcash->createPaymentIntent($data);

header('Location: ' . $paymentIntent->url);
exit;
```

After payment, Bayarcash calls your `callback_url` (server-to-server) and redirects the payer to your `return_url`. Verify both — see [Handling Callbacks](#handling-callbacks).

## Payment Channels

Pass one of these constants (or an array of them) as `payment_channel`:

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
Bayarcash::SHOPBACK_BNPL       // ShopBack BNPL
Bayarcash::SHOPEE_PAY          // ShopeePay
Bayarcash::FPX_B2B             // FPX B2B
```

## Payment Intents

Create a payment intent, then retrieve or cancel it later.

```php
$paymentIntent = $bayarcash->createPaymentIntent($data);
```

**Request fields:**

| Field | Required | Description |
|---|---|---|
| `portal_key` | ✅ | Your portal key. |
| `order_number` | ✅ | Your reference. Max 30 chars. |
| `amount` | ✅ | String with up to 2 decimals, e.g. `'10.00'`. Range `1.00`–`30000.00` (min differs for some channels). |
| `payer_name` | ✅ | Max 150 chars. |
| `payer_email` | ✅ | Valid email, max 250 chars. |
| `payment_channel` | ➖ | A `Bayarcash::*` channel id, or an array of ids. If omitted, the payer chooses on the Bayarcash page. |
| `payer_telephone_number` | ➖ | Required for e-wallet / DuitNow channels. Max 20 chars. |
| `return_url` | ➖ | Where the payer's browser is redirected after payment. |
| `callback_url` | ➖ | Server-to-server notification URL. |
| `metadata` | ➖ | Any extra data you want echoed back. |
| `checksum` | ➖ | Recommended. See below. |

### Checksum

The checksum protects the request from tampering. Generate it **after** building the request and append it as `checksum`:

```php
$data['checksum'] = $bayarcash->createPaymentIntentChecksumValue($apiSecretKey, $data);
```

The checksum is computed from `payment_channel`, `order_number`, `amount`, `payer_name`, and `payer_email`.

### Idempotency (v3)

Pass a unique key (UUID v4) as the second argument to make a create safe to retry — a
timed-out request can be re-sent with the same key without creating a duplicate intent:

```php
$paymentIntent = $bayarcash->createPaymentIntent($data, $idempotencyKey);
```

Keys are scoped per portal and expire after 24 hours.

### Payer identity verification (v3)

For a single FPX (`Bayarcash::FPX`) or FPX B2B (`Bayarcash::FPX_B2B`) intent, set
`verify_identity` and add the payer's bank account number (`fpx_eaccount_number`) and ID —
NRIC, passport, or business registration number (`fpx_ebuyer_id`):

```php
$data['verify_identity']     = true;
$data['fpx_eaccount_number'] = '1234567890';
$data['fpx_ebuyer_id']       = '900101011234';
```

The result comes back on the transaction as `payerIdentityVerified` (`true`/`false`/`null`)
and `fpxExtraInfo` (`account_type`, `account_number_verified`, `buyer_id_verified`).

### Retrieve or cancel (v3)

```php
$intent = $bayarcash->getPaymentIntent('payment_intent_id');
$bayarcash->cancelPaymentIntent('payment_intent_id');
```

## Handling Callbacks

Bayarcash sends **two kinds** of notification. Always verify them with your API secret key before trusting the data.

| Notification | How it arrives | Read it from |
|---|---|---|
| `callback_url` (transaction) | Server-to-server **POST** (form-encoded) | `$_POST` / `$request->all()` |
| `return_url` (payer redirect) | Browser redirect — **POST** on v2, **GET** query on v3 | `$_POST` / `$_GET` / `$request->all()` |

```php
$callbackData = $_POST; // Laravel: $request->all()

// Transaction callback (sent to your callback_url)
if ($bayarcash->verifyTransactionCallbackData($callbackData, $apiSecretKey)) {
    // Data is authentic — safe to process.
}

// Payer redirect (sent to your return_url)
if ($bayarcash->verifyReturnUrlCallbackData($callbackData, $apiSecretKey)) {
    // ...
}

// Pre-transaction callback (sent before the transaction record)
if ($bayarcash->verifyPreTransactionCallbackData($callbackData, $apiSecretKey)) {
    // ...
}
```

Each verifier returns `true` only when the checksum matches. See [FPX Direct Debit](#fpx-direct-debit) for mandate-specific callback verifiers.

## Payment & Transaction Status

Transaction status is an integer code. Use the `Fpx` helper instead of hardcoding numbers:

```php
use Bayarcash\Fpx;

Fpx::STATUS_NEW;        // 0
Fpx::STATUS_PENDING;    // 1
Fpx::STATUS_FAILED;     // 2
Fpx::STATUS_SUCCESS;    // 3
Fpx::STATUS_CANCELLED;  // 4

if ((int) $callbackData['status'] === Fpx::STATUS_SUCCESS) {
    // Payment successful
}

echo Fpx::getStatusText((int) $callbackData['status']); // e.g. "Successful"
```

## Transactions

```php
// Get a single transaction (v2 and v3)
$transaction = $bayarcash->getTransaction('transaction_id');
```

The following query helpers require **API v3** and throw an exception on v2:

```php
$bayarcash->setApiVersion('v3');

$result = $bayarcash->getAllTransactions([
    'order_number'              => 'INV-1001',
    'status'                    => '3',
    'payment_channel'           => Bayarcash::FPX,
    'exchange_reference_number' => 'REF123',
    'payer_email'               => 'ahmad@example.com',
]);
// $result['data'] => TransactionResource[], $result['meta'] => pagination meta

$byOrder   = $bayarcash->getTransactionByOrderNumber('INV-1001');
$byEmail   = $bayarcash->getTransactionsByPayerEmail('ahmad@example.com');
$byStatus  = $bayarcash->getTransactionsByStatus('3');
$byChannel = $bayarcash->getTransactionsByPaymentChannel(Bayarcash::FPX);
$byRef     = $bayarcash->getTransactionByReferenceNumber('REF123'); // single or null
```

## DuitNow QR

Render a DuitNow QR on your **own** page instead of redirecting the payer to Bayarcash. Requires a single DuitNow QR channel and API v3.

**1. Generate the QR while creating the intent** (one step):

```php
$intent = $bayarcash->createDuitNowQrPaymentIntent([
    'portal_key'      => 'your_portal_key',
    'payment_channel' => Bayarcash::DUITNOW_QR,
    'order_number'    => 'ORD001',
    'amount'          => '100.00',
    'payer_name'      => 'MOHD ALI',
    'payer_email'     => 'm.ali@gmail.com',
]);

$qr = $intent->duitnowQr;
```

**2. Poll the payment status** while the payer scans:

```php
$status = $bayarcash->getDuitNowQrStatus($qr->transactionId);
```

**3. Regenerate** a fresh QR for an existing intent (e.g. after the previous one expired):

```php
$qr = $bayarcash->regenerateDuitNowQr($paymentIntentId);
```

Both `$intent->duitnowQr` and `regenerateDuitNowQr()` return a `DuitNowQrResource`:

| Property | Description |
|---|---|
| `qrImage` | PNG data URI — drop straight into an `<img>`. |
| `qrString` | Raw QR payload, if you render it yourself. |
| `qrExpiresAt` | When the QR expires. |
| `transactionId` | Use it to poll the status. |
| `pollUrl` | Full status URL, if you prefer to poll it directly. |

> Webhooks to your `callback_url` are the primary notification path — polling is only a fallback. Respect `next_poll_after_ms` between polls and stop once the QR expires.

## FPX Direct Debit

FPX Direct Debit lets you set up a recurring mandate and later maintain or terminate it. Constants live on the `FpxDirectDebit` class:

```php
use Bayarcash\FpxDirectDebit;

// Payer ID type
FpxDirectDebit::NRIC;                  // 1 (New IC)
FpxDirectDebit::OLD_IC;                // 2
FpxDirectDebit::PASSPORT;              // 3
FpxDirectDebit::BUSINESS_REGISTRATION; // 4
FpxDirectDebit::OTHERS;                // 5

// Frequency mode
FpxDirectDebit::MODE_DAILY;   // 'DL'
FpxDirectDebit::MODE_WEEKLY;  // 'WK'
FpxDirectDebit::MODE_MONTHLY; // 'MT'
FpxDirectDebit::MODE_YEARLY;  // 'YR'
```

### 1. Enrolment

```php
$data = [
    'portal_key'             => 'your_portal_key',
    'order_number'           => 'DD-1001',
    'amount'                 => '10.00', // range 5.00–30000.00
    'payer_name'             => 'Ahmad bin Abdullah',
    'payer_id_type'          => FpxDirectDebit::NRIC,
    'payer_id'               => '900101011234',
    'payer_email'            => 'ahmad@example.com', // max 27 chars
    'payer_telephone_number' => '0123456789',
    'application_reason'      => 'Monthly subscription',
    'frequency_mode'         => FpxDirectDebit::MODE_MONTHLY,
    'effective_date'         => '2026-08-01', // optional, Y-m-d
    'expiry_date'            => '2027-08-01', // optional, Y-m-d
    'return_url'             => 'https://your-site.com/mandate/return',
];

$data['checksum'] = $bayarcash->createFpxDirectDebitEnrolmentChecksumValue($apiSecretKey, $data);

$mandate = $bayarcash->createFpxDirectDebitEnrollment($data);
header('Location: ' . $mandate->url); // redirect payer to the enrolment page
```

### 2. Maintenance

Update an existing mandate (identified by its mandate id):

```php
$data = [
    'amount'                 => '15.00',
    'payer_email'            => 'ahmad@example.com',
    'payer_telephone_number' => '0123456789',
    'application_reason'      => 'Update amount',
    'frequency_mode'         => FpxDirectDebit::MODE_MONTHLY,
];

$data['checksum'] = $bayarcash->createFpxDirectDebitMaintenanceChecksumValue($apiSecretKey, $data);

$mandate = $bayarcash->createFpxDirectDebitMaintenance($mandateId, $data);
header('Location: ' . $mandate->url);
```

### 3. Termination

```php
$mandate = $bayarcash->createFpxDirectDebitTermination($mandateId, [
    'application_reason' => 'Customer cancelled',
]);
header('Location: ' . $mandate->url);
```

### Retrieving mandates & verifying mandate callbacks

```php
$mandate     = $bayarcash->getFpxDirectDebit($mandateId);
$transaction = $bayarcash->getFpxDirectDebitTransaction($transactionId);

$mandates     = $bayarcash->getAllFpxDirectDebits();
$transactions = $bayarcash->getAllFpxDirectDebitTransactions();

$bayarcash->deactivateFpxDirectDebit($mandateId);
$bayarcash->activateFpxDirectDebit($mandateId);

// Mandate callback verifiers
$bayarcash->verifyDirectDebitBankApprovalCallbackData($callbackData, $apiSecretKey);
$bayarcash->verifyDirectDebitAuthorizationCallbackData($callbackData, $apiSecretKey);
$bayarcash->verifyDirectDebitTransactionCallbackData($callbackData, $apiSecretKey);
```

## Manual Bank Transfer

Submit a manual (offline) bank transfer with proof of payment:

```php
$response = $bayarcash->createManualBankTransfer([
    'portal_key'                   => 'your_portal_key',
    'payment_gateway'              => Bayarcash::MANUAL_TRANSFER, // must be 2
    'order_no'                     => 'MT-1001',
    'buyer_name'                   => 'Ahmad bin Abdullah',
    'buyer_email'                  => 'ahmad@example.com',
    'buyer_tel_no'                 => '0123456789', // optional
    'order_amount'                 => '10.00',
    'merchant_bank_name'           => 'Maybank',
    'merchant_bank_account'        => '1234567890',
    'merchant_bank_account_holder' => 'Your Company Sdn Bhd',
    'bank_transfer_type'           => 'Internet Banking', // or 'Cash Deposit Machine (CDM)'
    'bank_transfer_notes'          => 'Payment for order MT-1001',
    'bank_transfer_date'           => '2026-07-22', // optional, defaults to today
    'proof_of_payment'             => '/path/to/receipt.jpg', // jpeg/png/gif/pdf, max 10 MB
]);
```

Update the status of an existing transfer:

```php
use Bayarcash\Fpx;

$bayarcash->updateManualBankTransferStatus(
    'ref_no_here',
    (string) Fpx::STATUS_SUCCESS,
    '10.00'
);
```

## Portals & FPX Banks

```php
$portals   = $bayarcash->getPortals();
$portal    = $bayarcash->getPortal('portal_id');
$channels  = $bayarcash->getChannels('your_portal_key');

$banks     = $bayarcash->fpxBanksList();
$dobwBanks = $bayarcash->duitNowDobwBanksList();

$status    = $bayarcash->getServerStatus();
```

## Error Handling

Failed API calls throw typed exceptions. Catch them to handle errors gracefully:

```php
use Bayarcash\Exceptions\ValidationException;
use Bayarcash\Exceptions\FailedActionException;
use Bayarcash\Exceptions\NotFoundException;
use Bayarcash\Exceptions\RateLimitExceededException;

try {
    $paymentIntent = $bayarcash->createPaymentIntent($data);
} catch (ValidationException $e) {
    // 422 — invalid request data
    $errors = $e->errors();
} catch (NotFoundException $e) {
    // 404 — resource not found
} catch (RateLimitExceededException $e) {
    // 429 — too many requests
    $resetAt = $e->rateLimitResetsAt; // unix timestamp or null
} catch (FailedActionException $e) {
    // 400 — request failed
    $message = $e->getMessage();
}
```

| Exception | HTTP | Meaning |
|---|---|---|
| `ValidationException` | 422 | Invalid data. Call `->errors()` for details. |
| `FailedActionException` | 400 | Request failed. `->getMessage()` has the reason. |
| `NotFoundException` | 404 | Resource not found. |
| `RateLimitExceededException` | 429 | Rate limited. `->rateLimitResetsAt` holds the reset time. |
| `TimeoutException` | — | Thrown by the optional `retry()` helper after a timeout. |

## Response Objects

API methods return typed resource objects. Common properties:

**`PaymentIntentResource`** (from `createPaymentIntent` / `getPaymentIntent`)

```php
$paymentIntent->url;          // checkout URL to redirect the payer to
$paymentIntent->id;
$paymentIntent->status;
$paymentIntent->amount;
$paymentIntent->orderNumber;
$paymentIntent->payerName;
$paymentIntent->payerEmail;
```

**`TransactionResource`** (from `getTransaction` / transaction queries)

```php
$transaction->id;
$transaction->status;                   // int status code — see Fpx constants
$transaction->statusDescription;
$transaction->amount;
$transaction->orderNumber;
$transaction->exchangeReferenceNumber;
$transaction->payerName;
$transaction->payerEmail;
```

Any missing field is `null`. Convert a resource (including nested resources) to an array:

```php
$transaction->toArray();
```

## Security Recommendations

1. Always send a `checksum` with payment and mandate requests.
2. Verify **every** callback with the provided verification methods before acting on it.
3. Store and check transaction ids to prevent duplicate processing.
4. Use HTTPS for your `return_url` and `callback_url`.
5. Keep your API token and secret key out of source control.

## API Documentation

For full API details, see the [Official Bayarcash API Documentation](https://api.webimpian.support/bayarcash).

## Support

For support questions, contact Bayarcash support or open an issue in this repository.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the version history.

## License

Open-sourced software licensed under the [MIT license](LICENSE).

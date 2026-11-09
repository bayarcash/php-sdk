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
- [Boost Recurring Payments](#boost-recurring-payments-v3-api-only)
- [Security Recommendations](#security-recommendations)
- [Support](#support)

## Requirements

- PHP 7.4 – 8.5
- `ext-json`
- Guzzle 7 or 8 (installed automatically)

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
Fpx::STATUS_ABANDONED;  // 5
Fpx::STATUS_AUTHORIZED; // 6 - card authorized, no money moved
Fpx::STATUS_VOID;       // 7
Fpx::STATUS_REFUND;     // 8

if ((int) $callbackData['status'] === Fpx::STATUS_SUCCESS) {
    // Payment successful
}

echo Fpx::getStatusText((int) $callbackData['status']); // e.g. "Successful"
```

A `validate_only` save-card (see [Boost Recurring Payments](#boost-recurring-payments-v3-api-only))
succeeds with status `6` (`Fpx::STATUS_AUTHORIZED`) in its callback, not `3`: the card is authorized
and saved, no money moves.

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
// 'recurring_type' => 'on_demand' | 'subscription' | 'installment' narrows the list to recurring charges

$byOrder   = $bayarcash->getTransactionByOrderNumber('INV-1001'); // TransactionResource[]
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

// Mandate and application status
FpxDirectDebit::STATUS_NEW;                     // 0
FpxDirectDebit::STATUS_WAITING_APPROVAL;        // 1
FpxDirectDebit::STATUS_FAILED_BANK_VERIFICATION; // 2
FpxDirectDebit::STATUS_ACTIVE;                  // 3
FpxDirectDebit::STATUS_TERMINATED;              // 4
FpxDirectDebit::STATUS_APPROVED;                // 5
FpxDirectDebit::STATUS_REJECTED;                // 6
FpxDirectDebit::STATUS_CANCELLED;               // 7
FpxDirectDebit::STATUS_ERROR;                   // 8
FpxDirectDebit::STATUS_EXPIRED;                 // 9
FpxDirectDebit::STATUS_INACTIVE;                // 10

FpxDirectDebit::getStatusText(9); // "Expired"
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
use Bayarcash\Exceptions\ApiErrorException;

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
} catch (ApiErrorException $e) {
    // any other error status: 401, 403, 409, 5xx, a 504 page from a proxy, ...
    $status = $e->status();    // also $e->getCode()
    $code   = $e->errorCode(); // `error.code` of {"error": {"code", ...}}, or null
    $body   = $e->body();      // also $e->getMessage(): the raw body
}
```

| Exception | HTTP | Meaning |
|---|---|---|
| `ValidationException` | 422 | Invalid data. Call `->errors()` for details. |
| `FailedActionException` | 400 | Request failed. `->getMessage()` has the reason. |
| `NotFoundException` | 404 | Resource not found. |
| `RateLimitExceededException` | 429 | Rate limited. `->rateLimitResetsAt` holds the reset time. |
| `ApiErrorException` | any other (401, 403, 409, 5xx, ...) | `->getMessage()` / `->body()` is the raw body, `->status()` / `->getCode()` the HTTP status, `->errors()` the decoded JSON (or `[]`), `->errorCode()` the `error.code` (or null). Extends `\Exception`. |
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

## Boost Recurring Payments (V3 API only)

> Requires `$bayarcash->setApiVersion('v3')`. A subscription or installment is created at the
> provider, `pending`, and comes back with `redirectUrl` — the provider's card page. Redirect
> the buyer there: the agreement is `active` once they complete it and the first charge is
> taken at that moment. An on-demand payment charges the saved card synchronously and returns
> the charge. Your checkout holds the recurring consent (there is no Bayarcash page); the
> card processor reviews every merchant before switching recurring on (`403` until then).
>
> Ids: customers `rcus_`, plans `rpln_`, subscriptions `rsub_`, installments `rins_`,
> charges `trx_`.
>
> Creating a subscription, an installment or an on-demand charge requires your own `reference`
> (up to 36 of `A-Z a-z 0-9 _ -`, unique per merchant per type, forever, case-insensitive: `INV-1`
> and `inv-1` are the same reference). A reference already on
> file — any body, any state — throws `ValidationException` (`422 reference_already_used`, "The
> reference X already exists."); the record on file is never returned. Do not repeat a create to
> learn its outcome: read it. A first call the provider did not settle in time answers `202`: an
> agreement as itself, `pending`, without `redirectUrl`; an on-demand charge with no charge record yet,
> only `reference` and `status` the string `pending` (`id` is null).
>
> Every recurring call waits 90 seconds, `saveCard()` 120 (or your `setTimeout()` when larger; the
> one-off calls keep `setTimeout()`, 30 by default), so your own PHP and proxy limits must allow 90
> seconds (120 for `saveCard()`). A create
> can still take longer: an on-demand charge with `customer_id` about 90 seconds, a create with an
> inline `customer` up to 227 seconds (on-demand) or 182 seconds (subscription, installment).
> **A timeout or a transport error on a create is the same as a `202`**: the record may already
> exist and an on-demand card may already have been charged. Then:
>
> - Subscription / installment: `getSubscription($id)` / `getInstallment($id)` with the 202's id,
>   or `getSubscriptions(['reference' => ...])` / `getInstallments(['reference' => ...])` when no
>   answer arrived at all. While it reads `pending` it carries `redirectUrl`; the card page lives
>   20 minutes from `createdAt`. After that an installment needs a new create (with a new
>   reference); a subscription must be canceled first (`cancelSubscription()`), or left until it
>   reads `expired` (about 30 minutes after creation) — see "one subscription per customer per plan".
> - On-demand charge: `getAllTransactions(['order_number' => $reference, 'recurring_type' => 'on_demand'])`
>   (`data` holds `TransactionResource`) once it is booked, or the charge callback. **After a `202`
>   the card may already have been charged.** Wait for the callback or read the charge BEFORE ever charging again under a new
>   reference — otherwise the customer can be charged twice. Right after the `202` an empty answer
>   is not yet proof: Bayarcash's background check first asks about the charge 15 minutes after it
>   was recorded, then every 5 minutes, so it is usually booked within about 20 minutes; it can
>   take longer while the exchange is unreachable or has not settled the charge. Rely on the
>   callback: charge again under a new reference only once the callback says the charge failed,
>   or that lookup still finds nothing well after that.
>
> **A `401`, `403` or `429`, or a `502` whose body's `error.code` is `upstream_auth`, on a create
> is not a `202`**: nothing was created and no card was charged. Send it again under a **new**
> reference. After a `429`, wait a short while first: the SDK does not expose `Retry-After`, and
> `->rateLimitResetsAt` is set only when the API sends `X-RateLimit-Reset` (null otherwise). A
> `429` throws `RateLimitExceededException`; a `401`, `403`, `409` or `5xx` throws
> `ApiErrorException` (an `\Exception`) whose message is the API's body, with `->status()` and
> `->errorCode()` (`error.code`, e.g. `upstream_auth`).
>
> A `409` `upstream_conflict` on a create is not a `202` either: the provider refused it for the
> current state and nothing was created; on an update, re-read the record before trying again.
> **Any other `5xx` on a create, including a `504` page from a proxy, is the same as a timeout**:
> read the outcome as above.
>
> One subscription per customer per plan: while the customer holds one to the plan that has not
> ended (`pending` — including one still being created — `active` or `paused`), a new
> `createSubscription()` for the same customer and plan throws `ValidationException` (`422
> customer_already_subscribed`, "The customer is already subscribed to this plan."), as the
> provider itself refuses it. Cancel the existing one first; one still being created must settle
> first. Installments have no plan and are not affected.
>
> None of these send an `Idempotency-Key`, and neither do customers, plans or saved cards. A
> customer email you already hold is a `422 customer_email_exists`: find it with
> `getCustomers(['search' => $email])`; when that finds none, the email is held at the provider
> outside your records — contact support. A plan requires your own `code`
> (the same rule as `reference`: `PLAN-1` and `plan-1` are the same code); a code already on file is a `422 plan_code_already_used`, and
> `getPlans(['code' => ...])` finds the plan. Every `saveCard()` call makes a new card link:
> creating one charges nothing, but every link is payable, so with `capture` two paid links are two
> sales. After a timeout, look the intent up with `getPaymentIntent()` if you have its id, or create
> a new one and send the payer only the newest link.

```php
use Bayarcash\BoostRecurring;
use Bayarcash\Fpx;

$bayarcash->setApiVersion('v3');

// Customers — a known email throws ValidationException (422 customer_email_exists)
$customer = $bayarcash->createCustomer([
    'full_name' => 'Jane Doe',
    'email'     => 'jane@example.com',
    'phone'     => '60123456789',
]);
$customers = $bayarcash->getCustomers(['search' => 'jane', 'per_page' => 50]); // ['data','meta','links']
$customer  = $bayarcash->getCustomer('rcus_xxx');
// Save a card: a Credit Card payment intent (the createPaymentIntent body less the payer, who is
// the customer) plus `tokenize`
//   capture       - charge the amount and save the card (minimum 2.00)
//   validate_only - authorize the amount and save the card, no money moves (minimum 1.00)
$saveCard  = $bayarcash->saveCard('rcus_xxx', [
    'portal_key'             => 'your_portal_key',
    'order_number'           => 'CARD-0001',
    'amount'                 => '1.00',
    'tokenize'               => 'validate_only',
]); // PaymentIntentResource; payment_channel defaults to 12 (Credit Card)
// Optional checksum: it is checked against the customer's name and email, so pass
// getCustomer($id)->fullName / ->email to createSaveCardChecksumValue(), or omit the checksum:
//   $data['checksum'] = $bayarcash->createSaveCardChecksumValue($apiSecretKey, $data, $customer->fullName, $customer->email);
// url is the provider's card page: redirect at once. It lives 30 minutes and is used once.
// If it cannot be started, saveCard() throws ApiErrorException: 403 save_card_refused or 502
// save_card_failed (->errorCode()), the message being the JSON body
// {"error": {"code", "message", "payment_intent_id"}}.
header('Location: ' . $saveCard->url);
// Later: $bayarcash->getPaymentIntent($saveCard->id)->status is 'authorized' (validate_only) or 'paid' (capture);
// a validate_only callback succeeds with status 6 (Fpx::STATUS_AUTHORIZED), not 3

// Plans — interval is day|week|month|year
$plan = $bayarcash->createPlan([
    'code' => 'PRO-MONTHLY', // required; your own plan code, unique forever (->code)
    'name' => 'Pro', 'description' => 'Pro plan',
    'price' => 49.90, 'currency' => 'MYR', 'interval' => BoostRecurring::INTERVAL_MONTHLY, // 'month'
]);
$plan = $bayarcash->updatePlan('rpln_xxx', ['price' => 59.90]);

// Subscriptions — created at the provider, pending; redirect the buyer to ->redirectUrl
$subscription = $bayarcash->createSubscription([
    'portal_key'  => 'your-portal-key',   // required; the portal must have the card channel
    'reference'   => 'SUB-0001',          // required; your own id, unique forever
    'customer_id' => 'rcus_xxx',          // or inline: 'customer' => ['full_name','email','phone']
    'plan_id'     => 'rpln_xxx',
    'start_date'  => date('c', strtotime('+1 day')), // ISO-8601 timestamp, an hour or more ahead
    'callback_url'=> 'https://merchant/webhook',
    'return_url'  => 'https://merchant/return',
]);
if ($subscription->redirectUrl) {
    header('Location: ' . $subscription->redirectUrl); // the provider's card page, lives 20 minutes
} else {
    // 202: not settled in time - read it later with getSubscription($subscription->id) while pending.
}
// Lost the answer altogether (a timeout or a transport error)? Do not create it again; read it:
// $bayarcash->getSubscriptions(['reference' => 'SUB-0001'])
// The customer already holds a subscription to this plan that has not ended: ValidationException
// (422 customer_already_subscribed) - cancel that one first.

$subscription = $bayarcash->getSubscription('rsub_xxx'); // ->plan holds the plan it bills on
$bayarcash->pauseSubscription('rsub_xxx');
$bayarcash->resumeSubscription('rsub_xxx');
$bayarcash->cancelSubscription('rsub_xxx');

// Installments — the same: pending, with the card page
$installment = $bayarcash->createInstallment([
    'portal_key'        => 'your-portal-key',
    'reference'         => 'INS-0001',
    'customer_id'       => 'rcus_xxx',
    'total_amount'      => 1200.00,
    'interval'          => BoostRecurring::INTERVAL_MONTHLY,
    'installment_count' => 12,
    'description'       => 'Laptop, 12 payments', // required, up to 500 characters
    'start_date'        => date('c', strtotime('+1 day')),
    'callback_url'      => 'https://merchant/webhook',
    'return_url'        => 'https://merchant/return',
]);
if ($installment->redirectUrl) {
    header('Location: ' . $installment->redirectUrl);
} else {
    // 202: not settled in time - read it later with getInstallment($installment->id) while pending.
}
// Lost the answer altogether? $bayarcash->getInstallments(['reference' => 'INS-0001'])

// On-demand charge on a saved card — synchronous, the answer is the charge (no return_url)
$charge = $bayarcash->createOnDemandPayment([
    'portal_key'  => 'your-portal-key',
    'reference'   => 'INV-1042',      // unique forever: a repeat is a 422 reference_already_used
    'customer_id' => 'rcus_xxx',
    'amount'      => 25.00,
    'description' => 'Wallet top up', // letters, digits, spaces, full stops, hyphens
]); // ->orderNumber is your reference, ->exchangeReferenceNumber the BPGR- reference
if ($charge->status === 'pending') {
    // 202: no charge record yet, only ->reference, but the card may already have been charged.
    // A timeout or a transport error on this call means the same. Do not repeat, and never charge
    // again under a new reference before you know: wait for the charge callback, or read
    // $bayarcash->getAllTransactions(['order_number' => 'INV-1042', 'recurring_type' => 'on_demand'])['data'].
    // Usually booked within about 20 minutes, longer while the exchange is unreachable or has not settled
    // it: charge under a new reference only once the callback says it failed, or still empty well after that.
}
$charge->status == Fpx::STATUS_SUCCESS; // 3 charged, 2 declined, 1 still settling (callback follows)
// A customer with no saved card throws ValidationException (422 customer_no_saved_card): saveCard() first.

// Back on your return_url after the card page: the agreement's state, signed with your API secret key
// (recurring_type, status, customer_id, subscription_id, installment_id, transaction_id,
// exchange_reference_number, exchange_transaction_id, currency, amount, transaction_status,
// transaction_status_description + datetime + checksum). Verify before trusting status.
if (! $bayarcash->verifyRecurringReturnUrlData($_GET, $apiSecretKey)) {
    http_response_code(400); // someone typed a URL: act on nothing in it
    exit;
}
// status is the agreement's: pending|active|failed|expired|canceled... The transaction_* fields
// are the first charge, '' until it is booked (its callback lands seconds before or after you).
// The rule: verify, then read the agreement by id, then act. recurring_type says which one it is:
$agreement = $_GET['recurring_type'] === 'installment'
    ? $bayarcash->getInstallment($_GET['installment_id'])
    : $bayarcash->getSubscription($_GET['subscription_id']);
// A buyer who closes the card page never lands here: the first-charge callback still tells you
// when they enrolled, and the agreement reads `expired` 30 minutes after a create nobody finished.

// Charges are transaction records (TransactionResource, same shape as getTransaction()),
// paginated: ['data' => TransactionResource[], 'meta' => [...], 'links' => [...]]
$page = $bayarcash->getCustomerPayments('rcus_xxx', 1, 50); // the only list with on-demand charges
$page = $bayarcash->getSubscriptionPayments('rsub_xxx');    // page = 1, per_page = 15 (max 100)
$page = $bayarcash->getInstallmentPayments('rins_xxx', 2, 100);

foreach ($page['data'] as $charge) {
    $charge->id;                    // trx_...
    $charge->status == Fpx::STATUS_SUCCESS; // 3 successful, 2 failed, 1 pending
    $charge->exchangeTransactionId; // the provider charge id
    $charge->createdAt;             // 'Y-m-d H:i:s', Kuala Lumpur time
    $charge->recurringCustomerId;   // rcus_...
    $charge->subscription;          // array (with 'plan') on a subscription charge, else null
    $charge->installment;           // array on an installment charge, else null
}

$charge = $bayarcash->getPayment('trx_xxx'); // or the provider charge id
```

Status constants: `BoostRecurring::STATUS_PENDING|STATUS_ACTIVE|STATUS_PAUSED|STATUS_CANCELED|STATUS_EXPIRED|STATUS_TERMINATED|STATUS_FAILED|STATUS_COMPLETED`
(American spelling `canceled`). `failed` is an enrolment whose card was declined on the card
page; `expired` one nobody completed within 30 minutes of creation; `completed` is for
installments only - every instalment collected (a subscription ends as `canceled` or
`terminated`). `BoostRecurring::isTerminalStatus()` is true for `canceled`, `expired`,
`terminated`, `failed` and `completed`; `getStatusText()` labels each. Charge statuses use the
transaction codes in `Fpx::STATUS_*`.

### Recurring webhook

There is one: the **charge callback**, `record_type` `recurring_charge`, form-encoded on the
`callback_url` given at enrolment (or on the on-demand payment), signed with your API secret key
(ksort, `implode('|')`, HMAC-SHA256). It is sent for **every** charge — subscription,
installment, on-demand, the first charge of an enrolment included — on **every change of its
status**: pending (`1`), then successful (`3`) or failed (`2`). The first charge callback is the
activation signal; it can land before or after the buyer reaches your `return_url`. A declined
first charge arrives as a failed charge callback and the agreement then reads `failed`. There is
no agreement lifecycle webhook: a pause, a cancel, an `expired` agreement are read with
`getSubscription()` / `getInstallment()`.

Signed fields: the 13 transaction-callback fields (`record_type`, `transaction_id`,
`exchange_reference_number`, `exchange_transaction_id`, `order_number`, `currency`, `amount`,
`payer_name`, `payer_email`, `payer_bank_name`, `status`, `status_description`, `datetime`) plus
`charge_source`, `subscription_id`, `installment_id`, `customer_id` and the card the charge ran
on: `card_brand`, `card_country`, `card_last4`, `card_exp_month`, `card_exp_year` (never a token;
absent when the provider named no card). `payer_telephone_number` and `payment_gateway_id` are
sent but not signed. Form encoding drops null fields while the checksum signs them as `''`; the
verifier rebuilds the fixed field list, so pass the request body as you received it.

```php
$data = $request->all();

$valid = $bayarcash->verifyRecurringChargeCallbackData($data, API_SECRET_KEY);
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

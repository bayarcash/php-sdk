# Bayarcash Payment Gateway PHP SDK

## Introduction

The [Bayarcash](https://bayarcash.com/) SDK provides an expressive interface for interacting with Bayarcash's Payment Gateway API. This SDK supports both API v2 and v3, with enhanced features available in v3.

## Official Documentation

### Installation

To install the SDK in your project you need to require the package via composer:

```bash
composer require webimpian/bayarcash-php-sdk
```

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
> Creating a customer, a plan, a saved card or an on-demand charge sends an `Idempotency-Key` (the
> API requires it on the customer, plan and charge POSTs). The SDK generates a UUID v4 when you pass
> none; pass your own (e.g. from your order id) so a retry returns the original response instead of
> creating a second record or charging twice. Creating a subscription or an installment takes no key:
> every create is a new agreement, and it only bills once the payer authorizes it on the card page.

```php
use Webimpian\BayarcashSdk\BoostRecurring;
use Webimpian\BayarcashSdk\Fpx;

$bayarcash->setApiVersion('v3');

// Customers (get-or-create by email)
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
header('Location: ' . $saveCard->url);
// Later: $bayarcash->getPaymentIntent($saveCard->id)->status is 'authorized' (validate_only) or 'paid' (capture)

// Plans — interval is day|week|month|year
$plan = $bayarcash->createPlan([
    'name' => 'Pro', 'description' => 'Pro plan',
    'price' => 49.90, 'currency' => 'MYR', 'interval' => BoostRecurring::INTERVAL_MONTHLY, // 'month'
]);
$plan = $bayarcash->updatePlan('rpln_xxx', ['price' => 59.90]);

// Subscriptions — created at the provider, pending; redirect the buyer to ->redirectUrl
$subscription = $bayarcash->createSubscription([
    'portal_key'  => 'your-portal-key',   // required; the portal must have the card channel
    'customer_id' => 'rcus_xxx',          // or inline: 'customer' => ['full_name','email','phone']
    'plan_id'     => 'rpln_xxx',
    'start_date'  => '2026-11-01T09:00:00+08:00', // ISO-8601 timestamp, an hour or more ahead
    'callback_url'=> 'https://merchant/webhook',
    'return_url'  => 'https://merchant/return',
]);
header('Location: ' . $subscription->redirectUrl); // the provider's card page, lives 20 minutes

$subscription = $bayarcash->getSubscription('rsub_xxx'); // ->plan holds the plan it bills on
$bayarcash->pauseSubscription('rsub_xxx');
$bayarcash->resumeSubscription('rsub_xxx');
$bayarcash->cancelSubscription('rsub_xxx');

// Installments — the same: pending, with the card page
$installment = $bayarcash->createInstallment([
    'portal_key'        => 'your-portal-key',
    'customer_id'       => 'rcus_xxx',
    'total_amount'      => 1200.00,
    'interval'          => BoostRecurring::INTERVAL_MONTHLY,
    'installment_count' => 12,
    'description'       => 'Laptop, 12 payments', // required, up to 500 characters
    'start_date'        => '2026-11-01T09:00:00+08:00',
]);
header('Location: ' . $installment->redirectUrl);

// On-demand charge on a saved card — synchronous, the answer is the charge (no return_url)
$charge = $bayarcash->createOnDemandPayment([
    'portal_key'  => 'your-portal-key',
    'customer_id' => 'rcus_xxx',
    'amount'      => 25.00,
    'description' => 'Wallet top up', // letters, digits, spaces, full stops, hyphens
], 'invoice-1042'); // Idempotency-Key: a retry after a timeout returns the same charge
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
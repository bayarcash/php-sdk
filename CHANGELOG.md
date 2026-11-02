# Changelog

All notable changes to `bayarcash/php-sdk` will be documented in this file.

## Unreleased
### Added
- Boost recurring payment support (v3 only):
  - Customers: `createCustomer`, `getCustomers`, `getCustomer`, `saveCard`
  - Plans: `createPlan`, `getPlans`, `getPlan`, `updatePlan`
  - Subscriptions: `createSubscription`, `getSubscriptions`, `getSubscription`, `updateSubscription`, `getSubscriptionPayments`, plus `pauseSubscription`/`resumeSubscription`/`cancelSubscription`/`terminateSubscription`
  - Installments: `createInstallment`, `getInstallments`, `getInstallment`, `updateInstallment`, `getInstallmentPayments`, plus `pauseInstallment`/`resumeInstallment`/`cancelInstallment`/`terminateInstallment`
  - On-demand payments: `createOnDemandPayment`, `getPayment`
  - `BoostRecurring` status/type/interval constants and label helpers
- HTTP layer: per-request headers (`Idempotency-Key`) on every verb, and JSON request bodies.
- Recurring test suite (`tests/Recurring`, PHPUnit + Guzzle MockHandler).
- `getCustomerPayments($customerId, $page, $perPage)` — every charge for a customer, including on-demand charges.
- `verifyRecurringChargeCallbackData()` for the recurring charge callback — the only recurring webhook, sent for
  every charge on every status change; it signs the card the charge ran on (`card_brand`, `card_country`,
  `card_last4`, `card_exp_month`, `card_exp_year`).
- `verifyRecurringReturnUrlData()` for the signed landing on the recurring `return_url` (twelve fields + datetime + checksum).
- `SubscriptionResource` / `InstallmentResource`: `redirectUrl` (the provider's card page, on the create answer only)
  and `card['country']`. `BoostRecurring::STATUS_EXPIRED` (nobody completed the card page), terminal and labelled.
- `TransactionResource`: `recurringCustomerId`, `subscription`, `installment`, and the fields the API already
  answered undeclared (a dynamic property, deprecated on PHP 8.2+): `platformFees`, `nettAmount`, `transactionType`,
  `applicationReason`, `paymentIntentId`. `SubscriptionResource`: `plan`.
- `saveCard()` takes an optional Idempotency-Key.
- `BoostRecurring::STATUS_FAILED` and `STATUS_COMPLETED` (installments only); both count in `isTerminalStatus()`
  and have a `getStatusText()` label.

### Changed
- **Breaking:** `createSubscription()` / `createInstallment()` create the agreement at the provider and return the
  `pending` `SubscriptionResource` / `InstallmentResource` with `redirectUrl` — redirect the buyer there — instead
  of a recurring intent with `url`. `createOnDemandPayment()` charges the saved card synchronously and returns the
  charge as a `TransactionResource` (no `return_url`); a customer with no saved card is a 422 `customer_no_saved_card`.
- **Breaking:** `saveCard($customerId, array $data, $idempotencyKey = null)` creates a Credit Card payment intent that
  also saves the card: `$data` is the `createPaymentIntent()` body less the payer plus `tokenize` (`capture`, minimum
  2.00, or `validate_only`, minimum 1.00). The payer's name, email and phone come off the customer record. It returns a `PaymentIntentResource` — redirect to `->url` — instead of
  `['payment_url' => ...]`. `PaymentIntentResource` gains `tokenize`; a `validate_only` intent reads `authorized`.
- `createCustomer`, `createPlan`, `createOnDemandPayment` and `saveCard` now always send an `Idempotency-Key`,
  generating a UUID v4 when none is passed (the API requires it on the customer, plan and on-demand POSTs).
- **Breaking:** `createSubscription(array $data)` / `createInstallment(array $data)` take no Idempotency-Key and
  send none: every create is a new agreement, billed only once the payer authorizes it on the card page. A key
  passed as a second argument is ignored.
- `BoostRecurring::INTERVAL_*` are now `day`/`week`/`month`/`year` (the API rejects ISO durations).
- **Breaking:** `getPayment()` returns a `TransactionResource`; `getSubscriptionPayments()` / `getInstallmentPayments()`
  return a paginated `['data' => TransactionResource[], 'meta', 'links']` and take `$page` / `$perPage`.
- **Breaking:** `getSubscription()` / `getInstallment()` no longer carry `->charges` (the API stopped embedding them).

### Removed
- `RecurringIntentResource`, `getRecurringIntent()` and `verifyRecurringLifecycleCallbackData()` — there are no
  recurring intents and no lifecycle webhook; an agreement's state is read with `getSubscription()` / `getInstallment()`.
- `PaymentResource`, `ChargeResource` and the `BoostRecurring::PAYMENT_*` constants — charges are transaction records,
  whose status is the `Fpx::STATUS_*` code.

## 3.2.1 - 2026-11-01

### Added
- `Fpx::STATUS_ABANDONED` and `Dobw::STATUS_ABANDONED` (5), and `FpxDirectDebit::STATUS_EXPIRED` (9) and `FpxDirectDebit::STATUS_INACTIVE` (10). The gateway sends these codes, and `getStatusText()` returned `UNKNOWN STATUS` for them.

## 3.2.0 - 2026-10-29

### Added
- Support for Guzzle 8. The SDK now works with Guzzle 7 and 8.

### Fixed
- `setTimeout()` is now applied to every request (default 30 seconds), including through a client passed to `setToken()` or `useSandbox()`, and the default client fails a connection after 10 seconds. Previously requests had no timeout.
- `getPortals()` returns portals from every page, not just the first 15 (v3).
- `getChannels()` finds any portal on v3, using the API's `portal_key` filter in a single request.

## 3.1.0 - 2026-08-20

New v3 endpoints, DuitNow QR support, and payment-intent enhancements.

### Added

**Payment intents**
- `createDuitNowQrPaymentIntent()` — create an intent and generate its DuitNow QR in one request.
- Optional `Idempotency-Key` argument on `createPaymentIntent()` for safe retries (v3).
- Payer identity verification (v3): `verify_identity` request fields, plus `payerIdentityVerified` and `fpxExtraInfo` on the transaction.

**DuitNow QR**
- `regenerateDuitNowQr()` — issue a fresh QR for an existing intent.
- `getDuitNowQrStatus()` — poll a QR transaction's payment status.
- `DuitNowQrResource`, also exposed as the payment intent's `duitnowQr` property.

**FPX Direct Debit**
- `getAllFpxDirectDebits()` and `getAllFpxDirectDebitTransactions()` — list mandates and their transactions.
- `activateFpxDirectDebit()` and `deactivateFpxDirectDebit()`.

**Portals & banks**
- `getPortal()`, `duitNowDobwBanksList()`, and `getServerStatus()`.

**Payment channels**
- New constants: `SHOPBACK_BNPL` (20) and `FPX_B2B` (23).

## 3.0.0 - 2026-07-22
### Changed
- **Breaking:** Renamed the root namespace from `Webimpian\BayarcashSdk` to `Bayarcash`. Update your imports — e.g. `use Bayarcash\Bayarcash;`, `use Bayarcash\Fpx;`, `use Bayarcash\FpxDirectDebit;`. The package name (`bayarcash/php-sdk`) and the public API are otherwise unchanged.

## 2.3.0 - 2026-07-22
### Added
- Support for PHP 8.3, 8.4, and 8.5. The SDK now runs on PHP 7.4 through 8.5.
- `getApiVersion()`, a companion to the existing `setApiVersion()`, to read back the API version currently in use.
- `getFpxDirectDebitTransaction()` to retrieve an FPX Direct Debit transaction. The previous, misspelled `getfpxDirectDebitransaction()` still works but is now deprecated.
- A full integration guide in the README: setup, an end-to-end payment example, request field references, callback handling, Direct Debit, Manual Bank Transfer, error handling, and status constants.

### Fixed
- FPX Direct Debit authorization callbacks are now verified correctly; valid callbacks could previously be rejected.
- Payment, transaction, portal, and Direct Debit response objects no longer error when the API omits an optional field — missing values are returned as `null`.
- Requests that fail with a `400` response now throw a `FailedActionException` with the error message, instead of a fatal error.
- Callback verification no longer produces PHP warnings when a callback payload is incomplete.

### Security
- Strengthened payment callback verification against timing attacks.

## 2.2.0 - 2026-07-21
### Changed
- Renamed the package to `bayarcash/php-sdk` (previously `webimpian/bayarcash-php-sdk`). Existing installations using the old name continue to work.

## 2.1.2 - 2026-02-03
### Changed
- Refactored checksum generation:
  - `payment_channel` can now be omitted, null, an integer, or an array of integers.
  - Payment channels are normalized to a comma-separated string before checksum calculation.
  - Payload sorting and HMAC-SHA256 hash generation remain the same.

## 2.1.1 - 2026-01-28
### Added
- Add toArray() method to convert Resource instances to array format with support for nested Resource objects and arrays of Resources.

## 2.1.0 - 2026-01-26
### Added
- Added new Payment Intent features:
  - `cancelPaymentIntent` method to cancel payment intent

## 2.0.6 - 2025-11-10
### Added
- Add TOUCH_N_GO, BOOST_WALLET, GRABPAY, GRABPL, and SHOPEE_PAY payment channel id.

## 2.0.5 - 2025-07-29
### Added
- Add CREDIT_CARD, ALIPAY,PROMPTPAY and WECHATPAY payment channel id.

## 2.0.4 - 2025-04-29
### Added
- Added helper to submit manual transfers to Bayarcash.
- Added helper to update status manual transfer

## 2.0.3 - 2025-02-07
- Fix small bug

## 2.0.2 - 2024-01-18
- Support for `guzzlehttp/guzzle ^7.0`.

## 2.0.0 - 2024-01-17

### Added
- Added support for API version v3 with `setApiVersion` method
- Added new Portal management features:
  - `getPortals` method to retrieve all available portals
  - `getChannels` method to get payment channels for specific portal
- Added new Payment Intent features:
  - `getPaymentIntent` method to retrieve payment intent details (v3 only)
- Added new Transaction management features (v3 only):
  - `getAllTransactions` method with comprehensive filtering options
  - `getTransactionByOrderNumber` method
  - `getTransactionsByPayerEmail` method
  - `getTransactionsByStatus` method
  - `getTransactionsByPaymentChannel` method
  - `getTransactionByReferenceNumber` method
- Added NETS payment channel support

### Changed
- Enhanced API support to handle both v2 and v3 endpoints
- Improved error handling for API version-specific features
- Updated base URI handling for different API versions

## 1.2.2 - 2024-09-25

- Fixed code for php7.4

## 1.2.1 - 2024-09-25

- Add SPayLater, Boost PayFlex, QRIS Indonesia Online Banking and QRIS Indonesia e-Wallet payment channel id.

## 1.2.0 - 2024-09-25

- Add DUITNOW_QR, SPAYLATER and BOOST_PAYFLEX payment channel id.

## 1.1.0 - 2024-09-20

- Fix bug for PHP 7.4

## 1.0.0 - 2024-07-31

- Initial release.

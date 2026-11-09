# Changelog

All notable changes to `bayarcash/php-sdk` will be documented in this file.

## Unreleased
### Added
- Recurring payment support (v3 only):
  - Customers: `createCustomer`, `getCustomers`, `getCustomer`, `saveCard`
  - Plans: `createPlan`, `getPlans`, `getPlan`, `updatePlan`
  - Subscriptions: `createSubscription`, `getSubscriptions`, `getSubscription`, `updateSubscription`, `getSubscriptionPayments`, plus `pauseSubscription`/`resumeSubscription`/`cancelSubscription`/`terminateSubscription`
  - Installments: `createInstallment`, `getInstallments`, `getInstallment`, `updateInstallment`, `getInstallmentPayments`, plus `pauseInstallment`/`resumeInstallment`/`cancelInstallment`/`terminateInstallment`
  - On-demand payments: `createOnDemandPayment`, `getPayment`
  - `Recurring` status/type/interval constants and label helpers
- Recurring test suite (`tests/Recurring`, PHPUnit + Guzzle MockHandler).
- `getAllTransactions()` accepts `recurring_type` (`on_demand`, `subscription` or `installment`); with `order_number`
  it is the lookup after an on-demand 202.
- Every recurring call waits 90 seconds, or `setTimeout()` when larger; the one-off endpoints keep `setTimeout()`
  (30 by default). Your own PHP and proxy limits must allow 90 seconds. A create can still take longer (an on-demand
  charge with `customer_id` about 90 seconds; a create with an inline `customer` up to 227 seconds on-demand, 182
  seconds for a subscription or installment). A timeout or a transport error on a create is the same as a 202: the
  record may already exist and an on-demand card may already have been charged, so read the outcome (the callback,
  `getSubscription()` / `getInstallment()` / `getSubscriptions(['reference' => ...])`, or the on-demand lookup above)
  and never charge again under a new reference until it says so.
- `getCustomerPayments($customerId, $page, $perPage)` — every charge for a customer, including on-demand charges.
- `verifyRecurringChargeCallbackData()` for the recurring charge callback — the only recurring webhook, sent for
  every charge on every status change; it signs the card the charge ran on (`card_brand`, `card_country`,
  `card_last4`, `card_exp_month`, `card_exp_year`).
- `verifyRecurringReturnUrlData()` for the signed landing on the recurring `return_url` (twelve fields + datetime + checksum).
- `SubscriptionResource` / `InstallmentResource`: `redirectUrl` (the provider's card page, on every answer while the
  agreement is `pending`; null on a create's 202 until the provider has answered; the page lives 20 minutes from
  `createdAt`) and `card['country']`. `Recurring::STATUS_EXPIRED` (nobody completed the card page), terminal and labelled.
- `TransactionResource`: `recurringCustomerId`, `subscription`, `installment`, and the fields the API already
  answered undeclared (a dynamic property, deprecated on PHP 8.2+): `platformFees`, `nettAmount`, `transactionType`,
  `applicationReason`, `paymentIntentId`. `SubscriptionResource`: `plan`.
- `PlanResource::$code` — the merchant's own plan code. `getPlans(['code' => ...])` and
  `getSubscriptions(['reference' => ...])` / `getInstallments(['reference' => ...])` find a record by the merchant's
  id (list filters are passed through as given).
- `Recurring::STATUS_FAILED` and `STATUS_COMPLETED` (installments only); both count in `isTerminalStatus()`
  and have a `getStatusText()` label.
- `Fpx::STATUS_AUTHORIZED` (6), `Fpx::STATUS_VOID` (7) and `Fpx::STATUS_REFUND` (8) with `getStatusText()` labels; a `validate_only` save-card callback succeeds with 6.
- `Bayarcash\Exceptions\ApiErrorException` (extends `\Exception`) for every error status without its own exception (401, 403, 409, 5xx, a 504 proxy page): the message is still the raw body, `getCode()` / `status()` is the HTTP status, plus `body()`, `errors()` and `errorCode()` (`error.code`, e.g. `save_card_refused`, `upstream_conflict`).
- `createSaveCardChecksumValue($secretKey, $data, $customerFullName, $customerEmail)` — the optional `saveCard()` checksum, signed with the customer's name and email as stored and `payment_channel` 12 when left out.

### Changed
- **Breaking:** `createSubscription()` / `createInstallment()` create the agreement at the provider and return the
  `pending` `SubscriptionResource` / `InstallmentResource` with `redirectUrl` — redirect the buyer there — instead
  of a recurring intent with `url`. `createOnDemandPayment()` charges the saved card synchronously and returns the
  charge as a `TransactionResource` (no `return_url`); a customer with no saved card is a 422 `customer_no_saved_card`.
- **Breaking:** `saveCard($customerId, array $data)` creates a Credit Card payment intent that
  also saves the card: `$data` is the `createPaymentIntent()` body less the payer plus `tokenize` (`capture`, minimum
  2.00, or `validate_only`, minimum 1.00). The payer's name, email and phone come off the customer record. It returns a `PaymentIntentResource` — redirect to `->url` — instead of
  `['payment_url' => ...]`. `url` is the provider's card page (30 minutes, used once; the checkout does not open a
  save-card intent); a card page that cannot be started is a 403 `save_card_refused` or 502 `save_card_failed`. `PaymentIntentResource` gains `tokenize`; a `validate_only` intent reads `authorized`.
- **Breaking:** `createCustomer(array $data)`, `createPlan(array $data)` and `saveCard($customerId, array $data)` take
  no Idempotency-Key and send none (the protected `idempotencyHeaders()` / `generateIdempotencyKey()` helpers are
  gone; a key passed as an extra positional argument is ignored, but a named `idempotencyKey:` argument now
  throws PHP's `Error` "Unknown named parameter $idempotencyKey" - drop it). A known customer email is a 422
  `customer_email_exists`; when `getCustomers(['search' => $email])` finds none, the email is held at the provider
  outside your records - contact support.
  `createPlan()` requires the merchant's own `code` (up to 36 of `A-Z a-z 0-9 _ -`, unique per merchant forever,
  case-insensitive: `PLAN-1` and `plan-1` are the same code);
  a code on file is a 422 `plan_code_already_used`. Every `saveCard()` call makes a new card link: with `capture`
  two paid links are two sales, so after a timeout look the intent up or send the payer only the newest link.
  `createPaymentIntent()` keeps its optional key.
- `createSubscription()`, `createInstallment()` and `createOnDemandPayment()` require a merchant `reference` in
  `$data` (up to 36 of `A-Z a-z 0-9 _ -`, unique per merchant per type forever, case-insensitive: `INV-1` and `inv-1`
  are the same reference; the API validates it). A reference
  already on file — any body, any state — is a 422 `reference_already_used` ("The reference X already exists.",
  `ValidationException`); the record on file is never answered back. A first call not settled in time answers 202:
  an agreement as itself, `pending`, without `redirectUrl` (read it with `getSubscription()` / `getInstallment()`);
  an on-demand 202 is no charge record yet, a `TransactionResource` holding only `reference` and `status` the string
  `pending` (`id` null). The card may already have been charged: wait for the charge callback, or read the charge
  with `getAllTransactions(['order_number' => $reference, 'recurring_type' => 'on_demand'])` (`data` holds
  `TransactionResource`) once booked, BEFORE ever charging again under a new reference — otherwise the customer can
  be charged twice. Right after the 202 an empty
  answer is not yet proof (Bayarcash's background check first asks 15 minutes after the charge was recorded, then
  every 5 minutes), so it is usually booked within about 20 minutes; it can take longer while the exchange is unreachable
  or has not settled the charge. Rely on the callback: charge again under a new reference only once the callback says
  the charge failed, or that lookup still finds nothing well after that. A 401, 403, 429, or a 502 `upstream_auth`, on a
  create is not a 202: nothing was created and no card was charged, so send it again under a new reference (after a
  429, wait a short while first; `->rateLimitResetsAt` is null unless the API sends `X-RateLimit-Reset`). `SubscriptionResource` /
  `InstallmentResource` / `TransactionResource` gain `reference`; a booked charge answers it as `orderNumber`.
- **Breaking:** `createOnDemandPayment(array $data)` no longer takes `$idempotencyKey` and sends no `Idempotency-Key`:
  the `reference` dedupes the charge. A key passed as a second positional argument is ignored; a named
  `idempotencyKey:` argument now throws PHP's `Error` "Unknown named parameter $idempotencyKey".
- **Breaking:** `createSubscription(array $data)` / `createInstallment(array $data)` take no Idempotency-Key and
  send none: every create is a new agreement, billed only once the payer authorizes it on the card page. A key
  passed as a second positional argument is ignored; a named `idempotencyKey:` argument now throws (as above).
- `createSubscription()`: a customer already holding a subscription to the plan that has not ended (`pending`,
  including one still being created, `active` or `paused`) is a 422 `customer_already_subscribed` ("The customer is
  already subscribed to this plan.", `ValidationException`), as the provider itself refuses it: cancel the existing
  one first (`cancelSubscription()`); one still being created must settle first, or wait until it reads `expired`.
- `Recurring::INTERVAL_*` are now `day`/`week`/`month`/`year` (the API rejects ISO durations).
- `saveCard()` waits 120 seconds, or `setTimeout()` when larger; the other recurring calls stay at 90.
- **Breaking:** `getPayment()` returns a `TransactionResource`; `getSubscriptionPayments()` / `getInstallmentPayments()`
  return a paginated `['data' => TransactionResource[], 'meta', 'links']` and take `$page` / `$perPage`.
- **Breaking:** `getSubscription()` / `getInstallment()` no longer carry `->charges` (the API stopped embedding them).

### Removed
- `RecurringIntentResource`, `getRecurringIntent()` and `verifyRecurringLifecycleCallbackData()` — there are no
  recurring intents and no lifecycle webhook; an agreement's state is read with `getSubscription()` / `getInstallment()`.
- `PaymentResource`, `ChargeResource` and the `Recurring::PAYMENT_*` constants — charges are transaction records,
  whose status is the `Fpx::STATUS_*` code.

### Fixed
- `getTransactionByOrderNumber()` and `getTransactionsByStatus()` URL-encode their value, so an order number or
  status containing `&`, `#`, `+` or a space is sent as given.

## 3.2.2 - 2026-11-02

### Fixed
- `verifyDirectDebitTransactionCallbackData()` now includes the signed `payment_gateway_id` field, so real deduction callbacks pass verification.

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

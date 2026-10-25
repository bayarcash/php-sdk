# Changelog

All notable changes to will be documented in this file.

## Unreleased
### Added
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

## 2.2.0 - 2026-06-22
### Added
- Boost recurring payment support (v3 only):
  - Customers: `createCustomer`, `getCustomers`, `getCustomer`, `createCustomerPaymentMethod`
  - Plans: `createPlan`, `getPlans`, `getPlan`, `updatePlan`
  - Subscriptions: `createSubscription`, `getSubscriptions`, `getSubscription`, `updateSubscription`, `getSubscriptionPayments`, plus `pauseSubscription`/`resumeSubscription`/`cancelSubscription`/`terminateSubscription`
  - Installments: `createInstallment`, `getInstallments`, `getInstallment`, `updateInstallment`, `getInstallmentPayments`, plus `pauseInstallment`/`resumeInstallment`/`cancelInstallment`/`terminateInstallment`
  - On-demand payments: `createOnDemandPayment`, `getPayment`
  - `BoostRecurring` status/type/interval constants and label helpers
- HTTP layer: `PATCH` verb, per-request headers (`Idempotency-Key`), and JSON request bodies.
- First automated test suite (PHPUnit + Guzzle MockHandler).

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

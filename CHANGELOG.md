# Changelog

All notable changes to will be documented in this file.

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

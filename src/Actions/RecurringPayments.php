<?php

namespace Webimpian\BayarcashSdk\Actions;

use Webimpian\BayarcashSdk\Resources\CustomerResource;
use Webimpian\BayarcashSdk\Resources\InstallmentResource;
use Webimpian\BayarcashSdk\Resources\PlanResource;
use Webimpian\BayarcashSdk\Resources\RecurringIntentResource;
use Webimpian\BayarcashSdk\Resources\SubscriptionResource;
use Webimpian\BayarcashSdk\Resources\TransactionResource;

/**
 * Boost recurring-payment endpoints (v3 only).
 *
 * Customers and Plans return their resource directly on create. Subscriptions,
 * Installments and on-demand Payments return a RecurringIntentResource carrying
 * an authorization `url` — redirect the buyer there to authorize their card,
 * then fetch the eventual resource by id.
 *
 * The three money requests — createSubscription, createInstallment and
 * createOnDemandPayment — REQUIRE `portal_key`: the merchant's portal key, the
 * same value `createPaymentIntent` takes, and the portal must have the card
 * channel enabled. Customers and Plans are portal-agnostic records, so they do
 * not take it, and neither do GET and PATCH calls.
 *
 * Every POST that mints something (customer, plan, subscription, installment,
 * on-demand payment) REQUIRES an `Idempotency-Key` header — the API answers 422
 * without one. The SDK generates a UUID v4 when the caller passes none; pass
 * your own (e.g. derived from your order id) to make a retry return the
 * original response instead of minting a second record or authorization link.
 *
 * Charges are transaction records (TransactionResource, the same shape as
 * getTransaction()): `id` is the trx_ id, `status` is the Fpx::STATUS_* code
 * (3 successful, 2 failed, 1 pending) and `exchangeTransactionId` is the
 * provider charge id.
 */
trait RecurringPayments
{
    // ---------------------------------------------------------------------
    // Customers
    // ---------------------------------------------------------------------

    public function createCustomer(array $data, ?string $idempotencyKey = null)
    {
        $this->ensureV3('createCustomer');

        return new CustomerResource(
            $this->post('customers', ['json' => $data], $this->idempotencyHeaders($idempotencyKey)),
            $this
        );
    }

    public function getCustomers(array $filters = [])
    {
        $this->ensureV3('getCustomers');

        return $this->paginate($this->get('customers' . $this->query($filters)), CustomerResource::class);
    }

    public function getCustomer(string $customerId)
    {
        $this->ensureV3('getCustomer');

        return new CustomerResource($this->get('customers/' . $customerId), $this);
    }

    /**
     * Hosted page where the customer saves a card for later on-demand charges.
     *
     * The key is optional on the API, but with one a retry replays the first
     * hosted `payment_url` instead of opening a second card authorization.
     */
    public function saveCard(string $customerId, ?string $idempotencyKey = null)
    {
        $this->ensureV3('saveCard');

        return $this->post(
            'customers/' . $customerId . '/save-card',
            [],
            $this->idempotencyHeaders($idempotencyKey)
        );
    }

    /**
     * Every charge billed to the customer, newest first — across all their
     * agreements plus the on-demand charges (the only list that has those).
     *
     * @return array{data: TransactionResource[], meta: array, links: array}
     */
    public function getCustomerPayments(string $customerId, int $page = 1, int $perPage = 15)
    {
        $this->ensureV3('getCustomerPayments');

        return $this->chargePage('customers/' . $customerId . '/payments', $page, $perPage);
    }

    // ---------------------------------------------------------------------
    // Plans
    // ---------------------------------------------------------------------

    public function createPlan(array $data, ?string $idempotencyKey = null)
    {
        $this->ensureV3('createPlan');

        return new PlanResource(
            $this->post('plans', ['json' => $data], $this->idempotencyHeaders($idempotencyKey)),
            $this
        );
    }

    public function getPlans(array $filters = [])
    {
        $this->ensureV3('getPlans');

        return $this->paginate($this->get('plans' . $this->query($filters)), PlanResource::class);
    }

    public function getPlan(string $planId)
    {
        $this->ensureV3('getPlan');

        return new PlanResource($this->get('plans/' . $planId), $this);
    }

    public function updatePlan(string $planId, array $data)
    {
        $this->ensureV3('updatePlan');

        return new PlanResource($this->patch('plans/' . $planId, ['json' => $data]), $this);
    }

    // ---------------------------------------------------------------------
    // Subscriptions
    // ---------------------------------------------------------------------

    /**
     * @param  array  $data  Subscription payload. `portal_key` is required, and
     *                       `start_date` must be a full ISO-8601 timestamp
     *                       (e.g. "2026-07-01T09:00:00+08:00") in the future — a
     *                       minute ahead is enough; a bare date is rejected.
     */
    public function createSubscription(array $data, ?string $idempotencyKey = null)
    {
        $this->ensureV3('createSubscription');
        $this->requirePortalKey($data);

        return new RecurringIntentResource(
            $this->post('subscriptions', ['json' => $data], $this->idempotencyHeaders($idempotencyKey)),
            $this
        );
    }

    public function getSubscriptions(array $filters = [])
    {
        $this->ensureV3('getSubscriptions');

        return $this->paginate($this->get('subscriptions' . $this->query($filters)), SubscriptionResource::class);
    }

    public function getSubscription(string $subscriptionId)
    {
        $this->ensureV3('getSubscription');

        return new SubscriptionResource($this->get('subscriptions/' . $subscriptionId), $this);
    }

    public function updateSubscription(string $subscriptionId, array $data)
    {
        $this->ensureV3('updateSubscription');

        return new SubscriptionResource($this->patch('subscriptions/' . $subscriptionId, ['json' => $data]), $this);
    }

    public function pauseSubscription(string $subscriptionId)
    {
        return $this->updateSubscription($subscriptionId, ['status' => 'paused']);
    }

    public function resumeSubscription(string $subscriptionId)
    {
        return $this->updateSubscription($subscriptionId, ['status' => 'active']);
    }

    public function cancelSubscription(string $subscriptionId)
    {
        return $this->updateSubscription($subscriptionId, ['status' => 'canceled']);
    }

    public function terminateSubscription(string $subscriptionId)
    {
        return $this->updateSubscription($subscriptionId, ['status' => 'terminated']);
    }

    /** @return array{data: TransactionResource[], meta: array, links: array} */
    public function getSubscriptionPayments(string $subscriptionId, int $page = 1, int $perPage = 15)
    {
        $this->ensureV3('getSubscriptionPayments');

        return $this->chargePage('subscriptions/' . $subscriptionId . '/payments', $page, $perPage);
    }

    // ---------------------------------------------------------------------
    // Installments
    // ---------------------------------------------------------------------

    /**
     * @param  array  $data  Installment payload. `portal_key` is required,
     *                       `description` (what the customer is paying off, up to
     *                       500 characters) is required, `interval` is one of
     *                       day|week|month|year (BoostRecurring::INTERVAL_*), and
     *                       `start_date` must be a full ISO-8601 timestamp
     *                       (e.g. "2026-07-01T09:00:00+08:00") in the future — a
     *                       bare date is rejected.
     */
    public function createInstallment(array $data, ?string $idempotencyKey = null)
    {
        $this->ensureV3('createInstallment');
        $this->requirePortalKey($data);

        return new RecurringIntentResource(
            $this->post('installments', ['json' => $data], $this->idempotencyHeaders($idempotencyKey)),
            $this
        );
    }

    public function getInstallments(array $filters = [])
    {
        $this->ensureV3('getInstallments');

        return $this->paginate($this->get('installments' . $this->query($filters)), InstallmentResource::class);
    }

    public function getInstallment(string $installmentId)
    {
        $this->ensureV3('getInstallment');

        return new InstallmentResource($this->get('installments/' . $installmentId), $this);
    }

    public function updateInstallment(string $installmentId, array $data)
    {
        $this->ensureV3('updateInstallment');

        return new InstallmentResource($this->patch('installments/' . $installmentId, ['json' => $data]), $this);
    }

    public function pauseInstallment(string $installmentId)
    {
        return $this->updateInstallment($installmentId, ['status' => 'paused']);
    }

    public function resumeInstallment(string $installmentId)
    {
        return $this->updateInstallment($installmentId, ['status' => 'active']);
    }

    public function cancelInstallment(string $installmentId)
    {
        return $this->updateInstallment($installmentId, ['status' => 'canceled']);
    }

    public function terminateInstallment(string $installmentId)
    {
        return $this->updateInstallment($installmentId, ['status' => 'terminated']);
    }

    /** @return array{data: TransactionResource[], meta: array, links: array} */
    public function getInstallmentPayments(string $installmentId, int $page = 1, int $perPage = 15)
    {
        $this->ensureV3('getInstallmentPayments');

        return $this->chargePage('installments/' . $installmentId . '/payments', $page, $perPage);
    }

    // ---------------------------------------------------------------------
    // On-demand payments
    // ---------------------------------------------------------------------

    /**
     * Create a one-off on-demand charge against a customer's card-on-file.
     *
     * @param  array  $data  Payment payload. `portal_key` is required.
     */
    public function createOnDemandPayment(array $data, ?string $idempotencyKey = null)
    {
        $this->ensureV3('createOnDemandPayment');
        $this->requirePortalKey($data);

        return new RecurringIntentResource(
            $this->post('payments', ['json' => $data], $this->idempotencyHeaders($idempotencyKey)),
            $this
        );
    }

    /**
     * One charge, as a transaction record. Accepts either the provider charge id
     * (`exchangeTransactionId` of a list row or the charge callback) or the trx_ id.
     */
    public function getPayment(string $paymentId)
    {
        $this->ensureV3('getPayment');

        return new TransactionResource($this->get('payments/' . $paymentId), $this);
    }

    /**
     * Resolve a recurring intent (rint_...) to its current status/outcome.
     *
     * When a payer is redirected back after authorizing a subscription/installment or
     * an on-demand charge, the only id you hold is the intent id (returned by
     * createSubscription/createInstallment/createOnDemandPayment) — NOT a transaction
     * id. Call this to verify the outcome instead of trusting redirect query params.
     * The resource's `status` is one of: pending, authorizing, processing, completed,
     * failed, expired. A failed intent carries `failureReason`; a completed or failed
     * on-demand intent carries `transactionId` (the trx_ id of the charge, or null
     * when none was booked) — a failed charge sends no webhook, so this is the only
     * place to learn about it.
     */
    public function getRecurringIntent(string $intentId)
    {
        $this->ensureV3('getRecurringIntent');

        return new RecurringIntentResource($this->get('recurring-intents/' . $intentId), $this);
    }

    // ---------------------------------------------------------------------
    // Shared helpers
    // ---------------------------------------------------------------------

    /**
     * Guard a recurring method behind API v3.
     *
     * @throws \Exception
     */
    protected function ensureV3(string $method): void
    {
        if ($this->getApiVersion() !== 'v3') {
            throw new \Exception("The {$method} method is only available for API version v3.");
        }
    }

    /**
     * The three money requests must carry the merchant's portal key.
     *
     * @throws \InvalidArgumentException
     */
    protected function requirePortalKey(array $data): void
    {
        if (! isset($data['portal_key']) || $data['portal_key'] === '') {
            throw new \InvalidArgumentException("Required field 'portal_key' is missing");
        }
    }

    /**
     * The Idempotency-Key header, generating a UUID v4 when the caller gave none:
     * the API rejects a minting POST without one.
     */
    protected function idempotencyHeaders(?string $key): array
    {
        return ['Idempotency-Key' => $key ?: $this->generateIdempotencyKey()];
    }

    /** Generate an RFC-4122 v4 UUID for idempotency, without external deps. */
    protected function generateIdempotencyKey(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /** Build a query string from a filter array. */
    protected function query(array $filters): string
    {
        return empty($filters) ? '' : '?' . http_build_query($filters);
    }

    /** One page of a charge list; the API clamps per_page to 100. */
    protected function chargePage(string $uri, int $page, int $perPage): array
    {
        return $this->paginate(
            $this->get($uri . $this->query(['page' => $page, 'per_page' => $perPage])),
            TransactionResource::class
        );
    }

    /** Wrap a paginated API response into data/meta/links with transformed resources. */
    protected function paginate(array $response, string $class): array
    {
        return [
            'data' => $this->transformCollection($response['data'] ?? [], $class),
            'meta' => $response['meta'] ?? [],
            'links' => $response['links'] ?? [],
        ];
    }
}

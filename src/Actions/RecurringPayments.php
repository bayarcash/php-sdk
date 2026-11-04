<?php

namespace Bayarcash\Actions;

use Bayarcash\Resources\CustomerResource;
use Bayarcash\Resources\InstallmentResource;
use Bayarcash\Resources\PaymentIntentResource;
use Bayarcash\Resources\PlanResource;
use Bayarcash\Resources\SubscriptionResource;
use Bayarcash\Resources\TransactionResource;

/**
 * Boost recurring-payment endpoints (v3 only).
 *
 * Every create returns its resource directly. createSubscription() and
 * createInstallment() create the agreement at the provider, `pending`, and
 * return it with `redirectUrl` — the provider's card page: redirect the buyer
 * there; the agreement is `active` once they complete it and the first charge
 * is taken at that moment. createOnDemandPayment() charges the saved card
 * synchronously and returns the charge as a TransactionResource.
 *
 * The three money requests — createSubscription, createInstallment and
 * createOnDemandPayment — REQUIRE `portal_key`: the merchant's portal key, the
 * same value `createPaymentIntent` takes, and the portal must have the card
 * channel enabled. Customers and Plans are portal-agnostic records, so they do
 * not take it, and neither do GET and PATCH calls.
 *
 * The three money requests also REQUIRE `reference`: the merchant's own id for
 * the agreement or charge (up to 36 of A-Z a-z 0-9 _ -), unique per merchant
 * per type forever and case-insensitive (`INV-1` and `inv-1` are the same
 * reference). A reference already on file — any body, any state — is a
 * 422 `reference_already_used` ("The reference X already exists.",
 * ValidationException); the record on file is never answered back. So do not
 * repeat a create to learn its outcome. A first call the provider did not
 * settle in time answers 202: an agreement as itself, `pending`, without
 * `redirectUrl`; an on-demand charge with no charge record yet, only `reference` and
 * `status` the string `pending`. Read the outcome instead: getSubscription() /
 * getInstallment() by the 202's id, or getSubscriptions() / getInstallments()
 * filtered by `reference` when no answer arrived; an on-demand charge with
 * getTransactionByOrderNumber($reference) once it is booked (an array of
 * TransactionResource), or from the charge callback. After an on-demand 202 the
 * card may already have been charged: wait for the callback or read the charge
 * BEFORE ever charging again under a new reference, or the customer can be
 * charged twice. While an agreement reads `pending` it carries `redirectUrl`; the
 * card page lives 20 minutes from `createdAt`, after that only a new create
 * (with a new reference) helps. These three take no Idempotency-Key.
 *
 * Customers, plans and saveCard() take no Idempotency-Key either. A customer
 * email the merchant already holds is a 422 `customer_email_exists`. A plan
 * REQUIRES the merchant's own `code` (the same rule as `reference`, unique per
 * merchant forever, case-insensitive: `PLAN-1` and `plan-1` are the same code): a code on file is a 422 `plan_code_already_used`. Every
 * saveCard() call makes a new card link (see saveCard()).
 *
 * The only webhook is the recurring charge callback (every charge, every status
 * change; see CallbackVerifications::verifyRecurringChargeCallbackData()). An
 * agreement's own state is read with getSubscription() / getInstallment().
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

    /**
     * A customer email the merchant already holds is a 422 `customer_email_exists`
     * (ValidationException); look the customer up with
     * getCustomers(['search' => $email]) instead.
     */
    public function createCustomer(array $data)
    {
        $this->ensureV3('createCustomer');

        return new CustomerResource($this->post('customers', ['json' => $data]), $this);
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
     * Save a card for the customer: creates a Credit Card payment intent that
     * also vaults the payer's card against the customer, for later on-demand
     * charges. Redirect the payer to the returned intent's `url`, exactly as
     * for createPaymentIntent().
     *
     * `$data` is the createPaymentIntent() body less the payer (portal_key,
     * order_number, amount, optional checksum, ...) plus the required
     * `tokenize`, posted as given. The payer's name, email and phone come off
     * the customer record; any sent are ignored by the API.
     *   - `capture`       charges the amount and saves the card; minimum 2.00.
     *   - `validate_only` authorizes the amount and saves the card, no money
     *                     moves; minimum 1.00.
     * `payment_channel` may be left out (the API defaults it to Credit Card,
     * 12); any other channel is refused with 422.
     *
     * The card is saved once the payment succeeds (capture) or is authorized
     * (validate_only) — getPaymentIntent() then reads `paid` or `authorized`
     * and echoes `tokenize`.
     *
     * No Idempotency-Key: every call makes a new payment intent with its own
     * link. Creating a link charges nothing, but every link is payable: with
     * `capture` two paid links are two sales. After a timeout, look the intent
     * up with getPaymentIntent() if you have its id, or create a new one and send
     * the payer only the newest link.
     *
     * @return PaymentIntentResource
     */
    public function saveCard(string $customerId, array $data)
    {
        $this->ensureV3('saveCard');

        return new PaymentIntentResource(
            $this->post('customers/' . $customerId . '/save-card', ['json' => $data]),
            $this
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

    /**
     * @param  array  $data  Plan payload. `code` (the merchant's own plan code, up to
     *                       36 of A-Z a-z 0-9 _ -, unique per merchant forever,
     *                       case-insensitive: `PLAN-1` and `plan-1` are the same
     *                       code) is required, posted as given. A code on file is a 422
     *                       `plan_code_already_used` (ValidationException); read the
     *                       plan with getPlans(['code' => ...]) instead.
     * @return PlanResource
     */
    public function createPlan(array $data)
    {
        $this->ensureV3('createPlan');

        return new PlanResource($this->post('plans', ['json' => $data]), $this);
    }

    /** @param  array  $filters  e.g. `search`, `code`, `page`, `per_page`; passed through as given. */
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
     * @param  array  $data  Subscription payload. `portal_key` is required,
     *                       `reference` (the merchant's own id for this agreement,
     *                       up to 36 of A-Z a-z 0-9 _ -, unique per merchant
     *                       forever, case-insensitive: `INV-1` and `inv-1` are
     *                       the same reference) is required, and `start_date` must be a full
     *                       ISO-8601 timestamp (e.g. "2026-07-01T09:00:00+08:00")
     *                       at least an hour in the future; a bare date is rejected.
     * @return SubscriptionResource  `pending`, with `redirectUrl` (the provider's
     *                               card page) — redirect the buyer there. On a 202
     *                               (not settled in time) `redirectUrl` is null: read
     *                               it with getSubscription(). A reference on file is
     *                               a 422 `reference_already_used`. No Idempotency-Key.
     */
    public function createSubscription(array $data)
    {
        $this->ensureV3('createSubscription');
        $this->requirePortalKey($data);

        return new SubscriptionResource($this->post('subscriptions', ['json' => $data]), $this);
    }

    /** @param  array  $filters  e.g. `reference`, `customer_id`, `status`, `search`, `page`, `per_page`; passed through as given. */
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
     *                       `reference` is required (as on createSubscription()),
     *                       `description` (what the customer is paying off, up to
     *                       500 characters) is required, `interval` is one of
     *                       day|week|month|year (BoostRecurring::INTERVAL_*), and
     *                       `start_date` must be a full ISO-8601 timestamp
     *                       (e.g. "2026-07-01T09:00:00+08:00") at least an hour
     *                       in the future; a bare date is rejected.
     * @return InstallmentResource  `pending`, with `redirectUrl` (the provider's
     *                              card page) — redirect the buyer there. A 202 and
     *                              a reference on file answer as on
     *                              createSubscription(). No Idempotency-Key.
     */
    public function createInstallment(array $data)
    {
        $this->ensureV3('createInstallment');
        $this->requirePortalKey($data);

        return new InstallmentResource($this->post('installments', ['json' => $data]), $this);
    }

    /** @param  array  $filters  e.g. `reference`, `customer_id`, `status`, `search`, `page`, `per_page`; passed through as given. */
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
     * Charge a customer's saved card now. Synchronous: the answer is the charge
     * itself, as a transaction record — `status` 3 successful, 2 declined (a
     * decline is a charge that failed, not an exception), 1 still settling (its
     * final status arrives on `callback_url`). A customer with no saved card is a
     * 422 `customer_no_saved_card` (ValidationException): save one with
     * saveCard() first. No `return_url`: there is no customer page.
     *
     * The merchant's `reference` is the charge's identity, so no Idempotency-Key is
     * sent: a reference already on file is a 422 `reference_already_used`
     * (ValidationException), never the charge on file. A charge answers the
     * reference as `order_number`.
     *
     * A 202 means no charge record yet (a timeout or an error between Bayarcash and
     * the provider, or the provider not having settled it). The resource then holds
     * only `reference` and `status` the string `pending` (`id` is null). The card
     * may already have been charged. Do not repeat the create (a repeat is a 422):
     * wait for the charge callback, or read the charge with
     * getTransactionByOrderNumber($reference) (an array of TransactionResource)
     * once it is booked, BEFORE ever charging again under a new reference —
     * otherwise the customer can be charged twice.
     *
     * @param  array  $data  Payment payload. `portal_key` and `reference` (up to 36 of
     *                       A-Z a-z 0-9 _ -, unique per merchant forever,
     *                       case-insensitive: `INV-1` and `inv-1` are the same
     *                       reference) are required.
     * @return TransactionResource
     */
    public function createOnDemandPayment(array $data)
    {
        $this->ensureV3('createOnDemandPayment');
        $this->requirePortalKey($data);

        return new TransactionResource($this->post('payments', ['json' => $data]), $this);
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

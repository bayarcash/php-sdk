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
 * getAllTransactions(['order_number' => $reference, 'recurring_type' => 'on_demand'])
 * once it is booked (`data` holds TransactionResource), or from the charge callback.
 *
 * Every recurring call waits 90 seconds (or setTimeout() when larger; one-off
 * calls keep setTimeout(), 30 by default), so your own PHP and proxy limits must
 * allow 90 seconds. A create can still take longer: an on-demand charge with
 * `customer_id` up to about 90 seconds, a create with an inline `customer` up to
 * 227 seconds (on-demand) or 182 seconds (subscription, installment). A timeout or
 * a transport error is the same as a 202: the record may already exist and an
 * on-demand card may already have been charged, so read the outcome as above.
 *
 * A 401, 403 or 429, or a 502 whose body's `error.code` is `upstream_auth`, on a
 * create is not a 202: nothing was created and no card was charged. Send it
 * again under a NEW reference. After a 429, wait a short while first: the SDK
 * does not expose Retry-After, and ->rateLimitResetsAt is set only when the API
 * sends X-RateLimit-Reset (null otherwise). A 401, 403 or 502 throws \Exception
 * whose message is the API's body; a 429 throws RateLimitExceededException.
 *
 * After an on-demand 202 (or timeout) the card may already have been charged:
 * wait for the callback or read the charge BEFORE ever charging again under a
 * new reference, or the customer can be charged twice. Right after the 202 an
 * empty lookup is not yet proof (the charge may still be booked by the callback or by Bayarcash's
 * background check, which starts 15 minutes after the charge and runs every 5).
 * That is usually about 20 minutes; it can take longer while the exchange is
 * unreachable or has not settled the charge. Rely on the callback: charge again
 * under a new reference only once the callback says the charge failed, or the
 * lookup still finds nothing well after that. While an
 * agreement reads `pending` it carries `redirectUrl`; the card page lives 20
 * minutes from `createdAt`. After that an installment needs a new create (with a
 * new reference); a subscription still holds the customer's place on its plan
 * (below), so cancel it first. These three take no Idempotency-Key.
 *
 * One subscription per customer per plan: while the customer holds one to the
 * plan that has not ended (`pending` - including one still being created -
 * `active` or `paused`), createSubscription() for the same customer and plan is
 * a 422 `customer_already_subscribed` ("The customer is already subscribed to
 * this plan.", ValidationException), as the provider itself refuses it. Cancel
 * the existing one first (cancelSubscription()); one still being created must
 * settle first, or wait until it reads `expired` (about 30 minutes after its
 * creation).
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
     * getCustomers(['search' => $email]) instead. When that search finds none, the
     * email is held at the provider outside the merchant's records: contact support.
     */
    public function createCustomer(array $data)
    {
        $this->ensureV3('createCustomer');

        return new CustomerResource($this->recurringRequest('POST', 'customers', ['json' => $data]), $this);
    }

    public function getCustomers(array $filters = [])
    {
        $this->ensureV3('getCustomers');

        return $this->paginate($this->recurringRequest('GET', 'customers' . $this->query($filters)), CustomerResource::class);
    }

    public function getCustomer(string $customerId)
    {
        $this->ensureV3('getCustomer');

        return new CustomerResource($this->recurringRequest('GET', 'customers/' . $customerId), $this);
    }

    /**
     * Save a card for the customer: creates a Credit Card payment intent that
     * also vaults the payer's card against the customer, for later on-demand
     * charges. The returned intent's `url` is the provider's card page: redirect
     * the payer there at once. It lives 30 minutes and is used once; the
     * Bayarcash checkout does not open a save-card intent.
     *
     * When the card page cannot be started the API answers 403
     * (`save_card_refused`, with the reason) or 502 (`save_card_failed`, retry
     * with a new call), as `{"error": {"code", "message", "payment_intent_id"}}`.
     * This SDK throws both as an \Exception whose message is that JSON body.
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
            $this->recurringRequest('POST', 'customers/' . $customerId . '/save-card', ['json' => $data]),
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

        return new PlanResource($this->recurringRequest('POST', 'plans', ['json' => $data]), $this);
    }

    /** @param  array  $filters  e.g. `search`, `code`, `page`, `per_page`; passed through as given. */
    public function getPlans(array $filters = [])
    {
        $this->ensureV3('getPlans');

        return $this->paginate($this->recurringRequest('GET', 'plans' . $this->query($filters)), PlanResource::class);
    }

    public function getPlan(string $planId)
    {
        $this->ensureV3('getPlan');

        return new PlanResource($this->recurringRequest('GET', 'plans/' . $planId), $this);
    }

    public function updatePlan(string $planId, array $data)
    {
        $this->ensureV3('updatePlan');

        return new PlanResource($this->recurringRequest('PATCH', 'plans/' . $planId, ['json' => $data]), $this);
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
     *                               a 422 `reference_already_used`; a customer already
     *                               subscribed to the plan (one not ended) is a 422
     *                               `customer_already_subscribed` - cancel it first.
     *                               No Idempotency-Key.
     */
    public function createSubscription(array $data)
    {
        $this->ensureV3('createSubscription');
        $this->requirePortalKey($data);

        return new SubscriptionResource($this->recurringRequest('POST', 'subscriptions', ['json' => $data]), $this);
    }

    /** @param  array  $filters  e.g. `reference`, `customer_id`, `status`, `search`, `page`, `per_page`; passed through as given. */
    public function getSubscriptions(array $filters = [])
    {
        $this->ensureV3('getSubscriptions');

        return $this->paginate($this->recurringRequest('GET', 'subscriptions' . $this->query($filters)), SubscriptionResource::class);
    }

    public function getSubscription(string $subscriptionId)
    {
        $this->ensureV3('getSubscription');

        return new SubscriptionResource($this->recurringRequest('GET', 'subscriptions/' . $subscriptionId), $this);
    }

    public function updateSubscription(string $subscriptionId, array $data)
    {
        $this->ensureV3('updateSubscription');

        return new SubscriptionResource($this->recurringRequest('PATCH', 'subscriptions/' . $subscriptionId, ['json' => $data]), $this);
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

        return new InstallmentResource($this->recurringRequest('POST', 'installments', ['json' => $data]), $this);
    }

    /** @param  array  $filters  e.g. `reference`, `customer_id`, `status`, `search`, `page`, `per_page`; passed through as given. */
    public function getInstallments(array $filters = [])
    {
        $this->ensureV3('getInstallments');

        return $this->paginate($this->recurringRequest('GET', 'installments' . $this->query($filters)), InstallmentResource::class);
    }

    public function getInstallment(string $installmentId)
    {
        $this->ensureV3('getInstallment');

        return new InstallmentResource($this->recurringRequest('GET', 'installments/' . $installmentId), $this);
    }

    public function updateInstallment(string $installmentId, array $data)
    {
        $this->ensureV3('updateInstallment');

        return new InstallmentResource($this->recurringRequest('PATCH', 'installments/' . $installmentId, ['json' => $data]), $this);
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
     * only `reference` and `status` the string `pending` (`id` is null). A timeout
     * or a transport error is the same as a 202: this call waits 90 seconds (or
     * setTimeout() when larger; your own PHP and proxy limits must allow it), and a
     * charge with `customer_id` can take about that long, one with an inline
     * `customer` up to 227 seconds. The card may already have been charged. Do not repeat the create (a repeat is a 422):
     * wait for the charge callback, or read the charge with
     * getAllTransactions(['order_number' => $reference, 'recurring_type' => 'on_demand'])
     * (`data` holds TransactionResource) once it is booked, BEFORE ever charging
     * again under a new reference — otherwise the customer can be charged twice.
     * Right after the 202 an empty `data` is not yet proof: Bayarcash's background
     * check first asks about the charge 15 minutes after it was recorded, then every 5 minutes, so it is
     * usually booked within about 20 minutes; it can take longer while the exchange
     * is unreachable or has not settled the charge. Rely on the callback: charge
     * again under a new reference only once the callback says the charge failed, or
     * `data` is still empty well after that.
     *
     * A 401, 403, 429, or a 502 `upstream_auth`, is not a 202: nothing was created
     * and the card was not charged. Charge again under a NEW reference (after a
     * 429, wait a short while first).
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

        return new TransactionResource($this->recurringRequest('POST', 'payments', ['json' => $data]), $this);
    }

    /**
     * One charge, as a transaction record. Accepts either the provider charge id
     * (`exchangeTransactionId` of a list row or the charge callback) or the trx_ id.
     */
    public function getPayment(string $paymentId)
    {
        $this->ensureV3('getPayment');

        return new TransactionResource($this->recurringRequest('GET', 'payments/' . $paymentId), $this);
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
     * Every recurring call waits 90 seconds, or setTimeout() when that is larger: a
     * create can take that long at the API (a timeout is the same as a 202 - read the
     * record, never send it again under a new reference). One-off calls keep setTimeout().
     */
    protected function recurringRequest(string $verb, string $uri, array $payload = [])
    {
        return $this->request($verb, $uri, $payload, [], max(90.0, (float) $this->timeout));
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
            $this->recurringRequest('GET', $uri . $this->query(['page' => $page, 'per_page' => $perPage])),
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

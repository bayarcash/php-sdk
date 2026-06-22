<?php

namespace Webimpian\BayarcashSdk\Actions;

use Webimpian\BayarcashSdk\Resources\ChargeResource;
use Webimpian\BayarcashSdk\Resources\CustomerResource;
use Webimpian\BayarcashSdk\Resources\InstallmentResource;
use Webimpian\BayarcashSdk\Resources\PaymentResource;
use Webimpian\BayarcashSdk\Resources\PlanResource;
use Webimpian\BayarcashSdk\Resources\RecurringIntentResource;
use Webimpian\BayarcashSdk\Resources\SubscriptionResource;

/**
 * Boost recurring-payment endpoints (v3 only).
 *
 * Customers and Plans return their resource directly on create. Subscriptions,
 * Installments and on-demand Payments return a RecurringIntentResource carrying
 * an authorization `url` — redirect the buyer there to authorize their card,
 * then fetch the eventual resource by id.
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

    public function createCustomerPaymentMethod(string $customerId)
    {
        $this->ensureV3('createCustomerPaymentMethod');

        return $this->post('customers/' . $customerId . '/payment-methods');
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

    public function createSubscription(array $data, ?string $idempotencyKey = null)
    {
        $this->ensureV3('createSubscription');

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

        $response = $this->get('subscriptions/' . $subscriptionId);

        return $this->withCharges(new SubscriptionResource($response, $this), $response);
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

    public function getSubscriptionPayments(string $subscriptionId)
    {
        $this->ensureV3('getSubscriptionPayments');

        $response = $this->get('subscriptions/' . $subscriptionId . '/payments');

        return $this->transformCollection($response['data'] ?? [], ChargeResource::class);
    }

    // ---------------------------------------------------------------------
    // Installments
    // ---------------------------------------------------------------------

    public function createInstallment(array $data, ?string $idempotencyKey = null)
    {
        $this->ensureV3('createInstallment');

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

        $response = $this->get('installments/' . $installmentId);

        return $this->withCharges(new InstallmentResource($response, $this), $response);
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

    public function getInstallmentPayments(string $installmentId)
    {
        $this->ensureV3('getInstallmentPayments');

        $response = $this->get('installments/' . $installmentId . '/payments');

        return $this->transformCollection($response['data'] ?? [], ChargeResource::class);
    }

    // ---------------------------------------------------------------------
    // On-demand payments
    // ---------------------------------------------------------------------

    /**
     * Create a one-off on-demand charge against a customer's card-on-file.
     *
     * The API REQUIRES an Idempotency-Key; if none is supplied the SDK
     * auto-generates a UUID v4 (pass your own to make retries idempotent).
     */
    public function createOnDemandPayment(array $data, ?string $idempotencyKey = null)
    {
        $this->ensureV3('createOnDemandPayment');

        $idempotencyKey = $idempotencyKey ?: $this->generateIdempotencyKey();

        return new RecurringIntentResource(
            $this->post('payments', ['json' => $data], $this->idempotencyHeaders($idempotencyKey)),
            $this
        );
    }

    public function getPayment(string $paymentId)
    {
        $this->ensureV3('getPayment');

        return new PaymentResource($this->get('payments/' . $paymentId), $this);
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

    /** Build the Idempotency-Key header array (empty when no key). */
    protected function idempotencyHeaders(?string $key): array
    {
        return $key ? ['Idempotency-Key' => $key] : [];
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

    /**
     * Replace a resource's raw `charges` array with transformed ChargeResource objects.
     *
     * @param  \Webimpian\BayarcashSdk\Resources\SubscriptionResource|\Webimpian\BayarcashSdk\Resources\InstallmentResource  $resource
     * @return mixed
     */
    protected function withCharges($resource, array $response)
    {
        if (isset($response['charges']) && is_array($response['charges'])) {
            $resource->charges = $this->transformCollection($response['charges'], ChargeResource::class);
        }

        return $resource;
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

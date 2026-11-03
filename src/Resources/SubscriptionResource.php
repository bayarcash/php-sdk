<?php

namespace Bayarcash\Resources;

class SubscriptionResource extends Resource
{
    public ?string $type = null;
    public ?string $id = null;
    public ?string $portalKey = null;
    /** The merchant's own id for this agreement, as sent on the create. */
    public ?string $reference = null;
    public ?string $customerId = null;
    public ?string $planId = null;
    /** The plan it bills on, in the plan resource's shape (raw array). */
    public ?array $plan = null;
    /** pending, active, paused, canceled, expired, terminated or failed (see BoostRecurring::STATUS_*). */
    public ?string $status = null;
    /** Why it is paused: payment_failed, merchant or provider. Null when not paused. */
    public ?string $pausedReason = null;
    /**
     * The card the agreement bills, fixed at enrolment: ['last4' => '1118', 'brand' => 'visa'|null,
     * 'country' => 'MY'|null, 'exp_month' => 12, 'exp_year' => 2030]. Null until the first charge
     * has been observed.
     */
    public ?array $card = null;
    public ?string $startDate = null;
    public ?string $nextPaymentDate = null;
    public ?string $createdAt = null;
    /**
     * The provider's card page the customer must complete to enrol. Only on the answer to
     * createSubscription() (and its idempotent replay); null on every later read.
     */
    public ?string $redirectUrl = null;
}

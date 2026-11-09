<?php

namespace Bayarcash\Resources;

class InstallmentResource extends Resource
{
    public ?string $type = null;
    public ?string $id = null;
    public ?string $portalKey = null;
    /** The merchant's own id for this agreement, as sent on the create. */
    public ?string $reference = null;
    public ?string $customerId = null;
    public ?string $totalAmount = null;
    public ?string $remainingAmount = null;
    public ?string $paidAmount = null;
    public $progressPercent = null;
    public ?string $currency = null;
    public ?string $interval = null;
    public ?int $installmentCount = null;
    public ?string $description = null;
    /**
     * pending, active, paused, canceled, expired, terminated, failed, or completed (every
     * instalment collected; the provider itself ends such a plan as terminated).
     */
    public ?string $status = null;
    /** Why it is paused: payment_failed, merchant or provider. Null when not paused. */
    public ?string $pausedReason = null;
    /**
     * The card the agreement bills, fixed at enrolment: ['last4' => '1118', 'brand' => 'visa'|null,
     * 'country' => 'MY'|null, 'exp_month' => 12, 'exp_year' => 2030]. Null until the first charge
     * has been observed.
     */
    public ?array $card = null;
    public ?string $nextPaymentDate = null;
    public ?string $createdAt = null;
    /**
     * The provider's card page the customer must complete to enrol, on every answer while
     * the agreement is `pending` (null on a 202 until the provider has answered; read it
     * again with a get). The page lives 20 minutes from `createdAt`. Null once the agreement
     * has moved on.
     */
    public ?string $redirectUrl = null;
}

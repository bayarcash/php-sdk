<?php

namespace Webimpian\BayarcashSdk\Resources;

class InstallmentResource extends Resource
{
    public ?string $type = null;
    public ?string $id = null;
    public ?string $portalKey = null;
    public ?string $customerId = null;
    public ?string $totalAmount = null;
    public ?string $remainingAmount = null;
    public ?string $paidAmount = null;
    public $progressPercent = null;
    public ?string $currency = null;
    public ?string $interval = null;
    public ?int $installmentCount = null;
    public ?string $description = null;
    public ?string $status = null;
    /** Why it is paused: payment_failed, merchant or provider. Null when not paused. */
    public ?string $pausedReason = null;
    /**
     * The card the agreement bills, fixed at enrolment: ['last4' => '1118', 'brand' => 'visa'|null,
     * 'exp_month' => 12, 'exp_year' => 2030]. Null until the first charge has been observed.
     */
    public ?array $card = null;
    public ?string $nextPaymentDate = null;
    public ?string $createdAt = null;
}

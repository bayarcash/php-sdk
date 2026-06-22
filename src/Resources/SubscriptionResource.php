<?php

namespace Webimpian\BayarcashSdk\Resources;

class SubscriptionResource extends Resource
{
    public ?string $type = null;
    public ?string $id = null;
    public ?string $customerId = null;
    public ?string $planId = null;
    public ?string $status = null;
    public ?string $startDate = null;
    public ?string $nextPaymentDate = null;
    public ?string $createdAt = null;

    /** @var \Webimpian\BayarcashSdk\Resources\ChargeResource[] */
    public array $charges = [];
}

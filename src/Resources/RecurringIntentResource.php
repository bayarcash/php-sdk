<?php

namespace Webimpian\BayarcashSdk\Resources;

class RecurringIntentResource extends Resource
{
    public ?string $type = null;
    public ?string $id = null;
    public ?string $portalKey = null;
    public ?string $recurringType = null;
    public ?string $status = null;
    public ?string $customerId = null;
    public ?string $planId = null;
    public $amount = null;
    public $totalAmount = null;
    public ?string $interval = null;
    public ?int $installmentCount = null;
    public ?string $currency = null;
    public ?string $description = null;
    public ?string $startDate = null;
    public ?string $callbackUrl = null;
    public ?string $returnUrl = null;
    public ?string $createdAt = null;
    public ?string $expiredAt = null;
    public ?string $url = null;
}

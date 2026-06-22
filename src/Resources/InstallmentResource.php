<?php

namespace Webimpian\BayarcashSdk\Resources;

class InstallmentResource extends Resource
{
    public ?string $type = null;
    public ?string $id = null;
    public ?string $customerId = null;
    public ?string $totalAmount = null;
    public ?string $remainingAmount = null;
    public ?string $paidAmount = null;
    public $progressPercent = null;
    public ?string $currency = null;
    public ?string $interval = null;
    public ?int $installmentCount = null;
    public ?string $status = null;
    public ?string $nextPaymentDate = null;
    public ?string $createdAt = null;

    /** @var \Webimpian\BayarcashSdk\Resources\ChargeResource[] */
    public array $charges = [];
}

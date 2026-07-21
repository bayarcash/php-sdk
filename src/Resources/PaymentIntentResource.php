<?php

namespace Webimpian\BayarcashSdk\Resources;

class PaymentIntentResource extends Resource
{
    public ?string $payerName = null;
    public ?string $payerEmail = null;
    public ?string $payerTelephoneNumber = null;
    public ?string $orderNumber = null;
    public ?float $amount = null;
    public ?string $url = null;

    public ?string $type = null;
    public ?string $id = null;
    public ?string $status = null;
    public $lastAttempt = null;
    public ?string $paidAt = null;
    public ?string $currency = null;
    public ?array $attempts = null;
}

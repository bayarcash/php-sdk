<?php

namespace Webimpian\BayarcashSdk\Resources;

class FpxDirectDebitApplicationResource extends Resource
{
    public ?string $payerName = null;
    public ?int $payerIdType = null;
    public ?string $payerId = null;
    public ?string $payerEmail = null;
    public ?string $payerTelephoneNumber = null;
    public ?string $orderNumber = null;
    public ?float $amount = null;
    public ?string $applicationType = null;
    public ?string $applicationReason = null;
    public ?string $frequencyMode = null;
    public ?string $effectiveDate = null;
    public ?string $expiryDate = null;
    public ?string $url = null;
}

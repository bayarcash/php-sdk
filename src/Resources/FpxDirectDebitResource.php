<?php

namespace Webimpian\BayarcashSdk\Resources;

class FpxDirectDebitResource extends Resource
{
    public ?string $id = null;
    public ?string $updatedAt = null;
    public ?string $mandateReferenceNumber = null;
    public ?string $orderNumber = null;
    public ?string $applicationReason = null;
    public ?string $frequencyMode = null;
    public ?string $frequencyModeLabel = null;
    public ?string $effectiveDate = null;
    public ?string $expiryDate = null;
    public ?string $currency = null;
    public ?float $amount = null;
    public ?string $payerName = null;
    public ?string $payerId = null;
    public ?int $payerIdType = null;
    public ?string $payerBankAccountNumber = null;
    public ?string $payerEmail = null;
    public ?string $payerTelephoneNumber = null;
    public ?string $status = null;
    public ?string $statusDescription = null;
    public ?string $returnUrl = null;
    public ?array $metadata = null;
    public ?string $portal = null;
    public ?array $merchant = null;
}

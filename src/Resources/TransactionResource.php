<?php

namespace Bayarcash\Resources;

class TransactionResource extends Resource
{
    public ?string $id = null;
    public ?string $updatedAt = null;
    public ?string $createdAt = null;
    public ?string $datetime = null;
    public ?string $payerName = null;
    public ?string $payerEmail = null;
    public ?string $payerTelephoneNumber = null;
    public ?string $orderNumber = null;
    public ?string $currency = null;
    public ?float $amount = null;
    public ?string $exchangeReferenceNumber = null;
    public ?string $exchangeTransactionId = null;
    public ?string $payerBankName = null;
    public ?string $status = null;
    public ?string $statusDescription = null;
    public ?string $returnUrl = null;
    public ?array $metadata = null;
    public ?array $payout = null;
    public ?array $paymentGateway = null;
    public ?string $portal = null;
    public ?array $merchant = null;
    public ?array $mandate = null;
}
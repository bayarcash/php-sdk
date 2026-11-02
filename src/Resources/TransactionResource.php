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
    public ?float $platformFees = null;
    public ?float $nettAmount = null;
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
    public ?bool $payerIdentityVerified = null;
    public ?array $fpxExtraInfo = null;

    /* FPX Direct Debit only: the deduction or the application it belongs to. */
    public ?string $transactionType = null;
    public ?string $applicationReason = null;

    /* The pi_ id, on a transaction made from a payment intent. */
    public ?string $paymentIntentId = null;

    /*
     * Recurring charges only. recurringCustomerId is the rcus_ id; a scheduled
     * charge carries the agreement that produced it (raw subscription/installment
     * resource arrays, the subscription with its `plan`), an on-demand charge neither.
     */
    public ?string $recurringCustomerId = null;
    public ?array $subscription = null;
    public ?array $installment = null;
}

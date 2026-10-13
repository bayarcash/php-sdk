<?php

namespace Webimpian\BayarcashSdk\Resources;

class PaymentIntentResource extends Resource
{

    public string $payerName;
    public string $payerEmail;
    public ?string $payerTelephoneNumber;
    public string $orderNumber;
    public float $amount;
    public ?string $url;

    public string $type;
    public string $id;
    public string $status;
    public $lastAttempt; 
    public ?string $paidAt;
    public string $currency;
    public array $attempts;
    /** Save-card intents only: `capture` or `validate_only`; null on any other intent. */
    public ?string $tokenize = null;
}

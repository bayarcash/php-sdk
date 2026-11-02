<?php

namespace Bayarcash\Resources;

class DuitNowQrResource extends Resource
{
    public ?string $type = null;
    public ?string $transactionId = null;
    public ?string $paymentIntentId = null;
    public ?string $qrString = null;
    public ?string $qrImage = null;
    public ?string $qrExpiresAt = null;
    public ?string $pollUrl = null;
    public ?int $nextPollAfterMs = null;
    public ?float $amount = null;
    public ?string $currency = null;
    public ?string $orderNumber = null;
}

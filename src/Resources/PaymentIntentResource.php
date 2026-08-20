<?php

namespace Bayarcash\Resources;

use Bayarcash\Bayarcash;

class PaymentIntentResource extends Resource
{
    public ?string $payerName = null;
    public ?string $payerEmail = null;
    public ?string $payerTelephoneNumber = null;
    public ?string $orderNumber = null;
    public ?float $amount = null;
    public ?string $url = null;
    public ?DuitNowQrResource $duitnowQr = null;

    public ?string $type = null;
    public ?string $id = null;
    public ?string $status = null;
    public $lastAttempt = null;
    public ?string $paidAt = null;
    public ?string $currency = null;
    public ?array $attempts = null;

    public function __construct(array $attributes, ?Bayarcash $bayarcash = null)
    {
        $qr = $attributes['duitnow_qr'] ?? null;
        unset($attributes['duitnow_qr']);

        parent::__construct($attributes, $bayarcash);

        if (is_array($qr)) {
            $this->duitnowQr = new DuitNowQrResource($qr, $bayarcash);
        }
    }
}

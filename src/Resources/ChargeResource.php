<?php

namespace Webimpian\BayarcashSdk\Resources;

class ChargeResource extends Resource
{
    public ?string $type = null;
    public ?string $id = null;
    public ?string $amount = null;
    public ?string $currency = null;
    public ?string $status = null;
    public ?string $paymentMethod = null;
    public ?string $description = null;
    public ?string $createdAt = null;
}

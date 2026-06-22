<?php

namespace Webimpian\BayarcashSdk\Resources;

class PaymentResource extends Resource
{
    public ?string $type = null;
    public ?string $id = null;
    public ?string $customerId = null;
    public ?string $amount = null;
    public ?string $currency = null;
    public ?string $status = null;
    public ?string $description = null;
    public ?string $createdAt = null;
}

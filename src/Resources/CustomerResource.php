<?php

namespace Webimpian\BayarcashSdk\Resources;

class CustomerResource extends Resource
{
    public ?string $type = null;
    public ?string $id = null;
    public ?string $portalKey = null;
    public ?string $fullName = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?bool $cardOnFile = null;
    public ?string $cardLast4 = null;
    public ?string $cardBrand = null;
    public ?string $createdAt = null;
    public ?bool $existing = null;
}

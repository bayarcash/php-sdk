<?php

namespace Webimpian\BayarcashSdk\Resources;

class PlanResource extends Resource
{
    public ?string $type = null;
    public ?string $id = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $price = null;
    public ?string $currency = null;
    public ?string $interval = null;
    public ?string $createdAt = null;
}

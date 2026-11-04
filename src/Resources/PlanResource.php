<?php

namespace Bayarcash\Resources;

class PlanResource extends Resource
{
    public ?string $type = null;
    public ?string $id = null;
    /** The merchant's own plan code, as sent on the create (unique per merchant). */
    public ?string $code = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $price = null;
    public ?string $currency = null;
    public ?string $interval = null;
    public ?string $createdAt = null;
}

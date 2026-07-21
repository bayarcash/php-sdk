<?php

namespace Webimpian\BayarcashSdk\Resources;

class FpxBankResource extends Resource
{
    public ?string $bankName = null;
    public ?string $bankDisplayName = null;
    public ?string $bankCode = null;
    public ?string $bankCodeHashed = null;
    public ?bool $bankAvailability = null;
}

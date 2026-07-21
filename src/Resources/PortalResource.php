<?php

namespace Webimpian\BayarcashSdk\Resources;

class PortalResource extends Resource
{
    public ?string $id = null;
    public ?string $createdAt = null;
    public ?string $portalKey = null;
    public ?string $portalName = null;
    public ?string $websiteUrl = null;
    public ?string $transactionNotificationEmail = null;
    public ?string $secondaryTransactionNotificationEmail = null;
    public ?string $customPaymentButtonText = null;
    public ?int $enabledSmsOnSuccessfulTransaction = null;
    public ?bool $splitPaymentEnabled = null;
    public ?array $splitPaymentMerchants = null;
    public ?array $paymentChannels = null;
    public ?array $merchant = null;
    public ?string $url = null;
    public ?string $merchantId = null;
}

<?php

namespace Webimpian\BayarcashSdk\Tests\Recurring;

use Webimpian\BayarcashSdk\Resources\ChargeResource;
use Webimpian\BayarcashSdk\Resources\InstallmentResource;
use Webimpian\BayarcashSdk\Resources\RecurringIntentResource;
use Webimpian\BayarcashSdk\Tests\TestCase;

class InstallmentTest extends TestCase
{
    public function test_create_installment_returns_recurring_intent(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'recurring_intent', 'id' => 'brci_2', 'recurring_type' => 'installment',
                'status' => 'pending', 'total_amount' => 1200.0, 'installment_count' => 12,
                'interval' => 'P1M', 'url' => 'https://console/authorize/brci_2',
            ], 201),
        ]);

        $intent = $bayarcash->createInstallment([
            'customer_id' => 'bcus_1',
            'total_amount' => 1200.00,
            'interval' => 'P1M',
            'installment_count' => 12,
            'start_date' => '2026-07-01T09:00:00+08:00',
        ]);

        $this->assertInstanceOf(RecurringIntentResource::class, $intent);
        $this->assertSame('installment', $intent->recurringType);
        $this->assertSame(12, $intent->installmentCount);
        $this->assertSame('https://console/authorize/brci_2', $intent->url);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('installments', (string) $this->lastRequest()->getUri());
        $this->assertSame(12, $this->lastBody()['installment_count']);
    }

    public function test_get_installments_paginated(): void
    {
        $bayarcash = $this->sdk([$this->json(['data' => [['id' => 'bins_1']], 'meta' => ['total' => 1]])]);

        $result = $bayarcash->getInstallments(['status' => 'active']);

        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(InstallmentResource::class, $result['data'][0]);
        $this->assertStringContainsString('status=active', (string) $this->lastRequest()->getUri());
    }

    public function test_get_installment_transforms_charges_and_progress(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'id' => 'bins_1', 'total_amount' => '1200.00', 'paid_amount' => '300.00',
                'remaining_amount' => '900.00', 'progress_percent' => 25, 'installment_count' => 12,
                'charges' => [['id' => 'ch_1', 'amount' => '100.00']],
            ]),
        ]);

        $installment = $bayarcash->getInstallment('bins_1');

        $this->assertInstanceOf(InstallmentResource::class, $installment);
        $this->assertSame('1200.00', $installment->totalAmount);
        $this->assertSame(12, $installment->installmentCount);
        $this->assertCount(1, $installment->charges);
        $this->assertInstanceOf(ChargeResource::class, $installment->charges[0]);
        $this->assertStringContainsString('installments/bins_1', (string) $this->lastRequest()->getUri());
    }

    public function test_update_installment_and_lifecycle_helpers(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['id' => 'bins_1', 'status' => 'paused']),
            $this->json(['id' => 'bins_1', 'status' => 'active']),
            $this->json(['id' => 'bins_1', 'status' => 'canceled']),
            $this->json(['id' => 'bins_1', 'status' => 'terminated']),
        ]);

        $installment = $bayarcash->updateInstallment('bins_1', ['status' => 'paused']);
        $this->assertSame('paused', $installment->status);
        $this->assertSame('PATCH', $this->lastRequest()->getMethod());

        $bayarcash->resumeInstallment('bins_1');
        $this->assertSame('active', $this->lastBody()['status']);

        $bayarcash->cancelInstallment('bins_1');
        $this->assertSame('canceled', $this->lastBody()['status']);

        $bayarcash->terminateInstallment('bins_1');
        $this->assertSame('terminated', $this->lastBody()['status']);
    }

    public function test_get_installment_payments(): void
    {
        $bayarcash = $this->sdk([$this->json(['data' => [['id' => 'ch_1']]])]);

        $charges = $bayarcash->getInstallmentPayments('bins_1');

        $this->assertCount(1, $charges);
        $this->assertInstanceOf(ChargeResource::class, $charges[0]);
        $this->assertStringContainsString('installments/bins_1/payments', (string) $this->lastRequest()->getUri());
    }
}

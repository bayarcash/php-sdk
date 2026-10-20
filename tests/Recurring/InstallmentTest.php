<?php

namespace Webimpian\BayarcashSdk\Tests\Recurring;

use Webimpian\BayarcashSdk\Resources\TransactionResource;
use Webimpian\BayarcashSdk\Resources\InstallmentResource;
use Webimpian\BayarcashSdk\Tests\TestCase;

class InstallmentTest extends TestCase
{
    public function test_create_installment_returns_the_pending_plan_with_its_card_page(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'installment', 'id' => 'rins_2', 'portal_key' => 'pk_test_portal',
                'status' => 'pending', 'total_amount' => '1200.00', 'installment_count' => 12,
                'interval' => 'month', 'description' => 'Laptop, 12 payments',
                'redirect_url' => 'https://boost.example/card/def',
            ], 201),
        ]);

        $installment = $bayarcash->createInstallment([
            'portal_key' => 'pk_test_portal',
            'customer_id' => 'rcus_1',
            'total_amount' => 1200.00,
            'interval' => 'month',
            'installment_count' => 12,
            'description' => 'Laptop, 12 payments',
            'start_date' => '2026-07-01T09:00:00+08:00',
        ]);

        $this->assertInstanceOf(InstallmentResource::class, $installment);
        $this->assertSame('rins_2', $installment->id);
        $this->assertSame('pending', $installment->status);
        $this->assertSame('pk_test_portal', $installment->portalKey);
        $this->assertSame(12, $installment->installmentCount);
        $this->assertSame('https://boost.example/card/def', $installment->redirectUrl);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('installments', (string) $this->lastRequest()->getUri());
        $this->assertSame(12, $this->lastBody()['installment_count']);
        $this->assertSame('pk_test_portal', $this->lastBody()['portal_key']);
        $this->assertSame('Laptop, 12 payments', $this->lastBody()['description']);
        $this->assertSame('Laptop, 12 payments', $installment->description);
    }

    public function test_create_installment_requires_a_portal_key(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'rins_2'], 201)]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Required field 'portal_key' is missing");

        $bayarcash->createInstallment([
            'customer_id' => 'rcus_1', 'total_amount' => 1200.00,
            'interval' => 'month', 'installment_count' => 12,
        ]);
    }

    public function test_get_installments_paginated(): void
    {
        $bayarcash = $this->sdk([$this->json(['data' => [['id' => 'rins_1']], 'meta' => ['total' => 1]])]);

        $result = $bayarcash->getInstallments(['status' => 'active']);

        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(InstallmentResource::class, $result['data'][0]);
        $this->assertStringContainsString('status=active', (string) $this->lastRequest()->getUri());
    }

    public function test_get_installment_reads_progress(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'id' => 'rins_1', 'total_amount' => '1200.00', 'paid_amount' => '300.00',
                'remaining_amount' => '900.00', 'progress_percent' => 25, 'installment_count' => 12,
                'description' => 'Laptop, 12 payments',
            ]),
        ]);

        $installment = $bayarcash->getInstallment('rins_1');

        $this->assertInstanceOf(InstallmentResource::class, $installment);
        $this->assertSame('1200.00', $installment->totalAmount);
        $this->assertSame('Laptop, 12 payments', $installment->description);
        $this->assertSame(12, $installment->installmentCount);
        $this->assertStringContainsString('installments/rins_1', (string) $this->lastRequest()->getUri());
    }

    public function test_update_installment_and_lifecycle_helpers(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['id' => 'rins_1', 'status' => 'paused']),
            $this->json(['id' => 'rins_1', 'status' => 'active']),
            $this->json(['id' => 'rins_1', 'status' => 'canceled']),
            $this->json(['id' => 'rins_1', 'status' => 'terminated']),
        ]);

        $installment = $bayarcash->updateInstallment('rins_1', ['status' => 'paused']);
        $this->assertSame('paused', $installment->status);
        $this->assertSame('PATCH', $this->lastRequest()->getMethod());

        $bayarcash->resumeInstallment('rins_1');
        $this->assertSame('active', $this->lastBody()['status']);

        $bayarcash->cancelInstallment('rins_1');
        $this->assertSame('canceled', $this->lastBody()['status']);

        $bayarcash->terminateInstallment('rins_1');
        $this->assertSame('terminated', $this->lastBody()['status']);
    }

    public function test_get_installment_payments_returns_paginated_transactions(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'data' => [CustomerTest::chargeRow(['installment' => ['type' => 'installment', 'id' => 'rins_1']])],
                'links' => [],
                'meta' => ['total' => 1],
            ]),
        ]);

        $result = $bayarcash->getInstallmentPayments('rins_1', 3, 100);

        $this->assertInstanceOf(TransactionResource::class, $result['data'][0]);
        $this->assertSame('rins_1', $result['data'][0]->installment['id']);
        $this->assertNull($result['data'][0]->subscription);
        $uri = (string) $this->lastRequest()->getUri();
        $this->assertStringContainsString('installments/rins_1/payments', $uri);
        $this->assertStringContainsString('page=3', $uri);
        $this->assertStringContainsString('per_page=100', $uri);
    }
}

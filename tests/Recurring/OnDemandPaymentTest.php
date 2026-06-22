<?php

namespace Webimpian\BayarcashSdk\Tests\Recurring;

use Webimpian\BayarcashSdk\Resources\PaymentResource;
use Webimpian\BayarcashSdk\Resources\RecurringIntentResource;
use Webimpian\BayarcashSdk\Tests\TestCase;

class OnDemandPaymentTest extends TestCase
{
    public function test_create_on_demand_payment_returns_intent_and_sends_idempotency_key(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'recurring_intent', 'id' => 'brci_3', 'recurring_type' => 'on_demand',
                'status' => 'pending', 'amount' => 25.0, 'description' => 'Top up',
                'url' => 'https://console/authorize/brci_3',
            ], 201),
        ]);

        $intent = $bayarcash->createOnDemandPayment([
            'customer_id' => 'bcus_1',
            'amount' => 25.00,
            'description' => 'Top up',
        ], 'idem-key-1');

        $this->assertInstanceOf(RecurringIntentResource::class, $intent);
        $this->assertSame('on_demand', $intent->recurringType);
        $this->assertSame('https://console/authorize/brci_3', $intent->url);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('payments', (string) $this->lastRequest()->getUri());
        $this->assertSame('idem-key-1', $this->lastRequest()->getHeaderLine('Idempotency-Key'));
        $this->assertSame('Top up', $this->lastBody()['description']);
    }

    public function test_create_on_demand_payment_auto_generates_idempotency_key_when_omitted(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'brci_3', 'recurring_type' => 'on_demand'], 201)]);

        $bayarcash->createOnDemandPayment([
            'customer_id' => 'bcus_1', 'amount' => 25.00, 'description' => 'Top up',
        ]);

        // Required by the API — the SDK must always send a non-empty key (UUID v4).
        $key = $this->lastRequest()->getHeaderLine('Idempotency-Key');
        $this->assertNotEmpty($key);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $key
        );
    }

    public function test_get_payment(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'payment', 'id' => 'trx_9', 'customer_id' => 'bcus_1',
                'amount' => '25.00', 'currency' => 'MYR', 'status' => 'succeeded',
            ]),
        ]);

        $payment = $bayarcash->getPayment('trx_9');

        $this->assertInstanceOf(PaymentResource::class, $payment);
        $this->assertSame('trx_9', $payment->id);
        $this->assertSame('succeeded', $payment->status);
        $this->assertStringContainsString('payments/trx_9', (string) $this->lastRequest()->getUri());
    }
}

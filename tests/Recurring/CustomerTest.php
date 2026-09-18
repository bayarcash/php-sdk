<?php

namespace Webimpian\BayarcashSdk\Tests\Recurring;

use Webimpian\BayarcashSdk\Resources\CustomerResource;
use Webimpian\BayarcashSdk\Tests\TestCase;

class CustomerTest extends TestCase
{
    public function test_create_customer_posts_json_and_returns_resource(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'customer', 'id' => 'bcus_1',
                'full_name' => 'Jane Doe', 'email' => 'jane@example.com',
                'phone' => '0123456789', 'card_on_file' => false,
            ], 201),
        ]);

        $customer = $bayarcash->createCustomer([
            'full_name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '0123456789',
        ]);

        $this->assertInstanceOf(CustomerResource::class, $customer);
        $this->assertSame('bcus_1', $customer->id);
        $this->assertSame('Jane Doe', $customer->fullName);
        $this->assertFalse($customer->cardOnFile);

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('customers', (string) $request->getUri());
        $this->assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('jane@example.com', $this->lastBody()['email']);
        $this->assertArrayNotHasKey('portal_key', $this->lastBody());
    }

    public function test_create_customer_sends_idempotency_key_when_given(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'bcus_1'], 201)]);

        $bayarcash->createCustomer(
            ['full_name' => 'Jane', 'email' => 'j@e.com', 'phone' => '011'],
            'my-key-123'
        );

        $this->assertSame('my-key-123', $this->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function test_create_customer_omits_idempotency_header_by_default(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'bcus_1'], 201)]);

        $bayarcash->createCustomer([
            'full_name' => 'Jane', 'email' => 'j@e.com', 'phone' => '011',
        ]);

        $this->assertFalse($this->lastRequest()->hasHeader('Idempotency-Key'));
    }

    public function test_get_customers_returns_paginated_collection(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'data' => [['id' => 'bcus_1'], ['id' => 'bcus_2']],
                'meta' => ['current_page' => 1, 'total' => 2],
                'links' => ['next' => null],
            ]),
        ]);

        $result = $bayarcash->getCustomers(['search' => 'jane', 'per_page' => 50]);

        $this->assertCount(2, $result['data']);
        $this->assertInstanceOf(CustomerResource::class, $result['data'][0]);
        $this->assertSame(2, $result['meta']['total']);
        $this->assertStringContainsString('search=jane', (string) $this->lastRequest()->getUri());
        $this->assertStringContainsString('per_page=50', (string) $this->lastRequest()->getUri());
    }

    public function test_get_customer_by_id(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'bcus_9', 'card_on_file' => true, 'card_last4' => '4242'])]);

        $customer = $bayarcash->getCustomer('bcus_9');

        $this->assertSame('bcus_9', $customer->id);
        $this->assertTrue($customer->cardOnFile);
        $this->assertSame('4242', $customer->cardLast4);
        $this->assertStringContainsString('customers/bcus_9', (string) $this->lastRequest()->getUri());
    }

    public function test_save_card_returns_the_hosted_page_payload(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['type' => 'save_card', 'status' => 'pending', 'payment_url' => 'https://pay']),
        ]);

        $result = $bayarcash->saveCard('bcus_9');

        $this->assertSame('https://pay', $result['payment_url']);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('customers/bcus_9/save-card', (string) $this->lastRequest()->getUri());
        // Nothing is minted here, so the request carries no body at all.
        $this->assertSame('', (string) $this->lastRequest()->getBody());
    }

    public function test_recurring_methods_require_v3(): void
    {
        $bayarcash = (new \Webimpian\BayarcashSdk\Bayarcash('tok')); // defaults to v2

        $this->expectException(\Exception::class);
        $bayarcash->createCustomer([
            'full_name' => 'x', 'email' => 'x@y.com', 'phone' => '1',
        ]);
    }
}

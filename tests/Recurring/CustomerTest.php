<?php

namespace Webimpian\BayarcashSdk\Tests\Recurring;

use Webimpian\BayarcashSdk\Fpx;
use Webimpian\BayarcashSdk\Resources\CustomerResource;
use Webimpian\BayarcashSdk\Resources\TransactionResource;
use Webimpian\BayarcashSdk\Tests\TestCase;

class CustomerTest extends TestCase
{
    public function test_create_customer_posts_json_and_returns_resource(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'customer', 'id' => 'rcus_1',
                'full_name' => 'Jane Doe', 'email' => 'jane@example.com',
                'phone' => '0123456789', 'card_on_file' => false,
            ], 201),
        ]);

        $customer = $bayarcash->createCustomer([
            'full_name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '0123456789',
        ]);

        $this->assertInstanceOf(CustomerResource::class, $customer);
        $this->assertSame('rcus_1', $customer->id);
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
        $bayarcash = $this->sdk([$this->json(['id' => 'rcus_1'], 201)]);

        $bayarcash->createCustomer(
            ['full_name' => 'Jane', 'email' => 'j@e.com', 'phone' => '011'],
            'my-key-123'
        );

        $this->assertSame('my-key-123', $this->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function test_every_minting_post_auto_generates_an_idempotency_key(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['id' => 'rcus_1'], 201),
            $this->json(['id' => 'rpln_1'], 201),
            $this->json(['id' => 'rint_1'], 201),
            $this->json(['id' => 'rint_2'], 201),
            $this->json(['payment_url' => 'https://pay']),
        ]);

        // The API answers 422 without the header on these POSTs.
        $bayarcash->createCustomer(['full_name' => 'Jane', 'email' => 'j@e.com', 'phone' => '011']);
        $bayarcash->createPlan(['name' => 'Pro', 'price' => 10, 'interval' => 'month']);
        $bayarcash->createSubscription(['portal_key' => 'pk', 'customer_id' => 'rcus_1', 'plan_id' => 'rpln_1']);
        $bayarcash->createInstallment(['portal_key' => 'pk', 'customer_id' => 'rcus_1', 'total_amount' => 600]);
        $bayarcash->saveCard('rcus_1');

        $keys = array_map(function ($entry) {
            return $entry['request']->getHeaderLine('Idempotency-Key');
        }, $this->history);

        $this->assertCount(5, $keys);
        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $key
            );
        }
        $this->assertCount(5, array_unique($keys));
    }

    public function test_save_card_sends_the_given_idempotency_key(): void
    {
        $bayarcash = $this->sdk([$this->json(['payment_url' => 'https://pay'])]);

        $bayarcash->saveCard('rcus_1', 'card-key-1');

        $this->assertSame('card-key-1', $this->lastRequest()->getHeaderLine('Idempotency-Key'));
    }

    public function test_get_customer_payments_returns_paginated_transactions(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'data' => [
                    self::chargeRow(['id' => 'trx_od', 'exchange_transaction_id' => 'chg-od']),
                    self::chargeRow([
                        'id' => 'trx_sub',
                        'subscription' => ['type' => 'subscription', 'id' => 'rsub_1', 'plan' => ['id' => 'rpln_1', 'interval' => 'month']],
                    ]),
                ],
                'links' => ['next' => null],
                'meta' => ['current_page' => 2, 'per_page' => 50, 'total' => 52],
            ]),
        ]);

        $result = $bayarcash->getCustomerPayments('rcus_1', 2, 50);

        $uri = (string) $this->lastRequest()->getUri();
        $this->assertStringContainsString('customers/rcus_1/payments', $uri);
        $this->assertStringContainsString('page=2', $uri);
        $this->assertStringContainsString('per_page=50', $uri);
        $this->assertSame(52, $result['meta']['total']);
        $this->assertArrayHasKey('links', $result);

        [$onDemand, $scheduled] = $result['data'];
        $this->assertInstanceOf(TransactionResource::class, $onDemand);
        $this->assertSame('trx_od', $onDemand->id);
        $this->assertSame('chg-od', $onDemand->exchangeTransactionId);
        $this->assertSame('rcus_1', $onDemand->recurringCustomerId);
        $this->assertEquals(Fpx::STATUS_SUCCESS, $onDemand->status);
        $this->assertNull($onDemand->subscription);
        $this->assertNull($onDemand->installment);
        $this->assertSame('rsub_1', $scheduled->subscription['id']);
        $this->assertSame('month', $scheduled->subscription['plan']['interval']);
    }

    /** One row as TransactionResource (api) serves a recurring charge. */
    public static function chargeRow(array $overrides = []): array
    {
        return $overrides + [
            'id' => 'trx_1',
            'created_at' => '2026-10-06 10:15:00',
            'payer_name' => 'Jane Doe',
            'payer_email' => 'jane@example.com',
            'payer_telephone_number' => '60123456789',
            'order_number' => null,
            'currency' => 'MYR',
            'amount' => 49.9,
            'exchange_reference_number' => 'BPGR2026100600000042',
            'exchange_transaction_id' => 'chg-1',
            'payer_bank_name' => null,
            'status' => 3,
            'status_description' => 'Approved',
            'payment_gateway' => ['id' => 12, 'name' => 'Credit Card', 'code' => 'CC'],
            'portal' => 'My Portal',
            'merchant' => ['id' => 'm_1', 'name' => 'Merchant', 'email' => 'm@x.my'],
            'recurring_customer_id' => 'rcus_1',
        ];
    }

    public function test_get_customers_returns_paginated_collection(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'data' => [['id' => 'rcus_1'], ['id' => 'rcus_2']],
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
        $bayarcash = $this->sdk([$this->json(['id' => 'rcus_9', 'card_on_file' => true, 'card_last4' => '4242'])]);

        $customer = $bayarcash->getCustomer('rcus_9');

        $this->assertSame('rcus_9', $customer->id);
        $this->assertTrue($customer->cardOnFile);
        $this->assertSame('4242', $customer->cardLast4);
        $this->assertStringContainsString('customers/rcus_9', (string) $this->lastRequest()->getUri());
    }

    public function test_save_card_returns_the_hosted_page_payload(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['type' => 'save_card', 'status' => 'pending', 'payment_url' => 'https://pay']),
        ]);

        $result = $bayarcash->saveCard('rcus_9');

        $this->assertSame('https://pay', $result['payment_url']);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('customers/rcus_9/save-card', (string) $this->lastRequest()->getUri());
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

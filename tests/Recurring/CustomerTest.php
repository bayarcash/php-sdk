<?php

namespace Webimpian\BayarcashSdk\Tests\Recurring;

use Webimpian\BayarcashSdk\Fpx;
use Webimpian\BayarcashSdk\Resources\CustomerResource;
use Webimpian\BayarcashSdk\Resources\PaymentIntentResource;
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
            $this->json(self::saveCardIntent(), 201),
            $this->json(self::chargeRow(), 201),
        ]);

        // The API answers 422 without the header on the customer, plan and on-demand
        // POSTs. Agreement creates take none (SubscriptionTest, InstallmentTest).
        $bayarcash->createCustomer(['full_name' => 'Jane', 'email' => 'j@e.com', 'phone' => '011']);
        $bayarcash->createPlan(['name' => 'Pro', 'price' => 10, 'interval' => 'month']);
        $bayarcash->saveCard('rcus_1', self::saveCardBody());
        $bayarcash->createOnDemandPayment(['portal_key' => 'pk', 'customer_id' => 'rcus_1', 'amount' => 10]);

        $keys = array_map(function ($entry) {
            return $entry['request']->getHeaderLine('Idempotency-Key');
        }, $this->history);

        $this->assertCount(4, $keys);
        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $key
            );
        }
        $this->assertCount(4, array_unique($keys));
    }

    public function test_save_card_sends_the_given_idempotency_key(): void
    {
        $bayarcash = $this->sdk([$this->json(self::saveCardIntent(), 201)]);

        $bayarcash->saveCard('rcus_1', self::saveCardBody(), 'card-key-1');

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
            // The API's TransactionResource always answers these two.
            'platform_fees' => 0.5,
            'nett_amount' => 49.4,
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

    public function test_save_card_posts_the_body_as_given_and_returns_the_payment_intent(): void
    {
        $bayarcash = $this->sdk([
            $this->json(self::saveCardIntent(['tokenize' => 'validate_only', 'amount' => 1.0]), 201),
        ]);

        $body = self::saveCardBody(['tokenize' => 'validate_only', 'amount' => '1.00', 'checksum' => 'abc123']);
        $intent = $bayarcash->saveCard('rcus_9', $body);

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('customers/rcus_9/save-card', $request->getUri()->getPath());
        $this->assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));
        // Pass-through: exactly what the caller gave, tokenize and checksum included.
        $this->assertSame($body, $this->lastBody());

        $this->assertInstanceOf(PaymentIntentResource::class, $intent);
        $this->assertSame('payment_intent', $intent->type);
        $this->assertSame('pi_save1', $intent->id);
        $this->assertSame('validate_only', $intent->tokenize);
        $this->assertSame('https://console.example/payment-intent/pi_save1', $intent->url);
        $this->assertSame('ORD-1', $intent->orderNumber);
        $this->assertSame(1.0, $intent->amount);
        $this->assertSame(12, $intent->paymentChannel);
    }

    public function test_get_payment_intent_maps_tokenize_and_the_authorized_status(): void
    {
        // GET /payment-intents/{id} answers 201 (the api resource sets it).
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'payment_intent',
                'id' => 'pi_save1',
                'status' => 'authorized',
                'last_attempt' => '2026-10-13 10:00:00',
                'paid_at' => '',
                'order_number' => 'ORD-1',
                'amount' => 1.0,
                'currency' => 'MYR',
                'payer_name' => 'Jane Doe',
                'payer_email' => 'jane@example.com',
                'payer_telephone_number' => '60123456789',
                'payment_gateway' => ['id' => 12, 'name' => 'Credit Card', 'code' => 'CREDIT_CARD'],
                'tokenize' => 'validate_only',
                'attempts' => [],
            ], 201),
        ]);

        $intent = $bayarcash->getPaymentIntent('pi_save1');

        $this->assertInstanceOf(PaymentIntentResource::class, $intent);
        $this->assertSame('authorized', $intent->status);
        $this->assertSame('validate_only', $intent->tokenize);
        $this->assertStringEndsWith('payment-intents/pi_save1', $this->lastRequest()->getUri()->getPath());
    }

    public function test_a_payment_intent_without_tokenize_reads_null(): void
    {
        $intent = new PaymentIntentResource(['type' => 'payment_intent', 'id' => 'pi_1']);

        $this->assertNull($intent->tokenize);
    }

    /** A save-card request body: the POST /payment-intents fields plus tokenize. */
    private static function saveCardBody(array $overrides = []): array
    {
        return array_merge([
            'portal_key' => 'pk_1',
            'order_number' => 'ORD-1',
            'amount' => '2.00',
            'payer_name' => 'Jane Doe',
            'payer_email' => 'jane@example.com',
            'payer_telephone_number' => '60123456789',
            'payment_channel' => [12],
            'tokenize' => 'capture',
        ], $overrides);
    }

    /** The 201 body exactly as the api's PaymentIntentController::prepareOutputData() builds it. */
    private static function saveCardIntent(array $overrides = []): array
    {
        return array_merge([
            'type' => 'payment_intent',
            'id' => 'pi_save1',
            'payer_name' => 'Jane Doe',
            'payer_email' => 'jane@example.com',
            'payer_telephone_number' => '60123456789',
            'order_number' => 'ORD-1',
            'amount' => 2.0,
            'payment_channel' => 12,
            'url' => 'https://console.example/payment-intent/pi_save1',
            'tokenize' => 'capture',
        ], $overrides);
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

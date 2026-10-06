<?php

namespace Webimpian\BayarcashSdk\Tests\Recurring;

use Webimpian\BayarcashSdk\Resources\RecurringIntentResource;
use Webimpian\BayarcashSdk\Resources\SubscriptionResource;
use Webimpian\BayarcashSdk\Resources\TransactionResource;
use Webimpian\BayarcashSdk\Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_create_subscription_returns_recurring_intent_with_url(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'recurring_intent', 'id' => 'rint_1', 'portal_key' => 'pk_test_portal',
                'recurring_type' => 'subscription', 'status' => 'pending',
                'customer_id' => 'rcus_1', 'plan_id' => 'rpln_1',
                'url' => 'https://console/authorize/rint_1',
            ], 201),
        ]);

        $intent = $bayarcash->createSubscription([
            'portal_key' => 'pk_test_portal',
            'customer_id' => 'rcus_1',
            'plan_id' => 'rpln_1',
            'start_date' => '2026-07-01T09:00:00+08:00',
            'callback_url' => 'https://m/cb',
            'return_url' => 'https://m/return',
        ]);

        $this->assertInstanceOf(RecurringIntentResource::class, $intent);
        $this->assertSame('subscription', $intent->recurringType);
        $this->assertSame('pk_test_portal', $intent->portalKey);
        $this->assertSame('https://console/authorize/rint_1', $intent->url);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('subscriptions', (string) $this->lastRequest()->getUri());
        $this->assertSame('rpln_1', $this->lastBody()['plan_id']);
        $this->assertSame('pk_test_portal', $this->lastBody()['portal_key']);
    }

    public function test_create_subscription_requires_a_portal_key(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'rint_1'], 201)]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Required field 'portal_key' is missing");

        $bayarcash->createSubscription(['customer_id' => 'rcus_1', 'plan_id' => 'rpln_1']);
    }

    public function test_create_subscription_accepts_inline_customer(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'rint_1', 'recurring_type' => 'subscription'], 201)]);

        $bayarcash->createSubscription([
            'portal_key' => 'pk_test_portal',
            'customer' => ['full_name' => 'Jo', 'email' => 'jo@e.com', 'phone' => '011'],
            'plan_id' => 'rpln_1',
            'start_date' => '2026-07-01T09:00:00+08:00',
        ]);

        $this->assertSame('jo@e.com', $this->lastBody()['customer']['email']);
    }

    public function test_get_subscriptions_paginated_with_filters(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['data' => [['id' => 'rsub_1']], 'meta' => ['total' => 1]]),
        ]);

        $result = $bayarcash->getSubscriptions(['customer_id' => 'rcus_1', 'status' => 'active']);

        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(SubscriptionResource::class, $result['data'][0]);
        $uri = (string) $this->lastRequest()->getUri();
        $this->assertStringContainsString('customer_id=rcus_1', $uri);
        $this->assertStringContainsString('status=active', $uri);
    }

    public function test_get_subscription_carries_its_plan(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'subscription', 'id' => 'rsub_1', 'status' => 'active', 'plan_id' => 'rpln_1',
                'plan' => ['type' => 'plan', 'id' => 'rpln_1', 'price' => '49.90', 'interval' => 'month'],
            ]),
        ]);

        $subscription = $bayarcash->getSubscription('rsub_1');

        $this->assertInstanceOf(SubscriptionResource::class, $subscription);
        $this->assertSame('month', $subscription->plan['interval']);
        $this->assertFalse(property_exists($subscription, 'charges'));
        $this->assertStringContainsString('subscriptions/rsub_1', (string) $this->lastRequest()->getUri());
    }

    public function test_update_subscription_patches_status(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'rsub_1', 'status' => 'paused'])]);

        $subscription = $bayarcash->updateSubscription('rsub_1', ['status' => 'paused']);

        $this->assertSame('paused', $subscription->status);
        $this->assertSame('PATCH', $this->lastRequest()->getMethod());
        $this->assertSame('paused', $this->lastBody()['status']);
    }

    public function test_lifecycle_helpers_send_correct_status(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['id' => 'rsub_1', 'status' => 'paused']),
            $this->json(['id' => 'rsub_1', 'status' => 'active']),
            $this->json(['id' => 'rsub_1', 'status' => 'canceled']),
            $this->json(['id' => 'rsub_1', 'status' => 'terminated']),
        ]);

        $bayarcash->pauseSubscription('rsub_1');
        $this->assertSame('paused', $this->lastBody()['status']);

        $bayarcash->resumeSubscription('rsub_1');
        $this->assertSame('active', $this->lastBody()['status']);

        $bayarcash->cancelSubscription('rsub_1');
        $this->assertSame('canceled', $this->lastBody()['status']);

        $bayarcash->terminateSubscription('rsub_1');
        $this->assertSame('terminated', $this->lastBody()['status']);
    }

    public function test_get_subscription_payments_returns_paginated_transactions(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'data' => [CustomerTest::chargeRow(['subscription' => ['type' => 'subscription', 'id' => 'rsub_1']])],
                'links' => ['next' => null],
                'meta' => ['current_page' => 1, 'per_page' => 15, 'total' => 1],
            ]),
        ]);

        $result = $bayarcash->getSubscriptionPayments('rsub_1');

        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(TransactionResource::class, $result['data'][0]);
        $this->assertSame('trx_1', $result['data'][0]->id);
        $this->assertSame('rsub_1', $result['data'][0]->subscription['id']);
        $this->assertSame(1, $result['meta']['total']);
        $uri = (string) $this->lastRequest()->getUri();
        $this->assertStringContainsString('subscriptions/rsub_1/payments', $uri);
        $this->assertStringContainsString('page=1', $uri);
        $this->assertStringContainsString('per_page=15', $uri);
    }

    public function test_create_subscription_uses_the_given_idempotency_key(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'rint_1'], 201)]);

        $bayarcash->createSubscription(['portal_key' => 'pk', 'customer_id' => 'rcus_1', 'plan_id' => 'rpln_1'], 'order-42');

        $this->assertSame('order-42', $this->lastRequest()->getHeaderLine('Idempotency-Key'));
    }
}

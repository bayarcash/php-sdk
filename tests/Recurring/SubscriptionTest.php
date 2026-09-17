<?php

namespace Webimpian\BayarcashSdk\Tests\Recurring;

use Webimpian\BayarcashSdk\Resources\ChargeResource;
use Webimpian\BayarcashSdk\Resources\RecurringIntentResource;
use Webimpian\BayarcashSdk\Resources\SubscriptionResource;
use Webimpian\BayarcashSdk\Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_create_subscription_returns_recurring_intent_with_url(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'recurring_intent', 'id' => 'brci_1', 'portal_key' => 'pk_test_portal',
                'recurring_type' => 'subscription', 'status' => 'pending',
                'customer_id' => 'bcus_1', 'plan_id' => 'bpln_1',
                'url' => 'https://console/authorize/brci_1',
            ], 201),
        ]);

        $intent = $bayarcash->createSubscription([
            'portal_key' => 'pk_test_portal',
            'customer_id' => 'bcus_1',
            'plan_id' => 'bpln_1',
            'start_date' => '2026-07-01T09:00:00+08:00',
            'callback_url' => 'https://m/cb',
            'return_url' => 'https://m/return',
        ]);

        $this->assertInstanceOf(RecurringIntentResource::class, $intent);
        $this->assertSame('subscription', $intent->recurringType);
        $this->assertSame('pk_test_portal', $intent->portalKey);
        $this->assertSame('https://console/authorize/brci_1', $intent->url);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('subscriptions', (string) $this->lastRequest()->getUri());
        $this->assertSame('bpln_1', $this->lastBody()['plan_id']);
        $this->assertSame('pk_test_portal', $this->lastBody()['portal_key']);
    }

    public function test_create_subscription_requires_a_portal_key(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'brci_1'], 201)]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Required field 'portal_key' is missing");

        $bayarcash->createSubscription(['customer_id' => 'bcus_1', 'plan_id' => 'bpln_1']);
    }

    public function test_create_subscription_accepts_inline_customer(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'brci_1', 'recurring_type' => 'subscription'], 201)]);

        $bayarcash->createSubscription([
            'portal_key' => 'pk_test_portal',
            'customer' => ['full_name' => 'Jo', 'email' => 'jo@e.com', 'phone' => '011'],
            'plan_id' => 'bpln_1',
            'start_date' => '2026-07-01T09:00:00+08:00',
        ]);

        $this->assertSame('jo@e.com', $this->lastBody()['customer']['email']);
    }

    public function test_get_subscriptions_paginated_with_filters(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['data' => [['id' => 'bsub_1']], 'meta' => ['total' => 1]]),
        ]);

        $result = $bayarcash->getSubscriptions(['customer_id' => 'bcus_1', 'status' => 'active']);

        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(SubscriptionResource::class, $result['data'][0]);
        $uri = (string) $this->lastRequest()->getUri();
        $this->assertStringContainsString('customer_id=bcus_1', $uri);
        $this->assertStringContainsString('status=active', $uri);
    }

    public function test_get_subscription_transforms_nested_charges(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'subscription', 'id' => 'bsub_1', 'status' => 'active',
                'charges' => [
                    ['type' => 'payment', 'id' => 'ch_1', 'amount' => '49.90', 'status' => 'succeeded'],
                    ['type' => 'payment', 'id' => 'ch_2', 'amount' => '49.90', 'status' => 'failed'],
                ],
            ]),
        ]);

        $subscription = $bayarcash->getSubscription('bsub_1');

        $this->assertInstanceOf(SubscriptionResource::class, $subscription);
        $this->assertCount(2, $subscription->charges);
        $this->assertInstanceOf(ChargeResource::class, $subscription->charges[0]);
        $this->assertSame('ch_1', $subscription->charges[0]->id);
        $this->assertStringContainsString('subscriptions/bsub_1', (string) $this->lastRequest()->getUri());
    }

    public function test_update_subscription_patches_status(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'bsub_1', 'status' => 'paused'])]);

        $subscription = $bayarcash->updateSubscription('bsub_1', ['status' => 'paused']);

        $this->assertSame('paused', $subscription->status);
        $this->assertSame('PATCH', $this->lastRequest()->getMethod());
        $this->assertSame('paused', $this->lastBody()['status']);
    }

    public function test_lifecycle_helpers_send_correct_status(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['id' => 'bsub_1', 'status' => 'paused']),
            $this->json(['id' => 'bsub_1', 'status' => 'active']),
            $this->json(['id' => 'bsub_1', 'status' => 'canceled']),
            $this->json(['id' => 'bsub_1', 'status' => 'terminated']),
        ]);

        $bayarcash->pauseSubscription('bsub_1');
        $this->assertSame('paused', $this->lastBody()['status']);

        $bayarcash->resumeSubscription('bsub_1');
        $this->assertSame('active', $this->lastBody()['status']);

        $bayarcash->cancelSubscription('bsub_1');
        $this->assertSame('canceled', $this->lastBody()['status']);

        $bayarcash->terminateSubscription('bsub_1');
        $this->assertSame('terminated', $this->lastBody()['status']);
    }

    public function test_get_subscription_payments_returns_charge_collection(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['data' => [['id' => 'ch_1', 'amount' => '49.90']]]),
        ]);

        $charges = $bayarcash->getSubscriptionPayments('bsub_1');

        $this->assertCount(1, $charges);
        $this->assertInstanceOf(ChargeResource::class, $charges[0]);
        $this->assertStringContainsString('subscriptions/bsub_1/payments', (string) $this->lastRequest()->getUri());
    }
}

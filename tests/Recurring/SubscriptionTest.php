<?php

namespace Bayarcash\Tests\Recurring;

use Bayarcash\Resources\SubscriptionResource;
use Bayarcash\Resources\TransactionResource;
use Bayarcash\Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_create_subscription_returns_the_pending_subscription_with_its_card_page(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'subscription', 'id' => 'rsub_1', 'portal_key' => 'pk_test_portal',
                'status' => 'pending', 'customer_id' => 'rcus_1', 'plan_id' => 'rpln_1',
                'card' => null, 'redirect_url' => 'https://boost.example/card/abc',
            ], 201),
        ]);

        $subscription = $bayarcash->createSubscription([
            'portal_key' => 'pk_test_portal',
            'customer_id' => 'rcus_1',
            'plan_id' => 'rpln_1',
            'start_date' => '2026-07-01T09:00:00+08:00',
            'callback_url' => 'https://m/cb',
            'return_url' => 'https://m/return',
        ]);

        $this->assertInstanceOf(SubscriptionResource::class, $subscription);
        $this->assertSame('rsub_1', $subscription->id);
        $this->assertSame('pending', $subscription->status);
        $this->assertSame('pk_test_portal', $subscription->portalKey);
        $this->assertSame('https://boost.example/card/abc', $subscription->redirectUrl);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('subscriptions', (string) $this->lastRequest()->getUri());
        $this->assertSame('rpln_1', $this->lastBody()['plan_id']);
        $this->assertSame('pk_test_portal', $this->lastBody()['portal_key']);
    }

    public function test_create_subscription_requires_a_portal_key(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'rsub_1'], 201)]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Required field 'portal_key' is missing");

        $bayarcash->createSubscription(['customer_id' => 'rcus_1', 'plan_id' => 'rpln_1']);
    }

    public function test_create_subscription_accepts_inline_customer(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'rsub_1', 'status' => 'pending'], 201)]);

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

    public function test_get_subscription_carries_the_card_it_bills(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['type' => 'subscription', 'id' => 'rsub_1', 'status' => 'active',
                'card' => ['last4' => '1118', 'brand' => null, 'country' => 'MY', 'exp_month' => 12, 'exp_year' => 2030]]),
            $this->json(['type' => 'subscription', 'id' => 'rsub_2', 'status' => 'pending', 'card' => null]),
        ]);

        $subscription = $bayarcash->getSubscription('rsub_1');
        $this->assertSame(['last4' => '1118', 'brand' => null, 'country' => 'MY', 'exp_month' => 12, 'exp_year' => 2030], $subscription->card);
        $this->assertNull($subscription->redirectUrl); // only a create answers the card page
        $this->assertNull($bayarcash->getSubscription('rsub_2')->card);
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

    /*
     * The API takes no Idempotency-Key on an agreement create: every create is a new
     * agreement, and the payer still has to authorize it on Boost's card page.
     */
    public function test_create_subscription_sends_no_idempotency_key(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'rsub_1', 'status' => 'pending'], 201)]);

        $bayarcash->createSubscription(['portal_key' => 'pk', 'customer_id' => 'rcus_1', 'plan_id' => 'rpln_1']);

        $this->assertFalse($this->lastRequest()->hasHeader('Idempotency-Key'));
    }

    /*
     * The merchant's `reference` goes in the body as given (the API validates it), and the
     * agreement answers it back.
     */
    public function test_reference_is_sent_in_the_body(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['id' => 'rsub_1', 'reference' => 'SUB-0001', 'status' => 'pending'], 201),
        ]);

        $subscription = $bayarcash->createSubscription([
            'portal_key' => 'pk', 'reference' => 'SUB-0001', 'customer_id' => 'rcus_1', 'plan_id' => 'rpln_1',
        ]);

        $this->assertSame('SUB-0001', $this->lastBody()['reference']);
        $this->assertContains('reference', array_keys(get_class_vars(SubscriptionResource::class)));
        $this->assertSame('SUB-0001', $subscription->reference);
    }

    /*
     * A repeat with the same reference and body answers the stored agreement: 200 once the
     * create is settled, 202 while it is still pending. Both are answers, not errors.
     */
    public function test_a_repeat_answer_is_the_agreement_not_an_error(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['id' => 'rsub_1', 'reference' => 'SUB-0001', 'status' => 'pending'], 202),
            $this->json(['id' => 'rsub_1', 'reference' => 'SUB-0001', 'status' => 'active'], 200),
        ]);
        $data = ['portal_key' => 'pk', 'reference' => 'SUB-0001', 'customer_id' => 'rcus_1', 'plan_id' => 'rpln_1'];

        $this->assertSame('pending', $bayarcash->createSubscription($data)->status);
        $this->assertSame('active', $bayarcash->createSubscription($data)->status);
    }
}

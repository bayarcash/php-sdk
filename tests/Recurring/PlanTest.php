<?php

namespace Webimpian\BayarcashSdk\Tests\Recurring;

use Webimpian\BayarcashSdk\Resources\PlanResource;
use Webimpian\BayarcashSdk\Tests\TestCase;

class PlanTest extends TestCase
{
    public function test_create_plan(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'plan', 'id' => 'bpln_1', 'portal_key' => 'pk_test_portal',
                'name' => 'Pro', 'description' => 'Pro plan',
                'price' => '49.90', 'currency' => 'MYR', 'interval' => 'P1M',
            ], 201),
        ]);

        $plan = $bayarcash->createPlan([
            'portal_key' => 'pk_test_portal',
            'name' => 'Pro', 'price' => 49.90, 'currency' => 'MYR', 'interval' => 'P1M',
        ]);

        $this->assertInstanceOf(PlanResource::class, $plan);
        $this->assertSame('bpln_1', $plan->id);
        $this->assertSame('49.90', $plan->price);
        $this->assertSame('P1M', $plan->interval);
        $this->assertSame('pk_test_portal', $plan->portalKey);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('plans', (string) $this->lastRequest()->getUri());
        $this->assertSame('Pro', $this->lastBody()['name']);
        $this->assertSame('pk_test_portal', $this->lastBody()['portal_key']);
    }

    public function test_create_plan_requires_a_portal_key(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'bpln_1'], 201)]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Required field 'portal_key' is missing");

        $bayarcash->createPlan(['name' => 'Pro', 'price' => 49.90, 'interval' => 'P1M']);
    }

    public function test_get_plans_paginated(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['data' => [['id' => 'bpln_1']], 'meta' => ['total' => 1]]),
        ]);

        $result = $bayarcash->getPlans(['search' => 'pro']);

        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(PlanResource::class, $result['data'][0]);
        $this->assertStringContainsString('search=pro', (string) $this->lastRequest()->getUri());
    }

    public function test_get_plan(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'bpln_5', 'name' => 'Basic'])]);

        $plan = $bayarcash->getPlan('bpln_5');

        $this->assertSame('Basic', $plan->name);
        $this->assertStringContainsString('plans/bpln_5', (string) $this->lastRequest()->getUri());
    }

    public function test_update_plan_uses_patch(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'bpln_5', 'price' => '59.90'])]);

        $plan = $bayarcash->updatePlan('bpln_5', ['price' => 59.90]);

        $this->assertSame('59.90', $plan->price);
        $this->assertSame('PATCH', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('plans/bpln_5', (string) $this->lastRequest()->getUri());
        $this->assertSame(59.90, $this->lastBody()['price']);
    }
}

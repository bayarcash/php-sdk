<?php

namespace Bayarcash\Tests\Recurring;

use Bayarcash\Resources\PlanResource;
use Bayarcash\Tests\TestCase;

class PlanTest extends TestCase
{
    public function test_create_plan(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'plan', 'id' => 'rpln_1',
                'name' => 'Pro', 'description' => 'Pro plan',
                'price' => '49.90', 'currency' => 'MYR', 'interval' => 'month',
            ], 201),
        ]);

        $plan = $bayarcash->createPlan([
            'name' => 'Pro', 'price' => 49.90, 'currency' => 'MYR', 'interval' => 'month',
        ]);

        $this->assertInstanceOf(PlanResource::class, $plan);
        $this->assertSame('rpln_1', $plan->id);
        $this->assertSame('49.90', $plan->price);
        $this->assertSame('month', $plan->interval);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('plans', (string) $this->lastRequest()->getUri());
        $this->assertSame('Pro', $this->lastBody()['name']);
        $this->assertArrayNotHasKey('portal_key', $this->lastBody());
    }

    public function test_get_plans_paginated(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['data' => [['id' => 'rpln_1']], 'meta' => ['total' => 1]]),
        ]);

        $result = $bayarcash->getPlans(['search' => 'pro']);

        $this->assertCount(1, $result['data']);
        $this->assertInstanceOf(PlanResource::class, $result['data'][0]);
        $this->assertStringContainsString('search=pro', (string) $this->lastRequest()->getUri());
    }

    public function test_get_plan(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'rpln_5', 'name' => 'Basic'])]);

        $plan = $bayarcash->getPlan('rpln_5');

        $this->assertSame('Basic', $plan->name);
        $this->assertStringContainsString('plans/rpln_5', (string) $this->lastRequest()->getUri());
    }

    public function test_update_plan_uses_patch(): void
    {
        $bayarcash = $this->sdk([$this->json(['id' => 'rpln_5', 'price' => '59.90'])]);

        $plan = $bayarcash->updatePlan('rpln_5', ['price' => 59.90]);

        $this->assertSame('59.90', $plan->price);
        $this->assertSame('PATCH', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('plans/rpln_5', (string) $this->lastRequest()->getUri());
        $this->assertSame(59.90, $this->lastBody()['price']);
    }
}

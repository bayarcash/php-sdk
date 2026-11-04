<?php

namespace Bayarcash\Tests\Recurring;

use Bayarcash\Exceptions\ValidationException;
use Bayarcash\Resources\PlanResource;
use Bayarcash\Tests\TestCase;

class PlanTest extends TestCase
{
    public function test_create_plan(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'type' => 'plan', 'id' => 'rpln_1', 'code' => 'PRO-MONTHLY',
                'name' => 'Pro', 'description' => 'Pro plan',
                'price' => '49.90', 'currency' => 'MYR', 'interval' => 'month',
            ], 201),
        ]);

        $plan = $bayarcash->createPlan([
            'code' => 'PRO-MONTHLY', 'name' => 'Pro', 'price' => 49.90, 'currency' => 'MYR', 'interval' => 'month',
        ]);

        $this->assertInstanceOf(PlanResource::class, $plan);
        $this->assertSame('rpln_1', $plan->id);
        $this->assertContains('code', array_keys(get_class_vars(PlanResource::class)));
        $this->assertSame('PRO-MONTHLY', $plan->code);
        $this->assertSame('49.90', $plan->price);
        $this->assertSame('month', $plan->interval);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('plans', (string) $this->lastRequest()->getUri());
        $this->assertSame('Pro', $this->lastBody()['name']);
        $this->assertSame('PRO-MONTHLY', $this->lastBody()['code']);
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

    /* The merchant's plan code is a list filter, passed through as given. */
    public function test_get_plans_filters_by_code(): void
    {
        $bayarcash = $this->sdk([$this->json(['data' => [['id' => 'rpln_1', 'code' => 'PRO-MONTHLY']], 'meta' => []])]);

        $result = $bayarcash->getPlans(['code' => 'PRO-MONTHLY']);

        $this->assertSame('PRO-MONTHLY', $result['data'][0]->code);
        $this->assertStringContainsString('code=PRO-MONTHLY', (string) $this->lastRequest()->getUri());
    }

    /* A plan code the merchant already holds is refused, not answered with the plan on file. */
    public function test_a_repeated_plan_code_is_a_validation_exception(): void
    {
        $bayarcash = $this->sdk([$this->json(['error' => [
            'code' => 'plan_code_already_used',
            'message' => 'The plan code PRO-MONTHLY already exists.',
        ]], 422)]);

        try {
            $bayarcash->createPlan(['code' => 'PRO-MONTHLY', 'name' => 'Pro', 'price' => 49.90, 'interval' => 'month']);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame('plan_code_already_used', $e->errors()['error']['code']);
            $this->assertSame('The plan code PRO-MONTHLY already exists.', $e->errors()['error']['message']);
        }
    }
}

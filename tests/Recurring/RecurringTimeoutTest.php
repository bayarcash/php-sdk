<?php

namespace Bayarcash\Tests\Recurring;

use Bayarcash\Actions\RecurringPayments;
use Bayarcash\Tests\TestCase;

/**
 * Every recurring call waits 90 seconds (or setTimeout() when larger): the API can take
 * that long on a create. The one-off endpoints keep the instance timeout (30 by default).
 */
class RecurringTimeoutTest extends TestCase
{
    private function lastTimeout(): float
    {
        $entry = end($this->history);

        return $entry['options']['timeout'];
    }

    public function test_a_recurring_create_waits_90_seconds(): void
    {
        $sdk = $this->sdk([$this->json(['reference' => 'INV-1', 'status' => 'pending'], 202)]);

        $sdk->createOnDemandPayment(['portal_key' => 'pk', 'reference' => 'INV-1', 'customer_id' => 'rcus_1', 'amount' => 10]);

        $this->assertSame(90.0, $this->lastTimeout());
    }

    public function test_a_one_off_call_keeps_the_default_30_seconds(): void
    {
        $sdk = $this->sdk([$this->json(['data' => [], 'meta' => []])]);

        $sdk->getAllTransactions(['order_number' => 'INV-1']);

        $this->assertSame(30.0, $this->lastTimeout());
    }

    public function test_a_larger_set_timeout_applies_to_recurring_calls_too(): void
    {
        $sdk = $this->sdk([$this->json(['id' => 'rsub_1']), $this->json(['data' => [], 'meta' => []])]);
        $sdk->setTimeout(120);

        $sdk->getSubscription('rsub_1');
        $this->assertSame(120.0, $this->lastTimeout());

        $sdk->getAllTransactions();
        $this->assertSame(120.0, $this->lastTimeout());
    }

    public function test_a_smaller_set_timeout_does_not_shorten_recurring_calls(): void
    {
        $sdk = $this->sdk([$this->json(['id' => 'rsub_1']), $this->json(['data' => [], 'meta' => []])]);
        $sdk->setTimeout(60);

        $sdk->getSubscription('rsub_1');
        $this->assertSame(90.0, $this->lastTimeout());

        $sdk->getAllTransactions();
        $this->assertSame(60.0, $this->lastTimeout());
    }

    public function test_every_recurring_method_waits_90_seconds(): void
    {
        $methods = array_filter(
            (new \ReflectionClass(RecurringPayments::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
            function (\ReflectionMethod $method) {
                return ! $method->isStatic();
            }
        );
        $this->assertGreaterThan(20, count($methods));

        foreach ($methods as $method) {
            $sdk = $this->sdk([$this->json(['data' => [], 'meta' => [], 'links' => []])]);
            $args = array_map(function (\ReflectionParameter $parameter) {
                $type = $parameter->getType() ? $parameter->getType()->getName() : 'string';

                return $type === 'array' ? ['portal_key' => 'pk'] : ($type === 'int' ? 1 : 'x');
            }, $method->getParameters());

            call_user_func_array([$sdk, $method->getName()], $args);

            $this->assertCount(1, $this->history, $method->getName() . ' sends one request');
            $this->assertSame(90.0, $this->lastTimeout(), $method->getName() . ' waits 90 seconds');
        }
    }

    public function test_get_all_transactions_passes_recurring_type(): void
    {
        $sdk = $this->sdk([$this->json(['data' => [], 'meta' => []])]);

        $sdk->getAllTransactions(['order_number' => 'INV-1', 'recurring_type' => 'on_demand', 'other' => 'dropped']);

        $this->assertSame('order_number=INV-1&recurring_type=on_demand', $this->lastRequest()->getUri()->getQuery());
    }
}

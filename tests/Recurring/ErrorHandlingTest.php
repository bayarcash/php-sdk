<?php

namespace Webimpian\BayarcashSdk\Tests\Recurring;

use Webimpian\BayarcashSdk\Exceptions\FailedActionException;
use Webimpian\BayarcashSdk\Exceptions\NotFoundException;
use Webimpian\BayarcashSdk\Exceptions\ValidationException;
use Webimpian\BayarcashSdk\Tests\TestCase;

/**
 * Error-surfacing behaviour for the v3 recurring endpoints. A 4xx from the API
 * (e.g. "Customer already subscribed to plan") must be raised as a clean,
 * terminal exception that carries the API's own message — never a stdClass
 * fed into Exception's string constructor (which throws a TypeError) and never
 * a retry.
 */
class ErrorHandlingTest extends TestCase
{
    public function test_already_subscribed_400_throws_failed_action_with_api_message(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'timestamp' => '2026-06-26T06:33:40.976730791+08:00',
                'status' => 400,
                'error' => 'Bad Request',
                'message' => 'Customer 33b95dc4-f003-4f8a-8d25-391ec09898c2 is already subscribed to Laravel8 Module Test',
                'path' => '/api/v1/subscriptions',
            ], 400),
        ]);

        try {
            $bayarcash->createSubscription([
                'portal_key' => 'pk_test_portal',
                'customer_id' => 'bcus_1',
                'plan_id' => 'bpln_1',
                'start_date' => '2026-07-01T09:00:00+08:00',
            ]);
            $this->fail('Expected FailedActionException was not thrown.');
        } catch (FailedActionException $e) {
            // The human-readable API message must reach getMessage() so the
            // consumer can display it directly (no stdClass TypeError).
            $this->assertStringContainsString('is already subscribed to', $e->getMessage());
            $this->assertSame(400, $e->getCode());
            $this->assertSame('Bad Request', $e->errors()['error'] ?? null);
            $this->assertSame(
                'Customer 33b95dc4-f003-4f8a-8d25-391ec09898c2 is already subscribed to Laravel8 Module Test',
                $e->errors()['message'] ?? null
            );
        }
    }

    public function test_400_with_plain_string_body_still_surfaces_a_message(): void
    {
        $this->mock = null;
        $bayarcash = $this->sdk([
            new \GuzzleHttp\Psr7\Response(400, ['Content-Type' => 'text/plain'], 'Something went wrong'),
        ]);

        $this->expectException(FailedActionException::class);
        $this->expectExceptionMessage('Something went wrong');

        $bayarcash->createSubscription([
            'portal_key' => 'pk_test_portal', 'customer_id' => 'bcus_1', 'plan_id' => 'bpln_1',
        ]);
    }

    public function test_422_still_throws_validation_exception(): void
    {
        $bayarcash = $this->sdk([
            $this->json(['message' => 'The given data was invalid.', 'errors' => ['email' => ['required']]], 422),
        ]);

        $this->expectException(ValidationException::class);

        $bayarcash->createCustomer(['portal_key' => 'pk_test_portal', 'full_name' => 'x']);
    }

    public function test_rejected_portal_key_surfaces_as_a_validation_exception(): void
    {
        $bayarcash = $this->sdk([
            $this->json([
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'The given data was invalid.',
                    'fields' => ['portal_key' => ['The selected portal key is invalid.']],
                ],
            ], 422),
        ]);

        try {
            $bayarcash->createCustomer([
                'portal_key' => 'pk_wrong', 'full_name' => 'x', 'email' => 'x@y.com', 'phone' => '1',
            ]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['The selected portal key is invalid.'],
                $e->errors()['error']['fields']['portal_key'] ?? null
            );
        }
    }

    public function test_404_still_throws_not_found_exception(): void
    {
        $bayarcash = $this->sdk([$this->json(['message' => 'Not found'], 404)]);

        $this->expectException(NotFoundException::class);

        $bayarcash->getSubscription('bsub_missing');
    }
}

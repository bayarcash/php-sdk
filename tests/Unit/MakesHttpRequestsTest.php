<?php

namespace Bayarcash\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Bayarcash\Bayarcash;
use Bayarcash\Exceptions\FailedActionException;
use Bayarcash\Exceptions\NotFoundException;
use Bayarcash\Exceptions\RateLimitExceededException;
use Bayarcash\Exceptions\ValidationException;

class MakesHttpRequestsTest extends TestCase
{
    /**
     * Build a Bayarcash instance whose Guzzle client returns the given queued responses.
     */
    private function sdkWithResponses(array $responses): Bayarcash
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);

        return (new Bayarcash('test-token'))->setToken('test-token', $client);
    }

    public function test_successful_response_is_decoded_to_array(): void
    {
        $sdk = $this->sdkWithResponses([
            new Response(200, [], json_encode(['foo' => 'bar'])),
        ]);

        $this->assertSame(['foo' => 'bar'], $sdk->get('anything'));
    }

    public function test_422_throws_validation_exception_with_errors(): void
    {
        // Real API 422 shape: {"error": {"<field>": ["<msg>", ...]}}
        $sdk = $this->sdkWithResponses([
            new Response(422, [], json_encode(['error' => ['amount' => ['The amount field is required.']]])),
        ]);

        try {
            $sdk->get('anything');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('error', $e->errors());
        }
    }

    public function test_404_throws_not_found_exception(): void
    {
        $sdk = $this->sdkWithResponses([new Response(404, [], '')]);

        $this->expectException(NotFoundException::class);
        $sdk->get('anything');
    }

    /**
     * Regression test: a 400 previously passed a stdClass to Exception::__construct()
     * and blew up with a TypeError instead of a FailedActionException.
     */
    public function test_400_throws_failed_action_exception_with_string_message(): void
    {
        $sdk = $this->sdkWithResponses([
            new Response(400, [], json_encode(['message' => 'Bad request happened'])),
        ]);

        try {
            $sdk->get('anything');
            $this->fail('Expected FailedActionException');
        } catch (FailedActionException $e) {
            $this->assertSame('Bad request happened', $e->getMessage());
        }
    }

    public function test_400_extracts_error_key_message(): void
    {
        // Real API exception-path shape uses an "error" key, not "message".
        $sdk = $this->sdkWithResponses([
            new Response(400, [], json_encode(['error' => 'Something went wrong'])),
        ]);

        try {
            $sdk->get('anything');
            $this->fail('Expected FailedActionException');
        } catch (FailedActionException $e) {
            $this->assertSame('Something went wrong', $e->getMessage());
        }
    }

    public function test_400_with_non_json_body_still_throws_failed_action_exception(): void
    {
        $sdk = $this->sdkWithResponses([new Response(400, [], 'plain text error')]);

        $this->expectException(FailedActionException::class);
        $sdk->get('anything');
    }

    public function test_429_throws_rate_limit_exception_with_reset(): void
    {
        $sdk = $this->sdkWithResponses([
            new Response(429, ['x-ratelimit-reset' => '1700000000'], ''),
        ]);

        try {
            $sdk->get('anything');
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
            $this->assertSame(1700000000, $e->rateLimitResetsAt);
        }
    }

    public function test_api_version_getter_defaults_to_v2_and_reflects_setter(): void
    {
        $sdk = new Bayarcash('test-token');
        $this->assertSame('v2', $sdk->getApiVersion());

        $sdk->setApiVersion('v3');
        $this->assertSame('v3', $sdk->getApiVersion());
    }

    public function test_v3_only_method_throws_on_v2(): void
    {
        $sdk = new Bayarcash('test-token');

        $this->expectException(\Exception::class);
        $sdk->getAllTransactions();
    }
}

<?php

namespace Webimpian\BayarcashSdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Webimpian\BayarcashSdk\Bayarcash;

class TestCase extends BaseTestCase
{
    /** @var array */
    protected $history = [];

    /** @var \GuzzleHttp\Handler\MockHandler */
    protected $mock;

    /**
     * Build a v3 Bayarcash SDK whose Guzzle client is backed by a MockHandler.
     *
     * @param  \GuzzleHttp\Psr7\Response[]  $responses  Queued responses, FIFO.
     */
    protected function sdk(array $responses): Bayarcash
    {
        $this->mock = new MockHandler($responses);
        $stack = HandlerStack::create($this->mock);
        $this->history = [];
        $stack->push(Middleware::history($this->history));

        $client = new Client([
            'handler' => $stack,
            'base_uri' => 'https://api.console.bayarcash-sandbox.com/v3/',
            'http_errors' => false,
        ]);

        $bayarcash = new Bayarcash('test-token');
        $bayarcash->setApiVersion('v3');     // sets version (reinitialises real client)
        $bayarcash->setToken('test-token', $client); // swap in the mock client, keep v3

        return $bayarcash;
    }

    /** The most recent captured outbound request. */
    protected function lastRequest(): Request
    {
        $entry = end($this->history);

        return $entry['request'];
    }

    /** Decoded JSON body of the most recent request. */
    protected function lastBody(): array
    {
        return json_decode((string) $this->lastRequest()->getBody(), true) ?: [];
    }

    /** Build a JSON Response for the mock queue. */
    protected function json(array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
    }
}

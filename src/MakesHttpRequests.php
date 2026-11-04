<?php

namespace Bayarcash;

use Bayarcash\Exceptions\FailedActionException;
use Bayarcash\Exceptions\NotFoundException;
use Bayarcash\Exceptions\RateLimitExceededException;
use Bayarcash\Exceptions\TimeoutException;
use Bayarcash\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;

trait MakesHttpRequests
{
    /**
     * Make a GET request to Bayarcash servers and return the response.
     *
     * @param  string  $uri
     * @return mixed
     */
    public function get($uri, array $headers = [])
    {
        return $this->request('GET', $uri, [], $headers);
    }

    /**
     * Make a POST request to Bayarcash servers and return the response.
     *
     * @param  string  $uri
     * @return mixed
     */
    public function post($uri, array $payload = [], array $headers = [])
    {
        return $this->request('POST', $uri, $payload, $headers);
    }

    /**
     * Make a PUT request to Bayarcash servers and return the response.
     *
     * @param  string  $uri
     * @return mixed
     */
    public function put($uri, array $payload = [], array $headers = [])
    {
        return $this->request('PUT', $uri, $payload, $headers);
    }

    /**
     * Make a PATCH request to Bayarcash servers and return the response.
     *
     * @param  string  $uri
     * @return mixed
     */
    public function patch($uri, array $payload = [], array $headers = [])
    {
        return $this->request('PATCH', $uri, $payload, $headers);
    }

    /**
     * Make a DELETE request to Bayarcash servers and return the response.
     *
     * @param  string  $uri
     * @return mixed
     */
    public function delete($uri, array $payload = [], array $headers = [])
    {
        return $this->request('DELETE', $uri, $payload, $headers);
    }

    /**
     * Make request to Bayarcash servers and return the response.
     *
     * @param $verb
     * @param $uri
     * @param array $payload
     * @param array $headers
     * @param float|null $timeout  Seconds for this request; null uses setTimeout().
     * @return mixed|string
     */
    protected function request($verb, $uri, array $payload = [], array $headers = [], $timeout = null)
    {
        // Per request so setTimeout() also applies to a client passed to setToken()/useSandbox().
        $options = ['timeout' => (float) ($timeout === null ? $this->timeout : $timeout)];

        if (isset($payload['json'])) {
            $options['json'] = $payload['json'];
        } elseif (! empty($payload)) {
            $options['form_params'] = $payload;
        }

        if (! empty($headers)) {
            $options['headers'] = $headers;
        }

        $response = $this->guzzle->request($verb, $uri, $options);

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode > 299) {
            return $this->handleRequestError($response);
        }

        $responseBody = (string) $response->getBody();

        return json_decode($responseBody, true) ?: $responseBody;
    }

    /**
     * Handle the request error.
     *
     * @param ResponseInterface $response
     * @throws FailedActionException
     * @throws NotFoundException
     * @throws RateLimitExceededException
     * @throws ValidationException
     */
    protected function handleRequestError(ResponseInterface $response)
    {
        if ($response->getStatusCode() == 422) {
            throw new ValidationException(json_decode((string) $response->getBody(), true));
        }

        if ($response->getStatusCode() == 404) {
            throw new NotFoundException();
        }

        if ($response->getStatusCode() == 400) {
            // A 400 is a terminal validation/business rejection (e.g. "Customer
            // already subscribed to plan") — surface the API's message and do
            // NOT retry. Decode as an array so the message reaches getMessage()
            // instead of feeding a stdClass into Exception's string constructor.
            throw new FailedActionException((string) $response->getBody(), 400);
        }

        if ($response->getStatusCode() == 429) {
            throw new RateLimitExceededException(
                $response->hasHeader('x-ratelimit-reset')
                    ? (int) $response->getHeader('x-ratelimit-reset')[0]
                    : null
            );
        }

        throw new \Exception((string) $response->getBody());
    }

    /**
     * Retry the callback or fail after x seconds.
     *
     * @param $timeout
     * @param $callback
     * @param int $sleep
     * @return mixed
     * @throws TimeoutException
     */
    public function retry($timeout, $callback, $sleep = 5)
    {
        $start = time();

        beginning:

        if ($output = $callback()) {
            return $output;
        }

        if (time() - $start < $timeout) {
            sleep($sleep);

            goto beginning;
        }

        if ($output === null || $output === false) {
            $output = [];
        }

        if (! is_array($output)) {
            $output = [$output];
        }

        throw new TimeoutException($output);
    }
}

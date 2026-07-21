<?php

namespace Webimpian\BayarcashSdk;

use Webimpian\BayarcashSdk\Exceptions\FailedActionException;
use Webimpian\BayarcashSdk\Exceptions\NotFoundException;
use Webimpian\BayarcashSdk\Exceptions\RateLimitExceededException;
use Webimpian\BayarcashSdk\Exceptions\TimeoutException;
use Webimpian\BayarcashSdk\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;

trait MakesHttpRequests
{
    /**
     * Make a GET request to Bayarcash servers and return the response.
     *
     * @param  string  $uri
     * @return mixed
     */
    public function get($uri)
    {
        return $this->request('GET', $uri) ;
    }

    /**
     * Make a POST request to Bayarcash servers and return the response.
     *
     * @param  string  $uri
     * @return mixed
     */
    public function post($uri, array $payload = [])
    {
        return $this->request('POST', $uri, $payload) ;
    }


    /**
     * Make a PUT request to Bayarcash servers and return the response.
     *
     * @param  string  $uri
     * @return mixed
     */
    public function put($uri, array $payload = [])
    {
        return $this->request('PUT', $uri, $payload) ;
    }

    /**
     * Make a DELETE request to Bayarcash servers and return the response.
     *
     * @param  string  $uri
     * @return mixed
     */
    public function delete($uri, array $payload = [])
    {
        return $this->request('DELETE', $uri, $payload) ;
    }

    /**
     * Make request to Bayarcash servers and return the response.
     *
     * @param $verb
     * @param $uri
     * @param array $payload
     * @return mixed|string
     */
    protected function request($verb, $uri, array $payload = [])
    {
        if (isset($payload['json'])) {
            $payload = ['json' => $payload['json']];
        } else {
            $payload = empty($payload) ? [] : ['form_params' => $payload];
        }

        $response = $this->guzzle->request($verb, $uri, $payload);

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
            $rawBody = (string) $response->getBody();
            $decoded = json_decode($rawBody, true);

            $message = $rawBody;
            if (is_array($decoded)) {
                $message = $decoded['message'] ?? $decoded['error'] ?? $rawBody;
                if (is_array($message)) {
                    $message = json_encode($message);
                }
            }

            throw new FailedActionException($message);
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

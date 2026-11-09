<?php

namespace Bayarcash\Exceptions;

/**
 * Raised for a non-retryable 4xx "the action failed" response (e.g. HTTP 400
 * "Customer already subscribed to plan"). Carries the API's own human-readable
 * message on getMessage() and the full decoded body via errors(), so consumers
 * can display the reason and decide NOT to retry.
 */
class FailedActionException extends \Exception
{
    /**
     * The decoded error body returned by the API.
     *
     * @var array
     */
    public $errors = [];

    /**
     * @param  mixed  $response  Decoded array, stdClass, JSON string or plain string.
     */
    public function __construct($response = null, int $code = 0, ?\Throwable $previous = null)
    {
        $this->errors = $this->normalize($response);

        parent::__construct($this->resolveMessage($this->errors, $response), $code, $previous);
    }

    /**
     * The decoded error body.
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Coerce whatever shape the API/transport handed us into an array.
     *
     * @param  mixed  $response
     * @return array
     */
    private function normalize($response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response)) {
            return (array) $response;
        }

        if (is_string($response)) {
            $decoded = json_decode($response, true);

            return is_array($decoded) ? $decoded : ['message' => $response];
        }

        return [];
    }

    /**
     * Prefer the API's `message`, then `error`, then a non-empty raw string,
     * falling back to a generic line so getMessage() is always meaningful.
     *
     * @param  mixed  $original
     */
    private function resolveMessage(array $errors, $original): string
    {
        if (! empty($errors['message']) && is_string($errors['message'])) {
            return $errors['message'];
        }

        if (! empty($errors['error']) && is_string($errors['error'])) {
            return $errors['error'];
        }

        if (is_string($original) && $original !== '' && json_decode($original) === null) {
            return $original;
        }

        return 'The action failed and was not retried. Please check the request and try again.';
    }
}
